<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\CrossDomainPreview\Middleware\PreviewSessionFilter;
use Wazum\CrossDomainPreview\SessionTransfer;

final class PreviewSessionFilterTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['adminpanel'];
    protected array $testExtensionsToLoad = ['wazum/cross-domain-preview'];

    #[Test]
    public function previewSessionCookieIsRemovedFromBackendRequest(): void
    {
        $handler = $this->handleWithSessionCookie(true);

        self::assertArrayNotHasKey('be_typo_user', $handler->request?->getCookieParams() ?? []);
    }

    #[Test]
    public function backendSessionCookieIsKept(): void
    {
        $handler = $this->handleWithSessionCookie(false);

        self::assertArrayHasKey('be_typo_user', $handler->request?->getCookieParams() ?? []);
    }

    #[Test]
    #[DataProvider('adminPanelPaths')]
    public function previewSessionCookieIsKeptForAdminPanelRequest(string $path): void
    {
        $handler = $this->handleWithSessionCookie(true, $path);

        self::assertArrayHasKey('be_typo_user', $handler->request?->getCookieParams() ?? []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminPanelPaths(): array
    {
        return [
            'toggle' => ['/typo3/ajax/adminpanel/toggleActiveState'],
            'save form' => ['/typo3/ajax/adminpanel/form/save'],
        ];
    }

    #[Test]
    public function previewSessionCookieIsRemovedFromOtherAjaxRequest(): void
    {
        $handler = $this->handleWithSessionCookie(true, '/typo3/ajax/record/process');

        self::assertArrayNotHasKey('be_typo_user', $handler->request?->getCookieParams() ?? []);
    }

    private function handleWithSessionCookie(bool $previewOnly, string $path = '/typo3/main'): RequestHandlerInterface
    {
        $request = new ServerRequest('https://site-b.example' . $path, 'GET', null, [], ['HTTP_HOST' => 'site-b.example', 'HTTPS' => 'on', 'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '127.0.0.1']);
        $normalizedParams = NormalizedParams::createFromRequest($request);
        $userSessionManager = UserSessionManager::create('BE');
        $userSession = $userSessionManager->elevateToFixatedUserSession($userSessionManager->createAnonymousSession(), 1);
        if ($previewOnly) {
            $userSession->set(SessionTransfer::PREVIEW_SESSION_KEY, true);
            $userSession = $userSessionManager->updateSession($userSession);
        }
        $cookie = SetCookieService::create('be_typo_user', 'BE')->setSessionCookie($userSession, $normalizedParams);
        $request = $request
            ->withAttribute('normalizedParams', $normalizedParams)
            ->withCookieParams(['be_typo_user' => (string) $cookie?->getValue()]);
        $request = $request->withAttribute('route', $this->get(Router::class)->matchResult($request)->getRoute());
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response();
            }
        };
        (new PreviewSessionFilter())->process($request, $handler);

        return $handler;
    }
}
