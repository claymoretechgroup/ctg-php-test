<?php
declare(strict_types=1);

namespace CTG\Test\Tests;

use PHPUnit\Framework\TestCase;
use CTG\Test\CTGTestError;

class CTGTestErrorTest extends TestCase
{
    // Spec 2.6: Constructor accepts type name (string) and creates error with correct code
    public function testConstructorWithTypeName(): void
    {
        $error = new CTGTestError('INVALID_OPERATION');
        $this->assertSame('INVALID_OPERATION', $error->type);
        $this->assertSame(1000, $error->getCode());
    }

    // Spec 2.6: Constructor accepts type code (int) and creates error with correct name
    public function testConstructorWithTypeCode(): void
    {
        $error = new CTGTestError(1000);
        $this->assertSame('INVALID_OPERATION', $error->type);
        $this->assertSame(1000, $error->getCode());
    }

    // Spec 2.6: Constructor with custom message
    public function testConstructorWithMessage(): void
    {
        $error = new CTGTestError('INVALID_OPERATION', 'bad label');
        $this->assertSame('bad label', $error->msg);
    }

    // Spec 2.6: Constructor with data
    public function testConstructorWithData(): void
    {
        $data = ['label' => '', 'operation_index' => 0];
        $error = new CTGTestError('INVALID_OPERATION', 'test', $data);
        $this->assertSame($data, $error->data);
    }

    // Spec 2.6, 5: lookup() returns code for name
    public function testLookupReturnsCodeForName(): void
    {
        $this->assertSame(1000, CTGTestError::lookup('INVALID_OPERATION'));
        $this->assertSame(1001, CTGTestError::lookup('INVALID_CHAIN'));
        $this->assertSame(1002, CTGTestError::lookup('INVALID_CONFIG'));
        $this->assertSame(1003, CTGTestError::lookup('INVALID_EXPECTED_OUTCOME'));
        $this->assertSame(1004, CTGTestError::lookup('INVALID_SKIP'));
        $this->assertSame(2000, CTGTestError::lookup('FORMATTER_ERROR'));
        $this->assertSame(2001, CTGTestError::lookup('RUNNER_ERROR'));
    }

    // Spec 2.6, 5: lookup() returns name for code
    public function testLookupReturnsNameForCode(): void
    {
        $this->assertSame('INVALID_OPERATION', CTGTestError::lookup(1000));
        $this->assertSame('INVALID_CHAIN', CTGTestError::lookup(1001));
        $this->assertSame('INVALID_CONFIG', CTGTestError::lookup(1002));
        $this->assertSame('INVALID_EXPECTED_OUTCOME', CTGTestError::lookup(1003));
        $this->assertSame('INVALID_SKIP', CTGTestError::lookup(1004));
        $this->assertSame('FORMATTER_ERROR', CTGTestError::lookup(2000));
        $this->assertSame('RUNNER_ERROR', CTGTestError::lookup(2001));
    }

    // Spec 2.6: All canonical error types exist with correct codes
    public function testAllCanonicalErrorTypesExist(): void
    {
        $this->assertSame(1000, CTGTestError::INVALID_OPERATION);
        $this->assertSame(1001, CTGTestError::INVALID_CHAIN);
        $this->assertSame(1002, CTGTestError::INVALID_CONFIG);
        $this->assertSame(1003, CTGTestError::INVALID_EXPECTED_OUTCOME);
        $this->assertSame(1004, CTGTestError::INVALID_SKIP);
        $this->assertSame(2000, CTGTestError::FORMATTER_ERROR);
        $this->assertSame(2001, CTGTestError::RUNNER_ERROR);
    }

    // Spec 2.5, 2.6: CHAIN_DEPTH_EXCEEDED exists at 1100
    public function testChainDepthExceededExistsAt1100(): void
    {
        $this->assertSame(1100, CTGTestError::CHAIN_DEPTH_EXCEEDED);
    }

    // Spec 2.6: CHAIN_DEPTH_EXCEEDED in lookup
    public function testChainDepthExceededLookup(): void
    {
        $this->assertSame(1100, CTGTestError::lookup('CHAIN_DEPTH_EXCEEDED'));
        $this->assertSame('CHAIN_DEPTH_EXCEEDED', CTGTestError::lookup(1100));
    }

    // Spec 2.6: Unknown type name throws InvalidArgumentException
    public function testUnknownTypeNameThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CTGTestError::lookup('NONEXISTENT_ERROR');
    }

    // Spec 2.6: Unknown type code throws InvalidArgumentException
    public function testUnknownTypeCodeThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CTGTestError::lookup(9999);
    }

    // Spec 2.6: CTGTestError extends \Exception
    public function testErrorExtendsException(): void
    {
        $error = new CTGTestError('INVALID_OPERATION');
        $this->assertInstanceOf(\Exception::class, $error);
    }

    // Spec 2.6: readonly properties are accessible
    public function testReadonlyProperties(): void
    {
        $error = new CTGTestError('INVALID_CONFIG', 'bad key', ['key' => 'foo']);
        $this->assertSame('INVALID_CONFIG', $error->type);
        $this->assertSame('bad key', $error->msg);
        $this->assertSame(['key' => 'foo'], $error->data);
    }

    // Spec 2.6: TYPES constant has all entries
    public function testTypesConstantHasAllEntries(): void
    {
        $types = CTGTestError::TYPES;
        $this->assertArrayHasKey('INVALID_OPERATION', $types);
        $this->assertArrayHasKey('INVALID_CHAIN', $types);
        $this->assertArrayHasKey('INVALID_CONFIG', $types);
        $this->assertArrayHasKey('INVALID_EXPECTED_OUTCOME', $types);
        $this->assertArrayHasKey('INVALID_SKIP', $types);
        $this->assertArrayHasKey('FORMATTER_ERROR', $types);
        $this->assertArrayHasKey('RUNNER_ERROR', $types);
        $this->assertArrayHasKey('CHAIN_DEPTH_EXCEEDED', $types);
    }
}
