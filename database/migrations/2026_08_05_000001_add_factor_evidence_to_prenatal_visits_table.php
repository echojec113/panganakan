<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 12 — structured clinical factor evidence.
     *
     * Adds a single nullable JSON column storing the serialized
     * ClinicalFactorEvidence list for a prenatal visit. Additive and nullable:
     * no existing column or clinical data is modified. Legacy visits keep a
     * null factor_evidence and continue rendering from rule_reasons /
     * risk_reasons fallbacks.
     */
    public function up(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->json('factor_evidence')->nullable()->after('bp_assessment');
        });
    }

    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->dropColumn('factor_evidence');
        });
    }
};
