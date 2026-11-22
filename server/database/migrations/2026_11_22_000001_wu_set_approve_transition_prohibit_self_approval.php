<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enable prohibit_self_approval for approve transitions.
     *
     * Self-approval is a security anti-pattern. Existing databases seeded before
     * this flag was made mandatory must be updated.
     */
    public function up(): void
    {
        DB::table('workflow_transitions')
            ->where('action_code', 'approve')
            ->update(['prohibit_self_approval' => true]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        DB::table('workflow_transitions')
            ->where('action_code', 'approve')
            ->update(['prohibit_self_approval' => false]);
    }
};
