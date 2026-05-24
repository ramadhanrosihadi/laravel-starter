<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_send_verification_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/email/send-verification')
            ->assertUnauthorized();
    }

    public function test_send_verification_sends_email_to_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/email/send-verification')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Verification link sent successfully.']);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_send_verification_returns_already_verified_message(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/email/send-verification')
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Email already verified.']);

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verify_email_via_get_request_with_valid_hash_succeeds(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Email verified successfully.']);

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_verify_email_via_post_request_with_valid_hash_succeeds(): void
    {
        $user = User::factory()->unverified()->create();

        $expires = now()->addMinutes(60)->timestamp;
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $parsedUrl = parse_url($url);
        parse_str($parsedUrl['query'] ?? '', $queryParams);
        $signature = $queryParams['signature'] ?? '';

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/email/verify', [
                'id' => $user->id,
                'hash' => sha1($user->email),
                'expires' => $expires,
                'signature' => $signature,
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Email verified successfully.']);

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_verify_email_with_invalid_hash_fails(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => 'wrong-hash',
            ]
        );

        $this->getJson($url)
            ->assertStatus(400)
            ->assertJson(['success' => false, 'code' => 'INVALID_VERIFICATION_LINK']);
    }

    public function test_verify_email_with_expired_signature_fails(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(1),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $this->getJson($url)
            ->assertStatus(400)
            ->assertJson(['success' => false, 'code' => 'INVALID_VERIFICATION_LINK']);
    }
}
