<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
        $table->string('middle_name')->nullable()->after('first_name');

        $table->date('birthdate')->nullable()->after('last_name');

        $table->string('civil_status')->nullable()->after('age');

        $table->boolean('philhealth_member')->default(0)->after('civil_status');

        $table->string('philhealth_number')->nullable()->after('philhealth_member');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn([
            'middle_name',
            'birthdate',
            'civil_status',
            'philhealth_member',
            'philhealth_number'
        ]);
        });
    }
};
