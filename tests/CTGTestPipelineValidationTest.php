<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestStatus;
use CTG\Test\CTGTestError;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestPipelineValidationTest extends TestCase
{
    // Spec 4.3: Empty pipeline label throws INVALID_OPERATION
    public function testEmptyPipelineLabelThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Whitespace-only pipeline label throws INVALID_OPERATION (label trimming)
    public function testWhitespaceOnlyPipelineLabelThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('   ')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Empty operation label throws INVALID_OPERATION
    public function testEmptyOperationLabelThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('pipeline')
            ->stage('', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Whitespace-only operation label throws INVALID_OPERATION (label trimming)
    public function testWhitespaceOnlyOperationLabelThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('pipeline')
            ->stage('  ', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Duplicate label in same pipeline throws INVALID_OPERATION
    public function testDuplicateLabelThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('pipeline')
            ->stage('same name', fn(CTGTestState $s) => $s->getSubject())
            ->stage('same name', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);
    }

    // Spec 4.3: Non-callable stage fn throws INVALID_OPERATION
    public function testNonCallableStageFnThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('pipeline')
            ->stage('bad fn', 'not a closure')
            ->start(null);
    }

    // Spec 4.3: Non-callable assert fn throws INVALID_OPERATION
    public function testNonCallableAssertFnThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('pipeline')
            ->assert('bad fn', 42, CTGTestPredicates::equals(42))
            ->start(null);
    }

    // Spec Q5: Labels unique across operations only (stages, asserts, chains)
    public function testDuplicateLabelAcrossStageAndAssertThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        CTGTest::init('pipeline')
            ->stage('shared name', fn(CTGTestState $s) => $s->getSubject())
            ->assert(
                'shared name',
                fn(CTGTestState $s) => $s->getSubject(),
                CTGTestPredicates::equals(null)
            )
            ->start(null);
    }

    // Spec Q5: Labels unique across operations — chain label conflicts with stage label
    public function testDuplicateLabelAcrossStageAndChainThrowsInvalidOperation(): void
    {
        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);

        $sub = CTGTest::init('sub')
            ->stage('inner', fn(CTGTestState $s) => $s->getSubject());

        CTGTest::init('pipeline')
            ->stage('shared name', fn(CTGTestState $s) => $s->getSubject())
            ->chain('shared name', $sub)
            ->start(null);
    }

    // Spec 4.3: All validation happens in start(), not in builder methods
    public function testValidationHappensInStartNotInBuilderMethods(): void
    {
        // Builder methods should NOT throw even with invalid args
        $pipeline = CTGTest::init('pipeline')
            ->stage('op', 'not a closure');

        // The pipeline object was created — error should come from start()
        $this->assertInstanceOf(CTGTest::class, $pipeline);

        $this->expectException(CTGTestError::class);
        $this->expectExceptionCode(CTGTestError::INVALID_OPERATION);
        $pipeline->start(null);
    }

    // Spec Q5: Skip directives do not participate in label uniqueness
    public function testSkipTargetLabelDoesNotConflictWithOperationLabel(): void
    {
        // A skip targeting 'my op' should not conflict with the operation 'my op'
        $state = CTGTest::init('pipeline')
            ->skip('my op', fn(CTGTestState $s) => false)
            ->stage('my op', fn(CTGTestState $s) => $s->getSubject())
            ->start(42);

        // Operation runs normally (skip condition returned false)
        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame(CTGTestStatus::PASS, $results[0]->_status);
    }
}
