<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\IpLocker;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Wazum\CrossDomainPreview\SessionTransfer;

final class SessionTransferTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private VariableFrontend $cache;
    private SessionTransfer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        // TYPO3 v13 takes the context as first argument, v14 only the options
        $this->cache = new VariableFrontend('cross_domain_preview', new TransientMemoryBackend([]));
        $this->subject = new SessionTransfer($this->cache, new Random());
    }

    #[Test]
    public function createTokenStoresNoIpAddressWhenIpLockIsDisabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['lockIP'] = 0;
        $token = $this->subject->createToken(42, false, 'example.org', '192.0.2.1');
        self::assertSame(IpLocker::DISABLED_LOCK_VALUE, $this->cache->get(hash('sha256', $token))['ipLock']);
    }

    #[Test]
    public function redeemTokenReturnsUserIdAndMfaStateOfCreatedToken(): void
    {
        $token = $this->subject->createToken(42, true, 'example.org', '192.0.2.1');
        self::assertSame(['userId' => 42, 'mfa' => true], $this->subject->redeemToken($token, 'example.org', '192.0.2.1'));
    }

    #[Test]
    public function redeemTokenReturnsNullForUnknownToken(): void
    {
        self::assertNull($this->subject->redeemToken('unknown', 'example.org', '192.0.2.1'));
    }

    #[Test]
    public function redeemTokenReturnsNullForTokenThatWasAlreadyRedeemed(): void
    {
        $token = $this->subject->createToken(42, false, 'example.org', '192.0.2.1');
        $this->subject->redeemToken($token, 'example.org', '192.0.2.1');
        self::assertNull($this->subject->redeemToken($token, 'example.org', '192.0.2.1'));
    }

    #[Test]
    public function redeemTokenReturnsNullForTokenCreatedForAnotherHost(): void
    {
        $token = $this->subject->createToken(42, false, 'example.org', '192.0.2.1');
        self::assertNull($this->subject->redeemToken($token, 'example.com', '192.0.2.1'));
    }

    #[Test]
    public function redeemTokenReturnsNullForTokenCreatedForAnotherIpAddressWhenIpIsLocked(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['BE']['lockIP'] = 4;
        $token = $this->subject->createToken(42, false, 'example.org', '192.0.2.1');
        self::assertNull($this->subject->redeemToken($token, 'example.org', '198.51.100.7'));
    }
}
