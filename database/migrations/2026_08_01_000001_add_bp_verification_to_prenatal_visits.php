<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->integer('repeat_bp_sys')->nullable()->after('bp_dia');
            $table->integer('repeat_bp_dia')->nullable()->after('repeat_bp_sys');
            $table->timestamp('repeat_bp_recorded_at')->nullable()->after('repeat_bp_dia');
            $table->unsignedBigInteger('repeat_bp_recorded_by')->nullable()->after('repeat_bp_recorded_at');

            $table->foreign('repeat_bp_recorded_by', 'fk_repeat_bp_recorded_by')
                ->references('id')->on('users')->nullOnDelete();

            $table->string('bp_verification_status', 30)->nullable()->after('repeat_bp_recorded_by');
            $table->string('urgency', 30)->nullable()->after('bp_verification_status');
            $table->json('bp_assessment')->nullable()->after('urgency');
        });
    }

    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->dropForeign('fk_repeat_bp_recorded_by');
            $table->dropColumn([
                'repeat_bp_sys',
                'repeat_bp_dia',
                'repeat_bp_recorded_at',
                'repeat_bp_recorded_by',
                'bp_verification_status',
                'urgency',
                'bp_assessment',
            ]);
        });
    }
};
