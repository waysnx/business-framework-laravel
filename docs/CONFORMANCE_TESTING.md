# Conformance Testing – Laravel Runtime

| | |
|---|---|
| Status | **Implemented.** ADR-0008 Accepted (2026-11-01). CI prepared, not active |
| Date | 2026-11-01 |
| Fixtures (owned by Core) | `waysnx-business-framework/tests/conformance/` (suite 0.1.0, 33 fixtures, `suite.json`, `fixture.schema.json`) |
| Strategy and normalization rules | Core `docs/architecture/CROSS_RUNTIME_CONFORMANCE.md` |

## 1. What it does

This runtime executes the shared, technology-independent fixtures with **PHPUnit**. No new test framework is used. Expected outcomes are defined only in Core. This repository never edits or forks them.

| File | Purpose |
|---|---|
| `tests/Conformance/ConformanceTest.php` | Adapter. `testSuiteIntegrity()` checks the fixtures against `suite.json`; then one data-provider case per fixture, in manifest order. Drivers for validation, workflow, business-function, authorization and lifecycle |
| `tests/Conformance/conformance-known-differences.json` | Fixture IDs this runtime does not yet satisfy. Currently **empty** (`{}`) |
| `phpunit.xml` testsuite `conformance` | Runs the adapter. It is part of the default `vendor/bin/phpunit` run |
| `.github/workflows/conformance.yml` | CI job, pinned to Core `conformance-v0.1.0`. Manual trigger only until the tag exists |

## 2. Running

```bash
# Default: sibling checkout ../waysnx-business-framework/tests/conformance/fixtures
vendor/bin/phpunit --testsuite conformance

# Explicit location, strict mode (as in CI) and a JSON report
WBF_CONFORMANCE_FIXTURES=/path/to/waysnx-business-framework/tests/conformance/fixtures \
WBF_CONFORMANCE_REQUIRED=1 \
WBF_CONFORMANCE_REPORT=/tmp/laravel-conformance.json \
  vendor/bin/phpunit --testsuite conformance
```

| Fixtures found? | `WBF_CONFORMANCE_REQUIRED` | Result |
|---|---|---|
| no | unset | Skipped, with the expected path in the message. The regular `ci.yml` behaves this way |
| no | `1` | **Fails** with `CONFIGURATION ERROR: …` |
| yes | either | Integrity check, then every fixture |

## 3. Known differences

| Behavior | Result |
|---|---|
| A listed fixture that fails | Reported as **incomplete**, with the normalized actual outcome in the message |
| A listed fixture that passes | **Fails**. Remove it from the list |
| An unlisted fixture that fails | **Fails** |

Current list (2026-11-01): **empty**. The Phase 0 entries are resolved:

| Fixture | Resolved by |
|---|---|
| WF-003 missing sequence | D8: `Registry\WorkflowStep` requires `sequence` (WBF-SPEC-0007) |
| BF-004 business rule failure | D1: `BusinessFunctionRuntime::run()` with categorized exceptions and phase fallback (WBF-SPEC-0006) |
| BF-005 execution failure | D1: same |

## 4. Result (2026-11-01)

33 fixtures: **33 pass, 0 known differences, 0 unexpected failures**, plus the integrity test. The normalized outcomes are identical to the Node runtime's for all 33.

Negative controls (temporary fixture copies): duplicate ID, empty `expected`, disallowed status, wrong expectation and `fixtureCount` mismatch each made the adapter fail. A missing checkout with `WBF_CONFORMANCE_REQUIRED=1` failed with `CONFIGURATION ERROR`.

Passing suite 0.1.0 does **not** establish general parity with Node. It covers narrow slices of five contract areas.

## 5. How native outcomes are read (CROSS_RUNTIME_CONFORMANCE §6)

| Contract | Native source | Normalization |
|---|---|---|
| Business function, authorization | `BusinessFunctionRuntime::run()` → `ExecutionResult::$status`; `errorCode` → `authorizationReason` | None. Statuses are native D1 values |
| Workflow | `WorkflowResult`; construction errors | `WorkflowDefinitionException` / `DuplicateWorkflowException` → `definition_rejected`. Error messages → kinds `duplicate_sequence`, `duplicate_step_id`, `function_not_registered`, `step_failed`, otherwise `unclassified` |
| Validation | `ValidationIssue` | `{ruleId, field: target, code: errorCode}` grouped by severity |

The Phase 0 exception-to-status mapping (including `unclassified_failure`) was removed. Classification now lives in the runtime (`BusinessFunctionRuntime::classify()`), not in the adapter.

## 6. CI

`.github/workflows/conformance.yml`:

- checks out `waysnx/waysnx-business-framework` at `conformance-v0.1.0` into `wbf-core/`
- sets `WBF_CONFORMANCE_REQUIRED=1` and `WBF_CONFORMANCE_FIXTURES`
- fails with `CONFIGURATION ERROR` if `suite.json` is missing
- runs `vendor/bin/phpunit --testsuite conformance` and uploads the JSON report

Trigger: `workflow_dispatch` only. To activate after the Core commit and tag exist, add the `push` / `pull_request` triggers used by `ci.yml`.
