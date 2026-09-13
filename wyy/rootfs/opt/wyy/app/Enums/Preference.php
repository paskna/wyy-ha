<?php

namespace App\Enums;

enum Preference: string
{
    case Unrated = 'unrated';
    case Top = 'top';
    case Average = 'average';
    case Unsuitable = 'unsuitable';

    public function label(): string
    {
        return match ($this) {
            self::Unrated => 'Noch nicht bewertet',
            self::Top => 'Top-Wein',
            self::Average => 'Durchschnitt',
            self::Unsuitable => 'Unpassend',
        };
    }
}
