<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medical_histories', function (Blueprint $table) {
            $table->boolean('diabetes')->default(false)->after('std_history');
            $table->boolean('hypertension')->default(false)->after('diabetes');
            $table->boolean('asthma')->default(false)->after('hypertension');
            $table->boolean('thyroid_disease')->default(false)->after('asthma');
            $table->boolean('heart_disease')->default(false)->after('thyroid_disease');
            $table->boolean('anemia')->default(false)->after('heart_disease');
            $table->boolean('mental_health_condition')->default(false)->after('anemia');
            $table->string('other_specify', 255)->nullable()->after('mental_health_condition');
        });
    }

    public function down(): void
    {
        Schema::table('medical_histories', function (Blueprint $table) {
            $table->dropColumn([
                'diabetes',
                'hypertension',
                'asthma',
                'thyroid_disease',
                'heart_disease',
                'anemia',
                'mental_health_condition',
                'other_specify',
            ]);
        });
    }
};
