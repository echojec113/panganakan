<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 13 — context-aware assessment metadata.
     *
     * Adds a single nullable JSON column storing the serialized assessment
     * metadata document: the AssessmentContext snapshot, interaction evidence
     * (currently zero ACTIVE), data-quality verification flags, the decision
     * trace, and the versions used. Additive and nullable: no existing column
     * or clinical data is modified, and legacy visits keep a null
     * assessment_metadata. No backfill is performed.
     */
    public function up(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->json('assessment_metadata')->nullable()->after('factor_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->dropColumn('assessment_metadata');
        });
    }
};