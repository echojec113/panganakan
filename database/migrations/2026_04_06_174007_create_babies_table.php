<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('babies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->enum('sex', ['Male', 'Female'])->nullable();
            $table->date('date_of_birth');
            $table->time('time_of_birth');
            $table->decimal('birth_weight', 5, 2)->nullable(); // kg with 2 decimal places
            $table->decimal('birth_length', 5, 1)->nullable(); // cm with 1 decimal place
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('babies');
    }
};
