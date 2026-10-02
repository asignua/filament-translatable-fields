<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<int, array<string, mixed>>|null $items
 * @property array<int, array<string, mixed>>|null $blocks
 */
class Post extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /** @var array<int, string> */
    public array $translatable = ['title', 'body'];

    protected function casts(): array
    {
        return ['items' => 'array', 'blocks' => 'array'];
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
