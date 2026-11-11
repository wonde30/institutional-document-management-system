<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The API contract between the front end and the back end.
 *
 * The front end calls the API through the typed client in
 * client/src/api/client.ts. This test reads the calls that client declares and
 * asserts that each of them is a registered route, so a route that is renamed
 * or removed on one side cannot silently break the other.
 */
class FrontendApiContractTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The registered API routes as "METHOD /path" entries.
     *
     * A route parameter is normalised to a placeholder, so a route and a front
     * end call that differ only in the identifier of a path segment compare
     * as equal.
     *
     * @return list<string>
     */
    protected function registeredRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $uri = preg_replace('/\{[^}]+\}/', '__PARAM__', $route->uri());

                $routes[] = $method.' '.$uri;
            }
        }

        return $routes;
    }

    /**
     * The API calls the front end client declares.
     *
     * @return list<string>
     */
    protected function frontendApiCalls(): array
    {
        $client = dirname(__DIR__, 3).'/client/src/api/client.ts';

        if (! is_readable($client)) {
            $this->markTestSkipped('The front end API client is not available.');
        }

        $source = (string) file_get_contents($client);

        $calls = [];

        // A path literal that is passed to the HTTP client. The client
        // declares a type parameter, so it is tolerated.
        $generic = '(?:<[^<>]*(?:<[^<>]*>[^<>]*)*>)?';

        preg_match_all(
            "/apiClient\.(get|post|put|patch|delete){$generic}\(\s*'([^']+)'/i",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $calls[] = strtoupper($match[1]).' '.$match[2];
        }

        // A path that is built with a template literal.
        preg_match_all(
            '/apiClient\.(get|post|put|patch|delete)'.$generic.'\(\s*`([^`]+)`/i',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $calls[] = strtoupper($match[1]).' '.$match[2];
        }

        return array_values(array_unique($calls));
    }

    public function test_every_frontend_api_call_is_a_registered_route(): void
    {
        $registered = $this->registeredRoutes();
        $calls = $this->frontendApiCalls();

        $this->assertNotEmpty($calls, 'No front end API call was detected.');

        foreach ($calls as $call) {
            [$method, $path] = array_pad(explode(' ', $call, 2), 2, '');

            // The front end template literal and the route parameter are the
            // same placeholder once normalised.
            $normalisedPath = preg_replace('/\$\{[^}]+\}/', '__PARAM__', $path);

            // The client resolves its paths against the API base URL, and the
            // route table carries the same prefix.
            $this->assertContains(
                $method.' api/v1'.$normalisedPath,
                $registered,
                "The front end calls '{$call}', which is not a registered route."
            );
        }
    }
}
