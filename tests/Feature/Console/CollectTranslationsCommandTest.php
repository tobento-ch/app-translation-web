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

namespace Tobento\App\Translation\Web\Test\Feature\Console;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Collector\AllTranslations;
use Tobento\App\Translation\Web\Collector\Collectors;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Console\CollectTranslationsCommand;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\Console\Test\TestCommand;
use Tobento\Service\Translation\MissingTranslationHandler;
use Tobento\Service\Translation\Modifiers;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\Translator;
use Tobento\Service\Translation\TranslatorInterface;

class CollectTranslationsCommandTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;
    
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }
    
    public function testCollectsTranslations()
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
        
        new TestCommand(
            command: CollectTranslationsCommand::class,
            input: []
        )
        ->expectsTable(
            headers: ['Collector ID', 'Collector Name', 'created', 'updated', 'skipped'],
            rows: [
                ['app-root', 'All Translations for the root app', 1, 0, 0],
            ],
        )
        ->expectsExitCode(0)
        ->execute($app->container());
        
        $translationRepo = $app->get(TranslationRepositoryInterface::class);

        $this->assertSame(1, $translationRepo->count());
    }
    
    public function testCollectsOnlySpecificCollector()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Override translator
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

            // Override collectors
            $app->on(CollectorsInterface::class, function() {
                return new Collectors(
                    new AllTranslations('first', 'First Collector', 'root'),
                    new AllTranslations('second', 'Second Collector', 'root'),
                );
            });
        });

        $app = $this->bootingApp();

        new TestCommand(
            command: CollectTranslationsCommand::class,
            input: ['--collectorId' => ['first']]
        )
        ->expectsTable(
            headers: ['Collector ID', 'Collector Name', 'created', 'updated', 'skipped'],
            rows: [
                ['first', 'First Collector', 1, 0, 0],
            ],
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $repo = $app->get(TranslationRepositoryInterface::class);

        // Only one collector ran → only one translation saved
        $this->assertSame(1, $repo->count());
    }
    
    public function testInvalidCollectorId()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Override translator (won't be used because no collector matches)
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

            // Override collectors with known IDs
            $app->on(CollectorsInterface::class, function() {
                return new Collectors(
                    new AllTranslations('first', 'First Collector', 'root'),
                    new AllTranslations('second', 'Second Collector', 'root'),
                );
            });
        });

        $app = $this->bootingApp();

        new TestCommand(
            command: CollectTranslationsCommand::class,
            input: ['--collectorId' => ['does-not-exist']]
        )
        ->expectsTable(
            headers: ['Collector ID', 'Collector Name', 'created', 'updated', 'skipped'],
            rows: [] // no collectors matched
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $repo = $app->get(TranslationRepositoryInterface::class);

        // No collectors ran, no translations saved
        $this->assertSame(0, $repo->count());
    }
    
    public function testCollectsTranslationsVerbose()
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

        new TestCommand(
            command: CollectTranslationsCommand::class,
            input: ['-v'] // verbose mode
        )
        ->expectsTable(
            headers: ['Collector ID', 'Collector Name', 'created', 'updated', 'skipped'],
            rows: [
                ['app-root', 'All Translations for the root app', 1, 0, 0],
            ],
        )
        ->expectsOutputToContain('Collector: app-root')
        ->expectsOutputToContain('"message": "hello"')
        ->expectsOutputToContain('"translation": "Hello"')
        ->expectsExitCode(0)
        ->execute($app->container());
    }
}