<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Filament\Resources\Posts\Pages\ViewPost;
use Workbench\App\Models\Post;

class ColumnAndEntryTest extends TestCase
{
    public function test_the_current_language_is_shown_without_a_marker(): void
    {
        $post = Post::create(['title' => ['uk' => 'Привіт', 'en' => 'Hello']]);

        Livewire::test(ListPosts::class)->assertTableColumnStateSet('title', 'Привіт', $post);
    }

    public function test_a_missing_language_falls_back_with_a_marker(): void
    {
        $post = Post::create(['title' => ['uk' => '', 'en' => 'Hello']]);

        Livewire::test(ListPosts::class)->assertTableColumnStateSet('title', '[en] Hello', $post);
    }

    public function test_the_default_language_is_tried_before_the_others(): void
    {
        $post = Post::create(['title' => ['de' => 'Hallo', 'uk' => 'Привіт']]);
        app()->setLocale('en');

        Livewire::test(ListPosts::class)->assertTableColumnStateSet('title', '[uk] Привіт', $post);
    }

    public function test_the_marker_can_be_turned_off_per_column(): void
    {
        $post = Post::create(['title' => ['uk' => 'T'], 'body' => ['en' => 'Only english']]);

        Livewire::test(ListPosts::class)->assertTableColumnStateSet('body', 'Only english', $post);
    }

    public function test_the_marker_format_comes_from_config(): void
    {
        config()->set('filament-translatable-fields.marker', '(:locale) ');
        $post = Post::create(['title' => ['en' => 'Hello']]);

        Livewire::test(ListPosts::class)->assertTableColumnStateSet('title', '(en) Hello', $post);
    }

    public function test_search_looks_into_every_language(): void
    {
        $english = Post::create(['title' => ['uk' => 'Один', 'en' => 'Needle']]);
        $other = Post::create(['title' => ['uk' => 'Інший', 'en' => 'Hay']]);

        Livewire::test(ListPosts::class)
            ->searchTable('Needle')
            ->assertCanSeeTableRecords([$english])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_sorting_uses_the_current_language(): void
    {
        $b = Post::create(['title' => ['uk' => 'Б']]);
        $a = Post::create(['title' => ['uk' => 'А']]);

        Livewire::test(ListPosts::class)
            ->sortTable('title')
            ->assertCanSeeTableRecords([$a, $b], inOrder: true);
    }

    public function test_the_entry_uses_the_same_fallback(): void
    {
        $post = Post::create(['title' => ['en' => 'Hello']]);

        $html = Livewire::test(ViewPost::class, ['record' => $post->getKey()])->html();

        $this->assertStringContainsString('[en] Hello', $html);
    }
}
