<?php
/**
 * LicenseLifecycleTest — Full integration test of the license flow:
 * save key → validate → activate → deactivate → remove.
 *
 * Verifies option caching, cron scheduling, and state transitions
 * throughout the entire lifecycle.
 */

namespace RiseupAsia\Tests\Unit\Licensing;

use PHPUnit\Framework\TestCase;
use RiseupAsia\Licensing\LicenseManager;
use ReflectionClass;

class LicenseLifecycleTest extends TestCase
{
    private const KEY = 'RISEUP-LIFE-CYCL-TEST-ABCD';
    private const DOMAIN = 'example.com';
    private const CRON_HOOK = 'riseup_license_revalidate';

    private LicenseManager $manager;

    /** @var int Counts API calls made during the test. */
    private int $apiCallCount;

    /** @var string[] URLs hit during the test (in order). */
    private array $apiUrls;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(LicenseManager::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        global $_wp_test_options, $_wp_test_remote_handler, $_wp_test_scheduled_events;
        $_wp_test_options = [];
        $_wp_test_remote_handler = null;
        $_wp_test_scheduled_events = [];

        if (!defined('RISEUP_LICENSE_API_URL')) {
            define('RISEUP_LICENSE_API_URL', 'https://license.test');
        }
        if (!defined('RISEUP_LICENSE_HMAC_SECRET')) {
            define('RISEUP_LICENSE_HMAC_SECRET', 'test-hmac-secret');
        }

        $this->apiCallCount = 0;
        $this->apiUrls = [];
        $this->manager = LicenseManager::getInstance();
    }

    // ------------------------------------------------------------------
    // Full lifecycle: save → validate → activate → deactivate → remove
    // ------------------------------------------------------------------

    public function testFullLicenseLifecycle(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler, $_wp_test_scheduled_events;

        // ── Step 1: No key — everything is empty ─────────────────────
        $this->assertFalse($this->manager->isLicensed(), 'Should not be licensed without a key');
        $this->assertNull($this->manager->validateLicense(), 'Validate should return null without key');
        $this->assertNull($this->manager->activateLicense(), 'Activate should return null without key');
        $this->assertArrayNotHasKey(self::CRON_HOOK, $_wp_test_scheduled_events, 'Cron should not be scheduled without key');

        // ── Step 2: Save key — triggers validate ─────────────────────
        $_wp_test_remote_handler = $this->buildRouter();

        $saveResult = $this->manager->setLicenseKey(self::KEY);

        $this->assertNotNull($saveResult, 'setLicenseKey should return validation result');
        $this->assertTrue($saveResult['valid'], 'Validation should succeed');
        $this->assertSame('active', $saveResult['status']);
        $this->assertSame(self::KEY, $_wp_test_options['riseup_license_key'], 'Key should be stored');
        $this->assertSame('active', $_wp_test_options['riseup_license_status'], 'Status should be cached');
        $this->assertArrayHasKey('riseup_license_checked_at', $_wp_test_options, 'Checked-at should be cached');
        $this->assertArrayHasKey(self::CRON_HOOK, $_wp_test_scheduled_events, 'Cron should be scheduled after saving key');
        $this->assertContainsUrlFragment('/validate', 'setLicenseKey should call validate endpoint');

        // ── Step 3: isLicensed uses cache ────────────────────────────
        $callsBefore = $this->apiCallCount;
        $this->assertTrue($this->manager->isLicensed(), 'Should be licensed with active cached status');
        $this->assertSame($callsBefore, $this->apiCallCount, 'isLicensed should use cache, not call API');

        // ── Step 4: Activate on this domain ──────────────────────────
        $this->apiCallCount = 0;
        $this->apiUrls = [];

        $activateResult = $this->manager->activateLicense();

        $this->assertNotNull($activateResult, 'activateLicense should return result');
        $this->assertTrue($activateResult['activated']);
        $this->assertSame(self::DOMAIN, $activateResult['domain']);
        $this->assertContainsUrlFragment('/activate', 'Should hit activate endpoint');
        $this->assertContainsUrlFragment('/validate', 'Activate should trigger re-validation');
        $this->assertGreaterThanOrEqual(2, $this->apiCallCount, 'Activate should make at least 2 API calls');

        // Cache should be refreshed after activate.
        $this->assertSame('active', $_wp_test_options['riseup_license_status']);

        // ── Step 5: getLicenseStatus ─────────────────────────────────
        $statusResult = $this->manager->getLicenseStatus();

        $this->assertNotNull($statusResult);
        $this->assertArrayHasKey('license', $statusResult);
        $this->assertArrayHasKey('activations', $statusResult);
        $this->assertCount(1, $statusResult['activations']);
        $this->assertSame(self::DOMAIN, $statusResult['activations'][0]['domain']);

        // ── Step 6: Deactivate ───────────────────────────────────────
        $deactivateResult = $this->manager->deactivateLicense();

        $this->assertNotNull($deactivateResult);
        $this->assertTrue($deactivateResult['deactivated']);
        $this->assertArrayNotHasKey('riseup_license_status', $_wp_test_options, 'Cache should be cleared after deactivate');
        $this->assertArrayNotHasKey('riseup_license_checked_at', $_wp_test_options);

        // ── Step 7: Remove key ───────────────────────────────────────
        $this->manager->removeLicenseKey();

        $this->assertArrayNotHasKey('riseup_license_key', $_wp_test_options, 'Key should be removed');
        $this->assertArrayNotHasKey('riseup_license_status', $_wp_test_options);
        $this->assertArrayNotHasKey('riseup_license_data', $_wp_test_options);
        $this->assertArrayNotHasKey('riseup_license_checked_at', $_wp_test_options);
        $this->assertArrayNotHasKey(self::CRON_HOOK, $_wp_test_scheduled_events, 'Cron should be unscheduled after remove');

        // ── Step 8: Back to unlicensed ───────────────────────────────
        $this->assertFalse($this->manager->isLicensed());
    }

