# WBF Compatibility Audit – Laravel Runtime

| | |
|---|---|
| Status | Draft – for review |
| Date | 2026-10-31 |
| Audited | `main` @ `8c20977` (tag `v0.1.0`) |
| Consolidated audit | primary repo `docs/audit/REPOSITORY_AUDIT.md` (finding IDs `LAR-*`, `XR-*`) |
| Capability matrix | primary repo `docs/architecture/IMPLEMENTATION_CAPABILITY_MATRIX.md` |

This document lists how the Laravel runtime departs from the WBF specification and the proposed core contracts. It also lists the planned fix for each departure. **No production code was changed by this audit.**

## Phase 0 status (2026-10-31, branch `phase0/m0-remediation`, uncommitted)

| Finding | Status | Evidence |
|---|---|---|
| LAR-01 validation default pass | **Fixed.** `required` evaluated; unknown/custom rule types fail closed | `tests/Unit/Validation/ValidationFailClosedTest.php` (17 tests). Conformance VAL-001…009 |
| LAR-02 workflow sequencing | **Fixed.** Smallest greater sequence; duplicate/negative sequences and duplicate IDs fail before execution; a run with skipped steps cannot complete (default transitions) | `tests/Unit/Workflow/WorkflowSequencingTest.php` (14). WF-001/002/004/006 |
| LAR-02 missing sequence | **Open, decision D8.** The `WorkflowStep` constructor defaults `sequence` to 0 | Conformance WF-003 (known difference) |
| LAR-03 BF invocation | **Fixed.** `registerBusinessFunction()`; default `executeBusinessFunction()` runs `BusinessFunctionRuntime`; unregistered function fails the step | WorkflowSequencingTest. WF-005, WF-007. `wbf:createdemo` now executes real functions |
| LAR-04 hard-coded dates | **Fixed.** Deterministic dates relative to `today` (the reference `ApplyLeave` uses), clear of the reference-data approved-leave windows | 4 former errors pass |
| LAR-08 authorization default allow | **Fixed (fail closed).** API provisional (ADR-0010) | `tests/Unit/Authorization/AuthorizationFailClosedTest.php` (11). AUTH-001…004, BF-003 |
| LAR-07 error categories | **Open** (ADR-0009 / D1) | BF-004, BF-005 known differences |
| Others (LAR-05, 06, 09–23) | Unchanged in Phase 0 | — |

## Phase 0.1 status (2026-11-01, same branch, uncommitted)

Supersedes the Phase 0 rows above where they differ.

| Finding | Status | Evidence |
|---|---|---|
| LAR-02 missing sequence | **Fixed (D8, breaking).** `WorkflowStep` takes `mixed $sequence = null`; missing, non-integer and negative values throw `WorkflowDefinitionException` at construction; `WorkflowDefinition` rejects duplicates at construction; `WorkflowEngine` keeps its defensive check | `tests/Unit/Workflow/WorkflowSequencingTest.php`. WF-003, WF-004, WF-008…010 |
| LAR-07 error categories | **Fixed (D1, additive).** `BusinessFunctionRuntime::run(): ExecutionResult` with six statuses; `CategorizedExceptionInterface`; `ValidationFailedException`, `BusinessRuleViolationException`, `ResourceNotFoundException`; `AuthorizationException` categorized; phase fallback via `BusinessFunctionAbstract::getFailedPhase()`. `execute()` unchanged | `tests/Unit/Runtime/ExecutionResultTest.php` (20). BF-001…007, AUTH-001…004 |
| LAR-08 authorization API | **Final** (ADR-0010 accepted 2026-11-01) | — |
| LAR-19 `tests/Core` mapped but absent | **Fixed (R3).** `tests/Core/BaseModelAbstractTest.php` moved from Core (27 tests). The never-passing Core `BusinessFunctionAbstractTest.php` was retired; its uncovered definition-validation behavior is rewritten as `tests/Core/BusinessFunctionAbstractTest.php` (29 tests). `tests/Core` is in `phpunit.xml` | `vendor/bin/phpunit tests/Core` (56 tests) |
| Authorization fallback (review R-2b) | **Removed.** A plain exception from `checkAuthorization()` or an authorizer is `execution_failure`. `ApplyLeave` and `examples/ApplyLeave.php` now deny with `AuthorizationException` (the example previously had an empty, allow-all override) | `ExecutionResultTest` (3 new tests) |
| Validation fallback (review R-2a) | **Approved and tested**: plain `\InvalidArgumentException` in the validation phase only; categorized exceptions win | `ExecutionResultTest` (3 new tests) |
| BF-ERR-03 messages (ADR-0009 A1) | **Fixed.** `execution_failure` → `ExecutionResult::SAFE_MESSAGE` + `errorReference`; full exception to the error reporter (constructor / `setErrorReporter()`, default `error_log()`); `WorkflowEngine` uses `run()` so step errors carry the public message | `ExecutionResultTest` (8 new tests), `WorkflowSequencingTest` (2 new tests) |
| Conformance | **33/33**, no known differences | `docs/CONFORMANCE_TESTING.md` |
| Others (LAR-05, 06, 09–18, 20–23) | Unchanged | — |

