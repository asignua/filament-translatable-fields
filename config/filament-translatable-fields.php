<?php

declare(strict_types=1);

return [

    /*
    | The languages every translatable field offers, in tab order. `null` falls back to
    | spatie/laravel-translatable's `translatable.locales`, then to the app locale and its
    | fallback. `TranslatableFields::locales([...])` (e.g. in a service provider) overrides this.
    */
    'locales' => null,

    /*
    | The language that is required by `->requiredDefault()`, copied by "Copy from ..." and tried first
    | after the current one when a table column or infolist has to fall back. `null` = app.locale
    | (or the first of the locales when app.locale is not one of them).
    */
    'default_locale' => null,

    /*
    | Tab labels per locale, e.g. ['uk' => 'Українська']. Missing codes use the language's own name
    | from PHP's intl extension, then the upper-cased code.
    */
    'labels' => [],

    /*
    | Prefix glued to a table column / infolist value taken from another language than the current
    | one. `:locale` is the language code. An empty string disables the marker.
    */
    'marker' => '[:locale] ',

    /*
    | Opt-in: show an "empty" badge on the tab of a language that has no value yet. The inputs become
    | `live(onBlur: true)` so the badge follows what the editor typed
    | (one request per blur — mind large forms). A validation-error `!` badge is always shown.
    */
    'empty_badges' => false,

];
