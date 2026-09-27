<?php

namespace App\Services;

use App\Models\AcademicTask;
use App\Models\CalendarImport;
use App\Models\CourseMeeting;
use App\Models\PersonalEvent;
use App\Models\Timetable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarImportWriter
{
    public function duplicates(Timetable $timetable, array $rows): array
    {
        $found = [];
        foreach (DB::table('calendar_import_items')->where('user_id', $timetable->user_id)->where(fn ($query) => $query->where('timetable_id', $timetable->id)->orWhere('target_type', 'personal'))->whereIn('fingerprint', array_column($rows, 'fingerprint'))->get() as $item) {
            $class = match ($item->target_type) {
                'course' => CourseMeeting::class, 'personal' => PersonalEvent::class, default => AcademicTask::class
            };
            if ($class::whereKey($item->target_id)->exists()) {
                $found[] = $item->fingerprint;
            }
        }

        return $found;
    }

    public function import(Timetable $timetable, CalendarImport $batch, array $selected, array $kinds): array
    {
        return DB::transaction(function () use ($timetable, $batch, $selected, $kinds) {
            User::whereKey($timetable->user_id)->lockForUpdate()->firstOrFail();
            $batch = CalendarImport::whereKey($batch->id)->lockForUpdate()->first();
            if (! $batch || $batch->expires_at->isPast()) {
                throw ValidationException::withMessages(['calendar' => '预览已过期或已经导入，请重新上传。']);
            }
            $rows = $batch->payload['rows'];
            $duplicates = $this->duplicates($timetable, $rows);
            $courses = [];
            $meetings = [];
            $created = 0;
            $skipped = 0;
            foreach (array_unique($selected) as $index) {
                if (! isset($rows[$index])) {
                    throw ValidationException::withMessages(['calendar' => '选择的项目无效，请重新预览。']);
                }
                $row = $rows[$index];
                $kind = $kinds[$index] ?? 'personal';
                if (in_array($row['fingerprint'], $duplicates, true)) {
                    $skipped++;

                    continue;
                }
                $a = CarbonImmutable::parse($row['starts_at'])->timezone($timetable->timezone);
                $b = CarbonImmutable::parse($row['ends_at'])->timezone($timetable->timezone);
                if ($row['all_day']) {
                    $a = CarbonImmutable::parse($row['date_start'], $timetable->timezone);
                    $b = CarbonImmutable::parse($row['date_end'], $timetable->timezone);
                }
                if ($kind === 'course') {
                    if (! $timetable->term_start_date || $row['all_day'] || $a->gte($b) || ! $a->isSameDay($b) || $a->toDateString() < $timetable->term_start_date->toDateString() || $a->toDateString() > $timetable->resolvedTermEndDate()->toDateString() || mb_strlen($row['title']) > 120 || mb_strlen($row['location']) > 120) {
                        throw ValidationException::withMessages(['calendar' => '「'.$row['title'].'」不适合作为课程：需要学期内同一天的起止时间，名称和地点最多 120 字。可改选个人安排或学业活动。']);
                    }
                    $courseKey = $row['series'].'|'.$row['title'];
                    if (mb_strlen($row['notes']) > 2000) {
                        throw ValidationException::withMessages(['calendar' => '「'.$row['title'].'」的课程备注超过 2000 字，请精简后导入。']);
                    }
                    $course = $courses[$courseKey] ??= $timetable->courses()->create(['name' => $row['title'], 'notes' => $row['notes']]);
                    $week = (int) floor(CarbonImmutable::instance($timetable->weekStartDate())->diffInDays($a->startOfDay()) / 7) + 1;
                    $meetingKey = $courseKey.'|'.$a->isoWeekday().'|'.$a->format('H:i').'|'.$b->format('H:i').'|'.$row['location'];
                    if (isset($meetings[$meetingKey])) {
                        $target = $meetings[$meetingKey];
                        $target->update(['specific_weeks' => array_values(array_unique([...$target->specific_weeks, $week]))]);
                    } else {
                        if ($course->meetings()->count() >= 20) {
                            throw ValidationException::withMessages(['calendar' => '「'.$row['title'].'」包含超过 20 种时间段，请拆分课程或改为个人安排。']);
                        }
                        $target = $meetings[$meetingKey] = $course->meetings()->create(['label' => '导入课程', 'weekday' => $a->isoWeekday(), 'starts_at' => $a->format('H:i'), 'ends_at' => $b->format('H:i'), 'location' => $row['location'], 'week_mode' => 'specific', 'specific_weeks' => [$week]]);
                    }
                } elseif ($kind === 'personal') {
                    if ($b->lte($a)) {
                        throw ValidationException::withMessages(['calendar' => '「'.$row['title'].'」没有活动时长，请选择「提交截止」。']);
                    }
                    $target = $timetable->user->personalEvents()->create(['title' => $row['title'], 'category' => '日历导入', 'color' => '#6686b8', 'timezone' => $timetable->timezone, 'starts_at' => $a->utc(), 'ends_at' => $b->utc(), 'all_day' => $row['all_day'], 'location' => $row['location'], 'notes' => $row['notes'], 'repeat' => 'none']);
                } else {
                    if ($kind === 'event' && ($row['all_day'] || $b->lte($a))) {
                        throw ValidationException::withMessages(['calendar' => '「'.$row['title'].'」不是定时活动，请选择个人安排或提交截止。']);
                    }
                    $target = $timetable->academicTasks()->create(['title' => $row['title'], 'type' => 'other', 'location' => $row['location'], 'notes' => $row['notes'], 'completed_at' => $row['completed'] ? now() : null,
                        'due_at' => $kind === 'deadline' ? ($row['all_day'] ? $b->subDay()->setTime(23, 59)->utc() : $a->utc()) : null,
                        'starts_at' => $kind === 'event' ? $a->utc() : null, 'ends_at' => $kind === 'event' ? $b->utc() : null]);
                }
                DB::table('calendar_import_items')->updateOrInsert(['user_id' => $timetable->user_id, 'timetable_id' => $timetable->id, 'fingerprint' => $row['fingerprint']], ['target_type' => $kind, 'target_id' => $target->id, 'created_at' => now(), 'updated_at' => now()]);
                $created++;
            }
            $batch->delete();

            return [$created, $skipped];
        }, 3);
    }
}
