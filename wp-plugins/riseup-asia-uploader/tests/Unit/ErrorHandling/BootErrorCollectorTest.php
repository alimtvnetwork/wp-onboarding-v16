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
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
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

        $ref = new \ReflectionClass($collector);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);
        $errors = $prop->getValue($collector);

        $this->assertCount(2, $errors);
        $this->assertSame('autoloader', $errors[0]['context']);
        $this->assertSame('plugin_init', $errors[1]['context']);
    }

    public function testHasErrorsReturnsFalseWhenEmpty(): void
    {
        $collector = BootErrorCollector::getInstance();

        $ref = new \ReflectionClass($collector);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);

        $this->assertCount(0, $prop->getValue($collector));
    }

    public function testErrorContainsTimestamp(): void
    {
        $collector = BootErrorCollector::getInstance();
        $collector->addError('test', 'Test error');

        $ref = new \ReflectionClass($collector);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);
        $errors = $prop->getValue($collector);

        $this->assertArrayHasKey('timestamp', $errors[0]);
        $this->assertNotEmpty($errors[0]['timestamp']);
    }

    public function testErrorContainsMessage(): void
    {
        $collector = BootErrorCollector::getInstance();
        $collector->addError('ctx', 'Specific message');

        $ref = new \ReflectionClass($collector);
        $prop = $ref->getProperty('errors');
        $prop->setAccessible(true);
        $errors = $prop->getValue($collector);

        $this->assertSame('Specific message', $errors[0]['message']);
    }
}
