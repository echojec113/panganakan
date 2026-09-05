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
        $table->softDeletes();
    });

    Schema::table('prenatal_visits', function (Blueprint $table) {
        $table->softDeletes();
    });

    Schema::table('ultrasounds', function (Blueprint $table) {
        $table->softDeletes();
    });

    Schema::table('medical_histories', function (Blueprint $table) {
        $table->softDeletes();
    });

    Schema::table('birth_plans', function (Blueprint $table) {
        $table->softDeletes();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('all_tables', function (Blueprint $table) {
            //
        });
    }
};
