<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a dedicated image column to the ultrasound records.
     *
     * The legacy single `report_file` column held either an image or a PDF, so
     * a record could never carry both at the same time and the UI could not
     * distinguish "View Image" from "View PDF". A second column is required to
     * support an image AND a PDF report on the same record.
     *
     * Existing image uploads are re-homed from `report_file` into
     * `report_image` (by file extension) so historical records render correctly.
     * PDFs keep the `report_file` column. No physical files are moved.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('ultrasounds', 'report_image')) {
            Schema::table('ultrasounds', function (Blueprint $table) {
                $table->string('report_image')->nullable()->after('report_file');
            });
        }

        $rows = DB::table('ultrasounds')->whereNotNull('report_file')->get();
        foreach ($rows as $row) {
            $extension = strtolower(pathinfo((string) $row->report_file, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                DB::table('ultrasounds')->where('id', $row->id)->update([
                    'report_image' => $row->report_file,
                    'report_file' => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ultrasounds', 'report_image')) {
            Schema::table('ultrasounds', function (Blueprint $table) {
                $table->dropColumn('report_image');
            });
        }
    }
};