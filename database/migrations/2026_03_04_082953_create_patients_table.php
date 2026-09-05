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
        Schema::create('patients', function (Blueprint $table) {
    $table->id();

    $table->string('first_name');
    $table->string('last_name');
    $table->integer('age');

    $table->string('address')->nullable();
    $table->string('contact_number')->nullable();

    $table->integer('gravida')->nullable();
    $table->integer('para')->nullable();

    $table->string('blood_pressure')->nullable();
    $table->integer('gestational_age')->nullable();

    $table->string('risk_level')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
