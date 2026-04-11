# ctg-php-test v2.2 — Language-Specific Specification

**Realizes:** `test-design-doc.v2.2.md` (language-agnostic design document)
**Supersedes:** `spec.v2.md`
**Target:** PHP >=8.1, `declare(strict_types=1)`, zero external dependencies
**Namespace:** `CTG\Test`

---

## 1. Realization Map

| Design Doc Primitive | PHP Class / Type | Notes |
|---|---|---|
| `STATE` | `CTGTestState` | First-class object with `$_subject`, `$_computed`, `$_results` |
| `PREDICATE` | `CTGTestPredicate` | First-class object with `$_expectedOutcome` and `evaluate()` method |
| `PIPELINE` | `CTGTest` | The public-facing pipeline builder and executor; stores operations internally |
| `RESULT` | `CTGTestResult` | Value object; fields match design doc exactly |
| `STATUS` | `CTGTestStatus` (enum) | Backed string enum: `PASS`, `FAIL`, `ERROR` |
| `FRAMEWORK_ERROR` | `CTGTestError` | Extends `\Exception`; typed codes, bidirectional lookup |
| `CONFIG` | Associative array | `array{haltOnFailure: bool, timeout: int}` — no class needed |
| `ERROR` (primitive) | `\Throwable` | PHP's native exception interface |
| `VOID` (primitive) | `null` | PHP's native null |
| `FORMATTER` | `CTGTestFormatterInterface` | `CTGTestState -> STRING` contract |

**No STEP row.** The design doc v2.2 removes STEP as a primitive type. The pipeline stores its operations internally — how it represents them is an implementation concern. There is no public `CTGTestStep` class.

> **Judgment Call — STATE as a class, not an array:** v1 uses scattered arrays for state threading. v2+ requires `STATE` to be a first-class type per the design doc. A class gives us typed field access, prevents typo-based bugs in field names, and makes the slot-deposit model explicit. The class is mutable internally (the pipeline writes to its slots during execution) but its constructor and field access are controlled.

> **Judgment Call — CONFIG as an array, not a class:** CONFIG has exactly two keys with stable defaults and no behavior. A class would add ceremony without value. The pipeline validates the array structure and rejects unknown keys.

> **Judgment Call — STATUS as a backed enum:** PHP 8.1+ enums are the natural realization of a closed set of string values. A backed string enum gives us type safety at call sites and `->value` for serialization. The enum has exactly three cases per the design doc — `PASS`, `FAIL`, `ERROR`. `RECOVERED` is NOT a case; it is an implementation refinement outside the core status set.

> **Judgment Call — STEP removed from realization map:** The design doc v2.2 says: "PIPELINE stores the operations registered via its builder methods internally. How the pipeline represents those operations — closures, tagged records, parallel arrays, or any other structure — is an implementation concern." CTGTestStep is removed from the public surface. The pipeline may use any internal representation (closures in an array, tagged arrays, private value objects — whatever is idiomatic). No external code creates, inspects, or extends individual operation representations.

> **Judgment Call — label trimming:** v1 trims step labels on construction. v2.2 continues this: `trim()` is applied to all label strings. An empty-after-trim label is caught during validation, not at the builder call site.

---

## 2. Public Surface

### 2.1 CTGTestStatus (enum)

```
realizes: Core Semantics > Primitives > STATUS
```

```php
namespace CTG\Test;

enum CTGTestStatus: string {
    case PASS  = 'PASS';
    case FAIL  = 'FAIL';
    case ERROR = 'ERROR';
}
```

**Three cases, no more.** `RECOVERED` is not a status value. `skipped` is a bool field on `RESULT`, not a status.

> **Judgment Call — UPPERCASE backing values:** The design doc uses UPPERCASE for status names (`PASS`, `FAIL`, `ERROR`). The enum cases and their string values match this exactly. v1 used lowercase (`'pass'`, `'fail'`, `'error'`); v2+ breaks from v1 to align with the design doc. Formatters that need lowercase can call `strtolower($status->value)`.

---

### 2.2 CTGTestState

```
realizes: Core Semantics > Primitives > STATE
```

```php
namespace CTG\Test;

class CTGTestState {

    /* Instance Properties */
    private string $_label;
    private mixed $_subject;
    private mixed $_computed;
    private array $_results;

    // CONSTRUCTOR :: STRING, MIXED -> ctgTestState
    // Creates initial state with pipeline label and subject; computed is null, results is []
    private function __construct(string $label, mixed $subject) { ... }

    // :: VOID -> STRING
    public function getLabel(): string { ... }

    // :: VOID -> MIXED
    public function getSubject(): mixed { ... }

    // :: MIXED -> VOID
    public function setSubject(mixed $subject): void { ... }

    // :: VOID -> MIXED
    public function getComputed(): mixed { ... }

    // :: MIXED -> VOID
    public function setComputed(mixed $computed): void { ... }

    // :: VOID -> [ctgTestResult]
    public function getResults(): array { ... }

    // :: ctgTestResult -> VOID
    public function addResult(CTGTestResult $result): void { ... }

    // Static Factory Method :: STRING, MIXED -> ctgTestState
    public static function init(string $label, mixed $subject): static { ... }
}
```

**Initial field values:**
- `$_label`: the pipeline label, set by `start()` from `CTGTest::getLabel()`
- `$_subject`: the value passed to `init`
- `$_computed`: `null` (VOID)
- `$_results`: `[]`

`setSubject` and `setComputed` are package-internal mutation methods. They exist so the pipeline can write to state slots; user code receives the state from `start()` and reads it.

> **Judgment Call — mutable setters instead of `withX` immutables:** The design doc's slot-deposit model requires the pipeline to mutate state in place during execution. Immutable copies would break the shared-state threading that chains depend on. The setters are not public API surface for callers — they are pipeline-internal. PHP has no package-private visibility, so these are `public` methods on the class. The class is not `final` to allow extension by domain-specific state subclasses per the design doc's extension model (Core Concepts section 5).

> **Judgment Call — pipeline label on STATE:** The design doc's formatter contract is `STATE -> OUTPUT`, meaning formatters receive only STATE. But the reference formatter output starts with "Pipeline: {label}". To satisfy the formatter contract without passing the pipeline alongside state, STATE carries the pipeline label. `start()` sets it from `CTGTest::getLabel()` when creating the state. This keeps the `STATE -> OUTPUT` contract pure — formatters have everything they need from STATE alone.

> **Judgment Call — addResult takes CTGTestResult, not array:** v1 used arrays for results. v2+ uses the typed CTGTestResult class. This enforces structural correctness at the type level.

---

### 2.3 CTGTestResult

```
realizes: Core Semantics > Primitives > RESULT
```

```php
namespace CTG\Test;

class CTGTestResult {

    /* Instance Properties */
    public readonly array $_label;
    public readonly bool $_skipped;
    public readonly ?CTGTestStatus $_status;
    public readonly mixed $_computedValue;
    public readonly mixed $_expectedOutcome;
    public readonly ?\Throwable $_error;

    // CONSTRUCTOR :: [STRING], BOOL, ?ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult
    private function __construct(
        array $label,
        bool $skipped,
        ?CTGTestStatus $status,
        mixed $computedValue,
        mixed $expectedOutcome,
        ?\Throwable $error
    ) { ... }
}
```

**Field semantics:**

| Field | Type | VOID when | Populated when |
|---|---|---|---|
| `$_label` | `array` (of strings) | never — always present | always |
| `$_skipped` | `bool` | n/a | always |
| `$_status` | `?CTGTestStatus` | `null` when `$_skipped === true` | when operation ran |
| `$_computedValue` | `mixed` | `null` for stage results and skipped results | assert results |
| `$_expectedOutcome` | `mixed` | `null` for stage results and skipped results | assert results |
| `$_error` | `?\Throwable` | `null` unless status is ERROR | ERROR status |

