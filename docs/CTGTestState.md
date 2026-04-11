# CTGTestState

State carrier realizing the STATE primitive. Holds the pipeline label, the current subject, the current computed value, and the append-only list of results produced during execution. The setters and `addResult()` exist for the pipeline's slot-deposit model; callers receive a state from `CTGTest::start()` and typically only read it. Instances are created via the static `init()` factory.

### Properties

All properties are `private`. Callers access them through the getter methods below.

| Property | Visibility | Type | Description |
|----------|------------|------|-------------|
| _label | private | STRING | Pipeline label, set from `CTGTest::getLabel()` when the state is created |
| _subject | private | MIXED | The value threaded through the pipeline; mutated by stage operations |
| _computed | private | MIXED | Slot for the most recently produced assert value; reset before each operation |
| _results | private | [ctgTestResult] | Append-only list of results, one per executed operation |

### CTGTestState.init :: STRING, MIXED -> ctgTestState

Static factory. Returns a new state with the given label and subject. `_computed` is initialized to `null` and `_results` to the empty array.

```php
$state = CTGTestState::init('arithmetic', 1);
```

### ctgTestState.getLabel :: VOID -> STRING

Returns the pipeline label.

```php
$label = $state->getLabel();
```

### ctgTestState.getSubject :: VOID -> MIXED

Returns the current subject.

```php
$subject = $state->getSubject();
```

### ctgTestState.setSubject :: MIXED -> VOID

Replaces the current subject. Invoked by the pipeline during stage execution; user code should treat this as package-internal.

```php
$state->setSubject($newValue);
```

### ctgTestState.getComputed :: VOID -> MIXED

Returns the current computed slot. This holds the value produced by the most recent assert handler, or `null` if no assert has run since the slot was last reset.

```php
$computed = $state->getComputed();
```

### ctgTestState.setComputed :: MIXED -> VOID

Writes the computed slot. Invoked by the pipeline before and after each operation; user code should treat this as package-internal.

```php
$state->setComputed($value);
```

### ctgTestState.getResults :: VOID -> [ctgTestResult]

Returns the full ordered result list.

```php
foreach ($state->getResults() as $result) {
    // ...
}
```

### ctgTestState.addResult :: ctgTestResult -> VOID

Appends a result to the end of the result list. Invoked by the pipeline as it executes; user code should treat this as package-internal.

```php
$state->addResult($result);
```
