<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields;

use Closure;
use Locale;

/**
 * The one place that knows which languages a site edits: a call in a service provider
 * (`TranslatableFields::locales(['uk', 'en'])`), the config, a `translatable.locales` key if the app defines one
 * (spatie/laravel-translatable itself has no such key), and finally `app.locale` + `app.fallback_locale` — which on a
 * fresh Laravel app is just `['en']`, i.e. one tab. Configure the list.
 */
final class TranslatableFields
{
    /** @var array<int, string>|Closure|null */
    private static array|Closure|null $locales = null;

    private static string|Closure|null $defaultLocale = null;

    /** @var array<string, string> */
    private static array $labels = [];

    /**
     * Without an argument: the languages in tab order. With one: set them (a list or a closure that returns it).
     *
     * @param array<int, string>|Closure|null $locales
     *
     * @return list<string>
     */
    public static function locales(array|Closure|null $locales = null): array
    {
        if ($locales !== null) {
            self::$locales = $locales;
        }

        $resolved = self::$locales instanceof Closure ? (self::$locales)() : self::$locales;
        $resolved ??= config('filament-translatable-fields.locales');
        $resolved ??= self::spatieLocales();

        $list = [];

        foreach ((array) $resolved as $locale) {
            $locale = (string) $locale;

            if ($locale !== '' && !in_array($locale, $list, true)) {
                $list[] = $locale;
            }
        }

        return $list !== [] ? $list : [(string) config('app.locale', 'en')];
    }

    public static function defaultLocale(string|Closure|null $locale = null): string
    {
        if ($locale !== null) {
            self::$defaultLocale = $locale;
        }

        $resolved = self::$defaultLocale instanceof Closure ? (self::$defaultLocale)() : self::$defaultLocale;
        $resolved ??= config('filament-translatable-fields.default_locale');
        $resolved ??= config('app.locale');

        $locales = self::locales();

        return in_array($resolved, $locales, true) ? (string) $resolved : $locales[0];
    }

    /**
     * @param array<string, string> $labels
     */
    public static function labels(array $labels): void
    {
        self::$labels = $labels;
    }

    public static function label(string $locale): string
    {
        /** @var array<string, string> $configured */
        $configured = config('filament-translatable-fields.labels', []);

        if (isset(self::$labels[$locale])) {
            return self::$labels[$locale];
        }

        if (isset($configured[$locale])) {
            return $configured[$locale];
        }

        if (class_exists(Locale::class)) {
            // `pt_BR` and `pt_PT` must not both read "Português": with a region the label names it.
            $name = (string) ((Locale::getRegion($locale) ?? '') !== ''
                ? Locale::getDisplayName($locale, $locale)
                : Locale::getDisplayLanguage($locale, $locale));

            if ($name !== '' && $name !== $locale) {
                return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
            }
        }

        return mb_strtoupper($locale);
    }

    /**
     * The language order for a fallback: the current one, the default, then the rest.
     *
     * @return list<string>
     */
    public static function fallbackOrder(?string $current = null): array
    {
        return array_values(array_unique([
            $current ?? app()->getLocale(),
            self::defaultLocale(),
            ...self::locales(),
        ]));
    }

    /**
     * Whole translation map for the fields of a state array: every present locale key is a string (`null` becomes
     * `''`), so the stored JSON holds strings only. spatie clears a language given `null` just as well — this is
     * tidiness, not a correctness requirement. A locale key that is absent stays absent: spatie merges the languages
     * it is given into the stored ones, so a hidden/disabled input keeps its stored text.
     *
     * @param array<string, mixed> $data
     * @param array<int, string>   $fields
     *
     * @return array<string, mixed>
     */
    public static function normalize(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                continue;
            }

            foreach ($data[$field] as $locale => $value) {
                $data[$field][$locale] = $value ?? '';
            }
        }

        return $data;
    }

    /**
     * Forget every override (tests, Octane).
     */
    public static function reset(): void
    {
        self::$locales = null;
        self::$defaultLocale = null;
        self::$labels = [];
    }

    /**
     * An app-defined `translatable.locales` (spatie ships no such key; some apps add it), else the app locale and its
     * fallback.
     *
     * @return array<int, string>
     */
    private static function spatieLocales(): array
    {
        $configured = config('translatable.locales');

        if (!is_array($configured) || $configured === []) {
            return [(string) config('app.locale'), (string) config('app.fallback_locale')];
        }

        // spatie allows ['en' => ['en-GB', 'en-US'], 'nl'] — the plain code is the key or the value.
        $locales = [];

        foreach ($configured as $key => $value) {
            $locales[] = is_string($key) ? $key : (string) $value;
        }

        return $locales;
    }
}
