<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression: DocumentFormController references validation.document_form.*
 * translation keys. If a new key is added to the controller without a
 * matching lang entry, Laravel falls back to rendering the raw key text
 * (e.g. "validation.document_form.field_key_reserved") in the UI.
 *
 * This test scans the controller for every key referenced and asserts both
 * en + th lang files have a string value for it.
 */
class DocumentFormValidationMessagesTest extends TestCase
{
    public function test_all_referenced_validation_keys_have_en_and_th_translations(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Web/DocumentFormController.php'));

        // Matches both __('validation.document_form.X') and bare 'validation.document_form.X'
        // strings passed to helper methods like formatFieldError(...).
        preg_match_all(
            "/'validation\.document_form\.([a-z_]+)'/",
            $controller,
            $matches
        );

        $referencedKeys = array_unique($matches[1]);
        $this->assertNotEmpty($referencedKeys, 'Expected the controller to reference at least one key.');

        foreach (['en', 'th'] as $locale) {
            $messages = trans('validation.document_form', [], $locale);
            $this->assertIsArray($messages, "validation.document_form must exist in {$locale}");

            foreach ($referencedKeys as $key) {
                $this->assertArrayHasKey($key, $messages,
                    "Missing translation: validation.document_form.{$key} in {$locale}");
                $this->assertIsString($messages[$key]);
                $this->assertNotSame('', trim($messages[$key]),
                    "Translation for validation.document_form.{$key} in {$locale} must not be empty");
            }
        }
    }
}
