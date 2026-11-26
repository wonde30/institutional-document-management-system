<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccreditationEvidence;
use App\Models\Document;
use App\Models\DocumentMovement;
use App\Models\OrganizationUnit;
use Database\Seeders\LandingPageDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicLandingApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(LandingPageDemoSeeder::class);
    }

    public function test_landing_summary_endpoint_returns_accurate_aggregates_without_sensitive_data(): void
    {
        $response = $this->getJson('/api/v1/public/landing/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'published_documents',
                'active_offices',
                'correspondence_routed',
                'accreditation_evidence_items',
                'metrics_updated_at',
            ],
        ]);

        $data = $response->json('data');

        // Confirm metrics match real DB counts
        $expectedDocs = Document::where('lifecycle', 'active')->count();
        $expectedOffices = OrganizationUnit::where('active', true)->count();
        $expectedEvidence = AccreditationEvidence::count();

        $this->assertSame($expectedDocs, $data['published_documents']);
        $this->assertSame($expectedOffices, $data['active_offices']);
        $this->assertSame($expectedEvidence, $data['accreditation_evidence_items']);
        $this->assertGreaterThan(0, $data['published_documents']);
        $this->assertGreaterThan(0, $data['active_offices']);

        // Assert strictly NO sensitive leakage
        $responseContent = $response->getContent();
        $this->assertStringNotContainsString('password', (string) $responseContent);
        $this->assertStringNotContainsString('email', (string) $responseContent);
        $this->assertStringNotContainsString('users', (string) $responseContent);
        $this->assertStringNotContainsString('storage_key', (string) $responseContent);
    }

    public function test_landing_summary_caches_results(): void
    {
        $first = $this->getJson('/api/v1/public/landing/summary');
        $first->assertStatus(200);

        // Verify cache was populated
        $this->assertTrue(Cache::has('public_landing_summary'));

        $cachedData = Cache::get('public_landing_summary');
        $this->assertIsArray($cachedData);
        $this->assertSame($first->json('data.published_documents'), $cachedData['published_documents']);

        // Second request returns cached data
        $second = $this->getJson('/api/v1/public/landing/summary');
        $second->assertStatus(200);
        $this->assertSame($first->json('data.metrics_updated_at'), $second->json('data.metrics_updated_at'));
    }

    public function test_landing_modules_endpoint_returns_ordered_core_modules(): void
    {
        $response = $this->getJson('/api/v1/public/landing/modules');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'key',
                    'title',
                    'description',
                    'icon',
                    'route',
                    'order',
                    'highlights',
                ],
            ],
        ]);

        $modules = $response->json('data');
        $this->assertGreaterThanOrEqual(6, count($modules));

        // Assert modules contain key functional areas
        $keys = array_column($modules, 'key');
        $this->assertContains('repository', $keys);
        $this->assertContains('correspondence', $keys);
        $this->assertContains('routing', $keys);
        $this->assertContains('archive', $keys);
        $this->assertContains('accreditation', $keys);
        $this->assertContains('signatures', $keys);

        // Check ascending order
        $orders = array_column($modules, 'order');
        $sortedOrders = $orders;
        sort($sortedOrders);
        $this->assertSame($sortedOrders, $orders);
    }

    public function test_landing_accreditation_endpoint_returns_coverage_without_document_payloads(): void
    {
        $response = $this->getJson('/api/v1/public/landing/accreditation');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'framework' => [
                    'name',
                    'code',
                ],
                'cycle' => [
                    'name',
                    'starts_on',
                    'ends_on',
                ],
                'summary' => [
                    'total_standards',
                    'verified_standards',
                    'in_review_standards',
                    'total_evidence_items',
                    'coverage_percentage',
                ],
                'standards' => [
                    '*' => [
                        'code',
                        'raw_code',
                        'title',
                        'evidence_count',
                        'status',
                        'status_label',
                    ],
                ],
            ],
        ]);

        $data = $response->json('data');
        $this->assertSame('HERQA', $data['framework']['code']);
        $this->assertSame(10, $data['summary']['total_standards']);
        $this->assertGreaterThan(0, $data['summary']['total_evidence_items']);
        $this->assertGreaterThan(0, $data['summary']['coverage_percentage']);

        // Check standards formatting
        $standards = $data['standards'];
        $this->assertCount(10, $standards);
        $this->assertStringStartsWith('HERQA-', $standards[0]['code']);

        // Verify no document text or private keys leaked
        $raw = $response->getContent();
        $this->assertStringNotContainsString('file_objects', (string) $raw);
        $this->assertStringNotContainsString('storage_disk', (string) $raw);
        $this->assertStringNotContainsString('sha256', (string) $raw);
    }

    public function test_landing_announcements_endpoint_returns_public_announcements(): void
    {
        $response = $this->getJson('/api/v1/public/landing/announcements');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'summary',
                    'category',
                    'published_at',
                    'badge',
                    'pinned',
                ],
            ],
        ]);

        $announcements = $response->json('data');
        $this->assertNotEmpty($announcements);
        $this->assertTrue(collect($announcements)->contains('pinned', true));
    }

    public function test_legal_endpoint_returns_privacy_and_terms_content(): void
    {
        // Privacy
        $privacyResponse = $this->getJson('/api/v1/public/legal/privacy');
        $privacyResponse->assertStatus(200);
        $privacyResponse->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'type',
                'title',
                'subtitle',
                'effective_date',
                'last_updated',
                'statutory_basis',
                'sections' => [
                    '*' => [
                        'heading',
                        'content',
                    ],
                ],
            ],
        ]);
        $this->assertSame('privacy', $privacyResponse->json('data.type'));
        $this->assertStringContainsString('Proclamation No. 1152/2019', $privacyResponse->json('data.statutory_basis'));

        // Terms
        $termsResponse = $this->getJson('/api/v1/public/legal/terms');
        $termsResponse->assertStatus(200);
        $this->assertSame('terms', $termsResponse->json('data.type'));
        $this->assertStringContainsString('Proclamation No. 1072/2018', $termsResponse->json('data.statutory_basis'));

        // Invalid legal type returns 404
        $invalidResponse = $this->getJson('/api/v1/public/legal/unknown-policy');
        $invalidResponse->assertStatus(404);
    }

    public function test_public_endpoints_are_unauthenticated(): void
    {
        // Must succeed without Sanctum authentication cookie or Authorization header
        $this->getJson('/api/v1/public/landing/summary')->assertStatus(200);
        $this->getJson('/api/v1/public/landing/modules')->assertStatus(200);
        $this->getJson('/api/v1/public/landing/accreditation')->assertStatus(200);
        $this->getJson('/api/v1/public/landing/announcements')->assertStatus(200);
        $this->getJson('/api/v1/public/legal/privacy')->assertStatus(200);
        $this->getJson('/api/v1/public/legal/terms')->assertStatus(200);
    }
}
