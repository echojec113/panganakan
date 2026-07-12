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
            $table->timestamp('reminder_tomorrow_sent_at')->nullable()->after('next_visit_date');
            $table->timestamp('reminder_today_sent_at')->nullable()->after('reminder_tomorrow_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->dropColumn(['reminder_tomorrow_sent_at', 'reminder_today_sent_at']);
        });
    }
};
