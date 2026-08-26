<?php

declare(strict_types=1);

namespace Spinx\Validation;

/**
 * Static facade for Validation.
 *
 * Usage:
 *   Validate::make($data, ['email' => 'required|email'])->validate();
 *   Validate::check($data, $rules);         // throws on failure
 *   Validate::passes($data, $rules);        // returns bool
 *   Validate::errors($data, $rules);        // returns error array
 */
final class Validate
{
    /**
     * Create a new Validator instance.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     */
    public static function make(array $data, array $rules, array $messages = []): Validator
    {
        return Validator::make($data, $rules, $messages);
    }

    /**
     * Validate $data against $rules. Returns validated data or throws ValidationException.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function check(array $data, array $rules, array $messages = []): array
    {
        return Validator::make($data, $rules, $messages)->validate();
    }

    /**
     * Returns true if $data passes all $rules, false otherwise.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     */
    public static function passes(array $data, array $rules, array $messages = []): bool
    {
        return !Validator::make($data, $rules, $messages)->fails();
    }

    /**
     * Returns the validation errors array (empty if all passes).
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @return array<string, string[]>
     */
    public static function errors(array $data, array $rules, array $messages = []): array
    {
        return Validator::make($data, $rules, $messages)->errors();
    }
}
