# ctg-php-test v2 — Language-Specific Specification

**Realizes:** `test-design-doc.v2.md` (language-agnostic design document)
**Target:** PHP >=8.1, `declare(strict_types=1)`, zero external dependencies
**Namespace:** `CTG\Test`

---

## 1. Realization Map

| Design Doc Primitive | PHP Class / Type | Notes |
|---|---|---|
| `STATE` | `CTGTestState` | First-class object with `$_subject`, `$_computed`, `$_results` |
| `STEP` | `CTGTestStep` | Abstract — never instantiated directly; concrete instances created by procedures |
| `PREDICATE` | `CTGTestPredicate` | First-class object with `$_expectedOutcome` and `evaluate()` method |
| `PIPELINE` | `CTGTest` | The public-facing pipeline builder and executor |
| `RESULT` | `CTGTestResult` | Value object; fields match design doc exactly |
| `STATUS` | `CTGTestStatus` (enum) | Backed string enum: `PASS`, `FAIL`, `ERROR` |
| `FRAMEWORK_ERROR` | `CTGTestError` | Extends `\Exception`; typed codes, bidirectional lookup |
| `CONFIG` | Associative array | `array{haltOnFailure: bool, timeout: int}` — no class needed |
| `ERROR` (primitive) | `\Throwable` | PHP's native exception interface |
| `VOID` (primitive) | `null` | PHP's native null |
| `FORMATTER` | `CTGTestFormatterInterface` | `CTGTestState -> STRING` contract |

> **Judgment Call — STATE as a class, not an array:** v1 uses scattered arrays for state threading. v2 requires `STATE` to be a first-class type per the design doc. A class gives us typed field access, prevents typo-based bugs in field names, and makes the slot-deposit model explicit. The class is mutable internally (the pipeline writes to its slots during execution) but its constructor and field access are controlled.

> **Judgment Call — CONFIG as an array, not a class:** CONFIG has exactly two keys with stable defaults and no behavior. A class would add ceremony without value. The pipeline validates the array structure and rejects unknown keys.

> **Judgment Call — STATUS as a backed enum:** PHP 8.1+ enums are the natural realization of a closed set of string values. A backed string enum gives us type safety at call sites and `->value` for serialization. The enum has exactly three cases per the design doc — `PASS`, `FAIL`, `ERROR`. `RECOVERED` is NOT a case; it is an implementation refinement outside the core status set.

> **Judgment Call — STEP label trimming:** v1 trims step labels on construction. v2 continues this: `trim()` is applied to all label strings. An empty-after-trim label is caught during validation, not construction.

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

> **Judgment Call — UPPERCASE backing values:** The design doc uses UPPERCASE for status names (`PASS`, `FAIL`, `ERROR`). The enum cases and their string values match this exactly. v1 used lowercase (`'pass'`, `'fail'`, `'error'`); v2 breaks from v1 to align with the design doc. Formatters that need lowercase can call `strtolower($status->value)`.

---

### 2.2 CTGTestState

```
realizes: Core Semantics > Primitives > STATE
```

```php
namespace CTG\Test;

class CTGTestState {

    /* Instance Properties */
    private mixed $_subject;
    private mixed $_computed;
    private array $_results;

    // CONSTRUCTOR :: MIXED -> ctgTestState
    // Creates initial state with subject; computed is null, results is []
    private function __construct(mixed $subject) { ... }

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

    // Static Factory Method :: MIXED -> ctgTestState
    public static function init(mixed $subject): static { ... }
}
```

**Initial field values:**
- `$_subject`: the value passed to `init`
- `$_computed`: `null` (VOID)
- `$_results`: `[]`

`setSubject` and `setComputed` are package-internal mutation methods. They exist so the pipeline can write to state slots; user code receives the state from `start()` and reads it.

> **Judgment Call — mutable setters instead of `withX` immutables:** The design doc's slot-deposit model requires the pipeline to mutate state in place during step execution. Immutable copies would break the shared-state threading that chains depend on. The setters are not public API surface for callers — they are pipeline-internal. PHP has no package-private visibility, so these are `public` methods on the class. The class is not `final` to allow extension by domain-specific state subclasses per the design doc's extension model (Core Concepts section 5).

