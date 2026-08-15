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
use Tobento\App\Translation\Web\Queue\SchedulePublishTranslationsJobHandler;
use Tobento\Service\Queue\JobInterface;

class TranslationCrudControllerPublishSchedulingTest extends \Tobento\App\Crud\Testing\AbstractCrudTestCase
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
    
    public function testUpdateActionQueuesPublishJobWhenModified()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false, queueName: 'file'),
        ]);
        
        $fakeQueue = $this->fakeQueue();
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'published',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
            'app_id' => 'root',
        ])->times(1)->create();

        $http->response()->assertStatus(302);

        $fakeQueue->queue(name: 'file')
            ->assertPushed(SchedulePublishTranslationsJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['app_id'] === 'root'
                    && $job->getPayload()['translation_id'] === 1;
            })
            ->assertPushedTimes(SchedulePublishTranslationsJobHandler::class, 1);
        
        // Clear because of unique job
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
    
    public function testUpdateActionDoesNotQueuePublishJobWhenNotModified()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false, queueName: 'file'),
        ]);
        
        $fakeQueue = $this->fakeQueue();
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'imported',
            'translation' => 'Welcome',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
            'app_id' => 'root',
        ])->times(1)->create();

        $http->response()->assertStatus(302);

        $fakeQueue->queue(name: 'file')->assertNothingPushed();
        
        // Clear because of unique job
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
    
    public function testUpdateActionQueuesPublishJobWhenModifiedOnce()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false, queueName: 'file'),
        ]);
        
        $fakeQueue = $this->fakeQueue();
        
        $http = $this->fakeHttp();
        $http->previousUri($this->generateIndexUri());
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'published',
            'translation' => 'Willkommen',
        ]);
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
            'app_id' => 'root',
        ])->times(1)->create();

        $http->response()->assertStatus(302);

        $fakeQueue->queue(name: 'file')->assertPushedTimes(SchedulePublishTranslationsJobHandler::class, 1);
        
        $http->request(method: 'PATCH', uri: $this->generateUpdateUri(id: 1))->body([
            'status' => 'draft',
            'translation' => 'Willkommen',
        ]);
        
        $http->response()->assertStatus(302);

        $fakeQueue->queue(name: 'file')->assertPushedTimes(SchedulePublishTranslationsJobHandler::class, 1);
        
        // Clear because of unique job
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
    
    public function testDeleteActionQueuesPublishJob()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false, queueName: 'file'),
        ]);
        
        $fakeQueue = $this->fakeQueue();
        
        $http = $this->fakeHttp();
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
            'app_id' => 'root',
        ])->times(1)->create();

        $http->response()->assertStatus(302);

        $fakeQueue->queue(name: 'file')
            ->assertPushed(SchedulePublishTranslationsJobHandler::class, function (JobInterface $job): bool {
                return
                    $job->getPayload()['app_id'] === 'root'
                    && $job->getPayload()['translation_id'] === 1;
            })
            ->assertPushedTimes(SchedulePublishTranslationsJobHandler::class, 1);
        
        // Clear because of unique job
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
    
    public function testDeleteActionQueuesPublishJobOnce()
    {
        $this->fakeConfig()->with('translation-web.features', [
            new Feature\Translations(withAcl: false, queueName: 'file'),
        ]);
        
        $fakeQueue = $this->fakeQueue();
        
        $http = $this->fakeHttp();
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));
        
        $this->getSeedFactory([
            'status' => 'imported',
            'message' => 'Welcome',
            'translation' => 'Welcome',
            'resource_locale' => 'de',
            'app_id' => 'root',
        ])->times(1)->create();

        $http->response()->assertStatus(302);

        $fakeQueue->queue(name: 'file')->assertPushedTimes(SchedulePublishTranslationsJobHandler::class, 1);
        
        // Second Time:
        $http->request(method: 'DELETE', uri: $this->generateDeleteUri(id: 1));
        
        $http->response()->assertStatus(302);
        
        $fakeQueue->queue(name: 'file')->assertPushedTimes(SchedulePublishTranslationsJobHandler::class, 1);
        
        // Clear because of unique job
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
}