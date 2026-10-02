<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Forms\TranslatableTabs;
use Asignua\FilamentTranslatableFields\Tables\TranslatableColumn;
use Asignua\FilamentTranslatableFields\Tests\Fixtures\EditPostWithFillHook;
use Asignua\FilamentTranslatableFields\Tests\Fixtures\SchemaHarness;
use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use LogicException;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Models\Post;
use Workbench\App\Models\Section;

class ReviewFixesTest extends TestCase
{
    protected function tearDown(): void
    {
        SchemaHarness::$components = null;
        EditPostWithFillHook::$mutate = null;

        parent::tearDown();
    }

    public function test_changes_made_in_mutate_form_data_before_fill_survive(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello'], 'body' => ['uk' => 'x']]);
        EditPostWithFillHook::$mutate = static function (array $data): array {
            $data['title']['de'] = 'Vorbefüllt';
            $data['title']['en'] = 'Normalised';

            return $data;
        };

        Livewire::test(EditPostWithFillHook::class, ['record' => $post->getKey()])
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Normalised', 'de' => 'Vorbefüllt']]);
    }

    public function test_the_record_is_read_when_the_field_did_not_arrive_as_a_map(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello'], 'body' => ['uk' => 'x']]);
        EditPostWithFillHook::$mutate = static function (array $data): array {
            unset($data['title']);

            return $data;
        };

        Livewire::test(EditPostWithFillHook::class, ['record' => $post->getKey()])
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => null]]);
    }

    public function test_a_factory_after_state_hydrated_hook_is_kept(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn (string $locale) => TextInput::make('name')->afterStateHydrated(fn (TextInput $component) => $component->state("hook {$locale}"))),
        ];

        Livewire::test(SchemaHarness::class)
            ->assertSchemaStateSet(['name' => ['uk' => 'hook uk', 'en' => 'hook en', 'de' => 'hook de']]);
    }

    public function test_required_any_on_a_single_language_is_required(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name'))->locales(['uk'])->requiredAny(),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['name' => ['uk' => '']])
            ->call('save')
            ->assertHasFormErrors(['name.uk' => 'required']);
    }

    public function test_required_default_follows_the_field_locales(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name'))->locales(['en', 'de'])->requiredDefault(),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['name' => ['en' => '', 'de' => 'Hallo']])
            ->call('save')
            ->assertHasFormErrors(['name.en' => 'required']);
    }

    public function test_required_in_after_required_default_replaces_it(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name'))->requiredDefault()->requiredIn(['de']),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['name' => ['uk' => '', 'en' => '', 'de' => '']])
            ->call('save')
            ->assertHasFormErrors(['name.de' => 'required'])
            ->assertHasNoFormErrors(['name.uk']);
    }

    public function test_an_error_nested_under_a_language_flags_its_tab(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name')),
        ];

        $html = Livewire::test(SchemaHarness::class)->call('flag', 'data.name.en.0.text')->html();

        $this->assertStringContainsString('>!<', preg_replace('/\s+/', '', $html) ?? '');
    }

    public function test_copy_asks_before_overwriting_a_filled_language(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello'], 'body' => ['uk' => 'x']]);
        $action = TestAction::make('copyFromDefaultLocale')->schemaComponent('translatable-title-en.title.en');

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->mountAction($action)
            ->assertActionMounted($action)
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => null]], 'form');
    }

    public function test_copy_does_nothing_while_the_default_language_is_empty(): void
    {
        $post = Post::create(['title' => ['en' => 'Hello'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->callAction(TestAction::make('copyFromDefaultLocale')->schemaComponent('translatable-title-en.title.en'))
            ->assertSchemaStateSet(['title' => ['uk' => null, 'en' => 'Hello', 'de' => null]]);
    }

    public function test_sorting_falls_back_like_the_cell(): void
    {
        app()->setLocale('uk');

        $c = Post::create(['title' => ['uk' => 'В']]);
        $a = Post::create(['title' => ['en' => 'Я-only-en']]);
        $b = Post::create(['title' => ['uk' => 'Б']]);

        // The en-only row shows "[en] Я-only-en" and must sort as "Я", not as an empty (first) value.
        Livewire::test(ListPosts::class)
            ->sortTable('title')
            ->assertCanSeeTableRecords([$b, $c, $a], inOrder: true);
    }

    public function test_sorting_a_relationship_attribute_is_refused(): void
    {
        $this->expectException(LogicException::class);

        TranslatableColumn::make('post.title')->sortableByLocale();
    }

    public function test_search_on_a_relationship_attribute_goes_through_where_has(): void
    {
        $column = TranslatableColumn::make('post.title')->searchAcrossLocales();
        $query = Section::query();

        $post = Post::create(['title' => ['en' => 'Needle']]);
        $hit = $post->sections()->create(['heading' => ['uk' => 'x']]);
        Post::create(['title' => ['en' => 'Hay']])->sections()->create(['heading' => ['uk' => 'y']]);

        $isFirst = true;
        $column->applySearchConstraint($query, 'Needle', $isFirst);

        $this->assertSame([$hit->getKey()], $query->pluck('id')->all());
    }
}
