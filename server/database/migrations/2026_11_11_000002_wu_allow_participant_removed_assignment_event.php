<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The assignment lifecycle vocabulary, without the removal event.
     */
    protected const BASE_ACTIONS = [
        'assigned',
        'claimed',
        'delegated',
        'delegation_accepted',
        'delegation_revoked',
        'reassigned',
        'accepted',
        'completed',
        'returned',
        'cancelled',
    ];

    /**
     * Extend the assignment lifecycle vocabulary with the participant removal
     * event.
     *
     * The constraint follows the pattern already used by the assignment events
     * migration: the existing constraint is replaced with one that also admits
     * the new action. The historical migration is not modified.
     */
    public function up(): void
    {
        $this->replaceConstraint([...self::BASE_ACTIONS, 'participant_removed']);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $this->replaceConstraint(self::BASE_ACTIONS);
    }

    /**
     * Replace the action check constraint.
     *
     * @param  list<string>  $actions
     */
    protected function replaceConstraint(array $actions): void
    {
        DB::statement('ALTER TABLE `assignment_events` DROP CONSTRAINT `assignment_events_ck_1`');

        $list = "'".implode("','", $actions)."'";

        DB::statement(
            "ALTER TABLE `assignment_events` ADD CONSTRAINT `assignment_events_ck_1` CHECK (`action` IN ({$list}))"
        );
    }
};
