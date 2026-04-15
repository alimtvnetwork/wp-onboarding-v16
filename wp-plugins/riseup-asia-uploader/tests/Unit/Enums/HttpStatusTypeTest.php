<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\HttpStatusType;

final class HttpStatusTypeTest extends TestCase
{
    public function testSuccessCodesAreInRange(): void
    {
        $successCodes = [HttpStatusType::Ok, HttpStatusType::Created, HttpStatusType::NoContent];

        foreach ($successCodes as $code) {
            $this->assertTrue($code->isSuccess(), "{$code->name} ({$code->value}) should be success");
        }
    }

    public function testClientErrorCodesAreInRange(): void
    {
        $clientErrors = [
            HttpStatusType::BadRequest, HttpStatusType::Unauthorized,
            HttpStatusType::Forbidden, HttpStatusType::NotFound,
        ];

        foreach ($clientErrors as $code) {
            $this->assertTrue($code->isClientError(), "{$code->name} ({$code->value}) should be client error");
            $this->assertFalse($code->isSuccess());
        }
    }

    public function testServerErrorCodesAreInRange(): void
    {
        $serverErrors = [
            HttpStatusType::ServerError, HttpStatusType::BadGateway,
            HttpStatusType::ServiceUnavailable, HttpStatusType::GatewayTimeout,
        ];

        foreach ($serverErrors as $code) {
            $this->assertTrue($code->isServerError(), "{$code->name} ({$code->value}) should be server error");
        }
    }

    public function testRetryableCodesAreCorrect(): void
    {
        $retryable = [
            HttpStatusType::RequestTimeout, HttpStatusType::TooManyRequests,
            HttpStatusType::ServerError, HttpStatusType::BadGateway,
            HttpStatusType::ServiceUnavailable, HttpStatusType::GatewayTimeout,
        ];
        $nonRetryable = [HttpStatusType::BadRequest, HttpStatusType::NotFound, HttpStatusType::Ok];

        foreach ($retryable as $code) {
            $this->assertTrue($code->isRetryable(), "{$code->name} should be retryable");
        }
        foreach ($nonRetryable as $code) {
            $this->assertFalse($code->isRetryable(), "{$code->name} should not be retryable");
        }
    }

    public function testRedirectCodesAreCorrect(): void
    {
        $redirects = [
            HttpStatusType::MovedPermanently, HttpStatusType::Found,
            HttpStatusType::TemporaryRedirect, HttpStatusType::PermanentRedirect,
        ];

        foreach ($redirects as $code) {
            $this->assertTrue($code->isRedirect(), "{$code->name} should be redirect");
        }

        $this->assertFalse(HttpStatusType::Ok->isRedirect());
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(HttpStatusType::Ok->isEqual(HttpStatusType::Ok));
        $this->assertFalse(HttpStatusType::Ok->isEqual(HttpStatusType::NotFound));
    }

    public function testAllCasesHavePositiveIntValues(): void
    {
        foreach (HttpStatusType::cases() as $case) {
            $this->assertGreaterThan(0, $case->value, "{$case->name} must have a positive HTTP status code");
        }
    }
}
