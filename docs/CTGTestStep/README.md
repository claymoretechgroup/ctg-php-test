# CTGTestStep

Immutable value object representing a single step definition in a test pipeline. CTGTestStep stores the raw definition -- no validation occurs at construction time. Validation is deferred to `CTGTest.start()`.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| _type | STRING | Step type: `'stage'`, `'assert'`, or `'chain'` |
| _name | STRING | Step name (trimmed at construction) |
| _fn | MIXED | Callable for stage/assert, or ctgTest instance for chain |
| _expected | MIXED | Expected value for assert steps, null for others |
| _errorHandler | MIXED | Error handler callable, or null |

### Constants

| Constant | Value |
|----------|-------|
| TYPE_STAGE | `'stage'` |
| TYPE_ASSERT | `'assert'` |
| TYPE_CHAIN | `'chain'` |

## Methods

### CONSTRUCTOR :: STRING, STRING, MIXED, ?MIXED, ?MIXED -> testStep

Creates a step definition. Name is trimmed on construction. All other values are stored as-is.

```php
$step = new CTGTestStep(CTGTestStep::TYPE_STAGE, 'connect', fn($x) => DB::connect($x));
```

### testStep.getType :: VOID -> STRING

Returns the step type.

### testStep.getName :: VOID -> STRING

Returns the trimmed step name.

### testStep.getFn :: VOID -> MIXED

Returns the callable (for stage/assert) or CTGTest instance (for chain).

### testStep.getExpected :: VOID -> MIXED

Returns the expected value. Relevant for assert steps only.

### testStep.getErrorHandler :: VOID -> MIXED

Returns the error handler callable, or null.
