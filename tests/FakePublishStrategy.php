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

namespace Tobento\App\Translation\Web\Test;

use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\App\AppInterface;

class FakePublishStrategy implements TranslationsPublishInterface
{
    public array $published = [];
    
    public array $scheduled = [];

    public function publish(AppInterface $app): void
    {
        $this->published[] = $app->id();
    }
    
    public function schedulePublish(AppInterface $app): void
    {
        $this->scheduled[] = $app->id();
    }
}