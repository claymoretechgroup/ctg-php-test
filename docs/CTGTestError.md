# CTGTestError

Framework error class realizing the FRAMEWORK_ERROR primitive. Extends `\Exception` so thrown errors are catchable with native PHP exception machinery. Each canonical type has an integer code and an uppercase name, and the bidirectional `TYPES` map is the single source of truth for both directions. Validation errors live in the 1xxx range, runtime errors in the 2xxx range, and the structural `CHAIN_DEPTH_EXCEEDED` enforcement error lives in the 1100-1199 band.

### Properties

All properties are `public readonly` and accessed directly (e.g. `$error->type`).

| Property | Visibility | Type | Description |
|----------|------------|------|-------------|
| type | public readonly | STRING | Canonical type name (e.g. `'INVALID_OPERATION'`) |
| msg | public readonly | STRING | Human-readable message; defaults to the type name when none supplied |
| data | public readonly | MIXED | Structured diagnostic payload for programmatic inspection |

### Error Codes

| Code | Type | Description |
|------|------|-------------|
| 1000 | INVALID_OPERATION | Stage or assert operation is structurally invalid: empty label, duplicate label, non-Closure fn, or empty pipeline label |
| 1001 | INVALID_CHAIN | Chain target is not a `CTGTest` instance |
| 1002 | INVALID_CONFIG | Unknown config key, wrong value type, or value outside its allowed range |
| 1003 | INVALID_EXPECTED_OUTCOME | Assert predicate is not a `CTGTestPredicate` (with a hint when a bare callable was passed) |
| 1004 | INVALID_SKIP | Skip target label is empty, unknown, duplicated, or its condition is neither `null` nor a `\Closure` |
| 1100 | CHAIN_DEPTH_EXCEEDED | Chain nesting depth exceeds the maximum of 64 |
| 2000 | FORMATTER_ERROR | Formatter implementation raised a framework-level error |
| 2001 | RUNNER_ERROR | Pipeline execution reached an unreachable branch (should never occur) |

### CONSTRUCTOR :: STRING|INT, ?STRING, MIXED -> ctgTestError

Creates an error from a canonical type identifier — either the name (`'INVALID_OPERATION'`) or the code (`1000`). The identifier is normalized via `lookup()`, and a caller passing anything outside the `TYPES` map receives an `\InvalidArgumentException` rather than a silently constructed error. When `$message` is omitted, the type name is used as the message.

```php
throw new CTGTestError(
    'INVALID_OPERATION',
    "Duplicate operation label: setup",
    ['label' => 'setup', 'first_index' => 0, 'duplicate_index' => 3]
);
```

### CTGTestError.lookup :: STRING|INT -> STRING|INT

Bidirectional lookup against the `TYPES` map: name to code or code to name. Throws `\InvalidArgumentException` for any value not present in the map. The contract is strict in both directions, so `lookup(lookup($x)) === $x` holds for every canonical identifier.

```php
$code = CTGTestError::lookup('INVALID_OPERATION'); // 1000
$name = CTGTestError::lookup(1000);                // 'INVALID_OPERATION'
```
