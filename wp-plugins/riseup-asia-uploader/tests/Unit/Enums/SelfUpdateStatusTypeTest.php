<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\SelfUpdateStatusType;

final class SelfUpdateStatusTypeTest extends TestCase
{
    public function testRollbackReasonsAreCorrect(): void
    {
        $rollbackReasons = [
            SelfUpdateStatusType::ExtractionFailed,
            SelfUpdateStatusType::ValidationFailed,
            SelfUpdateStatusType::ActivationException,
            SelfUpdateStatusType::ActivationWpError,
            SelfUpdateStatusType::HealthCheckFailed,
            SelfUpdateStatusType::PluginFileNotFound,
        ];

        foreach ($rollbackReasons as $status) {
            $this->assertTrue($status->isRollbackReason(), "{$status->name} should be a rollback reason");
        }

        $this->assertFalse(SelfUpdateStatusType::Success->isRollbackReason());
        $this->assertFalse(SelfUpdateStatusType::CriticalFileMissing->isRollbackReason());
    }

    public function testValidationErrorsAreCorrect(): void
    {
        $validationErrors = [
            SelfUpdateStatusType::CriticalFileMissing,
            SelfUpdateStatusType::SyntaxError,
            SelfUpdateStatusType::FileUnreadable,
            SelfUpdateStatusType::DirectoryMissing,
        ];

        foreach ($validationErrors as $status) {
            $this->assertTrue($status->isValidationError(), "{$status->name} should be a validation error");
        }

        $this->assertFalse(SelfUpdateStatusType::Success->isValidationError());
    }

    public function testHealthCheckErrorsAreCorrect(): void
    {
        $healthErrors = [
            SelfUpdateStatusType::BootErrorDetected,
            SelfUpdateStatusType::CriticalClassMissing,
            SelfUpdateStatusType::RestHookMissing,
        ];

        foreach ($healthErrors as $status) {
            $this->assertTrue($status->isHealthCheckError(), "{$status->name} should be a health check error");
        }

        $this->assertFalse(SelfUpdateStatusType::SyntaxError->isHealthCheckError());
    }

    public function testSuccessIsOnlyForSuccessCase(): void
    {
        $this->assertTrue(SelfUpdateStatusType::Success->isSuccess());
        $this->assertFalse(SelfUpdateStatusType::RolledBack->isSuccess());
        $this->assertFalse(SelfUpdateStatusType::ValidationFailed->isSuccess());
    }

    public function testAllCasesHavePascalCaseValues(): void
    {
        foreach (SelfUpdateStatusType::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-zA-Z]+$/',
                $case->value,
                "Case {$case->name} value '{$case->value}' must be PascalCase",
            );
        }
    }

    public function testAllCasesHaveLabels(): void
    {
        foreach (SelfUpdateStatusType::cases() as $case) {
            $label = $case->label();
            $this->assertNotEmpty($label, "Case {$case->name} must have a label");
            $this->assertIsString($label);
        }
    }

    public function testIsEqualComparison(): void
    {
        $this->assertTrue(SelfUpdateStatusType::Success->isEqual(SelfUpdateStatusType::Success));
        $this->assertFalse(SelfUpdateStatusType::Success->isEqual(SelfUpdateStatusType::RolledBack));
    }

    public function testIsAnyOfComparison(): void
    {
        $this->assertTrue(SelfUpdateStatusType::Success->isAnyOf(
            SelfUpdateStatusType::Success,
            SelfUpdateStatusType::RolledBack,
        ));
        $this->assertFalse(SelfUpdateStatusType::Success->isAnyOf(
            SelfUpdateStatusType::RolledBack,
            SelfUpdateStatusType::RollbackFailed,
        ));
    }
}
