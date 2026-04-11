# CTGTestPredicates

Static convenience builders that construct `CTGTestPredicate` instances for the most common assertion shapes. Every builder returns a fully formed predicate whose expected-outcome value mirrors the argument passed in (or a descriptive placeholder for the nullary builders). All equality and identity comparisons use PHP's strict operators (`===` / `!==`); for loose comparison or custom logic, use `satisfies()` with a closure.

### CTGTestPredicates.equals :: MIXED -> ctgTestPredicate

Strict equality (`===`) against the expected value.

```php
$predicate = CTGTestPredicates::equals(42);
```

### CTGTestPredicates.notEquals :: MIXED -> ctgTestPredicate

Strict inequality (`!==`) against the expected value.

```php
$predicate = CTGTestPredicates::notEquals(0);
```

### CTGTestPredicates.isNull :: VOID -> ctgTestPredicate

Value is exactly `null`.

```php
$predicate = CTGTestPredicates::isNull();
```

### CTGTestPredicates.isNotNull :: VOID -> ctgTestPredicate

Value is anything other than `null`.

```php
$predicate = CTGTestPredicates::isNotNull();
```

### CTGTestPredicates.isTruthy :: VOID -> ctgTestPredicate

Value is truthy under PHP's bool cast.

```php
$predicate = CTGTestPredicates::isTruthy();
```

### CTGTestPredicates.isFalsy :: VOID -> ctgTestPredicate

Value is falsy under PHP's bool cast.

```php
$predicate = CTGTestPredicates::isFalsy();
```

### CTGTestPredicates.isTrue :: VOID -> ctgTestPredicate

Value is strictly the boolean `true`.

```php
$predicate = CTGTestPredicates::isTrue();
```

### CTGTestPredicates.isFalse :: VOID -> ctgTestPredicate

Value is strictly the boolean `false`.

```php
$predicate = CTGTestPredicates::isFalse();
```

### CTGTestPredicates.isInstanceOf :: STRING -> ctgTestPredicate

Value is an instance of the given class name.

```php
$predicate = CTGTestPredicates::isInstanceOf(\DateTime::class);
```

### CTGTestPredicates.isType :: STRING -> ctgTestPredicate

`gettype($value)` matches the given type name (e.g. `'string'`, `'integer'`, `'array'`).

```php
$predicate = CTGTestPredicates::isType('string');
```

### CTGTestPredicates.greaterThan :: MIXED -> ctgTestPredicate

Value is greater than (`>`) the expected value.

```php
$predicate = CTGTestPredicates::greaterThan(0);
```

### CTGTestPredicates.lessThan :: MIXED -> ctgTestPredicate

Value is less than (`<`) the expected value.

```php
$predicate = CTGTestPredicates::lessThan(100);
```

### CTGTestPredicates.contains :: STRING -> ctgTestPredicate

Value is a string containing the expected substring.

```php
$predicate = CTGTestPredicates::contains('error');
```

### CTGTestPredicates.matchesPattern :: STRING -> ctgTestPredicate

Value is a string that matches the given PCRE pattern.

```php
$predicate = CTGTestPredicates::matchesPattern('/^[a-z]+$/');
```

### CTGTestPredicates.hasCount :: INT -> ctgTestPredicate

Value is an array or `\Countable` whose `count()` matches the expected size.

```php
$predicate = CTGTestPredicates::hasCount(3);
```

### CTGTestPredicates.satisfies :: (MIXED -> BOOL) -> ctgTestPredicate

Custom predicate from a closure. The expected-outcome value is the literal string `'(custom)'`, shown in diagnostics when the assert fails.

```php
$predicate = CTGTestPredicates::satisfies(
    fn(mixed $value): bool => is_int($value) && $value % 2 === 0
);
```
