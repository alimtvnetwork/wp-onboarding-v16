<?php

namespace RiseupAsia\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Admin\Traits\AdminLicenseAjaxTrait;
use RiseupAsia\Licensing\LicenseManager;
use ReflectionClass;
use WpAjaxTestException;

/**
 * Concrete class that uses the trait so we can call its methods.
 */
class LicenseAjaxStub {
    use AdminLicenseAjaxTrait;
}

class AdminLicenseAjaxTraitTest extends TestCase
{
    private LicenseAjaxStub $handler;

    protected function setUp(): void
    {
        // Reset LicenseManager singleton.
        $ref = new ReflectionClass(LicenseManager::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        global $_wp_test_options, $_wp_test_remote_handler, $_wp_test_scheduled_events;
        $_wp_test_options = [];
        $_wp_test_remote_handler = null;
        $_wp_test_scheduled_events = [];
        $_POST = [];

        if (!defined('RISEUP_LICENSE_API_URL')) {
            define('RISEUP_LICENSE_API_URL', 'https://license.test');
        }
        if (!defined('RISEUP_LICENSE_HMAC_SECRET')) {
            define('RISEUP_LICENSE_HMAC_SECRET', 'test-hmac-secret');
        }

        $this->handler = new LicenseAjaxStub();
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    // ------------------------------------------------------------------
    // ajaxLicenseSave
    // ------------------------------------------------------------------

    public function testSaveReturnsErrorWhenKeyEmpty(): void
    {
        $_POST['license_key'] = '';

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseSave());

        $this->assertFalse($ex->success);
        $this->assertSame('License key is required.', $ex->data['message']);
    }

    public function testSaveReturnsErrorWhenKeyMissing(): void
    {
        // No license_key in $_POST at all.
        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseSave());

        $this->assertFalse($ex->success);
        $this->assertSame('License key is required.', $ex->data['message']);
    }

    public function testSaveReturnsSuccessOnValidKey(): void
    {
        global $_wp_test_remote_handler;

        $_POST['license_key'] = 'RISEUP-AAAA-BBBB-CCCC-DDDD';
        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['valid' => true, 'status' => 'active']),
        ];

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseSave());

        $this->assertTrue($ex->success);
        $this->assertSame('License key saved and validated.', $ex->data['message']);
        $this->assertTrue($ex->data['result']['valid']);
    }

    public function testSaveReturnsErrorOnApiFailure(): void
    {
        global $_wp_test_remote_handler;

        $_POST['license_key'] = 'RISEUP-FAIL-FAIL-FAIL-FAIL';
        $_wp_test_remote_handler = fn() => new \WP_Error('timeout', 'Timed out');

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseSave());

        $this->assertFalse($ex->success);
        $this->assertSame('Failed to validate license key.', $ex->data['message']);
    }

    // ------------------------------------------------------------------
    // ajaxLicenseActivate
    // ------------------------------------------------------------------

    public function testActivateReturnsErrorWithoutKey(): void
    {
        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseActivate());

        $this->assertFalse($ex->success);
        $this->assertSame('Activation failed. Check your license key.', $ex->data['message']);
    }

    public function testActivateReturnsSuccessOnValid(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-ACT1-ACT2-ACT3-ACT4';

        $callCount = 0;
        $_wp_test_remote_handler = function (string $url) use (&$callCount) {
            $callCount++;
            if (str_contains($url, '/activate')) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['activated' => true, 'domain' => 'example.com']),
                ];
            }
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['valid' => true, 'status' => 'active']),
            ];
        };

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseActivate());

        $this->assertTrue($ex->success);
        $this->assertSame('License activated on this site.', $ex->data['message']);
    }

    // ------------------------------------------------------------------
    // ajaxLicenseDeactivate
    // ------------------------------------------------------------------

    public function testDeactivateReturnsErrorWithoutKey(): void
    {
        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseDeactivate());

        $this->assertFalse($ex->success);
        $this->assertSame('Deactivation failed.', $ex->data['message']);
    }

    public function testDeactivateReturnsSuccessOnValid(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-DEA1-DEA2-DEA3-DEA4';
        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['deactivated' => true]),
        ];

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseDeactivate());

        $this->assertTrue($ex->success);
        $this->assertSame('License deactivated from this site.', $ex->data['message']);
    }

    // ------------------------------------------------------------------
    // ajaxLicenseRemove
    // ------------------------------------------------------------------

    public function testRemoveClearsKeyAndReturnsSuccess(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-REM1-REM2-REM3-REM4';
        $_wp_test_options['riseup_license_status'] = 'active';

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['deactivated' => true]),
        ];

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseRemove());

        $this->assertTrue($ex->success);
        $this->assertSame('License key removed.', $ex->data['message']);
        $this->assertArrayNotHasKey('riseup_license_key', $_wp_test_options);
    }

    // ------------------------------------------------------------------
    // ajaxLicenseRefresh
    // ------------------------------------------------------------------

    public function testRefreshReturnsErrorWithoutKey(): void
    {
        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseRefresh());

        $this->assertFalse($ex->success);
        $this->assertSame('Validation failed. No license key stored.', $ex->data['message']);
    }

    public function testRefreshReturnsSuccessWithKey(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-REF1-REF2-REF3-REF4';
        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['valid' => true, 'status' => 'active']),
        ];

        $ex = $this->catchAjax(fn() => $this->handler->ajaxLicenseRefresh());

        $this->assertTrue($ex->success);
        $this->assertSame('License status refreshed.', $ex->data['message']);
        $this->assertTrue($ex->data['result']['valid']);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function catchAjax(callable $fn): WpAjaxTestException
    {
        try {
            $fn();
            $this->fail('Expected WpAjaxTestException to be thrown');
        } catch (WpAjaxTestException $e) {
            return $e;
        }
    }
}
