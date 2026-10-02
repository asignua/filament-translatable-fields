<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Posts;

use Asignua\FilamentTranslatableFields\Forms\Translatable;
use Asignua\FilamentTranslatableFields\Forms\TranslatableTabs;
use Asignua\FilamentTranslatableFields\Infolists\TranslatableEntry;
use Asignua\FilamentTranslatableFields\Tables\TranslatableColumn;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Posts\Pages\CreatePost;
use Workbench\App\Filament\Resources\Posts\Pages\EditPost;
use Workbench\App\Filament\Resources\Posts\Pages\ListPosts;
use Workbench\App\Filament\Resources\Posts\Pages\ViewPost;
use Workbench\App\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableTabs::make('title', fn (string $locale) => TextInput::make('title')->label('Title')->maxLength(255))
                ->requiredDefault(),
            Translatable::field(Textarea::make('body')->label('Body'))->requiredAny(),

            Repeater::make('items')
                ->defaultItems(0)
                ->schema([
                    TextInput::make('sku'),
                    Translatable::field(TextInput::make('label')),
                    // Same name as the page's own translatable attribute: must NOT be read from the record.
                    Translatable::field(TextInput::make('title')),
                ]),

            Builder::make('blocks')
                ->blocks([
                    Builder\Block::make('heading')->schema([
                        Translatable::field(TextInput::make('text')),
                    ]),
                    Builder\Block::make('note')->schema([
                        Translatable::field(Textarea::make('text')),
                        TextInput::make('author'),
                    ]),
                ]),

            Repeater::make('sections')
                ->relationship()
                ->defaultItems(0)
                ->orderColumn('sort')
                ->schema([
                    Translatable::field(TextInput::make('heading')),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TranslatableEntry::make('title'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->alignEnd(),
            TranslatableColumn::make('title')->searchAcrossLocales()->sortableByLocale(),
            TranslatableColumn::make('body')->marker(false),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'view' => ViewPost::route('/{record}'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
