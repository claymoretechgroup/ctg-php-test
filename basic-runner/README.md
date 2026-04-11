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

## What the runner guarantees (and doesn't)

**Guarantees:**

- A parse error, autoload failure, or bad require in one test file
  reports the filename and keeps going
- A test file returning the wrong shape (not a `CTGTest` or iterable)
  is reported and skipped
- A lazy iterable or generator that throws **during iteration** (not
  just at require time) is caught at the file boundary and the run
  continues
- A framework error thrown out of `start()` (e.g.
  `INVALID_EXPECTED_OUTCOME` from a malformed pipeline) is reported
  per-pipeline and does not abort the run
- An unexpected status value from an extension result subclass is
  counted as errored via a defensive `default` arm, not crashed on
- The summary line always prints and the exit code always reflects
  the aggregate state — one broken file cannot silently mask a pass
- Any of the above increments an `aborted` counter shown in the
  summary so you can see that failures happened outside `start()`'s
  normal result model
- File discovery is sorted (`sort()` after `glob`) so output order is
  deterministic across environments and reruns

**Does not guarantee:**

- **Hard timeout or cancellation** — everything runs in one process
  with direct `start()` calls. A hung pipeline (infinite loop,
  unbounded I/O without a socket timeout) will hang the entire run.
  Mitigate at the operating system layer by wrapping the command
  with a process-level timeout:

  ```bash
  timeout 300 php tests/run.php
  ```

- **Isolation between pipelines** — they share the PHP process. If
  a test leaks global state, static properties, or file descriptors,
  the next pipeline sees that leakage. Run tests in disposable
  environments (Docker, DB transactions, tmpfs) when you need true
  isolation.
- **Parallel execution, retries, filtering, reporters** — the
  runner is intentionally sequential and minimal. Add what your
  project needs in your own copy; do not push features back into
  this directory.

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
