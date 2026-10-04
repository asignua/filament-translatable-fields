<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Forms;

use Asignua\FilamentTranslatableFields\Support\Blank;
use Asignua\FilamentTranslatableFields\TranslatableFields;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component as Livewire;
use LogicException;
use ReflectionException;
use ReflectionProperty;

/**
 * One locale tab per language around a single translatable field. Every language is a separate input bound to
 * `{field}.{locale}`, so the whole translation map is in the form state at once: nothing depends on a global
 * "active locale", which is what loses data inside a Repeater or a Builder when the editor switches language.
 *
 *     TranslatableTabs::make('title', fn (string $locale) => TextInput::make('title')->maxLength(255))
 *         ->requiredDefault()
 *
 * The state is a plain array (`['uk' => '…', 'en' => '…']`), so the same component works on a resource page,
 * inside a JSON Repeater/Builder item, inside a relationship Repeater item and on a settings page.
 */
class TranslatableTabs extends Tabs
{
    protected string $field = '';

    protected ?Closure $factory = null;

    /** @var array<int, string>|Closure|null */
    protected array|Closure|null $locales = null;

    /** @var array<int, string>|Closure */
    protected array|Closure $requiredLocales = [];

    protected bool $isRequiredDefault = false;

    protected bool $isRequiredAll = false;

    protected bool|Closure $isRequiredAny = false;

    protected bool|Closure $hasCopyAction = true;

    protected bool|Closure|null $hasEmptyBadges = null;

    public static function make(string|Htmlable|Closure|null $field = null, ?Closure $factory = null): static
    {
        $static = parent::make();

        if (!is_string($field) || $field === '') {
            throw new InvalidArgumentException('TranslatableTabs::make() needs the name of the translatable attribute.');
        }

        $static->field = $field;
        $static->factory = $factory;
        $static->label(Str::headline($field));

        return $static;
    }

    /**
     * Wrap an existing field: it is cloned once per language.
     */
    public static function wrap(Field $template): static
    {
        return static::make($template->getName(), static fn (): Field => $template->getClone());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->contained(false);
        $this->tabs(fn (): array => $this->buildTabs());
    }

    /**
     * @param array<int, string>|Closure $locales
     */
    public function locales(array|Closure $locales): static
    {
        $this->locales = $locales;

        return $this;
    }

    /**
     * @param array<int, string>|Closure $locales
     */
    public function requiredIn(array|Closure $locales): static
    {
        $this->requiredLocales = $locales;
        $this->isRequiredDefault = false;
        $this->isRequiredAll = false;

        return $this;
    }

    /**
     * The default language of THIS field must be filled: the global default when the field offers it, otherwise the
     * first of its {@see locales()} — the same language "Copy from …" copies from.
     */
    public function requiredDefault(): static
    {
        $this->requiredLocales = [];
        $this->isRequiredDefault = true;
        $this->isRequiredAll = false;

        return $this;
    }

    /**
     * Every language of THIS field must be filled — its own {@see locales()}, not only the global list.
     */
    public function requiredAll(): static
    {
        $this->requiredLocales = [];
        $this->isRequiredDefault = false;
        $this->isRequiredAll = true;

        return $this;
    }

    /**
     * At least one language must be filled; the error shows on the default language. With a single language this is
     * simply `required()`.
     */
    public function requiredAny(bool|Closure $condition = true): static
    {
        $this->isRequiredAny = $condition;

        return $this;
    }

    public function copyFromDefault(bool|Closure $condition = true): static
    {
        $this->hasCopyAction = $condition;

        return $this;
    }

