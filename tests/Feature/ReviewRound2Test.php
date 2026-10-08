<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Forms\TranslatableTabs;
use Asignua\FilamentTranslatableFields\Infolists\TranslatableEntry;
use Asignua\FilamentTranslatableFields\Support\TranslatedValue;
use Asignua\FilamentTranslatableFields\Tests\Fixtures\SchemaHarness;
use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Livewire;
use Workbench\App\Models\Post;

class ReviewRound2Test extends TestCase
{
    protected function tearDown(): void
    {
        SchemaHarness::$components = null;

        parent::tearDown();
    }

    public function test_required_any_fails_for_a_rich_editor_with_every_language_empty(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('body', fn () => RichEditor::make('body'))->requiredAny(),
        ];

        Livewire::test(SchemaHarness::class)
            ->call('save')
            ->assertHasFormErrors(['body.uk']);
    }

    public function test_required_any_passes_when_one_rich_language_is_filled(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('body', fn () => RichEditor::make('body'))->requiredAny(),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['body' => ['en' => '<p>Hello</p>']])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_required_any_on_a_text_input_still_works_with_blank_strings(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name'))->requiredAny(),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['name' => ['uk' => '', 'en' => '  ']])
            ->call('save')
            ->assertHasFormErrors(['name.uk']);
    }

    public function test_each_repeater_item_gets_its_own_required_any_context(): void
    {
        SchemaHarness::$components = static fn (): array => [
            Repeater::make('items')->schema([
                TextInput::make('strict'),
                TranslatableTabs::make('label', fn () => TextInput::make('label'))
                    ->requiredAny(static fn (Get $get): bool => (bool) $get('strict')),
            ]),
        ];

        Livewire::test(SchemaHarness::class)
            ->fillForm(['items' => [
                'a' => ['strict' => '1', 'label' => ['uk' => '', 'en' => '']],
                'b' => ['strict' => '', 'label' => ['uk' => '', 'en' => '']],
            ]])
            ->call('save')
            ->assertHasFormErrors(['items.a.label.uk'])
            ->assertHasNoFormErrors(['items.b.label.uk']);
    }

    public function test_the_factorys_own_validation_attribute_is_kept(): void
    {
        SchemaHarness::$components = static fn (): array => [
            TranslatableTabs::make('name', fn () => TextInput::make('name')->label('Назва')->validationAttribute('заголовок'))->requiredDefault(),
        ];

        $errors = Livewire::test(SchemaHarness::class)
            ->call('save')
            ->errors();

        $this->assertStringContainsString('заголовок (', (string) $errors->first('data.name.uk'));
    }

    public function test_from_map_resolves_arrays_and_marks_a_borrowed_language(): void
    {
        $this->assertSame('Привіт', TranslatedValue::forState(['title' => ['uk' => 'Привіт', 'en' => 'Hi']], 'title'));
        $this->assertSame('[en] Hi', TranslatedValue::forState(['title' => ['uk' => '', 'en' => 'Hi']], 'title'));
        $this->assertNull(TranslatedValue::forState(['other' => 1], 'title'));
        $this->assertNull(TranslatedValue::forState(null, 'title'));
    }

    public function test_the_entry_reads_json_items_and_plain_state_without_a_record(): void
    {
        $post = Post::create(['title' => ['uk' => 'Пост'], 'items' => [['label' => ['uk' => '', 'en' => 'Item']]]]);

        $schema = Schema::make(Livewire::new(SchemaHarness::class))->record($post)->components([
            RepeatableEntry::make('items')->schema([TranslatableEntry::make('label')]),
        ]);
        $html = $schema->toHtml();

        $this->assertStringContainsString('[en] Item', $html);
        $this->assertStringNotContainsString('Пост', $html);

        $plain = Schema::make(Livewire::new(SchemaHarness::class))->state(['title' => ['en' => 'Plain']])->components([
            TranslatableEntry::make('title'),
        ]);

        $this->assertStringContainsString('[en] Plain', $plain->toHtml());
    }
}
