<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('timetables')->whereNotNull('term_start_date')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $anchor = CarbonImmutable::parse($row->term_start_date)->startOfWeek(CarbonImmutable::MONDAY);
                if (! $row->term_end_date) {
                    continue;
                }
                $weeks = (int) ceil(($anchor->diffInDays(CarbonImmutable::parse($row->term_end_date)) + 1) / 7);
                if ($weeks <= $row->week_count) {
                    continue;
                }
                DB::transaction(function () use ($row, $weeks) {
                    // Preserve the saved end date and full-term course ranges when the first week is partial.
                    DB::table('timetables')->where('id', $row->id)->update(['week_count' => $weeks]);
                    DB::table('course_meetings')->whereIn('course_id', DB::table('courses')->where('timetable_id', $row->id)->select('id'))
                        ->where('end_week', $row->week_count)->where('week_mode', '!=', 'specific')->update(['end_week' => $weeks]);
                });
            }
        });
        DB::table('course_cancellation_records')->whereNotNull('occurrence_date')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $date = CarbonImmutable::parse($row->occurrence_date);
                $offset = ($date->isoWeekday() - (int) $row->weekday + 7) % 7;
                if ($offset) {
                    DB::table('course_cancellation_records')->where('id', $row->id)->update(['occurrence_date' => $date->subDays($offset)->toDateString()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Date corrections are retained on rollback; restore a database backup to recover pre-fix dates.
    }
};
