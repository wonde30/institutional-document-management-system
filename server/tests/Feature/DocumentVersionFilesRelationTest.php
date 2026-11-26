<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\FileObject;
use App\Models\VersionFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentVersionFilesRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_version_has_many_files_relation_alias(): void
    {
        $doc = Document::create([
            'title' => 'Test Doc',
            'reference_number' => 'WU/DOC/TEST/' . uniqid(),
            'document_date' => now()->toDateString(),
            'registered_at' => now(),
            'category_id' => 1,
            'confidentiality_id' => 1,
            'owner_unit_id' => 1,
            'created_by' => 1,
            'custodian_id' => 1,
            'status' => 'draft',
            'lock_version' => 0,
        ]);

        $version = DocumentVersion::create([
            'document_id' => $doc->id,
            'version_number' => 1,
            'created_by' => 1,
        ]);

        $fileObject = FileObject::create([
            'sha256' => hash('sha256', 'testcontent'),
            'byte_size' => 11,
            'storage_disk' => 'local',
            'storage_key' => 'test/file.pdf',
            'original_filename' => 'file.pdf',
            'detected_mime' => 'application/pdf',
            'uploaded_by' => 1,
            'uploaded_at' => now(),
        ]);

        VersionFile::create([
            'document_version_id' => $version->id,
            'file_object_id' => $fileObject->id,
            'purpose' => 'attachment',
            'display_order' => 1,
        ]);

        $this->assertTrue($version->relationLoaded('files') === false);
        $this->assertCount(1, $version->files);
        $this->assertEquals($fileObject->id, $version->files->first()->file_object_id);
    }
}