## 1. Test baseline (audit, before Phase 0)

| Command | Result |
|---|---|
| `composer install` | OK |
| `vendor/bin/phpunit` | 841 tests, 2140 assertions, **4 errors**. All errors are in `tests/Unit/BusinessFunctions/ApplyLeaveRuntimeIntegrationTest.php`: "startDate cannot be in the past" |
| `composer validate --strict` | OK |
| `composer lint` | OK on LF checkouts. Fails on Windows with `core.autocrlf=true` (no `.gitattributes`) |

## 2. Conformance against `specification/foundation/WBF-SPEC-0005` (Business Function)

| Requirement | Status | Where |
|---|---|---|
| BF-ID-01…07 identity | ✔ on `Models\BusinessFunction` (`validateMandatoryFields`). ✗ on `Registry\BusinessFunctionDefinition` (no domain, capability or lifecycle) | `src/Core/BusinessFunctionAbstract.php` L353-390; `src/Registry/BusinessFunctionDefinition.php` L184 |
| BF-META-01…10 | ✔ model. ✗ registry definition | same |
| BF-CON / BF-REQ / BF-RESP (contracts exist) | ✔ declared | — |
| §13 pipeline order | ✔ | `BusinessFunctionAbstract::execute()` L607-626 |
| BF-VAL (contract validation enforced) | ✗ empty hook `validateRequest()` | L644-647 |
| BF-SEC (authorization enforced) | ✗ empty hook, allow by default | L662-665 |
| Event publication | ✗ empty hook. Runtime lists *declared* events only | `publishEvents()`; `Runtime/BusinessFunctionRuntime.php` |
| §21 error categories | ✗ native `InvalidArgumentException` / `RuntimeException` | L604-605 |
| BF-ERR-02 distinguishable outcome | Partial (exception vs return) | `BusinessFunctionRuntime::execute()` |
| BF-VER | ✔ version field | — |

## 3. Findings and planned fixes

Severity:

- **P0** silent wrong outcome
- **P1** contract or parity violation
- **P2** correctness or maintainability
- **P3** hygiene

### P0

**LAR-02: `WorkflowEngine::determineNextStep()` skips steps** (`src/Workflow/WorkflowEngine.php` L336-349)
- **Current behavior:** the method returns the first step *in array order* whose `sequence` is greater than the current one.
  - Reproduced: steps declared `[s1:1, s3:3, s2:2]` executed `A, C`. Step `s2` was skipped, and the engine still reported `succeeded() === true`.
- **Planned fix:** pick the minimum greater sequence, and reject duplicate sequences at registration.

```php
protected function determineNextStep(WorkflowDefinition $workflow, WorkflowStep $currentStep, array $completedSteps): ?string
{
    $next = null;
    foreach ($workflow->steps as $step) {
        if ($step->sequence > $currentStep->sequence
            && ($next === null || $step->sequence < $next->sequence)) {
            $next = $step;
        }
    }
    return $next?->id;
}
```

- **Test:** a regression unit test, plus conformance scenario `WF-ORDER-001`.
- **Breaking:** no. This is a behavior fix.

**LAR-01: `ValidationFramework::evaluateRule()` passes every rule** (`src/Validation/ValidationFramework.php` L247-255)
- **Current behavior:** reproduced that a `required` rule on empty input passes.
- **Planned fix:** see `docs/VALIDATION_IMPLEMENTATION_PLAN.md`. The work is:
  - Built-in canonical rules evaluated through the Laravel Validator.
  - Unknown rule types fail closed.
  - A `strict` config flag for one minor release.

### P1