**Static factory methods** (not a public constructor — results are created by the framework only):

```php
// Static Factory Method :: [STRING], ctgTestStatus -> ctgTestResult
// Creates a stage result (PASS or ERROR)
public static function stageResult(array $label, CTGTestStatus $status, ?\Throwable $error = null): static { ... }

// Static Factory Method :: [STRING], ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult
// Creates an assert result (PASS, FAIL, or ERROR)
public static function assertResult(
    array $label,
    CTGTestStatus $status,
    mixed $computedValue = null,
    mixed $expectedOutcome = null,
    ?\Throwable $error = null
): static { ... }

// Static Factory Method :: [STRING] -> ctgTestResult
// Creates a skipped result (any operation type)
public static function skippedResult(array $label): static { ... }
```

> **Judgment Call — readonly public properties with underscore prefix:** The underscore prefix follows the CTG code style for instance properties. Making them `public readonly` allows read access without getters, which keeps the result object lightweight. The private constructor prevents external instantiation; factory methods enforce correct field combinations.

> **Judgment Call — no `type` field:** The design doc's RESULT has no `type` field (no `'stage'`, `'assert'`, etc.). v1 had `type`; v2+ drops it. A result's meaning is determined by which fields are populated (stage results have null `computedValue`/`expectedOutcome`; assert results have them populated). This is a deliberate v1-to-v2 break.

> **Judgment Call — no `duration_ms` field:** The design doc RESULT has no duration field. v1 had `duration_ms`. v2+ drops it from the core result. If an implementation wants timing, it can be added as an extension field on a result subclass, but it is not part of the canonical result shape.

> **Judgment Call — no `message` field:** The design doc RESULT has no message field. Diagnostic information lives in the `error` field (which carries the full \Throwable). Formatters extract messages from `$error->getMessage()` per host-language convention.

---

### 2.4 CTGTestPredicate

```
realizes: Core Semantics > Primitives > PREDICATE
```

```php
namespace CTG\Test;

class CTGTestPredicate {

    /* Instance Properties */
    private readonly mixed $_expectedOutcome;
    private readonly \Closure $_evaluate;

    // CONSTRUCTOR :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate
    private function __construct(mixed $expectedOutcome, \Closure $evaluate) { ... }

    // :: VOID -> MIXED
    public function getExpectedOutcome(): mixed { ... }

    // :: MIXED -> BOOL
    // Applies the evaluate function to the given value
    public function evaluate(mixed $value): bool { ... }

    // Static Factory Method :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate
    public static function init(mixed $expectedOutcome, \Closure $evaluate): static { ... }
}
```

**Contract:**
- `$_expectedOutcome` is the value the predicate is checking against. It is stored for diagnostic purposes — formatters display it in failure messages.
- `$_evaluate` is a closure that takes the computed value and returns `true` (PASS) or `false` (FAIL).
- The predicate does NOT determine status. It returns a bool. The pipeline maps `true` to `PASS` and `false` to `FAIL`.
- If `evaluate` throws, the pipeline catches it and records ERROR status with the thrown exception in the result's `error` field.

> **Judgment Call — \Closure, not callable:** The design doc says PREDICATE has an `evaluate` function. PHP's `callable` type is too broad — it accepts strings (function names), arrays (`[$obj, 'method']`), and invokable objects. Using `\Closure` restricts the evaluate function to actual closures (anonymous functions and arrow functions), which is safer and more predictable. Any callable can be wrapped in `\Closure::fromCallable()` at the call site if needed.

> **Judgment Call — not `final`:** Convenience predicate builders (section 7) create instances via the factory, not via subclassing. However, domain extensions may want to subclass CTGTestPredicate for richer predicate types. Leaving it non-final preserves the extension surface described in Core Concepts section 5.

---

### 2.5 CTGTest (Pipeline)

```
realizes: Core Semantics > Primitives > PIPELINE
realizes: Core Semantics > Procedures > STAGE, ASSERT, CHAIN, SKIP, START
```

```php
namespace CTG\Test;

class CTGTest {

    /* Constants */
    // Maximum chain nesting depth — optional structural enforcement
    private const MAX_CHAIN_DEPTH = 64;

    // Structural enforcement error code range: 1100-1199
    // Outside canonical range (1000-1004, 2000-2001) per design doc
    public const CHAIN_DEPTH_EXCEEDED = 1100;

    /* Instance Properties */
    private string $_label;
    private array $_operations = [];

    // CONSTRUCTOR :: STRING -> ctgTest
    private function __construct(string $label) { ... }
```

#### Builder Methods (Fluent)

```php
    // :: STRING, MIXED -> $this
    // Appends a stage operation — handler returns new subject value
    // realizes: Core Semantics > Procedures > STAGE
    public function stage(string $label, mixed $fn): static { ... }

    // :: STRING, MIXED, MIXED -> $this
    // Appends an assert operation — handler returns computed value, predicate evaluates it
    // realizes: Core Semantics > Procedures > ASSERT
    public function assert(string $label, mixed $fn, mixed $predicate): static { ... }

    // :: STRING, MIXED -> $this
    // Appends a chain operation — runs sub-pipeline against same state
    // realizes: Core Semantics > Procedures > CHAIN
    public function chain(string $label, mixed $pipeline): static { ... }

    // :: STRING, MIXED -> $this
    // Appends a skip directive — gates a target operation by label
    // realizes: Core Semantics > Procedures > SKIP
    public function skip(string $targetLabel, mixed $condition = null): static { ... }

    // :: MIXED, ?ARRAY -> ctgTestState
    // Validates and executes the pipeline, returns final state
    // realizes: Core Semantics > Procedures > START
    public function start(mixed $subject, array $config = []): CTGTestState { ... }
```

#### Accessor Methods

```php
    // :: VOID -> STRING
    public function getLabel(): string { ... }
```

**No `getSteps()` or `getOperations()` method.** The design doc v2.2 says the pipeline is "the only producer and consumer of its internal operation list; no external code creates, inspects, or extends individual operation representations." The internal operation list is not exposed.

#### Static Factory

```php
    // Static Factory Method :: STRING -> ctgTest
    public static function init(string $label): static { ... }
```

> **Judgment Call — `$_operations` not `$_steps`:** With STEP removed as a primitive, the internal storage array is named `$_operations` to reflect the design doc's terminology. This is an internal name — it does not appear in any public signature.

> **Judgment Call — internal operation representation:** The pipeline stores operations as an array of tagged arrays (e.g., `['type' => 'stage', 'label' => $label, 'fn' => $fn]`). This is a private implementation detail. Alternative representations (closures, private value objects, parallel arrays) are equally valid. The choice of tagged arrays is made for simplicity and debuggability during development. No external code depends on this shape.

> **Judgment Call — no getSteps()/getOperations():** The design doc v2.2 explicitly says no external code inspects individual operation representations. Removing the accessor enforces this. Internal test coverage can use reflection if needed; production code has no reason to inspect the operation list.

> **Judgment Call — `stage` handler receives STATE, not subject:** The design doc signatures show `STAGE :: STRING:label, (STATE -> *:subject)`. The handler receives the full STATE object and returns the new subject value. This differs from v1 where the handler received the raw subject. Receiving STATE gives stage handlers access to `state.computed` and `state.results` if needed, which is important for sophisticated stage logic. The pipeline writes the return value to `state.subject`.

> **Judgment Call — `assert` handler receives STATE, not subject:** Same reasoning. `ASSERT :: STRING:label, (STATE -> *:computed)`. The handler receives STATE and returns the computed value. The pipeline writes it to `state.computed`, then calls `predicate.evaluate(state.computed)`.

