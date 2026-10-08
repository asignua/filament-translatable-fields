<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Concerns;

use Asignua\FilamentTranslatableFields\TranslatableFields;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Optional helper for Create/Edit pages (and custom pages that write a model by hand; in a relation manager only the
 * helper methods apply, see below).
 * Filament's default save already works with spatie — a cleared language arrives as `null` and spatie clears it —
 * so the trait is NOT needed for correctness. What it adds:
 *
 * - {@see fillTranslations()} for models that guard their attributes (`$guarded = ['*']`) and cannot take the map
 *   through `fill()`; `forgetEmpty: true` drops a cleared language from the JSON instead of storing `''`;
 * - `mutateFormDataBeforeCreate/Save()` that store a cleared language as `''` rather than `null` (tidiness only).
 *   A page that defines its own `mutateFormDataBeforeSave()` replaces the trait's one: call
 *   `$this->normalizeTranslatableData($data, $this->translatableModel())` there if you want the same.
 *
 * In a RelationManager the page hooks above are never called (they belong to the actions): call
 * `$this->normalizeTranslatableData($data, Related::class)` from the action's `->mutateDataUsing()`.
 */
trait HandlesTranslatableFields
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeTranslatableData($data, $this->translatableModel());
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeTranslatableData($data, $this->translatableModel());
    }

    /**
     * @param array<string, mixed>      $data
     * @param class-string<Model>|Model $model
     *
     * @return array<string, mixed>
     */
    protected function normalizeTranslatableData(array $data, Model|string $model): array
    {
        return TranslatableFields::normalize($data, self::translatableAttributesOf($model));
    }

    /**
     * Write the translation maps of `$data` to `$record` (no save) and return `$data` without them.
     *
     * @param array<string, mixed> $data
     * @param bool                 $forgetEmpty drop a cleared language from the JSON instead of storing `''`
     *
     * @return array<string, mixed>
     */
    protected function fillTranslations(Model $record, array $data, bool $forgetEmpty = false): array
    {
        $data = $this->normalizeTranslatableData($data, $record);

        foreach (self::translatableAttributesOf($record) as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                continue;
            }

            /** @var array<string, string> $map */
            $map = $data[$field];

            foreach ($map as $locale => $value) {
                if ($forgetEmpty && $value === '') {
                    $record->forgetTranslation($field, (string) $locale); // @phpstan-ignore method.notFound

                    continue;
                }

                $record->setTranslation($field, (string) $locale, $value); // @phpstan-ignore method.notFound
            }

            unset($data[$field]);
        }

        return $data;
    }

    protected function translatableModel(): Model|string
    {
        // A RelationManager and a ManageRelatedRecords page write the RELATED model; `getRecord()` of the latter is the
        // owner, and a RelationManager has neither a record nor a resource.
        if (method_exists($this, 'getRelationship')) {
            $related = $this->getRelationship()->getRelated();

            if ($related instanceof Model) {
                return $related;
            }
        }

        if (method_exists($this, 'getRecord')) {
            $record = $this->getRecord();

            if ($record instanceof Model) {
                return $record;
            }
        }

        if (!method_exists($this, 'getModel') && !method_exists(static::class, 'getResource')) {
            throw new LogicException(static::class.' has no model of its own: pass it explicitly, normalizeTranslatableData($data, Related::class).');
        }

        /** @var class-string<Model> $model */
        $model = method_exists($this, 'getModel') ? $this->getModel() : static::getResource()::getModel(); // @phpstan-ignore staticMethod.notFound

        return $model;
    }

    /**
     * @param class-string<Model>|Model $model
     *
     * @return array<int, string>
     */
    private static function translatableAttributesOf(Model|string $model): array
    {
        $instance = is_string($model) ? new $model : $model;

        return method_exists($instance, 'getTranslatableAttributes') ? $instance->getTranslatableAttributes() : [];
    }
}
