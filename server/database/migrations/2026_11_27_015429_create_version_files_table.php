<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_files', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('file_object_id');
            $table->string('purpose', 16);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->tinyInteger('primary_slot')->storedAs('(case when (`purpose` = \'primary\') then 1 else NULL end)')->nullable();

            $table->primary(['document_version_id', 'file_object_id']);

            $table->index('file_object_id', 'version_files_ix_1');
            $table->unique(['document_version_id', 'primary_slot'], 'version_files_uq_1');
            $table->unique(['document_version_id', 'display_order'], 'version_files_uq_2');

            $table->foreign('document_version_id', 'version_files_fk_1')->references('id')->on('document_versions');
            $table->foreign('file_object_id', 'version_files_fk_2')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `version_files` ADD CONSTRAINT `version_files_ck_1` CHECK ((`purpose` in (\'primary\',\'attachment\')))');

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_version_files_no_delete`
BEFORE DELETE ON `version_files`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: version_files associations cannot be deleted.';
    END IF;
END
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_version_files_no_update`
BEFORE UPDATE ON `version_files`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: version_files associations are strictly append-only and immutable.';
    END IF;
END
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_version_files_no_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_version_files_no_update`');
        Schema::dropIfExists('version_files');
    }
};
