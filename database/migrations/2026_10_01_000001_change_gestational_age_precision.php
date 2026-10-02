<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertValuesFitDecimal('prenatal_visits', 'gestational_age');
        $this->assertValuesFitDecimal('ultrasounds', 'gestational_age_scan');

        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->decimal('gestational_age', 4, 1)->nullable()->change();
        });

        Schema::table('ultrasounds', function (Blueprint $table) {
            $table->decimal('gestational_age_scan', 4, 1)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('prenatal_visits')
            ->whereNotNull('gestational_age')
            ->update(['gestational_age' => DB::raw('ROUND(gestational_age)')]);

        DB::table('ultrasounds')
            ->whereNotNull('gestational_age_scan')
            ->update(['gestational_age_scan' => DB::raw('ROUND(gestational_age_scan)')]);

        Schema::table('prenatal_visits', function (Blueprint $table) {
            $table->integer('gestational_age')->nullable()->change();
        });

        Schema::table('ultrasounds', function (Blueprint $table) {
            $table->integer('gestational_age_scan')->nullable()->change();
        });
    }

    private function assertValuesFitDecimal(string $table, string $column): void
    {
        $hasOutOfRangeValue = DB::table($table)
            ->whereNotNull($column)
            ->where(function ($query) use ($column) {
                $query->where($column, '<', -999)
                    ->orWhere($column, '>', 999);
            })
            ->exists();

        if ($hasOutOfRangeValue) {
            throw new \RuntimeException("Cannot change {$table}.{$column} to DECIMAL(4,1): existing values exceed its range.");
        }
    }
};
