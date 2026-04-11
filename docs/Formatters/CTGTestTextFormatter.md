# CTGTestTextFormatter

Reference implementation of `CTGTestFormatterInterface`. Renders a pipeline header, one line per result with status brackets padded to a fixed-width column, indented detail lines for `FAIL` and `ERROR` results, a summary line, and a final aggregate `Result:` line. Skip directives never appear in the trace — only stage, assert, chain-child, and skipped entries do. The aggregate result uses the highest severity present: `ERROR` beats `FAIL` beats `PASS`, and `VOID` is emitted when nothing was recorded.

### CTGTestTextFormatter.format :: ctgTestState -> STRING

Transforms a final state into the reference text format. The output is deterministic: results appear in execution order, label paths are joined with ` > `, and computed/expected values in `FAIL` detail lines are rendered through a scalar-aware value printer that escapes whitespace and control bytes in strings.

```php
$state  = CTGTest::init('arithmetic')
    ->stage('double', fn($s) => $s->getSubject() * 2)
    ->assert('equals 4',
        fn($s) => $s->getSubject(),
        CTGTestPredicates::equals(4))
    ->start(2);

echo CTGTestTextFormatter::format($state);
// Pipeline: arithmetic
//
//   [PASS]    double
//   [PASS]    equals 4
//
// ---
//
// 2 passed, 0 failed, 0 skipped, 0 errored (2 total)
// Result: PASS
```
