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

namespace Tobento\App\Translation\Web\Test\Feature\Strategy;

use InvalidArgumentException;
use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Queue\GenerateJsonTranslationFilesJobHandler;
use Tobento\App\Translation\Web\Strategy\FileResources;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\Service\Queue\Job;
use Tobento\Service\Queue\JobInterface;
use Tobento\Service\Queue\QueueInterface;

class FileResourcesTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }

    public function testImplementsInterface()
    {
        $app = $this->bootingApp();
        
        $this->assertInstanceOf(TranslationsPublishInterface::class, new FileResources());
    }
    
    public function testConstructorRejectsInvalidFolderName()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $app = $this->bootingApp();
        
        new FileResources(folderName: 'INVALID!');
    }

    public function testConstructorRejectsReservedFolderName()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $app = $this->bootingApp();
        
        new FileResources(folderName: 'trans');
    }

    public function testConstructorRejectsInvalidDirName()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $app = $this->bootingApp();
        
        new FileResources(dirName: 'INVALID!');
    }

    public function testConstructorRejectsReservedDirName()
    {
        $this->expectException(InvalidArgumentException::class);
        
        $app = $this->bootingApp();
        
        new FileResources(dirName: 'trans');
    }

    public function testPublishRegistersDirectory()
    {
        $app = $this->bootingApp();

        $strategy = new FileResources(
            folderName: 'translations-custom',
            dirName: 'translations-custom',
        );

        $strategy->publish($app);

        $this->assertTrue($app->dirs()->has('translations-custom'));
    }

    public function testPublishThrowsIfDirectoryAlreadyExists()
    {
        $app = $this->bootingApp();

        $strategy = new FileResources(
            folderName: 'translations-custom',
            dirName: 'translations-custom',
        );

        // First publish is fine
        $strategy->publish($app);

        // Second publish must fail
        $this->expectException(InvalidArgumentException::class);
        $strategy->publish($app);
    }

    public function testSchedulePublishPushesJobToQueue()
    {
        $fakeQueue = $this->fakeQueue();
        
        $app = $this->bootingApp();

        $strategy = new FileResources();

        $strategy->schedulePublish($app);
        
        $fakeQueue->queue('sync')
            ->assertPushed(GenerateJsonTranslationFilesJobHandler::class, function(JobInterface $job) use ($app) {
                return $job->getPayload()['app_id'] === $app->id();
            });
                
        // Cleanup
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'sync')
        );
    }

    public function testSchedulePublishUsesCustomQueueName()
    {
        $fakeQueue = $this->fakeQueue();
        
        $app = $this->bootingApp();

        $strategy = new FileResources(queueName: 'file');

        $strategy->schedulePublish($app);

        $fakeQueue->queue('file')
            ->assertPushed(GenerateJsonTranslationFilesJobHandler::class);
        
        // Cleanup
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
}