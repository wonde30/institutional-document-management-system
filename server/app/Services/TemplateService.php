<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentVersion;
use App\Models\TemplateVersion;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TemplateService
{
    public function __construct(
        protected TemplateSanitizer $sanitizer,
        protected DocumentRepositoryService $documentRepository
    ) {}

    /**
     * Create a new document template header.
     *
     * @param array<string, mixed> $data
     */
    public function createTemplate(array $data, User $actor): DocumentTemplate
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($data, $actor, $now) {
            $template = DocumentTemplate::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'],
                'owner_unit_id' => $data['owner_unit_id'] ?? null, // Null = Global University-wide
                'visibility_scope' => $data['visibility_scope'] ?? 'global',
                'is_active' => $data['is_active'] ?? 1,
                'created_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'template.created',
                'target_type' => 'document_template',
                'target_id' => (string) $template->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['code' => $template->code, 'name' => $template->name],
            ]);

            return $template;
        });
    }

    /**
     * Create a draft template version.
     *
     * @param array<string, mixed> $data
     */
    public function createVersion(DocumentTemplate $template, array $data, User $actor): TemplateVersion
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($template, $data, $actor, $now) {
            $latestVersion = DB::table('template_versions')
                ->where('template_id', $template->id)
                ->max('version_number') ?? 0;

            $versionNumber = $latestVersion + 1;

            $cleanBody = $this->sanitizer->sanitize($data['body_template_html']);

            $version = TemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => $versionNumber,
                'schema_definition' => $data['schema_definition'] ?? ['type' => 'object', 'properties' => []],
                'body_template_html' => $cleanBody,
                'header_asset_key' => $data['header_asset_key'] ?? null,
                'footer_asset_key' => $data['footer_asset_key'] ?? null,
                'status' => 'draft',
                'published_at' => null,
                'created_by' => $actor->id,
                'created_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'template_version.draft_created',
                'target_type' => 'template_version',
                'target_id' => (string) $version->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => ['template_id' => $template->id, 'version_number' => $versionNumber],
            ]);

            return $version;
        });
    }

    /**
     * Publish a template version, retiring any currently published versions for the template.
     */
    public function publishVersion(TemplateVersion $version, User $actor): TemplateVersion
    {
        $now = now()->format('Y-m-d H:i:s.u');

        return DB::transaction(function () use ($version, $actor, $now) {
            // 1. Retire previously published versions of this template
            TemplateVersion::where('template_id', $version->template_id)
                ->where('status', 'published')
                ->where('id', '!=', $version->id)
                ->update(['status' => 'retired']);

            // 2. Mark this version published
            $version->update([
                'status' => 'published',
                'published_at' => $now,
            ]);

            AuditEvent::create([
                'actor_id' => $actor->id,
                'action' => 'template_version.published',
                'target_type' => 'template_version',
                'target_id' => (string) $version->id,
                'request_id' => (string) Str::uuid(),
                'occurred_at' => $now,
                'details_json' => [
                    'template_id' => $version->template_id,
                    'version_number' => $version->version_number,
                ],
            ]);

            return $version;
        });
    }

    /**
     * Retire an active or draft template version.
     */
    public function retireVersion(TemplateVersion $version, User $actor): TemplateVersion
    {
        $now = now()->format('Y-m-d H:i:s.u');

        $version->update(['status' => 'retired']);

        AuditEvent::create([
            'actor_id' => $actor->id,
            'action' => 'template_version.retired',
            'target_type' => 'template_version',
            'target_id' => (string) $version->id,
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'details_json' => ['template_id' => $version->template_id, 'version_number' => $version->version_number],
        ]);

        return $version;
    }

    /**
     * Validate data payload against schema definition.
     *
     * @param array<string, mixed> $data
     */
    public function validateDataAgainstSchema(TemplateVersion $version, array $data): void
    {
        $schema = is_array($version->schema_definition)
            ? $version->schema_definition
            : json_decode((string) $version->schema_definition, true) ?? [];

        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                throw new InvalidArgumentException("Missing required template variable: '{$field}'");
            }
        }
    }

    /**
     * Declaratively interpolate template variables and return sanitized HTML.
     *
     * @param array<string, mixed> $data
     */
    public function renderHtml(TemplateVersion $version, array $data): string
    {
        $this->validateDataAgainstSchema($version, $data);

        $template = $version->body_template_html;

        // Replace placeholders safely
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Render list or table if array
                $renderedVal = '<ul>'.implode('', array_map(fn ($item) => '<li>'.htmlspecialchars((string) $item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</li>', $value)).'</ul>';
            } else {
                $renderedVal = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            // Replace {{key}} or {{ key }}
            $template = preg_replace('/\{\{\s*'.preg_quote((string) $key, '/').'\s*\}\}/', $renderedVal, $template);
        }

        // Final sanitization check
        return $this->sanitizer->sanitize($template);
    }

    /**
     * Render official Wollo University institutional letter to PDF binary string.
     * Matches the Wollo University KIOT physical reference letters.
     *
     * @param array<string, mixed> $data
     */
    public function renderOfficialLetterPdf(
        array $data,
        ?string $referenceNumber = null,
        ?User $actor = null,
        ?DocumentVersion $version = null
    ): string {
        $fontPath = storage_path('fonts/nyala.ttf');
        if (! file_exists($fontPath)) {
            $fontPath = resource_path('fonts/nyala.ttf');
        }
        $fontUrl = 'file:///' . str_replace('\\', '/', $fontPath);

        // Date handling: Ethiopian calendar conversion
        $ethiopianDate = $data['ethiopian_date'] ?? null;
        if (! $ethiopianDate) {
            $rawDate = $data['document_date'] ?? now()->toDateString();
            $ethiopianDate = EthiopianDateService::formatFromDateString($rawDate);
        }

        // Institutional emblem: strictly real approved asset from controlled storage or settings
        $emblemUri = UniversityAssetService::getApprovedUniversityLogoDataUri();

        // Resolve document version context if available
        $docVersion = $version ?? ($data['document_version'] ?? null);
        if (! $docVersion && ! empty($data['document_version_id'])) {
            $docVersion = DocumentVersion::find($data['document_version_id']);
        }

        // Institutional stamp: strictly authentic registered stamp application on this version
        $stampDetails = UniversityAssetService::getVerifiedStampVisual($docVersion);
        $stampUri = $stampDetails['data_uri'] ?? null;

        // Digital signature: strictly verified cryptographic signature on this version
        $signatureDetails = UniversityAssetService::getVerifiedSignatureVisual($docVersion, $data['digital_signature'] ?? null);
        $isDigitallySigned = $signatureDetails !== null;

        // Footer institutional contact information resolved from SettingsService
        $footerPhone = null;
        $footerFax = null;
        $footerPobox = null;
        $footerNoticeAm = 'መልስ ሲፅፉ የደብዳቤያችንን ቁጥርና ቀን ይጠቅሱ';
        $footerNoticeEn = 'please quote our ref. number when replying';
        try {
            $settings = app(\App\Services\Settings\SettingsService::class);
            $valPhone = $settings->effectiveValue('institution.letter_phone');
            $footerPhone = ! empty($valPhone) ? (string) $valPhone : null;
            $valFax = $settings->effectiveValue('institution.letter_fax');
            $footerFax = ! empty($valFax) ? (string) $valFax : null;
            $valPobox = $settings->effectiveValue('institution.letter_pobox');
            $footerPobox = ! empty($valPobox) ? (string) $valPobox : null;
            $footerNoticeAm = (string) ($settings->effectiveValue('institution.letter_reply_notice_am') ?? $footerNoticeAm);
            $footerNoticeEn = (string) ($settings->effectiveValue('institution.letter_reply_notice_en') ?? $footerNoticeEn);
        } catch (\Throwable) {
            // Keep clean defaults
        }

        // Format body content safely
        $body = $data['body'] ?? ($data['letter_body'] ?? '');
        if (strpos($body, '<p>') === false) {
            $paragraphs = array_filter(array_map('trim', explode("\n", $body)));
            $bodyHtml = '';
            foreach ($paragraphs as $p) {
                if ($p !== '') {
                    $bodyHtml .= '<p>' . htmlspecialchars($p, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
                }
            }
        } else {
            $bodyHtml = $this->sanitizer->sanitize($body);
        }

        // Parse CC List
        $ccList = $data['cc_list'] ?? [];
        if (is_string($ccList)) {
            $decoded = json_decode($ccList, true);
            $ccList = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode("\n", $ccList)));
        }

        $refNumberResolved = $referenceNumber ?? ($data['reference_number'] ?? 'WU/DOC/DRAFT');
        $isDraft = (bool) ($data['is_draft'] ?? false);
        if (! array_key_exists('is_draft', $data) && (str_contains($refNumberResolved, 'DRAFT') || str_contains($refNumberResolved, 'PREVIEW'))) {
            $isDraft = true;
        }

        $viewData = [
            'title' => $data['title'] ?? ('Official Letter - ' . ($data['subject'] ?? 'WU')),
            'is_draft' => $isDraft,
            'font_url' => $fontUrl,
            'emblem_data_uri' => $emblemUri,
            'stamp_data_uri' => $stampUri,
            'is_digitally_signed' => $isDigitallySigned,
            'signature_details' => $signatureDetails,
            'uni_am' => $data['uni_am'] ?? 'ወሎ ዩኒቨርሲቲ',
            'faculty_am' => $data['faculty_am'] ?? 'ኮምቦልቻ ቴክኖሎጂ ኢንስቲትዩት',
            'dept_am' => $data['dept_am'] ?? 'ኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል',
            'location_am' => $data['location_am'] ?? 'ኮምቦልቻ',
            'uni_en' => $data['uni_en'] ?? 'WOLLO UNIVERSITY',
            'faculty_en' => $data['faculty_en'] ?? 'KOMBOLCHA INSTITUTE OF TECHNOLOGY',
            'dept_en' => $data['dept_en'] ?? 'Information Technology Department',
            'location_en' => $data['location_en'] ?? 'Kombolcha',
            'reference_number' => $referenceNumber ?? ($data['reference_number'] ?? 'WU/DOC/DRAFT'),
            'ethiopian_date' => $ethiopianDate,
            'recipient_lead' => $data['recipient_lead'] ?? ($data['recipient_name'] ?? 'ለኢንፎርማቲክስ ኮሌጅ'),
            'recipient_sub' => $data['recipient_sub'] ?? ($data['recipient_office'] ?? ''),
            'recipient_location' => $data['recipient_location'] ?? 'ኮምቦልቻ፤',
            'subject' => $data['subject'] ?? 'ጉዳዩ',
            'body_html' => $bodyHtml,
            'cc_list' => $ccList,
            'cc_location' => $data['cc_location'] ?? 'ኮምቦልቻ፤',
            'signatory_name' => $data['signatory_name'] ?? 'Tibebu Legesse Molla',
            'signatory_title_am' => $data['signatory_title_am'] ?? 'የኢንፎርሜሽን ቴክኖሎጂ ትምህርት ክፍል ኃላፊ',
            'signatory_title_en' => $data['signatory_title_en'] ?? 'Head Department of Information Technology',
            'footer_phone' => $footerPhone,
            'footer_fax' => $footerFax,
            'footer_pobox' => $footerPobox,
            'footer_notice_am' => $footerNoticeAm,
            'footer_notice_en' => $footerNoticeEn,
        ];

        $html = view('letters.official_institution_letter', $viewData)->render();

        return Pdf::setOptions([
            'isRemoteEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'fontDir' => storage_path('fonts'),
            'fontCache' => storage_path('fonts'),
            'chroot' => [base_path(), storage_path(), resource_path()],
        ])->loadHTML($html)->setPaper('a4', 'portrait')->output();
    }

    /**
     * Ensure the canonical official letter template exists in the registry.
     */
    public function ensureCanonicalLetterTemplateExists(?User $actor = null): DocumentTemplate
    {
        $existing = DocumentTemplate::with('publishedVersion')->where('code', 'TPL-OFFICIAL-LETTER-01')->first();
        if ($existing) {
            return $existing;
        }

        $now = now()->format('Y-m-d H:i:s.u');
        $actorId = $actor?->id ?? DB::table('users')->orderBy('id')->value('id') ?? 1;
        $categoryId = DB::table('document_categories')->where('active', 1)->value('id')
            ?? DB::table('document_categories')->value('id')
            ?? 1;

        return DB::transaction(function () use ($now, $actorId, $categoryId) {
            $template = DocumentTemplate::create([
                'code' => 'TPL-OFFICIAL-LETTER-01',
                'name' => 'Wollo University Official Institutional Letter',
                'description' => 'Canonical official university letter template matching the Wollo University KIOT reference standard.',
                'category_id' => $categoryId,
                'owner_unit_id' => null,
                'visibility_scope' => 'global',
                'is_active' => true,
                'created_by' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $schema = [
                'type' => 'object',
                'required' => ['subject', 'body'],
                'properties' => [
                    'recipient_lead' => ['type' => 'string', 'title' => 'Recipient Name / Title'],
                    'recipient_sub' => ['type' => 'string', 'title' => 'Recipient Office / Department'],
                    'recipient_location' => ['type' => 'string', 'title' => 'Recipient Location / Campus'],
                    'subject' => ['type' => 'string', 'title' => 'Letter Subject (ጉዳዩ)'],
                    'body' => ['type' => 'string', 'title' => 'Letter Body Content'],
                    'cc_list' => ['type' => 'array', 'items' => ['type' => 'string'], 'title' => 'CC Distribution List (ግልባጭ)'],
                    'signatory_name' => ['type' => 'string', 'title' => 'Authorized Signatory Name'],
                    'signatory_title_am' => ['type' => 'string', 'title' => 'Signatory Title (Amharic)'],
                    'signatory_title_en' => ['type' => 'string', 'title' => 'Signatory Title (English)'],
                    'document_date' => ['type' => 'string', 'title' => 'Document Date (YYYY-MM-DD)'],
                    'ethiopian_date' => ['type' => 'string', 'title' => 'Ethiopian Date (DD/MM/YYYY ዓ.ም)'],
                ],
            ];

            TemplateVersion::create([
                'template_id' => $template->id,
                'version_number' => 1,
                'schema_definition' => $schema,
                'body_template_html' => '<div class="letter-body">{{body}}</div>',
                'status' => 'published',
                'published_at' => $now,
                'created_by' => $actorId,
                'created_at' => $now,
            ]);

            return $template->fresh(['publishedVersion']);
        });
    }

    /**
     * Generate an official institutional letter document with optional approval workflow.
     *
     * @param array<string, mixed> $letterData
     * @param array<string, mixed> $docAttributes
     */
    public function generateOfficialLetter(
        array $letterData,
        array $docAttributes,
        User $actor,
        bool $submitForApproval = false
    ): Document {
        $template = $this->ensureCanonicalLetterTemplateExists($actor);
        $publishedVersion = $template->publishedVersion;

        $refNumber = $this->documentRepository->generateReferenceNumber($docAttributes['sequence_key'] ?? 'DOC');

        $pdfBinary = $this->renderOfficialLetterPdf($letterData, $refNumber, $actor);

        $categoryId = $docAttributes['category_id']
            ?? DB::table('document_categories')->where('active', 1)->value('id')
            ?? 1;
        $confidentialityId = $docAttributes['confidentiality_id']
            ?? DB::table('confidentiality_levels')->where('active', 1)->value('id')
            ?? 1;

        $docPayload = array_merge($docAttributes, [
            'reference_number' => $refNumber,
            'title' => $docAttributes['title'] ?? ('Official Letter: ' . ($letterData['subject'] ?? 'WU Letter')),
            'summary' => $docAttributes['summary'] ?? ($letterData['subject'] ?? 'Official Institutional Letter'),
            'category_id' => $categoryId,
            'confidentiality_id' => $confidentialityId,
            'owner_unit_id' => $docAttributes['owner_unit_id'],
            'document_date' => $docAttributes['document_date'] ?? ($letterData['document_date'] ?? now()->toDateString()),
            'ingestion_method' => 'template_generated',
            'template_version_id' => $publishedVersion?->id,
            'template_data' => $letterData,
            'created_by' => $actor->id,
            'change_note' => 'Generated official university letter matching Wollo University institutional reference standard.',
        ]);

        $document = $this->documentRepository->registerDocument($docPayload, null, $actor);

        // Bind rendered PDF to Version 1
        $initialVersion = $document->versions()->first();
        if ($initialVersion) {
            $this->documentRepository->attachFileToVersion(
                $initialVersion,
                $pdfBinary,
                $actor->id,
                'primary',
                1,
                Str::slug($document->title) . '.pdf',
                'application/pdf'
            );
        }

        // If requested, initiate the standard document approval workflow
        if ($submitForApproval) {
            $workflowEngine = app(WorkflowEngineService::class);
            $standardWf = DB::table('workflow_definitions')->where('code', 'standard_doc_approval')->first();
            $hasVersion = $standardWf ? DB::table('workflow_versions')->where('definition_id', $standardWf->id)->exists() : false;
            if ($standardWf && $hasVersion && $initialVersion) {
                try {
                    $workflowEngine->startInstance(
                        (int) $standardWf->id,
                        (int) $initialVersion->id,
                        null,
                        $actor
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Workflow initiation skipped: ' . $e->getMessage());
                }
            }
        }

        return $document;
    }

    /**
     * Render HTML to PDF binary string.
     */
    public function renderPdf(TemplateVersion $version, array $data, string $title = 'Document'): string
    {
        if (($version->template && $version->template->code === 'TPL-OFFICIAL-LETTER-01') || ! empty($data['is_official_letter'])) {
            return $this->renderOfficialLetterPdf($data, $data['reference_number'] ?? null);
        }

        $contentHtml = $this->renderHtml($version, $data);

        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $fullHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$safeTitle}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12pt; line-height: 1.6; color: #1f2937; margin: 40px; }
        .institution-header { text-align: center; border-bottom: 2px solid #002855; padding-bottom: 15px; margin-bottom: 25px; }
        .institution-header h1 { font-size: 16pt; color: #002855; margin: 0; text-transform: uppercase; }
        .institution-header h2 { font-size: 12pt; color: #4b5563; margin: 5px 0 0 0; }
        .document-body { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #d1d5db; padding: 8px 12px; }
        th { background-color: #f3f4f6; }
        .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 9pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="institution-header">
        <h1>Wollo University</h1>
        <h2>Administrative & Academic Document Services</h2>
    </div>
    <div class="document-body">
        {$contentHtml}
    </div>
    <div class="footer">
        Generated via WU-DMS Official Document Template System &bull; Version {$version->version_number}
    </div>
</body>
</html>
HTML;

        return Pdf::loadHTML($fullHtml)->setPaper('a4', 'portrait')->output();
    }

    /**
     * Generate a new Document in the unified repository from a published template.
     *
     * @param array<string, mixed> $templateData
     * @param array<string, mixed> $docAttributes
     */
    public function generateDocument(
        TemplateVersion $version,
        array $templateData,
        array $docAttributes,
        User $actor
    ): Document {
        if ($version->status !== 'published') {
            throw new InvalidArgumentException("Cannot generate document from non-published template version (current status: {$version->status})");
        }

        $pdfBinary = $this->renderPdf($version, $templateData, $docAttributes['title'] ?? 'Generated Document');

        $docPayload = array_merge($docAttributes, [
            'ingestion_method' => 'template_generated',
            'template_version_id' => $version->id,
            'template_data' => $templateData,
            'created_by' => $actor->id,
            'change_note' => "Generated from template '{$version->template->name}' version {$version->version_number}",
        ]);

        $document = $this->documentRepository->registerDocument($docPayload, null, $actor);

        // Bind rendered PDF to Version 1
        $initialVersion = $document->versions()->first();
        if ($initialVersion) {
            $this->documentRepository->attachFileToVersion(
                $initialVersion,
                $pdfBinary,
                $actor->id,
                'primary',
                1,
                Str::slug($document->title).'.pdf',
                'application/pdf'
            );
        }

        return $document;
    }
}
