<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Presentation only: never infer or overwrite a stored all-day flag. */
class CalendarDisplaySpan
{
    public static function isOverview(CarbonImmutable $start, CarbonImmutable $end, bool $allDay = false): bool
    {
        // A local day can be 23 or 25 hours across daylight-saving transitions.
        return $allDay || $end->gte($start->addDay());
    }

    public static function label(CarbonImmutable $start, CarbonImmutable $end, bool $allDay = false): string
    {
        $midnightRange = $start->isStartOfDay() && $end->isStartOfDay();
        if ($allDay || $midnightRange) {
            $lastDate = $end->subMicrosecond(); // Calendar end boundaries are exclusive.

            return $start->isSameDay($lastDate) ? '全天' : '跨日 · '.$start->format('n/j').'–'.$lastDate->format('n/j');
        }

        return '跨日安排';
    }
}
