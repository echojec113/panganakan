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
        Schema::table('birth_plans', function (Blueprint $table) {

            $table->integer('planned_visits')->nullable()->after('patient_id');

            $table->boolean('deliver_in_clinic')->default(0)->after('planned_visits');

            $table->boolean('transport_cost')->default(0)->after('transportation');

            $table->boolean('saving_started')->default(0)->after('payment_method');

            $table->string('caregiver_home')->nullable()->after('birth_companion');

            $table->boolean('plan_more_children')->default(0)->after('caregiver_home');

            $table->integer('number_more_children')->nullable()->after('plan_more_children');

            $table->boolean('knows_fp_method')->default(0)->after('number_more_children');

            $table->boolean('used_fp_before')->default(0)->after('knows_fp_method');

            $table->string('fp_source')->nullable()->after('family_planning_method');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('birth_plans', function (Blueprint $table) {

            $table->dropColumn([
                'planned_visits',
                'deliver_in_clinic',
                'transport_cost',
                'saving_started',
                'caregiver_home',
                'plan_more_children',
                'number_more_children',
                'knows_fp_method',
                'used_fp_before',
                'fp_source'
            ]);
        });
    }
};
