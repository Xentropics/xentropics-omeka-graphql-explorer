<?php declare(strict_types=1);

namespace OmekaGraphQLExplorer;

return [
    'controllers' => [
        'factories' => [
            'OmekaGraphQLExplorer\Controller\Admin\Explorer'
                => Service\Controller\ExplorerControllerFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'graphql-explorer' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/graphql-explorer',
                            'defaults' => [
                                '__NAMESPACE__' => 'OmekaGraphQLExplorer\Controller\Admin',
                                'controller' => 'Explorer',
                                'action' => 'index',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'GraphQL explorer', // @translate
                'route' => 'admin/graphql-explorer',
                'resource' => 'OmekaGraphQLExplorer\Controller\Admin\Explorer',
                'class' => 'o-icon-search',
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
];
