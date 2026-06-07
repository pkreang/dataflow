// Live evaluator for the `formula` field type.
//
// Mirrors App\Support\FormulaEvaluator on the server side — the two MUST stay
// in lockstep (PHP recomputes on save; this JS just provides instant feedback).
// If you change one side, change the other and re-run FormulaEvaluatorTest.
//
// Exposes `window.evaluateFormula(expression, values)` returning a number or
// `null` (division by zero, syntax error). Syntax errors are swallowed here
// because the user may be mid-typing — the server is authoritative; admins
// surface syntax errors at form-save time, not while filling.

class FormulaDivisionByZeroError extends Error {}

class FormulaEvaluator {
    constructor() {
        this.expr = '';
        this.pos = 0;
        this.len = 0;
        this.values = {};
    }

    evaluate(expression, values) {
        this.expr = String(expression == null ? '' : expression);
        this.pos = 0;
        this.len = this.expr.length;
        this.values = values || {};

        this.skipWhitespace();
        if (this.pos >= this.len) {
            throw new Error('Empty expression');
        }

        let result;
        try {
            result = this.parseExpression();
        } catch (e) {
            if (e instanceof FormulaDivisionByZeroError) return null;
            throw e;
        }

        this.skipWhitespace();
        if (this.pos < this.len) {
            throw new Error(
                `Unexpected token at position ${this.pos}: "${this.expr.substr(this.pos, 10)}"`
            );
        }

        return result;
    }

    parseExpression() {
        let left = this.parseTerm();
        for (;;) {
            this.skipWhitespace();
            const op = this.peek();
            if (op !== '+' && op !== '-') break;
            this.pos++;
            const right = this.parseTerm();
            left = op === '+' ? left + right : left - right;
        }
        return left;
    }

    parseTerm() {
        let left = this.parseFactor();
        for (;;) {
            this.skipWhitespace();
            const op = this.peek();
            if (op !== '*' && op !== '/') break;
            this.pos++;
            const right = this.parseFactor();
            if (op === '/') {
                if (right === 0) throw new FormulaDivisionByZeroError();
                left = left / right;
            } else {
                left = left * right;
            }
        }
        return left;
    }

    parseFactor() {
        this.skipWhitespace();
        const c = this.peek();
        if (c === null) throw new Error('Unexpected end of expression');

        if (c === '-') {
            this.pos++;
            return -this.parseFactor();
        }
        if (c === '(') {
            this.pos++;
            const value = this.parseExpression();
            this.skipWhitespace();
            if (this.peek() !== ')') throw new Error('Unmatched opening parenthesis');
            this.pos++;
            return value;
        }
        if (FormulaEvaluator.isDigit(c) || c === '.') return this.parseNumber();
        if (FormulaEvaluator.isAlpha(c) || c === '_') return this.parseIdentifier();

        throw new Error(`Unexpected character "${c}" at position ${this.pos}`);
    }

    parseNumber() {
        const start = this.pos;
        while (this.pos < this.len) {
            const c = this.expr[this.pos];
            if (FormulaEvaluator.isDigit(c) || c === '.') this.pos++;
            else break;
        }
        const raw = this.expr.substring(start, this.pos);
        if (raw === '.' || (raw.match(/\./g) || []).length > 1) {
            throw new Error(`Invalid number literal: ${raw}`);
        }
        return parseFloat(raw);
    }

    parseIdentifier() {
        const start = this.pos;
        while (this.pos < this.len) {
            const c = this.expr[this.pos];
            if (FormulaEvaluator.isAlnum(c) || c === '_') this.pos++;
            else break;
        }
        const name = this.expr.substring(start, this.pos);

        this.skipWhitespace();
        if (this.peek() === '(') {
            this.pos++; // consume '('
            return this.callBuiltin(name);
        }

        return FormulaEvaluator.coerceNumeric(this.values[name]);
    }

    callBuiltin(name) {
        const argKeys = [];
        this.skipWhitespace();
        while (this.peek() !== ')' && this.peek() !== null) {
            const argStart = this.pos;
            while (this.pos < this.len) {
                const c = this.expr[this.pos];
                if (FormulaEvaluator.isAlnum(c) || c === '_') this.pos++;
                else break;
            }
            if (this.pos > argStart) {
                argKeys.push(this.expr.substring(argStart, this.pos));
            }
            this.skipWhitespace();
            if (this.peek() === ',') {
                this.pos++;
                this.skipWhitespace();
            } else {
                break;
            }
        }
        this.skipWhitespace();
        if (this.peek() !== ')') throw new Error(`Expected ) to close function call: ${name}`);
        this.pos++;

        const upper = name.toUpperCase();
        if (upper === 'DAYS') return this.fnDays(argKeys);
        throw new Error(`Unknown function: ${name}`);
    }

    fnDays(argKeys) {
        if (argKeys.length < 2) return 0;
        const a = String(this.values[argKeys[0]] ?? '').trim();
        const b = String(this.values[argKeys[1]] ?? '').trim();
        if (!a || !b) return 0;
        const d1 = new Date(a), d2 = new Date(b);
        if (isNaN(d1) || isNaN(d2)) return 0;
        return Math.round((d2 - d1) / 86400000) + 1; // inclusive
    }

    static coerceNumeric(raw) {
        if (typeof raw === 'number') return Number.isFinite(raw) ? raw : 0;
        if (typeof raw === 'string') {
            const trimmed = raw.trim();
            if (trimmed === '' || isNaN(Number(trimmed))) return 0;
            return Number(trimmed);
        }
        return 0;
    }

    peek() {
        return this.pos < this.len ? this.expr[this.pos] : null;
    }

    skipWhitespace() {
        while (this.pos < this.len && /\s/.test(this.expr[this.pos])) this.pos++;
    }

    static isDigit(c) { return c >= '0' && c <= '9'; }
    static isAlpha(c) { return (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z'); }
    static isAlnum(c) { return FormulaEvaluator.isDigit(c) || FormulaEvaluator.isAlpha(c); }
}

window.evaluateFormula = function (expression, values) {
    try {
        return new FormulaEvaluator().evaluate(expression, values || {});
    } catch (e) {
        // Mid-typing produces syntax-error states constantly — silence them in
        // the UI. The server is the source of truth and surfaces real errors
        // at form-save time.
        return null;
    }
};
