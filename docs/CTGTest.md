# CTGTest

Public-facing builder and executor for the PIPELINE primitive. Builder methods append tagged operations to an internal list and return `$this` for chaining; `start()` validates the entire pipeline up front and then executes it against a subject, returning the final `CTGTestState`. Builder arguments for callables and sub-pipelines are deliberately typed `mixed` so that every structural error surfaces as a canonical `CTGTestError` at `start()` rather than a native `TypeError` at the builder call site. Instances are created via the static `init()` factory.

### Properties

Both properties are `private` and internal to the pipeline.

| Property | Visibility | Type | Description |
|----------|------------|------|-------------|
| _label | private | STRING | Pipeline label, trimmed on construction |
| _operations | private | ARRAY | Ordered list of tagged operation records (stage, assert, chain, skip) |

### CTGTest.init :: STRING -> ctgTest

Static factory. Returns a new, empty pipeline labeled with the given string. The label is trimmed on construction; an empty-after-trim label is rejected during `start()` validation as `INVALID_OPERATION`.

```php
$test = CTGTest::init('arithmetic');
```

### ctgTest.getLabel :: VOID -> STRING

Returns the trimmed pipeline label.

```php
$label = CTGTest::init('math')->getLabel();
```

### ctgTest.stage :: STRING, MIXED -> $this

Appends a stage operation. At execution time the `\Closure` receives the current `CTGTestState` and its return value becomes the new subject. The `fn` argument is accepted as `mixed`; non-Closure values surface as `INVALID_OPERATION` during `start()`. Chainable.

```php
$test = CTGTest::init('example')
    ->stage('double', fn($state) => $state->getSubject() * 2);
```

### ctgTest.assert :: STRING, MIXED, MIXED -> $this

Appends an assert operation. At execution time `fn` receives the current state and produces a computed value, which is then passed to `predicate->evaluate()`. A true predicate result maps to `PASS`, false maps to `FAIL`, and any thrown exception maps to `ERROR`. Both `fn` and `predicate` are accepted as `mixed` and validated in `start()`: `fn` must be a `\Closure` and `predicate` must be a `CTGTestPredicate`. Chainable.

```php
$test = CTGTest::init('example')
    ->assert('is positive',
        fn($state) => $state->getSubject(),
        CTGTestPredicates::greaterThan(0));
```

### ctgTest.chain :: STRING, MIXED -> $this

Appends a chain operation targeting a sub-pipeline. At execution time the sub-pipeline runs against the same state, contributing its label as a prefix to every result it produces. The chain itself does not emit its own result entry. The `pipeline` argument is accepted as `mixed` and must be a `CTGTest` instance; non-CTGTest values surface as `INVALID_CHAIN` during `start()`. Chainable.

```php
$validate = CTGTest::init('validate')
    ->assert('not null',
        fn($s) => $s->getSubject(),
        CTGTestPredicates::isNotNull());

$test = CTGTest::init('outer')
    ->chain('validate', $validate);
```

### ctgTest.skip :: STRING, MIXED -> $this

Appends a skip directive gating the operation identified by `targetLabel`. The target must exist in the same pipeline; skip targets in sub-pipelines are not resolved across chain boundaries. If `condition` is `null`, the target is always skipped. If `condition` is a `\Closure`, it is invoked with the current state at execution time and the target is skipped when it returns truthy. Non-null, non-Closure conditions surface as `INVALID_SKIP` during `start()`. Chainable.

```php
$test = CTGTest::init('example')
    ->stage('maybe run', fn($s) => $s->getSubject() + 1)
    ->skip('maybe run', fn($s) => $s->getSubject() < 0);
```

### ctgTest.start :: MIXED, ?ARRAY -> ctgTestState

Validates the entire pipeline tree, then executes it against the given subject. Returns the final `CTGTestState` containing the subject, computed slot, and the full result list. The optional `config` array accepts `haltOnFailure` (bool, default `true`) and `timeout` (int milliseconds, default `5000`). Unknown keys or bad types throw `CTGTestError` with code `INVALID_CONFIG`. Structural pipeline errors throw `CTGTestError` with the corresponding validation code before any operation runs.

```php
$state = CTGTest::init('add')
    ->stage('incr', fn($s) => $s->getSubject() + 1)
    ->assert('equals 2',
        fn($s) => $s->getSubject(),
        CTGTestPredicates::equals(2))
    ->start(1);
```
