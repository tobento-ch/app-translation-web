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
use Tobento\App\Translation\Web\Queue\CreateForLocaleJobHandler;
use Tobento\Service\Queue\JobInterface;

class TranslationCrudControllerCreateForLocaleTest extends \Tobento\App\Crud\Testing\AbstractCrudTestCase
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
    
    public function testIsRendered()
    {
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.create']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('<form action="http://localhost/translations/bulk/create-locale" name="create-locale" method="POST">')
            ->assertBodyContains('Create')
            ->assertBodyContains('Locale')
            ->assertBodyContains('App ID')
            ->assertBodyNotContains('Auto Translate')
            ->assertBodyNotContains('Set translation entries to published status.');
    }
    
    public function testIsRenderedShowsAutoTranslateOptionWithPermission()
    {
        $this->withMachineTranslator();
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.create', 'translations.auto-translate']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('<form action="http://localhost/translations/bulk/create-locale" name="create-locale" method="POST">')
            ->assertBodyContains('Auto Translate');
    }
    
    public function testIsRenderedShowsPublishOptionWithPermission()
    {
        $this->withMachineTranslator();
        
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(method: 'GET', uri: $this->generateIndexUri());
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.create', 'translations.publish']);
        
        $this->getSeedFactory()->times(1)->create();

        $http->response()
            ->assertStatus(200)
            ->assertBodyContains('<form action="http://localhost/translations/bulk/create-locale" name="create-locale" method="POST">')
            ->assertBodyContains('Set translation entries to published status.');
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
            ->assertBodyNotContains('<form action="http://localhost/translations/bulk/create-locale" name="create-locale" method="POST">');
    }
    
    public function testCreatesAndQueuesJob()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '0',
                'create-locale_publish' => '0',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.create']);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Locale onboarding has started and will continue in the background.')
            ->assertCrudIndexEntityCount(1);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(CreateForLocaleJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['user_id'] === 0
                    && $job->getPayload()['locale'] === 'en'
                    && $job->getPayload()['app_id'] === 'root'
                    && $job->getPayload()['auto_translate'] === false
                    && $job->getPayload()['publish'] === false;
            })
            ->assertPushedTimes(CreateForLocaleJobHandler::class, 1);
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
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '0',
                'create-locale_publish' => '0',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.create']);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'database')
            ->assertPushedTimes(CreateForLocaleJobHandler::class, 1);
    }

    public function testCreatesAndQueuesJobWithAutoTranslate()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '1',
                'create-locale_publish' => '0',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions([
            'translations', 'translations.edit', 'translations.delete', 'translations.create', 'translations.auto-translate'
        ]);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(CreateForLocaleJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['locale'] === 'en'
                    && $job->getPayload()['app_id'] === 'root'
                    && $job->getPayload()['auto_translate'] === true
                    && $job->getPayload()['publish'] === false;
            });
    }
    
    public function testCreatesAndQueuesJobCantAutoTranslateWithoutPermission()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '1',
                'create-locale_publish' => '0',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions([
            'translations', 'translations.edit', 'translations.delete', 'translations.create'
        ]);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(CreateForLocaleJobHandler::class, function (JobInterface $job): bool {
                return $job->getPayload()['auto_translate'] === false;
            });
    }
    
    public function testCreatesAndQueuesJobWithPublish()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '0',
                'create-locale_publish' => '1',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions([
            'translations', 'translations.edit', 'translations.delete', 'translations.create', 'translations.publish'
        ]);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(CreateForLocaleJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['locale'] === 'en'
                    && $job->getPayload()['app_id'] === 'root'
                    && $job->getPayload()['auto_translate'] === false
                    && $job->getPayload()['publish'] === true;
            });
    }
    
    public function testCreatesAndQueuesJobCantPublishWithoutPermission()
    {
        $this->withMachineTranslator();
        
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '0',
                'create-locale_publish' => '1',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions([
            'translations', 'translations.edit', 'translations.delete', 'translations.create'
        ]);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()->assertStatus(200);
        
        $fakeQueue->queue(name: 'file')
            ->assertPushed(CreateForLocaleJobHandler::class, function (JobInterface $job): bool {
                return $job->getPayload()['publish'] === false;
            });
    }
    
    public function testFailsWithoutPermission()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'root',
                'create-locale_auto_translate' => '0',
                'create-locale_publish' => '0',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete']);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('Action create-locale not found.');
        
        $fakeQueue->queue(name: 'file')->assertNothingPushed();
    }
    
    public function testFailsWhenValidationError()
    {
        $fakeQueue = $this->fakeQueue();
        $auth = $this->fakeAuth();
        $http = $this->fakeHttp();
        $http->request(
            method: 'POST',
            uri: $this->generateBulkUri(action: 'create-locale'),
            body: [
                'create-locale_locale' => 'en',
                'create-locale_app_id' => 'invalid',
                'create-locale_auto_translate' => '0',
                'create-locale_publish' => '0',
            ],
        );
        
        $app = $this->bootingApp();
        $auth->addPermissions(['translations', 'translations.edit', 'translations.delete', 'translations.create']);

        $this->getSeedFactory(['app_id' => 'root'])->times(1)->create();
        
        $http->followRedirects()
            ->assertStatus(200)
            ->assertBodyContains('The create-locale_app_id items are invalid.');
        
        $fakeQueue->queue(name: 'file')->assertNothingPushed();
    }
}