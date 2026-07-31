<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('prenatal_visits', 'notes')) {
                $table->text('notes')->nullable()->after('treatment_plan');
            }

            if (!Schema::hasColumn('prenatal_visits', 'recommendation')) {
                $table->text('recommendation')->nullable()->after('notes');
            }
        });
    }

    // Intentionally non-destructive. The `notes` and `recommendation` columns
    // predate this migration (they already exist in the production database,
    // which is why this migration only guards with hasColumn()). Rolling back
    // must never delete data the application has been writing since before
    // this reconciliation migration existed.
    public function down(): void
    {
        // No-op by design: dropping these columns could destroy historical
        // clinical notes on environments where they existed pre-migration.
    }
};
