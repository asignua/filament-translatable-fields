<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Support;

/**
 * "Is this language still empty?" for the value of any Filament field, including rich editors.
 */
final class Blank
{
    public static function is(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            if (trim($value) === '') {
                return true;
            }

            // Plain text: `<3` or `a < b` is not markup, so strip_tags() must not judge it.
            if (preg_match('/<[a-z!\/]/i', $value) !== 1) {
                return false;
            }

            // Rich HTML: empty paragraphs are empty, but an image, a table or a node such as a custom block or a
            // merge tag (serialised as an empty element with `data-type`) is content.
            return trim(strip_tags($value)) === ''
                && preg_match('/<(img|iframe|video|audio|hr|table|svg|figure)\b/i', $value) !== 1
                && preg_match('/<[a-z][^>]*\sdata-type\s*=/i', $value) !== 1;
        }

        if (is_array($value)) {
            return self::isBlankNode($value);
        }

        return false;
    }

    /**
     * A Tiptap document (`['type' => 'doc', 'content' => [...]]`) or a plain list of values.
     *
     * @param array<mixed> $node
     */
    private static function isBlankNode(array $node): bool
    {
        if ($node === []) {
            return true;
        }

        if (isset($node['type'])) {
            if (!in_array($node['type'], ['doc', 'paragraph'], true)) {
                return false;
            }

            if (isset($node['text']) && trim((string) $node['text']) !== '') {
                return false;
            }

            $children = $node['content'] ?? [];

            return !is_array($children) || self::all($children, static fn (mixed $child): bool => is_array($child) && self::isBlankNode($child));
        }

        return self::all($node, static fn (mixed $item): bool => self::is($item));
    }

    /**
     * @param array<mixed>          $items
     * @param callable(mixed): bool $test
     */
    private static function all(array $items, callable $test): bool
    {
        foreach ($items as $item) {
            if (!$test($item)) {
                return false;
            }
        }

        return true;
    }
}
