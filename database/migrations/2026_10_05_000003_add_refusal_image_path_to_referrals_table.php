<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ONE nullable relative storage path for the optional supporting
 * image uploaded when staff record a referral refusal (evidence/proof
 * documentation). Only the path is stored - never binary/base64 data.
 *
 * Guarded with Schema::hasColumn following the pattern established by
 * `2026_08_09_000001_add_referral_integration_to_referrals_table.php`
 * so re-running (or a partially applied state) cannot fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referrals') && ! Schema::hasColumn('referrals', 'refusal_image_path')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->string('refusal_image_path')->nullable()->after('refusal_notes');
            });
        }
    }

    public function down(): void
    {
        // Drops ONLY refusal_image_path; no other column is touched.
        if (Schema::hasTable('referrals') && Schema::hasColumn('referrals', 'refusal_image_path')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->dropColumn('refusal_image_path');
            });
        }
    }
};
