<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Posts\Pages\CreatePost;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Models\Post;

/**
 * The reason the plugin exists: translations inside a Repeater / Builder must survive a reorder and a save.
 */
class NestedFieldsTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function items(): array
    {
        return [
            'a' => ['sku' => 'A', 'label' => ['uk' => 'Перший', 'en' => 'First', 'de' => null]],
            'b' => ['sku' => 'B', 'label' => ['uk' => 'Другий', 'en' => 'Second', 'de' => 'Zweite']],
            'c' => ['sku' => 'C', 'label' => ['uk' => 'Третій', 'en' => null, 'de' => null]],
        ];
    }

    public function test_json_repeater_round_trips_all_languages_in_order(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => 'T'], 'body' => ['uk' => 'b'], 'items' => $this->items()])
            ->call('create')
            ->assertHasNoFormErrors();

        $items = Post::query()->firstOrFail()->items;

        $this->assertSame(['A', 'B', 'C'], array_column($items, 'sku'));
        $this->assertSame('Другий', $items[1]['label']['uk']);
        $this->assertSame('Zweite', $items[1]['label']['de']);
    }

    public function test_json_repeater_edit_hydrates_every_item_language_without_the_record(): void
    {
        $post = Post::create(['title' => ['uk' => 'T'], 'items' => array_values($this->items())]);

        $component = Livewire::test(EditPost::class, ['record' => $post->getKey()]);

        $items = array_values($component->get('data.items'));

        $this->assertSame('Перший', $items[0]['label']['uk']);
        $this->assertSame('First', $items[0]['label']['en']);
        $this->assertSame('Zweite', $items[1]['label']['de']);
    }

    public function test_translations_stay_with_their_item_after_a_reorder_and_save(): void
    {
        $post = Post::create(['title' => ['uk' => 'T'], 'body' => ['uk' => 'b'], 'items' => array_values($this->items())]);

        $component = Livewire::test(EditPost::class, ['record' => $post->getKey()]);

        $firstKey = array_key_first($component->get('data.items'));

        $component
            ->callAction(TestAction::make('moveDown')->schemaComponent('items')->arguments(['item' => $firstKey]))
            ->call('save')
            ->assertHasNoFormErrors();

        $items = $post->refresh()->items;

        $this->assertSame(['B', 'A', 'C'], array_column($items, 'sku'));
        $this->assertSame(['uk' => 'Другий', 'en' => 'Second', 'de' => 'Zweite'], $items[0]['label']);
        $this->assertSame('First', $items[1]['label']['en']);
        $this->assertSame('Третій', $items[2]['label']['uk']);
    }

    public function test_builder_blocks_keep_languages_and_order(): void
    {
        $blocks = [
            'x' => ['type' => 'heading', 'data' => ['text' => ['uk' => 'Заголовок', 'en' => 'Heading', 'de' => null]]],
            'y' => ['type' => 'note', 'data' => ['text' => ['uk' => 'Нотатка', 'en' => '', 'de' => 'Notiz'], 'author' => 'me']],
        ];

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => ['uk' => 'T'], 'body' => ['uk' => 'b'], 'blocks' => $blocks])
            ->call('create')
            ->assertHasNoFormErrors();

        $stored = Post::query()->firstOrFail()->blocks;

        $this->assertSame(['heading', 'note'], array_column($stored, 'type'));
        $this->assertSame('Heading', $stored[0]['data']['text']['en']);
        $this->assertSame('Notiz', $stored[1]['data']['text']['de']);
        $this->assertSame('me', $stored[1]['data']['author']);
    }

    public function test_builder_edit_reads_languages_from_the_block_data(): void
    {
        $post = Post::create(['title' => ['uk' => 'T'], 'blocks' => [
            ['type' => 'heading', 'data' => ['text' => ['uk' => 'Заголовок', 'en' => 'Heading']]],
        ]]);

        $component = Livewire::test(EditPost::class, ['record' => $post->getKey()]);

        $block = array_values($component->get('data.blocks'))[0];

        $this->assertSame('Heading', $block['data']['text']['en']);
    }

    public function test_relationship_repeater_creates_rows_with_their_translations_in_order(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => ['uk' => 'T'],
                'body' => ['uk' => 'b'],
                'sections' => [
                    's1' => ['heading' => ['uk' => 'Один', 'en' => 'One', 'de' => null]],
                    's2' => ['heading' => ['uk' => 'Два', 'en' => 'Two', 'de' => 'Zwei']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sections = Post::query()->firstOrFail()->sections()->orderBy('sort')->get();

        $this->assertCount(2, $sections);
        $this->assertSame('One', $sections[0]->getTranslation('heading', 'en'));
        $this->assertSame(['uk' => 'Два', 'en' => 'Two', 'de' => 'Zwei'], $sections[1]->getTranslations('heading'));
    }

    public function test_relationship_repeater_edit_hydrates_from_each_related_record_and_saves_a_cleared_language(): void
    {
        $post = Post::create(['title' => ['uk' => 'T'], 'body' => ['uk' => 'b']]);
        $first = $post->sections()->create(['heading' => ['uk' => 'Один', 'en' => 'One'], 'sort' => 1]);
        $second = $post->sections()->create(['heading' => ['uk' => 'Два', 'en' => 'Two'], 'sort' => 2]);

        $component = Livewire::test(EditPost::class, ['record' => $post->getKey()]);

        $rows = $component->get('data.sections');

        $this->assertSame('One', $rows["record-{$first->id}"]['heading']['en']);
        $this->assertSame('Two', $rows["record-{$second->id}"]['heading']['en']);

        $rows["record-{$first->id}"]['heading']['en'] = null;

        $component
            ->set('data.sections', $rows)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('', $first->refresh()->getTranslations('heading')['en'] ?? '');
        $this->assertSame('Two', $second->refresh()->getTranslation('heading', 'en'));
    }

    public function test_a_repeater_field_named_like_a_record_attribute_is_not_read_from_the_record(): void
    {
        $post = Post::create([
            'title' => ['uk' => 'Запис', 'en' => 'Record'],
            'items' => [['sku' => 'A', 'title' => ['uk' => 'Елемент', 'en' => 'Item']]],
        ]);

        $component = Livewire::test(EditPost::class, ['record' => $post->getKey()]);

        $item = array_values($component->get('data.items'))[0];

        $this->assertSame('Елемент', $item['title']['uk']);
        $this->assertSame('Item', $item['title']['en']);
        $this->assertSame('Запис', $component->get('data.title.uk'));
    }
}
