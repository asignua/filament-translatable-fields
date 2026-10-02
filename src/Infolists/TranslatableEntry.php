<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Infolists;

use Asignua\FilamentTranslatableFields\Support\TranslatedValue;
use Closure;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;

/**
 * Infolist twin of {@see \Asignua\FilamentTranslatableFields\Tables\TranslatableColumn}.
 */
class TranslatableEntry extends TextEntry
{
    protected bool|Closure $shouldMark = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getStateUsing(fn (Model $record): ?string => TranslatedValue::for($record, $this->getName(), $this->evaluate($this->shouldMark)));
        $this->placeholder('—');
    }

    public function marker(bool|Closure $condition = true): static
    {
        $this->shouldMark = $condition;

        return $this;
    }
}
