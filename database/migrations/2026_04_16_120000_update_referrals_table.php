<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Add doctor_name if not exists
            if (!Schema::hasColumn('referrals', 'doctor_name')) {
                $table->string('doctor_name')->nullable()->after('referred_to');
            }

            // Add completed_at if not exists
            if (!Schema::hasColumn('referrals', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
            }

            // Modify status enum to include Cancelled
            if (Schema::hasColumn('referrals', 'status')) {
                $table->enum('status', ['Pending', 'Completed', 'Cancelled'])
                    ->default('Pending')
                    ->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            if (Schema::hasColumn('referrals', 'doctor_name')) {
                $table->dropColumn('doctor_name');
            }
            if (Schema::hasColumn('referrals', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
