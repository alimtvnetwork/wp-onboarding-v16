<?php
/**
 * DateHelperTest — Tests for DateHelper utility class.
 *
 * @package RiseupAsia\Tests\Unit\Helpers
 */

namespace RiseupAsia\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Helpers\DateHelper;

class DateHelperTest extends TestCase
{
    public function testNowUtcReturnsIso8601Format(): void
    {
        $result = DateHelper::nowUtc();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result);
    }

    public function testNowIsoReturnsIso8601Format(): void
    {
        $result = DateHelper::nowIso();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $result);
    }

    public function testNowCompactReturnsCompactFormat(): void
    {
        $result = DateHelper::nowCompact();

        $this->assertMatchesRegularExpression('/^\d{8}-\d{6}$/', $result);
    }

    public function testFormatConstants(): void
    {
        $this->assertSame('Y-m-d\TH:i:s\Z', DateHelper::ISO_8601_UTC);
        $this->assertSame('c', DateHelper::ISO_8601);
        $this->assertSame('Ymd-His', DateHelper::COMPACT);
        $this->assertSame('Y-m-d', DateHelper::DATE_ONLY);
        $this->assertSame('Y-m-d H:i:s', DateHelper::DATETIME);
    }

    public function testDiffReturnsReadableString(): void
    {
        $past = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
        $result = DateHelper::diff($past);

        $this->assertNotEmpty($result);
    }

    public function testElapsedMsReturnsFormattedString(): void
    {
        $start = microtime(true) - 1.5;
        $result = DateHelper::elapsedMs($start);

        $this->assertMatchesRegularExpression('/\d+(\.\d+)?\s*ms/', $result);
    }
}
