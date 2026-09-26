<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\CrossDomainPreview\Controller\SessionTransferController;
use Wazum\CrossDomainPreview\SessionTransfer;

final class SessionTransferControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/cross-domain-preview'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
    }

    #[Test]
    public function signedUrlRedirectsToUrlWithTokenForCurrentUser(): void
    {
        $response = $this->requestWithUrl($this->sign('https://site-b.example/page?foo=bar', 1));

        self::assertSame(303, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        self::assertStringStartsWith('https://site-b.example/page?foo=bar&ADMCMD_sessionTransfer=', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $queryParameters);
        $token = $queryParameters['ADMCMD_sessionTransfer'] ?? null;
        self::assertIsString($token);
        self::assertSame(['userId' => 1, 'mfa' => false], $this->get(SessionTransfer::class)->redeemToken($token, 'site-b.example', '127.0.0.1'));
    }

    #[Test]
    public function tamperedUrlIsRejected(): void
    {
        $response = $this->requestWithUrl(str_replace('site-b', 'evil', $this->sign('https://site-b.example/page', 1)));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function urlSignedForAnotherUserIsRejected(): void
    {
        $response = $this->requestWithUrl($this->sign('https://site-b.example/page', 2));

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function switchUserSessionIsRejected(): void
    {
        $GLOBALS['BE_USER']->setAndSaveSessionData('backuserid', 2);

        $response = $this->requestWithUrl($this->sign('https://site-b.example/page', 1));

        self::assertSame(403, $response->getStatusCode());
    }

    private function sign(string $url, int $userId): string
    {
        return $this->get(HashService::class)->appendHmac($url, SessionTransferController::class . $userId);
    }

    private function requestWithUrl(string $url): ResponseInterface
    {
        $request = (new ServerRequest('https://site-a.example/typo3/cross-domain-preview/session-transfer', 'GET', null, [], ['REMOTE_ADDR' => '127.0.0.1']))
            ->withQueryParams(['url' => $url]);

        return $this->get(SessionTransferController::class)->handleRequest(
            $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
        );
    }
}
