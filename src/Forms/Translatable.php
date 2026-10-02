<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Forms;

use Closure;
use Filament\Forms\Components\Field;

/**
 * Shorthand: `Translatable::field(TextInput::make('title')->maxLength(255))`.
 */
final class Translatable
{
    public static function field(Field $template): TranslatableTabs
    {
        return TranslatableTabs::wrap($template);
    }

    /**
     * @param Closure(string): Field $factory
     */
    public static function make(string $field, Closure $factory): TranslatableTabs
    {
        return TranslatableTabs::make($field, $factory);
    }
}
