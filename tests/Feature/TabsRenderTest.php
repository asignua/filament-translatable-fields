<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Forms\TranslatableTabs;
use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Asignua\FilamentTranslatableFields\TranslatableFields;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\TextInput;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Workbench\App\Filament\Resources\Posts\Pages\CreatePost;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Models\Post;

class TabsRenderTest extends TestCase
{
    public function test_every_language_gets_a_tab_and_an_input(): void
    {
        $html = Livewire::test(CreatePost::class)->html();

        foreach (['Українська', 'English', 'Deutsch'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        foreach (['data.title.uk', 'data.title.en', 'data.title.de'] as $path) {
            $this->assertStringContainsString($path, $html);
        }
    }

    public function test_empty_badges_are_off_by_default(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт'], 'body' => ['uk' => 'x']]);

        $html = Livewire::test(EditPost::class, ['record' => $post->getKey()])->html();

        $this->assertStringNotContainsString('>'.__('filament-translatable-fields::translatable-fields.empty').'<', preg_replace('/\s+/', '', $html) ?? '');
    }

    public function test_empty_languages_get_a_badge_when_enabled(): void
    {
        config()->set('filament-translatable-fields.empty_badges', true);
        $post = Post::create(['title' => ['uk' => 'Привіт'], 'body' => ['uk' => 'x']]);

        $html = Livewire::test(EditPost::class, ['record' => $post->getKey()])->html();

        $this->assertGreaterThanOrEqual(4, substr_count($html, __('filament-translatable-fields::translatable-fields.empty')));
    }

    public function test_a_tab_with_a_validation_error_is_flagged(): void
    {
        $html = Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => '', 'en' => 'Hello'], 'body' => ['uk' => 'x']])
            ->call('create')
            ->assertHasFormErrors(['title.uk'])
            ->html();

        $this->assertStringContainsString('>!<', preg_replace('/\s+/', '', $html) ?? '');
    }

    public function test_the_copy_action_copies_the_default_language_into_another(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->callAction(TestAction::make('copyFromDefaultLocale')->schemaComponent('translatable-title-en.title.en'))
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Привіт', 'de' => null]]);
    }

    public function test_the_default_language_has_no_copy_action(): void
    {
        $html = Livewire::test(CreatePost::class)->html();

        $this->assertSame(
            2 * 2,
            substr_count($html, __('filament-translatable-fields::translatable-fields.copy_from', ['language' => 'Українська'])),
            'two languages (en, de) x two translatable fields (title, body) — never the default one',
        );
    }

    public function test_locales_come_from_the_registry(): void
    {
        TranslatableFields::locales(['pl', 'fr']);

        $this->assertSame(['pl', 'fr'], TranslatableFields::locales());
        $this->assertSame('pl', TranslatableFields::defaultLocale(), 'app.locale is not offered, the first language wins');
    }

    public function test_a_per_field_locale_list_overrides_the_registry(): void
    {
        $tabs = TranslatableTabs::make('x', fn () => TextInput::make('x'))->locales(['en', 'de']);

        $this->assertSame(['en', 'de'], $tabs->getLocales());
    }

    public function test_make_needs_a_field_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TranslatableTabs::make();
    }

    public function test_a_factory_is_mandatory_unless_wrapping(): void
    {
        $this->expectException(LogicException::class);

        $tabs = TranslatableTabs::make('x');
        $method = new \ReflectionMethod($tabs, 'buildTabs');
        $method->invoke($tabs);
    }
}
