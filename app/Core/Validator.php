<?php
declare(strict_types=1);

/**
 * Server-side validation. Client-side is never sufficient.
 */
final class Validator
{
    private array $data;
    private array $errors = [];
    private array $labels;

    public function __construct(array $data, array $labels = [])
    {
        $this->data = $data;
        $this->labels = $labels;
    }

    public function required(string $field): self
    {
        $v = trim((string)($this->data[$field] ?? ''));
        if ($v === '') {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.required');
        }
        return $this;
    }

    public function number(string $field, bool $allowZero = false): self
    {
        $v = trim((string)($this->data[$field] ?? ''));
        if ($v === '') return $this;
        if (!is_numeric($v)) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.number');
            return $this;
        }
        if (!$allowZero && bccomp((string)$v, '0', 10) <= 0) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.positive');
        }
        return $this;
    }

    public function positive(string $field): self
    {
        return $this->number($field, false);
    }

    public function integer(string $field): self
    {
        $v = trim((string)($this->data[$field] ?? ''));
        if ($v === '') return $this;
        if (!preg_match('/^\d+$/', $v)) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.integer');
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = trim((string)($this->data[$field] ?? ''));
        if ($v === '') return $this;
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.email');
        }
        return $this;
    }

    public function maxLen(string $field, int $max): self
    {
        $v = (string)($this->data[$field] ?? '');
        if (mb_strlen($v) > $max) {
            $this->errors[$field][] = $this->label($field) . ' ' . str_replace(':max', (string)$max, t('validate.maxlen'));
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $v = (string)($this->data[$field] ?? '');
        if ($v === '') return $this;
        if (!in_array($v, $allowed, true)) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.invalid');
        }
        return $this;
    }

    public function date(string $field): self
    {
        $v = (string)($this->data[$field] ?? '');
        if ($v === '') return $this;
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.date');
        }
        return $this;
    }

    public function datetime(string $field): self
    {
        $v = (string)($this->data[$field] ?? '');
        if ($v === '') return $this;
        if (!strtotime($v)) {
            $this->errors[$field][] = $this->label($field) . ' ' . t('validate.date');
        }
        return $this;
    }

    public function passes(): bool
    {
        return count($this->errors) === 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): string
    {
        foreach ($this->errors as $list) {
            if ($list) return $list[0];
        }
        return '';
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /** Value accessor. */
    public function get(string $field, $default = null)
    {
        return $this->data[$field] ?? $default;
    }
}
