<?php
/**
 * DEVMMND COMPANY - Tourism API (v1)
 * -------------------------------------------------------------
 * Validator.php
 * Lightweight, dependency-free validation helpers used by the
 * controllers. It collects errors as it goes so a single response
 * can report every problem at once.
 */

class Validator
{
    /**
     * Collected validation errors, keyed by field name.
     * @var array<string,string>
     */
    private array $errors = [];

    /**
     * The data set being validated.
     * @var array<string,mixed>
     */
    private array $data;

    /**
     * @param array<string,mixed> $data Decoded request body.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Field must be present and not an empty/whitespace-only string.
     */
    public function required(string $field, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = "The {$label} field is required.";
        }

        return $this;
    }

    /**
     * Field, when present and not empty, must be a valid email address.
     */
    public function email(string $field, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== ''
            && !filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "The {$label} must be a valid email address.";
        }

        return $this;
    }

    /**
     * Field, when present, must be at least the given length.
     */
    public function minLength(string $field, int $min, string $label = null): self
    {
        $label = $label ?? $field;

        if (isset($this->data[$field]) && is_string($this->data[$field])
            && mb_strlen(trim($this->data[$field])) < $min) {
            $this->errors[$field] = "The {$label} must be at least {$min} characters.";
        }

        return $this;
    }

    /**
     * Field, when present, must not exceed the given length.
     */
    public function maxLength(string $field, int $max, string $label = null): self
    {
        $label = $label ?? $field;

        if (isset($this->data[$field]) && is_string($this->data[$field])
            && mb_strlen(trim($this->data[$field])) > $max) {
            $this->errors[$field] = "The {$label} must not exceed {$max} characters.";
        }

        return $this;
    }

    /**
     * Field, when present and not empty, must be a positive integer.
     */
    public function positiveInteger(string $field, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_INT) || (int)$value <= 0) {
                $this->errors[$field] = "The {$label} must be a positive integer.";
            }
        }

        return $this;
    }

    /**
     * Field, when present and not empty, must be an integer within range.
     */
    public function intBetween(string $field, int $min, int $max, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '') {
            $int = filter_var($value, FILTER_VALIDATE_INT);
            if ($int === false || $int < $min || $int > $max) {
                $this->errors[$field] = "The {$label} must be an integer between {$min} and {$max}.";
            }
        }

        return $this;
    }

    /**
     * Field, when present and not empty, must be a number within range.
     * Accepts integers and decimals (e.g. latitude/longitude).
     */
    public function numericBetween(string $field, float $min, float $max, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '') {
            if (!is_numeric($value)) {
                $this->errors[$field] = "The {$label} must be a valid number.";
            } elseif ((float) $value < $min || (float) $value > $max) {
                $this->errors[$field] = "The {$label} must be between {$min} and {$max}.";
            }
        }

        return $this;
    }

    /**
     * Field, when present and not empty, must be one of the allowed values.
     *
     * @param array<int,string> $allowed
     */
    public function inList(string $field, array $allowed, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "The {$label} must be one of: " . implode(', ', $allowed) . '.';
        }

        return $this;
    }

    /**
     * Field, when present and not empty, must be a valid time
     * in HH:MM or HH:MM:SS (24-hour) format.
     */
    public function time(string $field, string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? null;

        if ($value !== null && $value !== ''
            && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)) {
            $this->errors[$field] = "The {$label} must be a valid time (HH:MM or HH:MM:SS).";
        }

        return $this;
    }

    /** Returns true when no validation errors were recorded. */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /** Returns true when at least one validation error exists. */
    public function fails(): bool
    {
        return !$this->passes();
    }

    /**
     * @return array<string,string> All collected errors.
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
