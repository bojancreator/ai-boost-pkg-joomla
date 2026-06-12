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
        $this->assertStringContainsString('/licenses/activate', $calls[0]);
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
        $this->assertStringContainsString('/licenses/validate', $calls[0]);
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
