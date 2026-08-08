<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'previous_cs')) {
                $table->boolean('previous_cs')->nullable();
            }

            if (!Schema::hasColumn('patients', 'miscarriage')) {
                $table->integer('miscarriage')->nullable();
            }
        });
    }

    // Intentionally non-destructive. The `previous_cs` and `miscarriage`
    // columns are consumed by the model, the PatientController validation, the
    // clinical rule engine, and the ML feature array, but were never created by
    // the original migration history. This reconciliation migration only guards
    // with hasColumn() so a clean install and an existing environment both stay
    // safe.
    //
    // Legacy safety: the columns are added as NULLABLE with no explicit default.
    // On environments that lack the columns but already contain patient rows,
    // adding them non-null with a default of false/0 would silently convert
    // unknown or unrecorded obstetric history into confirmed "no previous CS"
    // and "zero miscarriages". Keeping them nullable preserves NULL so unrecorded
    // legacy history stays indistinguishable-from-explicit-negative only at the
    // rule level for CS-01/RM-03, without manufacturing a confirmed negative in
    // the persisted data. All current application consumers (ClinicalRuleEngine,
    // AssessmentContextBuilder, MachineLearningService) already handle NULL, and
    // PatientController requires explicit values on create/update, so new records
    // never rely on a NULL default.
    //
    // No backfill and no historical-patient update is performed.
    // Rolling back must never delete data the application has been writing on
    // environments where these columns predate this migration.
    public function down(): void
    {
        // No-op by design.
    }
};