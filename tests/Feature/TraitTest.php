<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Tests\Feature;

use Asignua\FilamentTranslatableFields\Concerns\HandlesTranslatableFields;
use Asignua\FilamentTranslatableFields\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Workbench\App\Models\Post;

/**
 * A model with mass assignment off (`$guarded = ['*']`) cannot take the map through fill().
 */
class GuardedPost extends Model
{
    use HasTranslations;

    protected $table = 'posts';

    protected $guarded = ['*'];

    /** @var array<int, string> */
    public array $translatable = ['title'];
}

class TraitTest extends TestCase
{
    private function handler(): object
    {
        return new class
        {
            use HandlesTranslatableFields {
                normalizeTranslatableData as public;
                fillTranslations as public;
            }
        };
    }

    public function test_guarded_models_get_their_languages_through_set_translations(): void
    {
        $post = new GuardedPost;

        $rest = $this->handler()->fillTranslations($post, ['title' => ['uk' => 'Привіт', 'en' => null], 'other' => 1]);
        $post->save();

        $this->assertSame(['other' => 1], $rest);
        // spatie hides empty strings from getTranslations(); the raw JSON proves the language was written as ''.
        $this->assertSame(['uk' => 'Привіт'], $post->refresh()->getTranslations('title'));
        $this->assertSame(['uk' => 'Привіт', 'en' => ''], json_decode((string) $post->getRawOriginal('title'), true));
    }

    public function test_a_cleared_language_overwrites_the_stored_one_and_can_be_forgotten(): void
    {
        $post = GuardedPost::query()->create([]);
        $post->setTranslations('title', ['uk' => 'Привіт', 'en' => 'Hello'])->save();

        $this->handler()->fillTranslations($post, ['title' => ['en' => null]]);
        $this->assertSame('', $post->getTranslation('title', 'en', false));

        $this->handler()->fillTranslations($post, ['title' => ['en' => null]], forgetEmpty: true);
        $this->assertSame(['uk' => 'Привіт'], $post->getTranslations('title'));
    }

    public function test_normalize_accepts_a_model_class(): void
    {
        $data = $this->handler()->normalizeTranslatableData(['title' => ['uk' => null], 'body' => ['uk' => null]], Post::class);

        $this->assertSame(['uk' => ''], $data['title']);
        $this->assertSame(['uk' => ''], $data['body']);
    }
}
