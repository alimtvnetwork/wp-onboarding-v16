<?php

declare(strict_types=1);

namespace RiseupAsia\Tests\Unit\Update;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Enums\SelfUpdateStatusType;
use RiseupAsia\Update\SelfUpdateValidator;
use RiseupAsia\Logging\FileLogger;

final class SelfUpdateValidatorTest extends TestCase
{
    private string $pluginDir;
    private ?SelfUpdateValidator $validator = null;

    protected function setUp(): void
    {
        $this->pluginDir = sys_get_temp_dir() . '/riseup-validator-test-' . uniqid();
        $this->recursiveDelete($this->pluginDir);
        mkdir($this->pluginDir, 0755, true);

        $this->validator = new SelfUpdateValidator($this->createStubLogger());
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->pluginDir);
    }

    // ── Critical file validation ────────────────────────────────────

    public function testValidatePassesWithAllCriticalFiles(): void
    {
        $this->createValidPluginStructure();

        $result = $this->validator->validate($this->pluginDir);

        $this->assertTrue($result);
    }

    public function testValidateFailsWhenMainFileIsMissing(): void
    {
        $this->createValidPluginStructure();
        unlink($this->pluginDir . '/riseup-asia-uploader.php');

        $result = $this->validator->validate($this->pluginDir);

        $this->assertFalse($result);
        $this->assertErrorCodeExists(SelfUpdateStatusType::CriticalFileMissing);
    }

    public function testValidateFailsWhenAutoloaderIsMissing(): void
    {
        $this->createValidPluginStructure();
        unlink($this->pluginDir . '/includes/Autoloader.php');

        $result = $this->validator->validate($this->pluginDir);

        $this->assertFalse($result);
        $this->assertErrorCodeExists(SelfUpdateStatusType::CriticalFileMissing);
    }

    public function testValidateReportsAllMissingFiles(): void
    {
        // Empty dir — all critical files missing
        $result = $this->validator->validate($this->pluginDir);

        $this->assertFalse($result);

        $errors = $this->validator->getErrors();
        $criticalMissingCount = 0;

        foreach ($errors as $error) {
            if ($error['code'] === SelfUpdateStatusType::CriticalFileMissing->value) {
                $criticalMissingCount++;
            }
        }

        // All 16 critical files should be reported
        $this->assertSame(16, $criticalMissingCount);
    }

    // ── Syntax checking ─────────────────────────────────────────────

    public function testValidatePassesWithValidPhpSyntax(): void
    {
        $this->createValidPluginStructure();

        $result = $this->validator->validate($this->pluginDir);

        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testValidateDetectsSyntaxErrors(): void
    {
        $this->createValidPluginStructure();

        // Write a file with a syntax error
        file_put_contents(
            $this->pluginDir . '/includes/Broken.php',
            "<?php\nclass Broken {\n    public function foo( { }\n}\n"
        );

        $result = $this->validator->validate($this->pluginDir);

        $this->assertFalse($result);
        $this->assertErrorCodeExists(SelfUpdateStatusType::SyntaxError);
    }

    public function testValidateSyntaxErrorIncludesFilePath(): void
    {
        $this->createValidPluginStructure();

        file_put_contents(
            $this->pluginDir . '/includes/BadFile.php',
            "<?php\nfunction broken( { return 1; }\n"
        );

        $this->validator->validate($this->pluginDir);

        $errors = $this->validator->getErrors();
        $syntaxErrors = array_filter($errors, fn($e) => $e['code'] === SelfUpdateStatusType::SyntaxError->value);
        $syntaxError = reset($syntaxErrors);

        $this->assertStringContainsString('BadFile.php', $syntaxError['message']);
    }

    // ── Directory validation ────────────────────────────────────────

    public function testValidateFailsForNonexistentDirectory(): void
    {
        $result = $this->validator->validate('/nonexistent/path/to/plugin');

        $this->assertFalse($result);
        $this->assertErrorCodeExists(SelfUpdateStatusType::DirectoryMissing);
    }

    // ── getDiagnostics ──────────────────────────────────────────────

    public function testGetDiagnosticsReturnsStructuredData(): void
    {
        $this->createValidPluginStructure();
        $this->validator->validate($this->pluginDir);

        $diagnostics = $this->validator->getDiagnostics();

        $this->assertArrayHasKey('Passed', $diagnostics);
        $this->assertArrayHasKey('ErrorCount', $diagnostics);
        $this->assertArrayHasKey('Errors', $diagnostics);
        $this->assertTrue($diagnostics['Passed']);
        $this->assertSame(0, $diagnostics['ErrorCount']);
    }

    public function testGetDiagnosticsReflectsFailures(): void
    {
        // Empty dir triggers critical file errors
        $this->validator->validate($this->pluginDir);

        $diagnostics = $this->validator->getDiagnostics();

        $this->assertFalse($diagnostics['Passed']);
        $this->assertGreaterThan(0, $diagnostics['ErrorCount']);
        $this->assertCount($diagnostics['ErrorCount'], $diagnostics['Errors']);
    }

    public function testGetDiagnosticsErrorsHaveCodeAndMessage(): void
    {
        $this->validator->validate($this->pluginDir);

        $diagnostics = $this->validator->getDiagnostics();

        foreach ($diagnostics['Errors'] as $error) {
            $this->assertArrayHasKey('code', $error);
            $this->assertArrayHasKey('message', $error);
            $this->assertNotEmpty($error['code']);
            $this->assertNotEmpty($error['message']);
        }
    }

    // ── Multiple runs reset state ───────────────────────────────────

    public function testValidateResetsErrorsBetweenRuns(): void
    {
        // First run: fails
        $this->validator->validate($this->pluginDir);
        $this->assertNotEmpty($this->validator->getErrors());

        // Second run: passes
        $this->createValidPluginStructure();
        $this->validator->validate($this->pluginDir);
        $this->assertEmpty($this->validator->getErrors());
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function createValidPluginStructure(): void
    {
        $dirs = [
            $this->pluginDir . '/includes',
            $this->pluginDir . '/includes/Core',
            $this->pluginDir . '/includes/Enums',
            $this->pluginDir . '/includes/Logging',
            $this->pluginDir . '/includes/ErrorHandling',
            $this->pluginDir . '/includes/Database',
            $this->pluginDir . '/includes/Post',
            $this->pluginDir . '/includes/Update',
            $this->pluginDir . '/includes/Helpers',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        $stub = "<?php\n// stub\n";

        $files = [
            'riseup-asia-uploader.php' => "<?php\n/**\n * Plugin Name: Riseup Asia Uploader\n * Version: 2.4.0\n */\n",
            'includes/Autoloader.php' => $stub,
            'includes/Core/Plugin.php' => $stub,
            'includes/Enums/PluginConfigType.php' => "<?php\nnamespace RiseupAsia\\Enums;\nenum PluginConfigType: string { case Slug = 'test'; }\n",
            'includes/Enums/HookType.php' => $stub,
            'includes/Enums/ResponseKeyType.php' => $stub,
            'includes/Logging/FileLogger.php' => $stub,
            'includes/Logging/Logger.php' => $stub,
            'includes/ErrorHandling/BootErrorCollector.php' => $stub,
            'includes/ErrorHandling/FatalErrorHandler.php' => $stub,
            'includes/Database/Database.php' => $stub,
            'includes/Post/PostManager.php' => $stub,
            'includes/Update/UpdateResolver.php' => $stub,
            'includes/Helpers/PathHelper.php' => $stub,
            'includes/Helpers/InitHelpers.php' => $stub,
            'includes/Helpers/EnvelopeBuilder.php' => $stub,
        ];

        foreach ($files as $relativePath => $content) {
            file_put_contents($this->pluginDir . '/' . $relativePath, $content);
        }
    }

    private function assertErrorCodeExists(SelfUpdateStatusType $expectedCode): void
    {
        $errors = $this->validator->getErrors();
        $codes = array_column($errors, 'code');

        $this->assertContains(
            $expectedCode->value,
            $codes,
            'Expected error code ' . $expectedCode->value . ' not found in: ' . implode(', ', $codes)
        );
    }

    private function createStubLogger(): FileLogger
    {
        $stub = $this->createStub(FileLogger::class);
        $stub->method('info')->willReturn(null);
        $stub->method('warn')->willReturn(null);
        $stub->method('error')->willReturn(null);

        return $stub;
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
