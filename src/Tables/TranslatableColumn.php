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
     * Search the JSON of every language (`title->uk`, `title->en`, …) case-insensitively: `ilike` on PostgreSQL,
     * `lower(…) like lower(?)` on MySQL/MariaDB (MySQL 8 returns a JSON value as a binary-collated string, so a plain
     * `like` is case-sensitive there), a plain `like` on SQLite (ASCII only). A dotted name (`team.name`) searches the
     * related model through `whereHas()`.
     */
    public function searchAcrossLocales(): static
    {
        $name = $this->getName();

        $this->searchable(query: static function (Builder $query, string $search) use ($name): Builder {
            $relation = str_contains($name, '.') ? Str::beforeLast($name, '.') : null;
            $attribute = Str::afterLast($name, '.');

            $match = static fn (Builder $query): Builder => static::applyLocaleSearch($query, $attribute, $search);

            return $relation === null ? $query->where($match) : $query->whereHas($relation, $match);
        });

        return $this;
    }

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     *
     * @return Builder<TModel>
     */
    public static function applyLocaleSearch(Builder $query, string $attribute, string $search): Builder
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $grammar = $query->getQuery()->getGrammar();
        $column = $query->getModel()->qualifyColumn($attribute);

        return $query->where(static function (Builder $query) use ($column, $search, $driver, $grammar): void {
            foreach (TranslatableFields::locales() as $locale) {
                if (in_array($driver, ['mysql', 'mariadb'], true)) {
                    // The identifier is quoted by the grammar; the search term is a binding.
                    $query->orWhereRaw('lower('.$grammar->wrap("{$column}->{$locale}").') like lower(?)', ["%{$search}%"]); // @phpstan-ignore argument.type
                } else {
                    $query->orWhere("{$column}->{$locale}", $driver === 'pgsql' ? 'ilike' : 'like', "%{$search}%");
                }
            }
        });
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
            $column = $query->getModel()->qualifyColumn($name);
            // The very order the cell tries (the current language even when it is not configured); a value that is
            // blank after trim() falls through, as it does in the cell.
            $parts = array_map(static fn (string $locale): string => "nullif(trim({$grammar->wrap("{$column}->{$locale}")}), '')", TranslatableFields::fallbackOrder());
            $expression = count($parts) === 1 ? $parts[0] : 'coalesce('.implode(', ', $parts).')';

            // Identifiers are quoted by the grammar; the locales come from the developer's configuration.
            $sql = $expression.' '.(strtolower($direction) === 'desc' ? 'desc' : 'asc');

            return $query->orderByRaw($sql); // @phpstan-ignore argument.type
        });

        return $this;
    }
}
