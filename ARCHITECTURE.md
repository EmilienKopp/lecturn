# Architecture Guidelines

This document defines the architectural conventions for this project. All agents and LLMs
contributing to this codebase must follow these rules.

---

## Core Principle

This project uses a **pragmatic CQRS layered architecture**:

```text
Write side:
HTTP Layer → Action Layer → Domain Layer → Repository Layer → Tables (DB)

Read side:
HTTP Layer → ReadModel Layer → Views (DB)

     ↕ (via Inertia)
Frontend (Svelte 5 / Inertia)
```

**The fundamental split:**

- Need to load an aggregate and do something to it → **Repository + Action**
- Need shaped data for a view → **ReadModel**

Each layer has a single responsibility. Do not bleed logic across layers.

---

## Pragmatic Purity Boundary

Not every Laravel concern requires a wrapper or interface. Apply this test before adding abstraction:

**Wrap it when:**

- The implementation is realistically replaceable (e.g. a different storage driver, a third-party API)
- Test behaviour differs from production behaviour without a seam (e.g. Eloquent queries)

**Don't wrap it when:**

- The implementation is stable and will never be swapped independently of the framework
- Laravel already behaves identically in tests

| Concern                              | Wrap?  | Why                                              |
|--------------------------------------|--------|--------------------------------------------------|
| Eloquent queries (write side)        | ✓ Yes  | Repository pattern — the whole point             |
| Mail / Queue dispatch                | ✓ Yes  | Swappable driver, fakeable in tests              |
| `Collection` / `LengthAwarePaginator`| ✗ No   | Framework primitive, stable, testable as-is      |
| `Context` API                        | ✗ No   | No production vs. test behaviour difference      |
| Logging                              | ✗ No   | Laravel-injected, no custom seam needed          |

**`Collection`, and `LengthAwarePaginator` are treated as pure for this project.**
They may appear in repository interfaces and return types. This is a deliberate tradeoff
documented here so it is not "corrected" by a future contributor.

**`event()` and `dispatch()` are treated as pure for this project.** They are framework-level
language features, not infrastructure concerns. Actions may dispatch events directly without
wrapping them in a service. This is a deliberate tradeoff documented here so it is not "corrected" by a future contributor.

---

## DB Layer Convention

The database layer is split along the same CQRS boundary:

- **Tables** are touched only by Repositories (via Eloquent Models)
- **Views** are read only by ReadModels (via `ReadOnlyModel`)

This is a mechanical enforcement of the boundary: if it's a view, it's a ReadModel concern;
if it's a table, it's a Repository concern. When you need a new read shape, create the view
first — the schema forces you to design the read model explicitly.

View migrations live in `database/views/` as dedicated migration files, separate from table
migrations. Never mix view DDL into table migration files.

---

## Layer Rules

### 1. Controllers (`app/Http/Controllers/`)

- **Thin only.** Controllers orchestrate; they do not contain business logic.
- Resolve input from the request, call an Action or ReadModel, return an Inertia response or redirect.
- Use **constructor injection** for Actions and ReadModels.
- Always validate with a dedicated `FormRequest` class — never inline validation.
- Return `Inertia::render()` for page renders, `redirect()->route()` for post-action redirects.
- Controllers must not know about domain entities directly — they pass validated scalars or DTOs
  to Actions and receive whatever the Action or ReadModel returns.

**Injection pattern by operation type:**

- Mutation → inject Action(s)
- Read → inject ReadModel(s)
- Mixed page (reads + a form) → inject both

```php
// Write operation
class StopTimerController extends Controller
{
    public function __construct(private readonly StopTimer $stopTimer) {}

    public function __invoke(StopTimerRequest $request): RedirectResponse
    {
        $this->stopTimer->execute(
            new StopTimerCommand(
                userId: Auth::id(),
                dailyLogId: $request->daily_log_id,
                stoppedAt: now(),
            )
        );

        return redirect()->route('dashboard');
    }
}

// Read operation — no Action involved
class DashboardController extends Controller
{
    public function __construct(private readonly DailyLogReadModel $readModel) {}

    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'logs' => $this->readModel->forDashboard(Auth::id(), today()),
        ]);
    }
}
```

---

### 2. Actions (`app/Application/Actions/`)

Actions are the write side of the application layer. They represent a single business operation.

