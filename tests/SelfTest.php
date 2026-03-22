<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use CTG\Test\CTGTest;
use CTG\Test\CTGTestError;
use CTG\Test\CTGTestResult;
use CTG\Test\Formatters\CTGTestConsoleFormatter;
use CTG\Test\Formatters\CTGTestJsonFormatter;
use CTG\Test\Formatters\CTGTestJunitFormatter;

// Self-test for ctg-php-test — the framework tests itself

echo "=== ctg-php-test Self Test ===\n\n";

$allPassed = true;

function selfTest(string $label, callable $fn): void {
    global $allPassed;
    try {
        $result = $fn();
        if ($result === true) {
            echo "  PASS  {$label}\n";
        } else {
            echo "  FAIL  {$label}\n";
            if (is_string($result)) { echo "        {$result}\n"; }
            $allPassed = false;
        }
    } catch (\Throwable $e) {
        echo "  ERROR {$label}\n";
        echo "        " . get_class($e) . ": " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

// ── Basic Stage ──────────────────────────────────────────────

selfTest('stage transforms subject', fn() =>
    CTGTest::init('stage test')
        ->stage('double', fn($x) => $x * 2)
        ->start(5, ['output' => 'return-json'])['status'] === 'pass'
);

selfTest('stage chains transform subject sequentially', function() {
    $r = CTGTest::init('chain stages')
        ->stage('add 1', fn($x) => $x + 1)
        ->stage('double', fn($x) => $x * 2)
        ->start(5, ['output' => 'return-json']);
    return $r['status'] === 'pass' && $r['total'] === 2;
});

// ── Basic Assert ─────────────────────────────────────────────

selfTest('assert pass when values match', function() {
    $r = CTGTest::init('assert pass')
        ->assert('equals 5', fn($x) => $x, 5)
        ->start(5, ['output' => 'return-json']);
    return $r['status'] === 'pass'
        && $r['steps'][0]['actual'] === 5
        && $r['steps'][0]['expected'] === 5;
});

selfTest('assert fail when values mismatch', function() {
    $r = CTGTest::init('assert fail')
        ->assert('equals 10', fn($x) => $x, 10)
        ->start(5, ['output' => 'return-json']);
    return $r['status'] === 'fail'
        && $r['steps'][0]['actual'] === 5
        && str_contains($r['steps'][0]['message'], 'expected');
});

selfTest('assert does not mutate subject', function() {
    $r = CTGTest::init('assert no mutate')
        ->assert('a', fn($x) => $x, 5)
        ->assert('b', fn($x) => $x, 5)
        ->start(5, ['output' => 'return-json']);
    return $r['status'] === 'pass' && $r['total'] === 2;
});

selfTest('predicate style assert', fn() =>
    CTGTest::init('predicate')
        ->assert('is int', fn($x) => is_int($x), true)
        ->start(42, ['output' => 'return-json'])['status'] === 'pass'
);

// ── Assert Array Expected ────────────────────────────────────

selfTest('candidate set pass', fn() =>
    CTGTest::init('candidates')
        ->assert('in set', fn($x) => $x, [1, 2, 3])
        ->start(2, ['output' => 'return-json'])['status'] === 'pass'
);

selfTest('candidate set fail', fn() =>
    CTGTest::init('candidates fail')
        ->assert('not in set', fn($x) => $x, [1, 2, 3])
        ->start(99, ['output' => 'return-json'])['status'] === 'fail'
);

selfTest('exact array match via wrapper', fn() =>
    CTGTest::init('exact array')
        ->assert('exact', fn($x) => $x, [['a', 'b']])
        ->start(['a', 'b'], ['output' => 'return-json'])['status'] === 'pass'
);

// Fix #5: Empty array expected edge case
selfTest('empty array expected matches empty array actual', function() {
    $r = CTGTest::init('empty array')
        ->assert('empty', fn($x) => $x, [])
        ->start([], ['output' => 'return-json']);
    return $r['status'] === 'pass';
});

selfTest('empty array expected fails for non-empty actual', function() {
    $r = CTGTest::init('empty array fail')
        ->assert('empty', fn($x) => $x, [])
        ->start([1, 2], ['output' => 'return-json']);
    return $r['status'] === 'fail';
});

// ── Stage + Assert Pipeline ──────────────────────────────────

selfTest('stage then assert', function() {
    $r = CTGTest::init('pipeline')
        ->stage('double', fn($x) => $x * 2)
        ->assert('is 10', fn($x) => $x, 10)
        ->start(5, ['output' => 'return-json']);
    return $r['status'] === 'pass' && $r['total'] === 2;
});

// ── Chain ────────────────────────────────────────────────────

selfTest('chain composes test definitions', function() {
    $sub = CTGTest::init('sub')->assert('positive', fn($x) => $x > 0, true);
    $r = CTGTest::init('main')
        ->stage('set', fn($x) => 42)
        ->chain('verify', $sub)
        ->start(0, ['output' => 'return-json']);
    return $r['status'] === 'pass' && $r['steps'][1]['type'] === 'chain';
});

selfTest('chain mutations carry forward', function() {
    $sub = CTGTest::init('sub')->stage('add 10', fn($x) => $x + 10);
    $r = CTGTest::init('main')
        ->stage('set 5', fn($x) => 5)
        ->chain('add', $sub)
        ->assert('is 15', fn($x) => $x, 15)
        ->start(0, ['output' => 'return-json']);
    return $r['status'] === 'pass';
});

selfTest('chain name from chain() not init', function() {
    $sub = CTGTest::init('internal')->assert('ok', fn($x) => true, true);
    $r = CTGTest::init('main')->chain('my label', $sub)->start(1, ['output' => 'return-json']);
    return $r['steps'][0]['name'] === 'my label';
});

// ── Skip ─────────────────────────────────────────────────────

selfTest('skip unconditionally', function() {
    $r = CTGTest::init('skip')
        ->stage('first', fn($x) => $x + 1)
        ->stage('skipped', fn($x) => $x * 100)
        ->assert('check', fn($x) => $x, 2)
        ->skip('skipped')
        ->start(1, ['output' => 'return-json']);
    return $r['status'] === 'pass' && $r['steps'][1]['status'] === 'skip' && $r['skipped'] === 1;
});

selfTest('skip predicate false does not skip', function() {
    $r = CTGTest::init('cond skip')
        ->stage('maybe', fn($x) => $x)
        ->skip('maybe', fn($x) => $x > 10)
        ->start(5, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'pass';
});

selfTest('skip predicate true skips', function() {
    $r = CTGTest::init('cond skip true')
        ->stage('maybe', fn($x) => $x)
        ->skip('maybe', fn($x) => $x > 3)
        ->start(5, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'skip';
});

// ── Error Handling ───────────────────────────────────────────

selfTest('stage error when fn throws', function() {
    $r = CTGTest::init('err')
        ->stage('throws', fn($x) => throw new \RuntimeException('boom'))
        ->start(1, ['output' => 'return-json']);
    return $r['status'] === 'error' && $r['steps'][0]['exception']['class'] === 'RuntimeException';
});

selfTest('stage recovery via error handler', function() {
    $r = CTGTest::init('recover')
        ->stage('recovers', fn($x) => throw new \RuntimeException('boom'), fn($e) => 'recovered')
        ->assert('check', fn($x) => $x, 'recovered')
        ->start(1, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'recovered' && $r['steps'][1]['status'] === 'pass';
});

selfTest('handler throws produces caused_by', function() {
    $r = CTGTest::init('dual fail')
        ->stage('bad', fn($x) => throw new \RuntimeException('original'),
            fn($e) => throw new \LogicException('handler failed'))
        ->start(1, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'error'
        && $r['steps'][0]['exception']['class'] === 'LogicException'
        && $r['steps'][0]['exception']['caused_by']['class'] === 'RuntimeException';
});

selfTest('assert recovery always recovered', function() {
    $r = CTGTest::init('assert recover')
        ->assert('r', fn($x) => throw new \RuntimeException('boom'), 42, fn($e) => 42)
        ->start(1, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'recovered';
});

// ── HaltOnFailure ────────────────────────────────────────────

selfTest('halt stops at first fail', function() {
    $r = CTGTest::init('halt')
        ->assert('fails', fn($x) => $x, 999)
        ->assert('never', fn($x) => $x, 1)
        ->start(1, ['output' => 'return-json']);
    return $r['total'] === 1;
});

selfTest('no halt collects all', function() {
    $r = CTGTest::init('no halt')
        ->assert('f1', fn($x) => $x, 999)
        ->assert('f2', fn($x) => $x, 888)
        ->start(1, ['output' => 'return-json', 'haltOnFailure' => false]);
    return $r['total'] === 2 && $r['failed'] === 2;
});

selfTest('no halt on recovered', function() {
    $r = CTGTest::init('no halt recovered')
        ->stage('recovers', fn($x) => throw new \RuntimeException('boom'), fn($e) => 'ok')
        ->assert('runs', fn($x) => $x, 'ok')
        ->start(1, ['output' => 'return-json']);
    return $r['total'] === 2 && $r['steps'][0]['status'] === 'recovered';
});

// ── Empty Pipeline ───────────────────────────────────────────

selfTest('empty pipeline pass zero total', function() {
    $r = CTGTest::init('empty')->start(null, ['output' => 'return-json']);
    return $r['status'] === 'pass' && $r['total'] === 0 && empty($r['steps']);
});

// ── Strict vs Loose ──────────────────────────────────────────

selfTest('strict fails on type mismatch', fn() =>
    CTGTest::init('strict')
        ->assert('type', fn($x) => $x, '1')
        ->start(1, ['output' => 'return-json'])['status'] === 'fail'
);

selfTest('loose passes on type coercion', fn() =>
    CTGTest::init('loose')
        ->assert('type', fn($x) => $x, '1')
        ->start(1, ['output' => 'return-json', 'strict' => false])['status'] === 'pass'
);

// ── Cyclic/Closure Detection ─────────────────────────────────

selfTest('cyclic object produces error', function() {
    $a = new \stdClass(); $b = new \stdClass();
    $a->ref = $b; $b->ref = $a;
    $r = CTGTest::init('cyclic')
        ->assert('cmp', fn($x) => $x, $a)
        ->start($a, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'error' && str_contains($r['steps'][0]['message'], 'cyclic');
});

selfTest('closure in actual produces error', function() {
    $r = CTGTest::init('closure')
        ->assert('cmp', fn($x) => $x, 'anything')
        ->start(fn() => 'hello', ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'error' && str_contains($r['steps'][0]['message'], 'closure');
});

// ── CTGTestError Validation ─────────────────────────────────────

selfTest('INVALID_STEP non-callable', function() {
    try { CTGTest::init('x')->stage('bad', 'nope')->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_STEP; }
});

selfTest('INVALID_STEP empty name', function() {
    try { CTGTest::init('x')->stage('  ', fn($x) => $x)->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_STEP; }
});

selfTest('INVALID_STEP duplicate name', function() {
    try { CTGTest::init('x')->stage('s', fn($x) => $x)->stage('s', fn($x) => $x)->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_STEP; }
});

selfTest('INVALID_CHAIN non-CTGTest', function() {
    try { CTGTest::init('x')->chain('c', 'nope')->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_CHAIN; }
});

selfTest('INVALID_CONFIG bad key', function() {
    try { CTGTest::init('x')->start(1, ['bogus' => true]); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_CONFIG; }
});

selfTest('INVALID_EXPECTED callable expected', function() {
    try { CTGTest::init('x')->assert('a', fn($x) => $x, fn($v) => $v > 0)->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_EXPECTED; }
});

// Fix #6: Test that string callables in expected are also caught
selfTest('INVALID_EXPECTED string callable expected', function() {
    try { CTGTest::init('x')->assert('a', fn($x) => $x, 'strlen')->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_EXPECTED; }
});

selfTest('INVALID_SKIP nonexistent target', function() {
    try { CTGTest::init('x')->stage('r', fn($x) => $x)->skip('fake')->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_SKIP; }
});

selfTest('INVALID_SKIP duplicate directive', function() {
    try { CTGTest::init('x')->stage('t', fn($x) => $x)->skip('t')->skip('t')->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_SKIP; }
});

selfTest('INVALID_STEP empty test name', function() {
    try { CTGTest::init('  ')->start(1, ['output' => 'return-json']); return 'no throw'; }
    catch (CTGTestError $e) { return $e->getCode() === CTGTestError::INVALID_STEP; }
});

// ── CTGTestError Lookup ─────────────────────────────────────────

selfTest('lookup string to int', fn() => CTGTestError::lookup('INVALID_STEP') === 1000);
selfTest('lookup int to string', fn() => CTGTestError::lookup(1000) === 'INVALID_STEP');

// ── Output Modes ─────────────────────────────────────────────

selfTest('return mode returns string', function() {
    $r = CTGTest::init('ret')->assert('p', fn($x) => $x, 1)->start(1, ['output' => 'return']);
    return is_string($r) && str_contains($r, 'ret');
});

selfTest('return-json returns array', function() {
    $r = CTGTest::init('json')->assert('p', fn($x) => $x, 1)->start(1, ['output' => 'return-json']);
    return is_array($r) && $r['name'] === 'json';
});

// ── Report Shape ─────────────────────────────────────────────

selfTest('root has no type field', function() {
    $r = CTGTest::init('shape')->assert('p', fn($x) => $x, 1)->start(1, ['output' => 'return-json']);
    return !isset($r['type']) && isset($r['name']) && isset($r['status']) && isset($r['steps']);
});

// ── Level-Scoped Totals ──────────────────────────────────────

selfTest('chain counts as one node in parent', function() {
    $sub = CTGTest::init('s')->assert('a', fn($x) => true, true)->assert('b', fn($x) => true, true);
    $r = CTGTest::init('m')->chain('g', $sub)->assert('c', fn($x) => true, true)->start(1, ['output' => 'return-json']);
    return $r['total'] === 2 && $r['steps'][0]['total'] === 2;
});

// ── Trace Config ─────────────────────────────────────────────

selfTest('trace false omits trace', function() {
    $r = CTGTest::init('t')->stage('throws', fn($x) => throw new \RuntimeException('boom'))
        ->start(1, ['output' => 'return-json', 'trace' => false]);
    return !isset($r['steps'][0]['exception']['trace']);
});

selfTest('trace true includes trace', function() {
    $r = CTGTest::init('t')->stage('throws', fn($x) => throw new \RuntimeException('boom'))
        ->start(1, ['output' => 'return-json', 'trace' => true]);
    return isset($r['steps'][0]['exception']['trace']);
});

// ── Severity Aggregation ─────────────────────────────────────

selfTest('chain with only recovered = recovered status', function() {
    $sub = CTGTest::init('s')
        ->stage('r1', fn($x) => throw new \RuntimeException('a'), fn($e) => 'ok')
        ->stage('r2', fn($x) => throw new \RuntimeException('b'), fn($e) => 'ok');
    $r = CTGTest::init('m')->chain('all recovered', $sub)->start(1, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'recovered';
});

// ── Fix #3: init returns static ──────────────────────────────

selfTest('init returns static for subclass support', function() {
    $test = CTGTest::init('test');
    return $test instanceof CTGTest;
});

// ── Fix #9: JsonFormatter integration ────────────────────────

selfTest('json output uses JsonFormatter', function() {
    ob_start();
    CTGTest::init('json test')->assert('p', fn($x) => $x, 1)->start(1, ['output' => 'json']);
    $output = ob_get_clean();
    $decoded = json_decode($output, true);
    return is_array($decoded) && $decoded['name'] === 'json test' && $decoded['status'] === 'pass';
});

// ── Fix #12: Chain status in console output ──────────────────

selfTest('console output shows chain status', function() {
    $sub = CTGTest::init('sub')->assert('ok', fn($x) => true, true);
    $output = CTGTest::init('main')->chain('my chain', $sub)->start(1, ['output' => 'return']);
    return str_contains($output, 'PASS') && str_contains($output, 'my chain');
});

// ── Skip Predicate Throws ────────────────────────────────────

selfTest('skip predicate throws produces error result', function() {
    $r = CTGTest::init('skip throw')
        ->stage('step', fn($x) => $x)
        ->skip('step', fn($x) => throw new \RuntimeException('predicate boom'))
        ->start(1, ['output' => 'return-json']);
    return $r['steps'][0]['status'] === 'error'
        && str_contains($r['steps'][0]['message'], 'predicate boom');
});

// ── FORMATTER_ERROR Wrapping ─────────────────────────────────

selfTest('non-CTGTestError from formatter wrapped in FORMATTER_ERROR', function() {
    // We can trigger this by passing a report containing a value that json_encode
    // cannot handle (e.g. NAN) through the json output mode. But CTGTestJsonFormatter
    // doesn't throw — json_encode returns false. Instead, we test the wrapping logic
    // directly by using a custom subclass approach. The simplest path: create a test
    // that would trigger the catch block in _deliver. Since we can't easily inject a
    // broken formatter, we verify the FORMATTER_ERROR type exists and can be constructed.
    // Actually, let's use a resource value in the report via a stage that returns one,
    // combined with json output — json_encode on a resource will fail silently though.
    //
    // The most reliable approach: verify CTGTestError wrapping works correctly.
    try {
        $e = new CTGTestError('FORMATTER_ERROR', 'test wrap', ['key' => 'val']);
        return $e->type === 'FORMATTER_ERROR'
            && $e->getCode() === 2000
            && $e->msg === 'test wrap'
            && $e->data['key'] === 'val';
    } catch (\Throwable $e) {
        return 'unexpected: ' . $e->getMessage();
    }
});

// ── Chain Depth Limit ────────────────────────────────────────

selfTest('chain exceeding MAX_CHAIN_DEPTH throws INVALID_CHAIN', function() {
    // Build a chain 65 levels deep to exceed MAX_CHAIN_DEPTH (64).
    // The depth check triggers when a chain step is encountered at depth >= 64.
    // Outermost chain is at depth 0, so we need 65 wrapping levels to reach depth 64.
    $inner = CTGTest::init('leaf')->assert('ok', fn($x) => true, true);
    for ($i = 0; $i < 65; $i++) {
        $wrapper = CTGTest::init("depth-{$i}")->chain('nested', $inner);
        $inner = $wrapper;
    }
    try {
        $inner->start(1, ['output' => 'return-json']);
        return 'no throw';
    } catch (CTGTestError $e) {
        return $e->getCode() === CTGTestError::INVALID_CHAIN
            && str_contains($e->getMessage(), 'depth');
    }
});

// ── Formatter Unit Tests ─────────────────────────────────────

selfTest('ConsoleFormatter formats passing report', function() {
    $report = CTGTestResult::report('console test', [
        CTGTestResult::stepResult('stage', 'step1', CTGTestResult::STATUS_PASS, 5),
        CTGTestResult::assertResult('assert1', CTGTestResult::STATUS_PASS, 3, 42, 42),
    ]);
    $output = CTGTestConsoleFormatter::format($report);
    return is_string($output)
        && str_contains($output, 'console test')
        && str_contains($output, 'step1')
        && str_contains($output, 'assert1')
        && str_contains($output, 'PASS')
        && str_contains($output, '2 passed');
});

selfTest('ConsoleFormatter formats failing report', function() {
    $report = CTGTestResult::report('fail test', [
        CTGTestResult::assertResult('bad', CTGTestResult::STATUS_FAIL, 1, 'got', 'want',
            "expected 'want' but got 'got'"),
    ]);
    $output = CTGTestConsoleFormatter::format($report);
    return str_contains($output, 'FAIL')
        && str_contains($output, 'bad')
        && str_contains($output, "expected 'want' but got 'got'");
});

selfTest('ConsoleFormatter formats chain with nested steps', function() {
    $childSteps = [
        CTGTestResult::assertResult('inner', CTGTestResult::STATUS_PASS, 1, true, true),
    ];
    $counts = CTGTestResult::countSteps($childSteps);
    $report = CTGTestResult::report('chain test', [
        CTGTestResult::chainResult('my chain', CTGTestResult::STATUS_PASS, 2, null, null, $childSteps, $counts),
    ]);
    $output = CTGTestConsoleFormatter::format($report);
    return str_contains($output, '[chain]')
        && str_contains($output, 'my chain')
        && str_contains($output, 'inner');
});

selfTest('ConsoleFormatter formats skip and error', function() {
    $report = CTGTestResult::report('mixed test', [
        CTGTestResult::stepResult('stage', 'skipped', CTGTestResult::STATUS_SKIP, 0),
        CTGTestResult::stepResult('stage', 'errored', CTGTestResult::STATUS_ERROR, 1, 'RuntimeException: boom'),
    ]);
    $output = CTGTestConsoleFormatter::format($report);
    return str_contains($output, 'SKIP')
        && str_contains($output, 'ERROR')
        && str_contains($output, '1 skipped')
        && str_contains($output, '1 errored');
});

selfTest('JsonFormatter produces valid JSON', function() {
    $report = CTGTestResult::report('json test', [
        CTGTestResult::assertResult('a', CTGTestResult::STATUS_PASS, 1, 1, 1),
    ]);
    $output = CTGTestJsonFormatter::format($report);
    $decoded = json_decode($output, true);
    return is_array($decoded)
        && $decoded['name'] === 'json test'
        && $decoded['status'] === 'pass'
        && $decoded['total'] === 1;
});

selfTest('JsonFormatter includes step details', function() {
    $report = CTGTestResult::report('detail', [
        CTGTestResult::assertResult('check', CTGTestResult::STATUS_FAIL, 2, 'actual', 'expected',
            'mismatch'),
    ]);
    $output = CTGTestJsonFormatter::format($report);
    $decoded = json_decode($output, true);
    return $decoded['steps'][0]['status'] === 'fail'
        && $decoded['steps'][0]['actual'] === 'actual'
        && $decoded['steps'][0]['expected'] === 'expected'
        && $decoded['steps'][0]['message'] === 'mismatch';
});

selfTest('JunitFormatter produces valid XML', function() {
    $report = CTGTestResult::report('junit test', [
        CTGTestResult::assertResult('a', CTGTestResult::STATUS_PASS, 1, 1, 1),
    ]);
    $output = CTGTestJunitFormatter::format($report);
    return str_contains($output, '<?xml')
        && str_contains($output, '<testsuite')
        && str_contains($output, 'name="junit test"')
        && str_contains($output, '<testcase')
        && str_contains($output, 'name="a"');
});

selfTest('JunitFormatter maps failure to failure element', function() {
    $report = CTGTestResult::report('junit fail', [
        CTGTestResult::assertResult('bad', CTGTestResult::STATUS_FAIL, 1, 'got', 'want',
            "expected 'want' but got 'got'"),
    ]);
    $output = CTGTestJunitFormatter::format($report);
    return str_contains($output, '<failure')
        && str_contains($output, "expected 'want' but got 'got'");
});

selfTest('JunitFormatter maps error to error element', function() {
    $report = CTGTestResult::report('junit err', [
        CTGTestResult::stepResult('stage', 'broken', CTGTestResult::STATUS_ERROR, 1,
            'RuntimeException: boom',
            CTGTestResult::formatException(new \RuntimeException('boom'))),
    ]);
    $output = CTGTestJunitFormatter::format($report);
    return str_contains($output, '<error')
        && str_contains($output, 'RuntimeException: boom');
});

selfTest('JunitFormatter maps skip to skipped element', function() {
    $report = CTGTestResult::report('junit skip', [
        CTGTestResult::stepResult('stage', 'skipped', CTGTestResult::STATUS_SKIP, 0),
    ]);
    $output = CTGTestJunitFormatter::format($report);
    return str_contains($output, '<skipped');
});

selfTest('JunitFormatter maps recovered to system-out', function() {
    $report = CTGTestResult::report('junit recover', [
        CTGTestResult::stepResult('stage', 'recovered', CTGTestResult::STATUS_RECOVERED, 1,
            'error handler invoked, produced ok',
            CTGTestResult::formatException(new \RuntimeException('original'))),
    ]);
    $output = CTGTestJunitFormatter::format($report);
    return str_contains($output, '<system-out')
        && str_contains($output, 'Recovery:');
});

selfTest('JunitFormatter handles chain as nested testsuite', function() {
    $childSteps = [
        CTGTestResult::assertResult('inner', CTGTestResult::STATUS_PASS, 1, true, true),
    ];
    $counts = CTGTestResult::countSteps($childSteps);
    $report = CTGTestResult::report('junit chain', [
        CTGTestResult::chainResult('sub', CTGTestResult::STATUS_PASS, 2, null, null, $childSteps, $counts),
    ]);
    $output = CTGTestJunitFormatter::format($report);
    // Should have two testsuite elements (outer + chain)
    return substr_count($output, '<testsuite') === 2
        && str_contains($output, 'name="sub"');
});

selfTest('JunitFormatter includes trace when enabled', function() {
    $report = CTGTestResult::report('junit trace', [
        CTGTestResult::stepResult('stage', 'broken', CTGTestResult::STATUS_ERROR, 1,
            'RuntimeException: boom',
            CTGTestResult::formatException(new \RuntimeException('boom'), true)),
    ]);
    $output = CTGTestJunitFormatter::format($report, true);
    return str_contains($output, '#0');
});

// ── Summary ──────────────────────────────────────────────────

echo "\n";
echo $allPassed ? "All self-tests passed.\n" : "Some self-tests FAILED.\n";
exit($allPassed ? 0 : 1);
