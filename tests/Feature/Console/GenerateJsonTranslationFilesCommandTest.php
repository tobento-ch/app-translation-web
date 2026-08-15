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
use Tobento\App\Translation\Web\Console\GenerateJsonTranslationFilesCommand;
use Tobento\App\Translation\Web\Strategy;
use Tobento\App\Translation\Web\TranslationEntityFactory;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\Translation\Web\TranslationStorageRepository;
use Tobento\Apps\AppsInterface;
use Tobento\Service\Console\Test\TestCommand;
use Tobento\Service\Filesystem\Dir;
use Tobento\Service\Storage\Items;
use Tobento\Service\Storage\ItemsInterface;
use Tobento\Service\Translation\MissingTranslationHandler;
use Tobento\Service\Translation\Modifiers;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\Translator;
use Tobento\Service\Translation\TranslatorInterface;

class GenerateJsonTranslationFilesCommandTest extends \Tobento\App\Testing\TestCase
{
    use \Tobento\App\Testing\Database\RefreshDatabases;

    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');

        // Register FileResources strategy
        $app->on(Strategy\TranslationsPublishInterface::class, static function() {
            return new Strategy\FileResources(
                folderName: 'translations',
                dirName: 'translations',                
            );
        });

        // Boot translation web
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);

        return $app;
    }

    public function testGeneratesJsonFile()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Override translator with one translation
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

        // Insert a modified translation into the repository
        $repo = $app->get(TranslationRepositoryInterface::class);
        $saved = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system',
        );

        // Mark as modified and published
        $repo->updateById(1, ['is_modified' => true, 'status' => 'published']);

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsOutputToContain('App (root): Starting to generate files.')
        ->expectsExitCode(0)
        ->execute($app->container());

        // Assert file exists
        $file = $app->dir('translations').'en/en-messages.generated.json';
        $this->assertFileExists($file);

        // Assert JSON content
        $json = json_decode(file_get_contents($file), true);
        $this->assertSame(['hello' => 'Hello'], $json);

        // Assert modified flag reset
        $this->assertSame(0, $repo->findAllModified(appId: $app->id())->count());
        
        // Cleanup
        new Dir()->delete($app->dir('translations'));
    }
    
    public function testDeletesJsonFileWhenNoPublishedTranslationsRemain()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Override translator (not used here, but required by boot)
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

        $repo = $app->get(TranslationRepositoryInterface::class);

        // 1. Create a published + modified translation
        $saved = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system',
        );

        // Mark as modified + published
        $repo->updateById($saved->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // Run command → file should be created
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $file = $app->dir('translations').'en/en-messages.generated.json';
        $this->assertFileExists($file);

        // 2. Mark translation as UNPUBLISHED + modified again
        $repo->updateById($saved->entity()->id(), ['is_modified' => true, 'status' => 'draft']);

        // Run command again → file should be deleted
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $this->assertFileDoesNotExist($file);

        // Cleanup
        new Dir()->delete($app->dir('translations'));
    }
    
    public function testSkipsUnpublishedTranslations()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Translator override (not used but required)
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

        $repo = $app->get(TranslationRepositoryInterface::class);

        // Create a translation that is modified but NOT published
        $saved = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system',
        );

        // Mark as modified but NOT published
        $repo->updateById($saved->entity()->id(), [
            'is_modified' => true,
            'status' => 'draft', // not published
        ]);

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        // File should NOT exist
        $file = $app->dir('translations').'en/en-messages.generated.json';
        $this->assertFileDoesNotExist($file);

        // Modified flag should be reset
        $this->assertSame(0, $repo->findAllModified(appId: $app->id())->count());

        // Cleanup
        new Dir()->delete($app->dir('translations'));
    }
    
    public function testNothingGeneratedWhenStrategyMissing()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Override the strategy
            $app->on(Strategy\TranslationsPublishInterface::class, static function() {
                return null;
            })->priority(-100);

            // Translator override (not used but required)
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

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsOutputToContain('App (root): Starting to generate files.')
        ->expectsOutputToContain('Nothing generated: TranslationsPublishInterface not found.')
        ->expectsExitCode(0)
        ->execute($app->container());

        // Ensure no directory was created
        $this->assertDirectoryDoesNotExist($app->dir('translations'));
    }
    
    public function testNothingGeneratedWhenStrategyNotFileResources()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Remove the strategy binding entirely
            $app->on(Strategy\TranslationsPublishInterface::class, static function() {
                return new Strategy\NullStrategy();
            })->priority(-100);

            // Translator override (not used but required)
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
        $app->dirs()->dir($app->dir('app').'translations/', 'translations');

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsOutputToContain('App (root): Starting to generate files.')
        ->expectsOutputToContain('Nothing generated: FileResources strategy not active.')
        ->expectsExitCode(0)
        ->execute($app->container());

        // Ensure no directory was created
        $this->assertDirectoryDoesNotExist($app->dir('translations'));
    }
    
    public function testDetermineFileForTranslationNamingRules()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Translator override
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
        $repo = $app->get(TranslationRepositoryInterface::class);

        // 1. File-based resource
        $t1 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: 'custom.json',
            message: 'a',
            translation: 'A',
            translatedBy: 'system',
        );
        $repo->updateById($t1->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // 2. Default resource (*)
        $t2 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'b',
            translation: 'B',
            translatedBy: 'system',
        );
        $repo->updateById($t2->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // 3. Named resource (shop)
        $t3 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'shop',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'c',
            translation: 'C',
            translatedBy: 'system',
        );
        $repo->updateById($t3->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $base = $app->dir('app').'translations/en/';

        // Assert files exist
        $this->assertFileExists($base.'custom.json');
        $this->assertFileExists($base.'en.generated.json');
        $this->assertFileExists($base.'en-shop.generated.json');

        // Assert contents
        $this->assertSame(['a' => 'A'], json_decode(file_get_contents($base.'custom.json'), true));
        $this->assertSame(['b' => 'B'], json_decode(file_get_contents($base.'en.generated.json'), true));
        $this->assertSame(['c' => 'C'], json_decode(file_get_contents($base.'en-shop.generated.json'), true));

        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
    }
    
    public function testKeysAreSortedAlphabeticallyInGeneratedFile()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Translator override
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
        $repo = $app->get(TranslationRepositoryInterface::class);

        // Create translations in unsorted order
        $items = [
            ['key' => 'zeta',   'value' => 'Z'],
            ['key' => 'alpha',  'value' => 'A'],
            ['key' => 'beta',   'value' => 'B'],
            ['key' => 'Gamma',  'value' => 'G'], // mixed case
        ];

        foreach ($items as $item) {
            $saved = $repo->saveTranslation(
                appId: $app->id(),
                resourceName: 'messages',
                resourceLocale: 'en',
                resourceGroup: '',
                resourcePriority: 0,
                resourceFilename: '',
                message: $item['key'],
                translation: $item['value'],
                translatedBy: 'system',
            );

            $repo->updateById($saved->entity()->id(), [
                'is_modified' => true,
                'status' => 'published',
            ]);
        }

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $file = $app->dir('app').'translations/en/en-messages.generated.json';

        $this->assertFileExists($file);

        $json = json_decode(file_get_contents($file), true);

        // Assert sorted keys
        $this->assertSame(
            ['alpha', 'beta', 'Gamma', 'zeta'],
            array_keys($json)
        );

        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
    }
    
    public function testGeneratesSeparateFilesForMultipleLocales()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Translator override
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
        $repo = $app->get(TranslationRepositoryInterface::class);

        // EN translation
        $tEn = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system',
        );
        $repo->updateById($tEn->entity()->id(), [
            'is_modified' => true,
            'status' => 'published',
        ]);

        // DE translation
        $tDe = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'de',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hallo',
            translation: 'Hallo',
            translatedBy: 'system',
        );
        $repo->updateById($tDe->entity()->id(), [
            'is_modified' => true,
            'status' => 'published',
        ]);

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $base = $app->dir('app').'translations/';

        // Assert directories exist
        $this->assertDirectoryExists($base.'en/');
        $this->assertDirectoryExists($base.'de/');

        // Assert files exist
        $this->assertFileExists($base.'en/en-messages.generated.json');
        $this->assertFileExists($base.'de/de-messages.generated.json');

        // Assert contents
        $this->assertSame(
            ['hello' => 'Hello'],
            json_decode(file_get_contents($base.'en/en-messages.generated.json'), true)
        );

        $this->assertSame(
            ['hallo' => 'Hallo'],
            json_decode(file_get_contents($base.'de/de-messages.generated.json'), true)
        );

        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
    }

    public function testMixedFilenameRulesAcrossMultipleLocales()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Translator override
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
        $repo = $app->get(TranslationRepositoryInterface::class);

        // EN translations
        // 1. File-based resource
        $t1 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: 'custom.json',
            message: 'a',
            translation: 'A',
            translatedBy: 'system',
        );
        $repo->updateById($t1->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // 2. Default resource (*)
        $t2 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'b',
            translation: 'B',
            translatedBy: 'system',
        );
        $repo->updateById($t2->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // 3. Named resource (shop)
        $t3 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'shop',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'c',
            translation: 'C',
            translatedBy: 'system',
        );
        $repo->updateById($t3->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // DE translations
        // 4. File-based resource
        $t4 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'de',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: 'custom-de.json',
            message: 'x',
            translation: 'X',
            translatedBy: 'system',
        );
        $repo->updateById($t4->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // 5. Default resource (*)
        $t5 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: '*',
            resourceLocale: 'de',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'y',
            translation: 'Y',
            translatedBy: 'system',
        );
        $repo->updateById($t5->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // 6. Named resource (shop)
        $t6 = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'shop',
            resourceLocale: 'de',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'z',
            translation: 'Z',
            translatedBy: 'system',
        );
        $repo->updateById($t6->entity()->id(), ['is_modified' => true, 'status' => 'published']);

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        $base = $app->dir('app').'translations/';

        // Assert EN files
        $this->assertFileExists($base.'en/custom.json');
        $this->assertFileExists($base.'en/en.generated.json');
        $this->assertFileExists($base.'en/en-shop.generated.json');

        $this->assertSame(['a' => 'A'], json_decode(file_get_contents($base.'en/custom.json'), true));
        $this->assertSame(['b' => 'B'], json_decode(file_get_contents($base.'en/en.generated.json'), true));
        $this->assertSame(['c' => 'C'], json_decode(file_get_contents($base.'en/en-shop.generated.json'), true));

        // Assert DE files
        $this->assertFileExists($base.'de/custom-de.json');
        $this->assertFileExists($base.'de/de.generated.json');
        $this->assertFileExists($base.'de/de-shop.generated.json');

        $this->assertSame(['x' => 'X'], json_decode(file_get_contents($base.'de/custom-de.json'), true));
        $this->assertSame(['y' => 'Y'], json_decode(file_get_contents($base.'de/de.generated.json'), true));
        $this->assertSame(['z' => 'Z'], json_decode(file_get_contents($base.'de/de-shop.generated.json'), true));

        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
    }
    
    public function testResetModifiedIsCalledForAllWrittenTranslations()
    {
        $this->onCreateApp(function(AppInterface $app) {
            // Translator override
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
        $repo = $app->get(TranslationRepositoryInterface::class);

        // Create multiple modified translations
        $ids = [];

        foreach ([
            ['key' => 'a', 'value' => 'A'],
            ['key' => 'b', 'value' => 'B'],
            ['key' => 'c', 'value' => 'C'],
        ] as $item) {
            $saved = $repo->saveTranslation(
                appId: $app->id(),
                resourceName: 'messages',
                resourceLocale: 'en',
                resourceGroup: '',
                resourcePriority: 0,
                resourceFilename: '',
                message: $item['key'],
                translation: $item['value'],
                translatedBy: 'system',
            );

            $repo->updateById($saved->entity()->id(), [
                'is_modified' => true,
                'status' => 'published',
            ]);

            $ids[] = $saved->entity()->id();
        }

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsExitCode(0)
        ->execute($app->container());

        // Assert file exists
        $file = $app->dir('app').'translations/en/en-messages.generated.json';
        $this->assertFileExists($file);

        // Assert modified flags were reset
        foreach ($ids as $id) {
            $entity = $repo->findById($id);
            $this->assertFalse($entity->isModified(), sprintf('Translation %s was not reset', $id));
        }

        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
    }
    
    public function testInfiniteLoopBreaksAfterMaxIterations()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslationRepositoryInterface::class, static function($repo, TranslationEntityFactory $entityFactory) {
                return new class(
                    storage: $repo->storage(),
                    table: 'translations',
                    translationEntityFactory: $entityFactory,
                ) extends TranslationStorageRepository {
                    public function resetModified(iterable $translations): ItemsInterface
                    {
                        return new Items([]);
                    }
                };
            });
            
            // Translator override
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
        $repo = $app->get(TranslationRepositoryInterface::class);

        $saved = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'loop',
            translation: 'Loop',
            translatedBy: 'system',
        );

        $repo->updateById($saved->entity()->id(), [
            'is_modified' => true,
            'status' => 'published',
        ]);

        // Run command
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsOutputToContain('App (root): Starting to generate files.')
        ->expectsOutputToContain('Publish loop reached max iterations (10). Stopping to avoid infinite loop.')
        ->expectsExitCode(0)
        ->execute($app->container());
        
        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
    }
    
    public function testGeneratesFilesForAllApps()
    {
        // Boot multiple apps
        $this->onCreateApp(function(AppInterface $app) {
            $app->boot(\Tobento\App\Translation\Web\Test\App\Backend::class);
            $app->boot(\Tobento\App\Translation\Web\Test\App\Frontend::class);
        });
        
        $app = $this->bootingApp();
        
        // Retrieve the apps container
        $apps = $app->get(AppsInterface::class);

        // Get backend app
        $backend = $apps->get('backend')->app();
        $backend->on(Strategy\TranslationsPublishInterface::class, static function() {
            return new Strategy\FileResources(
                folderName: 'translations',
                dirName: 'translations',                
            );
        });
        $backend->on(TranslatorInterface::class, static function() {
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
        $backend->booting();
        
        // Add translations to backend
        $repoBackend = $backend->get(TranslationRepositoryInterface::class);
        $t1 = $repoBackend->saveTranslation(
            appId: $backend->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello Backend',
            translatedBy: 'system',
        );
        $repoBackend->updateById($t1->entity()->id(), [
            'is_modified' => true,
            'status' => 'published',
        ]);
        
        // Get frontend app
        $frontend = $apps->get('frontend')->app();
        $frontend->on(Strategy\TranslationsPublishInterface::class, static function() {
            return new Strategy\FileResources(
                folderName: 'translations',
                dirName: 'translations',                
            );
        });
        $frontend->on(TranslatorInterface::class, static function() {
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
        $frontend->booting();
        
        // Add translations to frontend
        $repoFrontend = $frontend->get(TranslationRepositoryInterface::class);
        $t2 = $repoFrontend->saveTranslation(
            appId: $frontend->id(),
            resourceName: 'messages',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello Frontend',
            translatedBy: 'system',
        );
        $repoFrontend->updateById($t2->entity()->id(), [
            'is_modified' => true,
            'status' => 'published',
        ]);
        
        $apps->bootingApp($app);

        // Run the command on the root app (it will process all apps)
        new TestCommand(
            command: GenerateJsonTranslationFilesCommand::class,
            input: []
        )
        ->expectsOutputToContain('App (backend): Starting to generate files.')
        ->expectsOutputToContain('App (frontend): Starting to generate files.')
        ->expectsOutputToContain('App (root): Starting to generate files.')
        ->expectsExitCode(0)
        ->execute($app->container());

        // Assert backend file exists
        $fileBackend = $backend->dir('app').'translations/en/en-messages.generated.json';
        $this->assertFileExists($fileBackend);
        $this->assertSame(
            ['hello' => 'Hello Backend'],
            json_decode(file_get_contents($fileBackend), true)
        );

        // Assert frontend file exists
        $fileFrontend = $frontend->dir('app').'translations/en/en-messages.generated.json';
        $this->assertFileExists($fileFrontend);
        $this->assertSame(
            ['hello' => 'Hello Frontend'],
            json_decode(file_get_contents($fileFrontend), true)
        );

        // Assert modified flags reset independently
        $this->assertFalse($repoBackend->findById($t1->entity()->id())->isModified());
        $this->assertFalse($repoFrontend->findById($t2->entity()->id())->isModified());

        // Cleanup
        new Dir()->delete($app->dir('app').'translations/');
        new Dir()->delete($backend->dir('app').'translations/');
        new Dir()->delete($frontend->dir('app').'translations/');
    }
}