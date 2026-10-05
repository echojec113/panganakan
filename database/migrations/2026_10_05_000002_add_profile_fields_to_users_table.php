<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the six profile columns that the application already expects on
     * the users table (they exist in production as manually added columns
     * but have never had a migration).
     *
     * Definitions match StaffProfileRequest validation and the isolated
     * test schemas in StaffAccountAdministrationBoundaryTest /
     * StaffProfileBatchOneTest:
     *
     *   - first_name / middle_name / last_name: string(100)
     *   - address: text (validation allows up to 1000 chars)
     *   - contact_number: string(30)
     *   - birthday: date (validated as Y-m-d)
     *
     * Every column is nullable so existing user rows remain valid and no
     * default/backfill of user data is required.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->date('birthday')->nullable();
        });
    }

    /**
     * Reverse ONLY these six newly added columns.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'address',
                'contact_number',
                'birthday',
            ]);
        });
    }
};
