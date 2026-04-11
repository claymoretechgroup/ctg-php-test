# basic-runner

The deliberate starter solution for running `ctg-php-test` pipelines
in a project. Copy `run.php` and one or more test files into your
project's `tests/` directory and run with:

```bash
php tests/run.php
```

## Contents

- **`run.php`** — the runner script. Discovers `*Test.php` files in
  its own directory, invokes each pipeline's `start()`, renders result
  state with `CTGTestTextFormatter`, aggregates counts, and sets an
  exit code (0 on full pass, 1 on any failure or error).
- **`ArithmeticTest.php`** — a working sample test file. Demonstrates
  the convention that test files `return` an array of `CTGTest`
  instances, each seeding its own subject in a first stage.

## Why files instead of a class or package

`ctg-php-test` deliberately ships the runner as copy-pasteable files
rather than a `CTGTestRunner` class or a separate composer package.
The rationale:

1. The design doc treats the runner as a caller concern — it is not
   part of the framework's semantic core, and a single-collector
   runner cannot compose across nested pipelines.
2. Every project has slightly different runner needs (fixture
   wrapping, CI output format, filtering). A class would either be
   too generic to be useful or too prescriptive to fit.
3. Forty lines of boilerplate per project is cheap; premature
   abstraction is expensive. See the ctg-php-test v1 → v2.2 rewrite
   for what speculative abstraction costs to remove.

A reusable `CTGTestRunner` class may be extracted later, once
multiple real projects have used this starter and the shared
patterns are visible. Until then, the files here are the canonical
starter.

## Customizing

The runner is intentionally minimal. Extend it in your project
however your needs dictate:

- **Different subject per pipeline** — replace the `null` passed to
  `start()` with a per-pipeline subject factory
- **JSON output for CI** — write a formatter alongside
  `CTGTestTextFormatter` and call it instead
- **Filtering by file or label** — add argv parsing before the glob
- **Fixture lifecycle (DB transactions, file cleanup)** — wrap each
  pipeline invocation in setup/teardown, or compose fixture chains
  inside each test pipeline

Do not try to push every customization back into this directory. The
whole point is that each project owns its own runner.
