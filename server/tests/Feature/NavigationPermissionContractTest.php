<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\NavigationController;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Contract test for the authorization surface shared by the API, the dynamic
 * navigation catalog and the front-end router.
 *
 * The same capability is declared in three places:
 *   1. the API route middleware  (server/routes/api.php)
 *   2. the navigation catalog     (NavigationController)
 *   3. the front-end route meta  (client/src/router/index.ts)
 *
 * These three must agree. When they drift, the client either hides a surface
 * the server would allow, or advertises a surface the server refuses. This test
 * is the single guard against that class of defect.
 */
class NavigationPermissionContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Navigation surface -> the API route that serves it.
     *
     * @var array<string, string>
     */
    protected const NAVIGATION_TO_API = [
        '/documents' => 'api/v1/documents',
        '/letters/incoming' => 'api/v1/correspondences',
        '/letters/outgoing' => 'api/v1/correspondences',
        '/memoranda' => 'api/v1/correspondences',
        '/routing' => 'api/v1/correspondences',
        '/tasks' => 'api/v1/workflows/tasks',
        '/research' => 'api/v1/research/outputs',
        '/staff' => 'api/v1/staff/records',
        '/archive' => 'api/v1/archive/collections',
        '/accreditation' => 'api/v1/accreditation/evidence',
        '/projects' => 'api/v1/projects',
        '/signatures' => 'api/v1/signatures',
        '/reports' => 'api/v1/reports/summary',
        '/admin/users' => 'api/v1/users',
        '/admin/roles' => 'api/v1/roles',
        '/admin/organization' => 'api/v1/organization-units/tree',
        '/admin/office-procedures' => 'api/v1/procedures',
        '/admin/audit' => 'api/v1/audit-events',
        '/admin/operations' => 'api/v1/operations/status',
        // The settings surface is served by the system configuration endpoint.
        '/admin/settings' => 'api/v1/settings',
        // The data operations surface is served by the dataset catalogue.
        '/exports' => 'api/v1/data/datasets',
    ];

    /**
     * Front-end route path -> the API route that serves it.
     *
     * @var array<string, string>
     */
    protected const ROUTER_TO_API = [
        '/documents' => 'api/v1/documents',
        '/documents/create' => 'api/v1/documents',
        '/documents/:id' => 'api/v1/documents',
        '/templates' => 'api/v1/templates',
        '/correspondence' => 'api/v1/correspondences',
        '/letters/incoming' => 'api/v1/correspondences',
        '/letters/outgoing' => 'api/v1/correspondences',
        '/memoranda' => 'api/v1/correspondences',
        '/routing' => 'api/v1/correspondences',
        '/tasks' => 'api/v1/workflows/tasks',
        '/research' => 'api/v1/research/outputs',
        '/staff' => 'api/v1/staff/records',
        '/archive' => 'api/v1/archive/collections',
        '/accreditation' => 'api/v1/accreditation/evidence',
        '/projects' => 'api/v1/projects',
        '/signatures' => 'api/v1/signatures',
        '/reports' => 'api/v1/reports/summary',
        '/search' => 'api/v1/search/documents',
        '/admin/users' => 'api/v1/users',
        '/admin/roles' => 'api/v1/roles',
        '/admin/organization' => 'api/v1/organization-units/tree',
        '/admin/office-procedures' => 'api/v1/procedures',
        '/admin/audit' => 'api/v1/audit-events',
        '/admin/operations' => 'api/v1/operations/status',
        '/admin/settings' => 'api/v1/settings',
        '/exports' => 'api/v1/data/datasets',
    ];

    /**
     * Resolve the capabilities declared by the API routes serving a URI.
     *
     * Every method bound to the URI is inspected, because a client surface
     * usually exercises several operations of the same resource collection
     * (for example listing templates and creating a template).
     *
     * The raw middleware declaration is inspected instead of the gathered
     * middleware, because gathering resolves the "permission" alias into the
     * middleware class and would hide the declared capability.
     *
     * @return list<string>
     */
    protected function apiRoutePermissions(string $uri): array
    {
        $capabilities = [];

        foreach (Route::getRoutes() as $route) {
            if ($route->uri() !== $uri) {
                continue;
            }

            foreach ($route->middleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'permission:')) {
                    foreach (explode('|', substr($middleware, strlen('permission:'))) as $capability) {
                        $capability = trim($capability);
                        if ($capability !== '') {
                            $capabilities[] = $capability;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Read the navigation catalog without issuing an HTTP request.
     *
     * @return list<array<string, mixed>>
     */
    protected function navigationCatalog(): array
    {
        $controller = new NavigationController();
        $method = new \ReflectionMethod($controller, 'getMasterNavigationCatalog');
        $method->setAccessible(true);

        return $method->invoke($controller);
    }

    /**
     * Parse the front-end router declaration.
     *
     * @return array<string, list<string>>
     */
    protected function frontendRouterPermissions(): array
    {
        $routerFile = dirname(__DIR__, 3).'/client/src/router/index.ts';

        if (! is_readable($routerFile)) {
            $this->markTestSkipped('Front-end router declaration is not available.');
        }

        $source = (string) file_get_contents($routerFile);

        preg_match_all("/path:\s*'([^']+)'/s", $source, $pathMatches, PREG_OFFSET_CAPTURE);

        $permissions = [];

        foreach ($pathMatches[1] as $index => [$path, $offset]) {
            $segmentEnd = isset($pathMatches[1][$index + 1])
                ? $pathMatches[1][$index + 1][1]
                : strlen($source);

            $segment = substr($source, (int) $offset, $segmentEnd - (int) $offset);

            $declared = [];

            if (preg_match("/permission:\s*\[([^\]]*)\]/s", $segment, $arrayMatch)) {
                preg_match_all("/'([^']+)'/", $arrayMatch[1], $items);
                $declared = $items[1];
            } elseif (preg_match("/permission:\s*'([^']+)'/s", $segment, $stringMatch)) {
                $declared = [$stringMatch[1]];
            }

            $permissions[$path] = $declared;
        }

        return $permissions;
    }

    /**
     * The navigation catalog must not advertise a capability that the serving
     * API route does not require.
     */
    public function test_navigation_catalog_agrees_with_the_api_route_contract(): void
    {
        foreach ($this->navigationCatalog() as $section) {
            foreach ($section['items'] as $item) {
                $to = $item['to'];

                if (! isset(self::NAVIGATION_TO_API[$to])) {
                    continue;
                }

                $required = $this->apiRoutePermissions(self::NAVIGATION_TO_API[$to]);

                $this->assertNotEmpty(
                    $required,
                    "The API route serving '{$to}' declares no capability, so the navigation item cannot be permission filtered."
                );

                $declared = (array) $item['permission'];

                if (empty($declared)) {
                    $this->fail("Navigation item '{$to}' declares no capability but is served by a protected API route.");
                }

                foreach ($declared as $capability) {
                    $this->assertContains(
                        $capability,
                        $required,
                        "Navigation item '{$to}' advertises '{$capability}', which the API route ".(self::NAVIGATION_TO_API[$to])." does not require."
                    );
                }
            }
        }
    }

    /**
     * Parse the front-end navigation fallback catalog.
     *
     * The sidebar falls back to a static catalog while the dynamic catalog is
     * being fetched, so the fallback must declare the same capabilities as the
     * dynamic catalog and the API contract.
     *
     * @return array<string, list<string>>
     */
    protected function frontendNavigationPermissions(): array
    {
        $navigationFile = dirname(__DIR__, 3).'/client/src/config/navigation.ts';

        if (! is_readable($navigationFile)) {
            $this->markTestSkipped('Front-end navigation catalog is not available.');
        }

        $source = (string) file_get_contents($navigationFile);

        preg_match_all("/to:\s*'([^']+)'/s", $source, $pathMatches, PREG_OFFSET_CAPTURE);

        $permissions = [];

        foreach ($pathMatches[1] as $index => [$path, $offset]) {
            $segmentEnd = isset($pathMatches[1][$index + 1])
                ? $pathMatches[1][$index + 1][1]
                : strlen($source);

            $segment = substr($source, (int) $offset, $segmentEnd - (int) $offset);

            $declared = [];

            if (preg_match("/permission:\s*\[([^\]]*)\]/s", $segment, $arrayMatch)) {
                preg_match_all("/'([^']+)'/", $arrayMatch[1], $items);
                $declared = $items[1];
            } elseif (preg_match("/permission:\s*'([^']+)'/s", $segment, $stringMatch)) {
                $declared = [$stringMatch[1]];
            }

            $permissions[$path] = $declared;
        }

        return $permissions;
    }

    /**
     * The front-end router must require a capability that the serving API
     * route also requires, otherwise the client either blocks a permitted
     * surface or admits a refused one.
     */
    public function test_frontend_router_agrees_with_the_api_route_contract(): void
    {
        $router = $this->frontendRouterPermissions();

        foreach (self::ROUTER_TO_API as $path => $uri) {
            $this->assertArrayHasKey(
                $path,
                $router,
                "The front-end router no longer declares the route '{$path}'; the permission contract map must be updated."
            );

            $declared = $router[$path];
            $required = $this->apiRoutePermissions($uri);

            if (empty($declared)) {
                // The route intentionally carries no capability; the API route
                // must therefore be open to every authenticated account.
                $this->assertEmpty(
                    $required,
                    "The front-end route '{$path}' declares no capability but '{$uri}' requires ".(implode('|', $required)).'.'
                );
                continue;
            }

            foreach ($declared as $capability) {
                $this->assertContains(
                    $capability,
                    $required,
                    "The front-end route '{$path}' requires '{$capability}', which the API route '{$uri}' does not require."
                );
            }
        }
    }

    /**
     * Every visible navigation item must be backed by a capability the server
     * published for the active organizational context.
     *
     * This is the coherence property between the published capability set and
     * the menu: the interface must never offer an action the server has not
     * granted in the current context.
     */
    public function test_visible_navigation_is_backed_by_the_published_capability_set(): void
    {
        // The roles and the capability registry are seeded by the baseline
        // reference vocabulary.
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $user = User::create([
            'name' => 'Contract Officer',
            'email' => 'contract.officer@wu.edu.et',
            'password' => bcrypt('StrongPassword123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->assignRoleToUser($user, 'records_officer', 'global', null);

        $this->authenticateAs($user);

        $me = $this->getJson('/api/v1/auth/me');
        $me->assertStatus(200);
        $published = $me->json('user.permissions');

        $navigation = $this->getJson('/api/v1/navigation');
        $navigation->assertStatus(200);

        foreach ($navigation->json('data') as $section) {
            foreach ($section['items'] as $item) {
                foreach ((array) $item['permission'] as $capability) {
                    $this->assertContains(
                        $capability,
                        $published,
                        "The navigation item '{$item['to']}' is offered but '{$capability}' is not published for the active context."
                    );
                }
            }
        }
    }

    /**
     * Every capability required by a route must exist in the permission
     * registry.
     *
     * A capability that is not registered can never be granted to a role, so
     * the route is unreachable for every account, including the super
     * administrator. This guard prevents that class of defect.
     */
    public function test_every_route_capability_exists_in_the_permission_registry(): void
    {
        // The registry is seeded by the baseline reference vocabulary.
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $registered = Permission::pluck('code')->flip();

        foreach (Route::getRoutes() as $route) {
            foreach ($route->middleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'permission:')) {
                    continue;
                }

                foreach (explode('|', substr($middleware, strlen('permission:'))) as $capability) {
                    $capability = trim($capability);
                    if ($capability === '') {
                        continue;
                    }

                    $this->assertTrue(
                        $registered->has($capability),
                        "The route '{$route->uri()}' requires '{$capability}', which is not present in the permission registry. The endpoint would be unreachable for every account."
                    );
                }
            }
        }
    }

    /**
     * The static navigation fallback used by the sidebar must declare the
     * same capabilities as the dynamic catalog served by the API.
     *
     * The two catalogs are rendered by the same component, so a divergence
     * makes the menu change shape between the first paint and the moment the
     * dynamic catalog arrives.
     */
    public function test_static_navigation_fallback_agrees_with_the_dynamic_catalog(): void
    {
        $static = $this->frontendNavigationPermissions();

        $dynamic = [];
        foreach ($this->navigationCatalog() as $section) {
            foreach ($section['items'] as $item) {
                $dynamic[$item['to']] = (array) $item['permission'];
            }
        }

        foreach ($dynamic as $to => $capabilities) {
            $this->assertArrayHasKey(
                $to,
                $static,
                "The static navigation fallback does not declare the surface '{$to}' that the dynamic catalog serves."
            );

            sort($capabilities);
            $staticCapabilities = $static[$to];
            sort($staticCapabilities);

            $this->assertSame(
                $capabilities,
                $staticCapabilities,
                "The static navigation fallback declares a different capability set for '{$to}' than the dynamic catalog."
            );
        }
    }
}
