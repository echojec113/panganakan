<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive-only: the legacy `address` column is left completely
     * untouched. These three new columns are nullable so every existing
     * (legacy) patient record remains valid without any backfill/parsing.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('address_line')->nullable()->after('address');
            $table->string('barangay')->nullable()->after('address_line');
            $table->string('city_municipality')->nullable()->after('barangay');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['address_line', 'barangay', 'city_municipality']);
        });
    }
};
