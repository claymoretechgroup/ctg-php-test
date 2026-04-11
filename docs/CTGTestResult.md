# CTGTestResult

Immutable value object realizing the RESULT primitive. Every field is `readonly` and instances are produced only by the three named factory methods — `stageResult`, `assertResult`, and `skippedResult` — so that shape correctness is enforced by the framework rather than by ad-hoc callers. Instances are created via those static factories; the constructor is private.

### Properties

All properties are `public readonly` and accessed directly (e.g. `$result->_status`).

| Property | Visibility | Type | Description |
|----------|------------|------|-------------|
| _label | public readonly | [STRING] | Full label path — parent chain labels followed by the operation label |
| _skipped | public readonly | BOOL | True for skipped-result entries; false otherwise |
| _status | public readonly | ?ctgTestStatus | `PASS`, `FAIL`, or `ERROR`; `null` when `_skipped` is true |
| _computedValue | public readonly | MIXED | Assert-only: the value produced by the handler, or `null` when not applicable |
| _expectedOutcome | public readonly | MIXED | Assert-only: the expected-outcome carried by the predicate, or `null` when not applicable |
| _error | public readonly | ?\Throwable | The thrown exception when `_status` is `ERROR`; `null` otherwise |

### CTGTestResult.stageResult :: [STRING], ctgTestStatus, ?\Throwable -> ctgTestResult

Static factory for stage-operation results. `_skipped` is `false`, `_computedValue` and `_expectedOutcome` are both `null`, and `_error` carries the thrown exception when `status` is `ERROR`.

```php
$result = CTGTestResult::stageResult(['pipeline', 'setup'], CTGTestStatus::PASS);
```

### CTGTestResult.assertResult :: [STRING], ctgTestStatus, MIXED, MIXED, ?\Throwable -> ctgTestResult

Static factory for assert-operation results. `computedValue` and `expectedOutcome` may both be `null` when the handler threw before producing a value; otherwise they carry the assert's diagnostic pair.

```php
$result = CTGTestResult::assertResult(
    ['pipeline', 'equals'],
    CTGTestStatus::FAIL,
    3,
    2
);
```

### CTGTestResult.skippedResult :: [STRING] -> ctgTestResult

Static factory for skipped entries. `_skipped` is `true`, `_status` is `null`, and every value field is `null`. Applies uniformly to stage, assert, and chain-child operations gated by a skip directive.

```php
$result = CTGTestResult::skippedResult(['pipeline', 'maybe run']);
```
