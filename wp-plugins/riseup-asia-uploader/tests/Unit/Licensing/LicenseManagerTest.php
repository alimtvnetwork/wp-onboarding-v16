<?php

namespace RiseupAsia\Tests\Unit\Licensing;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Licensing\LicenseManager;
use ReflectionClass;

class LicenseManagerTest extends TestCase
{
    private LicenseManager $manager;

    protected function setUp(): void
    {
        // Reset singleton and option store before each test.
        $ref = new ReflectionClass(LicenseManager::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        global $_wp_test_options, $_wp_test_remote_handler;
        $_wp_test_options = [];
        $_wp_test_remote_handler = null;

        // Define constants the constructor needs (only once per process).
        if (!defined('RISEUP_LICENSE_API_URL')) {
            define('RISEUP_LICENSE_API_URL', 'https://license.test');
        }
        if (!defined('RISEUP_LICENSE_HMAC_SECRET')) {
            define('RISEUP_LICENSE_HMAC_SECRET', 'test-hmac-secret');
        }

        $this->manager = LicenseManager::getInstance();
    }

    // ------------------------------------------------------------------
    // Singleton
    // ------------------------------------------------------------------

    public function testGetInstanceReturnsSameObject(): void
    {
        $a = LicenseManager::getInstance();
        $b = LicenseManager::getInstance();

        $this->assertSame($a, $b);
    }

    // ------------------------------------------------------------------
    // isLicensed — no key stored
    // ------------------------------------------------------------------

    public function testIsLicensedReturnsFalseWithoutKey(): void
    {
        $this->assertFalse($this->manager->isLicensed());
    }

    // ------------------------------------------------------------------
    // isLicensed — cached status
    // ------------------------------------------------------------------

    public function testIsLicensedUsesCachedActiveStatus(): void
    {
        global $_wp_test_options;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-TEST-TEST-TEST-TEST';
        $_wp_test_options['riseup_license_status'] = 'active';
        $_wp_test_options['riseup_license_checked_at'] = (string) time();

        $this->assertTrue($this->manager->isLicensed());
    }

    public function testIsLicensedReturnsFalseForExpiredCache(): void
    {
        global $_wp_test_options;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-TEST-TEST-TEST-TEST';
        $_wp_test_options['riseup_license_status'] = 'expired';
        $_wp_test_options['riseup_license_checked_at'] = (string) time();

        $this->assertFalse($this->manager->isLicensed());
    }

    public function testIsLicensedIgnoresStaleCache(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-TEST-TEST-TEST-TEST';
        $_wp_test_options['riseup_license_status'] = 'active';
        // 13 hours ago — beyond the 12-hour TTL.
        $_wp_test_options['riseup_license_checked_at'] = (string) (time() - 13 * 3600);

        // API returns invalid → isLicensed falls back to API.
        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['valid' => false, 'status' => 'expired']),
        ];

        $this->assertFalse($this->manager->isLicensed());
    }

    // ------------------------------------------------------------------
    // validateLicense
    // ------------------------------------------------------------------

    public function testValidateLicenseReturnsNullWithoutKey(): void
    {
        $this->assertNull($this->manager->validateLicense());
    }

    public function testValidateLicenseCachesResult(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-AAAA-BBBB-CCCC-DDDD';

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['valid' => true, 'status' => 'active', 'product' => 'pro']),
        ];

        $result = $this->manager->validateLicense();

        $this->assertNotNull($result);
        $this->assertTrue($result['valid']);
        $this->assertSame('active', $_wp_test_options['riseup_license_status']);
        $this->assertArrayHasKey('riseup_license_checked_at', $_wp_test_options);
    }

    public function testValidateLicenseReturnsNullOnApiError(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-AAAA-BBBB-CCCC-DDDD';

        // Simulate WP_Error return.
        $_wp_test_remote_handler = fn() => new \WP_Error('timeout', 'Connection timed out');

        $this->assertNull($this->manager->validateLicense());
    }

    // ------------------------------------------------------------------
    // setLicenseKey / removeLicenseKey
    // ------------------------------------------------------------------

    public function testSetLicenseKeyStoresAndValidates(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['valid' => true, 'status' => 'active']),
        ];

        $result = $this->manager->setLicenseKey('  RISEUP-NEW1-NEW2-NEW3-NEW4  ');

        $this->assertSame('RISEUP-NEW1-NEW2-NEW3-NEW4', $_wp_test_options['riseup_license_key']);
        $this->assertNotNull($result);
        $this->assertTrue($result['valid']);
    }

    public function testRemoveLicenseKeyClearsEverything(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-DEL1-DEL2-DEL3-DEL4';
        $_wp_test_options['riseup_license_status'] = 'active';
        $_wp_test_options['riseup_license_checked_at'] = (string) time();

        // Deactivate call — API returns success.
        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['deactivated' => true]),
        ];

        $this->manager->removeLicenseKey();

        $this->assertArrayNotHasKey('riseup_license_key', $_wp_test_options);
        $this->assertArrayNotHasKey('riseup_license_status', $_wp_test_options);
        $this->assertArrayNotHasKey('riseup_license_checked_at', $_wp_test_options);
    }

    // ------------------------------------------------------------------
    // activateLicense
    // ------------------------------------------------------------------

    public function testActivateLicenseReturnsNullWithoutKey(): void
    {
        $this->assertNull($this->manager->activateLicense());
    }

    public function testActivateLicenseSendsDomainAndRevalidates(): void
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

            // Second call is validate (triggered internally).
            return [
                'response' => ['code' => 200],
                'body' => json_encode(['valid' => true, 'status' => 'active']),
            ];
        };

        $result = $this->manager->activateLicense();

        $this->assertNotNull($result);
        $this->assertTrue($result['activated']);
        $this->assertSame(2, $callCount, 'activate should trigger a follow-up validate');
    }

    // ------------------------------------------------------------------
    // deactivateLicense
    // ------------------------------------------------------------------

    public function testDeactivateLicenseClearsCache(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-DEA1-DEA2-DEA3-DEA4';
        $_wp_test_options['riseup_license_status'] = 'active';
        $_wp_test_options['riseup_license_checked_at'] = (string) time();

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode(['deactivated' => true]),
        ];

        $result = $this->manager->deactivateLicense();

        $this->assertNotNull($result);
        $this->assertArrayNotHasKey('riseup_license_status', $_wp_test_options);
        $this->assertArrayNotHasKey('riseup_license_checked_at', $_wp_test_options);
    }

    // ------------------------------------------------------------------
    // getLicenseStatus
    // ------------------------------------------------------------------

    public function testGetLicenseStatusReturnsNullWithoutKey(): void
    {
        $this->assertNull($this->manager->getLicenseStatus());
    }

    public function testGetLicenseStatusReturnsApiResponse(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = 'RISEUP-STA1-STA2-STA3-STA4';

        $_wp_test_remote_handler = fn() => [
            'response' => ['code' => 200],
            'body' => json_encode([
                'license' => ['key' => 'RISEUP-STA1-STA2-STA3-STA4'],
                'activations' => [['domain' => 'example.com']],
            ]),
        ];

        $result = $this->manager->getLicenseStatus();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('license', $result);
        $this->assertArrayHasKey('activations', $result);
    }
}
