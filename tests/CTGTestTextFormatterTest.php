<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTest;
use CTG\Test\CTGTestState;
use CTG\Test\CTGTestResult;
use CTG\Test\CTGTestStatus;
use CTG\Test\Formatters\CTGTestTextFormatter;
use CTG\Test\Formatters\CTGTestFormatterInterface;
use CTG\Test\Predicates\CTGTestPredicates;

class CTGTestTextFormatterTest extends TestCase
{
    // Spec 6: format() accepts CTGTestState and returns string
    public function testFormatAcceptsStateAndReturnsString(): void
    {
        $state = CTGTestState::init('test pipeline', null);
        $result = CTGTestTextFormatter::format($state);
        $this->assertIsString($result);
    }

    // Spec 6: Output contains "Pipeline: {label}" header
    public function testOutputContainsPipelineHeader(): void
    {
        $state = CTGTestState::init('my pipeline', null);
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('Pipeline: my pipeline', $output);
    }

    // Spec 6: Output contains [PASS] bracket
    public function testOutputContainsPassBracket(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(['op1'], CTGTestStatus::PASS));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('[PASS]', $output);
    }

    // Spec 6: Output contains [FAIL] bracket
    public function testOutputContainsFailBracket(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['op1'],
            CTGTestStatus::FAIL,
            'actual',
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('[FAIL]', $output);
    }

    // Spec 6: Output contains [ERROR] bracket
    public function testOutputContainsErrorBracket(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(
            ['op1'],
            CTGTestStatus::ERROR,
            new \RuntimeException('broke')
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('[ERROR]', $output);
    }

    // Spec 6: Output contains [SKIPPED] bracket
    public function testOutputContainsSkippedBracket(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::skippedResult(['op1']));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('[SKIPPED]', $output);
    }

    // Spec 6: FAIL results show computed and expected values
    public function testFailResultShowsComputedAndExpected(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check value'],
            CTGTestStatus::FAIL,
            'actual',
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString("computed: 'actual'", $output);
        $this->assertStringContainsString("expected: 'expected'", $output);
    }

    // Spec 6: ERROR results show error class and message
    public function testErrorResultShowsErrorClassAndMessage(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(
            ['failing op'],
            CTGTestStatus::ERROR,
            new \RuntimeException('Connection refused')
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('error: RuntimeException: Connection refused', $output);
    }

    // Spec 6: Label arrays joined with " > "
    public function testLabelArraysJoinedWithArrow(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(
            ['chain label', 'inner op'],
            CTGTestStatus::PASS
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('chain label > inner op', $output);
    }

    // Spec 6: Summary line with pass/fail/skip/error counts
    public function testSummaryLineWithCounts(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(['op1'], CTGTestStatus::PASS));
        $state->addResult(CTGTestResult::assertResult(['op2'], CTGTestStatus::FAIL, 'a', 'b'));
        $state->addResult(CTGTestResult::skippedResult(['op3']));
        $state->addResult(CTGTestResult::stageResult(['op4'], CTGTestStatus::ERROR, new \RuntimeException('err')));

        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('1 passed', $output);
        $this->assertStringContainsString('1 failed', $output);
        $this->assertStringContainsString('1 skipped', $output);
        $this->assertStringContainsString('1 errored', $output);
        $this->assertStringContainsString('4 total', $output);
    }

    // Spec 6: CTGTestTextFormatter implements CTGTestFormatterInterface
    public function testImplementsFormatterInterface(): void
    {
        $this->assertTrue(
            in_array(
                CTGTestFormatterInterface::class,
                class_implements(CTGTestTextFormatter::class)
            )
        );
    }

    // Spec 6: Result line shows worst status — ERROR > FAIL > PASS
    public function testResultLineShowsWorstStatus(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(['op1'], CTGTestStatus::PASS));
        $state->addResult(CTGTestResult::assertResult(['op2'], CTGTestStatus::FAIL, 'a', 'b'));

        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('Result: FAIL', $output);
    }

    // Spec 6: Result line ERROR when any error present
    public function testResultLineShowsErrorWhenErrorPresent(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(['op1'], CTGTestStatus::PASS));
        $state->addResult(CTGTestResult::stageResult(['op2'], CTGTestStatus::ERROR, new \RuntimeException('err')));

        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('Result: ERROR', $output);
    }

    // Spec 6: Three-element label array joined correctly
    public function testThreeElementLabelArrayJoined(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::stageResult(
            ['outer', 'middle', 'inner'],
            CTGTestStatus::PASS
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('outer > middle > inner', $output);
    }

    // Spec 6: Value formatting — null displays as null
    public function testValueFormattingNull(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            null,
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('computed: null', $output);
    }

    // Spec 6: String values with newlines render as escape sequences, not literal whitespace
    public function testValueFormattingStringWithNewlineEscapesAsLiteral(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            "foo\nbar",
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        // The computed value should appear as 'foo\nbar' (literal backslash-n),
        // not as an actual newline that breaks the line-oriented output.
        $this->assertStringContainsString("computed: 'foo\\nbar'", $output);
        $this->assertStringNotContainsString("foo\nbar'", $output);
    }

    // Spec 6: String values with tabs and carriage returns render as escape sequences
    public function testValueFormattingStringWithTabAndCarriageReturn(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            "tab\there\rreturn",
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString("computed: 'tab\\there\\rreturn'", $output);
    }

    // Spec 6: String values with single quotes are escaped to avoid breaking the delimiter
    public function testValueFormattingStringWithSingleQuoteEscaped(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            "it's broken",
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString("computed: 'it\\'s broken'", $output);
    }

    // Spec 6: String values with backslashes are escaped
    public function testValueFormattingStringWithBackslashEscaped(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            'C:\\Users\\test',
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString("computed: 'C:\\\\Users\\\\test'", $output);
    }

    // Spec 6: NUL bytes and other control characters are rendered as \xHH
    // so they don't produce invisible or ambiguous output.
    public function testValueFormattingStringWithNulByteEscaped(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            "foo\x00bar",
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString("computed: 'foo\\x00bar'", $output);
    }

    // Spec 6: Other C0 control bytes (bell, backspace, escape, etc.) are escaped
    public function testValueFormattingStringWithMixedControlBytesEscaped(): void
    {
        $state = CTGTestState::init('test', null);
        $state->addResult(CTGTestResult::assertResult(
            ['check'],
            CTGTestStatus::FAIL,
            "a\x07b\x08c\x1Bd\x7Fe",
            'expected'
        ));
        $output = CTGTestTextFormatter::format($state);
        // bell (0x07), backspace (0x08), escape (0x1B), DEL (0x7F)
        $this->assertStringContainsString("computed: 'a\\x07b\\x08c\\x1Bd\\x7Fe'", $output);
    }

    // Spec 6: Result line shows VOID when all results are skipped
    public function testResultLineShowsVoidWhenAllResultsSkipped(): void
    {
        $state = CTGTestState::init('all skipped', null);
        $state->addResult(CTGTestResult::skippedResult(['op1']));
        $state->addResult(CTGTestResult::skippedResult(['op2']));

        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('Result: VOID', $output);
    }

    // Spec 2.2 + 2.5: start() stamps the pipeline label onto the state it returns
    public function testStartTransfersPipelineLabelToState(): void
    {
        $state = CTGTest::init('my pipeline label')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);

        $this->assertSame('my pipeline label', $state->getLabel());
    }

    // Spec 6: Formatter consumes state produced by start() and renders pipeline label in header
    public function testFormatterRendersPipelineLabelFromPipelineState(): void
    {
        $state = CTGTest::init('integration test pipeline')
            ->stage('op', fn(CTGTestState $s) => $s->getSubject())
            ->start(null);

        $output = CTGTestTextFormatter::format($state);
        $this->assertStringContainsString('Pipeline: integration test pipeline', $output);
    }
}
