<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TranslatableFieldsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-translatable-fields';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations();
    }
}
