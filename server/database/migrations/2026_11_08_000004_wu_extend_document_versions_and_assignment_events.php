<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Extend document_versions (Ingestion method & template binding)
        DB::statement(<<<'SQL'
ALTER TABLE `document_versions`
  ADD COLUMN `ingestion_method` VARCHAR(32) NOT NULL DEFAULT 'digital_upload' AFTER `version_number`,
  ADD COLUMN `template_version_id` BIGINT UNSIGNED NULL AFTER `ingestion_method`,
  ADD COLUMN `template_data` JSON NULL AFTER `template_version_id`,
  ADD KEY `document_versions_ix_2` (`template_version_id`),
  ADD KEY `document_versions_ix_3` (`ingestion_method`),
  ADD CONSTRAINT `document_versions_fk_3` FOREIGN KEY (`template_version_id`) REFERENCES `template_versions` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `document_versions_ck_2` CHECK (`ingestion_method` IN ('template_generated', 'scanned_paper', 'digital_upload', 'manually_registered'));
SQL
        );

        // 2. Extend assignment_events (Target user & expanded actions)
        DB::statement(<<<'SQL'
ALTER TABLE `assignment_events`
  ADD COLUMN `target_user_id` BIGINT UNSIGNED NULL AFTER `actor_id`,
  ADD KEY `assignment_events_ix_2` (`target_user_id`),
  ADD CONSTRAINT `assignment_events_fk_3` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  DROP CONSTRAINT `assignment_events_ck_1`,
  ADD CONSTRAINT `assignment_events_ck_1` CHECK (`action` IN ('assigned', 'claimed', 'delegated', 'delegation_accepted', 'delegation_revoked', 'reassigned', 'accepted', 'completed', 'returned', 'cancelled'));
SQL
        );

        // 3. Canonical document relationship normalization ('reply_to' -> 'responds_to')
        DB::transaction(function () {
            $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
            $legacy = DB::table('document_relation_types')->where('code', 'reply_to')->first();

            if ($legacy && ! $canonical) {
                // Case 1: Only legacy exists -> rename in place (preserves ID and all FK references)
                DB::table('document_relation_types')->where('id', $legacy->id)->update([
                    'code' => 'responds_to',
                    'name' => 'Responds to',
                    'description' => 'Official response to a source document',
                    'active' => 1,
                ]);
            } elseif ($legacy && $canonical) {
                // Case 2: Both exist -> migrate every relationship referencing legacy row to canonical row
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
                // Case 3: Neither exists -> create canonical
                DB::table('document_relation_types')->insert([
                    'code' => 'responds_to',
                    'name' => 'Responds to',
                    'description' => 'Official response to a source document',
                    'active' => 1,
                ]);
            }
        });

        // 4. Database-level immutability triggers for document_versions
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_document_versions_no_update`
BEFORE UPDATE ON `document_versions`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: document_versions records are strictly append-only and immutable.';
    END IF;
END;
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_document_versions_no_delete`
BEFORE DELETE ON `document_versions`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: document_versions records cannot be deleted.';
    END IF;
END;
SQL
        );

        // 5. Database-level immutability triggers for version_files
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_version_files_no_update`
BEFORE UPDATE ON `version_files`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: version_files associations are strictly append-only and immutable.';
    END IF;
END;
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `trg_version_files_no_delete`
BEFORE DELETE ON `version_files`
FOR EACH ROW
BEGIN
    IF @dms_allow_version_mutation IS NULL OR @dms_allow_version_mutation != 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Integrity violation: version_files associations cannot be deleted.';
    END IF;
END;
SQL
        );
    }

    public function down(): void
    {
        // Drop triggers
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_version_files_no_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_version_files_no_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_document_versions_no_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trg_document_versions_no_update`');

        // Revert document_relation_types
        $canonical = DB::table('document_relation_types')->where('code', 'responds_to')->first();
        $legacy = DB::table('document_relation_types')->where('code', 'reply_to')->first();
        if ($canonical && ! $legacy) {
            DB::table('document_relation_types')->where('id', $canonical->id)->update([
                'code' => 'reply_to',
                'name' => 'Reply to',
                'description' => 'Response to a previous document',
            ]);
        }

        // Revert assignment_events
        DB::statement(<<<'SQL'
ALTER TABLE `assignment_events`
  DROP CONSTRAINT `assignment_events_ck_1`,
  ADD CONSTRAINT `assignment_events_ck_1` CHECK (`action` IN ('assigned', 'accepted', 'completed', 'returned', 'cancelled')),
  DROP FOREIGN KEY `assignment_events_fk_3`,
  DROP KEY `assignment_events_ix_2`,
  DROP COLUMN `target_user_id`;
SQL
        );

        // Revert document_versions
        DB::statement(<<<'SQL'
ALTER TABLE `document_versions`
  DROP CONSTRAINT `document_versions_ck_2`,
  DROP FOREIGN KEY `document_versions_fk_3`,
  DROP KEY `document_versions_ix_3`,
  DROP KEY `document_versions_ix_2`,
  DROP COLUMN `template_data`,
  DROP COLUMN `template_version_id`,
  DROP COLUMN `ingestion_method`;
SQL
        );
    }
};