- **One Action per business operation.**
- Named with an imperative verb phrase: `StopTimer`, `CreateDailyLog`, `ApplyRate`.
- Single public method: `execute()` with a typed Command object and explicit return type.
- Accept **Command objects** (plain DTOs) as input — never raw arrays or HTTP requests.
- Return **domain entities or scalar values** — never Eloquent models.
- Use constructor-injected **repository interfaces** — never touch Eloquent directly.
- May dispatch Laravel events after persistence — the framework event system is a native language
  feature, not an infrastructure concern to abstract.
- Must not know about HTTP, sessions, or `Auth::` — resolved by the controller before calling.
- **Actions are write-only.** If an Action only reads and returns data without mutating anything,
  it belongs in a ReadModel instead.
- **Actions do not have interfaces.** A `StopTimerInterface` with one implementation is ceremony
  with no benefit. Add an interface only if a concrete second implementation exists. YAGNI applies.

```php
// CORRECT
class StopTimer
{
    public function __construct(
        private readonly DailyLogRepository $dailyLogRepo,
    ) {}

    public function execute(StopTimerCommand $cmd): DailyLogEntity
    {
        $log = $this->dailyLogRepo->findById($cmd->dailyLogId);
        $log->stopTimer($cmd->stoppedAt);
        $this->dailyLogRepo->save($log);

        TimerStopped::dispatch($log->id, $cmd->stoppedAt);

        return $log;
    }
}

// WRONG — action only reads, no mutation
class GetDashboardLogs
{
    public function execute(int $userId): array
    {
        // This is a ReadModel, not an Action
    }
}
```

---

### 3. Commands (`app/Application/Commands/`)

- Plain readonly DTOs carrying input data into an Action.
- No logic — just typed properties.
- Named after the Action they serve: `StopTimerCommand`, `CreateDailyLogCommand`.

```php
readonly class StopTimerCommand
{
    public function __construct(
        public int $userId,
        public int $dailyLogId,
        public DateTimeInterface $stoppedAt,
    ) {}
}
```

---

### 4. Domain Entities (`app/Domain/{Domain}/Entities/`)

- Named `NounEntity` — e.g., `DailyLogEntity`, `ProjectEntity`, `ClockEntryEntity`.
- Contain **business behaviour** — not just data bags.
- Enforce their own invariants: if a rule can be broken, the entity prevents it.
- **Parent entities** have an associated Repository and are the unit of persistence.
- **Child entities** have no Repository and are always loaded through their parent. Document this
  with a docblock: `Child entity of DailyLogEntity — no standalone repository.`
- Do not import Eloquent models, HTTP concerns, or repositories.
- Do not use Laravel facades or static calls — plain PHP only.
- Do not dispatch events or call services — entities compute and enforce invariants only.

```php
// Parent entity — has DailyLogRepository
class DailyLogEntity
{
    private array $clockEntries = [];

    public function stopTimer(DateTimeInterface $at): void
    {
        if (!$this->isRunning()) {
            throw new TimerNotRunning();
        }

        $entry = new ClockEntryEntity(
            dailyLogId: $this->id,
            in: $this->startedAt,
            out: $at,
        );

        $this->clockEntries[] = $entry;
        $this->recalculateTotal();
    }

    private function recalculateTotal(): void
    {
        $this->total_seconds = array_sum(
            array_map(fn($e) => $e->duration_seconds, $this->clockEntries)
        );
    }
}

/**
 * Child entity of DailyLogEntity — no standalone repository.
 * Load via DailyLogRepository::findById().
 */
class ClockEntryEntity
{
    public function __construct(
        public readonly int $daily_log_id,
        public readonly DateTimeInterface $in,
        public readonly DateTimeInterface $out,
    ) {}
}
```

---

### 5. Value Objects (`app/Domain/{Domain}/ValueObjects/`)

- Immutable — no setters, `readonly` properties.
- Compared by value, not identity — no ID.
- Validate constraints in the constructor; throw on invalid input.
- Transformations return a new instance, never mutate in place.
- Named as plain nouns: `Duration`, `Money`, `Timezone`, `DateRange`.
- Shared value objects live in `app/Domain/Shared/ValueObjects/` and may be imported across
  domains — this is the only permitted cross-domain import.

```php
readonly class Money
{
    public function __construct(
        public float $amount,
        public string $currency,
    ) {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatch();
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
```

---

### 6. Domain Events (`app/Domain/{Domain}/Events/`)

