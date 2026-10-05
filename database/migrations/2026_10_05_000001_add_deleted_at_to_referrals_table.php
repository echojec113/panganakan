<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the soft-delete column required by the Referral Archive workflow.
     *
     * Archived referrals keep their row (and full history) and are hidden from
     * normal Eloquent queries until restored. Historical migration files are
     * never modified.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
