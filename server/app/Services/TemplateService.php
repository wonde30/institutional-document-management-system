<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\DocumentTemplate;
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
     * Render HTML to PDF binary string.
     */
    public function renderPdf(TemplateVersion $version, array $data, string $title = 'Document'): string
    {
        $contentHtml = $this->renderHtml($version, $data);

        $fullHtml = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
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
