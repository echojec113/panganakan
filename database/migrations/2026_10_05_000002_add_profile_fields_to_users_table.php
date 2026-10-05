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
        // Each column is added only when it does not exist yet, so this
        // migration is safe both on fresh databases (none present) and on
        // databases where the columns were added manually before a
        // migration existed. Existing columns are never altered.
        $columns = [
            'first_name'     => fn (Blueprint $table) => $table->string('first_name', 100)->nullable(),
            'middle_name'    => fn (Blueprint $table) => $table->string('middle_name', 100)->nullable(),
            'last_name'      => fn (Blueprint $table) => $table->string('last_name', 100)->nullable(),
            'address'        => fn (Blueprint $table) => $table->text('address')->nullable(),
            'contact_number' => fn (Blueprint $table) => $table->string('contact_number', 30)->nullable(),
            'birthday'       => fn (Blueprint $table) => $table->date('birthday')->nullable(),
        ];

        foreach ($columns as $name => $definition) {
            if (Schema::hasColumn('users', $name)) {
                continue;
            }

            Schema::table('users', $definition);
        }
    }

    /**
     * Reverse ONLY these six columns, and only when each one exists.
     */
    public function down(): void
    {
        $columns = ['first_name', 'middle_name', 'last_name', 'address', 'contact_number', 'birthday'];

        $existing = array_values(array_filter(
            $columns,
            fn (string $name) => Schema::hasColumn('users', $name)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
