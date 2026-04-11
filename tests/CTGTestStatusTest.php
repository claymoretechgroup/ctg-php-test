<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTestStatus;

class CTGTestStatusTest extends TestCase
{
    // Spec 2.1: Three cases exist — PASS, FAIL, ERROR
    public function testPassCaseExists(): void
    {
        $this->assertInstanceOf(CTGTestStatus::class, CTGTestStatus::PASS);
    }

    // Spec 2.1: Three cases exist — PASS, FAIL, ERROR
    public function testFailCaseExists(): void
    {
        $this->assertInstanceOf(CTGTestStatus::class, CTGTestStatus::FAIL);
    }

    // Spec 2.1: Three cases exist — PASS, FAIL, ERROR
    public function testErrorCaseExists(): void
    {
        $this->assertInstanceOf(CTGTestStatus::class, CTGTestStatus::ERROR);
    }

    // Spec 2.1: Backed string enum — backing values are uppercase strings
    public function testPassBackingValue(): void
    {
        $this->assertSame('PASS', CTGTestStatus::PASS->value);
    }

    // Spec 2.1: Backed string enum — backing values are uppercase strings
    public function testFailBackingValue(): void
    {
        $this->assertSame('FAIL', CTGTestStatus::FAIL->value);
    }

    // Spec 2.1: Backed string enum — backing values are uppercase strings
    public function testErrorBackingValue(): void
    {
        $this->assertSame('ERROR', CTGTestStatus::ERROR->value);
    }

    // Spec 2.1: Exactly three cases — no RECOVERED case
    public function testExactlyThreeCases(): void
    {
        $cases = CTGTestStatus::cases();
        $this->assertCount(3, $cases);
    }

    // Spec 2.1: "RECOVERED is not a status value"
    public function testNoRecoveredCase(): void
    {
        $caseValues = array_map(fn($c) => $c->value, CTGTestStatus::cases());
        $this->assertNotContains('RECOVERED', $caseValues);
    }

    // Spec 2.1: "skipped is a bool field on RESULT, not a status"
    public function testNoSkipCase(): void
    {
        $caseValues = array_map(fn($c) => $c->value, CTGTestStatus::cases());
        $this->assertNotContains('SKIP', $caseValues);
        $this->assertNotContains('SKIPPED', $caseValues);
    }
}
