<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tables;

use Asignua\FilamentTranslatableFields\Support\TranslatedValue;
use Asignua\FilamentTranslatableFields\TranslatableFields;
use Closure;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

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
     * Search the JSON of every language (`title->uk`, `title->en`, …), case-insensitively on PostgreSQL too. A dotted
     * name (`team.name`) searches the related model through `whereHas()`.
     */
    public function searchAcrossLocales(): static
    {
        $name = $this->getName();

        $this->searchable(query: static function (Builder $query, string $search) use ($name): Builder {
            $relation = str_contains($name, '.') ? Str::beforeLast($name, '.') : null;
            $attribute = Str::afterLast($name, '.');

            $match = static function (Builder $query) use ($attribute, $search): void {
                $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

                $query->where(static function (Builder $query) use ($attribute, $search, $operator): void {
                    foreach (TranslatableFields::locales() as $locale) {
                        $query->orWhere("{$attribute}->{$locale}", $operator, "%{$search}%");
                    }
                });
            };

            return $relation === null ? $query->where($match) : $query->whereHas($relation, $match);
        });

        return $this;
    }

    /**
     * Sort by the text the cell shows: the current language, then the default one, then the rest (an empty language
     * falls through to the next one). Only for an attribute of the table's own model — not `team.name`.
     */
    public function sortableByLocale(): static
    {
        $name = $this->getName();

        if (str_contains($name, '.')) {
            throw new LogicException("TranslatableColumn [{$name}]: sortableByLocale() cannot sort by a relationship attribute; sort with your own query (a join) instead.");
        }

        $this->sortable(query: static function (Builder $query, string $direction) use ($name): Builder {
            $grammar = $query->getQuery()->getGrammar();
            $locales = array_values(array_intersect(TranslatableFields::fallbackOrder(), TranslatableFields::locales()));
            $parts = array_map(static fn (string $locale): string => "nullif({$grammar->wrap("{$name}->{$locale}")}, '')", $locales);
            $expression = count($parts) === 1 ? $parts[0] : 'coalesce('.implode(', ', $parts).')';

            // Identifiers are quoted by the grammar; the locales come from the developer's configuration.
            $sql = $expression.' '.(strtolower($direction) === 'desc' ? 'desc' : 'asc');

            return $query->orderByRaw($sql); // @phpstan-ignore argument.type
        });

        return $this;
    }
}
