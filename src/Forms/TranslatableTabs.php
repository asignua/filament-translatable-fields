<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Forms;

use Asignua\FilamentTranslatableFields\Support\Blank;
use Asignua\FilamentTranslatableFields\TranslatableFields;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
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

        return $this;
    }

    public function requiredAll(): static
    {
        return $this->requiredIn(static fn (): array => TranslatableFields::locales());
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
            // Never wipe a finished translation by a misclick.
            ->requiresConfirmation(static fn (Get $get): bool => !Blank::is($get("{$field}.{$locale}")))
            ->action(static function (Get $get, Set $set) use ($field, $locale, $default): void {
                $value = $get("{$field}.{$default}");

                if (Blank::is($value)) {
                    return;
                }

                $set("{$field}.{$locale}", $value);
            });
    }

    /**
     * Fill the language inputs from the record when the state did not arrive as a translation map.
     *
     * Normally it does: `EditRecord` and a `->relationship()` repeater fill the form from `attributesToArray()`, which
     * spatie turns into the whole map, and whatever the page changed in `mutateFormDataBeforeFill()` (or passed to
     * `$form->fill()` itself) must stay. Only when `{field}` is not an array (absent, a raw JSON string) are the
     * languages read from `$record->getTranslation($field, $locale, false)` — and only when the field really belongs
     * to the record's schema: inside a JSON Repeater/Builder item (or a statePath-ed group) nothing is read.
     *
     * Runs once for the whole field, after the inputs' own `afterStateHydrated()` hooks, before the tabs' one.
     */
    public function callAfterStateHydrated(): static
    {
        $this->hydrateFromRecord();

        return parent::callAfterStateHydrated();
    }

    protected function hydrateFromRecord(): void
    {
        $record = self::recordFor($this, $this->field);

        if ($record === null) {
            return;
        }

        $path = implode('.', array_filter([$this->getContainer()->getStatePath(), $this->field], static fn (?string $s): bool => (string) $s !== ''));

        if (is_array(data_get($this->getLivewire(), $path))) {
            return;
        }

        foreach ($this->getChildSchemas(withHidden: true) as $tabs) {
            foreach ($tabs->getComponents(withActions: false, withHidden: true) as $tab) {
                if (!$tab instanceof Component) {
                    continue;
                }

                foreach ($tab->getChildSchemas(withHidden: true) as $schema) {
                    foreach ($schema->getComponents(withActions: false, withHidden: true) as $component) {
                        if (!$component instanceof Field) {
                            continue;
                        }

                        $statePath = (string) $component->getStatePath();

                        if (!str_starts_with($statePath, $path.'.')) {
                            continue;
                        }

                        $value = $record->getTranslation($this->field, substr($statePath, strlen($path) + 1), false); // @phpstan-ignore method.notFound

                        $component->state(Blank::is($value) ? null : $value);
                    }
                }
            }
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