    public function emptyBadges(bool|Closure $condition = true): static
    {
        $this->hasEmptyBadges = $condition;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return list<string>
     */
    public function getLocales(): array
    {
        $locales = $this->evaluate($this->locales);

        if (!is_array($locales)) {
            return TranslatableFields::locales();
        }

        return array_values(array_map(strval(...), $locales));
    }

    /**
     * @return array<int, Tab>
     */
    protected function buildTabs(): array
    {
        $locales = $this->getLocales();
        $default = in_array(TranslatableFields::defaultLocale(), $locales, true) ? TranslatableFields::defaultLocale() : $locales[0];
        $required = array_map(strval(...), (array) $this->evaluate($this->requiredLocales));

        if ($this->isRequiredDefault) {
            $required[] = $default;
        }

        if ($this->isRequiredAll) {
            $required = $locales;
        }

        $requiredAny = (bool) $this->evaluate($this->isRequiredAny);
        $badges = (bool) ($this->evaluate($this->hasEmptyBadges) ?? config('filament-translatable-fields.empty_badges', false));
        $copy = (bool) $this->evaluate($this->hasCopyAction);
        $field = $this->field;

        $tabs = [];

        foreach ($locales as $locale) {
            $component = $this->factory === null
                ? throw new LogicException("TranslatableTabs [{$field}] needs a field factory: pass a closure to make(), or use wrap().")
                : $this->evaluate($this->factory, ['locale' => $locale, 'field' => $field]);

            if (!$component instanceof Component) {
                throw new LogicException("The factory of TranslatableTabs [{$field}] must return a Filament component.");
            }

            $isRequired = in_array($locale, $required, true);

            if ($component instanceof Field) {
                $others = array_map(static fn (string $other): string => "{$field}.{$other}", array_values(array_diff($locales, [$locale])));

                $component->validationAttribute(static function (Field $c) use ($locale): string {
                    $label = $c->getLabel();

                    return ($label instanceof Htmlable ? strip_tags($label->toHtml()) : (string) $label).' ('.TranslatableFields::label($locale).')';
                });

                if ($isRequired) {
                    $component->required();
                } elseif ($requiredAny && $locale === $default) {
                    $others === [] ? $component->required() : $component->requiredWithoutAll($others);
                }

                if ($badges && !self::hasLiveSetting($component)) {
                    $component->live(onBlur: true);
                }

                if ($copy && $locale !== $default) {
                    $component->hintAction($this->copyAction($field, $locale, $default));
                }
            }

            $component->statePath("{$field}.{$locale}");

            $tabs[] = Tab::make(TranslatableFields::label($locale))
                ->key("translatable-{$field}-{$locale}")
                ->badge(static function (Tab $tab, Livewire $livewire, Get $get) use ($field, $locale, $badges): ?string {
                    $path = implode('.', array_filter([$tab->getContainer()->getStatePath(), "{$field}.{$locale}"]));

                    if (self::hasErrorsUnder($livewire, $path)) {
                        return '!';
                    }

                    return $badges && Blank::is($get("{$field}.{$locale}"))
                        ? (string) __('filament-translatable-fields::translatable-fields.empty')
                        : null;
                })
                ->badgeColor(static function (Tab $tab, Livewire $livewire) use ($field, $locale, $isRequired): string {
                    $path = implode('.', array_filter([$tab->getContainer()->getStatePath(), "{$field}.{$locale}"]));

                    return self::hasErrorsUnder($livewire, $path) || $isRequired ? 'danger' : 'gray';
                })
                ->schema([$component]);
        }

        return $tabs;
    }

    /**
     * Whether the factory already decided about `live()`: `isLive()` needs a container, which does not exist yet.
     * The property is Filament's internals; should it ever disappear, the plugin simply treats the field as undecided.
     */
    protected static function hasLiveSetting(Field $component): bool
    {
        try {
            return (new ReflectionProperty($component, 'isLive'))->getValue($component) !== null;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * An error on the language input itself or anywhere below it (a Repeater/Builder/KeyValue in a language tab keeps
     * its errors at `{field}.{locale}.…`).
     */
    protected static function hasErrorsUnder(Livewire $livewire, string $path): bool
    {
        $errors = $livewire->getErrorBag();

        if ($errors->has($path)) {
            return true;
        }

        foreach ($errors->keys() as $key) {
            if (str_starts_with((string) $key, $path.'.')) {
                return true;
            }
        }

        return false;
    }

    protected function copyAction(string $field, string $locale, string $default): Action
    {
        return Action::make('copyFromDefaultLocale')
            ->label(__('filament-translatable-fields::translatable-fields.copy_from', ['language' => TranslatableFields::label($default)]))
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->link()
            // Never wipe a finished translation by a misclick. Evaluated when the action mounts, i.e. on the state the
            // click has just sent, so it is never stale.
            ->requiresConfirmation(static fn (Get $get): bool => !Blank::is($get("{$field}.{$locale}")))
            // No `disabled()` while the default language is empty: the attribute is rendered by the server, and the
            // inputs are not live, so the link would stay disabled after the editor types the default language until
            // some other request re-renders the form. The click itself carries the fresh state, so decide here.
            ->action(static function (Get $get, Set $set) use ($field, $locale, $default): void {
                $value = $get("{$field}.{$default}");

                if (Blank::is($value)) {
                    Notification::make()
                        ->title(__('filament-translatable-fields::translatable-fields.nothing_to_copy', ['language' => TranslatableFields::label($default)]))
                        ->warning()
                        ->send();

                    return;
                }

                $set("{$field}.{$locale}", $value);
            });
    }

    /**
     * Put the record's translation map into the state BEFORE the language inputs hydrate, so their own
     * `afterStateHydrated()` hooks (a Repeater, a multiple Select, a FileUpload in a language tab) see the record value.
     *
     * - `fill($data)` (`EditRecord`, a `->relationship()` repeater): the data wins whenever `{field}` arrived as a map —
     *   whatever the page changed in `mutateFormDataBeforeFill()` must stay. Only an absent or non-array `{field}`
     *   (a raw JSON string) is replaced by the record's languages.
     * - `fill()` without data (a record Action's default mount, a custom page with `->record($r)`): the children would
     *   write `null` into every language, so the record's map is written first and marked as hydrated — unless the
     *   field already got a value from a parent's default.
     *
     * Only when the field really belongs to the record's schema: inside a JSON Repeater/Builder item (or a
     * statePath-ed group) nothing is read.
     *
     * @param array<string, mixed>|null $hydratedDefaultState
     * @param array<string, true>       $appliedStateCastPaths
     */
    public function hydrateState(?array &$hydratedDefaultState, bool $shouldCallHydrationHooks = true, bool $shouldApplyStateCasts = true, array &$appliedStateCastPaths = []): void
    {
        $this->hydrateFromRecord($hydratedDefaultState);

        parent::hydrateState($hydratedDefaultState, $shouldCallHydrationHooks, $shouldApplyStateCasts, $appliedStateCastPaths);
    }

    /**
     * @param array<string, mixed>|null $hydratedDefaultState
     */
    protected function hydrateFromRecord(?array &$hydratedDefaultState): void
    {
        $record = self::recordFor($this, $this->field);

        if ($record === null) {
            return;
        }

        $path = implode('.', array_filter([$this->getContainer()->getStatePath(), $this->field], static fn (?string $s): bool => (string) $s !== ''));
        $livewire = $this->getLivewire();

        if ($hydratedDefaultState === null ? is_array(data_get($livewire, $path)) : Arr::has($hydratedDefaultState, $path)) {
            return;
        }

        $map = [];

        foreach ($this->getLocales() as $locale) {
            $value = $record->getTranslation($this->field, $locale, false); // @phpstan-ignore method.notFound
            // The stored value goes through as it is: only a missing language (`''`) becomes null. Judging it with
            // Blank::is() here would turn a body made of a custom block into null and wipe it on save.
            $map[$locale] = $value === '' ? null : $value;
        }

        data_set($livewire, $path, $map);

        if ($hydratedDefaultState !== null) {
            Arr::set($hydratedDefaultState, $path, $map); // @phpstan-ignore parameterByRef.type
        }
    }

    /**
     * The record that owns `$field`, or null: no record, not translatable, or the field sits deeper than the
     * schema the record was given to (its state is a JSON/array map then).
     */
    protected static function recordFor(Component $component, string $field): ?Model
    {
        $container = $component->getContainer();
        $path = $container->getStatePath();

        for ($schema = $container; $schema instanceof Schema; $schema = $schema->getParentComponent()?->getContainer()) {
            $record = $schema->getRecord(withParentComponentRecord: false);

            if (!$record instanceof Model) {
                continue;
            }

            if ($schema->getStatePath() !== $path) {
                return null;
            }

            return method_exists($record, 'isTranslatableAttribute') && $record->isTranslatableAttribute($field)
                ? $record
                : null;
        }

        return null;
    }
}
