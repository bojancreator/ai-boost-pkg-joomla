<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\LicenseValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for LicenseValidator::verify() — the real Lemon Squeezy
 * validate/activate flow behind the Licenses tab. verify() feeds
 * PluginRegistry::saveLicenseState(), whose 'active' outcome permanently sets
 * `pro_activated`, so EVERY error branch here must be fail-closed: a status
 * of 'active' is only acceptable for a confirmed live key whose
 * meta.store_id strictly matches the pinned store (EXPECTED_STORE_ID).
 *
 * The HTTP exchange is stubbed via LicenseValidator::setTransport() so no
 * test ever touches the network; LicenseValidator::setExpectedStoreId()
 * installs a stand-in pin (the real store does not exist yet — the release
 * constant is deliberately null and fail-closed).
 */
final class LicenseValidatorVerifyTest extends TestCase
{
    /** Stand-in for the production Lemon Squeezy store ID. */
    private const STORE_ID = 4242;

    protected function setUp(): void
    {
        LicenseValidator::resetCache();
        LicenseValidator::setExpectedStoreId(self::STORE_ID);
        // Any test that forgets to stub the exchange must fail loudly
        // instead of dialling out through the real HTTP client.
        LicenseValidator::setTransport(static function (): ?string {
            self::fail('verify() must not fall back to the real HTTP transport in tests.');
        });
    }

    protected function tearDown(): void
    {
        LicenseValidator::resetCache();
    }

    // ------------------------------------------------------------------
    // Happy path
    // ------------------------------------------------------------------

    public function testActiveKeyFromOurStoreActivates(): void
    {
        $calls = [];
        LicenseValidator::setTransport(static function (string $url, array $params) use (&$calls): ?string {
            $calls[] = $url;
            return json_encode([
                'valid'       => true,
                'license_key' => [
                    'status'           => 'active',
                    'expires_at'       => '2027-06-11T00:00:00.000000Z',
                    'activation_limit' => 5,
                    'activation_usage' => 1,
                ],
                'instance'    => ['id' => 'inst-123'],
                'meta'        => ['store_id' => self::STORE_ID, 'product_id' => 777],
            ]);
        });

        $state = LicenseValidator::verify('LS-KEY-GOOD', 'https://example.test');

        $this->assertSame('active', $state['status']);
        $this->assertSame('inst-123', $state['instance_id']);
        $this->assertSame('2027-06-11T00:00:00.000000Z', $state['expires_at']);
        $this->assertSame(4, $state['activations_remaining']);
        $this->assertSame(777, $state['product_id'], 'meta.product_id is captured for future tier mapping');
        $this->assertCount(1, $calls, 'no stored instance_id -> goes straight to /activate');
        $this->assertStringContainsString('/api/license/activate', $calls[0]);
    }

    public function testStoredInstanceRevalidatesWithoutConsumingAnActivation(): void
    {
        $calls = [];
        LicenseValidator::setTransport(static function (string $url, array $params) use (&$calls): ?string {
            $calls[] = $url;
            return json_encode([
                'valid'       => true,
                'license_key' => ['status' => 'active'],
                'meta'        => ['store_id' => self::STORE_ID],
            ]);
        });

        $state = LicenseValidator::verify('LS-KEY-GOOD', 'https://example.test', 'inst-123');

        $this->assertSame('active', $state['status']);
        $this->assertSame('inst-123', $state['instance_id']);
        $this->assertCount(1, $calls, 'a known instance is /validate-d, never re-/activate-d');
        $this->assertStringContainsString('/api/license/validate', $calls[0]);
    }

    // ------------------------------------------------------------------
    // Fail-closed table — no error branch may ever yield status 'active'
    // ------------------------------------------------------------------

