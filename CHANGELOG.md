# Changelog

All notable changes to `asignua/filament-translatable-fields` are documented here.

## v1.0.0 - unreleased

- `TranslatableTabs::make('title', fn (string $locale) => TextInput::make('title'))` and `Translatable::field(TextInput::make('title'))`: one tab per language around a field, one input per language bound to `title.{locale}`; the whole translation map is in the form state at once.
- Works in a JSON `Repeater`/`Builder`, a `->relationship()` repeater, a resource page and a model-less settings page.
- Hydrates from `getTranslation($field, $locale, false)` only when the field really belongs to the record's schema; array state (JSON items, settings) is left as it is.
- Validation: `requiredDefault()`, `requiredIn([...])`, `requiredAll()`, `requiredAny()`; messages name the language (`Title (English)`).
- "Copy from <default>" hint action, "empty" / error badges on tabs, per-field `locales([...])`.
- Data passed to `fill()` / `mutateFormDataBeforeFill()` is kept; the record is read only when the field did not arrive as a translation map. A record-bound `fill()` without data (a record Action's default mount) reads the record too, before the language inputs hydrate, so their own hooks see the record value.
- `requiredDefault()` follows the field's own default when its `locales()` leave the global one out; `requiredAll()` covers the field's own `locales()`; `requiredAny()` on a single language is `required()`.
- "Copy from <default>" asks before overwriting a filled language and, with the default empty, shows a "nothing to copy" notification (it is never rendered disabled: the inputs are not live, so a server-side disabled state would go stale while the editor types); the `!` badge also covers errors nested under a language.
- `HandlesTranslatableFields` page trait (optional): `fillTranslations()` for models with `$guarded = ['*']`, cleared languages stored as `''`.
- `TranslatableColumn` and `TranslatableEntry`: current language, then the default, then any filled one, with a `[en]` marker; cross-language search (case-insensitive on PostgreSQL and MySQL/MariaDB, through `whereHas()` for `team.name`) and sorting by the shown text (blank-after-trim values and languages cleared to JSON `null` fall through — on MySQL/MariaDB too, where `json_unquote()` would otherwise yield the string `'null'`; columns qualified).
- `TranslatableFields` registry (locales, default locale, labels with the region for `pt_BR`-style codes) over config, an app-defined `translatable.locales`, then `app.locale` + `app.fallback_locale`.
- Translations in 10 languages.
