<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\Backend\Exception\SessionNotFoundException;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\CrossDomainPreview\SessionTransfer;

final class EndPreviewSessionsOnLogoutTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['wazum/cross-domain-preview'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
    }

    #[Test]
    public function logoutEndsOnlyThePreviewSessionsOfTheUser(): void
    {
        $previewSessionOfUser = $this->createSession(1, true);
        $loginOfUser = $this->createSession(1, false);
        $previewSessionOfOtherUser = $this->createSession(2, true);
        $this->setUpBackendUser(1);

        $GLOBALS['BE_USER']->logoff();

        self::assertFalse($this->sessionExists($previewSessionOfUser));
        self::assertTrue($this->sessionExists($loginOfUser));
        self::assertTrue($this->sessionExists($previewSessionOfOtherUser));
    }

    private function createSession(int $userId, bool $previewOnly): string
    {
        $userSessionManager = UserSessionManager::create('BE');
        $userSession = $userSessionManager->elevateToFixatedUserSession($userSessionManager->createAnonymousSession(), $userId);
        if ($previewOnly) {
            $userSession->set(SessionTransfer::PREVIEW_SESSION_KEY, true);
            $userSessionManager->updateSession($userSession);
        }

        return $userSession->getIdentifier();
    }

    private function sessionExists(string $sessionId): bool
    {
        try {
            $this->get(SessionManager::class)->getSessionBackend('BE')->get($sessionId);

            return true;
        } catch (SessionNotFoundException) {
            return false;
        }
    }
}
