<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Validation\ValidationException;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\TimeZoneUtil;
use Throwable;

class CalendarImportParser
{
    public function upload(string $text, string $fallbackTimezone, string $fallbackFrom, string $fallbackTo, ?string $from = null, ?string $to = null, ?string $timezoneOverride = null): array
    {
        $calendar = $this->readCalendar($text);
        $timezone = $timezoneOverride ?: $fallbackTimezone;
        $notices = [];
        $calendarZone = trim((string) $calendar->{'X-WR-TIMEZONE'});
        if (! $timezoneOverride && $calendarZone !== '') {
            try {
                $timezone = (new DateTimeZone($calendarZone))->getName();
            } catch (Throwable) {
                $notices[] = '文件的日历默认时区无法识别；未标注时区的时间按 '.$timezone.' 解释，可在更多选项中调整。';
            }
        }
        $floating = false;
        $unbounded = false;
        foreach ([...$calendar->select('VEVENT'), ...$calendar->select('VTODO')] as $event) {
            foreach (['DTSTART', 'DTEND', 'DUE', 'RDATE', 'EXDATE', 'RECURRENCE-ID'] as $field) {
                foreach ($event->select($field) as $property) {
                    foreach ($property->getParts() as $value) {
                        if (str_contains($value, 'T') && ! str_ends_with($value, 'Z') && ! isset($property['TZID'])) {
                            $floating = true;
                        }
                    }
                }
            }
            if (isset($event->RRULE) && ! preg_match('/(?:^|;)(COUNT|UNTIL)=/i', (string) $event->RRULE)) {
                $unbounded = true;
            }
        }
        if ($floating) {
            $notices[] = '文件中有未单独标注时区的时间，按 '.$timezone.' 解释'.($calendarZone === $timezone && ! $timezoneOverride ? '（来自文件的日历时区）。' : '；如与原日历不符，可在更多选项中调整。');
        }
        if ($from && $to) {
            $payload = $this->parse($text, $timezone, $from, $to);
            $notices[] = '已使用你指定的日期范围。';
        } elseif ($unbounded) {
            $payload = $this->parse($text, $timezone, $fallbackFrom, $fallbackTo);
            $notices[] = '文件包含没有结束日期的重复安排，本次按当前学期 '.$fallbackFrom.' — '.$fallbackTo.' 预览；范围外不会导入，可在更多选项中调整。';
        } else {
            $payload = $this->parse($text, $timezone, '2000-01-01', '2099-12-31');
            if ($payload['rows']) {
                $dates = array_column($payload['rows'], 'date_start');
                $payload['from'] = min($dates);
                $payload['to'] = max($dates);
                if (CarbonImmutable::parse($payload['from'])->diffInDays(CarbonImmutable::parse($payload['to'])) > 366) {
                    $this->fail('文件跨越超过一年，请展开「更多选项」，选择需要导入的日期范围。');
                }
            } else {
                $payload['from'] = $fallbackFrom;
                $payload['to'] = $fallbackTo;
            }
            $notices[] = '已根据文件中的安排自动识别日期范围。';
        }
        $payload['notices'] = $notices;

        return $payload;
    }

