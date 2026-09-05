<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->json('type_colors')->nullable();
        });
        Schema::table('course_meeting_cancellations', function (Blueprint $table) {
            $table->string('reason', 30)->default('other');
            $table->text('note')->nullable();
        });
        Schema::create('course_cancellation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('course_name', 120);
            $table->string('course_code', 60)->nullable();
            $table->string('label', 40)->nullable();
            $table->unsignedSmallInteger('week_number');
            $table->unsignedTinyInteger('weekday');
            $table->date('occurrence_date')->nullable();
            $table->string('starts_at', 8);
            $table->string('ends_at', 8);
            $table->string('action', 20);
            $table->string('reason', 30);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['timetable_id', 'occurrence_date']);
        });

        // Preserve existing cancellations as legacy records without inventing a reason.
        DB::table('course_meeting_cancellations as x')
            ->join('course_meetings as m', 'm.id', '=', 'x.course_meeting_id')
            ->join('courses as c', 'c.id', '=', 'm.course_id')
            ->join('timetables as t', 't.id', '=', 'c.timetable_id')
            ->select('x.*', 'm.course_id', 'm.label', 'm.weekday', 'm.starts_at', 'm.ends_at', 'c.timetable_id', 'c.name', 'c.code', 't.term_start_date')
            ->orderBy('x.id')->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('course_cancellation_records')->insert([
                        'timetable_id' => $row->timetable_id, 'course_id' => $row->course_id,
                        'course_meeting_id' => $row->course_meeting_id, 'course_name' => $row->name,
                        'course_code' => $row->code, 'label' => $row->label,
                        'week_number' => $row->week_number, 'weekday' => $row->weekday,
                        'occurrence_date' => $row->term_start_date ? Carbon::parse($row->term_start_date)->addWeeks($row->week_number - 1)->addDays($row->weekday - 1)->toDateString() : null,
                        'starts_at' => $row->starts_at, 'ends_at' => $row->ends_at,
                        'action' => 'canceled', 'reason' => 'legacy', 'note' => null,
                        'created_at' => $row->created_at, 'updated_at' => $row->updated_at,
                    ]);
                }
            });
        DB::table('course_meeting_cancellations')->update(['reason' => 'legacy']);
    }

    public function down(): void
    {
        Schema::dropIfExists('course_cancellation_records');
        Schema::table('course_meeting_cancellations', fn (Blueprint $table) => $table->dropColumn(['reason', 'note']));
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('type_colors'));
    }
};
