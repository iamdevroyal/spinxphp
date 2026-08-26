<?php

declare(strict_types=1);

namespace Spinx\Validation;

/**
 * Laravel-familiar validation with an expanded, production-ready rule set.
 *
 * Usage (explicit, DDD-friendly):
 *   $validated = Validator::make($request->all(), [
 *       'name'     => 'required|string|min:2|max:100',
 *       'email'    => 'required|email|max:255',
 *       'password' => 'required|string|min:8|confirmed',
 *       'age'      => 'nullable|integer|between:18,120',
 *       'role'     => 'required|in:admin,editor,viewer',
 *       'avatar'   => 'nullable|url',
 *       'slug'     => 'required|alpha_dash|max:80',
 *       'phone'    => 'nullable|phone',
 *       'zip'      => 'nullable|digits:5',
 *       'score'    => 'required|numeric|gt:0',
 *   ])->validate();
 *
 *   // Or via Request facade:
 *   $validated = Request::validate(['email' => 'required|email']);
 *
 *   // Or via Validate facade:
 *   $validated = Validate::make($data, $rules)->validate();
 *
 * Supported rules:
 *   required, nullable, filled
 *   string, integer/int, numeric, float, boolean/bool, array
 *   email, url, ip, ipv4, ipv6, uuid
 *   min:N, max:N, between:min,max, size:N, digits:N, digits_between:min,max
 *   gt:N, lt:N, gte:N, lte:N
 *   min_words:N, max_words:N
 *   alpha, alpha_num, alpha_dash, alpha_spaces
 *   in:a,b,c, not_in:a,b,c
 *   confirmed (checks {field}_confirmation)
 *   same:other_field, different:other_field
 *   starts_with:prefix, ends_with:suffix, contains:str
 *   regex:pattern (applied as PHP regex)
 *   not_regex:pattern
 *   date, date_format:Y-m-d
 *   before:date, after:date
 *   phone (E.164 loose — digits, spaces, +, -, (, ), min 7 chars)
 *   json
 *   lowercase, uppercase
 *   prohibited (field must NOT be present/filled)
 *   accepted (must be yes/on/1/true)
 *   declined (must be no/off/0/false)
 */
