<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Unit;

use Asignua\FilamentTranslatableFields\Support\Blank;
use Asignua\FilamentTranslatableFields\Support\TranslatedValue;
use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Asignua\FilamentTranslatableFields\TranslatableFields;
use PHPUnit\Framework\Attributes\DataProvider;
use Workbench\App\Models\Post;
use Workbench\App\Models\Section;

class SupportTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function values(): array
    {
        return [
            'null' => [null, true],
            'empty string' => ['', true],
            'spaces' => ['   ', true],
            'empty paragraph' => ['<p></p>', true],
            'nbsp-less html' => ['<p> </p>', true],
            'text' => ['Hello', false],
            'html text' => ['<p>Hello</p>', false],
            'image only' => ['<p><img src="a.png"></p>', false],
            'empty doc' => [['type' => 'doc', 'content' => [['type' => 'paragraph']]], true],
            'doc with text' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hi']]]]], false],
            'doc with a heading' => [['type' => 'doc', 'content' => [['type' => 'heading']]], false],
            'empty array' => [[], true],
            'list of blanks' => [['', null], true],
            'int' => [0, false],
        ];
    }

    #[DataProvider('values')]
    public function test_blank_detection(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, Blank::is($value));
    }

    public function test_locales_fall_back_to_the_spatie_list(): void
    {
        TranslatableFields::reset();
        config()->set('translatable.locales', ['en' => ['en-GB'], 'nl', 'fr']);

        $this->assertSame(['en', 'nl', 'fr'], TranslatableFields::locales());
    }

    public function test_config_beats_spatie_and_the_registry_beats_config(): void
    {
        TranslatableFields::reset();
        config()->set('translatable.locales', ['en']);
        config()->set('filament-translatable-fields.locales', ['uk', 'pl']);

        $this->assertSame(['uk', 'pl'], TranslatableFields::locales());

        TranslatableFields::locales(fn (): array => ['de']);

        $this->assertSame(['de'], TranslatableFields::locales());
    }

    public function test_the_default_locale_is_configurable_and_must_be_offered(): void
    {
        TranslatableFields::defaultLocale('en');
        $this->assertSame('en', TranslatableFields::defaultLocale());

        TranslatableFields::defaultLocale('zz');
        $this->assertSame('uk', TranslatableFields::defaultLocale());
    }

    public function test_labels(): void
    {
        $this->assertSame('Українська', TranslatableFields::label('uk'));
        $this->assertSame('English', TranslatableFields::label('en'));

        config()->set('filament-translatable-fields.labels', ['en' => 'EN (config)']);
        $this->assertSame('EN (config)', TranslatableFields::label('en'));

        TranslatableFields::labels(['en' => 'EN (code)']);
        $this->assertSame('EN (code)', TranslatableFields::label('en'));
    }

    public function test_normalize_turns_null_into_empty_strings_but_leaves_absent_languages_alone(): void
    {
        $data = TranslatableFields::normalize(['title' => ['uk' => 'T', 'en' => null], 'other' => ['en' => null]], ['title', 'missing']);

        $this->assertSame(['uk' => 'T', 'en' => ''], $data['title']);
        $this->assertArrayNotHasKey('de', $data['title']);
        $this->assertNull($data['other']['en'], 'only translatable fields are touched');
    }

    public function test_translated_value_reads_a_related_model(): void
    {
        $post = Post::create(['title' => ['en' => 'Hello']]);
        $section = $post->sections()->create(['heading' => ['en' => 'Sec']]);
        $section->setRelation('post', $post);

        $this->assertSame('[en] Hello', TranslatedValue::for($section, 'post.title'));
        $this->assertNull(TranslatedValue::for($section, 'nope.title'));
    }

    public function test_translated_value_is_null_when_every_language_is_empty(): void
    {
        $this->assertNull(TranslatedValue::for(new Section(['heading' => []]), 'heading'));
    }

    public function test_translated_value_never_writes_back(): void
    {
        $post = Post::create(['title' => ['en' => 'Hello']]);

        TranslatedValue::for($post, 'title');

        $this->assertSame(['en' => 'Hello'], $post->refresh()->getTranslations('title'));
    }
}
