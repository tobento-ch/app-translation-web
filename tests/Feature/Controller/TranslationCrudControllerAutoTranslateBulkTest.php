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

namespace Tobento\App\Translation\Web\Test\Feature\Controller;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Feature;
use Tobento\App\Translation\Web\Queue\AutoTranslateJobHandler;
use Tobento\Service\Queue\JobInterface;

class TranslationCrudControllerAutoTranslateBulkTest extends \Tobento\App\Crud\Testing\AbstractCrudTestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    use \Tobento\App\Translation\Web\Test\Addon\MachineTranslatorAddon;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }
    
    protected function getCrudController(): string
    {
        return \Tobento\App\Translation\Web\Controller\TranslationCrudController::class;
    }
    
    public function testIsRenderedWithPermissionAndWhenEnabled()
    {
        $this->withMachineTranslator();
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.auto-translate']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('<form action="http://localhost/translations/bulk/auto-translate" name="auto-translate" method="POST">')
            ->assertBodyContains('Auto Translate')
            ->assertBodyContains('Records to Auto Translate');
    }
    
    public function testIsNotRenderedWithoutPermissionAndWhenEnabled()
    {
        $this->withMachineTranslator();
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('<form action="http://localhost/translations/bulk/auto-translate" name="auto-translate" method="POST">')
            ->assertBodyNotContains('Records to Auto Translate');
    }
    
    public function testIsNotRenderedWithPermissionButNotEnabled()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.auto-translate']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('<form action="http://localhost/translations/bulk/auto-translate" name="auto-translate" method="POST">')
            ->assertBodyNotContains('Records to Auto Translate');
    }
    
    public function testCreatesAndQueuesJobUsingSelectionModeIds()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'auto-translate'),
            body: [
                'ids' => ['1', '2', '12'],
                'auto-translate_selection_mode' => 'ids',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.auto-translate']);

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(2)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Auto‑translation has started and will continue in the background.')
            ->assertCrudIndexEntityCount(2);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(AutoTranslateJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['user_id'] === 0
                    && $job->getPayload()['filters'] === [
                        'where' => ['id' => ['in' => ['1', '2', '12']]],
                        'orderBy' => [],
                    ];
            })
            ->assertPushedTimes(AutoTranslateJobHandler::class, 1);
    }
    
    public function testCreatesAndQueuesJobUsingSelectionModeFiltered()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'auto-translate'),
            body: [
                'ids' => ['1', '2', '12'],
                'auto-translate_selection_mode' => 'filtered',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.auto-translate']);

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(2)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Auto‑translation has started and will continue in the background.')
            ->assertCrudIndexEntityCount(2);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(AutoTranslateJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['user_id'] === 0
                    && $job->getPayload()['filters'] === [
                        'where' => [],
                        'orderBy' => [],
                    ];
            })
            ->assertPushedTimes(AutoTranslateJobHandler::class, 1);
    }
    
    public function testCreatesAndQueuesJobUsingCustomQueue()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(queueName: 'database'),
            new Feature\MachineTranslation(),
        ]);
        
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'auto-translate'),
            body: [
                'ids' => ['1', '2', '12'],
                'auto-translate_selection_mode' => 'filtered',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.auto-translate']);

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(2)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'database')
            ->assertPushedTimes(AutoTranslateJobHandler::class, 1);
    }
    
    public function testFailsWithoutPermission()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'auto-translate'),
            body: [
                'ids' => ['1', '2', '12'],
                'auto-translate_selection_mode' => 'filtered',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete']);

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(2)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Action auto-translate not found.')
            ->assertCrudIndexEntityCount(2);
        
        $fakeQueue->queue(name: 'file')->assertNothingPushed();
    }
    
    public function testFailsIfNotEnabled()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'auto-translate'),
            body: [
                'ids' => ['1', '2', '12'],
                'auto-translate_selection_mode' => 'filtered',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.auto-translate']);

        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'en',
        ])->times(2)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Action auto-translate not found.')
            ->assertCrudIndexEntityCount(2);
        
        $fakeQueue->queue(name: 'file')->assertNothingPushed();
    }
}