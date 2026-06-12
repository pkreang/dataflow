<?php

namespace App\Support;

use App\Models\DocumentForm;

class FormulaFields
{
    /**
     * Recompute every `formula` field's value from the current payload, so the
     * persisted value is server-derived (not the client-supplied mirror, which
     * is editable via devtools / arbitrary API clients). Bad expressions
     * degrade to null rather than blocking the save — admins surface syntax
     * errors at form-save time.
     */
    public static function recompute(DocumentForm $form, array $payload): array
    {
        $form->loadMissing('fields');
        $evaluator = new FormulaEvaluator;

        foreach ($form->fields as $field) {
            if ($field->field_type !== 'formula') {
                continue;
            }
            $expression = $field->options['expression'] ?? null;
            if (! is_string($expression) || trim($expression) === '') {
                $payload[$field->field_key] = null;

                continue;
            }
            try {
                $payload[$field->field_key] = $evaluator->evaluate($expression, $payload);
            } catch (\InvalidArgumentException) {
                $payload[$field->field_key] = null;
            }
        }

        return $payload;
    }
}
