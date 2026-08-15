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
use Tobento\App\Translation\Web\Strategy\NullStrategy;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;

class NullStrategyTest extends \Tobento\App\Testing\TestCase
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
            new NullStrategy()
        );
    }

    public function testPublishDoesNothing()
    {
        $app = $this->bootingApp();

        $strategy = new NullStrategy();

        // Should not throw or modify anything
        $strategy->publish($app);

        $this->assertTrue(true);
    }

    public function testSchedulePublishDoesNothing()
    {
        $app = $this->bootingApp();

        $strategy = new NullStrategy();

        // Should not throw or modify anything
        $strategy->schedulePublish($app);

        $this->assertTrue(true);
    }
}