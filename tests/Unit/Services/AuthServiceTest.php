<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\Enums\DevicePlatform;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\AccessToken;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Mockery;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = new AuthService;

        // Set up a fresh Passport password client for each test
        $clientRepo = app(ClientRepository::class);
        $client = $clientRepo->createPasswordGrantClient('Test Password Grant', 'users', true);
        config([
            'passport.password_client.id' => $client->id,
            'passport.password_client.secret' => $client->plainSecret,
        ]);

        // Set up a personal access client
        $clientRepo->createPersonalAccessGrantClient('Test Personal Access Client', 'users');

        $this->user = User::factory()->create([
            'email' => 'auth_tester@example.com',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    public function test_login_successful_with_device_tracking(): void
    {
        $deviceInfo = [
            'device_id' => 'device-123',
            'platform' => 'android',
            'os_version' => '13.0',
            'app_version' => '1.0.0',
            'device_name' => 'Pixel 7',
            'push_token' => 'fcm-push-token',
        ];

        $tokens = $this->authService->login('auth_tester@example.com', 'password', $deviceInfo);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertIsInt($tokens['expires_in']);

        // Assert device info is tracked in the database
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'device_id' => 'device-123',
            'platform' => DevicePlatform::Android->value,
            'os_version' => '13.0',
            'app_version' => '1.0.0',
            'device_name' => 'Pixel 7',
            'push_token' => 'fcm-push-token',
        ]);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->user->update(['is_active' => false]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Your account is inactive.');

        $this->authService->login('auth_tester@example.com', 'password');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $this->authService->login('auth_tester@example.com', 'wrong-password');
    }

    public function test_issue_token_for_user_creates_personal_access_token(): void
    {
        $tokens = $this->authService->issueTokenForUser($this->user);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertNull($tokens['refresh_token']);
        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertIsInt($tokens['expires_in']);
    }

    public function test_refresh_token_generates_new_access_token(): void
    {
        $tokens = $this->authService->login('auth_tester@example.com', 'password');

        $refreshed = $this->authService->refresh($tokens['refresh_token']);

        $this->assertArrayHasKey('access_token', $refreshed);
        $this->assertArrayHasKey('refresh_token', $refreshed);
        $this->assertNotSame($tokens['access_token'], $refreshed['access_token']);
    }

    public function test_logout_revokes_tokens_and_nullifies_push_tokens(): void
    {
        // 1. Log in to generate real tokens in database
        $tokens = $this->authService->login('auth_tester@example.com', 'password', [
            'device_id' => 'device-123',
            'platform' => 'ios',
            'push_token' => 'push-token-1',
        ]);

        // Verify tokens exist in database and are active
        $this->assertDatabaseHas('oauth_access_tokens', [
            'user_id' => $this->user->id,
            'revoked' => false,
        ]);

        // Get the generated token model
        $tokenModel = Token::query()->where('user_id', $this->user->id)->first();
        $this->assertNotNull($tokenModel);

        // Bind a real AccessToken instance to the user that returns the correct database token ID
        $accessToken = new AccessToken([
            'oauth_access_token_id' => $tokenModel->id,
        ]);

        // Inject the real Token model into the protected 'token' property of AccessToken
        $refProperty = new \ReflectionProperty(AccessToken::class, 'token');
        $refProperty->setAccessible(true);
        $refProperty->setValue($accessToken, $tokenModel);

        // We make a partial mock of the User model to mock the token() call
        $userMock = Mockery::mock($this->user)->makePartial();
        $userMock->shouldReceive('token')->andReturn($accessToken);

        // 2. Perform logout
        $this->authService->logout($userMock, 'device-123');

        // Assert Access Token is revoked
        $this->assertDatabaseHas('oauth_access_tokens', [
            'id' => $tokenModel->id,
            'revoked' => true,
        ]);

        // Assert Refresh Token is revoked
        $this->assertDatabaseHas('oauth_refresh_tokens', [
            'access_token_id' => $tokenModel->id,
            'revoked' => true,
        ]);

        // Assert device push token is nullified
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->user->id,
            'device_id' => 'device-123',
            'push_token' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
