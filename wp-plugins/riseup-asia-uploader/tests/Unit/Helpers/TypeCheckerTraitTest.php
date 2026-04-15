<?php
/**
 * TypeCheckerTraitTest — Tests for TypeCheckerTrait validation helpers.
 *
 * @package RiseupAsia\Tests\Unit\Helpers
 */

namespace RiseupAsia\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Helpers\Traits\TypeCheckerTrait;
use RiseupAsia\Enums\PhpNativeType;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Concrete class to test the trait.
 */
class TypeCheckerTestSubject {
    use TypeCheckerTrait;

    public object $fileLogger;

    public function __construct() {
        $this->fileLogger = new class {
            public function warning(string $msg, array $ctx = []): void {}
        };
    }

    public function publicIsString(mixed $value): bool { return $this->isString($value); }
    public function publicIsInteger(mixed $value): bool { return $this->isInteger($value); }
    public function publicIsArray(mixed $value): bool { return $this->isArray($value); }
    public function publicIsBoolean(mixed $value): bool { return $this->isBoolean($value); }
    public function publicIsNumeric(mixed $value): bool { return $this->isNumeric($value); }
    public function publicIsNonEmptyString(mixed $value): bool { return $this->isNonEmptyString($value); }
    public function publicIsPositiveInteger(mixed $value): bool { return $this->isPositiveInteger($value); }
    public function publicExtractValidBody(WP_REST_Request $req): ?array { return $this->extractValidBody($req); }
    public function publicValidationError(string $msg, WP_REST_Request $req): WP_REST_Response { return $this->validationError($msg, $req); }
    public function publicRequireString(array $body, string $field, WP_REST_Request $req): string|WP_REST_Response { return $this->requireString($body, $field, $req); }
    public function publicRequireInteger(array $body, string $field, WP_REST_Request $req): int|WP_REST_Response { return $this->requireInteger($body, $field, $req); }
}

class TypeCheckerTraitTest extends TestCase
{
    private TypeCheckerTestSubject $subject;

    protected function setUp(): void
    {
        $this->subject = new TypeCheckerTestSubject();
    }

    // ── isString ──

    public function testIsStringWithString(): void
    {
        $this->assertTrue($this->subject->publicIsString('hello'));
    }

    public function testIsStringWithEmptyString(): void
    {
        $this->assertTrue($this->subject->publicIsString(''));
    }

    public function testIsStringWithInteger(): void
    {
        $this->assertFalse($this->subject->publicIsString(42));
    }

    public function testIsStringWithNull(): void
    {
        $this->assertFalse($this->subject->publicIsString(null));
    }

    public function testIsStringWithArray(): void
    {
        $this->assertFalse($this->subject->publicIsString([]));
    }

    // ── isInteger ──

    public function testIsIntegerWithInt(): void
    {
        $this->assertTrue($this->subject->publicIsInteger(42));
    }

    public function testIsIntegerWithZero(): void
    {
        $this->assertTrue($this->subject->publicIsInteger(0));
    }

    public function testIsIntegerWithFloat(): void
    {
        $this->assertFalse($this->subject->publicIsInteger(3.14));
    }

    public function testIsIntegerWithString(): void
    {
        $this->assertFalse($this->subject->publicIsInteger('42'));
    }

    // ── isArray ──

    public function testIsArrayWithArray(): void
    {
        $this->assertTrue($this->subject->publicIsArray([1, 2, 3]));
    }

    public function testIsArrayWithEmptyArray(): void
    {
        $this->assertTrue($this->subject->publicIsArray([]));
    }

    public function testIsArrayWithString(): void
    {
        $this->assertFalse($this->subject->publicIsArray('not array'));
    }

    // ── isBoolean ──

    public function testIsBooleanWithTrue(): void
    {
        $this->assertTrue($this->subject->publicIsBoolean(true));
    }

    public function testIsBooleanWithFalse(): void
    {
        $this->assertTrue($this->subject->publicIsBoolean(false));
    }

    public function testIsBooleanWithInt(): void
    {
        $this->assertFalse($this->subject->publicIsBoolean(1));
    }

    // ── isNumeric ──

    public function testIsNumericWithInt(): void
    {
        $this->assertTrue($this->subject->publicIsNumeric(42));
    }

    public function testIsNumericWithFloat(): void
    {
        $this->assertTrue($this->subject->publicIsNumeric(3.14));
    }

    public function testIsNumericWithString(): void
    {
        $this->assertFalse($this->subject->publicIsNumeric('42'));
    }

    // ── isNonEmptyString ──

    public function testIsNonEmptyStringWithValue(): void
    {
        $this->assertTrue($this->subject->publicIsNonEmptyString('hello'));
    }

    public function testIsNonEmptyStringWithEmpty(): void
    {
        $this->assertFalse($this->subject->publicIsNonEmptyString(''));
    }

    public function testIsNonEmptyStringWithInt(): void
    {
        $this->assertFalse($this->subject->publicIsNonEmptyString(42));
    }

    // ── isPositiveInteger ──

    public function testIsPositiveIntegerWithPositive(): void
    {
        $this->assertTrue($this->subject->publicIsPositiveInteger(1));
    }

    public function testIsPositiveIntegerWithZero(): void
    {
        $this->assertFalse($this->subject->publicIsPositiveInteger(0));
    }

    public function testIsPositiveIntegerWithNegative(): void
    {
        $this->assertFalse($this->subject->publicIsPositiveInteger(-5));
    }

    public function testIsPositiveIntegerWithFloat(): void
    {
        $this->assertFalse($this->subject->publicIsPositiveInteger(1.5));
    }

    // ── extractValidBody ──

    public function testExtractValidBodyWithValidJson(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_json_params')->willReturn(['key' => 'value']);

        $result = $this->subject->publicExtractValidBody($request);

        $this->assertNotNull($result);
        $this->assertSame('value', $result['key']);
    }

    public function testExtractValidBodyWithNullReturnsNull(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_json_params')->willReturn(null);

        $this->assertNull($this->subject->publicExtractValidBody($request));
    }

    // ── requireString ──

    public function testRequireStringWithValidField(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $body = ['name' => 'test'];

        $result = $this->subject->publicRequireString($body, 'name', $request);

        $this->assertSame('test', $result);
    }

    public function testRequireStringWithMissingField(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/test');
        $request->method('get_method')->willReturn('POST');

        $result = $this->subject->publicRequireString([], 'name', $request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    public function testRequireStringWithEmptyString(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/test');
        $request->method('get_method')->willReturn('POST');

        $result = $this->subject->publicRequireString(['name' => ''], 'name', $request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    // ── requireInteger ──

    public function testRequireIntegerWithValidField(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $body = ['count' => 42];

        $result = $this->subject->publicRequireInteger($body, 'count', $request);

        $this->assertSame(42, $result);
    }

    public function testRequireIntegerWithMissingField(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/test');
        $request->method('get_method')->willReturn('POST');

        $result = $this->subject->publicRequireInteger([], 'count', $request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    public function testRequireIntegerWithStringField(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/test');
        $request->method('get_method')->willReturn('POST');

        $result = $this->subject->publicRequireInteger(['count' => 'abc'], 'count', $request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    // ── validationError ──

    public function testValidationErrorReturns400Response(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_route')->willReturn('/api/test');
        $request->method('get_method')->willReturn('POST');

        $response = $this->subject->publicValidationError('Bad input', $request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(400, $response->get_status());
    }
}
