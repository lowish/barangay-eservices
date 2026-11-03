<?php
/**
 * Small server-side validator. Rules are checked in PHP regardless of any
 * HTML attributes on the form, so a tampered request is still rejected.
 *
 *   $v = new Validator($_POST);
 *   $v->required('email', 'Email')->email('email', 'Email');
 *   if ($v->fails()) { ... $v->errors() ... }
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function value(string $field): string
    {
        $v = $this->data[$field] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    private function add(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->add($field, "$label is required.");
        }
        return $this;
    }

    public function length(string $field, string $label, int $min, int $max): self
    {
        $len = mb_strlen($this->value($field));
        if ($len > 0 && ($len < $min || $len > $max)) {
            $this->add($field, "$label must be between $min and $max characters.");
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_EMAIL) || mb_strlen($v) > 120)) {
            $this->add($field, "$label must be a valid email address.");
        }
        return $this;
    }

    /** Letters, spaces, dots, hyphens and apostrophes (for names). */
    public function name(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match("/^[\\p{L} .'\\-]+$/u", $v)) {
            $this->add($field, "$label may only contain letters, spaces, periods, hyphens and apostrophes.");
        }
        return $this;
    }

    /** Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX. */
    public function phMobile(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^(09|\+639)\d{9}$/', $v)) {
            $this->add($field, "$label must be a mobile number like 09171234567.");
        }
        return $this;
    }

    public function intBetween(string $field, string $label, int $min, int $max): self
    {
        $v = $this->value($field);
        if ($v !== '' && (filter_var($v, FILTER_VALIDATE_INT) === false || (int) $v < $min || (int) $v > $max)) {
            $this->add($field, "$label must be a whole number from $min to $max.");
        }
        return $this;
    }

    public function decimalBetween(string $field, string $label, float $min, float $max): self
    {
        $v = $this->value($field);
        if ($v !== '' && (!preg_match('/^\d+(\.\d{1,2})?$/', $v) || (float) $v < $min || (float) $v > $max)) {
            $this->add($field, "$label must be an amount from " . number_format($min, 2) . ' to ' . number_format($max, 2) . '.');
        }
        return $this;
    }

    public function in(string $field, string $label, array $allowed): self
    {
        $v = $this->value($field);
        if ($v !== '' && !in_array($v, array_map('strval', $allowed), true)) {
            $this->add($field, "Choose a valid $label.");
        }
        return $this;
    }

    /** Value must exist as an id in the given table (e.g. a foreign key chosen in a dropdown). */
    public function exists(string $field, string $label, string $sql): self
    {
        $v = $this->value($field);
        if ($v !== '' && (filter_var($v, FILTER_VALIDATE_INT) === false || !db_value($sql, [(int) $v]))) {
            $this->add($field, "Choose a valid $label.");
        }
        return $this;
    }

    /** At least 8 characters with a letter and a number. */
    public function password(string $field, string $label): self
    {
        $v = $this->data[$field] ?? '';
        if (!is_string($v) || strlen($v) < 8 || strlen($v) > 72 || !preg_match('/[A-Za-z]/', $v) || !preg_match('/\d/', $v)) {
            $this->add($field, "$label must be 8 to 72 characters and include at least one letter and one number.");
        }
        return $this;
    }

    public function matches(string $field, string $other, string $message): self
    {
        if (($this->data[$field] ?? '') !== ($this->data[$other] ?? '')) {
            $this->add($field, $message);
        }
        return $this;
    }

    public function custom(bool $ok, string $field, string $message): self
    {
        if (!$ok) {
            $this->add($field, $message);
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

/** Show the errors from a failed validation on the next page, keep the input, and redirect back. */
function back_with_errors(array $errors, string $path): never
{
    $_SESSION['errors'] = $errors;
    remember_input($_POST);
    flash('danger', 'Please correct the highlighted fields and try again.');
    redirect($path);
}

function take_errors(): array
{
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors']);
    return $errors;
}

/** Bootstrap invalid-state helpers for a form field. */
function invalid_class(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback" id="' . e($field) . '-error">' . e($errors[$field]) . '</div>' : '';
}

function aria_invalid(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . e($field) . '-error"' : '';
}
