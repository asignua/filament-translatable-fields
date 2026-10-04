<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Forms\TranslatableTabs;
use Asignua\FilamentTranslatableFields\Tables\TranslatableColumn;
use Asignua\FilamentTranslatableFields\Tests\Fixtures\EditPostWithFillHook;
use Asignua\FilamentTranslatableFields\Tests\Fixtures\RecordSchemaHarness;
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

    public function test_a_record_bound_form_filled_without_data_reads_the_record(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello']]);
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('title', fn () => TextInput::make('title')),
        ];

        Livewire::test(RecordSchemaHarness::class, ['post' => $post])
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => null]]);
    }

    public function test_a_record_bound_form_filled_without_data_keeps_values_that_look_blank_as_html(): void
    {
        $block = '<div data-type="customBlock" data-config="{}" data-id="video"></div>';
        $post = Post::create(['title' => ['uk' => '<3', 'en' => $block, 'de' => '<p></p>']]);
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('title', fn () => TextInput::make('title')),
        ];

        Livewire::test(RecordSchemaHarness::class, ['post' => $post])
            ->assertSchemaStateSet(['title' => ['uk' => '<3', 'en' => $block, 'de' => '<p></p>']]);
    }

    public function test_the_input_hooks_see_the_record_value(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello']]);
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('title', fn () => TextInput::make('title')->afterStateHydrated(fn (TextInput $component, ?string $state) => $component->state($state === null ? null : mb_strtoupper($state)))),
        ];

        Livewire::test(RecordSchemaHarness::class, ['post' => $post])
            ->assertSchemaStateSet(['title' => ['uk' => 'ПРИВІТ', 'en' => 'HELLO', 'de' => null]]);
    }

    public function test_a_table_record_action_opens_with_the_record_translations_and_keeps_them(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello']]);
        $action = TestAction::make('translate')->table($post);

        Livewire::test(ListPosts::class)
            ->mountAction($action)
            ->assertActionDataSet(['title' => ['uk' => 'Привіт', 'en' => 'Hello', 'de' => null]])
            ->fillForm(['title.de' => 'Hallo'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(['uk' => 'Привіт', 'en' => 'Hello', 'de' => 'Hallo'], $post->refresh()->getTranslations('title'));
    }

    public function test_required_all_follows_the_field_locales(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name'))->locales(['uk', 'fr'])->requiredAll(),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['name' => ['uk' => 'Так', 'fr' => '']])
            ->call('save')
            ->assertHasFormErrors(['name.fr' => 'required']);
    }

    public function test_copy_stays_clickable_while_the_default_language_is_empty(): void
    {
        // The inputs are not live: a server-rendered `disabled` would outlive the editor typing the default language.
        $post = Post::create(['title' => ['en' => 'Hello'], 'body' => ['uk' => 'x']]);
        $action = TestAction::make('copyFromDefaultLocale')->schemaComponent('translatable-title-en.title.en');

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->assertActionEnabled($action);
    }

    public function test_copy_with_an_empty_default_language_says_so(): void
    {
        $post = Post::create(['title' => ['de' => 'Hallo'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->callAction(TestAction::make('copyFromDefaultLocale')->schemaComponent('translatable-title-en.title.en'))
            ->assertNotified(__('filament-translatable-fields::translatable-fields.nothing_to_copy', ['language' => 'Українська']))
            ->assertSchemaStateSet(['title' => ['uk' => null, 'en' => null, 'de' => 'Hallo']]);
    }

    public function test_copy_uses_the_default_language_typed_since_the_last_render(): void
    {
        $post = Post::create(['title' => ['de' => 'Hallo'], 'body' => ['uk' => 'x']]);

        Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->set('data.title.uk', 'Привіт')
            ->callAction(TestAction::make('copyFromDefaultLocale')->schemaComponent('translatable-title-en.title.en'))
            ->assertSchemaStateSet(['title' => ['uk' => 'Привіт', 'en' => 'Привіт', 'de' => 'Hallo']]);
    }

    public function test_search_is_case_insensitive_on_mysql(): void
    {
        $query = Post::on('mysql')->newQuery();

        TranslatableColumn::applyLocaleSearch($query, 'title', 'Hello');

        $this->assertStringContainsString('lower((case when json_type(json_extract(`posts`.`title`, \'$."uk"\')) = \'NULL\' then null else json_unquote(json_extract(`posts`.`title`, \'$."uk"\')) end)) like lower(?)', $query->toSql());
    }

    public function test_a_cleared_language_never_searches_as_the_string_null_on_mysql(): void
    {
        $query = Post::on('mysql')->newQuery();

        TranslatableColumn::applyLocaleSearch($query, 'title', 'nu');

        // json_unquote() of a JSON null is the string 'null'; a bare json_unquote() in the search would match "nu".
        $this->assertDoesNotMatchRegularExpression('/(?<!else )json_unquote\(json_extract\(`posts`\.`title`, \'\$\."(uk|en|de)"\'\)\)\) like/', $query->toSql());
        $this->assertSame(3, substr_count($query->toSql(), "= 'NULL' then null"));
    }

    public function test_a_cleared_language_falls_through_when_sorting_on_mysql(): void
    {
        $query = Post::on('mysql')->newQuery();

        TranslatableColumn::applyLocaleSort($query, 'title', 'asc');

        $sql = $query->toSql();
        $this->assertStringContainsString('nullif(trim((case when json_type(json_extract(`posts`.`title`, \'$."uk"\')) = \'NULL\' then null else json_unquote(json_extract(`posts`.`title`, \'$."uk"\')) end)), \'\')', $sql);
        $this->assertStringNotContainsString('nullif(trim(json_unquote(', $sql);
        $this->assertStringEndsWith(' asc', $sql);
    }

    public function test_a_cleared_language_falls_through_when_sorting(): void
    {
        app()->setLocale('uk');

        // Filament's default save stores a cleared language as JSON null.
        $cleared = Post::create(['title' => ['uk' => null, 'en' => 'Я']]);
        $plain = Post::create(['title' => ['uk' => 'Б']]);

        Livewire::test(ListPosts::class)
            ->sortTable('title')
            ->assertCanSeeTableRecords([$plain, $cleared], inOrder: true);
    }

    public function test_sorting_skips_whitespace_and_tries_an_unconfigured_current_locale(): void
    {
        app()->setLocale('pl');

        $blank = Post::create(['title' => ['pl' => '   ', 'uk' => 'Я']]);
        $polish = Post::create(['title' => ['pl' => 'A', 'uk' => 'Ю']]);
        $plain = Post::create(['title' => ['uk' => 'Б']]);

        Livewire::test(ListPosts::class)
            ->sortTable('title')
            ->assertCanSeeTableRecords([$polish, $plain, $blank], inOrder: true);
    }
}
