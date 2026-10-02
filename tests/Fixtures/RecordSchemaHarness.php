<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Fixtures;

use Filament\Schemas\Schema;
use Workbench\App\Models\Post;

/**
 * A custom page whose form is bound to a record and filled with `fill()` without data.
 */
class RecordSchemaHarness extends SchemaHarness
{
    public Post $post;

    public function mount(?Post $post = null): void
    {
        $this->post = $post ?? new Post;
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->record($this->post);
    }
}
