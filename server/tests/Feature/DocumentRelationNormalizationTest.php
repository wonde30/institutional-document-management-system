<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRelation;
use App\Models\DocumentRelationType;
use App\Models\OrganizationUnit;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentRelationNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected OrganizationUnit $unit;
    protected DocumentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first();
        $this->unit = OrganizationUnit::first();
        $this->category = DocumentCategory::first();
    }

    private function createDocument(string $title): Document
    {
        return Document::create([
            'reference_number' => 'DOC-' . uniqid(),
            'title' => $title,
            'summary' => 'Summary for ' . $title,
            'category_id' => $this->category->id,
            'confidentiality_id' => \App\Models\ConfidentialityLevel::first()->id,
            'owner_unit_id' => $this->unit->id,
            'custodian_id' => $this->adminUser->id,
            'created_by' => $this->adminUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'lifecycle' => 'active',
            'lock_version' => 1,
        ]);
    }

    /**
     * Executes the exact normalization algorithm from migration 2026_11_10_000004.
     */
    private function runNormalization(): void
    {
        DB::transaction(function () {
            $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
            $legacy = DB::table('document_relation_types')->where('code', 'reply_to')->first();

            if ($legacy && ! $canonical) {
                DB::table('document_relation_types')->where('id', $legacy->id)->update([
                    'code' => 'responds_to',
                    'name' => 'Responds to',
                    'description' => 'Official response to a source document',
                    'active' => 1,
                ]);
            } elseif ($legacy && $canonical) {
                $legacyRelations = DB::table('document_relations')->where('relation_type_id', $legacy->id)->get();
                foreach ($legacyRelations as $rel) {
                    $hasCanonical = DB::table('document_relations')
                        ->where('source_id', $rel->source_id)
                        ->where('target_id', $rel->target_id)
                        ->where('relation_type_id', $canonical->id)
                        ->exists();

                    if ($hasCanonical) {
                        DB::table('document_relations')
                            ->where('source_id', $rel->source_id)
                            ->where('target_id', $rel->target_id)
                            ->where('relation_type_id', $legacy->id)
                            ->delete();
                    } else {
                        DB::table('document_relations')
                            ->where('source_id', $rel->source_id)
                            ->where('target_id', $rel->target_id)
                            ->where('relation_type_id', $legacy->id)
                            ->update(['relation_type_id' => $canonical->id]);
                    }
                }

                $remaining = DB::table('document_relations')->where('relation_type_id', $legacy->id)->count();
                if ($remaining > 0) {
                    throw new \RuntimeException("Integrity error: {$remaining} document_relations still reference legacy reply_to.");
                }

                DB::table('document_relation_types')->where('id', $legacy->id)->delete();
            } elseif (! $legacy && ! $canonical) {
                DB::table('document_relation_types')->insert([
                    'code' => 'responds_to',
                    'name' => 'Responds to',
                    'description' => 'Official response to a source document',
                    'active' => 1,
                ]);
            }
        });
    }

    public function test_normalization_when_only_legacy_code_exists(): void
    {
        // Setup: remove canonical 'responds_to' and ensure only 'reply_to' exists
        DB::table('document_relations')->delete();
        DB::table('document_relation_types')->where('code', 'responds_to')->delete();
        DB::table('document_relation_types')->where('code', 'reply_to')->delete();

        $legacyId = DB::table('document_relation_types')->insertGetId([
            'code' => 'reply_to',
            'name' => 'Reply to',
            'description' => 'Legacy response type',
            'active' => 1,
        ]);

        $docA = $this->createDocument('Directive A');
        $docB = $this->createDocument('Response B');
        DB::table('document_relations')->insert([
            'source_id' => $docB->id,
            'target_id' => $docA->id,
            'relation_type_id' => $legacyId,
        ]);

        $this->runNormalization();

        // Verify: 'responds_to' exists with the exact same ID, 'reply_to' does not exist
        $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
        $this->assertNotNull($canonical);
        $this->assertEquals($legacyId, $canonical->id);
        $this->assertNull(DB::table('document_relation_types')->where('code', 'reply_to')->first());

        // Verify relationship points to canonical
        $rel = DB::table('document_relations')->where('source_id', $docB->id)->first();
        $this->assertEquals($canonical->id, $rel->relation_type_id);
    }

    public function test_normalization_when_only_canonical_code_exists(): void
    {
        // Baseline seeder creates only 'responds_to'
        $this->assertNotNull(DB::table('document_relation_types')->where('code', 'responds_to')->first());
        $this->assertNull(DB::table('document_relation_types')->where('code', 'reply_to')->first());

        $docA = $this->createDocument('Doc 1');
        $docB = $this->createDocument('Doc 2');
        $canonicalId = DB::table('document_relation_types')->where('code', 'responds_to')->value('id');

        DB::table('document_relations')->insert([
            'source_id' => $docB->id,
            'target_id' => $docA->id,
            'relation_type_id' => $canonicalId,
        ]);

        $this->runNormalization();

        $this->assertNotNull(DB::table('document_relation_types')->where('code', 'responds_to')->first());
        $this->assertNull(DB::table('document_relation_types')->where('code', 'reply_to')->first());
        $this->assertDatabaseHas('document_relations', [
            'source_id' => $docB->id,
            'target_id' => $docA->id,
            'relation_type_id' => $canonicalId,
        ]);
    }

    public function test_normalization_when_both_codes_exist_with_existing_relationships(): void
    {
        // Setup: canonical exists, create legacy row and link relations to both
        $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
        $legacyId = DB::table('document_relation_types')->insertGetId([
            'code' => 'reply_to',
            'name' => 'Reply to',
            'description' => 'Legacy duplicate',
            'active' => 1,
        ]);

        $doc1 = $this->createDocument('Letter 1');
        $doc2 = $this->createDocument('Reply 2');
        $doc3 = $this->createDocument('Letter 3');
        $doc4 = $this->createDocument('Reply 4');

        // Rel 1 points to legacy
        DB::table('document_relations')->insert([
            'source_id' => $doc2->id,
            'target_id' => $doc1->id,
            'relation_type_id' => $legacyId,
        ]);

        // Rel 2 points to canonical
        DB::table('document_relations')->insert([
            'source_id' => $doc4->id,
            'target_id' => $doc3->id,
            'relation_type_id' => $canonical->id,
        ]);

        $this->runNormalization();

        // Verify: legacy row is deleted
        $this->assertNull(DB::table('document_relation_types')->where('code', 'reply_to')->first());
        $this->assertNotNull(DB::table('document_relation_types')->where('code', 'responds_to')->first());

        // Verify: both relations now point to canonical
        $rel1 = DB::table('document_relations')->where('source_id', $doc2->id)->first();
        $this->assertEquals($canonical->id, $rel1->relation_type_id);

        $rel2 = DB::table('document_relations')->where('source_id', $doc4->id)->first();
        $this->assertEquals($canonical->id, $rel2->relation_type_id);
    }

    public function test_normalization_when_both_codes_exist_without_legacy_relationships(): void
    {
        $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
        $legacyId = DB::table('document_relation_types')->insertGetId([
            'code' => 'reply_to',
            'name' => 'Reply to',
            'description' => 'Unused legacy row',
            'active' => 1,
        ]);

        $this->runNormalization();

        $this->assertNull(DB::table('document_relation_types')->where('code', 'reply_to')->first());
        $this->assertNotNull(DB::table('document_relation_types')->where('code', 'responds_to')->first());
    }

    public function test_normalization_idempotency_does_not_duplicate_or_damage_records(): void
    {
        $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
        $docA = $this->createDocument('Source Doc');
        $docB = $this->createDocument('Target Doc');

        DB::table('document_relations')->insert([
            'source_id' => $docA->id,
            'target_id' => $docB->id,
            'relation_type_id' => $canonical->id,
        ]);

        // Execute multiple times
        $this->runNormalization();
        $this->runNormalization();
        $this->runNormalization();

        $count = DB::table('document_relation_types')->where('code', 'responds_to')->count();
        $this->assertEquals(1, $count);
        $this->assertNull(DB::table('document_relation_types')->where('code', 'reply_to')->first());

        $rel = DB::table('document_relations')->where('source_id', $docA->id)->first();
        $this->assertEquals($canonical->id, $rel->relation_type_id);
    }
}