- Named in past tense: `ClockEntryCreated`, `TimerStopped`, `BudgetAdjusted`.
- Use the `Dispatchable` trait only — do not use `SerializesModels` or `InteractsWithSockets`
  unless the event is a broadcast event.
- Carry only the data listeners need — IDs and value objects, never full entities or Eloquent models.
- Dispatched explicitly from Actions after persistence.
- Laravel's event system is a language-level feature — no wrapping interface needed.

```php
class TimerStopped
{
    use Dispatchable;

    public function __construct(
        public readonly int $daily_log_id,
        public readonly int $user_id,
        public readonly Duration $duration,
        public readonly DateTimeInterface $occurred_at,
    ) {}
}
```

---

### 7. Repositories (`app/Infrastructure/Persistence/Repositories/`)

Repositories are the authoritative data access object for an aggregate root. They handle both
reading and writing, but exclusively via **tables** — never views.

**Interfaces** live in `app/Domain/{Domain}/Contracts/`.
**Implementations** live in `app/Infrastructure/Persistence/Repositories/`.

- All repository interfaces are defined in the Domain layer — dependency direction flows inward.
- Repositories touch **tables only**. Views are ReadModel territory.
- Repositories follow aggregate root boundaries — one Repository per aggregate root, no exceptions.
  Child entities have no repository; they are always loaded through their parent.
- Hydration is done via a `HasDomainEntity` trait on the Eloquent Model — no standalone Mapper
  classes. The trait provides `toEntity()`; complex aggregates override it. This keeps mapping
  at the Infrastructure boundary (Model → Entity) without a third abstraction.
- Parent entities are always loaded with their child entities — the repository assembles the full
  aggregate in one operation.
- Repositories are the **only place** Eloquent models are touched on the write side.
- Register bindings in `app/Providers/RepositoryServiceProvider.php`.
- Always inject the interface, never the concrete implementation.

```php
// Interface — lives in Domain layer
interface DailyLogRepository
{
    public function findById(int $id): DailyLogEntity;
    public function findByUserAndDate(int $userId, DateTimeInterface $date): ?DailyLogEntity;
    public function save(DailyLogEntity $log): void;
}

// Eloquent Model with HasDomainEntity trait
class DailyLogModel extends Model
{
    use HasDomainEntity;

    public function toEntity(): DailyLogEntity
    {
        return new DailyLogEntity(
            id: $this->id,
            user_id: $this->user_id,
            total_seconds: $this->total_seconds,
            clockEntries: $this->clockEntries
                ->map(fn($e) => $e->toEntity())
                ->all(),
        );
    }
}

// Implementation — lives in Infrastructure
class EloquentDailyLogRepository implements DailyLogRepository
{
    public function findById(int $id): DailyLogEntity
    {
        return DailyLogModel::with('clockEntries')->findOrFail($id)->toEntity();
    }

    public function save(DailyLogEntity $log): void
    {
        DailyLogModel::updateOrCreate(
            ['id' => $log->id],
            $log->toArray(),
        );
        // child entities saved here too
    }
}
```

---

### 8. ReadModels (`app/Infrastructure/ReadModels/`)

ReadModels are the read side of the CQRS split. They query **views only** and return shaped data
directly for the HTTP layer — no domain entities, no hydration, no business rules.

- Named after the aggregate area they cover: `DailyLogReadModel`, `ProjectReadModel`.
- One class per aggregate area with multiple methods — never one class per query.
- Methods named after their purpose: `forDashboard()`, `paginateByUser()`, `summaryForReport()`.
- Return arrays, simple DTOs, or `LengthAwarePaginator` — whatever the controller needs.
- Use `ReadOnlyModel` (from `splitstack/laravel-rome`) as the Eloquent base for view-backed models.
- May use Eloquent or `DB::` query builder freely — they are Infrastructure, no restrictions apply.
- **Never define `toEntity()`** — ReadModels produce view data, not domain aggregates. If you need
  an entity, you are in Repository territory.
- No interface by default. Add one only if the same read contract is shared across multiple callers
  or you need a test seam. This is the exception, not the rule.

