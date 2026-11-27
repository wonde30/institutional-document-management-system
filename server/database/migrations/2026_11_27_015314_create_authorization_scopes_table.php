<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_scopes', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('kind', 16);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('unit_key')->storedAs('coalesce(`unit_id`,0)')->nullable();

            $table->index('unit_id', 'authorization_scopes_ix_1');
            $table->unique(['kind', 'unit_key'], 'authorization_scopes_uq_1');

            $table->foreign('unit_id', 'authorization_scopes_fk_1')->references('id')->on('organization_units');
        });

        DB::statement('ALTER TABLE `authorization_scopes` ADD CONSTRAINT `authorization_scopes_ck_1` CHECK ((((`kind` = \'global\') and (`unit_id` is null)) or ((`kind` in (\'unit\',\'subtree\')) and (`unit_id` is not null))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_scopes');
    }
};
