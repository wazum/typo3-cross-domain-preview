<?php

declare(strict_types=1);

use Wazum\CrossDomainPreview\Middleware\PreviewSessionFilter;
use Wazum\CrossDomainPreview\Middleware\SessionTransferAuthenticator;

return [
    'frontend' => [
        'wazum/cross-domain-preview/session-transfer' => [
            'target' => SessionTransferAuthenticator::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
    'backend' => [
        'wazum/cross-domain-preview/preview-session-filter' => [
            'target' => PreviewSessionFilter::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-backend/authentication',
            ],
        ],
    ],
];
