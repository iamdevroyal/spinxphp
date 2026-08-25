<?php

declare(strict_types=1);

namespace Spinx\Validation;

/**
 * Laravel-familiar validation: pipe-delimited rule strings per field.
 *
 * Deliberately NOT auto-injected form-request DTOs (a Laravel FormRequest
 * resolves and validates itself before the controller method runs,
 * requiring the container to know how to build the right DTO for the
 * right route) — that's a larger, more magic-heavy design this
 * framework hasn't taken on. Call Validator::make() explicitly in a
 * controller instead; it's one extra line and keeps what's happening
 * visible at the call site rather than hidden behind a type hint.
 *
 *   use Spinx\Validation\{Validator, ValidationException};
 *
 *   $data = Validator::make($request->request->all(), [
 *       'title' => 'required|string|max:255',
 *       'price' => 'required|numeric|min:0',
 *       'email' => 'nullable|email',
 *   ])->validate(); // throws ValidationException on failure, otherwise returns the validated data
 *
 * Supported rules: required, nullable, string, integer/int, numeric,
 * boolean/bool, array, email, date, min:N, max:N, in:a,b,c, confirmed
 * (checks a matching "{field}_confirmation" input). Unknown rule names
 * are ignored rather than treated as failures, so a typo silently does
 * nothing rather than mysteriously rejecting valid data — a deliberate
 * tradeoff for a small, dependency-free validator; write a custom check
 * in the controller for anything not covered here.
 *
 * mb_strlen is used for string min/max so multi-byte characters count as
 * one character — same as Laravel's own behaviour. Requires ext-mbstring
 * (listed in composer.json's require block).
 */
final class Validator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules    Field => pipe-delimited rule string, e.g. 'required|string|max:255'
     * @param array<string, string> $messages Optional overrides, keyed 'field.rule' => custom message
     */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    /** @return array<string, string[]> */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /**
     * Returns only the fields that had rules declared, with values
     * present in $data — anything in $data but not in $rules is
     * dropped, same "explicit allowlist" philosophy as Model::$fillable.
     *
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;

        foreach ($this->rules as $field => $ruleString) {
            $ruleNames = explode('|', $ruleString);
            $value     = $this->data[$field] ?? null;
            $present   = array_key_exists($field, $this->data) && $value !== null && $value !== '';

            // If the field is nullable and not present, skip all remaining rules.
            if (in_array('nullable', $ruleNames, true) && !$present) {
                continue;
            }

            foreach ($ruleNames as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                [$ruleName, $parameter] = str_contains($rule, ':')
                    ? explode(':', $rule, 2)
                    : [$rule, null];

                if (!$this->checkRule($ruleName, $field, $value, $present, $parameter)) {
                    $this->addError($field, $ruleName, $parameter);
                }
            }
        }
    }

    private function checkRule(
        string  $ruleName,
        string  $field,
        mixed   $value,
        bool    $present,
        ?string $parameter,
    ): bool {
        return match ($ruleName) {
            'required'         => $present,
            'string'           => !$present || is_string($value),
            'integer', 'int'   => !$present || (is_numeric($value) && (int) $value == $value),
            'numeric'          => !$present || is_numeric($value),
            'boolean', 'bool'  => !$present || in_array($value, [true, false, 0, 1, '0', '1'], true),
            'array'            => !$present || is_array($value),
            'email'            => !$present || (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false),
            'date'             => !$present || (is_string($value) && strtotime($value) !== false),
            'min'              => !$present || $this->checkMin($value, (float) $parameter),
            'max'              => !$present || $this->checkMax($value, (float) $parameter),
            'in'               => !$present || in_array((string) $value, explode(',', (string) $parameter), true),
            'confirmed'        => !$present || (($this->data["{$field}_confirmation"] ?? null) === $value),
            default            => true,  // Unknown rules silently pass — documented deliberate tradeoff
        };
    }

    private function checkMin(mixed $value, float $min): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return is_numeric($value) && (float) $value >= $min;
    }

    private function checkMax(mixed $value, float $max): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return is_numeric($value) && (float) $value <= $max;
    }

    private function addError(string $field, string $ruleName, ?string $parameter): void
    {
        $key                  = "{$field}.{$ruleName}";
        $this->errors[$field][] = $this->messages[$key] ?? $this->defaultMessage($field, $ruleName, $parameter);
    }

    private function defaultMessage(string $field, string $ruleName, ?string $parameter): string
    {
        return match ($ruleName) {
            'required'         => "The {$field} field is required.",
            'string'           => "The {$field} field must be a string.",
            'integer', 'int'   => "The {$field} field must be an integer.",
            'numeric'          => "The {$field} field must be a number.",
            'boolean', 'bool'  => "The {$field} field must be true or false.",
            'array'            => "The {$field} field must be an array.",
            'email'            => "The {$field} field must be a valid email address.",
            'date'             => "The {$field} field must be a valid date.",
            'min'              => "The {$field} field must be at least {$parameter}.",
            'max'              => "The {$field} field must not be greater than {$parameter}.",
            'in'               => "The selected {$field} is invalid.",
            'confirmed'        => "The {$field} confirmation does not match.",
            default            => "The {$field} field is invalid.",
        };
    }
}
