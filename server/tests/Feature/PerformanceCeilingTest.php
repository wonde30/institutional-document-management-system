<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceCeilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_metrics_query_count_ceiling(): void
    {
        $user = User::where('email', 'staff.tech@wu.edu.et')->first() ?? User::first();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard/metrics');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(30, $queryCount, "Dashboard metrics exceeded query count ceiling (was {$queryCount}, ceiling 30, baseline was 62-77)");
    }

    public function test_document_list_query_count_ceiling(): void
    {
        $user = User::where('email', 'staff.tech@wu.edu.et')->first() ?? User::first();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->actingAs($user)->getJson('/api/v1/documents?per_page=25');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(12, $queryCount, "Document list exceeded query count ceiling (was {$queryCount}, ceiling 12, baseline was 23)");
    }

    public function test_document_search_query_count_ceiling(): void
    {
        $user = User::where('email', 'staff.tech@wu.edu.et')->first() ?? User::first();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $response = $this->actingAs($user)->getJson('/api/v1/search/documents?q=Directive&per_page=25');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(12, $queryCount, "Document search exceeded query count ceiling (was {$queryCount}, ceiling 12, baseline was 22)");
    }
}
