<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Support;

use Asignua\FilamentTranslatableFields\TranslatableFields;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * A label for the ADMIN taken from the first filled language, with a marker (`[en] Title`) when it is
 * not the current one. Read-only: nothing is written back, so an untranslated language stays empty.
 *
 * Never use it to hydrate a form (the borrowed value would be saved into the empty language) and
 * never on the public site (`[en]` in a `<title>` is an SEO bug).
 */
final class TranslatedValue
{
    /**
     * @return array{locale: string, value: string}|null
     */
    public static function resolve(Model $record, string $field, ?string $current = null): ?array
    {
        $record = self::target($record, $field);

        if ($record === null) {
            return null;
        }

        [$model, $attribute] = $record;

        if (!method_exists($model, 'getTranslation') || !method_exists($model, 'isTranslatableAttribute') || !$model->isTranslatableAttribute($attribute)) {
            $value = $model->getAttribute($attribute);

            return is_scalar($value) || $value instanceof Stringable
                ? ['locale' => $current ?? app()->getLocale(), 'value' => (string) $value]
                : null;
        }

        foreach (TranslatableFields::fallbackOrder($current) as $locale) {
            $value = $model->getTranslation($attribute, $locale, false);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return ['locale' => $locale, 'value' => trim((string) $value)];
            }
        }

        return null;
    }

    public static function for(Model $record, string $field, bool $mark = true, ?string $current = null): ?string
    {
        $resolved = self::resolve($record, $field, $current);

        if ($resolved === null) {
            return null;
        }

        $current ??= app()->getLocale();

        return $mark && $resolved['locale'] !== $current
            ? self::marker($resolved['locale']).$resolved['value']
            : $resolved['value'];
    }

    public static function marker(string $locale): string
    {
        return str_replace(':locale', $locale, (string) config('filament-translatable-fields.marker', '[:locale] '));
    }

    /**
     * `team.title` reads `title` of the related model.
     *
     * @return array{Model, string}|null
     */
    private static function target(Model $record, string $field): ?array
    {
        if (!str_contains($field, '.')) {
            return [$record, $field];
        }

        $segments = explode('.', $field);
        $attribute = (string) array_pop($segments);
        $model = $record;

        foreach ($segments as $segment) {
            $next = $model->getRelationValue($segment);

            if (!$next instanceof Model) {
                return null;
            }

            $model = $next;
        }

        return [$model, $attribute];
    }
}