> **Judgment Call — builder methods accept `mixed`, not type-hinted args:** The design doc requires that all validation errors surface as canonical framework errors (`INVALID_OPERATION`, `INVALID_CHAIN`, `INVALID_EXPECTED_OUTCOME`), thrown during validation in `start()`. If builder methods type-hinted their arguments (e.g., `CTGTestPredicate $predicate`), PHP would throw a native `TypeError` at the call site before `start()` is ever reached, bypassing the canonical error contract. To ensure all validation is deferred and all errors are canonical, builder methods accept `mixed` for arguments that require validation (fn, predicate, pipeline target). Validation in `start()` checks types and throws the appropriate framework error with structured diagnostic data.

> **Judgment Call — `skip` has NO label of its own:** The design doc v2.2 signature is `SKIP :: STRING:targetLabel, (STATE -> BOOL):condition?`. A skip is identified by the target it gates, not by its own label. This is a change from v2 where skip had its own label. The `skip()` method takes one string argument (the target label) and an optional condition closure.

> **Judgment Call — `skip` condition receives STATE, not subject:** Consistent with stage/assert — the design doc shows `STATE -> BOOL` for the condition function.

---

### 2.6 CTGTestError

```
realizes: Error Semantics > The Framework Error Class
realizes: Error Semantics > Canonical Error Types
```

```php
namespace CTG\Test;

class CTGTestError extends \Exception {

    /* Constants */

    // Canonical validation errors (1xxx)
    public const INVALID_OPERATION         = 1000;
    public const INVALID_CHAIN             = 1001;
    public const INVALID_CONFIG            = 1002;
    public const INVALID_EXPECTED_OUTCOME  = 1003;
    public const INVALID_SKIP              = 1004;

    // Canonical runtime errors (2xxx)
    public const FORMATTER_ERROR           = 2000;
    public const RUNNER_ERROR              = 2001;

    // Structural enforcement errors (1100-1199) — non-canonical, implementation-defined
    public const CHAIN_DEPTH_EXCEEDED      = 1100;

    // Bidirectional type map: name <=> code
    public const TYPES = [
        'INVALID_OPERATION'        => 1000,
        'INVALID_CHAIN'            => 1001,
        'INVALID_CONFIG'           => 1002,
        'INVALID_EXPECTED_OUTCOME' => 1003,
        'INVALID_SKIP'             => 1004,
        'FORMATTER_ERROR'          => 2000,
        'RUNNER_ERROR'             => 2001,
        'CHAIN_DEPTH_EXCEEDED'     => 1100,
    ];

    /* Instance Properties */
    public readonly string $type;
    public readonly string $msg;
    public readonly mixed $data;

    // CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> ctgTestError
    public function __construct(string|int $type, ?string $message = null, mixed $data = null) { ... }

    // :: STRING|INT -> STRING|INT
    // Bidirectional lookup: name -> code, or code -> name
    public static function lookup(string|int $value): string|int { ... }
}
```

**Changes from v2:**
- `INVALID_STEP` renamed to `INVALID_OPERATION` to match the design doc v2.2's canonical name.
- Code stays 1000.
- `INVALID_OPERATION` covers shared structural requirements for labeled operations (STAGE, ASSERT, CHAIN): empty label, non-callable handler, duplicate label.
- SKIP is a directive, not a labeled operation — skip problems are `INVALID_SKIP` only.

> **Judgment Call — `$data` as `mixed`:** v1 used `?array`. The design doc says the data field's "shape is not prescribed." Using `mixed` is more faithful. Callers can still pass arrays (and typically will), but the type system does not restrict it.

---

### 2.7 CTGTestFormatterInterface

```
realizes: Format Semantics > The Formatter Contract
```

```php
namespace CTG\Test\Formatters;

interface CTGTestFormatterInterface {

    // :: ctgTestState -> STRING
    // Transforms a final state into a formatted string representation
    public static function format(\CTG\Test\CTGTestState $state): string;
}
```

**Changes from v1:**
- Input is `CTGTestState`, not `array $report`. The design doc says `FORMATTER :: STATE -> OUTPUT`.
- Config is not passed to the formatter. The design doc says formatters consume STATE; config is a pipeline concern, not a formatter concern.
- Return type is `string`. The design doc says OUTPUT is "typically a STRING." JSON and XML formatters return strings too (serialized JSON/XML).

> **Judgment Call — static method, not instance method:** v1 used a static `format()` method. This is a reasonable PHP idiom for stateless transformers. Formatters that need configuration (e.g., indentation width, color mode) can use a static factory that returns a configured instance, but the interface itself is stateless.

> **Judgment Call — no config parameter:** v1 passed `array $config` to formatters. The design doc says the formatter receives STATE only. Formatter-specific configuration (like whether to show traces) is the formatter's own concern — it can accept those via its own constructor or factory, not through the framework's config object.

---

## 3. Method Signatures (Complete)

All signatures use HM-like notation per the PHP code style guide.

### CTGTestStatus

```
// (enum — no methods beyond PHP enum defaults)
// ->value :: STRING   (backed enum accessor: 'PASS', 'FAIL', 'ERROR')
```

### CTGTestState

```
// CONSTRUCTOR :: STRING, MIXED -> ctgTestState
// :: VOID -> STRING                         getLabel()
// :: VOID -> MIXED                          getSubject()
// :: MIXED -> VOID                          setSubject(mixed $subject)
// :: VOID -> MIXED                          getComputed()
// :: MIXED -> VOID                          setComputed(mixed $computed)
// :: VOID -> [ctgTestResult]                getResults()
// :: ctgTestResult -> VOID                  addResult(CTGTestResult $result)
// Static Factory Method :: STRING, MIXED -> ctgTestState   init(string $label, mixed $subject)
```

### CTGTestResult

```
// CONSTRUCTOR :: [STRING], BOOL, ?ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult
//   (private — use factory methods)

// Static Factory Method :: [STRING], ctgTestStatus, ?\Throwable -> ctgTestResult
//   stageResult(array $label, CTGTestStatus $status, ?\Throwable $error = null)

// Static Factory Method :: [STRING], ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult
//   assertResult(array $label, CTGTestStatus $status, mixed $computedValue, mixed $expectedOutcome, ?\Throwable $error = null)

// Static Factory Method :: [STRING] -> ctgTestResult
//   skippedResult(array $label)
```

### CTGTestPredicate

```
// CONSTRUCTOR :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate
//   (private — use init)
// :: VOID -> MIXED                          getExpectedOutcome()
// :: MIXED -> BOOL                          evaluate(mixed $value)
// Static Factory Method :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate
//   init(mixed $expectedOutcome, \Closure $evaluate)
```

### CTGTest

```
// CONSTRUCTOR :: STRING -> ctgTest
//   (private — use init)
// :: STRING, MIXED -> $this                                                stage(...)
// :: STRING, MIXED, MIXED -> $this                                         assert(...)
// :: STRING, MIXED -> $this                                                chain(...)
// :: STRING, MIXED -> $this                                                skip(...)
// :: MIXED, ?ARRAY -> ctgTestState                                         start(...)
// :: VOID -> STRING                                                        getLabel()
// Static Factory Method :: STRING -> ctgTest                                init(string $label)
```

### CTGTestError

```
// CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> ctgTestError
// :: STRING|INT -> STRING|INT               lookup(string|int $value)
```

### CTGTestFormatterInterface

```
// :: ctgTestState -> STRING                 format(CTGTestState $state)
```

---

## 4. Resolution of Deferred Decisions

### 4.1 Concrete Class Hierarchies

```
realizes: Left to Language-Specific Specs > Concrete class hierarchies
```