```php
// View-backed model — no toEntity(), read-only
class DailyLogSummaryModel extends ReadOnlyModel
{
    protected $table = 'v_daily_log_summaries'; // DB view
}

// ReadModel — queries views, returns shaped data
class DailyLogReadModel
{
    public function forDashboard(int $userId, DateTimeInterface $date): array
    {
        return DailyLogSummaryModel::query()
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->get()
            ->toArray();
    }

    public function paginateByUser(int $userId, int $perPage = 20): LengthAwarePaginator
    {
        return DailyLogSummaryModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('date')
            ->paginate($perPage);
    }
}

// WRONG — ReadModel touching a table
class DailyLogReadModel
{
    public function forDashboard(int $userId): array
    {
        return DailyLogModel::where('user_id', $userId)->get()->toArray(); // ← table, not view
    }
}

// WRONG — ReadModel producing an entity
class DailyLogReadModel
{
    public function find(int $id): DailyLogEntity // ← use Repository::findById() instead
    {
    }
}
```

---

### 9. Eloquent Models (`app/Models/`)

- Models are a **persistence detail only** — they are not passed across layers.
- Keep models lean: relationships, casts, fillable, `HasDomainEntity` trait. No business logic.
- The domain and application layers must never import a Model class.
- Model names use the `Model` suffix: `DailyLogModel`, `ProjectModel`, `ClockEntryModel`.
- View-backed models extend `ReadOnlyModel` and live in `app/Models/Views/`:
  `DailyLogSummaryModel`, `ProjectSummaryModel`.

---

## Frontend: Prefer Inertia Over fetch

**Default rule: use Inertia, not `fetch`/`axios`.**

### Inertia Patterns (Svelte 5)

| Need                             | Pattern                                            |
| -------------------------------- | -------------------------------------------------- |
| Navigate to a page               | `router.visit(url)`                                |
| Submit a form                    | `router.post(url, data)`                           |
| Partial refresh (preserve state) | `router.post(url, data, { preserveState: true })`  |
| Full refresh after mutation      | `router.post(url, data, { preserveState: false })` |
| Reactive props                   | `let { myProp } = $props()`                        |

### When fetch Is Acceptable

Only use `fetch` when:

- The endpoint returns **non-page data** (e.g. a JSON lookup, a CLI sync payload).
- The operation must be **silent** (no page transition).
- You are calling a **third-party API** directly from the client.
- Real-time data arrives via **Reverb/WebSocket** — use the Echo client, not polling.

---

## Naming Conventions

| Layer                        | Convention                                   | Examples                                              |
| ---------------------------- | -------------------------------------------- | ----------------------------------------------------- |
| Actions                      | `VerbNoun`                                   | `StopTimer`, `CreateDailyLog`, `ApplyRate`            |
| Commands                     | `VerbNounCommand`                            | `StopTimerCommand`, `CreateDailyLogCommand`           |
| Entities                     | `NounEntity`                                 | `DailyLogEntity`, `ProjectEntity`, `ClockEntryEntity` |
| Value Objects                | `Noun`                                       | `Duration`, `Money`, `Timezone`                       |
| Domain Events                | `NounVerbed` (past tense)                    | `ClockEntryCreated`, `TimerStopped`                   |
| Repository Interfaces        | `NounRepository`                             | `DailyLogRepository`, `ProjectRepository`             |
| Repository Implementations   | `Eloquent{Noun}Repository`                   | `EloquentDailyLogRepository`                          |
| Eloquent Models (tables)     | `NounModel`                                  | `DailyLogModel`, `ClockEntryModel`                    |
| Eloquent Models (views)      | `NounSummaryModel` / `NounViewModel`         | `DailyLogSummaryModel`, `ProjectSummaryModel`         |
| ReadModels                   | `NounReadModel`                              | `DailyLogReadModel`, `ProjectReadModel`               |
| Controllers                  | `VerbNounController`                         | `StopTimerController`, `StartTimerController`         |
| Form Requests                | `VerbNounRequest`                            | `StopTimerRequest`, `CreateProjectRequest`            |
| Svelte Pages                 | `kebab-case.svelte` in `resources/js/pages/` | `dashboard.svelte`, `daily-log.svelte`                |
| Svelte Components            | `PascalCase.svelte`                          | `ClockEntry.svelte`, `ProjectSelector.svelte`         |

### Entity Property Casing

Entity properties use `snake_case` to match database column names directly.
This is a deliberate tradeoff — `fromArray()`/`toArray()` gymnastics are eliminated
at the cost of PSR convention. Do not "correct" this to camelCase.

---

## Dependency Direction

