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

namespace Tobento\App\Translation\Web\Test\Onboarding;

use PHPUnit\Framework\TestCase;
use Tobento\App\Translation\Web\Onboarding\CreatedTranslations;
use Tobento\App\Translation\Web\Onboarding\CreatedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslations;
use Tobento\App\Translation\Web\SavedTranslationsInterface;

class CreatedTranslationsTest extends TestCase
{
    public function testImplementsInterface()
    {
        $saved = new SavedTranslations();

        $created = new CreatedTranslations(
            locale: 'fr',
            appId: 'app123',
            saved: $saved,
        );

        $this->assertInstanceOf(CreatedTranslationsInterface::class, $created);
    }

    public function testLocaleMethod()
    {
        $created = new CreatedTranslations(
            locale: 'de',
            appId: 'root',
            saved: new SavedTranslations(),
        );

        $this->assertSame('de', $created->locale());
    }

    public function testAppIdMethod()
    {
        $created = new CreatedTranslations(
            locale: 'en',
            appId: 'my-app',
            saved: new SavedTranslations(),
        );

        $this->assertSame('my-app', $created->appId());
    }

    public function testSavedMethod()
    {
        $saved = new SavedTranslations();

        $created = new CreatedTranslations(
            locale: 'en',
            appId: 'root',
            saved: $saved,
        );

        $this->assertSame($saved, $created->saved());
    }
}