- `CTGTestState` — concrete class, non-final. Domain extensions subclass it to add fields (e.g., a browser test state with a `$_page` field).
- `CTGTestPredicate` — concrete class, non-final. Convenience builders use the factory; domain extensions may subclass for custom predicate types.
- `CTGTestResult` — concrete class, non-final. Domain extensions may subclass to add fields (e.g., `duration_ms`, `screenshot`), but must preserve the six canonical fields and their semantics.
- `CTGTest` — concrete class, non-final. Domain extensions subclass to add builder methods (e.g., `navigate()`, `query()`).
- `CTGTestStatus` — backed string enum, inherently final.
- `CTGTestError` — concrete class extending `\Exception`, non-final.

**No CTGTestStep class.** Operations are stored internally by the pipeline. There is no public class for individual operations.

> **Judgment Call — no class is `final`:** The design doc's extension model (Core Concepts section 5) relies on subclassing for domain-specific state types, predicate types, and pipeline types. Making any core class final would close the extension surface.

### 4.2 Constructor Shapes and Factory Methods

```
realizes: Left to Language-Specific Specs > Constructor shapes, factory methods
```

All constructors are `private`. Public entry points are static factory methods named `init()` that return `static` (not `self`) to support subclass construction.

Exception: `CTGTestError` has a `public` constructor because exceptions are commonly instantiated with `throw new CTGTestError(...)` and the `new` keyword is the PHP idiom for exception construction.

Exception: `CTGTestResult` uses named factory methods (`stageResult`, `assertResult`, `skippedResult`) instead of `init` because the construction shape varies by result kind. There is no single `init` — each factory enforces the correct field combinations for its result type.

### 4.3 Validation Rule Implementations

```
realizes: Left to Language-Specific Specs > Validation rule implementations
realizes: Error Semantics > When Framework Errors Are Thrown
```

All validation runs inside `start()`, before any operation executes. Validation is a two-phase process:

**Phase 1 — Config validation** (runs first):

| Condition | Error | Data |
|---|---|---|
| Config contains unknown key | `INVALID_CONFIG` (1002) | `['key' => $key]` |
| `haltOnFailure` is not bool | `INVALID_CONFIG` (1002) | `['key' => 'haltOnFailure', 'value' => $value, 'expected' => 'bool']` |
| `timeout` is not int | `INVALID_CONFIG` (1002) | `['key' => 'timeout', 'value' => $value, 'expected' => 'int']` |
| `timeout` is negative | `INVALID_CONFIG` (1002) | `['key' => 'timeout', 'value' => $value, 'constraint' => '>= 0']` |

**Phase 2 — Pipeline validation** (runs after config validation):

| Condition | Error | Data |
|---|---|---|
| Pipeline label is empty after trim | `INVALID_OPERATION` (1000) | `['label' => '']` |
| Operation label is empty after trim | `INVALID_OPERATION` (1000) | `['label' => '', 'operation_index' => $i]` |
| Duplicate operation label in same pipeline | `INVALID_OPERATION` (1000) | `['label' => $label, 'first_index' => $first, 'duplicate_index' => $i]` |
| Stage/assert fn is not a \Closure | `INVALID_OPERATION` (1000) | `['label' => $label, 'got' => gettype($fn)]` |
| Assert predicate is not CTGTestPredicate | `INVALID_EXPECTED_OUTCOME` (1003) | `['label' => $label, 'got' => gettype($pred)]` |
| Assert predicate is a callable (bare closure/function) | `INVALID_EXPECTED_OUTCOME` (1003) | `['label' => $label, 'got' => 'callable', 'hint' => 'Use CTGTestPredicate::init() to wrap']` |
| Chain target is not CTGTest | `INVALID_CHAIN` (1001) | `['label' => $label, 'got' => gettype($target)]` |
| Skip target label is empty | `INVALID_SKIP` (1004) | `['targetLabel' => '']` |
| Skip target not found in pipeline | `INVALID_SKIP` (1004) | `['targetLabel' => $target, 'available' => $labels]` |
| Duplicate skip targeting same operation | `INVALID_SKIP` (1004) | `['targetLabel' => $target]` |
| Skip condition is not null and not \Closure | `INVALID_SKIP` (1004) | `['targetLabel' => $target, 'got' => gettype($cond)]` |
| Chain depth exceeds MAX_CHAIN_DEPTH | `CHAIN_DEPTH_EXCEEDED` (1100) | `['label' => $label, 'depth' => $depth, 'max' => MAX_CHAIN_DEPTH]` |

**Key changes from v2 validation:**
- All `INVALID_STEP` references are now `INVALID_OPERATION` (1000).
- Skip validation data uses `targetLabel` instead of `label`, since skips no longer have their own label.
- Label uniqueness is enforced across operations (stages, asserts, chains) only — skips are not in the namespace and do not participate in uniqueness checks.

> **Judgment Call — all type validation deferred to `start()`:** Builder methods accept `mixed` for fn, predicate, and pipeline arguments. All type checking happens during pipeline validation in `start()`, which throws canonical framework errors with structured data. This ensures: (a) the design doc's canonical error codes are always used, (b) error messages include diagnostic context (label, expected type, actual type), and (c) validation of chained sub-pipelines works uniformly since the validation walker checks all operations recursively.

> **Judgment Call — no skip ordering constraint:** Since all operations are recorded during the builder phase and nothing executes until `start()`, the pipeline has the complete operation list before execution begins. Skip directives are collected into a lookup map keyed by target label. During execution, when the pipeline reaches an operation, it checks the map and evaluates the skip condition against current state at that moment. This means skip conditions can react to state changes from earlier operations (e.g., "skip this assert if the subject is null"), while the skip directive itself can appear at any position in the builder sequence. The only validation constraints are: the target must exist, no duplicate skips for the same target, and the condition (if any) must be callable.

### 4.4 Config Validation Details

```
realizes: Left to Language-Specific Specs > Config object validation details
```

CONFIG is an associative array with exactly two recognized keys:

| Key | Type | Default | Valid Range |
|---|---|---|---|
| `haltOnFailure` | `bool` | `true` | `true` or `false` |
| `timeout` | `int` | `5000` | `>= 0` (0 disables timeout) |

- Unknown keys throw `INVALID_CONFIG`.
- Wrong-typed values throw `INVALID_CONFIG`.
- Negative timeout throws `INVALID_CONFIG`.
- An empty config array `[]` is valid and uses all defaults.
- Omitting the config argument entirely is valid and uses all defaults.

> **Judgment Call — no `output`, `strict`, `trace`, `formatter`, `debug` keys:** v1 had these config keys. v2+ drops them all. The design doc says CONFIG has exactly two keys. Output/formatting is a caller concern; strict comparison is owned by predicates; trace/debug are implementation refinements outside the canonical config shape.

### 4.5 Execution Envelope — Timeout

```
realizes: Left to Language-Specific Specs > Execution envelope details
```

**Cancellation model: cooperative, alarm-based.**

PHP is single-threaded and does not support preemptive cancellation of userland code. The timeout mechanism uses `pcntl_alarm()` and `pcntl_signal()` where the `pcntl` extension is available:

1. Before each operation executes, if timeout > 0 and `pcntl` is loaded, set an alarm for `ceil(timeout / 1000)` seconds (pcntl_alarm works in whole seconds).
2. The signal handler sets a flag. After the operation's closure returns, the pipeline checks the flag.
3. If the flag is set (timeout exceeded), the operation's return value is NOT applied to state — `state.subject`, `state.computed` remain unchanged. A result with `status: ERROR` and a framework-generated `\RuntimeException('Operation timed out after {timeout}ms')` is recorded.
4. The alarm is cancelled after the operation completes (or after the flag is checked).

**When `pcntl` is not available** (Windows, some shared hosting):

