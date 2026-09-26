<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleInterface;
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Site\SiteFinder;
use Wazum\CrossDomainPreview\Controller\SessionTransferController;

final readonly class PreviewUriSessionTransfer
{
    public function __construct(
        private UriBuilder $uriBuilder,
        private HashService $hashService,
        private SiteFinder $siteFinder,
    ) {
    }

    /**
     * @throws RouteNotFoundException
     */
    #[AsEventListener('wazum/cross-domain-preview/preview-uri-session-transfer')]
    public function __invoke(AfterPagePreviewUriGeneratedEvent $event): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        $previewHost = $event->getPreviewUri()->getHost();
        if (!$request instanceof ServerRequestInterface
            || $this->isPreviewModule($request)
            || !$backendUser instanceof BackendUserAuthentication
            || null !== $backendUser->getOriginalUserIdWhenInSwitchUserMode()
            || $previewHost === $request->getUri()->getHost()
            || !$this->isHostOfAnySite($previewHost)
        ) {
            return;
        }
        $signedUrl = $this->hashService->appendHmac(
            (string) $event->getPreviewUri(),
            SessionTransferController::class . $backendUser->getUserId()
        );
        $event->setPreviewUri($this->uriBuilder->buildUriFromRoute('cross_domain_preview_session_transfer', ['url' => $signedUrl]));
    }

    // The Preview module shows the page in a cross-site iframe, which cannot store the SameSite=Strict session cookie
    private function isPreviewModule(ServerRequestInterface $request): bool
    {
        $module = $request->getAttribute('module');

        return $module instanceof ModuleInterface && 'page_preview' === $module->getIdentifier();
    }

    private function isHostOfAnySite(string $host): bool
    {
        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $language) {
                if ($language->getBase()->getHost() === $host) {
                    return true;
                }
            }
        }

        return false;
    }
}
