# CTGTestError

Framework exception class for ctg-php-test. Extends `\Exception` with typed error codes, bidirectional lookup, and structured context data. CTGTestError is never used for test outcomes -- those are statuses in the report. CTGTestError is strictly for when the framework itself cannot function.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| type | STRING | Error type name from the TYPES map (readonly) |
| msg | STRING | Human-readable error message (readonly) |
| data | ?ARRAY | Structured context for diagnosis (readonly) |

The integer code is passed to `parent::__construct()` so `getCode()` returns the typed code natively.

### Error Codes

**Definition-time errors (1xxx)** -- caught at the start of `start()`, before any steps execute:

| Code | Type | Description |
|------|------|-------------|
| 1000 | INVALID_STEP | Non-callable fn, empty name after trimming, or duplicate name at same level |
| 1001 | INVALID_CHAIN | Second argument to `chain()` is not a CTGTest instance |
| 1002 | INVALID_CONFIG | Unknown config key or invalid value type |
| 1003 | INVALID_EXPECTED | Assert expected is callable (predicate in wrong argument) |
| 1004 | INVALID_SKIP | Target doesn't match any step, non-callable predicate, or duplicate directive |

**Runtime errors (2xxx)** -- framework machinery failures during execution:

| Code | Type | Description |
|------|------|-------------|
| 2000 | FORMATTER_ERROR | Formatter threw while rendering. Report preserved in `data['report']` |
| 2001 | RUNNER_ERROR | Internal invariant violation (not user code failures) |

## Methods

### CONSTRUCTOR :: STRING|INT, ?STRING, ?ARRAY -> testError

Creates a CTGTestError from a type name or integer code. Message defaults to the type name if not provided. Data is an optional associative array of structured context for diagnosis.

```php
throw new CTGTestError('INVALID_STEP', "Step 'bad' function is not callable", [
    'step_index' => 2,
    'name' => 'bad',
    'got' => 'string',
]);
```

### CTGTestError.lookup :: STRING|INT -> STRING|INT

Static method. Bidirectional lookup between type names and integer codes.

```php
CTGTestError::lookup('INVALID_STEP');  // 1000
CTGTestError::lookup(1000);            // 'INVALID_STEP'
```
