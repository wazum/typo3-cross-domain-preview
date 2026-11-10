<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\BeforeUserLogoutEvent;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use Wazum\CrossDomainPreview\SessionTransfer;

final readonly class EndPreviewSessionsOnLogout
{
    public function __construct(private SessionManager $sessionManager)
    {
    }

    // Before, not after the logout: afterwards the user record is gone
    #[AsEventListener('wazum/cross-domain-preview/end-preview-sessions-on-logout')]
    public function __invoke(BeforeUserLogoutEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof BackendUserAuthentication) {
            return;
        }

        $userId = (int) ($user->user['uid'] ?? 0);
        $sessionBackend = $this->sessionManager->getSessionBackend('BE');
        foreach ($sessionBackend->getAll() as $record) {
            if ((int) $record['ses_userid'] === $userId
                && true === UserSession::createFromRecord($record['ses_id'], $record)->get(SessionTransfer::PREVIEW_SESSION_KEY)
            ) {
                $sessionBackend->remove($record['ses_id']);
            }
        }
    }
}
