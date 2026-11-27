<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_policies', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->unsignedBigInteger('from_unit_id')->nullable();
            $table->unsignedBigInteger('to_unit_id');
            $table->unsignedBigInteger('correspondence_type_id')->nullable();
            $table->unsignedBigInteger('document_category_id')->nullable();
            $table->unsignedBigInteger('priority_id')->nullable();
            $table->unsignedBigInteger('default_assignee_role_id')->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->unsignedBigInteger('escalation_unit_id')->nullable();
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('from_unit_id', 'routing_policies_fk_1');
            $table->index('to_unit_id', 'routing_policies_fk_2');
            $table->index('correspondence_type_id', 'routing_policies_fk_3');
            $table->index('document_category_id', 'routing_policies_fk_4');
            $table->index('priority_id', 'routing_policies_fk_5');
            $table->index('default_assignee_role_id', 'routing_policies_fk_6');
            $table->index('escalation_unit_id', 'routing_policies_fk_7');
            $table->unique('code', 'routing_policies_uq_1');

            $table->foreign('from_unit_id', 'routing_policies_fk_1')->references('id')->on('organization_units')->cascadeOnDelete();
            $table->foreign('to_unit_id', 'routing_policies_fk_2')->references('id')->on('organization_units')->cascadeOnDelete();
            $table->foreign('correspondence_type_id', 'routing_policies_fk_3')->references('id')->on('correspondence_types')->nullOnDelete();
            $table->foreign('document_category_id', 'routing_policies_fk_4')->references('id')->on('document_categories')->nullOnDelete();
            $table->foreign('priority_id', 'routing_policies_fk_5')->references('id')->on('priorities')->nullOnDelete();
            $table->foreign('default_assignee_role_id', 'routing_policies_fk_6')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('escalation_unit_id', 'routing_policies_fk_7')->references('id')->on('organization_units')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `routing_policies` ADD CONSTRAINT `routing_policies_ck_1` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_policies');
    }
};
