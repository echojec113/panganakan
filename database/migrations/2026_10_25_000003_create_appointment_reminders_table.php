<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->string('reminder_type', 24);
            $table->date('confirmed_date');
            $table->time('confirmed_time');
            $table->string('status', 16); // sending | sent | missed
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // A reschedule creates a new schedule key and therefore a new
            // independent reminder set for each reminder type.
            $table->unique(
                ['appointment_id', 'reminder_type', 'confirmed_date', 'confirmed_time'],
                'appointment_reminders_schedule_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};
