<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Fixtures;

use Closure;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * A model-less form whose components a test sets through {@see $components}.
 */
class SchemaHarness extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var (Closure(): array<int, mixed>)|null */
    public static ?Closure $components = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $saved = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components((self::$components ?? static fn (): array => [])())->statePath('data');
    }

    public function save(): void
    {
        $this->saved = $this->form->getState();
    }

    public function flag(string $key): void
    {
        $this->addError($key, 'Broken.');
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}
