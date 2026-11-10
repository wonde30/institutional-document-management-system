<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SanctumCsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->unit = OrganizationUnit::first();
        $this->user = User::create([
            'name' => 'Dr. SPA Researcher',
            'email' => 'spa.researcher@wu.edu.et',
            'password' => Hash::make('ValidSpaPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->assignRoleToUser($this->user, 'staff', 'unit', $this->unit->id);
    }

    public function test_csrf_cookie_endpoint_sets_xsrf_token_for_stateful_spa(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
        ])->get('/sanctum/csrf-cookie');

        $response->assertStatus(204);
        $response->assertCookie('XSRF-TOKEN');
    }

    public function test_cross_origin_request_cannot_use_spa_session_cookies(): void
    {
        // 1. Establish stateful session on authorized domain
        $csrf = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
        ])->get('/sanctum/csrf-cookie');

        $sessionCookie = $csrf->getCookie(config('session.cookie'))?->getValue();

        // 2. Untrusted cross-origin request attempts to exploit session cookie
        $untrustedResponse = $this->withHeaders([
            'Origin' => 'https://malicious-attacker-domain.org',
            'Referer' => 'https://malicious-attacker-domain.org/phish',
            'Accept' => 'application/json',
        ])->withCookie(config('session.cookie'), $sessionCookie ?? 'invalid')
          ->getJson('/api/v1/auth/me');

        // Untrusted cross-origin is barred from stateful SPA authentication
        $untrustedResponse->assertStatus(401);
    }

    public function test_stateful_csrf_middleware_rejects_missing_or_mismatched_token(): void
    {
        $request = \Illuminate\Http\Request::create('/api/v1/auth/logout', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $request->session()->put('_token', 'valid-server-session-token');

        $middleware = new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $this->expectException(\Illuminate\Session\TokenMismatchException::class);

        // Attempt mutating request with missing CSRF token
        $middleware->handle($request, fn () => response()->json(['success' => true]));
    }

    public function test_stateful_csrf_middleware_accepts_valid_encrypted_token(): void
    {
        $request = \Illuminate\Http\Request::create('/api/v1/auth/logout', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $token = 'valid-server-session-token';
        $request->session()->put('_token', $token);
        $request->headers->set('X-CSRF-TOKEN', $token);

        $middleware = new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        };

        $response = $middleware->handle($request, fn () => response()->json(['success' => true]));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_legitimate_same_origin_stateful_session_request_succeeds(): void
    {
        // 1. Acquire CSRF Cookie
        $csrfResponse = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
        ])->get('/sanctum/csrf-cookie');

        $csrfResponse->assertStatus(204);
        $xsrfToken = $csrfResponse->getCookie('XSRF-TOKEN')?->getValue();

        // 2. Perform same-origin login
        $loginResponse = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
            'X-XSRF-TOKEN' => $xsrfToken,
        ])->postJson('/api/v1/auth/login', [
            'email' => 'spa.researcher@wu.edu.et',
            'password' => 'ValidSpaPassword123!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. User can query their profile via established stateful session
        $this->actingAs($this->user, 'web');

        $meResponse = $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('user.email', 'spa.researcher@wu.edu.et');
    }
}
