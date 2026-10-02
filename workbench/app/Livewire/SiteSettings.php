<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Asignua\FilamentTranslatableFields\Forms\Translatable;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * A settings page: no model, the state is a plain array kept in the cache.
 */
class SiteSettings extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Cache::get('site-settings', []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Translatable::field(TextInput::make('site_name'))->requiredDefault(),
                Translatable::field(TextInput::make('tagline')),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        Cache::forever('site-settings', $this->form->getState());
    }

    public function render(): View
    {
        return view('workbench::site-settings');
    }
}
