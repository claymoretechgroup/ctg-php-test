<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestResult;
use CTG\Test\CTGTestStatus;

class CTGTestStateTest extends TestCase
{
    // Spec 2.2: init() creates state with pipeline label and subject
    public function testInitCreatesStateWithLabelAndSubject(): void
    {
        $state = CTGTestState::init('test pipeline', 42);

        $this->assertInstanceOf(CTGTestState::class, $state);
        $this->assertSame('test pipeline', $state->getLabel());
        $this->assertSame(42, $state->getSubject());
    }

    // Spec 2.2: computed starts as null (VOID)
    public function testComputedStartsAsNull(): void
    {
        $state = CTGTestState::init('test', 'subject');
        $this->assertNull($state->getComputed());
    }

    // Spec 2.2: results starts as empty array
    public function testResultsStartsAsEmptyArray(): void
    {
        $state = CTGTestState::init('test', 'subject');
        $this->assertSame([], $state->getResults());
    }

    // Spec 2.2: setSubject mutates state
    public function testSetSubjectMutatesState(): void
    {
        $state = CTGTestState::init('test', 'original');
        $state->setSubject('updated');
        $this->assertSame('updated', $state->getSubject());
    }

    // Spec 2.2: setComputed mutates state
    public function testSetComputedMutatesState(): void
    {
        $state = CTGTestState::init('test', 'subject');
        $state->setComputed('computed value');
        $this->assertSame('computed value', $state->getComputed());
    }

    // Spec 2.2: getLabel reads the label
    public function testGetLabelReturnsLabel(): void
    {
        $state = CTGTestState::init('my label', null);
        $this->assertSame('my label', $state->getLabel());
    }

    // Spec 2.2: getSubject reads the subject
    public function testGetSubjectReturnsSubject(): void
    {
        $state = CTGTestState::init('test', [1, 2, 3]);
        $this->assertSame([1, 2, 3], $state->getSubject());
    }

    // Spec 2.2: getComputed reads computed
    public function testGetComputedReturnsComputed(): void
    {
        $state = CTGTestState::init('test', null);
        $state->setComputed(99);
        $this->assertSame(99, $state->getComputed());
    }

    // Spec 2.2: getResults reads results array
    public function testGetResultsReturnsResultsArray(): void
    {
        $state = CTGTestState::init('test', null);
        $this->assertIsArray($state->getResults());
    }

    // Spec 2.2: addResult appends to results array
    public function testAddResultAppendsToResults(): void
    {
        $state = CTGTestState::init('test', null);
        $result = CTGTestResult::stageResult(['op1'], CTGTestStatus::PASS);
        $state->addResult($result);

        $results = $state->getResults();
        $this->assertCount(1, $results);
        $this->assertSame($result, $results[0]);
    }

    // Spec 2.2: addResult appends multiple results in order
    public function testAddResultAppendsMultipleInOrder(): void
    {
        $state = CTGTestState::init('test', null);
        $r1 = CTGTestResult::stageResult(['first'], CTGTestStatus::PASS);
        $r2 = CTGTestResult::stageResult(['second'], CTGTestStatus::ERROR, new \RuntimeException('err'));
        $state->addResult($r1);
        $state->addResult($r2);

        $results = $state->getResults();
        $this->assertCount(2, $results);
        $this->assertSame($r1, $results[0]);
        $this->assertSame($r2, $results[1]);
    }

    // Spec 2.2: init returns static (supports subclassing)
    public function testInitReturnsStaticType(): void
    {
        $state = CTGTestState::init('test', null);
        $this->assertInstanceOf(CTGTestState::class, $state);
    }

    // Spec 2.2: subject can be any type (mixed)
    public function testSubjectAcceptsMixedTypes(): void
    {
        $stateNull = CTGTestState::init('test', null);
        $this->assertNull($stateNull->getSubject());

        $stateInt = CTGTestState::init('test', 42);
        $this->assertSame(42, $stateInt->getSubject());

        $stateArray = CTGTestState::init('test', ['a' => 1]);
        $this->assertSame(['a' => 1], $stateArray->getSubject());

        $stateObj = CTGTestState::init('test', new \stdClass());
        $this->assertInstanceOf(\stdClass::class, $stateObj->getSubject());
    }
}
