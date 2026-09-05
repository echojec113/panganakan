<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('ultrasounds', function (Blueprint $table) {
            $table->id();

            // Relationship
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');

            // Scan Info
            $table->date('scan_date');

            // Fetal Data
            $table->string('fetal_heartbeat')->nullable(); // Normal / Weak / None
            $table->string('fetal_movement')->nullable();  // Normal / Reduced
            $table->string('presentation')->nullable();    // Cephalic / Breech / Transverse

            // Clinical Findings
            $table->string('amniotic_fluid')->nullable();     // Normal / Low / High
            $table->string('placenta_position')->nullable();  // Anterior / Posterior / Low-lying

            // Measurements
            $table->integer('gestational_age_scan')->nullable(); // in weeks
            $table->float('estimated_fetal_weight')->nullable(); // in grams

            // File Upload
            $table->string('report_file')->nullable(); // file path

            // Notes
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ultrasounds');
    }
};
