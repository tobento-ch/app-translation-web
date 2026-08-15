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

use PHPUnit\Framework\TestCase;
use Tobento\App\Translation\Web\SavedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslationStatus;
use Tobento\App\Translation\Web\TranslationEntityFactory;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\Translation\Web\TranslationStorageRepository;
use Tobento\Service\Storage\ItemsInterface;

class TranslationStorageRepositoryTest extends TestCase
{
    protected function createRepo(): TranslationStorageRepository
    {
        return Factory::createTranslationRepository();
    }

    public function testImplementsInterface()
    {
        $this->assertInstanceOf(
            TranslationRepositoryInterface::class,
            $this->createRepo(),
        );
    }

    public function testMessageLocale()
    {
        $repo = $this->createRepo();

        $this->assertSame('en', $repo->messageLocale());
    }

    public function testWithMessageLocaleIsImmutable()
    {
        $repo = $this->createRepo();

        $new = $repo->withMessageLocale('de');

        $this->assertNotSame($repo, $new);
        $this->assertSame('en', $repo->messageLocale());
        $this->assertSame('de', $new->messageLocale());
    }

    public function testSaveTranslationCreatesNewEntry()
    {
        $repo = $this->createRepo();

        $saved = $repo->saveTranslation(
            appId: 'app',
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourceFilename: null,
            resourcePriority: 0,
            message: 'hello',
            translation: 'Hello World',
            translatedBy: 'system'
        );

        $this->assertSame(SavedTranslationStatus::CREATED, $saved->status());
        $this->assertSame('hello', $saved->entity()->message());
        $this->assertSame('Hello World', $saved->entity()->translation());
    }

    public function testSaveTranslationUpdatesWhenSameTranslation()
    {
        $repo = $this->createRepo();

        // First create
        $repo->saveTranslation(
            appId: 'app',
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourceFilename: null,
            resourcePriority: 0,
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system'
        );

        // Update with same translation → should update
        $saved = $repo->saveTranslation(
            appId: 'app',
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourceFilename: null,
            resourcePriority: 0,
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system'
        );

        $this->assertSame(SavedTranslationStatus::UPDATED, $saved->status());
    }

    public function testSaveTranslationSkipsWhenHumanOverrideDetected()
    {
        $repo = $this->createRepo();

        // Create with translation "Hello"
        $repo->saveTranslation(
            appId: 'app',
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourceFilename: null,
            resourcePriority: 0,
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system'
        );

        // Human override: change translation manually
        $repo->updateById(1, [
            'translation' => 'Hello HUMAN',
            'is_translation_missing' => false,
        ]);

        // Now import tries to set "Hello" again → must SKIP
        $saved = $repo->saveTranslation(
            appId: 'app',
            resourceName: '*',
            resourceLocale: 'en',
            resourceGroup: '',
            resourceFilename: null,
            resourcePriority: 0,
            message: 'hello',
            translation: 'Hello',
            translatedBy: 'system'
        );

        $this->assertSame(SavedTranslationStatus::SKIPPED, $saved->status());
        $this->assertSame(
            'Existing translation differs - human override detected',
            $saved->skippedReason()
        );
    }

    public function testPublishTranslationsUpdatesStatus()
    {
        $repo = $this->createRepo();

        // Create two entries
        $repo->saveTranslation('app', '*', 'en', '', null, 0, 'hello', 'Hello', 'system');
        $repo->saveTranslation('app', '*', 'en', '', null, 0, 'bye', 'Bye', 'system');

        $result = $repo->publishTranslations('app');

        $this->assertInstanceOf(SavedTranslationsInterface::class, $result);
        $this->assertCount(2, $result->all());

        foreach ($repo->findAll() as $entity) {
            $this->assertSame('published', $entity->status());
        }
    }

    public function testPublishTranslationsFiltersByLocaleAndResource()
    {
        $repo = $this->createRepo();

        // en entry
        $repo->saveTranslation('app', 'shop', 'en', '', null, 0, 'hello', 'Hello', 'system');

        // de entry
        $repo->saveTranslation('app', 'shop', 'de', '', null, 0, 'hello', 'Hallo', 'system');

        $result = $repo->publishTranslations('app', resourceName: 'shop', resourceLocale: 'de');

        $this->assertCount(1, $result->all());
        $this->assertSame('de', $result->all()[0]->entity()->resourceLocale());
    }

    public function testFindAllByFiltersCorrectly()
    {
        $repo = $this->createRepo();

        $repo->saveTranslation('app1', '*', 'en', '', null, 0, 'hello', 'Hello', 'system');
        $repo->saveTranslation('app2', '*', 'de', '', null, 0, 'hello', 'Hallo', 'system');

        $items = $repo->findAllBy(appId: 'app2', locale: 'de');

        $this->assertInstanceOf(ItemsInterface::class, $items);
        $this->assertCount(1, $items);
        $this->assertSame('de', $items->first()->resourceLocale());
    }

    public function testFindAllPublished()
    {
        $repo = $this->createRepo();

        // Create two entries
        $repo->saveTranslation('app', '*', 'en', '', null, 0, 'hello', 'Hello', 'system');
        $repo->saveTranslation('app', '*', 'en', '', null, 0, 'bye', 'Bye', 'system');

        // Publish only one
        $repo->publishTranslations('app', resourceName: '*', resourceLocale: 'en');

        $published = $repo->findAllPublished('app', 'en');

        $this->assertCount(2, $published);
        foreach ($published as $entity) {
            $this->assertSame('published', $entity->status());
        }
    }

    public function testFindAllModified()
    {
        $repo = $this->createRepo();

        // Create entry
        $repo->saveTranslation('app', '*', 'en', '', null, 0, 'hello', 'Hello', 'system');

        // Mark modified
        $repo->updateById(1, ['is_modified' => true]);

        $modified = $repo->findAllModified(appId: 'app', status: null, locale: 'en');

        $this->assertCount(1, $modified);
        $this->assertTrue($modified->first()->isModified());
    }

    public function testResetModified()
    {
        $repo = $this->createRepo();

        // Create entry
        $repo->saveTranslation('app', '*', 'en', '', null, 0, 'hello', 'Hello', 'system');

        // Mark modified
        $repo->updateById(1, ['is_modified' => true]);

        $items = $repo->findAllModified();

        $reset = $repo->resetModified($items);

        $this->assertInstanceOf(ItemsInterface::class, $reset);
        $this->assertFalse($reset->first()->isModified());
    }

    public function testDistinctValues()
    {
        $repo = $this->createRepo();

        $repo->saveTranslation('app', 'shop', 'en', '', null, 0, 'hello', 'Hello', 'system');
        $repo->saveTranslation('app', 'shop', 'de', '', null, 0, 'hello', 'Hallo', 'system');

        $values = $repo->distinctValues('resource_locale');

        $this->assertSame(['en' => 'en', 'de' => 'de'], $values);
    }

    public function testIsMissingLogicViaSaveTranslation()
    {
        $repo = $this->createRepo();

        // Missing because empty
        $saved = $repo->saveTranslation('app', '*', 'en', '', null, 0, 'hello', '', 'system');
        $this->assertTrue($saved->entity()->isTranslationMissing());

        // Not missing in message locale when translation == message
        $saved = $repo->saveTranslation('app', '*', 'en', '', null, 0, 'hello', 'hello', 'system');
        $this->assertFalse($saved->entity()->isTranslationMissing());

        // Missing in other locale when translation == message
        $saved = $repo->saveTranslation('app', '*', 'de', '', null, 0, 'hello', 'hello', 'system');
        $this->assertTrue($saved->entity()->isTranslationMissing());
    }
}