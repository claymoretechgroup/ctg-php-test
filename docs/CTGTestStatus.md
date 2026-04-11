# CTGTestStatus

Backed string enum realizing the STATUS primitive. Exactly three cases: `PASS`, `FAIL`, and `ERROR`. The `skipped` state is a bool field on `CTGTestResult`, not a status value, and there is no `RECOVERED` case in the core set. Each case's backing value is the uppercase name, so `CTGTestStatus::PASS->value` returns the string `'PASS'`.

### Cases

| Case | Value | Description |
|------|-------|-------------|
| PASS | 'PASS' | Operation completed successfully; assert predicate returned true |
| FAIL | 'FAIL' | Assert predicate returned false |
| ERROR | 'ERROR' | Operation handler, predicate, or skip condition threw an exception |

### Usage

```php
$status = CTGTestStatus::PASS;
$label  = $status->value; // 'PASS'
```
