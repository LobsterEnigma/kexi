<?php

namespace Database\Seeders;

use App\Enums\WeekMode;
use App\Models\Timetable;
use App\Models\User;
use App\Services\SiteSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoSeeder is restricted to local and testing environments.');
        }

        app(SiteSettings::class)->setMany([
            'site_name' => '课隙',
            'registration_enabled' => true,
            'sharing_enabled' => true,
        ]);

        DB::transaction(function (): void {
            $user = User::query()->updateOrCreate(
                ['email' => 'demo@kexi.test'],
                ['name' => '林同学', 'password' => 'password'],
            );
            User::query()->updateOrCreate(
                ['email' => 'admin@kexi.test'],
                ['name' => '课隙管理员', 'password' => 'password', 'is_admin' => true],
            );

            $user->timetables()->delete();
            $main = $user->timetables()->create([
                'name' => '主课表',
                'term_name' => '2026 秋季',
                'term_start_date' => '2026-09-07',
                'term_end_date' => '2027-01-10',
                'week_count' => 18,
                'timezone' => 'Asia/Shanghai',
                'near_threshold_minutes' => 30,
                'is_default' => true,
            ]);

            $this->course($main, '高等数学', 'MATH101', [
                'weekday' => 1, 'starts_at' => '08:00', 'ends_at' => '09:30', 'location' => '博学楼 A201',
            ]);
            $this->course($main, '数据结构', 'CS201', [
                'weekday' => 2, 'starts_at' => '09:00', 'ends_at' => '10:30', 'location' => '计算机楼 301',
            ]);
            $this->course($main, '大学英语', 'ENG203', [
                'weekday' => 2, 'starts_at' => '10:40', 'ends_at' => '11:40', 'location' => '文科楼 108',
            ]);
            $this->course($main, '操作系统', 'CS305', [
                'weekday' => 3, 'starts_at' => '10:00', 'ends_at' => '11:30', 'location' => '计算机楼 402',
            ]);
            $this->course($main, '数据库系统', 'CS307', [
                'weekday' => 3, 'starts_at' => '11:00', 'ends_at' => '12:30', 'location' => '计算机楼 405',
            ]);
            $this->course($main, '软件工程实验', 'SE310', [
                'label' => '实验', 'weekday' => 4, 'starts_at' => '16:00', 'ends_at' => '18:00', 'location' => '工程训练中心 5',
            ]);
            $this->course($main, '计算机网络', 'CS309', [
                'weekday' => 5, 'starts_at' => '14:00', 'ends_at' => '15:30', 'location' => '博学楼 B304',
                'week_mode' => WeekMode::Odd->value,
            ]);

            foreach (['周三空闲', '紧凑安排'] as $index => $name) {
                $user->timetables()->create([
                    'name' => $name,
                    'term_name' => '2026 秋季',
                    'term_start_date' => '2026-09-07',
                    'term_end_date' => '2027-01-10',
                    'week_count' => 18,
                    'timezone' => 'Asia/Shanghai',
                    'near_threshold_minutes' => $index === 0 ? 15 : 45,
                    'is_default' => false,
                ]);
            }
        });
    }

    /** @param array<string, mixed> $meeting */
    private function course(Timetable $timetable, string $name, string $code, array $meeting): void
    {
        $course = $timetable->courses()->create(['name' => $name, 'code' => $code]);
        $course->meetings()->create([
            'label' => $meeting['label'] ?? '讲授',
            'teacher' => '示例教师',
            'weekday' => $meeting['weekday'],
            'starts_at' => $meeting['starts_at'],
            'ends_at' => $meeting['ends_at'],
            'location' => $meeting['location'],
            'week_mode' => $meeting['week_mode'] ?? WeekMode::All->value,
            'start_week' => 1,
            'end_week' => 18,
            'specific_weeks' => null,
        ]);
    }
}
