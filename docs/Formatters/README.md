# Formatters

## CTGTestFormatterInterface

Interface that defines the contract for test report formatters. All built-in formatters implement this interface, and custom formatters must implement it to be used via the `formatter` config option.

### CTGTestFormatterInterface.format :: ARRAY -> STRING

Static method that accepts a report array and returns a formatted string representation. The report array follows the standard report structure documented in CTGTest.

```php
class MyFormatter implements CTGTestFormatterInterface {
    public static function format(array $report): string {
        return $report['name'] . ': ' . $report['status'];
    }
}
```

### Usage with CTGTest

Pass a formatter class-string via the `formatter` config key:

```php
$test->start($subject, [
    'output' => 'return',
    'formatter' => MyFormatter::class,
]);
```

The `output` mode controls delivery behavior when a custom formatter is set:

| Output Mode | Behavior with Custom Formatter |
|-------------|-------------------------------|
| `'return'` | Returns the formatted string |
| `'return-json'` | Returns the formatted string |
| `'console'` | Echoes the formatted string, returns null |
| `'json'` | Echoes the formatted string, returns null |
| `'junit'` | Echoes the formatted string, returns null |

### Validation

The formatter class is validated at config resolution time (before any steps execute):

- Must be a string (class-string)
- Class must exist
- Class must implement `CTGTestFormatterInterface`

Invalid formatters throw `INVALID_CONFIG` (code 1002). If the formatter's `format()` method throws at delivery time, the exception is wrapped in `FORMATTER_ERROR` (code 2000).

## Built-in Formatters

### CTGTestConsoleFormatter

Human-readable console output with indentation, dot-padding, and status labels. Used by default for `console` and `return` output modes.

### CTGTestJsonFormatter

Pretty-printed JSON output of the full report structure. Used by the `json` output mode.

### CTGTestJunitFormatter

JUnit XML output for CI integration. Best-effort lossy mapping -- JUnit has no concept of `recovered` status, so recovered steps map to `system-out` elements. Used by the `junit` output mode. Accepts an optional `includeTrace` parameter beyond the interface contract.
