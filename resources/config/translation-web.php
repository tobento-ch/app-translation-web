<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

use Tobento\App\Translation\Web\Collector;
use Tobento\App\Translation\Web\Feature;
use Tobento\App\Translation\Web\MissingHandler;
use Tobento\App\Translation\Web\Onboarding;
use Tobento\App\Translation\Web\Strategy;
use Tobento\App\Translation\Web\TranslationEntityFactory;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\Translation\Web\TranslationStorageRepository;
use Tobento\Service\Database\DatabasesInterface;
use Tobento\Service\MachineTranslator\MachineTranslatorsInterface;
use Tobento\Service\Translation\MissingTranslationHandlerInterface;

return [

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Configure the features you wish to use or remove uneeded.
    |
    | See: https://github.com/tobento-ch/app-translation-web#features
    |
    */
    
    'features' => [
        
        new Feature\Translations(
            // A menu name to show the translations link, or null for no menu entry.
            menu: 'main',
            menuLabel: 'Translations',
            // A menu parent name (e.g. 'system') or null if none.
            menuParent: null,

            // Optional queue name used for all translation background jobs.
            // If null (default), the default queue is used.
            queueName: 'file',

            // You may disable ACL while testing.
            // Otherwise, only users with the required permissions can access the page.
            withAcl: true,
        ),
        
        // Required if Feature\Translations::class is enabled,
        // otherwise collecting or scheduling translation publishing
        // will not be available.
        Feature\TranslationsConsoleCommands::class,
        
        // Enables auto‑translate bulk action and onboarding auto-translate.
        // Does nothing unless a machine translator is configured.
        new Feature\MachineTranslation(),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Collectors
    |--------------------------------------------------------------------------
    |
    | Configure the collectors you wish to use or remove uneeded.
    |
    | See: https://github.com/tobento-ch/app-translation-web#collectors
    |
    */
    
    'collectors' => [
        new Collector\AllTranslations(
            id: 'app-root',
            name: 'All Translations for the root app',
            appId: 'root',
            //collectOnly: [],
            //collectExcept: [],
            //locales: [],
        ),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Interfaces
    |--------------------------------------------------------------------------
    |
    | Do not change the interface's names as it may be used in other app bundles!
    |
    */
    
    'interfaces' => [
        
        // Strategy
        // See: https://github.com/tobento-ch/app-translation-web#publishing-strategies
        
        //Strategy\TranslationsPublishInterface::class => Strategy\NullStrategy::class,
        Strategy\TranslationsPublishInterface::class => Strategy\FileResources::class,
        //Strategy\TranslationsPublishInterface::class => Strategy\InMemoryResources::class,
        
        MissingTranslationHandlerInterface::class => MissingHandler\Log::class,
        /*MissingTranslationHandlerInterface::class => static function($app) {
            return new \Tobento\Service\Translation\MissingHandler\Chain(
                new MissingHandler\Log(
                    logger: $app->get(LoggerInterface::class)
                ),
                new MissingHandler\AutoTranslate(
                    translator: $app->get(MachineTranslatorsInterface::class)->get('null')
                ),
            );
        },*/

        // Onboarding
        // See: https://github.com/tobento-ch/app-translation-web#onboarding-service
        
        Onboarding\OnboardingInterface::class => static function(
            TranslationRepositoryInterface $translationRepository,
            null|MachineTranslatorsInterface $machineTranslators = null,
        ): Onboarding\OnboardingInterface {

            $machineTranslator = null;

            if ($machineTranslators && $machineTranslators->has('translations')) {
                $machineTranslator = $machineTranslators->get('translations');
            }

            return new Onboarding\Onboarding(
                translationRepository: $translationRepository,
                machineTranslator: $machineTranslator,
                autoTranslateInChunksOf: 20,
            );
        },
        
        // Default repository: uses the application's default storage database.
        // Works out-of-the-box for single-app setups.
        // For multi-app setups, override this to use a shared storage connection.
        // See: https://github.com/tobento-ch/app-translation-web#manage-translations-across-multiple-apps
        TranslationRepositoryInterface::class =>
        static function(DatabasesInterface $databases, TranslationEntityFactory $entityFactory): TranslationRepositoryInterface {
            return new TranslationStorageRepository(
                storage: $databases->default('storage')->storage()->new(),
                table: 'translations',
                translationEntityFactory: $entityFactory,
                messageLocale: 'en',
            );
        },
    ],
    
];