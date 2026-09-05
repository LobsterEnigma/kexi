<?php

namespace App\Support;

class CourseColors
{
    public const PRESETS = ['#2f67c7', '#138a7b', '#7257cf', '#bd4f76', '#247ba0', '#c06135', '#558b2f', '#a16207'];

    public static function key(?string $label): string
    {
        return 'type:'.mb_strtolower(trim($label ?? ''));
    }

    public static function appearance(string $color): array
    {
        $rgb = sscanf($color, '#%02x%02x%02x');
        $mix = static fn (int $white, float $amount): string => sprintf('#%02x%02x%02x', ...array_map(
            static fn (int $channel): int => (int) round($channel * (1 - $amount) + $white * $amount),
            $rgb,
        ));

        return ['accent' => $color, 'border' => $mix(255, .65), 'surface' => $mix(255, .93), 'text' => $mix(0, .55)];
    }
}
