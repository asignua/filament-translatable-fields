<?php

declare(strict_types=1);

namespace Asignua\FilamentTranslatableFields\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails when EVERY language of a field is blank in the sense of {@see Blank}. Laravel's `required_without_all` cannot
 * do it: it counts an empty Tiptap document (`['type' => 'doc', …]`, which a rich editor hydrates an empty language
 * with) as present. Implicit, so it also runs when the default language arrives as `null` or `''`.
 */
final class RequiredInAnyLocale implements ValidationRule
{
    public bool $implicit = true;

    /**
     * @param array<int, mixed> $values the state of every language of the field
     */
    public function __construct(private readonly array $values) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach ([$value, ...$this->values] as $candidate) {
            if (!Blank::is($candidate)) {
                return;
            }
        }

        $fail('validation.required')->translate();
    }
}
