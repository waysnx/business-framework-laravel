# Entity Implementation Plan – Laravel Runtime

| | |
|---|---|
| Status | **Plan only – blocked until ADR-0006 is accepted** |
| Date | 2026-10-31 |
| Architecture | primary repo `docs/architecture/ENTITY_ARCHITECTURE.md`, `decisions/ADR-0006-Entity-Contract-and-Runtime-Realization.md` |

## 1. Starting point (verified)

- `Core\BaseModelAbstract` → `Models\BaseModel` is the base of the meta-model artifacts:
  - Module, Domain, BusinessCapability, BusinessFunction, Workflow.
  - It is not Eloquent. It has UUID identity via `ramsey/uuid`, audit and soft-delete fields, and no-op hooks.
- `Registry\EntityDefinition(id, name, displayName, className, namespace, description, version, tags, metadata)` has no attributes.
- `Contracts\RepositoryInterface` + `Repositories\BaseRepository`:
  - Nothing is persisted.
  - `transaction()` calls the callback directly.
  - `create()` / `update()` assign any input key that matches a property.
  - The container binding is unresolvable, because the constructor requires `$model`.
- `Traits\HasAudit`, `HasIdentifiers`, `HasLifecycleHooks`, `HasMetadata` and `HasVersioning` are unused duplicates of the base-class logic.
- There is no `illuminate/database` dependency.

## 2. Principles

1. Do **not** change `BaseModelAbstract` / `BaseModel` behavior or API. Add documentation only.
2. Application entities stay idiomatic Eloquent models (`extends Model`) and *compose* WBF behavior.
3. All guarantees (lifecycle, audit, tenancy, revision) are enforced in the repository adapter. Direct `save()` is documented as a bypass.
4. No new mandatory dependency. `illuminate/database` and `illuminate/validation` become `suggest` + `require-dev`, or the adapter ships as a separate package (decision D1).
5. Every step lands with unit tests and the matching conformance scenarios (`ENT-*`).

## 3. Phases

### E0 – Clarify and deprecate (no behavior change)

- Docblocks:
  - `BaseModelAbstract`: "base for WBF meta-model artifacts; not an application entity base".
- Deprecations:
  - `@deprecated` on `Repositories\BaseRepository` and `Services\BaseService` transaction semantics, with a pointer to E2.
  - Deprecate the `RepositoryInterface → BaseRepository` binding. Remove it in a later minor.
- Tests: existing suite stays green.

### E1 – Contracts (framework-independent PHP)

| New type | Responsibility |
|---|---|
| `Contracts\Entity\WbfEntity extends Types\EntityInterface` | `getEntityType(): string` (EntityDefinition id), `getEntityId(): string`, `getRevision(): int`, audit getters, `isDeleted()` |
| `Contracts\Entity\EntityRepository` | `findById`, `getById`, `save`, `delete`, `restore`, `findBy(criteria, page, sort)`, all taking an `ExecutionContext` |
| `Contracts\Persistence\TransactionManager` | `run(ExecutionContext $ctx, callable $fn): mixed` |
| `Contracts\Clock` | `now(): DateTimeImmutable`, injected for testability |
| `Registry\EntityDefinition` (extended) | New optional named parameters appended at the end: `moduleId`, `attributes` (`AttributeDefinition[]`), `softDelete`, `audit`, `timestamps`, `identity` (`uuid` \| `increment` \| `assigned`) |
| `Registry\AttributeDefinition` | `name`, `type` (canonical), `required`, `nullable`, `default`, `writable`, `sensitive`, `rules[]` |
| `Exceptions\ConcurrencyError` | Category `ConcurrencyError` |

Tests: value-object construction, definition validation, and canonical serialization.

### E2 – Eloquent adapter

| New type | Responsibility |
|---|---|
| `Concerns\InteractsWithWbfEntity` (trait for Eloquent models) | Implements `WbfEntity` on top of model attributes. Maps `revision`, `created_by` and similar columns. Supplies `wbfDefinitionId()` |
| `Persistence\Eloquent\EloquentEntityRepository` | Allow-listed `forceFill()` from `AttributeDefinition::writable`. Timestamps from `Clock`. `revision` optimistic check (`UPDATE … WHERE revision = ?` → `ConcurrencyError`). Soft delete via `SoftDeletes` when `softDelete=true`. Audit fields from `ExecutionContext.actor`. Tenant scope from `ExecutionContext.tenant`. Dispatches `before*/after*` lifecycle events through `LifecycleManager` |
| `Persistence\Eloquent\DatabaseTransactionManager` | `DB::connection()->transaction()` |
| Service provider | Binds the adapters only when `class_exists(\Illuminate\Database\Eloquent\Model::class)` |

Tests:

- **Unit:** an in-memory SQLite connection via `illuminate/database` in require-dev.
- **Conformance:** `ENT-ID-001` (identity immutable), `ENT-SD-001` (soft delete excluded from reads), `ENT-REV-001` (stale revision → `ConcurrencyError`), `ENT-AUD-001` (createdBy from actor, not from payload), `ENT-MA-001` (undeclared attribute rejected).

### E3 – Validation and BF integration

- The repository runs L2 entity validation (`docs/VALIDATION_IMPLEMENTATION_PLAN.md`) before writes.
- `BusinessFunctionRuntime` optionally wraps execution and state changes in `TransactionManager::run()`. It publishes events after commit.

### E4 – Cleanup (later minor)

- Remove the unused `Traits/*`, or reuse them in `InteractsWithWbfEntity`.
- Remove the deprecated binding.

## 4. Database schema impact

No schema in this repository changes. Host applications that adopt E2 need these columns on entity tables:

- `revision` (unsigned int, default 1)
- optional `deleted_at` / `deleted_by`
- optional `created_by` / `updated_by`

A publishable migration stub (`wbf:entity-columns`) is proposed for E2. It is not created now.

## 5. Decisions required

| # | Decision |
|---|---|
| D1 | Eloquent adapter location: this package (`suggest`) or `waysnx/business-framework-eloquent` |
| D2 | Canonical `entityId` for integer primary keys: allow and serialize as strings, or require UUIDs |
| D3 | Lifecycle source: repository only, or bridge Eloquent model events too (risk of double firing) |
| D4 | Whether `EntityDefinition::className` / `namespace` stay required (Node doesn't have them) |
