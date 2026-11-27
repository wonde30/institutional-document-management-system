<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('employee_number', 64);
            $table->text('biography')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique('user_id', 'staff_profiles_uq_1');
            $table->unique('employee_number', 'staff_profiles_uq_2');

            $table->foreign('user_id', 'staff_profiles_fk_1')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
