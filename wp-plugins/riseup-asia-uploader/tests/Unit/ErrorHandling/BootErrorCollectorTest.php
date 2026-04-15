<?php
/**
 * BootErrorCollectorTest — Tests for BootErrorCollector singleton.
 *
 * @package RiseupAsia\Tests\Unit\ErrorHandling
 */

namespace RiseupAsia\Tests\Unit\ErrorHandling;

use PHPUnit\Framework\TestCase;
use RiseupAsia\ErrorHandling\BootErrorCollector;

class BootErrorCollectorTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset singleton for each test
        $ref = new \ReflectionClass(BootErrorCollector::class);

        $instanceProp = $ref->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, null);
    }

    public function testGetInstanceReturnsSameObject(): void
    {
        $a = BootErrorCollector::getInstance();
        $b = BootErrorCollector::getInstance();

        $this->assertSame($a, $b);
    }

    public function testAddErrorAccumulatesErrors(): void
    {
        $collector = BootErrorCollector::getInstance();
        $collector->addError('autoloader', 'Class not found');
        $collector->addError('plugin_init', 'Init failed');

        $this->assertTrue($collector->hasErrors());

        $errors = $collector->getErrors();

        $this->assertCount(2, $errors);
        $this->assertSame('autoloader', $errors[0]['context']);
        $this->assertSame('plugin_init', $errors[1]['context']);
    }

    public function testHasErrorsReturnsFalseWhenEmpty(): void
    {
        $collector = BootErrorCollector::getInstance();

        $this->assertFalse($collector->hasErrors());
        $this->assertCount(0, $collector->getErrors());
    }

    public function testErrorContainsTimestamp(): void
    {
        $collector = BootErrorCollector::getInstance();
        $collector->addError('test', 'Test error');

        $errors = $collector->getErrors();

        $this->assertArrayHasKey('timestamp', $errors[0]);
        $this->assertNotEmpty($errors[0]['timestamp']);
    }

    public function testErrorContainsMessage(): void
    {
        $collector = BootErrorCollector::getInstance();
        $collector->addError('ctx', 'Specific message');

        $errors = $collector->getErrors();

        $this->assertSame('Specific message', $errors[0]['message']);
    }

    public function testFlushClearsErrors(): void
    {
        $collector = BootErrorCollector::getInstance();
        $collector->addError('test', 'Will be flushed');

        $this->assertTrue($collector->hasErrors());

        $collector->flush();

        $this->assertFalse($collector->hasErrors());
        $this->assertCount(0, $collector->getErrors());
    }

    public function testFlushDoesNothingWhenEmpty(): void
    {
        $collector = BootErrorCollector::getInstance();

        // Should not throw
        $collector->flush();

        $this->assertFalse($collector->hasErrors());
    }
}
