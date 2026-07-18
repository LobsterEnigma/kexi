<?php

namespace App\Enums;

enum WeekMode: string
{
    case All = 'all';
    case Odd = 'odd';
    case Even = 'even';
    case Specific = 'specific';
}
