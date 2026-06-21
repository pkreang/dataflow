<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\DocumentFormSubmissionController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Parity tests for DocumentFormSubmissionController::evaluateRulesPhp.
 *
 * The matching JS implementation lives in resources/js/app.js
 * (window.evaluateVisibilityRules). The server is authoritative, so when the
 * two diverge the JS must change — see the project plan for context.
 *
 * One known divergence is patched in JS: `is_empty` for the literal string
 * '0' must return false (the value IS present), aligning with PHP behavior.
 */
class EvaluateRulesPhpTest extends TestCase
{
    private function evaluate(array $rules, array $payload): bool
    {
        $method = new ReflectionMethod(DocumentFormSubmissionController::class, 'evaluateRulesPhp');

        // PHP 8.1+ allows invoking private static methods directly via reflection
        return (bool) $method->invoke(null, $rules, $payload);
    }

    public function test_equals_string_match(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'equals', 'value' => 'x']],
            ['a' => 'x']
        ));
    }

    public function test_equals_string_mismatch(): void
    {
        $this->assertFalse($this->evaluate(
            [['field' => 'a', 'operator' => 'equals', 'value' => 'x']],
            ['a' => 'y']
        ));
    }

    public function test_equals_against_array_value_finds_needle(): void
    {
        // multi_select / checkbox stores arrays; equals must check membership.
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'equals', 'value' => 'x']],
            ['a' => ['x', 'y']]
        ));
    }

    public function test_not_equals_against_array_value_when_needle_present(): void
    {
        $this->assertFalse($this->evaluate(
            [['field' => 'a', 'operator' => 'not_equals', 'value' => 'x']],
            ['a' => ['x', 'y']]
        ));
    }

    public function test_is_empty_for_missing_field(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'is_empty']],
            []
        ));
    }

    public function test_is_empty_for_empty_array(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'is_empty']],
            ['a' => []]
        ));
    }

    public function test_is_empty_for_blank_string(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'is_empty']],
            ['a' => '']
        ));
    }

    public function test_is_not_empty_for_zero_string_treats_as_filled(): void
    {
        // '0' is a real value (e.g. "Number of accidents = 0"); it must not
        // be treated as empty. This is the JS divergence we patched —
        // see resources/js/app.js evaluateVisibilityRules.
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'is_not_empty']],
            ['a' => '0']
        ));
    }

    public function test_greater_than_numeric(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'greater_than', 'value' => 5]],
            ['a' => 10]
        ));
    }

    public function test_greater_than_non_numeric_fails(): void
    {
        $this->assertFalse($this->evaluate(
            [['field' => 'a', 'operator' => 'greater_than', 'value' => 5]],
            ['a' => 'abc']
        ));
    }

    public function test_less_than_numeric(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'less_than', 'value' => 100]],
            ['a' => 42]
        ));
    }

    public function test_in_string_array(): void
    {
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'in', 'value' => ['x', 'y']]],
            ['a' => 'x']
        ));
    }

    public function test_in_with_numeric_string_coerce(): void
    {
        // Rule value is integer, payload value is its string form — must match.
        $this->assertTrue($this->evaluate(
            [['field' => 'a', 'operator' => 'in', 'value' => [1, 2]]],
            ['a' => '1']
        ));
    }

    public function test_not_in_with_match_is_false(): void
    {
        $this->assertFalse($this->evaluate(
            [['field' => 'a', 'operator' => 'not_in', 'value' => ['x']]],
            ['a' => 'x']
        ));
    }

    public function test_multi_rule_an_d_passes_when_all_match(): void
    {
        $rules = [
            ['field' => 'a', 'operator' => 'equals', 'value' => 'x'],
            ['field' => 'b', 'operator' => 'greater_than', 'value' => 5],
        ];
        $this->assertTrue($this->evaluate($rules, ['a' => 'x', 'b' => 10]));
    }

    public function test_multi_rule_an_d_fails_when_one_fails(): void
    {
        $rules = [
            ['field' => 'a', 'operator' => 'equals', 'value' => 'x'],
            ['field' => 'b', 'operator' => 'greater_than', 'value' => 5],
        ];
        $this->assertFalse($this->evaluate($rules, ['a' => 'x', 'b' => 1]));
    }

    public function test_unknown_operator_fails_safe(): void
    {
        // Defensive: a typo in the rule shouldn't accidentally match.
        $this->assertFalse($this->evaluate(
            [['field' => 'a', 'operator' => 'foobar', 'value' => 'x']],
            ['a' => 'x']
        ));
    }
}