```
Domain        → nothing (plain PHP, no framework imports)
Application   → Domain only
Infrastructure → Domain interfaces (implements them), Laravel internals allowed
Http          → Application (Actions, Commands), Infrastructure (ReadModels), Laravel allowed
```

The Domain layer is the strict boundary. Everything else may use Laravel freely.

ReadModels sit in Infrastructure and are injected directly into Controllers — there is no
Application layer intermediary on the read side. This is intentional.

---

## Types

Entities and Value Objects can be generated as TypeScript types with `php artisan split:typegen`.
Discovery is not automatic — add directories in `config/typegen.php`.

---

## Adding a New Feature — Checklist

### Write operation (mutation)

1. **Domain first** — does it need a new entity or value object? Define it in `app/Domain/`.
2. **Repository contract** — add a method to the relevant interface in `app/Domain/{Domain}/Contracts/`,
   or create a new interface if this is a new aggregate root.
3. **Repository implementation** — implement in `app/Infrastructure/Persistence/Repositories/`.
4. **Register DI** — add the binding in `RepositoryServiceProvider`.
5. **Command** — create a `VerbNounCommand` DTO in `app/Application/Commands/`.
6. **Action** — create the Action in `app/Application/Actions/`.
7. **Domain event** — if the operation has side effects, create an event in
   `app/Domain/{Domain}/Events/` and register listeners in `EventServiceProvider`.
8. **Form Request** — create a `FormRequest` for input validation.
9. **Controller** — thin controller that calls the Action and returns Inertia response.
10. **Route** — register in `routes/web.php`.
11. **Frontend** — build or update the Svelte page/component.
12. **Tests** — write a Pest feature test covering the happy path and key edge cases.

### Read operation (view data)

1. **View** — create a DB view migration in `database/views/`.
2. **View-backed model** — create a `ReadOnlyModel` in `app/Models/Views/`.
3. **ReadModel** — add a method to the relevant `NounReadModel` in `app/Infrastructure/ReadModels/`,
   or create one if none exists for this aggregate area.
4. **Controller** — inject the ReadModel, call the method, pass result to Inertia.
5. **Route** — register in `routes/web.php`.
6. **Frontend** — build or update the Svelte page/component.
7. **Tests** — write a Pest feature test against the view.

---

## Anti-Patterns to Avoid

- **Fat controllers** — no business logic in controllers, ever.
- **Eloquent in Actions** — Actions must not import or query Eloquent models directly.
- **Domain importing Laravel** — domain entities and value objects are plain PHP only.
- **Actions that only read** — if there is no mutation, use a ReadModel directly from the controller.
- **Ceremony interfaces on Actions** — a `StopTimerInterface` with one implementation is indirection
  without substitution. Don't add it.
- **ReadModels touching tables** — ReadModels read from views only. If you need table access,
  use a Repository.
- **Repositories touching views** — views are ReadModel territory. Repositories touch tables only.
- **`toEntity()` on a view-backed model** — ReadModels produce shaped data, not domain aggregates.
  If you need an entity, use the Repository.
- **One ReadModel class per query** — ReadModels are scoped to an aggregate area, not to a consumer
  or a single query. Add a method, don't create a new class.
- **Standalone Mapper classes** — use the `HasDomainEntity` trait on Eloquent Models instead.
- **Wrapping stable Laravel primitives** — `Collection`, `LengthAwarePaginator`, `Context`
  do not need interfaces. See Pragmatic Purity Boundary.
- **Child entity repositories** — `ClockEntryEntity` has no repository; load it through
  `DailyLogRepository`.
- **Cross-domain entity imports** — reference other domains by ID value objects only; the one
  exception is `app/Domain/Shared/ValueObjects/`.
- **`DB::` raw queries in Repositories** — use Eloquent; reserve query builder for genuinely
  complex cases and keep those in ReadModels where the restriction does not apply.
- **`env()` outside config files** — use `config('key')` everywhere in application code.
- **Side effects in domain entities** — entities raise no events, call no services, touch no
  infrastructure; they compute and enforce invariants only.
- **Raw `fetch` for page data** — use Inertia deferred props instead.
- **Mutable value objects** — value objects that change state in place break the domain model.
- **Concrete repository injection** — always inject the interface, not the Eloquent implementation.
- **Repositories returning Eloquent models** — repositories return domain entities, full stop.
- **View DDL in table migrations** — view migrations live in `database/views/` only.
