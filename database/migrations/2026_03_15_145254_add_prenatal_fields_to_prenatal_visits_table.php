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
        Schema::table('prenatal_visits', function (Blueprint $table) {
        $table->decimal('temperature', 4, 1)->nullable()->after('weight');

        $table->string('fundic_height')->nullable()->after('gestational_age');

        $table->string('fetal_heart_tone')->nullable()->after('fundic_height');

        $table->string('fetal_movement')->nullable()->after('fetal_heart_tone');

        $table->string('presenting_part')->nullable()->after('fetal_movement');

        $table->string('uterine_activity')->nullable()->after('presenting_part');

        $table->string('cervical_dilation')->nullable()->after('uterine_activity');

        $table->string('bag_of_water')->nullable()->after('cervical_dilation');

        $table->text('assessment')->nullable()->after('bag_of_water');

        $table->text('treatment_plan')->nullable()->after('assessment');

        $table->date('next_visit_date')->nullable()->after('treatment_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
             $table->dropColumn([
            'temperature',
            'fundic_height',
            'fetal_heart_tone',
            'fetal_movement',
            'presenting_part',
            'uterine_activity',
            'cervical_dilation',
            'bag_of_water',
            'assessment',
            'treatment_plan',
            'next_visit_date'
        ]);
        });
    }
};