Timeout enforcement is best-effort. The pipeline records `hrtime(true)` before each operation and checks elapsed time after the operation returns. If the operation exceeded the timeout, the same timeout-exceeded handling applies (return value not applied, ERROR result recorded). This cannot interrupt a long-running operation, but it prevents the operation's effect from being applied.

> ⚠️ **OPERATIONAL RISK — post-hoc timeout detection is NOT hard interruption.**
>
> When the `pcntl` extension is unavailable, timeout is detected **only
> after** the operation's closure returns control to the pipeline. A
> truly blocking or infinite operation — an unbounded `while` loop, a
> network call with no socket timeout, a DB query with no query
> timeout, a fork bomb — **will hang the pipeline indefinitely**. The
> configured `timeout` value has no effect until control returns.
>
> This is a fundamental consequence of PHP's cooperative execution
> model in environments without signal-based cancellation. The
> framework cannot forcibly terminate userland code; it can only
> observe that the code took too long after the fact.
>
> **Mitigations callers should apply:**
> - Set low-level timeouts on any I/O the operation performs
>   (`curl_setopt(..., CURLOPT_TIMEOUT)`, `stream_set_timeout`,
>   `PDO::ATTR_TIMEOUT`, etc.)
> - Avoid unbounded loops inside operation handlers
> - If running in CI or a process-supervised environment, configure
>   an outer process-level timeout (e.g., `timeout` command, CI job
>   timeout) as a hard backstop
>
> The framework's timeout config is a **budget observer**, not a
> **budget enforcer** in environments without `pcntl`. Treat it as a
> sanity check for accidentally slow operations, not as a guarantee
> that runaway code will be stopped.

**Timeout precedence when an operation both throws and exceeds budget:**

If an operation's handler (or an assert's predicate) throws an
exception AND the elapsed time exceeds the configured timeout, the
framework records the **timeout error**, not the user's thrown
exception. The rationale: the operation violated the framework's
budget contract first; the user's exception is secondary. The
thrown exception is discarded. This ensures a consistent rule —
"did the operation fit in its budget?" — rather than a case split
on which failure mode to prefer.

**Timeout value of 0:**

Disables timeout enforcement entirely. No alarm is set, no elapsed-time check is performed.

**Timeout rollback scope:**

After an operation exceeds its timeout, the framework guarantees `state.subject` and `state.computed` are unchanged from before the operation ran. The pipeline snapshots these two slots before execution and restores them on timeout.

This guarantee does NOT extend to:
- Extension-defined state fields (fields added by CTGTestState subclasses)
- External side effects (file writes, database mutations, network calls)
- Mutations to shared mutable objects that the operation's handler may have performed before the timeout fired

Extensions that need rollback protection for their own state fields must implement their own transactional mechanism (snapshot/restore, copy-on-write, etc.) and document it in their extension spec.

> **Judgment Call — `pcntl_alarm` granularity:** `pcntl_alarm()` works in whole seconds, not milliseconds. A timeout of 500ms would round up to 1 second. This is a known limitation of PHP's process control API. The `hrtime`-based fallback provides millisecond-accurate post-hoc detection. The framework documents this: alarm-based interruption has second-level granularity; elapsed-time detection has millisecond accuracy.

> **Judgment Call — no snapshotState()/restoreState() for extensions:** The design doc v2.2 is explicit: timeout rollback is scoped to framework-owned slots only (`state.subject` and `state.computed`). The v2 spec's language about subclasses overriding `snapshotState()`/`restoreState()` is removed. Extensions wanting rollback must build their own mechanism independently.

### 4.6 Synchronous Realization

```
realizes: Left to Language-Specific Specs > Synchronous vs asynchronous realization
```

PHP is synchronous. All function arrows are direct calls. There is no async machinery, no promises, no awaiting. An operation's closure is called, it returns, the pipeline proceeds. This is the simplest conforming realization.

### 4.7 Host-Language Ergonomics

```
realizes: Left to Language-Specific Specs > Host-language ergonomics
```

- **Fluent builder:** `stage()`, `assert()`, `chain()`, `skip()` return `$this` (typed as `static` for subclass support).
- **Arrow functions:** PHP arrow functions (`fn($state) => ...`) are idiomatic for short handlers.
- **Named arguments:** PHP 8.0+ named arguments work with all methods, especially useful for `CTGTestPredicate::init(expectedOutcome: ..., evaluate: ...)`.

### 4.8 Module Structure / File Layout

```
realizes: Left to Language-Specific Specs > Module structure
```

```
src/
    CTGTest.php                    # Pipeline builder/executor
    CTGTestState.php               # State carrier
    CTGTestResult.php              # Result value object
    CTGTestPredicate.php           # Predicate type
    CTGTestStatus.php              # Status enum
    CTGTestError.php               # Framework error class
    Predicates/
        CTGTestPredicates.php      # Convenience predicate builders (static methods)
    Formatters/
        CTGTestFormatterInterface.php   # Formatter contract
        CTGTestTextFormatter.php        # Reference text formatter
```

**No CTGTestStep.php.** The file is removed from the layout. Operations are stored internally by CTGTest.

All classes in `CTG\Test` namespace. Formatters in `CTG\Test\Formatters`. Predicates in `CTG\Test\Predicates`.

### 4.9 Conformance Verification

```
realizes: Left to Language-Specific Specs > Conformance verification
```

Tests are written using PHPUnit as an independent oracle. The
framework runs pipelines and returns STATE; PHPUnit asserts against
the STATE object using its own proven assertion infrastructure. This
avoids the bootstrapping problem of self-testing (where a bug in
result recording would be invisible to tests that use the same
result recording machinery).

PHPUnit is a dev-only dependency (`composer require --dev phpunit/phpunit`).
It does not ship with the library — zero production dependencies are
preserved.

Each design doc requirement maps to one or more PHPUnit test methods.
The test file naming follows PHPUnit conventions:

```
tests/
    CTGTestStateTest.php
    CTGTestPredicateTest.php
    CTGTestResultTest.php
    CTGTestPipelineStageTest.php
    CTGTestPipelineAssertTest.php
    CTGTestPipelineChainTest.php
    CTGTestPipelineSkipTest.php
    CTGTestPipelineConfigTest.php
    CTGTestPipelineTimeoutTest.php
    CTGTestErrorTest.php
    CTGTestTextFormatterTest.php
    CTGTestPredicatesConvenienceTest.php
```

---

## 5. Concrete Error Class

See section 2.6 (`CTGTestError`).

Canonical error types with codes:

| Constant | Code | Design Doc Name |
|---|---|---|
| `INVALID_OPERATION` | 1000 | `INVALID_OPERATION` |
| `INVALID_CHAIN` | 1001 | `INVALID_CHAIN` |
| `INVALID_CONFIG` | 1002 | `INVALID_CONFIG` |
| `INVALID_EXPECTED_OUTCOME` | 1003 | `INVALID_EXPECTED_OUTCOME` |
| `INVALID_SKIP` | 1004 | `INVALID_SKIP` |
| `FORMATTER_ERROR` | 2000 | `FORMATTER_ERROR` |
| `RUNNER_ERROR` | 2001 | `RUNNER_ERROR` |
| `CHAIN_DEPTH_EXCEEDED` | 1100 | *(non-canonical, structural enforcement)* |

Bidirectional lookup:
```php
CTGTestError::lookup('INVALID_OPERATION');     // => 1000
CTGTestError::lookup(1000);                    // => 'INVALID_OPERATION'
CTGTestError::lookup('CHAIN_DEPTH_EXCEEDED');  // => 1100
CTGTestError::lookup(1100);                    // => 'CHAIN_DEPTH_EXCEEDED'
```

---

## 6. Concrete Formatter — CTGTestTextFormatter

```
realizes: Format Semantics > The Formatter Contract
```

