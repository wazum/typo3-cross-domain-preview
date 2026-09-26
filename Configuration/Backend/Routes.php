<?php

declare(strict_types=1);

use Wazum\CrossDomainPreview\Controller\SessionTransferController;

return [
    'cross_domain_preview_session_transfer' => [
        'path' => '/cross-domain-preview/session-transfer',
        'methods' => ['GET'],
        'target' => SessionTransferController::class . '::handleRequest',
    ],
];
