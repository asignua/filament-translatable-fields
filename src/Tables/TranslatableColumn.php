<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tables;

use Asignua\FilamentTranslatableFields\Support\TranslatedValue;
use Asignua\FilamentTranslatableFields\TranslatableFields;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The current language, falling back to the default and then to any filled one, with a `[en]` marker so an
 * editor sees at a glance that the shown text is not in the language they work in. Nothing is written back.
 *
 *     TranslatableColumn::make('title')->searchAcrossLocales()->sortableByLocale()
 */
class TranslatableColumn extends TextColumn
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

    /**
     * Search the JSON of every language (`title->uk`, `title->en`, …).
     */
    public function searchAcrossLocales(): static
    {
        $name = $this->getName();

        $this->searchable(query: static fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($name, $search): void {
            foreach (TranslatableFields::locales() as $locale) {
                $query->orWhere("{$name}->{$locale}", 'like', "%{$search}%");
            }
        }));

        return $this;
    }

    /**
     * Sort by the current language's text.
     */
    public function sortableByLocale(): static
    {
        $name = $this->getName();

        $this->sortable(query: static fn (Builder $query, string $direction): Builder => $query->orderBy($name.'->'.app()->getLocale(), strtolower($direction) === 'desc' ? 'desc' : 'asc'));

        return $this;
    }
}