> **Judgment Call — addResult takes CTGTestResult, not array:** v1 used arrays for results. v2 uses the typed CTGTestResult class. This enforces structural correctness at the type level.

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
| `$_status` | `?CTGTestStatus` | `null` when `$_skipped === true` | when step ran |
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
// Creates a skipped result (any step type)
public static function skippedResult(array $label): static { ... }
```

> **Judgment Call — readonly public properties with underscore prefix:** The underscore prefix follows the CTG code style for instance properties. Making them `public readonly` allows read access without getters, which keeps the result object lightweight. The private constructor prevents external instantiation; factory methods enforce correct field combinations.

> **Judgment Call — no `type` field:** The design doc's RESULT has no `type` field (no `'stage'`, `'assert'`, etc.). v1 had `type`; v2 drops it. A result's meaning is determined by which fields are populated (stage results have null `computedValue`/`expectedOutcome`; assert results have them populated). This is a deliberate v1-to-v2 break.

> **Judgment Call — no `duration_ms` field:** The design doc RESULT has no duration field. v1 had `duration_ms`. v2 drops it from the core result. If an implementation wants timing, it can be added as an extension field on a result subclass, but it is not part of the canonical result shape.

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

### 2.5 CTGTestStep

```
realizes: Core Semantics > Primitives > STEP
```

```php
namespace CTG\Test;

class CTGTestStep {

    /* Instance Properties */
    private readonly string $_label;
    private readonly \Closure $_execute;

    // CONSTRUCTOR :: STRING, (ctgTestState -> ctgTestState) -> ctgTestStep
    private function __construct(string $label, \Closure $execute) { ... }

    // :: VOID -> STRING
    public function getLabel(): string { ... }

    // :: ctgTestState -> ctgTestState
    // Executes the step against state, returning the (possibly mutated) state
    public function execute(CTGTestState $state): CTGTestState { ... }

    // Static Factory Method :: STRING, (ctgTestState -> ctgTestState) -> ctgTestStep
    public static function init(string $label, \Closure $execute): static { ... }
}
```

**This class is structural, not semantic.** It holds a label and an execute closure. The semantic behavior (stage transforms subject, assert computes and checks) is encoded in the closure that the pipeline's builder methods construct. Users never create `CTGTestStep` directly — they call `stage()`, `assert()`, `chain()`, `skip()` on the pipeline.

> **Judgment Call — `$_label` not `$_name`:** The design doc uses `label` for the step identifier. v1 used `name`. v2 aligns with the design doc terminology. This is a deliberate rename.

> **Judgment Call — single \Closure `execute` instead of type-dispatched fields:** v1 stored type, fn, expected, and errorHandler as separate fields and dispatched on type during execution. v2 follows the design doc's uniform `execute :: STATE -> STATE` shape — each builder method (stage, assert, chain, skip) constructs the appropriate closure at definition time. The pipeline runs all steps through the same `execute()` call with no type dispatch.

---

### 2.6 CTGTest (Pipeline)

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
    private array $_steps = [];

    // CONSTRUCTOR :: STRING -> ctgTest
    private function __construct(string $label) { ... }
```

#### Builder Methods (Fluent)

```php
    // :: STRING, (ctgTestState -> MIXED) -> $this
    // Appends a stage step — handler returns new subject value
    // realizes: Core Semantics > Procedures > STAGE
    public function stage(string $label, \Closure $fn): static { ... }

    // :: STRING, (ctgTestState -> MIXED), ctgTestPredicate -> $this
    // Appends an assert step — handler returns computed value, predicate evaluates it
    // realizes: Core Semantics > Procedures > ASSERT
    public function assert(string $label, \Closure $fn, CTGTestPredicate $predicate): static { ... }

    // :: STRING, ctgTest -> $this
    // Appends a chain step — runs sub-pipeline against same state
    // realizes: Core Semantics > Procedures > CHAIN
    public function chain(string $label, CTGTest $pipeline): static { ... }

    // :: STRING, STRING, ?(ctgTestState -> BOOL) -> $this
    // Appends a skip directive — gates a target step by label
    // realizes: Core Semantics > Procedures > SKIP
    public function skip(string $label, string $targetLabel, ?\Closure $condition = null): static { ... }

    // :: MIXED, ?ARRAY -> ctgTestState
    // Validates and executes the pipeline, returns final state
    // realizes: Core Semantics > Procedures > START
    public function start(mixed $subject, array $config = []): CTGTestState { ... }
```

#### Accessor Methods

```php
    // :: VOID -> STRING
    public function getLabel(): string { ... }

    // :: VOID -> [ctgTestStep]
    public function getSteps(): array { ... }
```

#### Static Factory

```php
    // Static Factory Method :: STRING -> ctgTest
    public static function init(string $label): static { ... }
```

