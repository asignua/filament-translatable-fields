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

        return $query->where(static function (Builder $query) use ($attribute, $search, $driver): void {
            foreach (TranslatableFields::locales() as $locale) {
                if (self::isMysql($driver)) {
                    // The search term is a binding; a cleared language (JSON null) is SQL NULL and never matches.
                    $query->orWhereRaw('lower('.self::localeValueSql($query, $attribute, $locale).') like lower(?)', ["%{$search}%"]); // @phpstan-ignore argument.type
                } else {
                    $column = $query->getModel()->qualifyColumn($attribute);

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

        $this->sortable(query: static fn (Builder $query, string $direction): Builder => static::applyLocaleSort($query, $name, $direction));

        return $this;
    }

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     *
     * @return Builder<TModel>
     */
    public static function applyLocaleSort(Builder $query, string $attribute, string $direction): Builder
    {
        // The very order the cell tries (the current language even when it is not configured); a value that is blank
        // after trim() — or a cleared language stored as JSON null — falls through, as it does in the cell.
        $parts = array_map(
            static fn (string $locale): string => 'nullif(trim('.self::localeValueSql($query, $attribute, $locale).'), \'\')',
            TranslatableFields::fallbackOrder(),
        );
        $expression = count($parts) === 1 ? $parts[0] : 'coalesce('.implode(', ', $parts).')';

        return $query->orderByRaw($expression.' '.(strtolower($direction) === 'desc' ? 'desc' : 'asc')); // @phpstan-ignore argument.type
    }

    /**
     * The text of one language as SQL, NULL when the language is absent or cleared. Spatie stores a cleared language
     * as JSON `null`; PostgreSQL (`->>`) and SQLite (`json_extract`) turn it into SQL NULL, but MySQL/MariaDB's
     * `json_unquote()` turns it into the four-letter string `'null'` — which would sort under "n" and match a search
     * for "nu". There the JSON null is mapped to SQL NULL explicitly.
     *
     * Identifiers are quoted by the grammar; the locales come from the developer's configuration.
     *
     * @param Builder<covariant Model> $query
     */
    protected static function localeValueSql(Builder $query, string $attribute, string $locale): string
    {
        $grammar = $query->getQuery()->getGrammar();
        $column = $query->getModel()->qualifyColumn($attribute);

        if (!self::isMysql($query->getModel()->getConnection()->getDriverName())) {
            return $grammar->wrap("{$column}->{$locale}");
        }

        $path = "'$.\"".str_replace(['\\', "'", '"'], ['\\\\', "''", '\\"'], $locale)."\"'";
        $extract = 'json_extract('.$grammar->wrap($column).", {$path})";

        return "(case when json_type({$extract}) = 'NULL' then null else json_unquote({$extract}) end)";
    }

    protected static function isMysql(string $driver): bool
    {
        return in_array($driver, ['mysql', 'mariadb'], true);
    }
}
