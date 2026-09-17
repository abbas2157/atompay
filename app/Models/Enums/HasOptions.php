<?php

namespace App\Models\Enums;

/** Shared helpers for the string-backed enums that feed <select> options. */
trait HasOptions
{
    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
