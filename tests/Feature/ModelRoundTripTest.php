<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Posts\Pages\CreatePost;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Models\Post;

class ModelRoundTripTest extends TestCase
{
    public function test_create_stores_every_language(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => 'Hallo'],
                'body' => ['uk' => 'Текст', 'en' => '', 'de' => ''],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->firstOrFail();

        $this->assertSame(['uk' => 'Привіт', 'en' => 'Hello', 'de' => 'Hallo'], $post->getTranslations('title'));
        $this->assertSame('Текст', $post->getTranslation('body', 'uk'));
    }

    public function test_edit_hydrates_each_language_from_the_record_not_the_current_locale(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->assertSchemaStateSet([
                'title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => null],
            ]);
    }

    public function test_hydration_does_not_depend_on_the_app_locale(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello']]);
        app()->setLocale('en');

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => null]]);
    }

    public function test_edit_saves_a_changed_language_and_keeps_the_others(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['title' => ['uk' => 'Привіт!', 'en' => 'Hello', 'de' => 'Hallo']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['uk' => 'Привіт!', 'en' => 'Hello', 'de' => 'Hallo'], $post->refresh()->getTranslations('title'));
    }

    public function test_clearing_a_language_clears_it_in_the_database(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['title' => ['uk' => 'Привіт', 'en' => null, 'de' => null]])
            ->call('save')
            ->assertHasNoFormErrors();

        $translations = $post->refresh()->getTranslations('title');

        $this->assertSame('Привіт', $translations['uk']);
        $this->assertSame('', $translations['en'] ?? '');
        $this->assertNotContains('Hello', json_decode((string) $post->getRawOriginal('title'), true), 'the old text must be gone from the JSON');
    }

    public function test_unicode_stays_unescaped_in_the_json_column(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => 'Дякуємо', 'en' => '', 'de' => ''], 'body' => ['uk' => 'ок']])
            ->call('create');

        $raw = (string) Post::query()->firstOrFail()->getRawOriginal('title');

        $this->assertStringContainsString('Дякуємо', $raw);
    }

    public function test_the_default_language_is_required(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => '', 'en' => 'Hello'], 'body' => ['en' => 'x']])
            ->call('create')
            ->assertHasFormErrors(['title.uk' => 'required']);
    }

    public function test_required_any_accepts_any_single_language(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => 'T'], 'body' => ['uk' => '', 'en' => '', 'de' => 'Nur Deutsch']])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Nur Deutsch', Post::query()->firstOrFail()->getTranslation('body', 'de'));
    }

    public function test_required_any_fails_when_every_language_is_empty(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => 'T'], 'body' => ['uk' => '', 'en' => '', 'de' => '']])
            ->call('create')
            ->assertHasFormErrors(['body.uk']);
    }
}
