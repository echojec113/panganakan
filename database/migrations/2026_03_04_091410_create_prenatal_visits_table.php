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
        Schema::create('prenatal_visits', function (Blueprint $table) {
    $table->id();

    $table->foreignId('patient_id')->constrained()->onDelete('cascade');

    $table->date('visit_date');

    $table->integer('bp_sys')->nullable();
    $table->integer('bp_dia')->nullable();

    $table->integer('weight')->nullable();
    $table->integer('gestational_age')->nullable();

    $table->boolean('hypertension')->default(false);
    $table->boolean('diabetes')->default(false);
    $table->boolean('anemia')->default(false);

    $table->string('risk_level')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prenatal_visits');
    }
};