final class Validator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    private bool $ran = false;

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules    Field => pipe-delimited rule string
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

    /** @return string[] */
    public function flatErrors(): array
    {
        $flat = [];
        foreach ($this->errors() as $messages) {
            foreach ($messages as $msg) {
                $flat[] = $msg;
            }
        }
        return $flat;
    }

    /** @return string|null First error for a field, or null */
    public function firstError(?string $field = null): ?string
    {
        $errors = $this->errors();
        if ($field !== null) {
            return $errors[$field][0] ?? null;
        }

        foreach ($errors as $msgs) {
            if (!empty($msgs)) {
                return $msgs[0];
            }
        }

        return null;
    }

    /**
     * Returns only the validated fields (allowlist). Throws on failure.
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

    /**
     * Returns validated data or null on failure (no exception).
     *
     * @return array<string, mixed>|null
     */
    public function safe(): ?array
    {
        if ($this->fails()) {
            return null;
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

            // If the field is nullable and not present/empty, skip all remaining rules.
            if (in_array('nullable', $ruleNames, true) && !$present) {
                continue;
            }

            // If field is prohibited it must NOT be present
            if (in_array('prohibited', $ruleNames, true)) {
                if ($present) {
                    $this->addError($field, 'prohibited', null);
                }
                continue;
            }

            foreach ($ruleNames as $rule) {
                if ($rule === 'nullable' || $rule === 'filled') {
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
            // ─── Presence ────────────────────────────────────────────────────────
            'required'                 => $present,
            'accepted'                 => $present && in_array($value, ['yes', 'on', '1', 1, true], true),
            'declined'                 => $present && in_array($value, ['no', 'off', '0', 0, false], true),
            'prohibited'               => !$present,

            // ─── Type rules ──────────────────────────────────────────────────────
            'string'                   => !$present || is_string($value),
            'integer', 'int'           => !$present || (is_numeric($value) && (int) $value == $value),
            'numeric'                  => !$present || is_numeric($value),
            'float', 'decimal'         => !$present || is_numeric($value),
            'boolean', 'bool'          => !$present || in_array($value, [true, false, 0, 1, '0', '1'], true),
            'array'                    => !$present || is_array($value),
            'json'                     => !$present || (is_string($value) && json_validate($value)),

            // ─── Format rules ────────────────────────────────────────────────────
            'email'                    => !$present || (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false),
            'url'                      => !$present || (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false),
            'ip'                       => !$present || (is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false),
            'ipv4'                     => !$present || (is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false),
            'ipv6'                     => !$present || (is_string($value) && filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false),
            'uuid'                     => !$present || (is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1),
            'phone'                    => !$present || (is_string($value) && preg_match('/^[+\d\s\-().]{7,20}$/', $value) === 1),
            'date'                     => !$present || (is_string($value) && strtotime($value) !== false),
            'date_format'              => !$present || $this->checkDateFormat((string) $value, (string) $parameter),
            'before'                   => !$present || (is_string($value) && strtotime($value) < strtotime((string) $parameter)),
            'after'                    => !$present || (is_string($value) && strtotime($value) > strtotime((string) $parameter)),
            'alpha'                    => !$present || (is_string($value) && preg_match('/^[a-zA-Z]+$/', $value) === 1),
            'alpha_num'                => !$present || (is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value) === 1),
            'alpha_dash'               => !$present || (is_string($value) && preg_match('/^[a-zA-Z0-9_\-]+$/', $value) === 1),
            'alpha_spaces'             => !$present || (is_string($value) && preg_match('/^[a-zA-Z\s]+$/', $value) === 1),
            'lowercase'                => !$present || (is_string($value) && $value === mb_strtolower($value)),
            'uppercase'                => !$present || (is_string($value) && $value === mb_strtoupper($value)),

            // ─── Size rules ──────────────────────────────────────────────────────
            'min'                      => !$present || $this->checkMin($value, (float) $parameter),
            'max'                      => !$present || $this->checkMax($value, (float) $parameter),
            'size'                     => !$present || $this->checkSize($value, (float) $parameter),
            'between'                  => !$present || $this->checkBetween($value, (string) $parameter),
            'gt'                       => !$present || (is_numeric($value) && (float) $value > (float) $parameter),
            'lt'                       => !$present || (is_numeric($value) && (float) $value < (float) $parameter),
            'gte'                      => !$present || (is_numeric($value) && (float) $value >= (float) $parameter),
            'lte'                      => !$present || (is_numeric($value) && (float) $value <= (float) $parameter),
            'digits'                   => !$present || (is_string($value) && ctype_digit($value) && strlen($value) === (int) $parameter),
            'digits_between'           => !$present || $this->checkDigitsBetween((string) $value, (string) $parameter),
            'min_words'                => !$present || (is_string($value) && str_word_count($value) >= (int) $parameter),
            'max_words'                => !$present || (is_string($value) && str_word_count($value) <= (int) $parameter),

            // ─── Content rules ───────────────────────────────────────────────────
            'in'                       => !$present || in_array((string) $value, explode(',', (string) $parameter), true),
            'not_in'                   => !$present || !in_array((string) $value, explode(',', (string) $parameter), true),
            'confirmed'                => !$present || (($this->data["{$field}_confirmation"] ?? null) === $value),
            'same'                     => !$present || (($this->data[$parameter ?? ''] ?? null) === $value),
            'different'                => !$present || (($this->data[$parameter ?? ''] ?? null) !== $value),
            'starts_with'              => !$present || (is_string($value) && str_starts_with($value, (string) $parameter)),
            'ends_with'                => !$present || (is_string($value) && str_ends_with($value, (string) $parameter)),
            'contains'                 => !$present || (is_string($value) && str_contains($value, (string) $parameter)),
            'regex'                    => !$present || (is_string($value) && preg_match($parameter ?? '/.*/', $value) === 1),
            'not_regex'                => !$present || (is_string($value) && preg_match($parameter ?? '/.*/', $value) === 0),

            default                    => true, // Unknown rules silently pass
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

    private function checkSize(mixed $value, float $size): bool
    {
        if (is_string($value)) {
            return mb_strlen($value) === (int) $size;
        }

        if (is_array($value)) {
            return count($value) === (int) $size;
        }

        return is_numeric($value) && (float) $value === $size;
    }

    private function checkBetween(mixed $value, string $parameter): bool
    {
        [$min, $max] = array_map('floatval', explode(',', $parameter, 2));

        if (is_string($value)) {
            $len = mb_strlen($value);
            return $len >= $min && $len <= $max;
        }

        if (is_array($value)) {
            $count = count($value);
            return $count >= $min && $count <= $max;
        }

        return is_numeric($value) && (float) $value >= $min && (float) $value <= $max;
    }

    private function checkDigitsBetween(string $value, string $parameter): bool
    {
        [$min, $max] = array_map('intval', explode(',', $parameter, 2));
        $len = strlen($value);

        return ctype_digit($value) && $len >= $min && $len <= $max;
    }

    private function checkDateFormat(string $value, string $format): bool
    {
        $d = \DateTime::createFromFormat($format, $value);
        return $d !== false && $d->format($format) === $value;
    }

    private function addError(string $field, string $ruleName, ?string $parameter): void
    {
        $key                  = "{$field}.{$ruleName}";
        $this->errors[$field][] = $this->messages[$key] ?? $this->defaultMessage($field, $ruleName, $parameter);
    }

    private function defaultMessage(string $field, string $ruleName, ?string $parameter): string
    {
        $label = str_replace(['_', '.'], ' ', $field);

        return match ($ruleName) {
            'required'         => "The {$label} field is required.",
            'accepted'         => "The {$label} field must be accepted.",
            'declined'         => "The {$label} field must be declined.",
            'prohibited'       => "The {$label} field is not allowed.",
            'string'           => "The {$label} field must be a string.",
            'integer', 'int'   => "The {$label} field must be an integer.",
            'numeric'          => "The {$label} field must be a number.",
            'float', 'decimal' => "The {$label} field must be a decimal number.",
            'boolean', 'bool'  => "The {$label} field must be true or false.",
            'array'            => "The {$label} field must be an array.",
            'json'             => "The {$label} field must be a valid JSON string.",
            'email'            => "The {$label} field must be a valid email address.",
            'url'              => "The {$label} field must be a valid URL.",
            'ip'               => "The {$label} field must be a valid IP address.",
            'ipv4'             => "The {$label} field must be a valid IPv4 address.",
            'ipv6'             => "The {$label} field must be a valid IPv6 address.",
            'uuid'             => "The {$label} field must be a valid UUID.",
            'phone'            => "The {$label} field must be a valid phone number.",
            'date'             => "The {$label} field must be a valid date.",
            'date_format'      => "The {$label} field does not match the expected date format {$parameter}.",
            'before'           => "The {$label} field must be a date before {$parameter}.",
            'after'            => "The {$label} field must be a date after {$parameter}.",
            'alpha'            => "The {$label} field may only contain alphabetic characters.",
            'alpha_num'        => "The {$label} field may only contain letters and numbers.",
            'alpha_dash'       => "The {$label} field may only contain letters, numbers, dashes, and underscores.",
            'alpha_spaces'     => "The {$label} field may only contain letters and spaces.",
            'lowercase'        => "The {$label} field must be lowercase.",
            'uppercase'        => "The {$label} field must be uppercase.",
            'min'              => "The {$label} field must be at least {$parameter}.",
            'max'              => "The {$label} field must not exceed {$parameter}.",
            'size'             => "The {$label} field must be exactly {$parameter}.",
            'between'          => "The {$label} field must be between {$parameter}.",
            'gt'               => "The {$label} field must be greater than {$parameter}.",
            'lt'               => "The {$label} field must be less than {$parameter}.",
            'gte'              => "The {$label} field must be greater than or equal to {$parameter}.",
            'lte'              => "The {$label} field must be less than or equal to {$parameter}.",
            'digits'           => "The {$label} field must be exactly {$parameter} digits.",
            'digits_between'   => "The {$label} field must be between {$parameter} digits.",
            'min_words'        => "The {$label} field must contain at least {$parameter} words.",
            'max_words'        => "The {$label} field must not contain more than {$parameter} words.",
            'in'               => "The selected {$label} is invalid.",
            'not_in'           => "The selected {$label} is not allowed.",
            'confirmed'        => "The {$label} confirmation does not match.",
            'same'             => "The {$label} field must match {$parameter}.",
            'different'        => "The {$label} field must be different from {$parameter}.",
            'starts_with'      => "The {$label} field must start with \"{$parameter}\".",
            'ends_with'        => "The {$label} field must end with \"{$parameter}\".",
            'contains'         => "The {$label} field must contain \"{$parameter}\".",
            'regex'            => "The {$label} field format is invalid.",
            'not_regex'        => "The {$label} field format is invalid.",
            default            => "The {$label} field is invalid.",
        };
    }
}
