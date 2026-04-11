<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\CTGTestError;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineChainTest extends TestCase
{
    // Spec Design Doc CHAIN: Chain runs sub-pipeline against same state
    public function testChainRunsSubPipelineAgainstSameState(): void
    {
        $subPipeline = CTGTest::init('sub')
            ->stage('increment', fn(CTGTestState $s) => $s->getSubject() + 1);

        $state = CTGTest::init('outer')
            ->chain('run sub', $subPipeline)
            ->start(10);

        // Subject should be modified by the chained pipeline
        $this->assertSame(11, $state->getSubject());
    }

    // Spec Design Doc Result Labeling: Chain results have label array prepended with chain label
    public function testChainResultsHaveChainLabelPrepended(): void
    {
        $subPipeline = CTGTest::init('sub')
            ->stage('inner op', fn(CTGTestState $s) => $s->getSubject());

        $state = CTGTest::init('outer')
            ->chain('my chain', $subPipeline)
            ->start(42);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(['my chain', 'inner op'], $results[0]->_label);
    }

    // Spec Design Doc Result Labeling: Chain that executes normally produces NO result entry of its own
    public function testChainProducesNoResultEntryOfItsOwn(): void
    {
        $subPipeline = CTGTest::init('sub')
            ->stage('op1', fn(CTGTestState $s) => $s->getSubject())
            ->stage('op2', fn(CTGTestState $s) => $s->getSubject());

        $state = CTGTest::init('outer')
            ->stage('before', fn(CTGTestState $s) => $s->getSubject())
            ->chain('chained', $subPipeline)
            ->stage('after', fn(CTGTestState $s) => $s->getSubject())
            ->start('x');

        $results = $state->getResults();
        // before + op1 + op2 + after = 4 results, no separate entry for chain itself
        $this->assertCount(4, $results);
        $this->assertSame(['before'], $results[0]->_label);
        $this->assertSame(['chained', 'op1'], $results[1]->_label);
        $this->assertSame(['chained', 'op2'], $results[2]->_label);
        $this->assertSame(['after'], $results[3]->_label);
    }

    // Spec Design Doc Result Labeling: Nested chains produce label arrays with multiple elements
    public function testNestedChainsProduceLabelArraysWithMultipleElements(): void
    {
        $innerPipeline = CTGTest::init('inner')
            ->stage('deep op', fn(CTGTestState $s) => $s->getSubject());

        $middlePipeline = CTGTest::init('middle')
            ->chain('nest', $innerPipeline);

        $state = CTGTest::init('outer')
            ->chain('level1', $middlePipeline)
            ->start('val');

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(['level1', 'nest', 'deep op'], $results[0]->_label);
    }

    // Spec Q6: Skipped chain produces single skipped result with [$chainLabel]
    public function testSkippedChainProducesSingleSkippedResult(): void
    {
        $subPipeline = CTGTest::init('sub')
            ->stage('should not run', fn(CTGTestState $s) => $s->getSubject());

        $state = CTGTest::init('outer')
            ->skip('skipped chain')
            ->chain('skipped chain', $subPipeline)
            ->start(null);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(['skipped chain'], $results[0]->_label);
        $this->assertTrue($results[0]->_skipped);
        $this->assertNull($results[0]->_status);
    }

    // Spec Q4: Chain failure halts outer pipeline when haltOnFailure=true
    public function testChainFailureHaltsOuterPipelineWhenHaltOnFailure(): void
    {
        $failingSubPipeline = CTGTest::init('sub')
            ->assert(
                'will fail',
                fn(CTGTestState $s) => 'wrong',
                CTGTestPredicates::equals('right')
            );

        $state = CTGTest::init('outer')
            ->chain('failing chain', $failingSubPipeline)
            ->stage('should not run', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['haltOnFailure' => true]);

        $results = $state->getResults();
        // Only the failing assert from the chain — outer pipeline halts
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::FAIL, $results[0]->_status);
        $this->assertSame(['failing chain', 'will fail'], $results[0]->_label);
    }

    // Spec 4.3: Chain target must be CTGTest — non-pipeline throws INVALID_CHAIN in start()
    public function testChainNonPipelineThrowsInvalidChain(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_CHAIN);

        CTGTest::init('bad chain')
            ->chain('not a pipeline', 'string value')
            ->start(null);
    }

    // Spec Q4: Chain with haltOnFailure=false allows outer pipeline to continue
    public function testChainFailureDoesNotHaltWhenHaltOnFailureFalse(): void
    {
        $failingSubPipeline = CTGTest::init('sub')
            ->assert(
                'will fail',
                fn(CTGTestState $s) => 'wrong',
                CTGTestPredicates::equals('right')
            );

        $state = CTGTest::init('outer')
            ->chain('failing chain', $failingSubPipeline)
            ->stage('should run', fn(CTGTestState $s) => $s->getSubject())
            ->start(null, ['haltOnFailure' => false]);

        $results = $state->getResults();
        // Both the failing assert from chain AND the stage after
        $this->assertCount(2, $results);
        $this->assertSame(CTGTestStatus::FAIL, $results[0]->_status);
        $this->assertSame(CTGTestStatus::PASS, $results[1]->_status);
    }

    // Spec 2.5: CHAIN_DEPTH_EXCEEDED thrown when chain nesting exceeds MAX_CHAIN_DEPTH (64)
    public function testChainDepthExceededThrowsWhenNestingExceedsLimit(): void
    {
        // Build a chain nested 65 levels deep — one past the MAX_CHAIN_DEPTH (64)
        $deepest = CTGTest::init('leaf')
            ->stage('noop', fn(CTGTestState $s) => $s->getSubject());

        for ($i = 0; $i < 65; $i++) {
            $wrapper = CTGTest::init("level {$i}")
                ->chain("nest {$i}", $deepest);
            $deepest = $wrapper;
        }

        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::CHAIN_DEPTH_EXCEEDED);

        $deepest->start(null);
    }

    // Spec 2.5: Chains nested within MAX_CHAIN_DEPTH execute without error
    public function testChainDepthAtLimitExecutesWithoutError(): void
    {
        // Build a chain nested exactly at MAX_CHAIN_DEPTH (64)
        $deepest = CTGTest::init('leaf')
            ->stage('noop', fn(CTGTestState $s) => 'reached');

        for ($i = 0; $i < 63; $i++) {
            $wrapper = CTGTest::init("level {$i}")
                ->chain("nest {$i}", $deepest);
            $deepest = $wrapper;
        }

        $state = $deepest->start(null);
        $this->assertSame('reached', $state->getSubject());
    }
}