    /**
     * Rows: [transport behaviour, expected status, expected message fragment].
     * Behaviour is ['json', payload] for a canned LS response, ['raw', body]
     * for a non-JSON body, ['network'] for a request-level failure and
     * ['unavailable'] for a host with no usable HTTP transport.
     *
     * @return array<string, array{0: array, 1: string, 2: string}>
     */
    public static function failClosedOutcomes(): array
    {
        return [
            'expired key' => [
                ['json', [
                    'valid'       => false,
                    'license_key' => ['status' => 'expired', 'expires_at' => '2025-01-01T00:00:00.000000Z'],
                    'meta'        => ['store_id' => self::STORE_ID],
                ]],
                'expired',
                'expired',
            ],
            'disabled key' => [
                ['json', [
                    'valid'       => false,
                    'license_key' => ['status' => 'disabled'],
                    'meta'        => ['store_id' => self::STORE_ID],
                ]],
                'deactivated',
                'disabled',
            ],
            'activation limit reached' => [
                ['json', [
                    'valid'       => false,
                    'error'       => 'This license key has reached the activation limit.',
                    'license_key' => ['status' => 'active', 'activation_limit' => 1, 'activation_usage' => 1],
                    'meta'        => ['store_id' => self::STORE_ID],
                ]],
                'limit_reached',
                'activation limit',
            ],
            'unknown key' => [
                ['json', ['valid' => false, 'error' => 'license_key not found.']],
                'invalid',
                'license_key not found',
            ],
            'network failure' => [
                ['network'],
                'invalid',
                'Could not reach the licensing server',
            ],
            'malformed JSON body' => [
                ['raw', '<html>502 Bad Gateway</html>'],
                'invalid',
                'Could not reach the licensing server',
            ],
            'no HTTP transport on host' => [
                ['unavailable'],
                'invalid',
                'cannot make outbound HTTP requests',
            ],
            'wrong store id' => [
                ['json', [
                    'valid'       => true,
                    'license_key' => ['status' => 'active'],
                    'instance'    => ['id' => 'inst-foreign'],
                    'meta'        => ['store_id' => self::STORE_ID + 1, 'product_id' => 1],
                ]],
                'invalid',
                'different product or store',
            ],
            'active response without store id' => [
                ['json', [
                    'valid'       => true,
                    'license_key' => ['status' => 'active'],
                    'instance'    => ['id' => 'inst-anon'],
                ]],
                'invalid',
                'different product or store',
            ],
            'expired key from a foreign store' => [
                ['json', [
                    'valid'       => false,
                    'license_key' => ['status' => 'expired'],
                    'meta'        => ['store_id' => self::STORE_ID + 1],
                ]],
                'invalid',
                'different product or store',
            ],
        ];
    }

    #[DataProvider('failClosedOutcomes')]
    public function testVerifyFailsClosedOnEveryErrorBranch(array $behaviour, string $expectedStatus, string $messageNeedle): void
    {
        self::applyTransportBehaviour($behaviour);

        $state = LicenseValidator::verify('LS-KEY-0000', 'https://example.test');

        $this->assertNotSame('active', $state['status'], 'error branches must never unlock Pro');
        $this->assertSame($expectedStatus, $state['status']);
        $this->assertStringContainsStringIgnoringCase($messageNeedle, (string) $state['message']);
    }

    // ------------------------------------------------------------------
    // Store pinning configuration
    // ------------------------------------------------------------------

    public function testUnconfiguredStorePinningFailsClosedWithoutCallingTheApi(): void
    {
        // Simulate the shipped (pre-release) state: EXPECTED_STORE_ID null.
        LicenseValidator::setExpectedStoreId(null);
        LicenseValidator::setTransport(static function (): ?string {
            self::fail('verify() must not call the API while store pinning is unconfigured.');
        });

        $state = LicenseValidator::verify('LS-KEY-GOOD', 'https://example.test');

        $this->assertSame('invalid', $state['status']);
        $this->assertStringContainsString('store pinning missing', (string) $state['message']);
    }

    public function testEmptyKeyIsRejectedBeforeAnyApiCall(): void
    {
        // setUp's transport stub fails the test if the API is touched.
        $state = LicenseValidator::verify('   ', 'https://example.test');

        $this->assertSame('invalid', $state['status']);
        $this->assertSame('License key is required.', $state['message']);
    }