```php
namespace CTG\Test\Formatters;

class CTGTestTextFormatter implements CTGTestFormatterInterface {

    // :: ctgTestState -> STRING
    public static function format(\CTG\Test\CTGTestState $state): string { ... }
}
```

### Output Format

The reference text formatter produces output in this exact format:

```
Pipeline: {pipeline_label}

  [PASS]    load cart
  [PASS]    validate payment > check card
  [PASS]    validate payment > verify auth
  [FAIL]    complete order
              computed: 'pending'
              expected: 'complete'
  [ERROR]   finalize
              error: RuntimeException: Connection refused
  [SKIPPED] optional cleanup

---
3 passed, 1 failed, 1 skipped, 1 errored (6 total)
Result: FAIL
```

**Format rules:**

1. First line: `Pipeline: {label}` where label is `$state->getLabel()`.
2. Blank line.
3. One line per result in `state.results`, indented 2 spaces:
   - Status in brackets: `[PASS]`, `[FAIL]`, `[ERROR]`, `[SKIPPED]`
   - Padded to 10 chars (brackets + status + spaces)
   - Label path joined with ` > ` (the label array elements joined)
4. For FAIL results, two indented detail lines:
   - `computed: {formatted_value}`
   - `expected: {formatted_value}`
5. For ERROR results, one indented detail line:
   - `error: {exception_class}: {exception_message}`
6. For SKIPPED results, no detail lines.
7. Blank line, `---` separator, blank line.
8. Summary line: `{n} passed, {n} failed, {n} skipped, {n} errored ({total} total)`
9. Result line: `Result: {worst_status}` where worst status is ERROR > FAIL > PASS, or `PASS` if all passed, or `VOID` if all skipped.

**Value formatting** for computed/expected display:
- `null` -> `null`
- `true`/`false` -> `true`/`false`
- int/float -> string representation
- string -> `'{escaped_value}'`
- array -> `array({count})`
- object -> `object({class_name})`
- resource -> `resource({type})`

**Result trace rule (affects formatter output):**
- Every stage and assert produces a result entry (with `skipped: true` if bypassed).
- A chain that executes normally produces no entry of its own — only its child results appear (with the chain label prepended).
- A chain that is skipped produces a single `skipped: true` entry.
- Skip directives never appear in the result trace.

The formatter renders exactly what is in `state.results`. It does not need special logic for chains vs. stages vs. skips — the pipeline has already applied the result trace rule before the formatter sees the results.

> **Judgment Call — `>` as label separator:** The design doc says "labels themselves may contain any characters; the framework never joins them at the semantic level." The formatter joins them for display; this is a formatter concern, not a semantic one. ` > ` is visually clear and unlikely to collide with label content. Formatters that need different separators write their own.

> **Judgment Call — renamed from CTGTestConsoleFormatter:** v1 used "console" naming. v2+ uses "text" because the formatter produces a string — it does not write to any console. The caller writes to stdout if it wants to.

---

## 7. Convenience Builders — CTGTestPredicates

```
realizes: Core Concepts > 4. Assert Is the Only Correctness Primitive
realizes: Left to Language-Specific Specs > Convenience builders
```

```php
namespace CTG\Test\Predicates;

class CTGTestPredicates {

    // :: MIXED -> ctgTestPredicate
    // Strict equality (===) against expected value
    public static function equals(mixed $expected): \CTG\Test\CTGTestPredicate { ... }

    // :: MIXED -> ctgTestPredicate
    // Strict inequality (!==) against expected value
    public static function notEquals(mixed $expected): \CTG\Test\CTGTestPredicate { ... }

    // :: VOID -> ctgTestPredicate
    // Value is null
    public static function isNull(): \CTG\Test\CTGTestPredicate { ... }

    // :: VOID -> ctgTestPredicate
    // Value is not null
    public static function isNotNull(): \CTG\Test\CTGTestPredicate { ... }

    // :: VOID -> ctgTestPredicate
    // Value is truthy (equivalent to (bool)$value === true)
    public static function isTruthy(): \CTG\Test\CTGTestPredicate { ... }

    // :: VOID -> ctgTestPredicate
    // Value is falsy (equivalent to (bool)$value === false)
    public static function isFalsy(): \CTG\Test\CTGTestPredicate { ... }

    // :: VOID -> ctgTestPredicate
    // Value is true (=== true)
    public static function isTrue(): \CTG\Test\CTGTestPredicate { ... }

    // :: VOID -> ctgTestPredicate
    // Value is false (=== false)
    public static function isFalse(): \CTG\Test\CTGTestPredicate { ... }

    // :: STRING -> ctgTestPredicate
    // Value is an instance of the given class name
    public static function isInstanceOf(string $className): \CTG\Test\CTGTestPredicate { ... }

    // :: STRING -> ctgTestPredicate
    // Value is of the given type (uses gettype())
    public static function isType(string $type): \CTG\Test\CTGTestPredicate { ... }

    // :: MIXED -> ctgTestPredicate
    // Value is greater than expected (>)
    public static function greaterThan(mixed $expected): \CTG\Test\CTGTestPredicate { ... }

    // :: MIXED -> ctgTestPredicate
    // Value is less than expected (<)
    public static function lessThan(mixed $expected): \CTG\Test\CTGTestPredicate { ... }

    // :: STRING -> ctgTestPredicate
    // String value contains the expected substring
    public static function contains(string $expected): \CTG\Test\CTGTestPredicate { ... }

    // :: STRING -> ctgTestPredicate
    // String value matches the given regex pattern
    public static function matchesPattern(string $pattern): \CTG\Test\CTGTestPredicate { ... }

    // :: INT -> ctgTestPredicate
    // Array/Countable has the expected count
    public static function hasCount(int $expected): \CTG\Test\CTGTestPredicate { ... }

    // :: (MIXED -> BOOL) -> ctgTestPredicate
    // Custom predicate from a closure — expectedOutcome is '(custom)'
    public static function satisfies(\Closure $fn): \CTG\Test\CTGTestPredicate { ... }
}
```

Each convenience builder constructs a `CTGTestPredicate` instance with:
- An `$_expectedOutcome` that captures what the predicate is checking against (for diagnostic display)
- An `$_evaluate` closure that performs the actual check

**Example implementations:**

```php
public static function equals(mixed $expected): \CTG\Test\CTGTestPredicate {
    return \CTG\Test\CTGTestPredicate::init(
        $expected,
        fn(mixed $value): bool => $value === $expected
    );
}

public static function isNull(): \CTG\Test\CTGTestPredicate {
    return \CTG\Test\CTGTestPredicate::init(
        null,
        fn(mixed $value): bool => $value === null
    );
}

public static function contains(string $expected): \CTG\Test\CTGTestPredicate {
    return \CTG\Test\CTGTestPredicate::init(
        $expected,
        fn(mixed $value): bool => is_string($value) && str_contains($value, $expected)
    );
}

public static function satisfies(\Closure $fn): \CTG\Test\CTGTestPredicate {
    return \CTG\Test\CTGTestPredicate::init(
        '(custom)',
        $fn
    );
}
```

> **Judgment Call — all predicates use strict comparison:** v1 had a `strict` config option. v2+ drops loose comparison entirely. `equals()` uses `===`. There is no `looseEquals()`. Rationale: the design doc has no notion of comparison modes; predicates own their own comparison semantics. If a user needs loose comparison, they use `satisfies(fn($v) => $v == $expected)`.

> **Judgment Call — `satisfies()` uses `'(custom)'` as expectedOutcome:** When a user provides an arbitrary closure, there's no meaningful expected value to display. The string `'(custom)'` signals to formatters that the predicate is user-defined and the expected outcome is not a literal value.

---

## 8. Anti-Pattern Enforcement

```
realizes: Constraints > Anti-Patterns
```

Each anti-pattern from the design doc is explicitly **not provided** in v2.2:

