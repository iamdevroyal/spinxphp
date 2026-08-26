# Validation Subsystem

Spinx provides an explicit, zero-dependency validation engine with **over 40 production-ready validation rules**, the `Validate` facade, and the `Request::validate()` shorthand.

---

## 1. Quick Usage

### Via `Request::validate()` (Recommended in Controllers)
```php
use Spinx\Http\Request;

$validated = Request::validate([
    'name'     => 'required|string|min:2|max:100',
    'email'    => 'required|email|max:255',
    'password' => 'required|string|min:8|confirmed',
    'age'      => 'nullable|integer|between:18,120',
    'role'     => 'required|in:admin,editor,viewer',
]);
```

### Via `Validate` Facade
```php
use Spinx\Validation\Validate;

// Check and throw ValidationException on failure
$data = Validate::check($input, [
    'title' => 'required|string|max:255',
    'price' => 'required|numeric|gt:0',
]);

// Boolean check
if (Validate::passes($input, $rules)) {
    // ...
}

// Retrieve error array
$errors = Validate::errors($input, $rules);
```

### Via `Validator::make()` (Conditional / Safe)
```php
use Spinx\Validation\Validator;

$validator = Validator::make($data, $rules);

if ($validator->fails()) {
    $errors = $validator->errors();
    $firstError = $validator->firstError('email');
} else {
    $validated = $validator->safe();
}
```

---

## 2. Complete Rules Reference

| Category | Rule | Description |
|---|---|---|
| **Presence** | `required` | Field must be present and not empty string/null |
| | `nullable` | If field is absent or empty, remaining rules are skipped |
| | `accepted` | Must be `yes`, `on`, `1`, or `true` |
| | `declined` | Must be `no`, `off`, `0`, or `false` |
| | `prohibited` | Field must NOT be present or filled |
| **Types** | `string` | Must be a string |
| | `integer`, `int` | Must be a valid integer |
| | `numeric` | Must be numeric (int or float) |
| | `float`, `decimal` | Must be a decimal / floating-point number |
| | `boolean`, `bool` | Must be boolean representation |
| | `array` | Must be an array |
| | `json` | Must be valid JSON string |
| **Formats** | `email` | Must be a valid email address |
| | `url` | Must be a valid URL |
| | `ip` | Must be a valid IP address |
| | `ipv4` | Must be a valid IPv4 address |
| | `ipv6` | Must be a valid IPv6 address |
| | `uuid` | Must be a valid UUID (v4/standard) |
| | `phone` | Must be a valid phone number (7-20 chars) |
| | `alpha` | Letters only |
| | `alpha_num` | Letters and numbers only |
| | `alpha_dash` | Letters, numbers, dashes, and underscores |
| | `alpha_spaces` | Letters and spaces only |
| | `lowercase` | Must be lowercase string |
| | `uppercase` | Must be uppercase string |
| **Size & Range** | `min:N` | Minimum length (string), count (array), or value (number) |
| | `max:N` | Maximum length, count, or value |
| | `size:N` | Exact length, count, or value |
| | `between:min,max` | Must be between min and max |
| | `gt:N`, `gte:N` | Strictly greater than / greater than or equal to N |
| | `lt:N`, `lte:N` | Strictly less than / less than or equal to N |
| | `digits:N` | Must be numeric and exactly N digits |
| | `digits_between:min,max` | Must be numeric and between min and max digits |
| | `min_words:N`, `max_words:N` | Minimum / maximum word count in string |
| **Dates** | `date` | Must be a parseable date string |
| | `date_format:format` | Must match PHP date format (e.g. `Y-m-d`) |
| | `before:date` | Must be a date before specified date |
| | `after:date` | Must be a date after specified date |
| **Content** | `in:a,b,c` | Must be one of the listed values |
| | `not_in:a,b,c` | Must not be one of the listed values |
| | `confirmed` | Must match `{field}_confirmation` input |
| | `same:other` | Must match value of other field |
| | `different:other` | Must differ from value of other field |
| | `starts_with:prefix` | String must start with prefix |
| | `ends_with:suffix` | String must end with suffix |
| | `contains:str` | String must contain substring |
| | `regex:pattern` | Must match regex (e.g. `/^[A-Z0-9]+$/`) |
| | `not_regex:pattern` | Must NOT match regex |

---

## 3. Handling Validation Exceptions

When `Request::validate()` or `Validate::check()` fails, it throws `Spinx\Validation\ValidationException`:

```php
use Spinx\Validation\ValidationException;

try {
    $data = Request::validate(['email' => 'required|email']);
} catch (ValidationException $e) {
    // Array of errors: ['email' => ['The email field must be a valid email address.']]
    $errors = $e->errors();

    return view('Auth::register', ['errors' => $errors], 422);
}
```