    public function parse(string $text, string $timezone, string $from, string $to): array
    {
        $calendar = $this->readCalendar($text);
        $components = [...$calendar->select('VEVENT'), ...$calendar->select('VTODO')];
        if (count($components) > 500) {
            $this->fail('文件项目过多，请拆分为每份不超过 500 个原始项目。');
        }
        $zone = new DateTimeZone($timezone);
        $start = CarbonImmutable::parse($from, $zone)->startOfDay();
        $end = CarbonImmutable::parse($to, $zone)->endOfDay();
        $rows = [];
        $warnings = [];
        $groups = [];
        $iterations = 0;
        $skipped = 0;
        foreach ($components as $component) {
            $uid = (string) $component->UID;
            if ($uid === '') {
                $uid = hash('sha256', $component->serialize());
                $component->UID = $uid;
            }
            $groups[$component->name.':'.$uid][] = $component;
        }
        foreach ($groups as $events) {
            $label = mb_substr((string) $events[0]->SUMMARY ?: '未命名安排', 0, 80);
            $groupRows = [];
            try {
                foreach ($events as $event) {
                    foreach (['DTSTART', 'DTEND', 'DUE', 'RECURRENCE-ID', 'EXDATE', 'RDATE'] as $field) {
                        foreach ($event->select($field) as $property) {
                            foreach ($property->getParts() as $dateValue) {
                                $this->validateDateValue($dateValue);
                            }
                            if (isset($property['TZID'])) {
                                TimeZoneUtil::getTimeZone((string) $property['TZID'], $calendar, true);
                            }
                            foreach ($property->getDateTimes($zone) as $index => $parsed) {
                                $raw = $property->getParts()[$index];
                                if ($parsed->format(str_contains($raw, 'T') ? 'Ymd\THis' : 'Ymd') !== rtrim($raw, 'Z')) {
                                    throw new \RuntimeException('日期或当地时间不存在，请检查时区与夏令时');
                                }
                            }
                        }
                    }
                    if (isset($event->{'RECURRENCE-ID'}['RANGE']) || isset($event->EXRULE)) {
                        throw new \RuntimeException('包含不支持的整段改期或 EXRULE 规则');
                    }
                    if (isset($event->RRULE)) {
                        $this->validateRule((string) $event->RRULE);
                    }
                    if (isset($event->RRULE) && isset($event->RDATE)) {
                        throw new \RuntimeException('同时包含 RRULE 与 RDATE，请在原日历拆分后导入');
                    }
                    if (isset($event->DTEND) && isset($event->DURATION)) {
                        throw new \RuntimeException('结束时间和持续时长不能同时填写');
                    }
                    if ($event->name === 'VEVENT' && ! isset($event->DTSTART)) {
                        if (isset($event->{'RECURRENCE-ID'}) && (string) $event->STATUS === 'CANCELLED') {
                            $property = clone $event->{'RECURRENCE-ID'};
                            $property->name = 'DTSTART';
                            $event->add($property);
                        } else {
                            throw new \RuntimeException('缺少开始时间');
                        }
                    }
                }
                if ($events[0]->name === 'VTODO') {
                    foreach ($events as $event) {
                        if ((string) $event->STATUS === 'CANCELLED') {
                            $skipped++;

                            continue;
                        }
                        if (! isset($event->DUE) || isset($event->RRULE)) {
                            throw new \RuntimeException('待办需要截止时间；暂不支持重复 VTODO');
                        }
                        $due = CarbonImmutable::instance($event->DUE->getDateTime($zone));
                        if (! $event->DUE->hasTime()) {
                            $due = $due->setTime(23, 59);
                        }
                        if ($due->betweenIncluded($start, $end)) {
                            $groupRows[] = $this->row($event, $due, $due, false, true, $timezone);
                        }
                    }
                } else {
                    $master = collect($events)->first(fn ($event) => ! isset($event->{'RECURRENCE-ID'})) ?? $events[0];
                    $iterator = new EventIterator($events, null, $zone);
                    while ($iterator->valid()) {
                        if (++$iterations > 10000) {
                            $this->fail('重复规则计算量过大，请从原日历导出较短的日期范围。');
                        }
                        $a = CarbonImmutable::instance($iterator->getDtStart());
                        $b = CarbonImmutable::instance($iterator->getDtEnd());
                        if ($a->gt($end)) {
                            break;
                        }
                        if ($a->gte($start)) {
                            $event = $iterator->getEventObject();
                            // A DATE recurrence uses calendar days, not 86400-second periods across DST.
                            if (! $event->DTSTART->hasTime()) {
                                $source = in_array($event, $events, true) ? $event : $master;
                                $sourceStart = $source->DTSTART->getDateTime($zone);
                                $sourceEnd = isset($source->DTEND) ? $source->DTEND->getDateTime($zone) : (isset($source->DURATION) ? $sourceStart->add($source->DURATION->getDateInterval()) : $sourceStart->modify('+1 day'));
                                $days = (int) CarbonImmutable::parse($sourceStart->format('Y-m-d'), 'UTC')->diffInDays(CarbonImmutable::parse($sourceEnd->format('Y-m-d'), 'UTC'));
                                if ($days < 1 || $days > 7) {
                                    throw new \RuntimeException('全天安排的天数无效或超过 7 天');
                                }
                                $a = CarbonImmutable::parse($a->format('Y-m-d'), $zone);
                                $b = $a->addDays($days);
                            }
                            if ((string) $event->STATUS === 'CANCELLED') {
                                $skipped++;
                            } else {
                                $groupRows[] = $this->row($event, $a, $b, ! $event->DTSTART->hasTime(), false, $timezone);
                            }
                        }
                        if (count($rows) + count($groupRows) > 400) {
                            $this->fail('所选范围超过 400 次安排，请缩小日期范围后重新预览。');
                        }
                        $iterator->next();
                    }
                }
                array_push($rows, ...$groupRows);
                if (count($rows) > 400) {
                    $this->fail('所选范围超过 400 次安排，请缩小日期范围后重新预览。');
                }
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $warnings[] = $label.'：未导入此系列。'.$this->reason($exception);
            }
        }
        foreach ($calendar->children() as $component) {
            if ($component instanceof Component && ! in_array($component->name, ['VEVENT', 'VTODO', 'VTIMEZONE'], true)) {
                $warnings[] = '未导入 '.$component->name.' 类型的内容。';
            }
        }
        if ($skipped) {
            $warnings[] = '已跳过 '.$skipped.' 次标记为取消的安排。';
        }
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row['fingerprint']] = $row;
        }
        if (count($rows) !== count($unique)) {
            $warnings[] = '文件内的重复项目已合并。';
        }
        $rows = array_values($unique);
        usort($rows, fn ($a, $b) => [$a['starts_at'], $a['title']] <=> [$b['starts_at'], $b['title']]);

        return ['rows' => $rows, 'warnings' => array_values(array_unique($warnings)), 'from' => $from, 'to' => $to, 'timezone' => $timezone];
    }

    private function row($event, CarbonImmutable $start, CarbonImmutable $end, bool $allDay, bool $deadline, string $timezone): array
    {
        if ($end->lt($start) || $end->gt($start->addDays(7))) {
            throw new \RuntimeException('起止时间无效或单次活动超过 7 天');
        }
        if ($start->second !== 0 || $end->second !== 0) {
            throw new \RuntimeException('含秒级时间，请调整为整分钟后导入');
        }
        $title = trim((string) $event->SUMMARY) ?: '未命名安排';
        $location = (string) $event->LOCATION;
        $notes = (string) $event->DESCRIPTION;
        if (mb_strlen($title) > 160 || mb_strlen($location) > 200 || mb_strlen($notes) > 4000) {
            throw new \RuntimeException('名称、地点或备注过长，请在原日历精简后导入');
        }
        $identity = (string) ($event->{'RECURRENCE-ID'} ?? $event->DTSTART ?? $event->DUE);
        if ($allDay) {
            $start = CarbonImmutable::parse($start->format('Y-m-d'), $timezone);
            $end = CarbonImmutable::parse($end->format('Y-m-d'), $timezone);
        }

        return ['fingerprint' => hash('sha256', (string) $event->UID.'|'.$identity), 'series' => hash('sha256', (string) $event->UID),
            'title' => $title, 'location' => $location, 'notes' => $notes, 'all_day' => $allDay, 'deadline' => $deadline,
            'date_start' => $start->toDateString(), 'date_end' => $end->toDateString(),
            'starts_at' => $start->utc()->toIso8601String(), 'ends_at' => $end->utc()->toIso8601String(),
            'completed' => (string) $event->STATUS === 'COMPLETED'];
    }

    private function validateRule(string $rule): void
    {
        $parts = [];
        foreach (explode(';', strtoupper($rule)) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }
        if (! in_array($parts['FREQ'] ?? '', ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            throw new \RuntimeException('仅支持按天、周、月、年重复');
        }
        $allowed = ['FREQ', 'UNTIL', 'COUNT', 'INTERVAL', ...match ($parts['FREQ']) {
            'DAILY' => ['BYDAY', 'BYMONTH'], 'WEEKLY' => ['BYDAY', 'WKST'],
            'MONTHLY' => ['BYMONTHDAY', 'BYDAY', 'BYSETPOS'], 'YEARLY' => ['BYMONTH', 'BYMONTHDAY', 'BYDAY'],
        }];
        if (array_diff(array_keys($parts), $allowed)) {
            throw new \RuntimeException('重复规则包含暂不支持的选项');
        }
        if (isset($parts['UNTIL'])) {
            $this->validateDateValue($parts['UNTIL']);
        }
        if (isset($parts['BYDAY'])) {
            foreach (explode(',', $parts['BYDAY']) as $day) {
                $pattern = in_array($parts['FREQ'], ['DAILY', 'WEEKLY'], true) ? '/^(MO|TU|WE|TH|FR|SA|SU)$/' : '/^([+-]?[1-5])?(MO|TU|WE|TH|FR|SA|SU)$/';
                if (! preg_match($pattern, $day)) {
                    throw new \RuntimeException('重复星期规则无效');
                }
            }
        }
        foreach (['BYMONTH' => 12, 'BYMONTHDAY' => 31, 'BYSETPOS' => 31] as $key => $max) {
            if (isset($parts[$key])) {
                foreach (explode(',', $parts[$key]) as $value) {
                    if (! preg_match('/^-?\d+$/', $value) || (int) $value === 0 || abs((int) $value) > $max || ($key === 'BYMONTH' && (int) $value < 1)) {
                        throw new \RuntimeException('重复日期规则无效');
                    }
                }
            }
        }
        if ($parts['FREQ'] === 'YEARLY' && isset($parts['BYMONTHDAY']) && ! isset($parts['BYMONTH'])) {
            throw new \RuntimeException('按年重复的指定日期需要同时指定月份');
        }
        if (isset($parts['WKST']) && ! in_array($parts['WKST'], ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'], true)) {
            throw new \RuntimeException('每周起始日无效');
        }
        if (isset($parts['INTERVAL']) && (! ctype_digit($parts['INTERVAL']) || (int) $parts['INTERVAL'] < 1 || (int) $parts['INTERVAL'] > 366)) {
            throw new \RuntimeException('重复间隔无效');
        }
        if (isset($parts['COUNT']) && (! ctype_digit($parts['COUNT']) || (int) $parts['COUNT'] < 1 || (int) $parts['COUNT'] > 10000)) {
            throw new \RuntimeException('重复次数过大或无效');
        }
    }

    private function reason(Throwable $exception): string
    {
        return $exception instanceof \RuntimeException && ! str_starts_with($exception::class, 'Sabre\\') ? $exception->getMessage() : '日期、时区或重复规则无法可靠解析，请检查原文件。';
    }

    private function validateDateValue(string $value): void
    {
        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})Z?)?$/', $value, $match)
            || ! checkdate((int) $match[2], (int) $match[3], (int) $match[1])
            || (isset($match[4]) && ((int) $match[4] > 23 || (int) $match[5] > 59 || (int) $match[6] > 59))) {
            throw new \RuntimeException('日期或时间格式无效；不支持 RDATE 的 PERIOD 值');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['calendar' => $message]);
    }

    private function readCalendar(string $text): VCalendar
    {
        if (strlen($text) > 1048576 || ! mb_check_encoding($text, 'UTF-8')) {
            $this->fail('请上传不超过 1 MB 的 UTF-8 日历文件。');
        }
        try {
            $calendar = Reader::read(ltrim($text, "\xEF\xBB\xBF"));
        } catch (Throwable) {
            $this->fail('文件内容不是有效的 ICS 日历，请重新从原日历导出。');
        }
        if (! $calendar instanceof VCalendar) {
            $this->fail('仅支持 VCALENDAR 格式的 .ics 文件。');
        }

        return $calendar;
    }
}
