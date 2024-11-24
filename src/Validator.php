<?php

namespace App;

class Validator
{
    function __construct
    (
        protected array $rules,
        protected array $errors = [],
        protected bool  $valid = false
    )
    {
        //
    }


    public function validate(array $data): void
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);
            foreach ($rulesArray as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;

                if (!$this->$ruleName($data[$field] ?? null, $ruleValue)) {
                    $this->errors[$field][] = $this->getErrorMessage($field, $ruleName, $ruleValue);
                }
            }
        }

        $this->valid = empty($this->errors);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function __toString(): string
    {
        $string = PHP_EOL;
        foreach ($this->errors as $field => $errors) {
            foreach ($errors as $error) {
                $string .= "$field: $error" . PHP_EOL;
            }
        }
        return $string;
    }

    protected function required($value): bool
    {
        return !is_null($value) && $value !== '';
    }

    protected function url($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    protected function string($value): bool
    {
        return is_string($value);
    }

    protected function regex($value, $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    protected function getErrorMessage($field, $rule, $value): string
    {
        $messages = [
            'required' => "The $field field is required.",
            'url' => "The $field field must be a valid URL.",
            'string' => "The $field field must be a string.",
            'regex' => "The $field field must match the pattern $value.",
        ];

        return $messages[$rule] ?? "The $field field has an invalid value.";
    }
}
