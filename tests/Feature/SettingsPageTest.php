<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Workbench\App\Livewire\SiteSettings;

/**
 * A page without a model: the state is a plain array.
 */
class SettingsPageTest extends TestCase
{
    public function test_array_state_round_trips_every_language(): void
    {
        Livewire::test(SiteSettings::class)
            ->fillForm(['site_name' => ['uk' => 'Сайт', 'en' => 'Site', 'de' => ''], 'tagline' => ['uk' => 'Слоган']])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Cache::get('site-settings');

        $this->assertSame('Сайт', $saved['site_name']['uk']);
        $this->assertSame('Site', $saved['site_name']['en']);
        $this->assertSame('Слоган', $saved['tagline']['uk']);
    }

    public function test_it_hydrates_from_the_stored_array(): void
    {
        Cache::forever('site-settings', ['site_name' => ['uk' => 'Сайт', 'en' => 'Site'], 'tagline' => ['de' => 'Spruch']]);

        Livewire::test(SiteSettings::class)
            ->assertSchemaStateSet([
                'site_name' => ['uk' => 'Сайт', 'en' => 'Site', 'de' => null],
                'tagline' => ['uk' => null, 'en' => null, 'de' => 'Spruch'],
            ]);
    }

    public function test_the_default_language_is_required_on_a_settings_page(): void
    {
        Livewire::test(SiteSettings::class)
            ->fillForm(['site_name' => ['uk' => '', 'en' => 'Site']])
            ->call('save')
            ->assertHasFormErrors(['site_name.uk' => 'required']);
    }
}
