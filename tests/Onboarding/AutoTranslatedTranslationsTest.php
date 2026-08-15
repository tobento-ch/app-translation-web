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
use Tobento\App\Translation\Web\Onboarding\AutoTranslatedTranslations;
use Tobento\App\Translation\Web\Onboarding\AutoTranslatedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslations;
use Tobento\App\Translation\Web\SavedTranslationsInterface;

class AutoTranslatedTranslationsTest extends TestCase
{
    public function testImplementsInterface()
    {
        $saved = new SavedTranslations();

        $auto = new AutoTranslatedTranslations(
            locale: 'fr',
            appId: 'app123',
            saved: $saved,
        );

        $this->assertInstanceOf(AutoTranslatedTranslationsInterface::class, $auto);
    }

    public function testLocaleMethod()
    {
        $auto = new AutoTranslatedTranslations(
            locale: 'de',
            appId: 'root',
            saved: new SavedTranslations(),
        );

        $this->assertSame('de', $auto->locale());
    }

    public function testAppIdMethod()
    {
        $auto = new AutoTranslatedTranslations(
            locale: 'en',
            appId: 'my-app',
            saved: new SavedTranslations(),
        );

        $this->assertSame('my-app', $auto->appId());
    }

    public function testSavedMethod()
    {
        $saved = new SavedTranslations();

        $auto = new AutoTranslatedTranslations(
            locale: 'en',
            appId: 'root',
            saved: $saved,
        );

        $this->assertSame($saved, $auto->saved());
    }
}