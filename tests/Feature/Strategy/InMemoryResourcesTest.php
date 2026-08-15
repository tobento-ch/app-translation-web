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

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Strategy\InMemoryResources;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\Translation\TranslatorInterface;
use Tobento\Service\Translation\Translator;
use Tobento\Service\Translation\Resources;
use Tobento\Service\Translation\Resource;
use Tobento\Service\Translation\Modifiers;
use Tobento\Service\Translation\MissingTranslationHandler;

class InMemoryResourcesTest extends \Tobento\App\Testing\TestCase
{
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../../..');
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }

    public function testImplementsInterface()
    {
        $this->assertInstanceOf(
            TranslationsPublishInterface::class,
            new InMemoryResources()
        );
    }
    
    public function testPublishAddsRepositoryResourcesToTranslator()
    {
        $this->onCreateApp(function(AppInterface $app) {

            $app->on(TranslatorInterface::class, function() {
                return new Translator(
                    resources: new Resources(
                        new Resource('*', 'en', ['hello' => 'Hello']),
                    ),
                    modifiers: new Modifiers(),
                    missingTranslationHandler: new MissingTranslationHandler(),
                );
            });

            $app->on(
                TranslationsPublishInterface::class,
                new InMemoryResources()
            );
        });

        $app = $this->bootingApp();
        $repo = $app->get(TranslationRepositoryInterface::class);

        $t = $repo->saveTranslation(
            appId: $app->id(),
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourcePriority: 0,
            resourceFilename: '',
            message: 'hello',
            translation: 'Hello World',
            translatedBy: 'system',
        );

        $repo->updateById($t->entity()->id(), [
            'is_modified' => false,
            'status' => 'published',
        ]);
        
        $app->get(TranslationsPublishInterface::class)->publish($app);
        
        $translator = $app->get(TranslatorInterface::class);

        $this->assertSame(
            'Hello World',
            $translator->trans('hello', locale: 'en')
        );
    }

    public function testPublishDoesNothingIfTranslatorNotResourcesAware()
    {
        $this->onCreateApp(function(AppInterface $app) {
            $app->on(TranslatorInterface::class, function() {
                // Fake translator that is NOT ResourcesAware
                return new class implements TranslatorInterface {
                    public function trans(string $message, array $parameters = [], ?string $locale = null): string
                    {
                        return 'NOOP';
                    }
                };
            });
        });
        
        $app = $this->bootingApp();

        $strategy = new InMemoryResources();
        $strategy->publish($app);

        $resolved = $app->get(TranslatorInterface::class);

        // Should remain unchanged
        $this->assertSame('NOOP', $resolved->trans('hello', locale: 'en'));
    }

    public function testSchedulePublishDoesNothing()
    {
        $app = $this->bootingApp();

        $strategy = new InMemoryResources();

        // Should not throw, should not queue anything
        $strategy->schedulePublish($app);

        $this->assertTrue(true);
    }
}