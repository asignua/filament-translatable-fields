<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Infolists;

use Asignua\FilamentTranslatableFields\Support\TranslatedValue;
use Closure;
use Filament\Infolists\Components\TextEntry;

/**
 * Infolist twin of {@see \Asignua\FilamentTranslatableFields\Tables\TranslatableColumn}.
 */
class TranslatableEntry extends TextEntry
{
    protected bool|Closure $shouldMark = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Not the injected `$record`: inside a JSON RepeatableEntry item it is the PARENT record. The entry's own
        // container knows its record, or else the item's array as constant state; `static` for the Repeater's clones.
        $this->getStateUsing(static function (TranslatableEntry $component): ?string {
            $container = $component->getContainer();

            return TranslatedValue::forState(
                $container->getRecord(withParentComponentRecord: false) ?? $container->getConstantState(),
                $component->getName(),
                (bool) $component->evaluate($component->shouldMark),
            );
        });
        $this->placeholder('—');
    }

    public function marker(bool|Closure $condition = true): static
    {
        $this->shouldMark = $condition;

        return $this;
    }
}