| Anti-Pattern | Design Doc Reason | Enforcement in v2.2 |
|---|---|---|
| Static result accumulator | Causes leakage between pipelines | No static result storage. `CTGTestState` is instance-scoped. No static `$_results` anywhere. |
| Static config singleton | Config is per-invocation | No static `$_config`. No `CTGTest::setCliConfig()` (v1 had this; v2+ removes it). Config is passed to `start()` only. |
| `collector` / `publishResult` config keys | Caller concern | CONFIG accepts only `haltOnFailure` and `timeout`. Unknown keys throw `INVALID_CONFIG`. |
| `output` / `formatter` config keys | Caller concern | Not in CONFIG. v1 had `output` and `formatter` keys; v2+ removes them. |
| Pipeline-owned delivery (stdout) | Pipeline returns state | `start()` returns `CTGTestState`. It never calls `echo`. The caller decides what to do with the state. |
| Built-in generic test runner | Single-collector runner cannot compose | No runner class. No test discovery. No static `run()` method. Callers write their own runners. |
| Pipeline-owned subject snapshot/debug | Observation concern | No `debug` config key. No `$_snapshotSubject()`. No `'subject'` key on results. |
| Pipeline-level `compare` hook | Predicate concern | No `compare()` method on CTGTest. No `strict` config key. Comparison is exclusively owned by `CTGTestPredicate::evaluate()`. |

---

## 9. Test Target

Tests live in `tests/` at the project root. PHPUnit is the test
runner — an independent oracle that asserts against the framework's
output without depending on the framework's own correctness.

**Running tests:**

```bash
# Run all tests
./vendor/bin/phpunit

# Or via Makefile
make test
```

**PHPUnit configuration** (`phpunit.xml` at project root):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="ctg-php-test">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**Test pattern:** Each test method creates a pipeline, runs it via
`start()`, and uses PHPUnit assertions to verify the returned
`CTGTestState` — its results, statuses, computed values, expected
outcomes, and error fields.

**Stage example:**

```php
<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineStageTest extends TestCase
{
    public function testStageTransformsSubject(): void
    {
        $state = CTGTest::init('stage test')
            ->stage('double it', fn(CTGTestState $s) => $s->getSubject() * 2)
            ->assert('is doubled', fn(CTGTestState $s) => $s->getSubject(), CTGTestPredicates::equals(10))
            ->start(5);

        $results = $state->getResults();
        $this->assertCount(2, $results);
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status);
        $this->assertSame(CTGTestStatus::PASS, $results[1]->_status);
        $this->assertSame(10, $results[1]->_computedValue);
        $this->assertSame(10, $results[1]->_expectedOutcome);
    }
}
```

**Skip example (v2.2 signature — no skip label):**

```php
public function testConditionalSkip(): void
{
    $state = CTGTest::init('conditional skip')
        ->stage('setup', fn(CTGTestState $s) => ['ready' => false])
        ->skip('check readiness', fn(CTGTestState $s) => $s->getSubject()['ready'] === false)
        ->assert('check readiness', fn(CTGTestState $s) => $s->getSubject()['ready'], CTGTestPredicates::isTrue())
        ->start(null);

    $results = $state->getResults();
    $this->assertSame(CTGTestStatus::PASS, $results[0]->_status);
    $this->assertTrue($results[1]->_skipped);
    $this->assertNull($results[1]->_status);
}
```

---

## 10. Judgment Calls Index

All judgment calls are annotated inline with `> **Judgment Call —** ...` blocks throughout this spec. This section indexes them for reference:

1. **STATE as class, not array** (section 1) — typed fields prevent typo bugs, makes slot-deposit explicit.
2. **CONFIG as array, not class** (section 1) — only two keys, no behavior, class adds ceremony without value.
3. **STATUS as backed enum** (section 1) — PHP 8.1+ enums are the natural realization of a closed set.
4. **STEP removed from realization map** (section 1) — design doc v2.2 removes STEP as a primitive; pipeline stores operations internally.
5. **Label trimming** (section 1) — `trim()` applied to all labels; empty-after-trim caught during validation.
6. **UPPERCASE backing values** (section 2.1) — aligns with design doc convention, breaks from v1 lowercase.
7. **Mutable setters on STATE** (section 2.2) — slot-deposit requires mutation; immutable copies break shared state.
8. **addResult takes CTGTestResult** (section 2.2) — typed results enforce structural correctness.
9. **Readonly public properties with underscore prefix** (section 2.3) — lightweight read access, private construction.
10. **No `type` field on RESULT** (section 2.3) — design doc omits it; v1 break is intentional.
11. **No `duration_ms` on RESULT** (section 2.3) — design doc omits it; available as extension.
12. **No `message` on RESULT** (section 2.3) — diagnostics live in `error` field.
13. **\Closure not callable for evaluate** (section 2.4) — narrower type is safer.
14. **CTGTestPredicate not final** (section 2.4) — preserves extension surface.
15. **`$_operations` not `$_steps`** (section 2.5) — internal name reflects design doc terminology change.
16. **Internal operation representation** (section 2.5) — tagged arrays chosen for simplicity; representation is private.
17. **No getSteps()/getOperations()** (section 2.5) — design doc says no external inspection of operation list.
18. **Stage/assert handlers receive STATE** (section 2.5) — design doc signatures show `STATE -> *`.
19. **Builder methods accept mixed, not type-hinted** (section 2.5) — ensures canonical framework errors, not native TypeError.
20. **Skip has NO label of its own** (section 2.5) — design doc v2.2 signature: `SKIP :: STRING:targetLabel, condition?`.
21. **Skip condition receives STATE** (section 2.5) — consistent with stage/assert.
22. **`$data` as mixed** (section 2.6) — design doc says shape is not prescribed.
23. **No config parameter on formatter** (section 2.7) — design doc says formatters receive STATE only.
24. **No class is final** (section 4.1) — extension model relies on subclassing.
25. **No skip ordering constraint** (section 4.3) — pipeline has full operation list before execution; skip position is irrelevant.
26. **All type validation deferred to start()** (section 4.3) — canonical errors with structured data, uniform recursive validation.
27. **No v1 config keys** (section 4.4) — design doc has exactly two keys.
28. **pcntl_alarm for timeout** (section 4.5) — best available PHP mechanism, second-level granularity.
29. **No snapshotState()/restoreState() for extensions** (section 4.5) — v2.2 scopes timeout rollback to framework-owned slots only.
30. **`>` as label separator in formatter** (section 6) — formatter concern, not semantic.
31. **Renamed to CTGTestTextFormatter** (section 6) — formatter produces string, not console output.
32. **Strict comparison only** (section 7) — predicates own comparison; no `strict` config.
33. **`'(custom)'` as expectedOutcome for satisfies()** (section 7) — signals user-defined predicate.
34. **PHPUnit as test runner** (sections 4.9, 9) — independent oracle avoids bootstrapping problem of self-testing; dev-only dependency preserves zero production deps.

---

## 11. Resolved Questions

These questions surfaced during the spec transform where the design doc did not provide enough information to decide. All have been resolved by owner decision.

### Q1: `state.computed` resets between operations

Reset `state.computed` to `null` before each operation executes. Each operation starts with a clean computed slot. This prevents stale computed values from leaking across operations.

### Q2: Stage result field values

