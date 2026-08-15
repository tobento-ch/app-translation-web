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

namespace Tobento\App\Translation\Web\Strategy;

use Tobento\App\AppInterface;

/**
 * Defines the strategy for publishing translations. Implementations may
 * publish immediately or schedule the publishing process asynchronously.
 */
interface TranslationsPublishInterface
{
    /**
     * Publishes all modified translations immediately using
     * the strategy's concrete publishing mechanism.
     *
     * @param AppInterface $app The application instance.
     * @return void
     */
    public function publish(AppInterface $app): void;

    /**
     * Schedules or triggers a publish operation, for example
     * by dispatching a queue job instead of publishing directly.
     *
     * @param AppInterface $app The application instance.
     * @return void
     */
    public function schedulePublish(AppInterface $app): void;
}