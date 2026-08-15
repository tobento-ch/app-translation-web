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

namespace Tobento\App\Translation\Web\Test\Collector;

use PHPUnit\Framework\TestCase;
use Tobento\App\Translation\Web\Collector\CollectedTranslations;
use Tobento\App\Translation\Web\Collector\CollectedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslations;

class CollectedTranslationsTest extends TestCase
{
    public function testImplementsInterface()
    {
        $collected = new CollectedTranslations(
            saved: new SavedTranslations(),
        );
        
        $this->assertInstanceof(CollectedTranslationsInterface::class, $collected);
    }
    
    public function testSavedMethod()
    {
        $saved = new SavedTranslations();
        $collected = new CollectedTranslations(saved: $saved);

        $this->assertSame($saved, $collected->saved());
    }
}