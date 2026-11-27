<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_version_id')->nullable();
            $table->unsignedBigInteger('workflow_version_id');
            $table->unsignedBigInteger('parent_instance_id')->nullable();
            $table->unsignedBigInteger('started_by');
            $table->dateTime('started_at', 6);

            $table->index(['document_version_id', 'started_at'], 'workflow_instances_ix_1');
            $table->index('workflow_version_id', 'workflow_instances_ix_2');
            $table->index('started_by', 'workflow_instances_ix_3');
            $table->index('parent_instance_id', 'workflow_instances_ix_4');

            $table->foreign('document_version_id', 'workflow_instances_fk_1')->references('id')->on('document_versions');
            $table->foreign('workflow_version_id', 'workflow_instances_fk_2')->references('id')->on('workflow_versions');
            $table->foreign('started_by', 'workflow_instances_fk_3')->references('id')->on('users');
            $table->foreign('parent_instance_id', 'workflow_instances_fk_4')->references('id')->on('workflow_instances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
