<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

/**
 * Tests fail-fast validation patterns following Phase 9 §9.9.
 *
 * Uses an anonymous class to simulate handler validation logic
 * without requiring a full WordPress environment.
 */
final class ValidationPatternTest extends TestCase
{
    private object $handler;

    protected function setUp(): void
    {
        $this->handler = new class {
            /**
             * Validate a "create transaction" request body.
             *
             * @param array<string, mixed>|null $body
             * @return array{valid: bool, error: string|null, data: array<string, mixed>}
             */
            public function validateCreateTransaction(?array $body): array
            {
                $hasBody = ($body !== null && is_array($body));

                if (!$hasBody) {
                    return ['valid' => false, 'error' => 'Request body must be a JSON object', 'data' => []];
                }

                // Required: domain (string)
                $domain = $body['domain'] ?? null;
                $hasDomain = ($domain !== null && is_string($domain) && trim($domain) !== '');

                if (!$hasDomain) {
                    return ['valid' => false, 'error' => 'Missing required field: domain', 'data' => []];
                }

                $domainLength = mb_strlen($domain);
                $isDomainTooLong = ($domainLength > 253);

                if ($isDomainTooLong) {
                    return ['valid' => false, 'error' => 'Field "domain" must not exceed 253 characters', 'data' => []];
                }

                // Required: status (string, must be known value)
                $status = $body['status'] ?? null;
                $allowedStatuses = ['Pending', 'Active', 'Error', 'Completed'];
                $isValidStatus = (is_string($status) && in_array($status, $allowedStatuses, true));

                if (!$isValidStatus) {
                    return ['valid' => false, 'error' => 'Field "status" must be one of: ' . implode(', ', $allowedStatuses), 'data' => []];
                }

                // Optional: max_retries (integer, 0-10)
                $maxRetries = $body['max_retries'] ?? 3;
                $isValidRetries = (is_int($maxRetries) && $maxRetries >= 0 && $maxRetries <= 10);

                if (!$isValidRetries) {
                    return ['valid' => false, 'error' => 'Field "max_retries" must be an integer between 0 and 10', 'data' => []];
                }

                return [
                    'valid' => true,
                    'error' => null,
                    'data'  => [
                        'domain'      => trim($domain),
                        'status'      => $status,
                        'max_retries' => $maxRetries,
                    ],
                ];
            }
        };
    }

    // ── Body validation ─────────────────────────────────────

    public function testRejectsNullBody(): void
    {
        $result = $this->handler->validateCreateTransaction(null);

        $this->assertFalse($result['valid']);
        $this->assertSame('Request body must be a JSON object', $result['error']);
    }

    // ── Domain field ────────────────────────────────────────

    public function testRejectsMissingDomain(): void
    {
        $result = $this->handler->validateCreateTransaction(['status' => 'Active']);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('domain', $result['error']);
    }

    public function testRejectsEmptyDomain(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => '   ',
            'status' => 'Active',
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testRejectsNonStringDomain(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 12345,
            'status' => 'Active',
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testRejectsOverlongDomain(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => str_repeat('a', 254),
            'status' => 'Active',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('253', $result['error']);
    }

    public function testAcceptsMaxLengthDomain(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => str_repeat('a', 253),
            'status' => 'Active',
        ]);

        $this->assertTrue($result['valid']);
    }

    // ── Status field (enum validation) ──────────────────────

    public function testRejectsMissingStatus(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('status', $result['error']);
    }

    public function testRejectsInvalidStatusValue(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'InvalidStatus',
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testRejectsNonStringStatus(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 42,
        ]);

        $this->assertFalse($result['valid']);
    }

    /**
     * @dataProvider validStatusProvider
     */
    public function testAcceptsAllValidStatuses(string $status): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => $status,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame($status, $result['data']['status']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validStatusProvider(): array
    {
        return [
            'Pending'   => ['Pending'],
            'Active'    => ['Active'],
            'Error'     => ['Error'],
            'Completed' => ['Completed'],
        ];
    }

    // ── max_retries field (optional integer) ────────────────

    public function testDefaultsMaxRetriesToThree(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'Active',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['data']['max_retries']);
    }

    public function testRejectsNegativeMaxRetries(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'Active',
            'max_retries' => -1,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testRejectsMaxRetriesAboveTen(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'Active',
            'max_retries' => 11,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testRejectsFloatMaxRetries(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'Active',
            'max_retries' => 3.5,
        ]);

        $this->assertFalse($result['valid']);
    }

    public function testAcceptsBoundaryMaxRetries(): void
    {
        $resultZero = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'Active',
            'max_retries' => 0,
        ]);
        $resultTen = $this->handler->validateCreateTransaction([
            'domain' => 'example.com',
            'status' => 'Active',
            'max_retries' => 10,
        ]);

        $this->assertTrue($resultZero['valid']);
        $this->assertTrue($resultTen['valid']);
    }

    // ── Happy path ──────────────────────────────────────────

    public function testAcceptsFullyValidInput(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain'      => 'example.com',
            'status'      => 'Pending',
            'max_retries' => 5,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
        $this->assertSame('example.com', $result['data']['domain']);
        $this->assertSame('Pending', $result['data']['status']);
        $this->assertSame(5, $result['data']['max_retries']);
    }

    public function testDomainIsTrimmed(): void
    {
        $result = $this->handler->validateCreateTransaction([
            'domain' => '  example.com  ',
            'status' => 'Active',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('example.com', $result['data']['domain']);
    }
}
