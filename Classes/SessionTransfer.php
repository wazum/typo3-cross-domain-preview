<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Authentication\IpLocker;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Crypto\Random;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(public: true)]
final readonly class SessionTransfer
{
    public const QUERY_PARAMETER = 'ADMCMD_sessionTransfer';
    public const PREVIEW_SESSION_KEY = 'crossDomainPreview.previewOnly';

    public function __construct(
        #[Autowire(service: 'cache.cross_domain_preview')]
        private FrontendInterface $cache,
        private Random $random,
    ) {
    }

    public function createToken(int $userId, bool $mfa, string $host, string $remoteAddress): string
    {
        $token = $this->random->generateRandomHexString(64);
        $this->cache->set(
            hash('sha256', $token),
            ['userId' => $userId, 'mfa' => $mfa, 'host' => $host, 'ipLock' => $this->createIpLocker()->getSessionIpLock($remoteAddress)],
            [],
            30
        );

        return $token;
    }

    /**
     * @return array{userId: int, mfa: bool}|null
     */
    public function redeemToken(string $token, string $host, string $remoteAddress): ?array
    {
        $cacheIdentifier = hash('sha256', $token);
        $entry = $this->cache->get($cacheIdentifier);
        if (!is_array($entry) || !$this->cache->remove($cacheIdentifier)) {
            return null;
        }
        if ($entry['host'] !== $host || !$this->createIpLocker()->validateRemoteAddressAgainstSessionIpLock($remoteAddress, $entry['ipLock'])) {
            return null;
        }

        return ['userId' => $entry['userId'], 'mfa' => $entry['mfa']];
    }

    private function createIpLocker(): IpLocker
    {
        return GeneralUtility::makeInstance(
            IpLocker::class,
            (int) ($GLOBALS['TYPO3_CONF_VARS']['BE']['lockIP'] ?? 0),
            (int) ($GLOBALS['TYPO3_CONF_VARS']['BE']['lockIPv6'] ?? 0)
        );
    }
}
