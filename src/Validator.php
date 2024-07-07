<?php

namespace App;

class Validator
{
    protected array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);
            foreach ($rulesArray as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;

                if (!$this->$ruleName($data[$field] ?? null, $ruleValue)) {
                    $this->errors[$field][] = $this->getErrorMessage($field, $ruleName);
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
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

    protected function getErrorMessage($field, $rule): string
    {
        $messages = [
            'required' => "The $field field is required.",
            'url' => "The $field field must be a valid URL.",
            'string' => "The $field field must be a string.",
        ];

        return $messages[$rule] ?? "The $field field has an invalid value.";
    }
}