| ID | Location | Issue | Planned fix |
|---|---|---|---|
| LAR-03 | `WorkflowEngine::executeBusinessFunction()` L273-277 | Empty, so workflows "succeed" without invoking any Business Function | Add an optional `BusinessFunctionResolver` (container-backed) and invoke `BusinessFunctionRuntime` per step. Keep the method overridable. The default is the resolver when it is bound, and the current no-op otherwise (non-breaking) |
| LAR-04 | `tests/Unit/BusinessFunctions/ApplyLeaveRuntimeIntegrationTest.php` (9 date pairs, L44-348); `ApplyLeaveTest.php` L253 | Hard-coded 2026 dates | Reuse the `futureDate()` / `pastDate()` helpers from `ApplyLeaveTest` L46-63 |
| LAR-05 | `Registry\BusinessFunctionDefinition` | Lacks domain, capability, lifecycle status and owners | Append optional named constructor parameters. Add `BusinessFunctionAbstract::toDefinition()` |
| LAR-06 | `Repositories\BaseRepository` | Does not persist. `transaction()` is a no-op. Mass assignment of any matching property. Unresolvable container binding | Mark `@deprecated` / experimental now. Replace via `docs/ENTITY_IMPLEMENTATION_PLAN.md` |
| LAR-07 | BF exceptions | No §21 categories | Add `Exceptions\BusinessError` with `category()` / `code()` and subclasses (`ValidationError`, `AuthorizationError`, `BusinessRuleViolation`, `ResourceNotFound`, `Conflict`, `ConcurrencyError`, `ExternalDependencyFailure`). Make them extend `\InvalidArgumentException` / `\RuntimeException` where they replace existing throws, so existing `catch` blocks keep working. Add `BusinessFunctionRuntime::run(): ExecutionResult` |
| LAR-08 | `checkAuthorization()` | Fail-open | Add an `Authorizer` contract. The default throws `AuthorizationError` when `authorizationRequirements` is non-empty and no authorizer is bound. Opt-in flag first. Offer a Laravel `Gate` adapter |
| LAR-09 | `Core\BaseModelAbstract` | Artifact base, confused with an entity base | Docblock clarification. ADR-0006 |
| LAR-22 | BF pipeline | Only `mixed $caller`. No correlation or tenant | Add an optional `ExecutionContext` parameter. `$caller` is still accepted and wrapped as `actor` |

### P2 / P3

| ID | Issue | Planned fix |
|---|---|---|
| LAR-10 | `illuminate/console` is require-dev only, but `src/Console/*` extends `Illuminate\Console\Command`. `config_path()` and `base_path()` are foundation helpers | Decide between `require` and `suggest` with a guard (`class_exists`) in `registerCommands()`. **Package metadata change, needs approval** |
| LAR-11 | `src/BusinessFunctions/ApplyLeave.php` and `src/ReferenceData/LeaveReferenceData.php` hold application logic and static state | `@deprecated`. Move to `examples/` and test fixtures in the next minor |
| LAR-12 | `config/business-framework.php` keys are never read | Wire `validation.framework.stop_on_error` and `workflow.engine.max_steps`. Mark the rest as reserved |
| LAR-13 / LAR-17 | README inaccuracies (`src/Providers/`, `afterExecute()`, `BusinessFrameworkServiceProvider` role, "WorkflowEngine invokes business functions") | README correction (documentation synchronization S1.4) |
| LAR-14 | Exception tree: no root. `ValidationFrameworkException` and `WorkflowException` extend `LifecycleException` | Introduce a root `WbfException` above the existing bases. This is additive |
| LAR-15 | Generators: path traversal and identifier injection | Validate `name`, `module` and `--namespace` against identifier regexes. Reject `/`, `\` and `..` |
| LAR-16 | `BusinessFunctionRuntime` stores per-execution state on the instance | Return an immutable execution record. Keep the `getContext()` accessor for the last run |
| LAR-18 | Dead code: `Traits/*`, `WorkflowTransition`, `WorkflowExecution`, `WorkflowStepExecution`, `ValidationExecution` | Remove after a deprecation window, or adopt in the entity work |
| LAR-19 | `composer.lock` is tracked yet listed in `.gitignore`. `autoload-dev` maps a missing `tests/Core/` | Decide whether the lock is tracked (library: usually not). Move `packages/core/tests` from the primary repo into `tests/Core/` (ADR-0005) |
| LAR-20 | No `.gitattributes` | Add `* text=auto eol=lf` |
| LAR-21 | `BaseModel::toArray()` uses snake_case. The BFD fixture uses Laravel rule strings | Add a canonical serializer (camelCase). Add canonical rule objects to the fixture |

## 4. Intentional runtime-specific behavior (not violations)

- Business logic is attached by subclassing (`executeBusiness()`). Node uses registered handler functions instead.
- Pipeline hooks are protected template methods.
- Artisan commands are `wbf:*`, and generators write files under `app/`.
- Exception classes are per artifact type. They are acceptable once each maps to a §21 category.
- Container singletons are used for registries and engines.

## 5. Claims to correct

The README must not claim equivalence with the Node runtime until the shared conformance suite passes (ADR-0008).

Add a "WBF compatibility" section to the README. It should state:

- the target spec version
- the conformance suite version and its results
- a link to the capability matrix
