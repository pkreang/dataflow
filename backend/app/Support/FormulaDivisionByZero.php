<?php

namespace App\Support;

use RuntimeException;

/**
 * Internal short-circuit signal raised by FormulaEvaluator when it encounters
 * a division by zero. Caught at the top of evaluate() and translated to a
 * `null` result so the UI shows an empty value rather than crashing.
 */
class FormulaDivisionByZero extends RuntimeException {}
