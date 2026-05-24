<?php

namespace Tests\Unit\Services;

use App\Models\OtpCode;
use App\Services\OtpService;
use App\Services\Sms\SmsInterface;
use App\Support\Enums\OtpPurpose;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;

    private SmsInterface $smsMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->smsMock = Mockery::mock(SmsInterface::class);
        $this->otpService = new OtpService($this->smsMock);

        Cache::clear();
    }

    public function test_generate_otp_creates_hashed_record_and_sends_sms(): void
    {
        $phone = '08123456789';
        $purpose = OtpPurpose::Login;
        $ip = '127.0.0.1';

        $plainCode = null;
        $this->smsMock->shouldReceive('send')
            ->once()
            ->withArgs(function ($recipientPhone, $message) use ($phone, &$plainCode) {
                if ($recipientPhone !== $phone) {
                    return false;
                }
                if (preg_match('/Your verification code is: (\d+)\./', $message, $matches)) {
                    $plainCode = $matches[1];

                    return true;
                }

                return false;
            })
            ->andReturn(true);

        $otp = $this->otpService->generate($phone, $purpose, $ip);

        $this->assertInstanceOf(OtpCode::class, $otp);
        $this->assertSame($phone, $otp->phone);
        $this->assertSame($purpose, $otp->purpose);
        $this->assertSame($ip, $otp->ip_address);
        $this->assertNotNull($otp->expires_at);

        // Verify the database record has hashed code
        $dbOtp = OtpCode::find($otp->id);
        $this->assertNotNull($plainCode);
        $this->assertTrue(Hash::check($plainCode, $dbOtp->code));
    }

    public function test_generate_otp_deletes_previous_unused_otps_for_same_recipient_and_purpose(): void
    {
        $phone = '08123456789';
        $purpose = OtpPurpose::Login;

        // Pre-create two unused OTPs
        OtpCode::factory()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'used_at' => null,
        ]);
        OtpCode::factory()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'used_at' => null,
        ]);

        // Pre-create one USED OTP (should not be deleted)
        $usedOtp = OtpCode::factory()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'used_at' => now(),
        ]);

        // Pre-create an unused OTP for different purpose (should not be deleted)
        $otherPurposeOtp = OtpCode::factory()->create([
            'phone' => $phone,
            'purpose' => OtpPurpose::ResetPassword,
            'used_at' => null,
        ]);

        $this->smsMock->shouldReceive('send')->once()->andReturn(true);

        // Act
        $this->otpService->generate($phone, $purpose);

        // Assert: only the used one, other purpose, and the newly generated one should exist
        $this->assertSame(3, OtpCode::count());
        $this->assertDatabaseHas('otp_codes', ['id' => $usedOtp->id]);
        $this->assertDatabaseHas('otp_codes', ['id' => $otherPurposeOtp->id]);
    }

    public function test_generate_otp_enforces_rate_limiting(): void
    {
        $phone = '08123456789';
        $purpose = OtpPurpose::Login;

        $this->smsMock->shouldReceive('send')->times(3)->andReturn(true);

        // Generate 3 times (the max requests per window)
        $this->otpService->generate($phone, $purpose);
        $this->otpService->generate($phone, $purpose);
        $this->otpService->generate($phone, $purpose);

        // Expect Exception on the 4th call
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Too many OTP requests. Please wait before trying again.');

        $this->otpService->generate($phone, $purpose);
    }

    public function test_verify_valid_otp_returns_true_and_marks_used(): void
    {
        $phone = '08123456789';
        $plainCode = '123456';
        $purpose = OtpPurpose::Login;

        $otp = OtpCode::factory()->create([
            'phone' => $phone,
            'code' => Hash::make($plainCode),
            'purpose' => $purpose,
            'used_at' => null,
            'expires_at' => now()->addMinutes(5),
        ]);

        $result = $this->otpService->verify($phone, $plainCode, $purpose);

        $this->assertTrue($result);
        $this->assertNotNull($otp->refresh()->used_at);
    }

    public function test_verify_invalid_otp_returns_false(): void
    {
        $phone = '08123456789';
        $plainCode = '123456';
        $purpose = OtpPurpose::Login;

        OtpCode::factory()->create([
            'phone' => $phone,
            'code' => Hash::make($plainCode),
            'purpose' => $purpose,
            'used_at' => null,
            'expires_at' => now()->addMinutes(5),
        ]);

        // Wrong code
        $result = $this->otpService->verify($phone, '654321', $purpose);
        $this->assertFalse($result);

        // Wrong phone
        $result = $this->otpService->verify('08999999999', $plainCode, $purpose);
        $this->assertFalse($result);

        // Wrong purpose
        $result = $this->otpService->verify($phone, $plainCode, OtpPurpose::ResetPassword);
        $this->assertFalse($result);
    }

    public function test_verify_expired_otp_returns_false(): void
    {
        $phone = '08123456789';
        $plainCode = '123456';
        $purpose = OtpPurpose::Login;

        OtpCode::factory()->create([
            'phone' => $phone,
            'code' => Hash::make($plainCode),
            'purpose' => $purpose,
            'used_at' => null,
            'expires_at' => now()->subMinutes(1), // Already expired
        ]);

        $result = $this->otpService->verify($phone, $plainCode, $purpose);

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
