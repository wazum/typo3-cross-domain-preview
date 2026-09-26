<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cross-domain preview',
    'description' => 'Preview hidden pages of sites on other domains than the one used to log in to the backend',
    'category' => 'be',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'wolfgang@wazum.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
