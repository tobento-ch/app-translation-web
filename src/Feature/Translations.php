<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);

namespace Tobento\App\Translation\Web\Feature;

use Tobento\App\AppInterface;
use Tobento\App\Boot;
use Tobento\App\Crud\Boot\Crud;
use Tobento\App\Migration\Boot\Migration;
use Tobento\App\Translation\Web\Controller\TranslationCrudController;
use Tobento\Service\Acl\AclInterface;
use Tobento\Service\Menu\MenusInterface;
use Tobento\Service\Routing\RouterInterface;
use function Tobento\App\Translation\trans;

class Translations extends Boot
{
    public const INFO = [
        'boot' => [
            'routes translations',
        ],
    ];

    public const BOOT = [
        \Tobento\App\User\Boot\Acl::class,
        \Tobento\App\User\Boot\User::class,
        \Tobento\App\User\Boot\HttpUserErrorHandler::class,
        Crud::class,
        \Tobento\App\ImportExport\Boot\HttpErrorHandler::class,
        \Tobento\App\ImportExport\Boot\ImportExport::class,        
        \Tobento\App\Queue\Boot\Queue::class,
        \Tobento\App\Cache\Boot\Cache::class, // needed for unique queue
        \Tobento\App\Notifier\Boot\Notifier::class,
    ];
    
    /**
     * Create a new instance.
     *
     * @param null|string $menu The menu name or null if none.
     * @param string $menuLabel The menu label.
     * @param null|string $menuParent The menu parent or null if none.
     * @param null|string $queueName
     * @param bool $withAcl
     */
    public function __construct(
        protected null|string $menu = 'main',
        protected string $menuLabel = 'Translations',
        protected null|string $menuParent = null,
        protected null|string $queueName = null,
        protected bool $withAcl = true,
    ) {}

    /**
     * Boot application services.
     *
     * @param AppInterface $app
     * @param Migration $migration
     * @param Crud $crud
     * @return void
     */
    public function boot(AppInterface $app, Migration $migration, Crud $crud): void
    {
        $migration->install(\Tobento\App\Translation\Web\Migration\Translations::class);
        
        // ACL:
        $acl = $app->get(AclInterface::class);
        $acl->rule('translations')->description('User can access translations.');
        $acl->rule('translations.edit')->description('User can edit translations.');
        $acl->rule('translations.publish')->description('User can publish translations.');
        $acl->rule('translations.delete')->description('User can delete translations.');
        $acl->rule('translations.collect')->description('User can collect translations.');
        $acl->rule('translations.create')->description('User can create new translations.');
        $acl->rule('translations.auto-translate')->description('User can auto-translate translations.');
        $acl->rule('translations.export')->description('User can export translations.');
        $acl->rule('translations.import')->description('User can import translations.');
        
        if ($this->withAcl === false) {
            $acl->addPermissions([
                'translations', 'translations.edit', 'translations.publish', 'translations.delete',
                'translations.collect', 'translations.create', 'translations.auto-translate',
                'translations.export', 'translations.import',
            ]);
        }
        
        // Configure bindings:
        $app->set(TranslationCrudController::class)->with(['queueName' => $this->queueName]);
        
        // Routes:
        $crud->routeController(
            TranslationCrudController::class,
            middleware: [
                [
                    \Tobento\App\User\Middleware\VerifyRoutePermission::class,
                    'permissions' => [
                        'translations.index' => 'translations',
                        'translations.show' => 'translations',
                        'translations.edit' => 'translations.edit',
                        'translations.update' => 'translations.edit',
                        'translations.delete' => 'translations.delete',
                        'translations.bulk' => 'translations.edit|translations.delete',
                    ],
                ]
            ],
            except: ['create', 'store', 'copy'],
            localized: true,
        );
        
        // Menu:
        if ($this->menu) {
            $app->on(
                MenusInterface::class,
                function(MenusInterface $menus, AclInterface $acl, RouterInterface $router) {
                    if ($acl->can('translations')) {
                        $menus->menu($this->menu)
                            ->link($router->url('translations.index'), trans($this->menuLabel))
                            ->parent($this->menuParent)
                            ->id('translations.index');
                    }
                }
            );
        }
    }
}