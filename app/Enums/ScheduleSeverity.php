<?php

namespace App\Enums;

enum ScheduleSeverity: string
{
    case Conflict = 'conflict';
    case Near = 'near';
    case SlackLight = 'slack_light';
    case SlackMedium = 'slack_medium';
    case SlackDeep = 'slack_deep';

    public function priority(): int
    {
        return match ($this) {
            self::Conflict => 5,
            self::Near => 4,
            self::SlackLight => 3,
            self::SlackMedium => 2,
            self::SlackDeep => 1,
        };
    }
}
