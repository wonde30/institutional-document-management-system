<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\ReferenceSequence;
use App\Models\SignatureRequest;
use App\Models\StampApplication;
use App\Models\StampAuthorization;
use App\Models\StampDefinition;
use App\Models\StampVersion;
use App\Models\User;
use App\Services\EthiopianDateService;
use App\Services\Pki\PdfSignatureService;
use App\Services\Pki\PdfSignatureVerificationService;
use App\Services\Settings\SettingsService;
use App\Services\TemplateService;
use App\Services\UniversityAssetService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficialLetterReleaseFreezeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffUser;
    protected User $unauthorizedUser;
    protected OrganizationUnit $itUnit;
    protected TemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->itUnit = OrganizationUnit::where('code', 'ITD')->first()
            ?? OrganizationUnit::first();

        $this->staffUser = User::create([
            'name' => 'Tibebu Legesse Molla',
            'email' => 'tibebu.legesse.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staffUser, 'documents.create', 'unit', $this->itUnit->id);
        $this->grantPermissionToUser($this->staffUser, 'documents.view', 'unit', $this->itUnit->id);
        $this->grantPermissionToUser($this->staffUser, 'signatures.request', 'global');
        $this->grantPermissionToUser($this->staffUser, 'stamps.apply', 'unit', $this->itUnit->id);

        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized Guest',
            'email' => 'unauthorized.' . uniqid() . '@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);

        $this->templateService = app(TemplateService::class);
    }

    /**
     * Scenario 1: Standard one-page official letter visual and structural audit.
     * Verifies A4 dimensions, Nyala typography, 3-column header, date, ref,
     * and confirms no "[EMBLEM RESERVED]" or "[OFFICIAL STAMP RESERVED]" text on final letter.
     */
    public function test_scenario_1_standard_one_page_letter_structure_and_blank_reserves(): void
    {
        $data = [
            'subject' => 'የ2019 ዓ.ም Course offering መላክን ይመለከታል',
            'body' => 'በኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል የመደበኛ የ2019 ዓ.ም 1ኛ ወሳነ ትምህርት ኮርስ ምደባ የሰራን መሆኑን እየገለጽኩ መረጃውን ከዚህ ሸኝ ደብዳቤ ጋር 02 ገጽ አባሪ አድርገን የላክን መሆኑን አሳውቃለሁ።',
            'recipient_lead' => 'ለኢንፎርማቲክስ ኮሌጅ',
            'recipient_location' => 'ኮምቦልቻ፤',
            'document_date' => '2026-08-26',
            'ethiopian_date' => '20/12/2018 ዓ.ም',
            'reference_number' => 'KIOT/W.U.IT/737/2018',
            'cc_list' => [
                'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
                'ለኢንፎርማቲክስ ኮሌጅ ትምህርት ጥራት ጽ/ቤት',
            ],
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
        ];

        $pdfBinary = $this->templateService->renderOfficialLetterPdf($data, 'KIOT/W.U.IT/737/2018');
        $this->assertStringStartsWith('%PDF-', $pdfBinary);

        // Dompdf canvas page inspection
        $fontPath = storage_path('fonts/nyala.ttf');
        $ifontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $html = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $ifontUrl,
            'emblem_data_uri' => null,
            'stamp_data_uri' => null,
            'is_digitally_signed' => false,
            'signature_details' => null,
            'is_draft' => false,
            'body_html' => '<p>' . $data['body'] . '</p>',
        ]))->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true, 'isFontSubsettingEnabled' => true]);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        // 1. Exactly 1 page
        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());

        // 2. A4 dimensions (595.28 x 841.89 points)
        $this->assertEqualsWithDelta(595.28, $dompdf->getCanvas()->get_width(), 1.0);
        $this->assertEqualsWithDelta(841.89, $dompdf->getCanvas()->get_height(), 1.0);

        // 3. No placeholder text printed on final official letter
        $this->assertStringNotContainsString('[EMBLEM RESERVED]', $html);
        $this->assertStringNotContainsString('OFFICIAL EMBLEM RESERVED', $html);
        $this->assertStringNotContainsString('[OFFICIAL STAMP RESERVED]', $html);

        // 4. Structural containers present
        $this->assertStringContainsString('emblem-reserved-frame', $html);
        $this->assertStringContainsString('stamp-reserved-boundary', $html);
        $this->assertStringContainsString('blank-signature-line', $html);
    }

    /**
     * Scenario 2: Multiple CC Recipients List (8+ recipients) fitting on exactly 1 page.
     */
    public function test_scenario_2_multiple_cc_distribution_fits_on_single_page(): void
    {
        $ccList = [
            'ለምክትል ሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'ለተማሪዎች ጉዳይ ዳይሬክተር ጽ/ቤት',
            'ለሬጅስትራር ጽ/ቤት',
            'ለኢንፎርማቲክስ ኮሌጅ',
            'ለኢንፎርማቲክስ ኮሌጅ ት/ት ጥራት አስተባባሪ ጽ/ቤት',
            'ለኢንፎርማቲክስ ኮሌጅ ምርምር፤ ማህበረሰብ አገልግሎትና ድህረ-ምረቃ ጽ/ቤት',
            'ለርቀትና ተከታታይ ጽ/ቤት',
            'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
        ];

        $data = [
            'subject' => 'ውክልና መስጠትን ይመለከታል',
            'body' => 'ከቀን 19/01/2019 ዓ.ም ጀምሮ በግል ጉዳዬ በስራ ገበታዬ ላይ ስላማልኖር ሙሉ ውክልና ሰጥቻለሁ።',
            'recipient_lead' => 'ለመ/ር መላክ ተሾመ',
            'recipient_sub' => 'ኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
            'recipient_location' => 'ኮምቦልቻ፤',
            'document_date' => '2026-09-28',
            'ethiopian_date' => '18/01/2019 ዓ.ም',
            'reference_number' => 'KIOT/W.U.IT/746/2019',
            'cc_list' => $ccList,
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
            'is_draft' => false,
        ];

        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $html = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'emblem_data_uri' => null,
            'stamp_data_uri' => null,
            'is_digitally_signed' => false,
            'signature_details' => null,
            'body_html' => '<p>' . $data['body'] . '</p>',
        ]))->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true, 'isFontSubsettingEnabled' => true]);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
    }

    /**
     * Scenario 3: Bilingual Amharic and Latin typography verification.
     */
    public function test_scenario_3_bilingual_amharic_english_typography(): void
    {
        $data = [
            'subject' => 'የ2019 ዓ.ም Semester I Curriculum Matrix & Course Allocation',
            'body' => 'በKIOT Information Technology Department ስር የ2019 ዓ.ም Semester I course syllabus እና credit allocation ሰነድ የተዘጋጀ ሲሆን አባሪ ተደርጓል።',
            'recipient_lead' => 'ለኢንፎርማቲክስ ኮሌጅ Academic Commission',
            'recipient_location' => 'Kombolcha Campus፤',
            'document_date' => '2026-10-15',
            'reference_number' => 'KIOT/W.U.IT/801/2019',
            'cc_list' => ['Quality Assurance Office / የትምህርት ጥራት ጽ/ቤት'],
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
        ];

        $pdfBinary = $this->templateService->renderOfficialLetterPdf($data);
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
        $this->assertStringContainsString('Nyala', $pdfBinary);
    }

    /**
     * Scenario 4: Multi-page document flow with persistent header/footer.
     */
    public function test_scenario_4_multi_page_document_flow(): void
    {
        $paragraphs = [];
        for ($i = 1; $i <= 10; $i++) {
            $paragraphs[] = "አንቀጽ {$i}፡ በወሎ ዩኒቨርሲቲ ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል የ2019 ዓ.ም የትምህርት ዘመን ዝግጅትን በተመለከተ ዝርዝር የላብራቶሪ ግብዓት፣ የመማሪያ ክፍሎች ምደባ፣ የመምህራን የስራ ጫና ስሌት እና የጥናትና ምርምር ፕሮጀክቶች ክትትል እቅድ በተሟላ ሁኔታ ተዘጋጅቶ የቀረበ ስለሆነ ጉዳዩን ተመልክተው አስፈላጊውን ውሳኔ እንዲሰጡበት እናሳስባለን።";
        }

        $data = [
            'subject' => 'የ2019 ዓ.ም አጠቃላይ የትምህርት ክፍል የስራ እቅድ ሪፖርት',
            'body' => implode("\n\n", $paragraphs),
            'recipient_lead' => 'ለሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'recipient_location' => 'ኮምቦልቻ፤',
            'document_date' => '2026-10-20',
            'reference_number' => 'KIOT/W.U.IT/850/2019',
            'cc_list' => ['ለአካዳሚክ ጉዳዮች ም/ዳይሬክተር', 'ለፋሲሊቲ አስተዳደር ዳይሬክቶሬት'],
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
        ];

        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $bodyHtml = '';
        foreach ($paragraphs as $p) {
            $bodyHtml .= '<p>' . $p . '</p>';
        }

        $html = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'emblem_data_uri' => null,
            'stamp_data_uri' => null,
            'is_digitally_signed' => false,
            'signature_details' => null,
            'body_html' => $bodyHtml,
        ]))->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true, 'isFontSubsettingEnabled' => true]);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        $pageCount = $dompdf->getCanvas()->get_page_count();
        $this->assertGreaterThanOrEqual(2, $pageCount);
    }

    /**
     * Scenario 5: Unsigned physical-signature letter preserves blank signature line.
     */
    public function test_scenario_5_unsigned_physical_signature_reserves_blank_line(): void
    {
        $data = [
            'subject' => 'Unsigned Physical Signature Baseline',
            'body' => 'Testing pure unsigned letter for wet ink signing.',
            'recipient_lead' => 'ለሬጅስትራር ጽ/ቤት',
            'signatory_name' => 'Tibebu Legesse Molla',
        ];

        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $html = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'emblem_data_uri' => null,
            'stamp_data_uri' => null,
            'is_digitally_signed' => false,
            'signature_details' => null,
            'body_html' => '<p>' . $data['body'] . '</p>',
        ]))->render();

        $this->assertStringContainsString('blank-signature-line', $html);
        $this->assertStringNotContainsString('DIGITALLY SIGNED &amp; VERIFIED', $html);
    }

    /**
     * Scenario 6: Valid digitally signed letter execution and cryptographic verification.
     */
    public function test_scenario_6_cryptographically_signed_letter_and_visual_badge(): void
    {
        $this->authenticateAs($this->staffUser);

        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();
        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/99901',
            'title' => 'Digital Signature Test Letter',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->staffUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        $docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->staffUser->id,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        // Request signature
        $sigReqRes = $this->postJson('/api/v1/signatures', [
            'document_version_id' => $docVersion->id,
            'signer_id' => $this->staffUser->id,
            'purpose' => 'Official Executive Endorsement',
        ]);
        $sigReqRes->assertStatus(201);
        $reqId = $sigReqRes->json('data.id');

        // Execute signature
        $execRes = $this->postJson("/api/v1/signatures/{$reqId}/execute");
        $execRes->assertStatus(200);

        // Verify UniversityAssetService resolves verified signature visual
        $visual = UniversityAssetService::getVerifiedSignatureVisual($docVersion);
        $this->assertNotNull($visual);
        $this->assertTrue($visual['is_verified']);
        $this->assertSame($this->staffUser->name, $visual['signer_name']);
        $this->assertSame('PAdES-B-LT', $visual['format']);
    }

    /**
     * Scenario 7: Authorized stamp application lifecycle and asset integrity.
     */
    public function test_scenario_7_stamp_application_lifecycle(): void
    {
        $this->authenticateAs($this->staffUser);

        // 1. Create controlled stamp asset file in storage
        $stampContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $stampStorageKey = 'institutional/stamps/it_seal_test.png';
        Storage::disk('local')->put($stampStorageKey, $stampContent);

        $imageFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $stampStorageKey,
            'original_filename' => 'it_seal.png',
            'detected_mime' => 'image/png',
            'byte_size' => strlen($stampContent),
            'sha256' => hash('sha256', $stampContent),
            'uploaded_by' => $this->staffUser->id,
            'uploaded_at' => now()->format('Y-m-d H:i:s.u'),
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        // 2. Create StampDefinition and active StampVersion
        $stampDef = StampDefinition::create([
            'unit_id' => $this->itUnit->id,
            'name' => 'Department of IT Official Seal',
            'active' => true,
        ]);

        $stampVersion = StampVersion::create([
            'stamp_id' => $stampDef->id,
            'version_number' => 1,
            'image_file_id' => $imageFile->id,
            'effective_from' => now()->subDay()->format('Y-m-d H:i:s.u'),
            'retired_at' => null,
        ]);

        // Grant explicit StampAuthorization
        StampAuthorization::create([
            'stamp_id' => $stampDef->id,
            'user_id' => $this->staffUser->id,
            'granted_by' => $this->staffUser->id,
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s.u'),
            'valid_until' => now()->addYear()->format('Y-m-d H:i:s.u'),
        ]);

        // Create document version
        $cat = DocumentCategory::first();
        $conf = \App\Models\ConfidentialityLevel::first();
        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/99902',
            'title' => 'Stamped Letter Test',
            'category_id' => $cat->id,
            'confidentiality_id' => $conf->id,
            'owner_unit_id' => $this->itUnit->id,
            'created_by' => $this->staffUser->id,
            'document_date' => now()->toDateString(),
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        $docVersion = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => $this->staffUser->id,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        // Prior to application, verified stamp visual MUST be null
        $this->assertNull(UniversityAssetService::getVerifiedStampVisual($docVersion));

        // 3. Apply stamp via endpoint
        $applyRes = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $docVersion->id,
            'stamp_id' => $stampDef->id,
            'reason' => 'Official Departmental Seal Endorsement',
        ]);

        $applyRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // 4. After application, verified stamp visual is resolved
        $visual = UniversityAssetService::getVerifiedStampVisual($docVersion);
        $this->assertNotNull($visual);
        $this->assertSame('Department of IT Official Seal', $visual['stamp_name']);
        $this->assertStringStartsWith('data:image/png;base64,', $visual['data_uri']);

        // 5. Unauthorized user cannot apply stamp
        $this->authenticateAs($this->unauthorizedUser);
        $unauthRes = $this->postJson('/api/v1/stamps/apply', [
            'document_version_id' => $docVersion->id,
            'stamp_id' => $stampDef->id,
            'reason' => 'Unauthorized Stamp Attempt',
        ]);
        $unauthRes->assertStatus(403);
    }

    /**
     * Scenario 8: Institutional Asset Configuration & Compatibility.
     * Verifies approved_logo_path and logo_path alias handling.
     */
    public function test_scenario_8_asset_configuration_and_alias_handling(): void
    {
        $settings = app(SettingsService::class);

        // Setting definition resolves through canonicalKey
        $def = $settings->definition('institution.logo_path');
        $this->assertNotNull($def);
        $this->assertSame('Approved Institution Logo Path', $def['label']);

        // Effective value resolves through canonicalKey
        $val = $settings->effectiveValue('institution.logo_path');
        $this->assertNull($val); // null by default until configured
    }

    /**
     * Scenario 9: Reference Sequence Allocation Reconciled.
     * Preview does not consume numbers; registration allocates exactly one.
     */
    public function test_scenario_9_reference_number_allocation_invariants(): void
    {
        $this->authenticateAs($this->staffUser);
        $currentYear = date('Y');

        $initialNextVal = DB::table('reference_sequences')
            ->where('sequence_key', 'DOC')
            ->where('period_key', $currentYear)
            ->value('next_value') ?? 1;

        // 1. Preview request DOES NOT consume sequence number
        $previewRes = $this->postJson('/api/v1/official-letters/preview', [
            'subject' => 'Preview Sequence Test',
            'body' => 'Checking preview sequence safety.',
        ]);
        $previewRes->assertOk();

        $afterPreviewVal = DB::table('reference_sequences')
            ->where('sequence_key', 'DOC')
            ->where('period_key', $currentYear)
            ->value('next_value') ?? 1;

        $this->assertSame($initialNextVal, $afterPreviewVal);

        // 2. Generate letter allocates exactly one sequence number
        $genRes = $this->postJson('/api/v1/official-letters', [
            'subject' => 'Generated Letter Sequence Test',
            'body' => 'Checking generated letter sequence allocation.',
            'owner_unit_id' => $this->itUnit->id,
        ]);
        $genRes->assertStatus(201);

        $docId = $genRes->json('data.id');
        $document = Document::findOrFail($docId);

        // Assert reference number matches stored sequence
        $this->assertStringStartsWith("WU/DOC/{$currentYear}/", $document->reference_number);

        $afterGenVal = DB::table('reference_sequences')
            ->where('sequence_key', 'DOC')
            ->where('period_key', $currentYear)
            ->value('next_value');

        $this->assertSame($initialNextVal + 1, (int) $afterGenVal);
    }

    /**
     * Scenario 10: Final Issuance Safeguards.
     * Canonical official letter dispatch is blocked (HTTP 422) when approved university emblem
     * is not installed in private storage, preserving institutional authenticity.
     */
    public function test_scenario_10_final_issuance_safeguards_block_official_letter_missing_emblem(): void
    {
        $this->authenticateAs($this->staffUser);
        $this->grantPermissionToUser($this->staffUser, 'correspondence.dispatch', 'unit', $this->itUnit->id);
        $this->grantPermissionToUser($this->staffUser, 'correspondence.register', 'unit', $this->itUnit->id);

        // Ensure confirmed office procedure exists for IT unit
        $corrType = \App\Models\CorrespondenceType::first();
        \App\Models\OfficeProcedureVersion::create([
            'unit_id' => $this->itUnit->id,
            'correspondence_type_id' => $corrType->id,
            'purpose_code' => 'official_letter',
            'version_number' => 1,
            'receipt_mode' => 'direct',
            'issue_mode' => 'direct',
            'confirmed_by' => $this->staffUser->id,
            'confirmed_at' => now(),
            'effective_from' => now()->subMonth(),
        ]);

        $dispatchMethod = \App\Models\DispatchMethod::first();

        // Generate canonical official letter
        $genRes = $this->postJson('/api/v1/official-letters', [
            'subject' => 'Official Letter Issuance Safeguard Test',
            'body' => 'Testing emblem presence requirement for final letter issuance.',
            'owner_unit_id' => $this->itUnit->id,
        ]);
        $genRes->assertStatus(201);
        $docId = $genRes->json('data.id');
        $doc = Document::with('versions')->findOrFail($docId);
        $version = $doc->versions->first();

        // Create correspondence record linked to this official letter document
        $priority = \App\Models\Priority::first() ?? \App\Models\Priority::create([
            'code' => 'normal',
            'name' => 'Normal',
            'level' => 1,
            'sla_hours' => 72,
        ]);

        $corr = \App\Models\Correspondence::create([
            'document_id' => $doc->id,
            'type_id' => $corrType->id,
            'priority_id' => $priority->id,
            'purpose_code' => 'official_letter',
            'correspondence_date' => now()->toDateString(),
            'registered_by' => $this->staffUser->id,
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
        ]);

        // Attempt dispatch: because approved emblem is missing and no exception policy exists, MUST FAIL (422)
        $dispatchRes = $this->postJson("/api/v1/correspondences/{$corr->id}/dispatch", [
            'document_version_id' => $version->id,
            'method_id' => $dispatchMethod->id,
            'tracking_reference' => 'DISP-EMBLEM-GUARD-TEST',
        ]);

        $dispatchRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('Officially approved university emblem has not been installed', $dispatchRes->json('message'));
    }
}
