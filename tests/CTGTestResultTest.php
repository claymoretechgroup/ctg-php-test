<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTestResult;
use CTG\Test\CTGTestStatus;

class CTGTestResultTest extends TestCase
{
    // Spec 2.3: stageResult creates result with correct fields
    public function testStageResultPassHasCorrectFields(): void
    {
        $result = CTGTestResult::stageResult(['my stage'], CTGTestStatus::PASS);

        $this->assertSame(['my stage'], $result->_label);
        $this->assertFalse($result->_skipped);
        $this->assertSame(CTGTestStatus::PASS, $result->_status);
        $this->assertNull($result->_computedValue);
        $this->assertNull($result->_expectedOutcome);
        $this->assertNull($result->_error);
    }

    // Spec 2.3: stageResult with ERROR status includes error
    public function testStageResultErrorHasErrorField(): void
    {
        $exception = new \RuntimeException('something broke');
        $result = CTGTestResult::stageResult(['failing stage'], CTGTestStatus::ERROR, $exception);

        $this->assertSame(['failing stage'], $result->_label);
        $this->assertFalse($result->_skipped);
        $this->assertSame(CTGTestStatus::ERROR, $result->_status);
        $this->assertNull($result->_computedValue);
        $this->assertNull($result->_expectedOutcome);
        $this->assertSame($exception, $result->_error);
    }

    // Spec 2.3: assertResult creates result with all fields populated
    public function testAssertResultPassHasAllFields(): void
    {
        $result = CTGTestResult::assertResult(
            ['my assert'],
            CTGTestStatus::PASS,
            42,
            42
        );

        $this->assertSame(['my assert'], $result->_label);
        $this->assertFalse($result->_skipped);
        $this->assertSame(CTGTestStatus::PASS, $result->_status);
        $this->assertSame(42, $result->_computedValue);
        $this->assertSame(42, $result->_expectedOutcome);
        $this->assertNull($result->_error);
    }

    // Spec 2.3: assertResult with FAIL status
    public function testAssertResultFailHasComputedAndExpected(): void
    {
        $result = CTGTestResult::assertResult(
            ['check value'],
            CTGTestStatus::FAIL,
            'actual',
            'expected'
        );

        $this->assertSame(CTGTestStatus::FAIL, $result->_status);
        $this->assertSame('actual', $result->_computedValue);
        $this->assertSame('expected', $result->_expectedOutcome);
        $this->assertNull($result->_error);
    }

    // Spec 2.3: assertResult with ERROR status includes error
    public function testAssertResultErrorHasErrorField(): void
    {
        $exception = new \RuntimeException('assert handler threw');
        $result = CTGTestResult::assertResult(
            ['broken assert'],
            CTGTestStatus::ERROR,
            null,
            null,
            $exception
        );

        $this->assertSame(CTGTestStatus::ERROR, $result->_status);
        $this->assertSame($exception, $result->_error);
    }

    // Spec 2.3: assertResult ERROR when predicate throws — computedValue populated, expectedOutcome populated
    public function testAssertResultErrorFromPredicateHasComputedAndExpected(): void
    {
        $exception = new \RuntimeException('predicate threw');
        $result = CTGTestResult::assertResult(
            ['predicate error'],
            CTGTestStatus::ERROR,
            'computed value',
            'expected value',
            $exception
        );

        $this->assertSame(CTGTestStatus::ERROR, $result->_status);
        $this->assertSame('computed value', $result->_computedValue);
        $this->assertSame('expected value', $result->_expectedOutcome);
        $this->assertSame($exception, $result->_error);
    }

    // Spec 2.3: skippedResult creates result with skipped=true, status=null, all others null
    public function testSkippedResultHasCorrectFields(): void
    {
        $result = CTGTestResult::skippedResult(['skipped op']);

        $this->assertSame(['skipped op'], $result->_label);
        $this->assertTrue($result->_skipped);
        $this->assertNull($result->_status);
        $this->assertNull($result->_computedValue);
        $this->assertNull($result->_expectedOutcome);
        $this->assertNull($result->_error);
    }

    // Spec 2.3: readonly properties are accessible
    public function testReadonlyPropertiesAreAccessible(): void
    {
        $result = CTGTestResult::stageResult(['test'], CTGTestStatus::PASS);

        // These should not throw — readonly properties are publicly readable
        $label = $result->_label;
        $skipped = $result->_skipped;
        $status = $result->_status;
        $computed = $result->_computedValue;
        $expected = $result->_expectedOutcome;
        $error = $result->_error;

        $this->assertIsArray($label);
        $this->assertIsBool($skipped);
    }

    // Spec 2.3: label is always an array of strings
    public function testLabelIsArrayOfStrings(): void
    {
        $result = CTGTestResult::stageResult(['chain', 'inner op'], CTGTestStatus::PASS);
        $this->assertSame(['chain', 'inner op'], $result->_label);
    }
}
