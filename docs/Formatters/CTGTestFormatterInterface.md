# CTGTestFormatterInterface

Contract for the FORMATTER primitive: a transform from a final `CTGTestState` to a string representation. Formatters consume STATE only — any formatter-specific configuration (indentation, colour, verbosity) is the formatter's own concern and not part of this contract. Implementations expose a single static `format()` method so that no formatter instance ever needs to be constructed.

### CTGTestFormatterInterface.format :: ctgTestState -> STRING

Transforms the given final state into a string. Implementations should read only from the state passed in and must not mutate it. Any framework-level error raised inside a formatter should be thrown as `CTGTestError` with code `FORMATTER_ERROR`.

```php
$output = CTGTestTextFormatter::format($state);
```
