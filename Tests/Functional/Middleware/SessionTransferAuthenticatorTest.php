<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\CrossDomainPreview\SessionTransfer;
use Wazum\CrossDomainPreview\Tests\Functional\SiteConfigurationTrait;

final class SessionTransferAuthenticatorTest extends FunctionalTestCase
{
    use SiteConfigurationTrait;

    protected array $testExtensionsToLoad = ['wazum/cross-domain-preview'];

    protected array $configurationToUseInTestInstance = [
        'BE' => ['lockIP' => 4],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->writeSiteConfiguration('site-b', 3, 'https://site-b.example/');
    }

    #[Test]
    public function crossSiteRequestRefreshesToSameUrlWithoutRedeemingToken(): void
    {
        $token = $this->get(SessionTransfer::class)->createToken(1, false, 'site-b.example', '127.0.0.1');

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://site-b.example/?foo=bar&' . SessionTransfer::QUERY_PARAMETER . '=' . $token))
                ->withHeader('Sec-Fetch-Site', 'cross-site')
        );

        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString(
            'url=https://site-b.example/?foo=bar&amp;' . SessionTransfer::QUERY_PARAMETER . '=' . $token . '"',
            (string) $response->getBody()
        );
        self::assertNotNull($this->get(SessionTransfer::class)->redeemToken($token, 'site-b.example', '127.0.0.1'));
    }

    #[Test]
    public function validTokenStartsPreviewSessionAndRefreshesToUrlWithoutToken(): void
    {
        $response = $this->requestWithToken($this->get(SessionTransfer::class)->createToken(1, true, 'site-b.example', '127.0.0.1'));

        self::assertStringStartsWith('be_typo_user=', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('url=https://site-b.example/?foo=bar"', (string) $response->getBody());
        $sessionData = unserialize(
            (string) $this->getConnectionPool()->getConnectionForTable('be_sessions')->select(['ses_data'], 'be_sessions', ['ses_userid' => 1])->fetchOne(),
            ['allowed_classes' => false]
        );
        self::assertSame([SessionTransfer::PREVIEW_SESSION_KEY => true, 'mfa' => true], $sessionData);
    }

    #[Test]
    public function invalidTokenIsPassedOnWithoutSession(): void
    {
        $response = $this->requestWithToken('invalid');

        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringNotContainsString('http-equiv="refresh"', (string) $response->getBody());
    }

    #[Test]
    public function tokenCreatedForAnotherIpAddressIsPassedOnWithoutSession(): void
    {
        $response = $this->requestWithToken($this->get(SessionTransfer::class)->createToken(1, false, 'site-b.example', '192.0.2.1'));

        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
    }

    private function requestWithToken(string $token): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            new InternalRequest('https://site-b.example/?foo=bar&' . SessionTransfer::QUERY_PARAMETER . '=' . $token)
        );
    }
}
