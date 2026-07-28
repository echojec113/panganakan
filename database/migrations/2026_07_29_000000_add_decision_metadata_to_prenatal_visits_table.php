<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->string('decision_source')->nullable()->after('risk_reasons');
            $table->json('missing_records')->nullable()->after('decision_source');
            $table->json('rule_reasons')->nullable()->after('missing_records');
            $table->string('ml_prediction')->nullable()->after('rule_reasons');
            $table->boolean('ml_valid')->default(false)->after('ml_prediction');
        });
    }

    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->dropColumn(['decision_source', 'missing_records', 'rule_reasons', 'ml_prediction', 'ml_valid']);
        });
    }
};