> **Judgment Call — `stage` handler receives STATE, not subject:** The design doc signatures show `STAGE :: STRING:label, (STATE -> *:subject)`. The handler receives the full STATE object and returns the new subject value. This differs from v1 where the handler received the raw subject. Receiving STATE gives stage handlers access to `state.computed` and `state.results` if needed, which is important for sophisticated stage logic. The pipeline writes the return value to `state.subject`.

> **Judgment Call — `assert` handler receives STATE, not subject:** Same reasoning. `ASSERT :: STRING:label, (STATE -> *:computed)`. The handler receives STATE and returns the computed value. The pipeline writes it to `state.computed`, then calls `predicate.evaluate(state.computed)`.

> **Judgment Call — `assert` third arg is type-hinted CTGTestPredicate:** The design doc says "The third argument must be a PREDICATE instance. Raw values are not coerced or auto-wrapped." PHP's type system enforces this at the call site — passing a non-CTGTestPredicate throws a TypeError before our validation even runs. This is strictly stronger than what the design doc requires (which says to throw INVALID_EXPECTED_OUTCOME). We allow the TypeError to propagate naturally; it serves the same purpose.

> **Judgment Call — `skip` has its own label AND a targetLabel:** The design doc says `SKIP :: STRING:label, STRING:targetLabel, (STATE -> BOOL):condition?`. A skip step has its own label (for identification in the pipeline's step list) and a target label (the step it gates). v1 had skip as metadata without its own label; v2 treats it as a step with a label per the design doc.

> **Judgment Call — `skip` condition receives STATE, not subject:** Consistent with stage/assert — the design doc shows `STATE -> BOOL` for the condition function.

---

### 2.7 CTGTestError

```
realizes: Error Semantics > The Framework Error Class
realizes: Error Semantics > Canonical Error Types
```

```php
namespace CTG\Test;

class CTGTestError extends \Exception {

    /* Constants */

    // Canonical validation errors (1xxx)
    public const INVALID_STEP             = 1000;
    public const INVALID_CHAIN            = 1001;
    public const INVALID_CONFIG           = 1002;
    public const INVALID_EXPECTED_OUTCOME = 1003;
    public const INVALID_SKIP             = 1004;

    // Canonical runtime errors (2xxx)
    public const FORMATTER_ERROR          = 2000;
    public const RUNNER_ERROR             = 2001;

    // Structural enforcement errors (1100-1199) — non-canonical, implementation-defined
    public const CHAIN_DEPTH_EXCEEDED     = 1100;

    // Bidirectional type map: name <=> code
    public const TYPES = [
        'INVALID_STEP'             => 1000,
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

**Changes from v1:**
- `INVALID_EXPECTED` renamed to `INVALID_EXPECTED_OUTCOME` to match the design doc's canonical name.
- `CHAIN_DEPTH_EXCEEDED` (1100) added for optional structural enforcement, using the 1100-1199 range per design doc guidance.
- `$data` typed as `mixed` instead of `?array` — the design doc says `data: * | VOID`, which allows any structured context, not just arrays.

> **Judgment Call — `$data` as `mixed`:** v1 used `?array`. The design doc says the data field's "shape is not prescribed." Using `mixed` is more faithful. Callers can still pass arrays (and typically will), but the type system does not restrict it.

---

### 2.8 CTGTestFormatterInterface

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
// CONSTRUCTOR :: MIXED -> ctgTestState
// :: VOID -> MIXED                          getSubject()
// :: MIXED -> VOID                          setSubject(mixed $subject)
// :: VOID -> MIXED                          getComputed()
// :: MIXED -> VOID                          setComputed(mixed $computed)
// :: VOID -> [ctgTestResult]                getResults()
// :: ctgTestResult -> VOID                  addResult(CTGTestResult $result)
// Static Factory Method :: MIXED -> ctgTestState   init(mixed $subject)
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

### CTGTestStep

```
// CONSTRUCTOR :: STRING, (ctgTestState -> ctgTestState) -> ctgTestStep
//   (private — use init)
// :: VOID -> STRING                         getLabel()
// :: ctgTestState -> ctgTestState            execute(CTGTestState $state)
// Static Factory Method :: STRING, (ctgTestState -> ctgTestState) -> ctgTestStep
//   init(string $label, \Closure $execute)
```

### CTGTest

```
// CONSTRUCTOR :: STRING -> ctgTest
//   (private — use init)
// :: STRING, (ctgTestState -> MIXED) -> $this                              stage(...)
// :: STRING, (ctgTestState -> MIXED), ctgTestPredicate -> $this            assert(...)
// :: STRING, ctgTest -> $this                                              chain(...)
// :: STRING, STRING, ?(ctgTestState -> BOOL) -> $this                      skip(...)
// :: MIXED, ?ARRAY -> ctgTestState                                         start(...)
// :: VOID -> STRING                                                        getLabel()
// :: VOID -> [ctgTestStep]                                                 getSteps()
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
- `CTGTestStep` — concrete class, non-final. Steps are structurally uniform (`label` + `execute` closure). No subclassing is needed for core step types, but domain extensions may subclass for introspection.
- `CTGTestPredicate` — concrete class, non-final. Convenience builders use the factory; domain extensions may subclass for custom predicate types.
- `CTGTestResult` — concrete class, non-final. Domain extensions may subclass to add fields (e.g., `duration_ms`, `screenshot`).
- `CTGTest` — concrete class, non-final. Domain extensions subclass to add builder methods (e.g., `navigate()`, `query()`).
- `CTGTestStatus` — backed string enum, inherently final.
- `CTGTestError` — concrete class extending `\Exception`, non-final.

> **Judgment Call — no class is `final`:** The design doc's extension model (Core Concepts section 5) relies on subclassing for domain-specific step types, state types, and pipeline types. Making any core class final would close the extension surface.

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

All validation runs inside `start()`, before any step executes. Validation is a two-phase process:

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
| Pipeline label is empty after trim | `INVALID_STEP` (1000) | `['label' => '']` |
| Step label is empty after trim | `INVALID_STEP` (1000) | `['label' => '', 'step_index' => $i]` |
| Duplicate step label in same pipeline | `INVALID_STEP` (1000) | `['label' => $label, 'first_index' => $first, 'duplicate_index' => $i]` |
| Stage/assert fn is not a \Closure | `INVALID_STEP` (1000) | `['label' => $label, 'got' => gettype($fn)]` |
| Assert predicate is not CTGTestPredicate | `INVALID_EXPECTED_OUTCOME` (1003) | `['label' => $label, 'got' => gettype($pred)]` |
| Assert predicate is a callable (bare closure/function) | `INVALID_EXPECTED_OUTCOME` (1003) | `['label' => $label, 'got' => 'callable', 'hint' => 'Use CTGTestPredicate::init() to wrap']` |
| Chain target is not CTGTest | `INVALID_CHAIN` (1001) | `['label' => $label, 'got' => gettype($target)]` |
| Skip target label is empty | `INVALID_SKIP` (1004) | `['label' => $label]` |
| Skip target not found in pipeline | `INVALID_SKIP` (1004) | `['label' => $label, 'targetLabel' => $target, 'available' => $labels]` |
| Skip target appears before skip in pipeline | `INVALID_SKIP` (1004) | `['label' => $label, 'targetLabel' => $target, 'skip_index' => $si, 'target_index' => $ti]` |
| Duplicate skip targeting same step | `INVALID_SKIP` (1004) | `['label' => $label, 'targetLabel' => $target]` |
| Skip condition is not null and not \Closure | `INVALID_SKIP` (1004) | `['label' => $label, 'got' => gettype($cond)]` |
| Chain depth exceeds MAX_CHAIN_DEPTH | `CHAIN_DEPTH_EXCEEDED` (1100) | `['label' => $label, 'depth' => $depth, 'max' => MAX_CHAIN_DEPTH]` |

> **Judgment Call — validating assert predicate type redundantly:** PHP's type hint on the `assert()` method signature (`CTGTestPredicate $predicate`) will throw a TypeError if a non-predicate is passed. However, the validation phase still checks for this because: (a) the design doc explicitly defines `INVALID_EXPECTED_OUTCOME` for this case, (b) a TypeError is less informative than a framework error with structured data, and (c) validation of chained sub-pipelines walks their step lists and needs to re-check. In practice, the type hint catches most cases before validation runs; the validation catches cases where steps were constructed via internal/reflection mechanisms.

> **Judgment Call — skip must appear AFTER its target:** The design doc says "target appears before the skip" is an INVALID_SKIP condition. This means the skip directive must be defined after the step it gates. A skip at index 2 can gate a step at index 5, but a skip at index 5 cannot gate a step at index 2. Rationale: the skip is evaluated during execution, and it must evaluate before the target step is reached. Since steps execute in order, a skip must precede its target.

**Ambiguity note:** The design doc says "target appears before the skip" is invalid. This could mean the *target* must not appear at an earlier index than the *skip*, which is the opposite of what makes execution sense. I interpret this as: "it is invalid for the target to have *already been passed* when the skip is encountered," meaning the skip must appear at an *earlier* index than the target. A skip gates a *future* step, not a past one.

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

> **Judgment Call — no `output`, `strict`, `trace`, `formatter`, `debug` keys:** v1 had these config keys. v2 drops them all. The design doc says CONFIG has exactly two keys. Output/formatting is a caller concern; strict comparison is owned by predicates; trace/debug are implementation refinements outside the canonical config shape.

### 4.5 Execution Envelope — Timeout

```
realizes: Left to Language-Specific Specs > Execution envelope details
```

**Cancellation model: cooperative, alarm-based.**

PHP is single-threaded and does not support preemptive cancellation of userland code. The timeout mechanism uses `pcntl_alarm()` and `pcntl_signal()` where the `pcntl` extension is available:

1. Before each step executes, if timeout > 0 and `pcntl` is loaded, set an alarm for `ceil(timeout / 1000)` seconds (pcntl_alarm works in whole seconds).
2. The signal handler sets a flag. After the step's closure returns, the pipeline checks the flag.
3. If the flag is set (timeout exceeded), the step's return value is NOT applied to state — `state.subject`, `state.computed` remain unchanged. A result with `status: ERROR` and a framework-generated `\RuntimeException('Step timed out after {timeout}ms')` is recorded.
4. The alarm is cancelled after the step completes (or after the flag is checked).

**When `pcntl` is not available** (Windows, some shared hosting):

Timeout enforcement is best-effort. The pipeline records `hrtime(true)` before each step and checks elapsed time after the step returns. If the step exceeded the timeout, the same timeout-exceeded handling applies (return value not applied, ERROR result recorded). This cannot interrupt a long-running step, but it prevents the step's effect from being applied.

**Timeout value of 0:**

Disables timeout enforcement entirely. No alarm is set, no elapsed-time check is performed.

> **Judgment Call — `pcntl_alarm` granularity:** `pcntl_alarm()` works in whole seconds, not milliseconds. A timeout of 500ms would round up to 1 second. This is a known limitation of PHP's process control API. The `hrtime`-based fallback provides millisecond-accurate post-hoc detection. The framework documents this: alarm-based interruption has second-level granularity; elapsed-time detection has millisecond accuracy.

> **Judgment Call — state protection for extensions:** The design doc says "domain extensions that add mutable fields to state inherit this guarantee." In PHP, the timeout mechanism snapshots `state.subject` and `state.computed` before the step runs and restores them if the step times out. Extension state fields added by subclasses must follow the same pattern — subclasses that add mutable fields should override a protected `snapshotState()`/`restoreState()` method pair (or equivalent) to participate in timeout protection. This is an extension contract, not a core method — the base pipeline protects `subject` and `computed` only.

### 4.6 Synchronous Realization

```
realizes: Left to Language-Specific Specs > Synchronous vs asynchronous realization
```

PHP is synchronous. All function arrows are direct calls. There is no async machinery, no promises, no awaiting. A step's closure is called, it returns, the pipeline proceeds. This is the simplest conforming realization.

### 4.7 Host-Language Ergonomics

```
realizes: Left to Language-Specific Specs > Host-language ergonomics
```

- **Fluent builder:** `stage()`, `assert()`, `chain()`, `skip()` return `$this` (typed as `static` for subclass support).
- **Arrow functions:** PHP 8.4 arrow functions (`fn($state) => ...`) are idiomatic for short handlers.
- **Named arguments:** PHP 8.0+ named arguments work with all methods, especially useful for `CTGTestPredicate::init(expectedOutcome: ..., evaluate: ...)`.

### 4.8 Module Structure / File Layout

```
realizes: Left to Language-Specific Specs > Module structure
```

```
src/
    CTGTest.php                    # Pipeline builder/executor
    CTGTestState.php               # State carrier
    CTGTestStep.php                # Step value object
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

All classes in `CTG\Test` namespace. Formatters in `CTG\Test\Formatters`. Predicates in `CTG\Test\Predicates`.

### 4.9 Conformance Verification

```
realizes: Left to Language-Specific Specs > Conformance verification
```

Tests are written using ctg-php-test itself (v2 tests its own framework). Test files live in `tests/`. A test runner script (`tests/run.php` or Makefile target) executes all test files and reports results.

Each design doc requirement maps to one or more test pipelines. The test file naming convention is:

```
tests/
    test_state.php
    test_step.php
    test_predicate.php
    test_result.php
    test_pipeline_stage.php
    test_pipeline_assert.php
    test_pipeline_chain.php
    test_pipeline_skip.php
    test_pipeline_config.php
    test_pipeline_timeout.php
    test_error.php
    test_formatter.php
    test_predicates_convenience.php
```

---

## 5. Concrete Error Class

See section 2.7 (`CTGTestError`).

Canonical error types with codes:

| Constant | Code | Design Doc Name |
|---|---|---|
| `INVALID_STEP` | 1000 | `INVALID_STEP` |
| `INVALID_CHAIN` | 1001 | `INVALID_CHAIN` |
| `INVALID_CONFIG` | 1002 | `INVALID_CONFIG` |
| `INVALID_EXPECTED_OUTCOME` | 1003 | `INVALID_EXPECTED_OUTCOME` |
| `INVALID_SKIP` | 1004 | `INVALID_SKIP` |
| `FORMATTER_ERROR` | 2000 | `FORMATTER_ERROR` |
| `RUNNER_ERROR` | 2001 | `RUNNER_ERROR` |
| `CHAIN_DEPTH_EXCEEDED` | 1100 | *(non-canonical, structural enforcement)* |

Bidirectional lookup:
```php
CTGTestError::lookup('INVALID_STEP');       // => 1000
CTGTestError::lookup(1000);                 // => 'INVALID_STEP'
CTGTestError::lookup('CHAIN_DEPTH_EXCEEDED'); // => 1100
CTGTestError::lookup(1100);                 // => 'CHAIN_DEPTH_EXCEEDED'
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

1. First line: `Pipeline: {label}` where label is `CTGTest::getLabel()`.
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

> **Judgment Call — `>` as label separator:** The design doc says "labels themselves may contain any characters; the framework never joins them at the semantic level." The formatter joins them for display; this is a formatter concern, not a semantic one. ` > ` is visually clear and unlikely to collide with label content. Formatters that need different separators write their own.

> **Judgment Call — renamed from CTGTestConsoleFormatter:** v1 used "console" naming. v2 uses "text" because the formatter produces a string — it does not write to any console. The caller writes to stdout if it wants to.

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

> **Judgment Call — all predicates use strict comparison:** v1 had a `strict` config option. v2 drops loose comparison entirely. `equals()` uses `===`. There is no `looseEquals()`. Rationale: the design doc has no notion of comparison modes; predicates own their own comparison semantics. If a user needs loose comparison, they use `satisfies(fn($v) => $v == $expected)`.

> **Judgment Call — `satisfies()` uses `'(custom)'` as expectedOutcome:** When a user provides an arbitrary closure, there's no meaningful expected value to display. The string `'(custom)'` signals to formatters that the predicate is user-defined and the expected outcome is not a literal value.

---

## 8. Anti-Pattern Enforcement

```
realizes: Constraints > Anti-Patterns
```

Each anti-pattern from the design doc is explicitly **not provided** in v2:

| Anti-Pattern | Design Doc Reason | Enforcement in v2 |
|---|---|---|
| Static result accumulator | Causes leakage between pipelines | No static result storage. `CTGTestState` is instance-scoped. No static `$_results` anywhere. |
| Static config singleton | Config is per-invocation | No static `$_config`. No `CTGTest::setCliConfig()` (v1 had this; v2 removes it). Config is passed to `start()` only. |
| `collector` / `publishResult` config keys | Caller concern | CONFIG accepts only `haltOnFailure` and `timeout`. Unknown keys throw `INVALID_CONFIG`. |
| `output` / `formatter` config keys | Caller concern | Not in CONFIG. v1 had `output` and `formatter` keys; v2 removes them. |
| Pipeline-owned delivery (stdout) | Pipeline returns state | `start()` returns `CTGTestState`. It never calls `echo`. The caller decides what to do with the state. |
| Built-in generic test runner | Single-collector runner cannot compose | No runner class. No test discovery. No static `run()` method. Callers write their own runners. |
| Pipeline-owned subject snapshot/debug | Observation concern | No `debug` config key. No `$_snapshotSubject()`. No `'subject'` key on results. |
| Pipeline-level `compare` hook | Predicate concern | No `compare()` method on CTGTest. No `strict` config key. Comparison is exclusively owned by `CTGTestPredicate::evaluate()`. |

---

## 9. Test Target

Tests live in `tests/` at the project root. Each test file is a standalone PHP script that uses the framework to test itself.

**Running tests:**

```bash
# Run all tests
php tests/run.php

# Or via Makefile
make test
```

**Test file structure:**

Each test file creates pipelines, runs them via `start()`, and checks the returned `CTGTestState` for expected results. A test runner script collects results from all test files and reports pass/fail.

**Self-testing pattern:**

```php
<?php
declare(strict_types=1);

use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\Predicates\CTGTestPredicates;

$state = CTGTest::init('stage transforms subject')
    ->stage('double it', fn(CTGTestState $s) => $s->getSubject() * 2)
    ->assert('is doubled', fn(CTGTestState $s) => $s->getSubject(), CTGTestPredicates::equals(10))
    ->start(5);

// $state is a CTGTestState — inspect its results
$results = $state->getResults();
assert($results[0]->_status === \CTG\Test\CTGTestStatus::PASS);
assert($results[1]->_status === \CTG\Test\CTGTestStatus::PASS);
assert($results[1]->_computedValue === 10);
assert($results[1]->_expectedOutcome === 10);
```

---

## 10. Judgment Calls Index

All judgment calls are annotated inline with `> **Judgment Call —** ...` blocks throughout this spec. This section indexes them for reference:

1. **STATE as class, not array** (section 2.2) — typed fields prevent typo bugs, makes slot-deposit explicit.
2. **CONFIG as array, not class** (section 1) — only two keys, no behavior, class adds ceremony without value.
3. **STATUS as backed enum** (section 2.1) — PHP 8.1+ enums are the natural realization of a closed set.
4. **UPPERCASE backing values** (section 2.1) — aligns with design doc convention, breaks from v1 lowercase.
5. **Mutable setters on STATE** (section 2.2) — slot-deposit requires mutation; immutable copies break shared state.
6. **addResult takes CTGTestResult** (section 2.2) — typed results enforce structural correctness.
7. **Readonly public properties with underscore prefix** (section 2.3) — lightweight read access, private construction.
8. **No `type` field on RESULT** (section 2.3) — design doc omits it; v1 break is intentional.
9. **No `duration_ms` on RESULT** (section 2.3) — design doc omits it; available as extension.
10. **No `message` on RESULT** (section 2.3) — diagnostics live in `error` field.
11. **\Closure not callable for evaluate** (section 2.4) — narrower type is safer.
12. **CTGTestPredicate not final** (section 2.4) — preserves extension surface.
13. **`$_label` not `$_name`** (section 2.5) — aligns with design doc terminology.
14. **Single execute closure** (section 2.5) — uniform step shape, no type dispatch.
15. **Stage/assert handlers receive STATE** (section 2.6) — design doc signatures show `STATE -> *`.
16. **Assert type-hinted CTGTestPredicate** (section 2.6) — PHP enforces at call site.
17. **Skip has own label + targetLabel** (section 2.6) — design doc signature shows two string args.
18. **Skip condition receives STATE** (section 2.6) — consistent with stage/assert.
19. **`$data` as mixed** (section 2.7) — design doc says shape is not prescribed.
20. **No config parameter on formatter** (section 2.8) — design doc says formatters receive STATE only.
21. **No class is final** (section 4.1) — extension model relies on subclassing.
22. **Skip must appear after target** (section 4.3) — skip gates a future step, not a past one.
23. **Validate predicate type redundantly** (section 4.3) — framework error is more informative than TypeError.
24. **No v1 config keys** (section 4.4) — design doc has exactly two keys.
25. **pcntl_alarm for timeout** (section 4.5) — best available PHP mechanism, second-level granularity.
26. **State protection for extensions** (section 4.5) — subclasses must participate in timeout protection.
27. **`>` as label separator in formatter** (section 6) — formatter concern, not semantic.
28. **Renamed to CTGTestTextFormatter** (section 6) — formatter produces string, not console output.
29. **Strict comparison only** (section 7) — predicates own comparison; no `strict` config.
30. **`'(custom)'` as expectedOutcome for satisfies()** (section 7) — signals user-defined predicate.

---

## 11. Resolved Questions

These questions surfaced during the spec transform where the design doc did not provide enough information to decide. All have been resolved by owner decision.

### Q1: `state.computed` resets between steps

Reset `state.computed` to `null` before each step executes. Each step starts with a clean computed slot. This prevents stale computed values from leaking across steps.

### Q2: Stage result field values

Stage results have `null` for `computedValue` and `expectedOutcome` (PHP's realization of VOID). All `CTGTestResult` instances carry all six fields — there is no concept of "absent." Formatters use status and the combination of non-null fields to determine rendering.

### Q3: Skip condition throws → ERROR result for the target step

When a skip's condition function throws, the framework records an ERROR result for the **target** step. The skip asked "should this step run?" and got an error instead of an answer. The target did not run, and its result reflects that with `status: ERROR` and the caught exception in `error`. The label on the ERROR result is the target step's label, not the skip's label. This is consistent with how handler exceptions produce ERROR results for their step.

### Q4: Chain failure halts outer pipeline under `haltOnFailure`

Yes. The sub-pipeline runs with the same `haltOnFailure` setting, so it halts at the failing sub-step. That failure result is appended to the outer state's results (with the chain label prepended). The outer pipeline sees the failure and halts. Conversely, when `haltOnFailure` is false, neither the sub-pipeline nor the outer pipeline halts — every step everywhere runs to completion.

### Q5: All labels share one uniqueness namespace per pipeline

Yes. All step labels — stage, assert, chain, and skip — share one namespace per pipeline. A skip cannot have the same label as any other step in the same pipeline.

### Q6: Skipped chain label array is `[$chainLabel]`

A skipped chain result has label `[$chainLabel]` (single-element array), `skipped: true`, `status: null`, all other fields `null`. The sub-pipeline never ran, so there are no child results.

### Q7: Skip steps appear in `getSteps()`

Yes. Skips are structural steps in `$_steps`. They execute during the pipeline run (evaluate their condition, mark their target). They do not call `state.addResult()`. Their effect is visible on the target step's result entry (`skipped: true`).

---

## Appendix A: Execution Algorithm (Pseudocode)

```
function START(subject, config):
    config = resolveConfig(config)        // merge defaults, validate
    validatePipeline(this)                // validate all steps recursively

    state = CTGTestState::init(subject)

    for each step in this._steps:
        state.setComputed(null)           // reset computed slot

        if step is a skip directive:
            evaluate skip condition
            if condition holds: mark target step as skipped
            continue (no result for skip itself)

        if step is marked as skipped:
            state.addResult(skippedResult([step.label]))
            continue

        // timeout protection: snapshot state
        snapshot = [state.subject, state.computed]

        try:
            step.execute(state)           // mutates state via slot deposit
        catch (Throwable e):
            restore state from snapshot
            state.addResult(errorResult(...))
            if haltOnFailure: break
            continue

        // timeout check
        if timed out:
            restore state from snapshot
            state.addResult(errorResult(...))
            if haltOnFailure: break
            continue

        // for assert steps: predicate was already called inside execute closure
        // result was already added inside execute closure

        // check haltOnFailure
        lastResult = last element of state.results
        if haltOnFailure AND (lastResult.status is FAIL or ERROR):
            break

    return state
```

> **Note:** This pseudocode describes the flow. The actual implementation may differ in structure (e.g., the step's execute closure may add the result itself, or the pipeline may add it after execute returns). The semantic contract is what matters: steps compute, the pipeline determines, results are recorded in order.

---

## Appendix B: v1 to v2 Migration Summary

| v1 Concept | v2 Equivalent | Breaking Change? |
|---|---|---|
| `CTGTest::init(string $name)` | `CTGTest::init(string $label)` | Rename only |
| `->assert($name, $fn, $expected)` | `->assert($label, $fn, CTGTestPredicate)` | Yes — third arg is now a PREDICATE |
| `->assertAny(...)` | Removed from core | Yes — use convenience predicate |
| `->skip($name, $predicate)` | `->skip($label, $targetLabel, $condition)` | Yes — skip now has its own label |
| `start()` returns `string\|array\|null` | `start()` returns `CTGTestState` | Yes — no built-in output |
| Result arrays with `type`, `duration_ms`, `message` | `CTGTestResult` objects with design-doc fields | Yes — complete restructure |
| `STATUS_RECOVERED`, `STATUS_SKIP` | Removed from status enum | Yes — only PASS/FAIL/ERROR |
| Config keys: output, strict, trace, formatter, debug | Only haltOnFailure and timeout | Yes — all others removed |
| `compare()` method on CTGTest | Removed — predicates own comparison | Yes |
| `CTGTest::setCliConfig()` / `getCliConfig()` | Removed — static singletons are anti-patterns | Yes |
| `CTGTestStep` with type/fn/expected/errorHandler fields | `CTGTestStep` with label/execute fields | Yes |
| `CTGTestResult` static factory methods returning arrays | `CTGTestResult` returning typed objects | Yes |
| `CTGTestConsoleFormatter` | `CTGTestTextFormatter` | Rename + interface change |
| `INVALID_EXPECTED` (1003) | `INVALID_EXPECTED_OUTCOME` (1003) | Rename, same code |
