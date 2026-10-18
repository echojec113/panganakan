<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive, reversible soft-delete support for baby records so patient
     * archival soft-deletes (instead of permanently deleting) baby rows.
     * The babies table already exists; this only adds the deleted_at column
     * and preserves every existing row.
     */
    public function up(): void
    {
        Schema::table('babies', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('babies', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
