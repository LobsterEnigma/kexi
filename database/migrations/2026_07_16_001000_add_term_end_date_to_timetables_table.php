<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->date('term_end_date')->nullable()->after('term_start_date');
        });

        DB::table('timetables')
            ->whereNotNull('term_start_date')
            ->orderBy('id')
            ->eachById(function (object $timetable): void {
                DB::table('timetables')
                    ->where('id', $timetable->id)
                    ->update([
                        'term_end_date' => CarbonImmutable::parse($timetable->term_start_date)
                            ->addDays(((int) $timetable->week_count * 7) - 1)
                            ->toDateString(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            $table->dropColumn('term_end_date');
        });
    }
};
