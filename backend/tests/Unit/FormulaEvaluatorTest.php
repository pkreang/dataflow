<?php

namespace Tests\Unit;

use App\Support\FormulaEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for App\Support\FormulaEvaluator — pure expression evaluator used
 * by the formula field type. Supports `+ - * / ( )`, decimals, unary minus,
 * and field-key references resolved from a values map.
 *
 * Rules:
 *  - Unknown / missing / empty field reference → treated as 0
 *  - Division by zero → returns null (caller stores null, UI shows blank)
 *  - Syntax errors → throws InvalidArgumentException
 */
class FormulaEvaluatorTest extends TestCase
{
    private function eval(string $expr, array $values = []): float|null
    {
        return (new FormulaEvaluator())->evaluate($expr, $values);
    }

    // ---- arithmetic ----

    public function test_simple_addition(): void
    {
        $this->assertSame(3.0, $this->eval('1 + 2'));
    }

    public function test_simple_subtraction(): void
    {
        $this->assertSame(5.0, $this->eval('10 - 5'));
    }

    public function test_multiplication(): void
    {
        $this->assertSame(12.0, $this->eval('3 * 4'));
    }

    public function test_division(): void
    {
        $this->assertSame(3.0, $this->eval('6 / 2'));
    }

    public function test_operator_precedence(): void
    {
        // * binds tighter than +
        $this->assertSame(7.0, $this->eval('1 + 2 * 3'));
    }

    public function test_parentheses_override_precedence(): void
    {
        $this->assertSame(9.0, $this->eval('(1 + 2) * 3'));
    }

    public function test_nested_parentheses(): void
    {
        $this->assertSame(3.0, $this->eval('((1 + 2) * 3) / 3'));
    }

    public function test_decimal_arithmetic(): void
    {
        $this->assertSame(4.0, $this->eval('1.5 + 2.5'));
    }

    public function test_unary_minus_literal(): void
    {
        $this->assertSame(-3.0, $this->eval('-3'));
    }

    public function test_unary_minus_in_expression(): void
    {
        $this->assertSame(2.0, $this->eval('5 + -3'));
    }

    public function test_whitespace_is_ignored(): void
    {
        $this->assertSame(3.0, $this->eval('   1   +   2   '));
    }

    // ---- field references ----

    public function test_field_reference(): void
    {
        $this->assertSame(7.0, $this->eval('a + b', ['a' => 3, 'b' => 4]));
    }

    public function test_missing_field_is_zero(): void
    {
        // b not in values map — treated as 0
        $this->assertSame(3.0, $this->eval('a + b', ['a' => 3]));
    }

    public function test_field_with_string_numeric_value_is_cast(): void
    {
        // Form payload values are JSON strings — caster must accept them
        $this->assertSame(6.0, $this->eval('a + 1', ['a' => '5']));
    }

    public function test_field_with_empty_string_is_zero(): void
    {
        $this->assertSame(1.0, $this->eval('a + 1', ['a' => '']));
    }

    public function test_field_reference_precedence(): void
    {
        $this->assertSame(10.0, $this->eval('a * b + c', ['a' => 2, 'b' => 3, 'c' => 4]));
    }

    public function test_underscore_in_field_name(): void
    {
        $this->assertSame(15.0, $this->eval(
            'score_a + score_b + score_c',
            ['score_a' => 4, 'score_b' => 5, 'score_c' => 6]
        ));
    }

    // ---- division by zero ----

    public function test_division_by_zero_returns_null(): void
    {
        $this->assertNull($this->eval('1 / 0'));
    }

    public function test_division_by_computed_zero_returns_null(): void
    {
        $this->assertNull($this->eval('1 / (2 - 2)'));
    }

    // ---- syntax errors ----

    public function test_unmatched_open_paren_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval('(1 + 2');
    }

    public function test_consecutive_operators_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval('1 + + 2');
    }

    public function test_trailing_operator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval('1 +');
    }

    public function test_empty_expression_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval('');
    }

    public function test_unknown_function_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->eval('sum(a, b)', ['a' => 1, 'b' => 2]);
    }

    // ---- DAYS() built-in ----

    public function test_days_same_day_returns_one(): void
    {
        $this->assertSame(1.0, $this->eval('DAYS(date_from, date_to)', [
            'date_from' => '2026-06-10',
            'date_to'   => '2026-06-10',
        ]));
    }

    public function test_days_inclusive_count(): void
    {
        $this->assertSame(3.0, $this->eval('DAYS(date_from, date_to)', [
            'date_from' => '2026-06-10',
            'date_to'   => '2026-06-12',
        ]));
    }

    public function test_days_missing_value_returns_zero(): void
    {
        $this->assertSame(0.0, $this->eval('DAYS(date_from, date_to)', [
            'date_from' => '2026-06-10',
        ]));
    }

    public function test_days_invalid_date_returns_zero(): void
    {
        $this->assertSame(0.0, $this->eval('DAYS(date_from, date_to)', [
            'date_from' => 'not-a-date',
            'date_to'   => '2026-06-12',
        ]));
    }

    public function test_days_usable_in_arithmetic(): void
    {
        // DAYS(d1, d2) + 0 = 3
        $this->assertSame(5.0, $this->eval('DAYS(date_from, date_to) + 2', [
            'date_from' => '2026-06-10',
            'date_to'   => '2026-06-12',
        ]));
    }
}
