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

namespace Tobento\App\Translation\Web\Test\Feature\Collector;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Collector\AllTranslations;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\Translation\MissingTranslationHandler;
use Tobento\Service\Translation\Modifiers;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\ResourceFile;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\Translator;
use Tobento\Service\Translation\TranslatorInterface;

class AllTranslationsTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }

    public function testIdAndNameMethod()
    {
        $app = $this->bootingApp();
        
        $collector = new AllTranslations(
            id: 'my-id',
            name: 'My Name',
            appId: 'root',
        );

        $this->assertSame('my-id', $collector->id());
        $this->assertSame('My Name', $collector->name());
    }
    
    public function testCollectsTranslationsUsingAppIdRoot()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, static function() {
                return new Translator(
                    resources: new Resources(
                        new Resource(
                            name: 'messages',
                            locale: 'en',
                            translations: ['hello' => 'Hello'],
                        ),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });
        
        $app = $this->bootingApp();
        
        $translationRepo = $app->get(TranslationRepositoryInterface::class);
        
        $this->assertSame(0, $translationRepo->count());
        
        $collector = new AllTranslations(
            id: 'app-root',
            name: 'Test',
            appId: 'root',
        );

        $collected = $collector->collect($app);

        $this->assertSame(1, $translationRepo->count());
        $this->assertSame(1, $collected->saved()->created()->count());
        $this->assertSame(0, $collected->saved()->updated()->count());
        $this->assertSame(0, $collected->saved()->skipped()->count());
    }
    
    public function testCollectOnlyCollectsOnlySpecifiedResources()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new Resource('messages', 'en', ['hello' => 'Hello']),
                        new Resource('validation', 'en', ['required' => 'Required', 'optional' => 'Optional']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
            collectOnly: ['messages'],
        );

        $collected = $collector->collect($app);

        $this->assertSame(1, $repo->count());
        $this->assertSame(1, $collected->saved()->created()->count());
        $this->assertSame(0, $collected->saved()->updated()->count());
        $this->assertSame(0, $collected->saved()->skipped()->count());
    }
    
    public function testCollectExceptSkipsSpecifiedResources()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new Resource('messages', 'en', ['hello' => 'Hello']),
                        new Resource('validation', 'en', ['required' => 'Required', 'optional' => 'Optional']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
            collectExcept: ['validation'],
        );

        $collected = $collector->collect($app);

        $this->assertSame(1, $repo->count());
        $this->assertSame(1, $collected->saved()->created()->count());
        $this->assertSame(0, $collected->saved()->updated()->count());
        $this->assertSame(0, $collected->saved()->skipped()->count());
    }
    
    public function testLocalesOnlyCollectsSpecifiedLocales()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new Resource('messages', 'en', ['hello' => 'Hello']),
                        new Resource('messages', 'de', ['hello' => 'Hallo', 'world' => 'Welt']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
            locales: ['de'],
        );

        $collected = $collector->collect($app);

        $this->assertSame(2, $repo->count());
        $this->assertSame(2, $collected->saved()->created()->count());
        $this->assertSame(0, $collected->saved()->updated()->count());
        $this->assertSame(0, $collected->saved()->skipped()->count());
    }

    public function testCollectorLoadsFallbackLocalesWhenLocalesEmpty()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                $translator = new Translator(
                    resources: new Resources(
                        new Resource('messages', 'en', ['hello' => 'Hello']),
                        new Resource('messages', 'fr', ['hello' => 'Bonjour']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );

                $translator->setLocale('fr');
                $translator->setLocaleFallbacks(['en']);

                return $translator;
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
        );

        $collected = $collector->collect($app);

        $this->assertSame(2, $repo->count());
        $this->assertSame(2, $collected->saved()->created()->count());
        $this->assertSame(0, $collected->saved()->updated()->count());
        $this->assertSame(0, $collected->saved()->skipped()->count());
    }
    
    public function testTranslatedByForDetectsCorrectSource()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new Resource('messages', 'en', ['hello' => 'Hello']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
        );

        $collector->collect($app);

        $saved = $repo->findById(1);

        $this->assertSame('system', $saved->translatedBy());
        $this->assertSame(null, $saved->resourceFilename());
    }
    
    public function testResourceFilenameIsStoredWhenResourceIsFile()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new ResourceFile(
                            file: __DIR__.'/../../../resources/trans/en/en-translation-web.json',
                            locale: 'en',
                            resourceName: 'trans-web',
                        ),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
        );

        $collector->collect($app);

        $saved = $repo->findById(1);

        $this->assertSame('en-translation-web.json', $saved->resourceFilename());
        $this->assertSame('system.file', $saved->translatedBy());
    }
    
    public function testSecondRunUpdatesExistingTranslations()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new Resource('messages', 'en', ['hello' => 'Hello']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $collector = new AllTranslations(
            id: 'test',
            name: 'Test',
            appId: 'root',
        );

        // First run: creates
        $collector->collect($app);

        // Second run: updates
        $collected = $collector->collect($app);

        $this->assertSame(1, $collected->saved()->updated()->count());
        $this->assertSame(0, $collected->saved()->created()->count());
    }
}