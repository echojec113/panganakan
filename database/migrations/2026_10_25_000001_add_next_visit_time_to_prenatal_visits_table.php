<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEPLA Phase 3 (prenatal follow-up appointments) - add the clock half of
 * the Next Visit schedule to the existing prenatal_visits table.
 *
 * ADDITIVE ONLY: one nullable time column, no column is renamed, dropped
 * or rewritten, and every existing date-only row keeps working exactly as
 * before (next_visit_time stays NULL, which is the historical date-only
 * state). NULL is the semantic default: a risk-generated suggested date
 * never carries a time, so it can never be mistaken for a confirmed
 * follow-up appointment.
 *
 * The column is deliberately stored as a plain string (Laravel 'time'
 * column, no Eloquent date cast) so it stays a bare HH:MM clock value -
 * matching how appointments.requested_time / confirmed_time are already
 * treated (see Appointment::$casts) and avoiding SQLite's
 * 'Y-m-d 00:00:00' serialization pitfall that AppointmentSlotHold
 * documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->time('next_visit_time')
                ->nullable()
                ->after('next_visit_date');
        });
    }

    public function down(): void
    {
        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->dropColumn('next_visit_time');
        });
    }
};
