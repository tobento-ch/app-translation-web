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

namespace Tobento\App\Translation\Web\Test\Feature;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Strategy;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;

class TranslationWebBootTest extends \Tobento\App\Testing\TestCase
{
    public function createApp(): AppInterface
    {
        $app = $this->createTmpApp(rootDir: __DIR__.'/../..');
        
        $app->boot(\Tobento\App\Translation\Web\Boot\TranslationWeb::class);
        return $app;
    }

    public function testInterfacesAreAvailable()
    {
        $app = $this->bootingApp();

        $this->assertInstanceOf(CollectorsInterface::class, $app->get(CollectorsInterface::class));
        $this->assertInstanceOf(OnboardingInterface::class, $app->get(OnboardingInterface::class));
        $this->assertInstanceOf(TranslationsPublishInterface::class, $app->get(TranslationsPublishInterface::class));
        $this->assertInstanceOf(TranslationRepositoryInterface::class, $app->get(TranslationRepositoryInterface::class));
    }
    
    public function testDefaultStragey()
    {
        $app = $this->bootingApp();
        
        $this->assertInstanceOf(Strategy\FileResources::class, $app->get(TranslationsPublishInterface::class));
    }
}