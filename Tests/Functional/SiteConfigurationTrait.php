<?php

declare(strict_types=1);

namespace Wazum\CrossDomainPreview\Tests\Functional;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait SiteConfigurationTrait
{
    private function writeSiteConfiguration(string $identifier, int $rootPageId, string $base): void
    {
        $path = $this->instancePath . '/typo3conf/sites/' . $identifier;
        GeneralUtility::mkdir_deep($path);
        file_put_contents($path . '/config.yaml', Yaml::dump([
            'rootPageId' => $rootPageId,
            'base' => $base,
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/'],
            ],
        ]));
    }
}
