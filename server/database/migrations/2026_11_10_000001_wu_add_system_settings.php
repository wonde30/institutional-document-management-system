<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Runtime configuration values.
     *
     * The authoritative registry of configuration keys lives in the code
     * (config/settings.php). This table stores only the values that an
     * administrator has overridden, together with the scope they apply to.
     *
     * A row is unique per (setting_key, scope). The global scope is
     * represented by a null unit, and the scope discriminator is a plain
     * column rather than a generated one: MySQL refuses a cascading foreign
     * key on a table that carries a stored generated column, and the
     * consistency check below keeps the two columns in agreement.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();

            $table->string('setting_key', 100);

            // Null represents the global scope.
            $table->foreignId('scope_unit_id')
                ->nullable()
                ->constrained('organization_units')
                ->cascadeOnDelete();

            // Scope discriminator: 0 for the global scope, otherwise the unit.
            $table->unsignedBigInteger('scope_key')->default(0);

            $table->string('value_type', 16);

            // Exactly one value column is populated, enforced by the check
            // constraints below. The type is declared by the catalogue.
            $table->string('string_value', 500)->nullable();
            $table->integer('integer_value')->nullable();
            $table->boolean('boolean_value')->nullable();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['setting_key', 'scope_key'], 'system_settings_key_scope_unique');
            $table->index('scope_unit_id', 'system_settings_scope_unit_index');
            $table->index('setting_key', 'system_settings_key_index');
        });

        DB::statement(
            "ALTER TABLE system_settings ADD CONSTRAINT system_settings_ck_scope
             CHECK (
                (scope_unit_id IS NULL AND scope_key = 0)
                OR (scope_unit_id IS NOT NULL AND scope_key = scope_unit_id)
             )"
        );

        DB::statement(
            "ALTER TABLE system_settings ADD CONSTRAINT system_settings_ck_type
             CHECK (value_type IN ('string', 'integer', 'boolean', 'select'))"
        );

        DB::statement(
            "ALTER TABLE system_settings ADD CONSTRAINT system_settings_ck_value
             CHECK (
                (value_type IN ('string', 'select') AND string_value IS NOT NULL AND integer_value IS NULL AND boolean_value IS NULL)
                OR (value_type = 'integer' AND integer_value IS NOT NULL AND string_value IS NULL AND boolean_value IS NULL)
                OR (value_type = 'boolean' AND boolean_value IS NOT NULL AND string_value IS NULL AND integer_value IS NULL)
             )"
        );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