    // ------------------------------------------------------------------
    // Cache staleness triggers re-validation
    // ------------------------------------------------------------------

    public function testStaleCacheForcesRevalidation(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = self::KEY;
        $_wp_test_options['riseup_license_status'] = 'active';
        // 13 hours ago — beyond the 12-hour TTL.
        $_wp_test_options['riseup_license_checked_at'] = (string) (time() - 13 * 3600);

        $_wp_test_remote_handler = $this->buildRouter();

        $isLicensed = $this->manager->isLicensed();

        $this->assertFalse($isLicensed, 'Validation returns valid=true but isLicensed checks the result array');
        $this->assertGreaterThanOrEqual(1, $this->apiCallCount, 'Stale cache should trigger API call');
    }

    // ------------------------------------------------------------------
    // Cron static callback
    // ------------------------------------------------------------------

    public function testCronRevalidateCallsValidate(): void
    {
        global $_wp_test_options, $_wp_test_remote_handler;

        $_wp_test_options['riseup_license_key'] = self::KEY;
        $_wp_test_remote_handler = $this->buildRouter();

        LicenseManager::cronRevalidate();

        $this->assertGreaterThanOrEqual(1, $this->apiCallCount);
        $this->assertContainsUrlFragment('/validate');
        $this->assertSame('active', $_wp_test_options['riseup_license_status'], 'Cron should update cached status');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a request router that responds based on URL path.
     */
    private function buildRouter(): callable
    {
        return function (string $url) {
            $this->apiCallCount++;
            $this->apiUrls[] = $url;

            if (str_contains($url, '/validate')) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'valid' => true,
                        'status' => 'active',
                        'product' => 'pro',
                        'type' => 'subscription',
                        'activations' => 1,
                        'maxActivations' => 5,
                    ]),
                ];
            }

            if (str_contains($url, '/activate')) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'activated' => true,
                        'domain' => self::DOMAIN,
                    ]),
                ];
            }

            if (str_contains($url, '/deactivate')) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['deactivated' => true]),
                ];
            }

            if (str_contains($url, '/status')) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'license' => ['key' => self::KEY, 'status' => 'active'],
                        'activations' => [
                            ['domain' => self::DOMAIN, 'activatedAt' => '2026-03-01T00:00:00Z'],
                        ],
                    ]),
                ];
            }

            // Fallback for unknown endpoints.
            return [
                'response' => ['code' => 404],
                'body' => json_encode(['error' => 'not_found']),
            ];
        };
    }

    /**
     * Assert that at least one captured URL contains the given fragment.
     */
    private function assertContainsUrlFragment(string $fragment, string $message = ''): void
    {
        $found = false;

        foreach ($this->apiUrls as $url) {
            if (str_contains($url, $fragment)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, $message ?: "Expected a URL containing '{$fragment}', got: " . implode(', ', $this->apiUrls));
    }
}
