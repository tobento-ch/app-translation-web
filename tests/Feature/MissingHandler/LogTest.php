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
use Tobento\App\Testing\Logging\LogEntry;
use Tobento\App\Translation\Web\MissingHandler;
use Tobento\App\Translation\Web\Queue\AutoTranslateMissingJobHandler;
use Tobento\Service\Translation\MissingTranslationHandlerInterface;
use Tobento\Service\Translation\Modifiers;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\ResourceFile;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\Translator;
use Tobento\Service\Translation\TranslatorInterface;

class LogTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }
    
    public function testMissingTranslationWillLog()
    {
        $this->onCreateApp(function(AppInterface $app) {

            // Bind a custom translator that uses AutoTranslate for missing translations
            $app->on(TranslatorInterface::class, function() use ($app) {

                $missingTranslationHandler = $app->make(MissingHandler\Log::class);

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

        $fakeLogging = $this->fakeLogging();

        $app = $this->bootingApp();

        $translator = $app->get(TranslatorInterface::class);

        // Trigger missing translation
        $translated = $translator->trans('MISSING');

        // Should return original message
        $this->assertSame('MISSING', $translated);

        $fakeLogging->logger()
            ->assertLogged(fn (LogEntry $log): bool =>
                $log->level === 'warning'
                && $log->message === 'Missing translation for the message' 
            );
    }
    
    public function testFallbackTranslationWillQueueJob()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() use ($app) {
                
                $missingTranslationHandler = $app->make(MissingHandler\Log::class);
                
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
                
        $fakeLogging = $this->fakeLogging();
        
        $app = $this->bootingApp();

        // Translator with fallback locale configured by TranslationWeb boot
        $translator = $app->get(TranslatorInterface::class);

        // Trigger fallback:
        $translated = $translator->trans('hello', [], 'fr');

        // Should return the fallback translation (from "en")
        $this->assertSame('Hello', $translated);
        
        $fakeLogging->logger()
            ->assertLogged(fn (LogEntry $log): bool =>
                $log->level === 'warning'
                && $log->message === 'Missing translation message fallbacked to the locale defined' 
            );
    }
    
    public function testFallbackToDefaultTranslationWillQueueJob()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() use ($app) {

                $missingTranslationHandler = $app->make(MissingHandler\Log::class);

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

        $fakeLogging = $this->fakeLogging();

        $app = $this->bootingApp();

        $translator = $app->get(TranslatorInterface::class);

        // Trigger fallbackToDefault:
        // - "hello" exists only in default locale "en"
        // - requested locale "fr" has no fallback defined
        $translated = $translator->trans('hello', [], 'fr');

        // Should return the default locale translation
        $this->assertSame('Hello', $translated);
        
        $fakeLogging->logger()
            ->assertLogged(fn (LogEntry $log): bool =>
                $log->level === 'warning'
                && $log->message === 'Missing translation message fallbacked to default locale.' 
            );
    }
}