<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Account recovery and address verification.
 *
 * These flows are security sensitive: they are unauthenticated, they deliver
 * credentials by mail, and they change the credential that guards the
 * repository. The tests assert that they work, that they do not disclose
 * whether an address is registered, and that a completed recovery terminates
 * the sessions established with the previous credential.
 */
class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $email = 'recoverable@wu.edu.et'): User
    {
        return User::create([
            'name' => 'Recoverable Officer',
            'email' => $email,
            'password' => bcrypt('OriginalPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
    }

    public function test_recovery_link_is_dispatched_for_an_active_account(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'recoverable@wu.edu.et',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('success', true);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_recovery_response_does_not_disclose_whether_an_address_is_registered(): void
    {
        Notification::fake();

        $this->makeUser();

        $known = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'recoverable@wu.edu.et',
        ]);

        $unknown = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown.person@wu.edu.et',
        ]);

        $unknown->assertStatus(202);
        $this->assertSame(
            $known->json('message'),
            $unknown->json('message'),
            'The recovery response must be identical for a registered and an unregistered address.'
        );

        // Exactly one notice was dispatched: the one for the registered
        // account. The unregistered address must not trigger a delivery.
        Notification::assertCount(1);
    }

    public function test_recovery_is_not_dispatched_for_a_suspended_account(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $user->update(['status' => 'locked']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'recoverable@wu.edu.et',
        ])->assertStatus(202);

        Notification::assertNothingSent();
    }

    public function test_password_is_reset_and_previous_sessions_are_terminated(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $originalAuthzVersion = (int) $user->authz_version;

        // Establish a session that was authenticated with the old credential.
        DB::table('sessions')->insert([
            'id' => 'stale-session-token',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/Test',
            'payload' => base64_encode(serialize(['user_id' => $user->id])),
            'last_activity' => time(),
        ]);

        $token = app('auth.password.broker')->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'recoverable@wu.edu.et',
            'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $user->refresh();

        $this->assertTrue(Hash::check('ReplacementPassword123!', $user->password));
        $this->assertFalse(Hash::check('OriginalPassword123!', $user->password));
        $this->assertSame($originalAuthzVersion + 1, (int) $user->authz_version);

        // The session established with the previous credential is terminated.
        $this->assertDatabaseMissing('sessions', ['id' => 'stale-session-token']);

        // The recovery token is single use.
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'recoverable@wu.edu.et',
        ]);
    }

    public function test_an_invalid_recovery_token_is_rejected(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'not-a-valid-token',
            'email' => 'recoverable@wu.edu.et',
            'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_a_recovered_credential_can_be_used_to_sign_in(): void
    {
        Notification::fake();

        $user = $this->makeUser();
        $token = app('auth.password.broker')->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'recoverable@wu.edu.et',
            'password' => 'ReplacementPassword123!',
            'password_confirmation' => 'ReplacementPassword123!',
        ])->assertStatus(200);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'recoverable@wu.edu.et',
            'password' => 'ReplacementPassword123!',
        ])->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_address_confirmation_requires_the_matching_address_hash(): void
    {
        $user = $this->makeUser();

        $wrongHash = $this->getJson("/api/v1/auth/verify-email/{$user->id}/".sha1('someone.else@wu.edu.et'));
        $wrongHash->assertStatus(403);
        $wrongHash->assertJsonPath('success', false);

        $this->assertNull($user->fresh()->email_verified_at);

        $correctHash = $this->getJson("/api/v1/auth/verify-email/{$user->id}/".sha1($user->email));
        $correctHash->assertStatus(200);
        $correctHash->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_address_confirmation_for_an_unknown_account_is_not_found(): void
    {
        $this->getJson('/api/v1/auth/verify-email/999999/'.sha1('nobody@wu.edu.et'))
            ->assertStatus(404);
    }

    public function test_verification_notice_requires_an_authenticated_session(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(401);

        $user = $this->makeUser();
        $this->authenticateAs($user);

        $this->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(202);
    }
}
