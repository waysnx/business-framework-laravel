# Validation Implementation Plan – Laravel Runtime

| | |
|---|---|
| Status | Plan – pending ADR-0007 |
| Date | 2026-10-31 |
| Architecture | primary repo `docs/architecture/VALIDATION_ARCHITECTURE.md`, `decisions/ADR-0007-Validation-Responsibility-Split.md` |

## 1. Starting point (verified)

- `Validation\ValidationFramework::execute($validationId, ValidationContext)` loops over `ValidationRuleDefinition`s and calls `evaluateRule()`. That method **returns `true` by default** (L247-255, finding LAR-01).
  - Reproduced: a `required` rule on empty input passes.
- Severities are `error`, `warning` and `info`. `ValidationResult::$passed` is true when there are no error issues. `stopOnError` breaks only on error-severity rules.
- A rule that throws is wrapped in `ValidationRuleException`. Every other failure is wrapped in `ValidationExecutionException`. Both extend `LifecycleException`.
- Laravel Validator is not used anywhere in `src/`.
- The BF pipeline (`BusinessFunctionAbstract`) does not call `ValidationFramework`. `validateRequest()` is an empty hook.
- `tests/Fixtures/BFD/ApplyLeave.canonical.json` → `processing.validation` uses Laravel rule strings.

## 2. Target

Canonical rules (VALIDATION_ARCHITECTURE §5) are compiled to Laravel Validator rules. Results are normalized to canonical issues. Unknown rules fail closed. Contract validation (L1) is enforced in the BF pipeline.

## 3. Work items

| # | Item | Detail | Breaking? |
|---|---|---|---|
| V1 | `Validation\Canonical\RuleCompiler` | Translates canonical rule → Laravel rule. `required`→`required`. `type:string`→`string`. `type:integer`→`integer`. `type:date`→`date_format:Y-m-d`. `minLength`/`maxLength`→`min`/`max` together with `string`/`array`. `min`/`max` numeric→`numeric\|min\|max`, or `after_or_equal`/`before_or_equal` for dates. `pattern`→`regex`. `format:email\|uuid\|uri`→`email\|uuid\|url`. `enum`→`Rule::in`. `compare`→`same\|different\|gt\|gte\|lt\|lte`. Non-`required` rules get `nullable` semantics (VALIDATION_ARCHITECTURE §4.8) | No |
| V2 | `Validation\Engine\IlluminateValidationEngine` | Runs `Validator::make()` on the input. Maps `MessageBag` failures to `ValidationIssue` with `code` = canonical rule, `path` = attribute key, and severity from rule → definition → `error` | No |
| V3 | `ValidationFramework::evaluateRule()` default | Delegates to the engine for canonical rule types. Unknown `ruleType` → error issue `WBF.VALIDATION.UNKNOWN_RULE` when `config('business-framework.validation.strict')` is true. In non-strict mode (default for one minor release) it is a **warning** issue, not a silent pass | Yes (behavior). Mitigated by the flag |
| V4 | `Validation\CustomRuleRegistry` | `ruleType: custom` + `name` → resolved from the container (tag `wbf.validation.rule`). Unregistered name fails closed | No (new) |
| V5 | L1 contract validation in BF pipeline | The default `validateRequest()` evaluates `processing.validation` canonical rules when present. It throws `ValidationError` (new, extends `\InvalidArgumentException` for BC) carrying all issues | Additive. Subclasses that override `validateRequest()` are unaffected |
| V6 | Warnings in BF outcome | `ExecutionResult::$issues` (see WBF_COMPATIBILITY_AUDIT LAR-07) | No |
| V7 | Rule evaluator exception | Reported as `InternalSystemError`, not as a validation issue. `ValidationRuleException` is kept as the PHP type | No |
| V8 | Canonical BFD fixture | Add a `processing.validationRules` canonical object form alongside the existing Laravel strings. Test that both forms produce equal outcomes | No |
| V9 | Dependency | `illuminate/validation` → `suggest` + `require-dev`. The engine is bound only when `class_exists(\Illuminate\Validation\Factory::class)`. Otherwise strict mode is required and unknown rules fail | Package metadata: **needs approval** |
| V10 | Exception tree | Introduce root `WbfException`. `ValidationFrameworkException` stays a subclass of `LifecycleException` for BC | No |

## 4. Tests

- **Unit:** compiler mapping table, one test per canonical rule and boundary (length 5/6, range 0/1/10/11).
- **Unit:** fail-closed for unknown types in strict and non-strict mode.
- **Integration:** `ApplyLeave` with the canonical rules, using an injected clock instead of `after_or_equal:today`. That rule is reclassified as an L3 business rule.
- **Conformance:** every `VAL-*` scenario in CROSS_RUNTIME_CONFORMANCE §7, plus `BF-VAL-001/002/003`.

## 5. Decisions required

1. Default for `validation.strict` in the first release: `false` (warn) or `true` (fail).
2. `illuminate/validation` as `suggest` vs `require`.
3. Whether relative-date rules (`after_or_equal:today`) stay available as a Laravel-specific extension or move entirely to business rules.
