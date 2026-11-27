<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('setting_key', 100);
            $table->unsignedBigInteger('scope_unit_id')->nullable();
            $table->unsignedBigInteger('scope_key')->default(0);
            $table->string('value_type', 16);
            $table->string('string_value', 500)->nullable();
            $table->integer('integer_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('setting_key', 'system_settings_key_index');
            $table->unique(['setting_key', 'scope_key'], 'system_settings_key_scope_unique');
            $table->index('scope_unit_id', 'system_settings_scope_unit_index');
            $table->index('updated_by', 'system_settings_updated_by_foreign');

            $table->foreign('scope_unit_id', 'system_settings_scope_unit_id_foreign')->references('id')->on('organization_units')->cascadeOnDelete();
            $table->foreign('updated_by', 'system_settings_updated_by_foreign')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `system_settings` ADD CONSTRAINT `system_settings_ck_scope` CHECK ((((`scope_unit_id` is null) and (`scope_key` = 0)) or ((`scope_unit_id` is not null) and (`scope_key` = `scope_unit_id`))))');
        DB::statement('ALTER TABLE `system_settings` ADD CONSTRAINT `system_settings_ck_type` CHECK ((`value_type` in (\'string\',\'integer\',\'boolean\',\'select\')))');
        DB::statement('ALTER TABLE `system_settings` ADD CONSTRAINT `system_settings_ck_value` CHECK ((((`value_type` in (\'string\',\'select\')) and (`string_value` is not null) and (`integer_value` is null) and (`boolean_value` is null)) or ((`value_type` = \'integer\') and (`integer_value` is not null) and (`string_value` is null) and (`boolean_value` is null)) or ((`value_type` = \'boolean\') and (`boolean_value` is not null) and (`string_value` is null) and (`integer_value` is null))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
