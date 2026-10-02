<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Fixtures;

use Closure;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;

/**
 * An edit page that changes the data before it reaches the form.
 */
class EditPostWithFillHook extends EditPost
{
    /** @var (Closure(array<string, mixed>): array<string, mixed>)|null */
    public static ?Closure $mutate = null;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return self::$mutate === null ? $data : (self::$mutate)($data);
    }
}
