# CTGTestPredicate

Realizes the PREDICATE primitive. Carries an expected-outcome value for diagnostic display and a `\Closure` that maps a computed value to a bool. The predicate itself does not determine status — the pipeline maps `true` to `PASS` and `false` to `FAIL`, and any exception thrown from `evaluate()` maps to `ERROR`. Instances are created via the static `init()` factory; the constructor is private.

### Properties

Both properties are `private readonly`. Callers access them through `getExpectedOutcome()` and `evaluate()`.

| Property | Visibility | Type | Description |
|----------|------------|------|-------------|
| _expectedOutcome | private readonly | MIXED | Value used for diagnostic display in `FAIL` result detail lines |
| _evaluate | private readonly | (MIXED -> BOOL) | Closure that decides whether a computed value satisfies the predicate |

### CTGTestPredicate.init :: MIXED, (MIXED -> BOOL) -> ctgTestPredicate

Static factory. The `evaluate` argument is typed as `\Closure` — not a generic callable — so that the stored reference is invariant and the call site is type-predictable. The `expectedOutcome` value is carried verbatim and never interpreted by the framework.

```php
$predicate = CTGTestPredicate::init(
    42,
    fn(mixed $value): bool => $value === 42
);
```

### ctgTestPredicate.getExpectedOutcome :: VOID -> MIXED

Returns the expected-outcome value supplied at construction. Used by the pipeline to populate the assert result's `_expectedOutcome` field.

```php
$expected = $predicate->getExpectedOutcome();
```

### ctgTestPredicate.evaluate :: MIXED -> BOOL

Applies the evaluate closure to the given computed value and returns its bool result. Any exception raised inside the closure propagates to the pipeline, where it is recorded as an `ERROR` result.

```php
$ok = $predicate->evaluate(42);
```
