<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedInteger('version_number');
            $table->string('ingestion_method', 32)->default('digital_upload');
            $table->unsignedBigInteger('template_version_id')->nullable();
            $table->json('template_data')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->text('change_note')->nullable();
            $table->dateTime('created_at', 6);

            $table->index('created_by', 'document_versions_ix_1');
            $table->index('template_version_id', 'document_versions_ix_2');
            $table->index('ingestion_method', 'document_versions_ix_3');
            $table->unique(['document_id', 'version_number'], 'document_versions_uq_1');

            $table->foreign('document_id', 'document_versions_fk_1')->references('id')->on('documents');
            $table->foreign('created_by', 'document_versions_fk_2')->references('id')->on('users');
            $table->foreign('template_version_id', 'document_versions_fk_3')->references('id')->on('template_versions')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `document_versions` ADD CONSTRAINT `document_versions_ck_1` CHECK ((`version_number` > 0))');
        DB::statement('ALTER TABLE `document_versions` ADD CONSTRAINT `document_versions_ck_2` CHECK ((`ingestion_method` in (\'template_generated\',\'scanned_paper\',\'digital_upload\',\'manually_registered\')))');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_document_versions_no_delete`
BEFORE DELETE ON `document_versions`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: document_versions records cannot be deleted.';
    END IF;
END
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_document_versions_no_update`
BEFORE UPDATE ON `document_versions`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: document_versions records are strictly append-only and immutable.';
    END IF;
END
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_document_versions_no_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_document_versions_no_update`');
        Schema::dropIfExists('document_versions');
    }
};
