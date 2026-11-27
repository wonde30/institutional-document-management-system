<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('primary_memberships', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('membership_id');
            $table->dateTime('selected_at', 6);

            $table->primary('user_id');

            $table->unique('membership_id', 'primary_memberships_uq_1');

            $table->foreign('user_id', 'primary_memberships_fk_1')->references('id')->on('users');
            $table->foreign('membership_id', 'primary_memberships_fk_2')->references('id')->on('organization_memberships');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primary_memberships');
    }
};
