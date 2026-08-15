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
use Tobento\App\Translation\Web\Queue\CollectTranslationsJobHandler;
use Tobento\Service\Queue\JobInterface;

class TranslationCrudControllerCollectBulkTest extends \Tobento\App\Crud\Testing\AbstractCrudTestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    
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
    
    public function testIsRendered()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.collect']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('<form action="http://localhost/translations/bulk/collect" name="collect" method="POST">')
            ->assertBodyContains('Collect')
            ->assertBodyContains('Translations to Collect')
            ->assertBodyContains('All Translations for the root app');
    }
    
    public function testIsNotRenderedWithoutPermission()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyNotContains('<form action="http://localhost/translations/bulk/collect" name="collect" method="POST">')
            ->assertBodyNotContains('Translations to Collect');
    }
    
    public function testCreatesAndQueuesJob()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'collect'),
            body: [
                'collect_collector_ids' => ['app-root'],
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.collect']);

        $this->getSeedFactory()->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Collecting translations has started and will continue in the background.')
            ->assertCrudIndexEntityCount(1);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(CollectTranslationsJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['user_id'] === 0
                    && $job->getPayload()['collector_ids'] === ['app-root'];
            })
            ->assertPushedTimes(CollectTranslationsJobHandler::class, 1);
    }
    
    public function testCreatesAndQueuesJobUsingCustomQueue()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(queueName: 'database'),
        ]);
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'collect'),
            body: [
                'collect_collector_ids' => ['app-root'],
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.collect']);

        $this->getSeedFactory()->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'database')
            ->assertPushedTimes(CollectTranslationsJobHandler::class, 1);
    }

    public function testFailsWithoutPermission()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'collect'),
            body: [
                'collect_collector_ids' => ['app-root'],
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete']);

        $this->getSeedFactory()->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Action collect not found.');
        
        $fakeQueue->queue(name: 'file')->assertNothingPushed();
    }
    
    public function testFailsWhenValidationError()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'collect'),
            body: [
                'collect_collector_ids' => [],
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.collect']);

        $this->getSeedFactory()->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The collect_collector_ids must have at least 1 items.');
        
        $fakeQueue->queue(name: 'file')->assertNothingPushed();
    }
}