# Changelog

All notable changes to `asignua/filament-translatable-fields` are documented here.

## v1.0.0 - unreleased

- `TranslatableTabs::make('title', fn (string $locale) => TextInput::make('title'))` and `Translatable::field(TextInput::make('title'))`: one tab per language around a field, one input per language bound to `title.{locale}`; the whole translation map is in the form state at once.
- Works in a JSON `Repeater`/`Builder`, a `->relationship()` repeater, a resource page and a model-less settings page.
- Hydrates from `getTranslation($field, $locale, false)` only when the field really belongs to the record's schema; array state (JSON items, settings) is left as it is.
- Validation: `requiredDefault()`, `requiredIn([...])`, `requiredAll()`, `requiredAny()`; messages name the language (`Title (English)`).
- "Copy from <default>" hint action, "empty" / error badges on tabs, per-field `locales([...])`.
- `HandlesTranslatableFields` page trait: cleared languages become `''`, `fillTranslations()` for models with `$guarded = ['*']`.
- `TranslatableColumn` and `TranslatableEntry`: current language, then the default, then any filled one, with a `[en]` marker; cross-language search and sorting.
- `TranslatableFields` registry (locales, default locale, labels) over config and spatie's `translatable.locales`.
- Translations in 10 languages.
