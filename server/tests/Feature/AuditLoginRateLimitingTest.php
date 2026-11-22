<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuditLoginRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
    }

    public function test_login_endpoint_enforces_rate_limiting_after_excessive_attempts(): void
    {
        $user = User::where('email', 'admin@wu.edu.et')->first();
        $email = $user->email;

        // Perform 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'WrongPassword!',
            ]);
            $response->assertStatus(422);
        }

        // 6th attempt should be blocked by rate limiter (429 Too Many Requests)
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_login_rate_limiter_clears_on_successful_authentication(): void
    {
        $email = 'admin@wu.edu.et';

        // Perform 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'WrongPassword!',
            ])->assertStatus(422);
        }

        // Successful login
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'Password123!',
        ]);
        $response->assertStatus(200);

        // Verify next attempt is not throttled
        $throttleKey = \Illuminate\Support\Str::transliterate(\Illuminate\Support\Str::lower($email) . '|127.0.0.1');
        $this->assertEquals(0, RateLimiter::attempts($throttleKey));
    }
}
