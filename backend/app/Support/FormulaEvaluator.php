<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Safe arithmetic expression evaluator for the `formula` field type.
 *
 * Grammar (recursive-descent):
 *   expression → term      (('+' | '-') term)*
 *   term       → factor    (('*' | '/') factor)*
 *   factor     → '-' factor | '(' expression ')' | number | identifier
 *
 * Semantics:
 *  - Identifiers (`[a-zA-Z_][a-zA-Z0-9_]*`) refer to other field keys; their
 *    value is read from the `$values` map. Missing / empty / non-numeric
 *    values resolve to 0.
 *  - Division by zero short-circuits the whole expression to `null`.
 *  - Function-call syntax (`min(...)`, `sum(...)`) is reserved for a future
 *    extension and currently rejected as a syntax error.
 *
 * Mirrored on the client by `resources/js/formula-evaluator.js`. The two must
 * produce identical results — see FormulaEvaluatorParityTest.
 */
class FormulaEvaluator
{
    private string $expr;
    private int $pos;
    private int $len;
    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param  array<string, mixed>  $values  Map of field_key → value (numeric or numeric-string).
     */
    public function evaluate(string $expression, array $values = []): ?float
    {
        $this->expr = $expression;
        $this->pos = 0;
        $this->len = strlen($expression);
        $this->values = $values;

        $this->skipWhitespace();
        if ($this->pos >= $this->len) {
            throw new InvalidArgumentException('Empty expression');
        }

        try {
            $result = $this->parseExpression();
        } catch (FormulaDivisionByZero) {
            return null;
        }

        $this->skipWhitespace();
        if ($this->pos < $this->len) {
            throw new InvalidArgumentException(sprintf(
                'Unexpected token at position %d: "%s"',
                $this->pos,
                substr($this->expr, $this->pos, 10),
            ));
        }

        return (float) $result;
    }

    private function parseExpression(): float
    {
        $left = $this->parseTerm();

        while (true) {
            $this->skipWhitespace();
            $op = $this->peek();
            if ($op !== '+' && $op !== '-') {
                break;
            }
            $this->pos++;
            $right = $this->parseTerm();
            $left = $op === '+' ? $left + $right : $left - $right;
        }

        return $left;
    }

    private function parseTerm(): float
    {
        $left = $this->parseFactor();

        while (true) {
            $this->skipWhitespace();
            $op = $this->peek();
            if ($op !== '*' && $op !== '/') {
                break;
            }
            $this->pos++;
            $right = $this->parseFactor();
            if ($op === '/') {
                if ($right === 0.0) {
                    throw new FormulaDivisionByZero();
                }
                $left = $left / $right;
            } else {
                $left = $left * $right;
            }
        }

        return $left;
    }

    private function parseFactor(): float
    {
        $this->skipWhitespace();
        $c = $this->peek();

        if ($c === null) {
            throw new InvalidArgumentException('Unexpected end of expression');
        }

        if ($c === '-') {
            $this->pos++;
            return -$this->parseFactor();
        }

        if ($c === '(') {
            $this->pos++;
            $value = $this->parseExpression();
            $this->skipWhitespace();
            if ($this->peek() !== ')') {
                throw new InvalidArgumentException('Unmatched opening parenthesis');
            }
            $this->pos++;
            return $value;
        }

        if (ctype_digit($c) || $c === '.') {
            return $this->parseNumber();
        }

        if (ctype_alpha($c) || $c === '_') {
            return $this->parseIdentifier();
        }

        throw new InvalidArgumentException(sprintf(
            'Unexpected character "%s" at position %d',
            $c,
            $this->pos,
        ));
    }

    private function parseNumber(): float
    {
        $start = $this->pos;
        while ($this->pos < $this->len) {
            $c = $this->expr[$this->pos];
            if (ctype_digit($c) || $c === '.') {
                $this->pos++;
            } else {
                break;
            }
        }
        $raw = substr($this->expr, $start, $this->pos - $start);
        if ($raw === '.' || substr_count($raw, '.') > 1) {
            throw new InvalidArgumentException('Invalid number literal: '.$raw);
        }

        return (float) $raw;
    }

    private function parseIdentifier(): float
    {
        $start = $this->pos;
        while ($this->pos < $this->len) {
            $c = $this->expr[$this->pos];
            if (ctype_alnum($c) || $c === '_') {
                $this->pos++;
            } else {
                break;
            }
        }
        $name = substr($this->expr, $start, $this->pos - $start);

        $this->skipWhitespace();
        if ($this->peek() === '(') {
            $this->pos++; // consume '('
            return $this->callBuiltin($name);
        }

        return self::coerceNumeric($this->values[$name] ?? null);
    }

    private function callBuiltin(string $name): float
    {
        $argKeys = [];
        $this->skipWhitespace();
        while ($this->peek() !== ')' && $this->peek() !== null) {
            $argStart = $this->pos;
            while ($this->pos < $this->len) {
                $c = $this->expr[$this->pos];
                if (ctype_alnum($c) || $c === '_') {
                    $this->pos++;
                } else {
                    break;
                }
            }
            if ($this->pos > $argStart) {
                $argKeys[] = substr($this->expr, $argStart, $this->pos - $argStart);
            }
            $this->skipWhitespace();
            if ($this->peek() === ',') {
                $this->pos++;
                $this->skipWhitespace();
            } else {
                break;
            }
        }
        $this->skipWhitespace();
        if ($this->peek() !== ')') {
            throw new InvalidArgumentException('Expected ) to close function call: '.$name);
        }
        $this->pos++;

        return match(strtoupper($name)) {
            'DAYS' => $this->fnDays($argKeys),
            default => throw new InvalidArgumentException('Unknown function: '.$name),
        };
    }

    private function fnDays(array $argKeys): float
    {
        if (count($argKeys) < 2) {
            return 0.0;
        }
        $a = trim((string) ($this->values[$argKeys[0]] ?? ''));
        $b = trim((string) ($this->values[$argKeys[1]] ?? ''));
        if ($a === '' || $b === '') {
            return 0.0;
        }
        try {
            $d1 = new \DateTimeImmutable($a);
            $d2 = new \DateTimeImmutable($b);
            return (float) ($d1->diff($d2)->days + 1);
        } catch (\Exception) {
            return 0.0;
        }
    }

    /**
     * Coerce a payload value to a float. Anything non-numeric (including
     * empty / null / arrays) becomes 0 so missing data doesn't blow up the
     * whole calculation.
     */
    private static function coerceNumeric(mixed $raw): float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '' || ! is_numeric($trimmed)) {
                return 0.0;
            }
            return (float) $trimmed;
        }

        return 0.0;
    }

    private function peek(): ?string
    {
        return $this->pos < $this->len ? $this->expr[$this->pos] : null;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->len && ctype_space($this->expr[$this->pos])) {
            $this->pos++;
        }
    }
}
