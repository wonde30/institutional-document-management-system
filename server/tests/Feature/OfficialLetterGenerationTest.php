<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\EthiopianDateService;
use App\Services\TemplateService;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Dompdf\Dompdf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfficialLetterGenerationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected User $staffUser;
    protected User $unauthorizedStaff;
    protected OrganizationUnit $itUnit;
    protected OrganizationUnit $otherUnit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BaselineReferenceVocabularySeeder::class);

        $this->adminUser = User::where('email', 'admin@wu.edu.et')->first();

        $this->itUnit = OrganizationUnit::where('code', 'ITD')->first()
            ?? OrganizationUnit::first();

        $this->otherUnit = OrganizationUnit::create([
            'unit_type_id' => $this->itUnit->unit_type_id,
            'code' => 'OTHER-UNIT',
            'name' => 'Other Branch Unit',
            'active' => 1,
        ]);

        $this->staffUser = User::create([
            'name' => 'Tibebu Legesse Molla',
            'email' => 'tibebu.legesse@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->staffUser, 'documents.create', 'unit', $this->itUnit->id);
        $this->grantPermissionToUser($this->staffUser, 'documents.view', 'unit', $this->itUnit->id);

        $this->unauthorizedStaff = User::create([
            'name' => 'Foreign Office Staff',
            'email' => 'foreign.staff@wu.edu.et',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
            'authz_version' => 1,
        ]);
        $this->grantPermissionToUser($this->unauthorizedStaff, 'documents.create', 'unit', $this->otherUnit->id);
        $this->grantPermissionToUser($this->unauthorizedStaff, 'documents.view', 'unit', $this->otherUnit->id);
    }

    public function test_baseline_config_returns_unit_hierarchy_and_reference_samples(): void
    {
        $this->authenticateAs($this->staffUser);

        $response = $this->getJson("/api/v1/official-letters/baseline-config?unit_id={$this->itUnit->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uni_am', 'ወሎ ዩኒቨርሲቲ')
            ->assertJsonPath('data.uni_en', 'WOLLO UNIVERSITY')
            ->assertJsonStructure([
                'data' => [
                    'unit_id',
                    'uni_am',
                    'uni_en',
                    'faculty_am',
                    'faculty_en',
                    'dept_am',
                    'dept_en',
                    'common_cc_offices',
                    'samples',
                ],
            ]);

        $samples = $response->json('data.samples');
        $this->assertCount(2, $samples);
        $this->assertSame('የ2019 ዓ.ም Course offering መላክን ይመለከታል', $samples[0]['subject']);
        $this->assertSame('ውክልና መስጠትን ይመለከታል', $samples[1]['subject']);
    }

    public function test_preview_official_letter_generates_valid_pdf_base64(): void
    {
        $this->authenticateAs($this->staffUser);

        $payload = [
            'subject' => 'ውክልና መስጠትን ይመለከታል',
            'body' => 'ከቀን 19/01/2019 ዓ.ም ጀምሮ በግል ጉዳዬ በስራ ገበታዬ ላይ ስላማልኖር ሙሉ ውክልና ሰጥቻለሁ።',
            'recipient_lead' => 'ለመ/ር መላክ ተሾመ',
            'recipient_sub' => 'ኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
            'recipient_location' => 'ኮምቦልቻ፤',
            'document_date' => '2026-09-28',
            'ethiopian_date' => '18/01/2019 ዓ.ም',
            'cc_list' => [
                'ለምክትል ሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
                'ለተማሪዎች ጉዳይ ዳይሬክተር ጽ/ቤት',
            ],
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
            'has_signature' => true,
            'has_stamp' => true,
        ];

        $response = $this->postJson('/api/v1/official-letters/preview', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $base64 = $response->json('data.pdf_base64');
        $this->assertNotEmpty($base64);
        $pdfBytes = base64_decode($base64);
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertGreaterThan(10000, strlen($pdfBytes));
    }

    public function test_generate_official_letter_creates_document_with_attached_pdf_and_workflow(): void
    {
        $this->authenticateAs($this->staffUser);

        $payload = [
            'subject' => 'የ2019 ዓ.ም Course offering መላክን ይመለከታል',
            'body' => 'በኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል የመደበኛ የ2019 ዓ.ም 1ኛ ወሳነ ትምህርት ኮርስ ምደባ የሰራን መሆኑን እየገለጽኩ መረጃውን አባሪ አድርገን የላክን መሆኑን አሳውቃለሁ።',
            'recipient_lead' => 'ለኢንፎርማቲክስ ኮሌጅ',
            'recipient_location' => 'ኮምቦልቻ፤',
            'document_date' => '2026-08-26',
            'owner_unit_id' => $this->itUnit->id,
            'cc_list' => [
                'ለኢንፎርሜሽን ቴክኖሎጂ ት/ት ክፍል',
                'ለኢንፎርማቲክስ ኮሌጅ ትምህርት ጥራት ጽ/ቤት',
            ],
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
            'submit_for_approval' => true,
            'has_signature' => true,
            'has_stamp' => true,
        ];

        $response = $this->postJson('/api/v1/official-letters', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $docId = $response->json('data.id');
        $this->assertNotNull($docId);

        $document = Document::with(['versions.versionFiles.fileObject'])->find($docId);
        $this->assertNotNull($document);
        $this->assertStringStartsWith('WU/DOC/', $document->reference_number);
        $this->assertSame('Official Letter: የ2019 ዓ.ም Course offering መላክን ይመለከታል', $document->title);

        // Check Version 1
        $version = $document->versions->first();
        $this->assertNotNull($version);
        $this->assertSame('template_generated', $version->ingestion_method);
        $this->assertNotNull($version->template_data);
        $this->assertSame('የ2019 ዓ.ም Course offering መላክን ይመለከታል', $version->template_data['subject']);

        // Check attached PDF binary
        $versionFile = $version->versionFiles->first();
        $this->assertNotNull($versionFile);
        $fileObject = $versionFile->fileObject;
        $this->assertNotNull($fileObject);
        $this->assertSame('application/pdf', $fileObject->detected_mime);
        $this->assertGreaterThan(10000, $fileObject->byte_size);
    }

    public function test_unauthorized_user_cannot_generate_letter_for_foreign_unit(): void
    {
        $this->authenticateAs($this->unauthorizedStaff);

        $payload = [
            'subject' => 'Unauthorized Directive',
            'body' => 'Attempting unauthorized letter issuance.',
            'owner_unit_id' => $this->itUnit->id,
        ];

        $response = $this->postJson('/api/v1/official-letters', $payload);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /**
     * Scenario A: Standard Single-Page Institutional Letter
     */
    public function test_scenario_a_standard_letter_structure_and_single_page(): void
    {
        $service = app(TemplateService::class);

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
            'has_signature' => true,
            'has_stamp' => true,
        ];

        $pdfBinary = $service->renderOfficialLetterPdf($data, 'KIOT/W.U.IT/737/2018');
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
        $this->assertStringContainsString('Nyala', $pdfBinary);

        // Check it fits on 1 page exactly
        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $view = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'emblem_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'stamp_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'signature_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'body_html' => '<p>' . $data['body'] . '</p>',
        ]))->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true, 'isFontSubsettingEnabled' => true]);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($view);
        $dompdf->render();
        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
    }

    /**
     * Scenario B: Multiple CC Recipients List (matching Reference Letter 1)
     */
    public function test_scenario_b_multiple_cc_distribution_list(): void
    {
        $service = app(TemplateService::class);

        $ccList = [
            'ለምክትል ሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'ለተማሪዎች ጉዳይ ዳይሬክተር ጽ/ቤት',
            'ለሬጅስትራር ጽ/ቤት',
            'ለኢንፎርማቲክስ ኮሌጅ',
            'ለኢንፎርማቲክስ ኮሌጅ ት/ት ጥራት አስተባባሪ ጽ/ቤት',
            'ለኢንፎርማቲክስ ኮሌጅ ምርምር፤ ማህበረሰብ አገልግሎትና ድህረ-ምረቃ ጽ/ቤት',
            'ለርቀትና ተከታታይ ጽ/ቤት',
            'ለ----------------------- ትም/ክፍል',
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
            'has_signature' => true,
            'has_stamp' => true,
        ];

        $pdfBinary = $service->renderOfficialLetterPdf($data, 'KIOT/W.U.IT/746/2019');
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);

        // Verify page count is 1
        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $view = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'emblem_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'stamp_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'signature_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'body_html' => '<p>' . $data['body'] . '</p>',
        ]))->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true, 'isFontSubsettingEnabled' => true]);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($view);
        $dompdf->render();
        $this->assertSame(1, $dompdf->getCanvas()->get_page_count());
    }

    /**
     * Scenario C: Mixed Bilingual Typography (Amharic & Latin Characters)
     */
    public function test_scenario_c_mixed_bilingual_amharic_and_latin_typography(): void
    {
        $service = app(TemplateService::class);

        $data = [
            'subject' => 'የ2019 ዓ.ም Course offering and Curriculum Matrix መላክን ይመለከታል',
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

        $pdfBinary = $service->renderOfficialLetterPdf($data);
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);
        $this->assertStringContainsString('Nyala', $pdfBinary);
    }

    /**
     * Scenario D: Multi-Page Document Flow with Persistent Footers
     */
    public function test_scenario_d_multi_page_document_flow(): void
    {
        $service = app(TemplateService::class);

        // Generate extensive administrative body text that naturally spans onto page 2
        $paragraphs = [];
        for ($i = 1; $i <= 10; $i++) {
            $paragraphs[] = "አንቀጽ {$i}፡ በወሎ ዩኒቨርሲቲ ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል የ2019 ዓ.ም የትምህርት ዘመን ዝግጅትን በተመለከተ ዝርዝር የላብራቶሪ ግብዓት፣ የመማሪያ ክፍሎች ምደባ፣ የመምህራን የስራ ጫና ስሌት እና የጥናትና ምርምር ፕሮጀክቶች ክትትል እቅድ በተሟላ ሁኔታ ተዘጋጅቶ የቀረበ ስለሆነ ጉዳዩን ተመልክተው አስፈላጊውን ውሳኔ እንዲሰጡበት እናሳስባለን።";
        }
        $longBody = implode("\n\n", $paragraphs);

        $data = [
            'subject' => 'የ2019 ዓ.ም አጠቃላይ የትምህርት ክፍል የስራ እቅድ ሪፖርት',
            'body' => $longBody,
            'recipient_lead' => 'ለሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'recipient_location' => 'ኮምቦልቻ፤',
            'document_date' => '2026-10-20',
            'reference_number' => 'KIOT/W.U.IT/850/2019',
            'cc_list' => ['ለአካዳሚክ ጉዳዮች ም/ዳይሬክተር', 'ለፋሲሊቲ አስተዳደር ዳይሬክቶሬት'],
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Head Department of Information Technology',
        ];

        $pdfBinary = $service->renderOfficialLetterPdf($data);
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', $pdfBinary);

        // Check it gracefully spans to 2 or more pages without layout corruption
        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $bodyHtml = '';
        foreach ($paragraphs as $p) {
            $bodyHtml .= '<p>' . $p . '</p>';
        }
        $view = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'emblem_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'stamp_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'signature_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
            'body_html' => $bodyHtml,
        ]))->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => true, 'isFontSubsettingEnabled' => true]);
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->loadHtml($view);
        $dompdf->render();

        $pageCount = $dompdf->getCanvas()->get_page_count();
        $this->assertGreaterThanOrEqual(2, $pageCount);
    }

    public function test_authenticity_excludes_synthetic_emblem_signatures_and_stamps(): void
    {
        $service = app(TemplateService::class);

        $data = [
            'subject' => 'Authenticity Verification Letter',
            'body' => 'Testing strictly authentic institutional rendering.',
            'recipient_lead' => 'ለሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'signatory_name' => 'Tibebu Legesse Molla',
            'signatory_title_am' => 'የትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => 'Department Head',
        ];

        // Render PDF directly
        $pdfBinary = $service->renderOfficialLetterPdf($data, 'WU/DOC/TEST');
        $this->assertNotEmpty($pdfBinary);

        // Render HTML view directly to inspect structural nodes
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

        // Must contain reserved frame, reserved stamp, and blank signature line
        $this->assertStringContainsString('emblem-reserved-frame', $html);
        $this->assertStringContainsString('blank-signature-line', $html);
        $this->assertStringContainsString('stamp-reserved-boundary', $html);

        // Must NOT contain synthetic SVG fake seals or cursive signature paths
        $this->assertStringNotContainsString('DIGITALLY SIGNED &amp; SEALED', $html);
        $this->assertStringNotContainsString('stampTopArc', $html);
        $this->assertStringNotContainsString('topArc', $html);
    }

    public function test_footer_cleanly_omits_unconfigured_contact_info(): void
    {
        $service = app(TemplateService::class);

        $data = [
            'subject' => 'Clean Footer Test',
            'body' => 'Testing clean omission of unverified contact numbers.',
            'recipient_lead' => 'ለሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'signatory_name' => 'Tibebu Legesse Molla',
        ];

        $fontPath = storage_path('fonts/nyala.ttf');
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);
        $html = view('letters.official_institution_letter', array_merge($data, [
            'font_url' => $fontUrl,
            'footer_phone' => null,
            'footer_fax' => null,
            'footer_pobox' => null,
            'footer_notice_am' => 'መልስ ሲፅፉ የደብዳቤያችንን ቁጥርና ቀን ይጠቅሱ',
            'footer_notice_en' => 'please quote our ref. number when replying',
            'body_html' => '<p>' . $data['body'] . '</p>',
        ]))->render();

        // Must not contain broken phone/fax lines
        $this->assertStringNotContainsString('033-351-00-92', $html);
        $this->assertStringNotContainsString('033-551-24-78', $html);
        // But must contain the institutional notices
        $this->assertStringContainsString('መልስ ሲፅፉ የደብዳቤያችንን ቁጥርና ቀን ይጠቅሱ', $html);
        $this->assertStringContainsString('please quote our ref. number when replying', $html);
    }

    public function test_signature_and_stamp_verification_enforces_version_and_validity(): void
    {
        $catId = \Illuminate\Support\Facades\DB::table('document_categories')->where('active', 1)->value('id')
            ?? \Illuminate\Support\Facades\DB::table('document_categories')->value('id');
        $confId = \Illuminate\Support\Facades\DB::table('confidentiality_levels')->where('active', 1)->value('id')
            ?? \Illuminate\Support\Facades\DB::table('confidentiality_levels')->value('id');

        $doc = Document::create([
            'reference_number' => 'WU/DOC/2026/00099',
            'title' => 'Official Endorsement Letter',
            'owner_unit_id' => $this->itUnit->id,
            'category_id' => $catId,
            'confidentiality_id' => $confId,
            'document_date' => '2026-10-15',
            'registered_at' => now()->format('Y-m-d H:i:s.u'),
            'created_by' => $this->staffUser->id,
        ]);

        $v1 = \App\Models\DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'ingestion_method' => 'template_generated',
            'created_by' => $this->staffUser->id,
        ]);

        // Version 1 has no signature and no stamp application initially
        $this->assertNull(\App\Services\UniversityAssetService::getVerifiedSignatureVisual($v1));
        $this->assertNull(\App\Services\UniversityAssetService::getVerifiedStampVisual($v1));

        // When Version 2 is created, it also has no signature
        $v2 = \App\Models\DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 2,
            'ingestion_method' => 'template_generated',
            'created_by' => $this->staffUser->id,
        ]);
        $this->assertNull(\App\Services\UniversityAssetService::getVerifiedSignatureVisual($v2));
        $this->assertNull(\App\Services\UniversityAssetService::getVerifiedStampVisual($v2));
    }

    public function test_final_official_letter_omits_placeholder_reserved_text(): void
    {
        $service = app(TemplateService::class);

        $data = [
            'subject' => 'Final Official Letter Test',
            'body' => 'Testing clean final letter output without placeholder text.',
            'recipient_lead' => 'ለሳይንቲፊክ ዳይሬክተር ጽ/ቤት',
            'signatory_name' => 'Tibebu Legesse Molla',
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

        // Must preserve frames for layout/blank areas
        $this->assertStringContainsString('emblem-reserved-frame', $html);
        $this->assertStringContainsString('stamp-reserved-boundary', $html);

        // Must NOT print unsightly placeholder text on final official letter
        $this->assertStringNotContainsString('OFFICIAL EMBLEM<br>RESERVED', $html);
        $this->assertStringNotContainsString('[OFFICIAL STAMP', $html);
    }

    public function test_settings_service_resolves_logo_path_alias_consistently(): void
    {
        $settings = app(\App\Services\Settings\SettingsService::class);

        $this->assertSame('institution.approved_logo_path', $settings->canonicalKey('institution.logo_path'));
        $this->assertSame('institution.approved_logo_path', $settings->canonicalKey('institution.approved_logo_path'));

        $def = $settings->definition('institution.logo_path');
        $this->assertNotNull($def);
        $this->assertSame('Approved Institution Logo Path', $def['label']);
    }
}
