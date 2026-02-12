# Memory: architecture/coding-standards/control-flow-and-formatting
Updated: 2026-02-12

Code style across all languages (PHP, TypeScript, Go) is governed by a canonical spec at `spec/01-coding-guidelines/code-style.md` with five rules: (1) mandatory curly braces `{}` for all control structures, (2) no nested `if` blocks — flatten via combined conditions or early returns, (3) extract any `if` condition with 2+ operators into a named boolean variable, method, or constant, (4) blank line before `return` unless it's the sole statement, (5) blank line after `}` when more code follows (exception: consecutive `}` or `else`/`catch`). The PHP spec at `spec/04-php-standards/README.md` references this canonical source and repeats rules with PHP-specific examples.