Stage results have `null` for `computedValue` and `expectedOutcome` (PHP's realization of VOID). All `CTGTestResult` instances carry all six fields — there is no concept of "absent." Formatters use status and the combination of non-null fields to determine rendering.

### Q3: Skip condition throws → ERROR result for the target operation

When a skip's condition function throws, the framework records an ERROR result for the **target** operation. The skip asked "should this operation run?" and got an error instead of an answer. The target did not run, and its result reflects that with `status: ERROR` and the caught exception in `error`. The label on the ERROR result is the target operation's label, not the skip's label (skips have no label of their own in v2.2). This is consistent with how handler exceptions produce ERROR results for their operation.

### Q4: Chain failure halts outer pipeline under `haltOnFailure`

Yes. The sub-pipeline runs with the same `haltOnFailure` setting, so it halts at the failing sub-operation. That failure result is appended to the outer state's results (with the chain label prepended). The outer pipeline sees the failure and halts. Conversely, when `haltOnFailure` is false, neither the sub-pipeline nor the outer pipeline halts — every operation everywhere runs to completion.

### Q5: Label uniqueness namespace per pipeline

Label uniqueness is enforced per-pipeline across all operations (stages, asserts, and chains). Skip directives do not have labels of their own and do not participate in the uniqueness namespace. A stage cannot have the same label as an assert or chain in the same pipeline, but a skip can target any label without namespace conflict because skips are identified by their target, not by their own label.

**Change from v2:** In v2, skips had their own labels and participated in uniqueness. In v2.2, skips are labelless directives — they are identified by the target they gate. The uniqueness namespace shrinks to operations only.

### Q6: Skipped chain label array is `[$chainLabel]`

A skipped chain result has label `[$chainLabel]` (single-element array), `skipped: true`, `status: null`, all other fields `null`. The sub-pipeline never ran, so there are no child results.

### Q7: Skip directives are internal to the pipeline

Skip directives execute during the pipeline run (evaluate their condition, mark their target). They do not produce result entries of their own. Their effect is visible on the target operation's result entry (`skipped: true`). With STEP removed as a primitive, skips are part of the pipeline's internal operation list, not a public concern. There is no public method to inspect skip directives.

**Change from v2:** In v2, Q7 was about whether skips appear in `getSteps()`. In v2.2, there is no `getSteps()` — skips are internal to the pipeline's operation storage, and no external code inspects them.

---

## Appendix A: Execution Algorithm (Pseudocode)

```
function START(subject, config):
    config = resolveConfig(config)        // merge defaults, validate
    validatePipeline(this)                // validate all operations recursively

    state = CTGTestState::init(this.label, subject)

    // Build skip lookup map from all skip directives
    // Key: target label, Value: condition closure (or null for unconditional)
    // NOTE: validatePipeline() above has already rejected duplicate skip targets,
    // so each target label appears at most once — no overwrite possible.
    skipMap = {}
    for each operation in this._operations:
        if operation is a skip directive:
            skipMap[operation.targetLabel] = operation.condition

    // Execute non-skip operations in order
    for each operation in this._operations:
        if operation is a skip directive:
            continue                      // skip directives are not executed inline

        state.setComputed(null)           // reset computed slot

        // Check if this operation is targeted by a skip
        if operation.label is in skipMap:
            condition = skipMap[operation.label]
            try:
                if condition is null or condition(state) is true:
                    state.addResult(skippedResult([operation.label]))
                    continue
                // condition returned false — operation runs normally
            catch (Throwable e):
                record ERROR result for operation (using caught exception)
                if haltOnFailure: break
                continue

        // timeout protection: snapshot framework-owned slots
        snapshot = [state.subject, state.computed]

        try:
            execute operation against state   // mutates state via slot deposit
        catch (Throwable e):
            restore state.subject, state.computed from snapshot
            state.addResult(errorResult(...))
            if haltOnFailure: break
            continue

        // timeout check
        if timed out:
            restore state.subject, state.computed from snapshot
            state.addResult(errorResult(...))
            if haltOnFailure: break
            continue

        // result was recorded by the operation execution logic
        // (stage records PASS, assert records PASS/FAIL/ERROR)

        // check haltOnFailure
        lastResult = last element of state.results
        if haltOnFailure AND (lastResult.status is FAIL or ERROR):
            break

    return state
```

> **Note:** This pseudocode describes the flow. The actual implementation may differ in structure (e.g., the operation execution logic may add the result itself, or the pipeline may add it after execution). The semantic contract is what matters: operations compute, the pipeline determines, results are recorded in order.

> **Note on skip evaluation timing:** Skip conditions are evaluated when the target operation is reached during execution, not when the skip directive is encountered in the list. This means skip conditions see the current state at the point the target would execute — including any mutations from earlier operations. A skip directive can appear at any position relative to its target because the pipeline builds a skip lookup map before execution and consults it when each operation is reached.

> **Note on skip condition errors:** When a skip's condition function throws, the framework records an ERROR result with the **target's** label and the caught exception. The target operation does not execute. This ensures the result trace has exactly one entry for the target — either the ERROR from the skip condition, the skipped result, or the normal execution result — never more than one.

---

## Appendix B: Extension Surfaces

```
realizes: Core Concepts > 5. Extension Surfaces
```

The framework has three extension surfaces: **STATE**, **PREDICATE**, and **PIPELINE**.

| Surface | PHP Extension Mechanism | Example |
|---|---|---|
| STATE | Subclass `CTGTestState`, add domain fields | `CTGBrowserTestState` adds `$_page`, `$_document` |
| PREDICATE | Subclass `CTGTestPredicate` or build via `CTGTestPredicate::init()` | `CTGHttpPredicates::statusIs(200)` returns a predicate |
| PIPELINE | Subclass `CTGTest`, add builder methods | `CTGBrowserTest::navigate($url)` internally calls `$this->stage(...)` |

Extensions do NOT:
- Create or inspect individual operation representations (no public STEP type)
- Add new status values to the enum (STATUS is a closed set)
- Add keys to CONFIG (unknown keys throw INVALID_CONFIG)
- Omit or redefine the six canonical RESULT fields (subclasses may add extra fields but the canonical shape is required)

Extensions that need rollback protection for their own state fields must implement their own mechanism. The framework only guarantees rollback of `state.subject` and `state.computed` on timeout.

---

## Appendix C: v2 to v2.2 Migration Summary

| v2 Concept | v2.2 Equivalent | Breaking Change? |
|---|---|---|
| `CTGTestStep` (public class) | Removed — operations stored internally by pipeline | Yes — class no longer exists |
| `STEP` row in Realization Map | Removed | Yes — no public type for steps |
| `getSteps()` on CTGTest | Removed | Yes — no public inspection of operations |
| `$_steps` (instance property) | `$_operations` (internal, same purpose) | Internal rename only |
| `INVALID_STEP` (1000) | `INVALID_OPERATION` (1000) | Yes — constant renamed, code unchanged |
| `skip($label, $targetLabel, $condition)` | `skip($targetLabel, $condition)` | Yes — skip no longer has its own label |
| Skip labels in uniqueness namespace | Skips excluded from uniqueness | Yes — skips are labelless directives |
| `snapshotState()`/`restoreState()` for extension rollback | Removed — extensions own their rollback | Yes — no framework-provided extension rollback |
| `test_step.php` test file | Removed | Yes — no standalone step tests |
| `CTGTestStep.php` source file | Removed | Yes — file deleted from layout |
| Skip directives in `getSteps()` result | Skips internal to pipeline, not inspectable | Conceptual change — public API removed |

**Code-level migration steps:**

1. **Remove all references to `CTGTestStep`.** No imports, no type hints, no instantiation.
2. **Rename `INVALID_STEP` to `INVALID_OPERATION`** in error handling code, TYPES map, constants, and lookup tests.
3. **Update `skip()` calls** from `skip($label, $targetLabel, $condition)` to `skip($targetLabel, $condition)`.
4. **Remove any `getSteps()` calls.** No replacement — the operation list is internal.
5. **Remove extension rollback methods** (`snapshotState()`/`restoreState()` if they existed). Extensions needing rollback must implement their own.
6. **Update validation error data keys** from `step_index` to `operation_index` in any code that inspects error data.
