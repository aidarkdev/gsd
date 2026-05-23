<?php

declare(strict_types=1);

namespace App\Validation;

final class Validator
{
    public function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && trim((string) $value) === '') {
                    $errors[$field] = 'validation.required';
                    break;
                }

                if ($rule === 'email' && $value !== null && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'validation.email';
                    break;
                }

                if (str_starts_with($rule, 'min:') && strlen((string) $value) < (int) substr($rule, 4)) {
                    $errors[$field] = 'validation.too_short';
                    break;
                }
            }
        }

        return $errors;
    }
}
