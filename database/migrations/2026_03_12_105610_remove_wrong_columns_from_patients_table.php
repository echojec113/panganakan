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
        Schema::table('patients', function (Blueprint $table) {
             $table->dropColumn([
            'blood_pressure',
            'gestational_age',
            'risk_level'
        ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
             $table->string('blood_pressure')->nullable();
        $table->integer('gestational_age')->nullable();
        $table->string('risk_level')->nullable();
        });
    }
};
