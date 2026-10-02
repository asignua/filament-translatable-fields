<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts\Pages;

use Asignua\FilamentTranslatableFields\Concerns\HandlesTranslatableFields;
use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Posts\PostResource;

class CreatePost extends CreateRecord
{
    use HandlesTranslatableFields;

    protected static string $resource = PostResource::class;
}
