# Validation Subsystem

Spinx provides an integrated, pipe-delimited data validation engine via `Spinx\Validation\Validator`.

## Usage

```php
use Spinx\Validation\Validator;
use Spinx\Validation\ValidationException;

try {
    $validated = Validator::make($request->request->all(), [
        'name'     => 'required|string|max:100',
        'email'    => 'required|email',
        'password' => 'required|min:8|confirmed',
        'tier'     => 'required|in:starter,pro,enterprise',
        'bio'      => 'nullable|string|max:500',
    ])->validate();
} catch (ValidationException $e) {
    return new JsonResponse([
        'message' => $e->getMessage(),
        'errors'  => $e->errors(),
    ], 422);
}
```

## Available Rules

| Rule | Description |
|---|---|
| `required` | Field must exist in the input array and cannot be an empty string or null. |
| `nullable` | If the field is absent or empty, all subsequent validation rules on this field are skipped. |
| `string` | Value must be a string. |
| `integer` | Value must be an integer or integer string (`ctype_digit` / `is_int`). |
| `numeric` | Value must be numeric (integer or float). |
| `array` | Value must be a PHP array. |
| `email` | Value must be a valid email address (`FILTER_VALIDATE_EMAIL`). |
| `min:n` | For strings: length >= `n` using UTF-8 `mb_strlen()`. For numbers: value >= `n`. |
| `max:n` | For strings: length <= `n` using UTF-8 `mb_strlen()`. For numbers: value <= `n`. |
| `in:a,b,c` | Value must strictly match one of the comma-separated options. |
| `confirmed` | Value must match the `{field}_confirmation` input field in the payload. |

## Allowlist Output

Calling `validate()` returns strictly the key-value pairs that were declared in the rules array. Any extraneous fields submitted by the client are omitted, preventing mass-assignment vulnerabilities.

## Custom Error Messages

Pass an optional third array to `Validator::make()`:

```php
$validator = Validator::make($data, $rules, [
    'email.required' => 'An email address is required.',
    'email.email'    => 'Please provide a valid corporate email address.',
]);
```
