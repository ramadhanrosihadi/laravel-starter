<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Passport clients for tests
        $client = app(ClientRepository::class)->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        // Setup Personal Access Client for tests requiring personal tokens
        $personalClient = app(ClientRepository::class)->createPersonalAccessGrantClient('Test Personal Access Client', 'users');
        config([
            'passport.personal_access_client.id' => $personalClient->id,
            'passport.personal_access_client.secret' => $personalClient->plainSecret,
        ]);

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_forgot_password_sends_reset_link_successfully(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'johndoe@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'johndoe@example.com',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'data']);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_fails_if_email_does_not_exist(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_forgot_password_fails_if_email_is_invalid(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Notification::assertNothingSent();
    }

    public function test_reset_password_succeeds_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        // Generate token
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'johndoe@example.com',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'data']);

        // Assert password has changed
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->refresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'johndoe@example.com',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Assert password did not change
        $this->assertTrue(Hash::check('OldPassword123!', $user->refresh()->password));
    }

    public function test_reset_password_fails_if_passwords_do_not_match(): void
    {
        $user = User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'johndoe@example.com',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Assert password did not change
        $this->assertTrue(Hash::check('OldPassword123!', $user->refresh()->password));
    }

    public function test_reset_password_revokes_existing_passport_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'johndoe@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        // Create dummy tokens for the user
        $user->createToken('TestToken1');
        $user->createToken('TestToken2');

        $this->assertCount(2, $user->tokens);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'johndoe@example.com',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertOk();

        // Check that all tokens have been revoked
        $user->refresh();
        foreach ($user->tokens as $t) {
            $this->assertTrue($t->revoked);
        }
    }
}
