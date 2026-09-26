<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Event\AfterPagePreviewUriGeneratedEvent;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Wazum\CrossDomainPreview\Tests\Functional\SiteConfigurationTrait;

final class PreviewUriSessionTransferTest extends FunctionalTestCase
{
    use SiteConfigurationTrait;

    protected array $coreExtensionsToLoad = ['viewpage'];
    protected array $testExtensionsToLoad = ['wazum/cross-domain-preview'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->writeSiteConfiguration('site-a', 1, 'https://site-a.example/');
        $this->writeSiteConfiguration('site-b', 3, 'https://site-b.example/');
        $this->setUpBackendUser(1);
        $GLOBALS['TYPO3_REQUEST'] = new ServerRequest('https://site-a.example/typo3/');
    }

    #[Test]
    public function previewUriOfPageOnCurrentDomainIsKept(): void
    {
        self::assertSame('https://site-a.example/a-hidden', (string) PreviewUriBuilder::create(2)->buildUri());
    }

    #[Test]
    public function previewUriOfPageOnOtherDomainPointsToSessionTransferRoute(): void
    {
        $previewUri = PreviewUriBuilder::create(4)->buildUri();

        self::assertNotNull($previewUri);
        self::assertStringEndsWith('typo3/cross-domain-preview/session-transfer', $previewUri->getPath());
        parse_str($previewUri->getQuery(), $queryParameters);
        $url = $queryParameters['url'] ?? null;
        self::assertIsString($url);
        self::assertStringStartsWith('https://site-b.example/b-hidden', $url);
    }

    #[Test]
    public function previewUriInPreviewModuleIsKept(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $GLOBALS['TYPO3_REQUEST']
            ->withAttribute('module', $this->get(ModuleProvider::class)->getModule('page_preview'));

        self::assertSame('https://site-b.example/b-hidden', (string) PreviewUriBuilder::create(4)->buildUri());
    }

    #[Test]
    public function previewUriOnHostOfNoSiteIsKept(): void
    {
        $event = new AfterPagePreviewUriGeneratedEvent(new Uri('https://typo3.org/'), 4, 0, [], '', [], new Context(), []);

        $this->get(EventDispatcherInterface::class)->dispatch($event);

        self::assertSame('https://typo3.org/', (string) $event->getPreviewUri());
    }
}
