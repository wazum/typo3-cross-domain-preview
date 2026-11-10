<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Core\Session\UserSessionManager;
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
    public function validTokenWithSectionRefreshesToUrlWithSection(): void
    {
        $response = $this->requestWithToken($this->get(SessionTransfer::class)->createToken(1, false, 'site-b.example', '127.0.0.1', 'c123'));

        self::assertStringContainsString('url=https://site-b.example/?foo=bar#c123"', (string) $response->getBody());
    }

    #[Test]
    public function previewSessionOfNonAdminEditorShowsHiddenPage(): void
    {
        $this->setUpFrontendRootPage(3, ['EXT:cross_domain_preview/Tests/Functional/Fixtures/page.typoscript']);

        $response = $this->requestHiddenPageWithPreviewSession(2, false);

        self::assertStringContainsString('<h1>B hidden</h1>', (string) $response->getBody());
    }

    #[Test]
    public function previewSessionOfMfaEditorShowsHiddenPageWhenMfaWasPassed(): void
    {
        $this->setUpFrontendRootPage(3, ['EXT:cross_domain_preview/Tests/Functional/Fixtures/page.typoscript']);

        $response = $this->requestHiddenPageWithPreviewSession(3, true);

        self::assertStringContainsString('<h1>B hidden</h1>', (string) $response->getBody());
    }

    #[Test]
    public function previewSessionOfMfaEditorDoesNotShowHiddenPageWhenMfaWasNotPassed(): void
    {
        $this->setUpFrontendRootPage(3, ['EXT:cross_domain_preview/Tests/Functional/Fixtures/page.typoscript']);

        $response = $this->requestHiddenPageWithPreviewSession(3, false);

        self::assertStringNotContainsString('<h1>B hidden</h1>', (string) $response->getBody());
    }

    #[Test]
    public function previewSessionOfAnotherUserIsReplaced(): void
    {
        $response = $this->requestWithTokenAndSession(
            $this->get(SessionTransfer::class)->createToken(1, false, 'site-b.example', '127.0.0.1'),
            $this->createSessionCookie(2, true)
        );

        self::assertStringStartsWith('be_typo_user=', $response->getHeaderLine('Set-Cookie'));
    }

    #[Test]
    public function previewSessionOfSameUserIsKept(): void
    {
        $response = $this->requestWithTokenAndSession(
            $this->get(SessionTransfer::class)->createToken(1, false, 'site-b.example', '127.0.0.1'),
            $this->createSessionCookie(1, true)
        );

        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('http-equiv="refresh"', (string) $response->getBody());
    }

    #[Test]
    public function backendLoginOfAnotherUserIsKept(): void
    {
        $response = $this->requestWithTokenAndSession(
            $this->get(SessionTransfer::class)->createToken(1, false, 'site-b.example', '127.0.0.1'),
            $this->createSessionCookie(2, false)
        );

        self::assertSame('', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('http-equiv="refresh"', (string) $response->getBody());
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

    private function requestHiddenPageWithPreviewSession(int $userId, bool $mfa): ResponseInterface
    {
        $setCookie = $this->requestWithToken($this->get(SessionTransfer::class)->createToken($userId, $mfa, 'site-b.example', '127.0.0.1'))
            ->getHeaderLine('Set-Cookie');
        $sessionCookie = urldecode(explode('=', explode(';', $setCookie)[0], 2)[1] ?? '');

        return $this->executeFrontendSubRequest(
            (new InternalRequest('https://site-b.example/b-hidden'))->withCookieParams(['be_typo_user' => $sessionCookie])
        );
    }

    private function requestWithTokenAndSession(string $token, string $sessionCookie): ResponseInterface
    {
        return $this->executeFrontendSubRequest(
            (new InternalRequest('https://site-b.example/?foo=bar&' . SessionTransfer::QUERY_PARAMETER . '=' . $token))
                ->withCookieParams(['be_typo_user' => $sessionCookie])
        );
    }

    private function createSessionCookie(int $userId, bool $previewOnly): string
    {
        $userSessionManager = UserSessionManager::create('BE');
        $userSession = $userSessionManager->elevateToFixatedUserSession($userSessionManager->createAnonymousSession(), $userId);
        if ($previewOnly) {
            $userSession->set(SessionTransfer::PREVIEW_SESSION_KEY, true);
            $userSession = $userSessionManager->updateSession($userSession);
        }
        $request = new ServerRequest('https://site-b.example/', 'GET', null, [], ['HTTP_HOST' => 'site-b.example', 'HTTPS' => 'on', 'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '127.0.0.1']);

        return (string) SetCookieService::create('be_typo_user', 'BE')
            ->setSessionCookie($userSession, NormalizedParams::createFromRequest($request))
            ?->getValue();
    }
}
