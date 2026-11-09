<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Session\UserSessionManager;
use Wazum\CrossDomainPreview\SessionTransfer;

final readonly class PreviewSessionFilter implements MiddlewareInterface
{
    private const ADMIN_PANEL_ROUTES = ['ajax_adminPanel_toggle', 'ajax_adminPanel_saveForm'];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookieName = BackendUserAuthentication::getCookieName();
        $cookieParams = $request->getCookieParams();
        if (!isset($cookieParams[$cookieName]) || $this->isAdminPanelRoute($request)) {
            return $handler->handle($request);
        }

        $userSession = UserSessionManager::create('BE')->createFromRequestOrAnonymous($request, $cookieName);
        if (true !== $userSession->get(SessionTransfer::PREVIEW_SESSION_KEY)) {
            return $handler->handle($request);
        }

        unset($cookieParams[$cookieName]);

        return $handler->handle($request->withCookieParams($cookieParams));
    }

    private function isAdminPanelRoute(ServerRequestInterface $request): bool
    {
        $route = $request->getAttribute('route');

        return $route instanceof Route && in_array($route->getOption('_identifier'), self::ADMIN_PANEL_ROUTES, true);
    }
}
