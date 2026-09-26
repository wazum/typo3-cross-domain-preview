<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotCreatedException;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Core\Session\UserSessionManager;
use Wazum\CrossDomainPreview\SessionTransfer;

final readonly class SessionTransferAuthenticator implements MiddlewareInterface
{
    public function __construct(private SessionTransfer $sessionTransfer)
    {
    }

    /**
     * @throws SessionNotCreatedException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getQueryParams()[SessionTransfer::QUERY_PARAMETER] ?? null;
        if (!is_string($token)) {
            return $handler->handle($request);
        }

        // Cross-site requests lack the SameSite=Strict cookie of an existing login, which would be overwritten
        if ('cross-site' === $request->getHeaderLine('Sec-Fetch-Site')) {
            return $this->createRefreshResponse($request->getUri());
        }

        $transfer = $this->redeemToken($request, $token);
        if (null === $transfer) {
            return $handler->handle($request);
        }

        $response = $this->createRefreshResponse($this->removeToken($request->getUri()));
        if ($this->hasSessionToKeep($request, $transfer['userId'])) {
            return $response;
        }

        return $this->addSessionCookie($response, $request, $this->startPreviewSession($transfer));
    }

    // No redirect: the follow-up request must be same-site to carry SameSite=Strict cookies
    private function createRefreshResponse(UriInterface $uri): ResponseInterface
    {
        return (new HtmlResponse('<!DOCTYPE html><meta http-equiv="refresh" content="0;url=' . htmlspecialchars((string) $uri) . '">'))
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @return array{userId: int, mfa: bool}|null
     */
    private function redeemToken(ServerRequestInterface $request, string $token): ?array
    {
        return $this->sessionTransfer->redeemToken($token, $request->getUri()->getHost(), $this->getNormalizedParams($request)->getRemoteAddress());
    }

    private function getNormalizedParams(ServerRequestInterface $request): NormalizedParams
    {
        return $request->getAttribute('normalizedParams');
    }

    private function removeToken(UriInterface $uri): UriInterface
    {
        return $uri->withQuery(implode('&', array_filter(
            explode('&', $uri->getQuery()),
            static fn(string $part): bool => !str_starts_with($part, SessionTransfer::QUERY_PARAMETER . '=')
        )));
    }

    // A backend login of any user is kept, a preview session only when it belongs to the same user
    private function hasSessionToKeep(ServerRequestInterface $request, int $userId): bool
    {
        $userSessionManager = UserSessionManager::create('BE');
        $userSession = $userSessionManager->createFromRequestOrAnonymous($request, BackendUserAuthentication::getCookieName());
        if ($userSession->isAnonymous() || $userSessionManager->hasExpired($userSession)) {
            return false;
        }

        return true !== $userSession->get(SessionTransfer::PREVIEW_SESSION_KEY) || $userSession->getUserId() === $userId;
    }

    /**
     * @param array{userId: int, mfa: bool} $transfer
     *
     * @throws SessionNotCreatedException
     */
    private function startPreviewSession(array $transfer): UserSession
    {
        $userSessionManager = UserSessionManager::create('BE');
        $userSession = $userSessionManager->elevateToFixatedUserSession($userSessionManager->createAnonymousSession(), $transfer['userId']);
        $userSession->set(SessionTransfer::PREVIEW_SESSION_KEY, true);
        $userSession->set('mfa', $transfer['mfa']);

        return $userSessionManager->updateSession($userSession);
    }

    private function addSessionCookie(ResponseInterface $response, ServerRequestInterface $request, UserSession $userSession): ResponseInterface
    {
        $cookie = SetCookieService::create(BackendUserAuthentication::getCookieName(), 'BE')
            ->setSessionCookie($userSession, $this->getNormalizedParams($request));

        return null === $cookie ? $response : $response->withAddedHeader('Set-Cookie', (string) $cookie);
    }
}
