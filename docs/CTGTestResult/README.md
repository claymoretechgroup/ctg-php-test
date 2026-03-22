# CTGTestResult

Static utility class for constructing step results, assembling reports, computing aggregates, and formatting values. CTGTestResult has no instance state -- all methods are static.

### Constants

| Constant | Value |
|----------|-------|
| STATUS_PASS | `'pass'` |
| STATUS_FAIL | `'fail'` |
| STATUS_ERROR | `'error'` |
| STATUS_RECOVERED | `'recovered'` |
| STATUS_SKIP | `'skip'` |

Severity order for aggregation:

| Status | Severity |
|--------|----------|
| error | 5 (highest) |
| fail | 4 |
| recovered | 3 |
| pass | 2 |
| skip | 1 (lowest) |

## Methods

### CTGTestResult.stepResult :: STRING, STRING, STRING, INT, ?STRING, ?ARRAY -> ARRAY

Creates a step result array for stage steps.

```php
$result = CTGTestResult::stepResult('stage', 'connect', 'pass', 5);
```

### CTGTestResult.assertResult :: STRING, STRING, INT, MIXED, MIXED, ?STRING, ?ARRAY -> ARRAY

Creates a step result array for assert steps, including `actual` and `expected` fields.

```php
$result = CTGTestResult::assertResult('check id', 'fail', 1, 3, 5, 'expected 5 but got 3');
```

### CTGTestResult.chainResult :: STRING, STRING, INT, ?STRING, ?ARRAY, ARRAY, ARRAY -> ARRAY

Creates a chain step result with nested child steps and aggregate counts.

```php
$result = CTGTestResult::chainResult('group', 'pass', 4, null, null, $childSteps, $counts);
```

### CTGTestResult.report :: STRING, ARRAY -> ARRAY

Creates a root report from a test name and an array of step results. Computes aggregate status, counts, and duration. The root report has no `type` field.

```php
$report = CTGTestResult::report('My Test', $stepResults);
```

### CTGTestResult.aggregateStatus :: ARRAY -> STRING

Derives the aggregate status from an array of step results using the severity order. Empty arrays return `'pass'` by special case.

```php
$status = CTGTestResult::aggregateStatus($steps);  // 'error', 'fail', etc.
```

### CTGTestResult.countSteps :: ARRAY -> ARRAY

Counts steps by status at the current level. Returns an associative array with `passed`, `failed`, `skipped`, `recovered`, `errored`, and `total`.

```php
$counts = CTGTestResult::countSteps($steps);
```

### CTGTestResult.sumDuration :: ARRAY -> INT

Sums `duration_ms` across steps at the current level.

### CTGTestResult.formatException :: \Throwable, ?BOOL, ?ARRAY -> ARRAY

Converts a PHP exception to the structured exception array format. Includes `class`, `message`, `code`, optional `trace` (when second argument is true), and optional `caused_by` (for the dual-exception case).

```php
$formatted = CTGTestResult::formatException($e, true);
```

### CTGTestResult.chainMessage :: INT, INT, INT -> ?STRING

Generates the canonical chain summary message. Returns null if there are no failures or errors.

```php
$msg = CTGTestResult::chainMessage(2, 1, 5);  // '2 failed, 1 errored in 5 steps'
$msg = CTGTestResult::chainMessage(0, 0, 3);  // null
```

### CTGTestResult.formatValue :: MIXED -> STRING

Formats a value for display in messages. Uses `var_export` short form for scalars and type summaries for complex values.

```php
CTGTestResult::formatValue(42);           // '42'
CTGTestResult::formatValue('hello');      // "'hello'"
CTGTestResult::formatValue(true);         // 'true'
CTGTestResult::formatValue([1, 2, 3]);    // 'array(3)'
CTGTestResult::formatValue($obj);         // 'object(stdClass)'
```
