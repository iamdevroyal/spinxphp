<?php

declare(strict_types=1);

namespace Spinx\Validation;

/**
 * Thrown by Validator::validate() when one or more fields fail their rules.
 *
 * Carry the full structured error map so a controller can either inspect
 * it directly or forward it as a 422 JSON response body:
 *
 *   try {
 *       $data = Validator::make($input, $rules)->validate();
 *   } catch (ValidationException $e) {
 *       return new JsonResponse(['errors' => $e->errors()], 422);
 *   }
 */
final class ValidationException extends \RuntimeException
{
    /** @param array<string, string[]> $errors Field => list of error messages */
    public function __construct(
        private readonly array $errors,
    ) {
        parent::__construct('The given data was invalid.');
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        return $this->errors;
    }
}
