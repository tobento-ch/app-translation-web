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

namespace Tobento\App\Translation\Web\Test\Feature\MissingHandler;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\MissingHandler;
use Tobento\App\Translation\Web\Queue\AutoTranslateMissingJobHandler;
use Tobento\Service\Queue\JobInterface;
use Tobento\Service\Translation\MissingTranslationHandlerInterface;
use Tobento\Service\Translation\Modifiers;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\ResourceFile;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\Translator;
use Tobento\Service\Translation\TranslatorInterface;

class AutoTranslateTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }
    
    public function testMissingTranslationWillQueueJob()
    {
        $this->onCreateApp(function(AppInterface $app) {

            // Bind a custom translator that uses AutoTranslate for missing translations
            $app->on(TranslatorInterface::class, function() use ($app) {

                $missingTranslationHandler = $app->make(MissingHandler\AutoTranslate::class, [
                    'queueName' => 'file',
                    'auto' => ['missing'],   // enable missing event
                    'translatorName' => 'null',
                ]);

                // Translator with only EN resources → "MISSING" will be missing
                $translator = new Translator(
                    resources: new Resources(
                        new Resource('*', 'en', ['hello' => 'Hello']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: $missingTranslationHandler,
                );

                // Default locale
                $translator->setLocale('en');

                return $translator;
            });
        });

        $fakeQueue = $this->fakeQueue();

        $app = $this->bootingApp();

        $translator = $app->get(TranslatorInterface::class);

        // Trigger missing translation
        $translated = $translator->trans('MISSING');

        // Should return original message
        $this->assertSame('MISSING', $translated);

        // Assert job was pushed to queue "file"
        $fakeQueue->queue(name: 'file')
            ->assertPushed(AutoTranslateMissingJobHandler::class, function (JobInterface $job): bool {

                $payload = $job->getPayload();

                return $payload['appId'] === 'root'
                    && $payload['reason'] === 'missing'
                    && $payload['translation'] === 'MISSING'
                    && $payload['text'] === 'MISSING'
                    && $payload['parameters'] === []
                    && $payload['locale'] === 'en'
                    && $payload['targetLocale'] === 'en'
                    && $payload['translator'] === 'null';
            });

        // Cleanup
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
    
    public function testFallbackTranslationWillQueueJob()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() use ($app) {
                $missingTranslationHandler = $app->make(MissingHandler\AutoTranslate::class, [
                    'queueName' => 'file',
                    'auto' => ['fallback'], // enable fallback event
                    'translatorName' => 'null',
                ]);
                
                $translator = new Translator(
                    resources: new Resources(
                        new Resource('*', 'en', ['hello' => 'Hello']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: $missingTranslationHandler,
                );

                $translator->setLocale('en');
                $translator->setLocaleFallbacks(['fr' => 'en']);

                return $translator;
            });
        });
                
        $fakeQueue = $this->fakeQueue();
        
        $app = $this->bootingApp();

        // Translator with fallback locale configured by TranslationWeb boot
        $translator = $app->get(TranslatorInterface::class);

        // Trigger fallback:
        $translated = $translator->trans('hello', [], 'fr');

        // Should return the fallback translation (from "en")
        $this->assertSame('Hello', $translated);

        // Assert job was pushed to queue "file"
        $fakeQueue->queue(name: 'file')
            ->assertPushed(AutoTranslateMissingJobHandler::class, function (JobInterface $job): bool {
                $payload = $job->getPayload();

                return $payload['appId'] === 'root'
                    && $payload['reason'] === 'fallback'
                    && $payload['translation'] === 'Hello'
                    && $payload['text'] === 'hello'
                    && $payload['parameters'] === []
                    && $payload['fallbackLocale'] === 'en'
                    && $payload['targetLocale'] === 'fr'
                    && $payload['translator'] === 'null';
            });

        // Cleanup
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
    
    public function testFallbackToDefaultTranslationWillQueueJob()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() use ($app) {

                // AutoTranslate handler with fallbackToDefault enabled
                $missingTranslationHandler = $app->make(MissingHandler\AutoTranslate::class, [
                    'queueName' => 'file',
                    'auto' => ['fallbackToDefault'],
                    'translatorName' => 'null',
                ]);

                // Translator with only default locale "en"
                $translator = new Translator(
                    resources: new Resources(
                        new Resource('*', 'en', ['hello' => 'Hello']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: $missingTranslationHandler,
                );

                // Default locale is "en"
                $translator->setLocale('en');

                // No fallback for "fr" → will fallback to default locale "en"
                $translator->setLocaleFallbacks([]);

                return $translator;
            });
        });

        $fakeQueue = $this->fakeQueue();

        $app = $this->bootingApp();

        $translator = $app->get(TranslatorInterface::class);

        // Trigger fallbackToDefault:
        // - "hello" exists only in default locale "en"
        // - requested locale "fr" has no fallback defined
        $translated = $translator->trans('hello', [], 'fr');

        // Should return the default locale translation
        $this->assertSame('Hello', $translated);

        // Assert job was pushed to queue "file"
        $fakeQueue->queue(name: 'file')
            ->assertPushed(AutoTranslateMissingJobHandler::class, function (JobInterface $job): bool {

                $payload = $job->getPayload();

                return $payload['appId'] === 'root'
                    && $payload['reason'] === 'fallbackToDefault'
                    && $payload['translation'] === 'Hello'
                    && $payload['text'] === 'hello'
                    && $payload['parameters'] === []
                    && $payload['defaultLocale'] === 'en'
                    && $payload['targetLocale'] === 'fr'
                    && $payload['translator'] === 'null';
            });

        // Cleanup
        $fakeQueue->clearQueue(
            queue: $fakeQueue->queue(name: 'file')
        );
    }
}