<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');
            $table->comment('Append-only audit trail. Application account must not have UPDATE or DELETE grants.');

            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('target_type', 100);
            $table->string('target_id', 100);
            $table->char('request_id', 36)->charset('ascii')->collation('ascii_bin');
            $table->dateTime('occurred_at', 6);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('details_json')->nullable();

            $table->index(['target_type', 'target_id', 'occurred_at'], 'audit_events_ix_1');
            $table->index(['actor_id', 'occurred_at'], 'audit_events_ix_2');
            $table->index('request_id', 'audit_events_ix_3');
            $table->index('occurred_at', 'audit_events_ix_4');
            $table->index(['target_type', 'occurred_at'], 'audit_events_ix_5');

            $table->foreign('actor_id', 'audit_events_fk_1')->references('id')->on('users');
        });

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `audit_events_prevent_delete`
BEFORE DELETE ON `audit_events`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'AUDIT INTEGRITY VIOLATION: audit_events table is append-only and cannot be deleted.';
END
SQL
        );

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `audit_events_prevent_update`
BEFORE UPDATE ON `audit_events`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'AUDIT INTEGRITY VIOLATION: audit_events table is append-only and cannot be modified.';
END
SQL
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS `audit_events_prevent_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `audit_events_prevent_update`');
        Schema::dropIfExists('audit_events');
    }
};
