# ctg-php-test

`ctg-php-test` is a composable, pipeline-based test framework for PHP. Tests are defined as pipelines of stage and assert operations on a subject. Stage transforms the subject. Assert inspects without mutating. Chain composes Test instances together. The framework separates test definition from execution, making pipelines reusable across subjects.

**Key Features:**

* **Pipeline model**: Tests are ordered sequences of stages and asserts on a threaded subject
* **Composable**: Chain separately defined Test instances into larger pipelines
* **Five-status reporting**: pass, fail, error, recovered, skip -- not just pass/fail
* **Zero dependencies**: Only PHP's standard library

## Install

```
composer require ctg/php-test
```

Requires PHP >= 8.1.

## Examples

### Basic Pipeline

Define a test pipeline and run it:

```php
<?php
require_once 'vendor/autoload.php';

use CTG\Test\CTGTest;

CTGTest::init('arithmetic')
    ->stage('double', fn($x) => $x * 2)
    ->assert('is 10', fn($x) => $x, 10)
    ->assert('is even', fn($x) => $x % 2 === 0, true)
    ->start(5);
```

### Predicate-Style Assert

Use a function that returns bool and compare against `true`:

```php
CTGTest::init('type checks')
    ->assert('is int', fn($x) => is_int($x), true)
    ->assert('is positive', fn($x) => $x > 0, true)
    ->start(42);
```

### Candidate Set Assert

Use `assertAny` when the actual value should match any one of several acceptable values:

```php
CTGTest::init('status check')
    ->assertAny('valid status', fn($x) => $x->getStatus(), ['active', 'pending', 'trial'])
    ->start($user);
```

Note: `assert` always does direct comparison. If you pass an array as expected to `assert`, it compares the actual value against that exact array:

```php
CTGTest::init('exact match')
    ->assert('exact tags', fn($x) => $x->getTags(), ['php', 'test'])
    ->start($post);
```

### Subject Evolution

The subject evolves through the pipeline as stages transform it:

```php
$config = ['host' => 'localhost', 'db' => 'test', 'user' => 'root', 'pass' => ''];

CTGTest::init('User Registration')
    ->stage('setup auth', fn($class) => $class::create($config))
    ->stage('register user', fn($auth) => [
        'auth' => $auth,
        'result' => $auth->register('alice@test.com', 'secure123')
    ])
    ->assert('returns user id', fn($s) => is_int($s['result']['user_id']), true)
    ->stage('attempt duplicate', function($s) {
        try {
            $s['auth']->register('alice@test.com', 'other');
            return array_merge($s, ['error' => null]);
        } catch (\Exception $e) {
            return array_merge($s, ['error' => $e->getCode()]);
        }
    })
    ->assert('duplicate rejected', fn($s) => $s['error'], 'DUPLICATE_ENTRY')
    ->start(AuthService::class);
```

### Composing Pipelines

Define reusable test components and chain them together:

```php
$hasId = CTGTest::init('has id')
    ->assert('id exists', fn($r) => isset($r['id']), true)
    ->assert('id is int', fn($r) => is_int($r['id']), true);

$hasEmail = CTGTest::init('has email')
    ->assert('email exists', fn($r) => isset($r['email']), true)
    ->assert('email has @', fn($r) => str_contains($r['email'], '@'), true);

CTGTest::init('User API')
    ->stage('create user', fn($api) => $api->createUser($data))
    ->chain('validate id', $hasId)
    ->chain('validate email', $hasEmail)
    ->start($api);
```

### Error Recovery

Stage error handlers receive the exception and return a replacement subject:

```php
CTGTest::init('resilient pipeline')
    ->stage('connect', fn($cfg) => DB::connect($cfg),
        fn($e) => DB::connectFallback())
    ->assert('connected', fn($db) => $db->isConnected(), true)
    ->start($config);
```

### Skip

Mark steps to skip by name. Can be conditional:

```php
CTGTest::init('conditional tests')
    ->stage('setup', fn($x) => $x)
    ->stage('heavy migration', fn($db) => $db->migrate())
    ->assert('migrated', fn($db) => $db->version(), 2)
    ->skip('heavy migration', fn($db) => $db->isProduction())
    ->start($db);
```

### Output Modes

```php
// Default: echoes human-readable report to stdout
$test->start($subject);

// Capture structured data for programmatic use
$report = $test->start($subject, ['output' => 'return-json']);

// Capture formatted string
$output = $test->start($subject, ['output' => 'return']);

// JSON to stdout
$test->start($subject, ['output' => 'json']);

// JUnit XML for CI
$test->start($subject, ['output' => 'junit']);
```

### Configuration

```php
$test->start($subject, [
    'output' => 'console',       // console, return, return-json, json, junit
    'haltOnFailure' => true,     // stop on first fail or error
    'strict' => true,            // === (strict) or == (loose)
    'trace' => false,            // include stack traces in exception structures
]);
```

### Error Handling

```php
use CTG\Test\CTGTestError;

try {
    $test->start($subject);
} catch (CTGTestError $e) {
    echo $e->type;          // 'INVALID_STEP'
    echo $e->getCode();     // 1000
    echo $e->msg;           // human-readable message
    print_r($e->data);      // structured context
}
```

### CLI Runner

```bash
./bin/ctg-test                        # run all *Test.php files
./bin/ctg-test tests/MyTest.php       # run specific file
./bin/ctg-test --format=junit         # JUnit XML output
./bin/ctg-test --no-halt --trace      # continue after failures, show traces
```

### Reusable Definitions

The same definition can run against different subjects:

```php
$crudTest = CTGTest::init('CRUD')
    ->stage('connect', fn($cls) => $cls::connect($config))
    ->stage('insert', fn($db) => $db->create('users', $data))
    ->assert('has id', fn($db) => is_int($db->lastId()), true);

$crudTest->start(MySQL::class);
$crudTest->start(Postgres::class);
$crudTest->start(SQLite::class);
```

## Class Documentation

* [CTGTest](docs/CTGTest/)
* [CTGTestError](docs/CTGTestError/)
* [CTGTestStep](docs/CTGTestStep/)
* [CTGTestResult](docs/CTGTestResult/)

## Notice

`ctg-php-test` is under active development. The core API is stable but formatters and CLI tooling may change.