    public function testDifferentProductIdFromOurStoreStillActivates(): void
    {
        // product_id is captured for future tier mapping but must never reject.
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-9'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => 31337],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-GOOD', 'https://example.test');

        $this->assertSame('active', $state['status']);
        $this->assertSame(31337, $state['product_id']);
    }

    // ------------------------------------------------------------------
    // Distinct support messages
    // ------------------------------------------------------------------

    public function testTransportUnavailableMessageIsDistinctFromConnectivityFailure(): void
    {
        self::applyTransportBehaviour(['network']);
        $network = LicenseValidator::verify('LS-KEY-0000', 'https://example.test');

        self::applyTransportBehaviour(['unavailable']);
        $transport = LicenseValidator::verify('LS-KEY-0000', 'https://example.test');

        $this->assertNotSame(
            $network['message'],
            $transport['message'],
            'support must be able to tell host configuration from a connectivity blip'
        );
    }

    // ------------------------------------------------------------------
    // Plan 2a — product pinning (per-integration, fail closed)
    // ------------------------------------------------------------------

    /** Stand-in Lemon Squeezy product ID for an integration SKU. */
    private const PRODUCT_ID = 9001;

    public function testMatchingProductIdActivatesWhenProductPinned(): void
    {
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-yoo'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => self::PRODUCT_ID],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-GOOD', 'https://example.test', '', self::PRODUCT_ID);

        $this->assertSame('active', $state['status'], 'a key for the pinned product must activate');
        $this->assertSame(self::PRODUCT_ID, $state['product_id']);
    }

    public function testMismatchedProductIdIsRejectedEvenFromOurStore(): void
    {
        // The core leak this prevents: a same-store key for ANOTHER integration
        // product (e.g. a Multilang key used against the YOOtheme SKU).
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-other'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => self::PRODUCT_ID + 1],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-OTHER', 'https://example.test', '', self::PRODUCT_ID);

        $this->assertSame('invalid', $state['status'], 'a key for a different product must NOT activate');
        $this->assertStringContainsString('different AI Boost product', (string) $state['message']);
    }

    public function testActiveKeyWithoutProductIdIsRejectedWhenProductPinned(): void
    {
        // Fail-closed: an "active" response that proves no product at all cannot
        // satisfy a product-pinned SKU.
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-anon'],
            'meta'        => ['store_id' => self::STORE_ID],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-ANON', 'https://example.test', '', self::PRODUCT_ID);

        $this->assertSame('invalid', $state['status']);
        $this->assertStringContainsString('different AI Boost product', (string) $state['message']);
    }

    public function testCoreSkuPassesNullExpectedProductIdAndIgnoresProductPinning(): void
    {
        // Core SKUs are store-pinned only: any product_id from our store activates.
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-core'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => 424242],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-CORE', 'https://example.test', '', null);

        $this->assertSame('active', $state['status']);
    }

    public function testExpectedProductIdReadsConstantAndOverride(): void
    {
        // The accessor reads the configured constants; 'bundle' is never in the
        // per-integration map (core is product-pinned via EXPECTED_CORE_PRODUCT_IDS).
        $this->assertSame(LicenseValidator::EXPECTED_PRODUCT_IDS['int_yootheme'], LicenseValidator::expectedProductId('int_yootheme'));
        $this->assertSame(LicenseValidator::EXPECTED_PRODUCT_IDS['int_falang'], LicenseValidator::expectedProductId('int_falang'));
        $this->assertNull(LicenseValidator::expectedProductId('bundle'));

        // An override wins over the constant and is per-SKU.
        LicenseValidator::setExpectedProductId('int_yootheme', 555);
        $this->assertSame(555, LicenseValidator::expectedProductId('int_yootheme'));
        $this->assertSame(LicenseValidator::EXPECTED_PRODUCT_IDS['int_falang'], LicenseValidator::expectedProductId('int_falang'), 'override is per-SKU');
    }

    // ------------------------------------------------------------------
    // Core product-set pinning — the 3/10/unlimited tiers share the bundle
    // ------------------------------------------------------------------

    /** Stand-in Lemon Squeezy product IDs for the three core tiers. */
    private const CORE_PRODUCT_IDS = [201, 202, 203];

    public function testAnyCoreTierProductActivatesTheBundle(): void
    {
        // A key for the PRO+ (10-site) tier must activate the same core bundle
        // as the PRO (3-site) tier — the plugin never distinguishes tiers.
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-core10'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => 202],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-CORE10', 'https://example.test', '', self::CORE_PRODUCT_IDS);

        $this->assertSame('active', $state['status'], 'any core tier product unlocks core');
        $this->assertSame(202, $state['product_id']);
    }

    public function testAddonKeyCannotActivateCoreWhenCorePinned(): void
    {
        // The leak this closes: a same-store €20 YOOtheme key entered into the
        // core "AI Boost" field must NOT activate the €65+ core bundle.
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-yoo'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => self::PRODUCT_ID],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-YOO', 'https://example.test', '', self::CORE_PRODUCT_IDS);

        $this->assertSame('invalid', $state['status'], 'an add-on product must not unlock core');
        $this->assertStringContainsString('different AI Boost product', (string) $state['message']);
    }

    public function testActiveCoreKeyWithoutProductIdIsRejectedWhenCorePinned(): void
    {
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-anon'],
            'meta'        => ['store_id' => self::STORE_ID],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-ANON', 'https://example.test', '', self::CORE_PRODUCT_IDS);

        $this->assertSame('invalid', $state['status']);
        $this->assertStringContainsString('different AI Boost product', (string) $state['message']);
    }

    public function testEmptyCoreProductSetFallsBackToStorePinOnly(): void
    {
        // Pre-launch behaviour: with no core product IDs configured, any
        // product_id from our store activates core (store pinning only).
        self::applyTransportBehaviour(['json', [
            'valid'       => true,
            'license_key' => ['status' => 'active'],
            'instance'    => ['id' => 'inst-core'],
            'meta'        => ['store_id' => self::STORE_ID, 'product_id' => 999999],
        ]]);

        $state = LicenseValidator::verify('LS-KEY-CORE', 'https://example.test', '', []);

        $this->assertSame('active', $state['status'], 'an empty allowed list means store-pin only');
    }

    public function testExpectedCoreProductIdsReadsConstantAndOverride(): void
    {
        // Defaults to the configured constant (the three core tiers).
        $this->assertSame(LicenseValidator::EXPECTED_CORE_PRODUCT_IDS, LicenseValidator::expectedCoreProductIds());

        LicenseValidator::setExpectedCoreProductIds([201, 202, 203]);
        $this->assertSame([201, 202, 203], LicenseValidator::expectedCoreProductIds());

        // Non-int values are filtered out of the override.
        LicenseValidator::setExpectedCoreProductIds([201, 'x', null, 202]);
        $this->assertSame([201, 202], LicenseValidator::expectedCoreProductIds());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Install a transport stub matching a failClosedOutcomes() behaviour row. */
    private static function applyTransportBehaviour(array $behaviour): void
    {
        switch ($behaviour[0]) {
            case 'json':
                $body = json_encode($behaviour[1], JSON_UNESCAPED_SLASHES);
                LicenseValidator::setTransport(static fn (): ?string => $body);
                break;
            case 'raw':
                LicenseValidator::setTransport(static fn (): ?string => $behaviour[1]);
                break;
            case 'network':
                LicenseValidator::setTransport(static fn (): ?string => null);
                break;
            case 'unavailable':
                LicenseValidator::setTransport(static function (): ?string {
                    throw new \RuntimeException('HTTP transport unavailable: no transport driver available.');
                });
                break;
            default:
                self::fail('Unknown transport behaviour: ' . (string) $behaviour[0]);
        }
    }
}
