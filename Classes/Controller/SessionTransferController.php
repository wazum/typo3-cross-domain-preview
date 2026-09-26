<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\SysLog\Action\Login as SystemLogLoginAction;
use TYPO3\CMS\Core\SysLog\Error as SystemLogErrorClassification;
use TYPO3\CMS\Core\SysLog\Type as SystemLogType;
use Wazum\CrossDomainPreview\SessionTransfer;

#[AsController]
final readonly class SessionTransferController
{
    public function __construct(
        private HashService $hashService,
        private SessionTransfer $sessionTransfer,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $this->getBackendUser();
        if (null !== $backendUser->getOriginalUserIdWhenInSwitchUserMode()) {
            return new HtmlResponse('', 403);
        }

        $targetUri = $this->getTargetUri($request, $backendUser);
        if (null === $targetUri) {
            return new HtmlResponse('', 400);
        }

        $token = $this->createToken($request, $backendUser, $targetUri);
        $this->logPreviewSession($backendUser, $targetUri);

        return new RedirectResponse($this->addToken($targetUri, $token), 303);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getTargetUri(ServerRequestInterface $request, BackendUserAuthentication $backendUser): ?UriInterface
    {
        $signedUrl = $request->getQueryParams()['url'] ?? '';
        if (!is_string($signedUrl) || '' === $signedUrl) {
            return null;
        }

        try {
            return new Uri($this->hashService->validateAndStripHmac($signedUrl, self::class . $backendUser->getUserId()));
        } catch (InvalidHashStringException) {
            return null;
        }
    }

    private function createToken(ServerRequestInterface $request, BackendUserAuthentication $backendUser, UriInterface $targetUri): string
    {
        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');

        return $this->sessionTransfer->createToken(
            (int) $backendUser->getUserId(),
            (bool) $backendUser->getSessionData('mfa'),
            $targetUri->getHost(),
            $normalizedParams->getRemoteAddress()
        );
    }

    private function logPreviewSession(BackendUserAuthentication $backendUser, UriInterface $targetUri): void
    {
        $backendUser->writelog(
            SystemLogType::LOGIN,
            SystemLogLoginAction::LOGIN,
            SystemLogErrorClassification::MESSAGE,
            null,
            'User %s started a preview session on %s',
            [$backendUser->user['username'] ?? '', $targetUri->getHost()]
        );
    }

    private function addToken(UriInterface $targetUri, string $token): UriInterface
    {
        $query = ltrim($targetUri->getQuery() . '&' . SessionTransfer::QUERY_PARAMETER . '=' . $token, '&');

        return $targetUri->withQuery($query);
    }
}
