<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditStorageDiskExposureTest extends TestCase
{
    public function test_private_storage_disk_does_not_register_unauthenticated_public_routes(): void
    {
        $this->assertFalse(
            config('filesystems.disks.local.serve', false),
            'The private local disk must have serve=false to prevent unauthenticated access via storage/{path}.'
        );

        $this->assertFalse(
            Route::has('storage.local'),
            'Unauthenticated route storage.local must not be registered by FilesystemServiceProvider.'
        );
    }
}
