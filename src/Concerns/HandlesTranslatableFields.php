<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Concerns;

use Asignua\FilamentTranslatableFields\TranslatableFields;
use Illuminate\Database\Eloquent\Model;

/**
 * For Create/Edit pages (and relation managers, and custom pages that write a model by hand).
 *
 * The form state of a translatable attribute is a map (`title => ['uk' => '…', 'en' => null]`). Before it reaches
 * the model every present language becomes a string: spatie MERGES the languages it is given into the stored
 * ones, so a cleared input must arrive as `''`, not `null`/missing, or the old text silently stays.
 *
 * Models that guard their attributes (`$guarded = ['*']`) cannot take the map through `fill()`: use
 * {@see fillTranslations()}, which calls `setTranslations()` and hands back the remaining data.
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
        if (method_exists($this, 'getRecord')) {
            $record = $this->getRecord();

            if ($record instanceof Model) {
                return $record;
            }
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
