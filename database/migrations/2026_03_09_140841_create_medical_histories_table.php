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
        Schema::create('medical_histories', function (Blueprint $table) {

        $table->id();

        $table->foreignId('patient_id')->constrained()->onDelete('cascade');

        $table->boolean('epilepsy')->default(false);
        $table->boolean('severe_headache')->default(false);
        $table->boolean('visual_disturbance')->default(false);
        $table->boolean('chest_pain')->default(false);
        $table->boolean('shortness_breath')->default(false);
        $table->boolean('breast_mass')->default(false);
        $table->boolean('liver_disease')->default(false);
        $table->boolean('smoking')->default(false);
        $table->boolean('allergies')->default(false);
        $table->boolean('drug_intake')->default(false);
        $table->boolean('std_history')->default(false);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_histories');
    }
};
