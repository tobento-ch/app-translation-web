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

namespace Tobento\App\Translation\Web\Collector;

use Tobento\App\AppInterface;

/**
 * Collects translations for a given application and returns the result.
 */
interface CollectorInterface
{
    /**
     * Returns the collector id.
     *
     * @return string
     */
    public function id(): string;
    
    /**
     * Returns the collector name.
     *
     * @return string
     */
    public function name(): string;

    /**
     * Collect all translations for the given app.
     *
     * @param AppInterface $app
     * @return CollectedTranslationsInterface
     */
    public function collect(AppInterface $app): CollectedTranslationsInterface;
}