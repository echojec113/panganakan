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
        Schema::create('birth_plans', function (Blueprint $table) {

        $table->id();

        $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

        
        $table->string('delivery_location')->nullable();
        $table->string('transportation')->nullable();
        $table->string('payment_method')->nullable();
        $table->string('birth_companion')->nullable();
        $table->string('family_planning_method')->nullable();

        $table->text('notes')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birth_plans');
    }
};
