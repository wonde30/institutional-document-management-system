<?php

namespace Tests\Feature;

use App\Models\ArchiveCollection;
use App\Models\ArchiveEntry;
use App\Models\ConfidentialityLevel;
use App\Models\DispositionRequest;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\OrganizationUnit;
use App\Models\RetentionPolicy;
use App\Models\RetentionPolicyVersion;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use App\Services\Authorization\RouteScopeResolver;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuditRouteScopeResolverIdorTest extends TestCase
{
    use RefreshDatabase;

    protected RouteScopeResolver $resolver;
    protected User $admin;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected Document $docUnitA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);
        $this->resolver = new RouteScopeResolver();

        $this->admin = User::where('email', 'admin@wu.edu.et')->first();
        $units = OrganizationUnit::take(2)->get();
        $this->unitA = $units[0];
        $this->unitB = $units->count() > 1 ? $units[1] : OrganizationUnit::create([
            'parent_id' => $this->unitA->id,
            'unit_type_id' => $this->unitA->unit_type_id,
            'code' => 'UNIT_B_' . \Illuminate\Support\Str::random(4),
            'name' => 'Unit B',
            'active' => 1,
        ]);

        $category = DocumentCategory::first();
        $conf = ConfidentialityLevel::where('code', 'internal')->first();

        $this->docUnitA = Document::create([
            'reference_number' => 'DOC-IDOR-TEST-001',
            'category_id' => $category->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->unitA->id,
            'title' => 'Unit A Confidential Record',
            'summary' => 'Summary',
            'created_by' => $this->admin->id,
            'lifecycle' => 'active',
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
        ]);
    }

    public function test_resolver_resolves_archive_entry_to_document(): void
    {
        $collection = ArchiveCollection::create(['code' => 'TEST_COLL', 'name' => 'Test Collection']);
        $policy = RetentionPolicy::create(['code' => 'TEST_POL', 'name' => 'Policy', 'active' => true]);
        $policyVersion = RetentionPolicyVersion::create([
            'policy_id' => $policy->id,
            'version_number' => 1,
            'duration_months' => 12,
            'trigger_code' => 'creation_date',
            'effective_from' => now()->toDateString(),
            'disposition_action' => 'destroy',
        ]);

        $entry = ArchiveEntry::create([
            'document_id' => $this->docUnitA->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
            'archived_by' => $this->admin->id,
            'archived_at' => now(),
        ]);

        $request = Request::create("/api/v1/archive/entries/{$entry->id}/disposal-eligibility", 'GET');
        $request->setRouteResolver(function () use ($request, $entry) {
            $route = new \Illuminate\Routing\Route('GET', 'api/v1/archive/entries/{id}/disposal-eligibility', []);
            $route->bind($request);
            $route->setParameter('id', (string) $entry->id);
            return $route;
        });

        $target = $this->resolver->resolve($request);

        $this->assertNotNull($target->document, 'RouteScopeResolver must resolve archive/entries/{id} to the owning document.');
        $this->assertEquals($this->docUnitA->id, $target->document->id);
    }

    public function test_resolver_resolves_disposition_request_to_document(): void
    {
        $collection = ArchiveCollection::create(['code' => 'TEST_COLL2', 'name' => 'Test Collection 2']);
        $policy = RetentionPolicy::create(['code' => 'TEST_POL2', 'name' => 'Policy 2', 'active' => true]);
        $policyVersion = RetentionPolicyVersion::create([
            'policy_id' => $policy->id,
            'version_number' => 1,
            'duration_months' => 12,
            'trigger_code' => 'creation_date',
            'effective_from' => now()->toDateString(),
            'disposition_action' => 'destroy',
        ]);

        $entry = ArchiveEntry::create([
            'document_id' => $this->docUnitA->id,
            'collection_id' => $collection->id,
            'retention_policy_version_id' => $policyVersion->id,
            'archived_by' => $this->admin->id,
            'archived_at' => now(),
        ]);

        $disp = DispositionRequest::create([
            'archive_entry_id' => $entry->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
            'requested_action' => 'destroy_digital',
            'justification' => 'Retention period elapsed.',
            'requested_at' => now(),
        ]);

        $request = Request::create("/api/v1/archive/disposition-requests/{$disp->id}/decide", 'POST');
        $request->setRouteResolver(function () use ($request, $disp) {
            $route = new \Illuminate\Routing\Route('POST', 'api/v1/archive/disposition-requests/{id}/decide', []);
            $route->bind($request);
            $route->setParameter('id', (string) $disp->id);
            return $route;
        });

        $target = $this->resolver->resolve($request);

        $this->assertNotNull($target->document, 'RouteScopeResolver must resolve archive/disposition-requests/{id} to the owning document.');
        $this->assertEquals($this->docUnitA->id, $target->document->id);
    }

    public function test_resolver_resolves_workflow_instance_to_document(): void
    {
        $version = DocumentVersion::create([
            'document_id' => $this->docUnitA->id,
            'version_number' => 1,
            'created_by' => $this->admin->id,
        ]);

        $wfDef = WorkflowDefinition::create(['code' => 'TEST_WF', 'name' => 'Test WF', 'active' => true]);
        $wfVer = WorkflowVersion::create([
            'definition_id' => $wfDef->id,
            'version_number' => 1,
            'status' => 'published',
            'created_by' => $this->admin->id,
        ]);

        $instance = WorkflowInstance::create([
            'workflow_version_id' => $wfVer->id,
            'document_version_id' => $version->id,
            'started_by' => $this->admin->id,
            'initiator_id' => $this->admin->id,
            'status' => 'running',
        ]);

        $request = Request::create("/api/v1/workflows/instances/{$instance->id}/cancel", 'POST');
        $request->setRouteResolver(function () use ($request, $instance) {
            $route = new \Illuminate\Routing\Route('POST', 'api/v1/workflows/instances/{id}/cancel', []);
            $route->bind($request);
            $route->setParameter('id', (string) $instance->id);
            return $route;
        });

        $target = $this->resolver->resolve($request);

        $this->assertNotNull($target->document, 'RouteScopeResolver must resolve workflows/instances/{id} to the owning document.');
        $this->assertEquals($this->docUnitA->id, $target->document->id);
    }
}
