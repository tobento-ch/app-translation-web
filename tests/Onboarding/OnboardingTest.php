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
use Tobento\App\Translation\Web\Onboarding\Onboarding;
use Tobento\App\Translation\Web\Onboarding\OnboardingInterface;
use Tobento\App\Translation\Web\Onboarding\CreatedTranslationsInterface;
use Tobento\App\Translation\Web\Onboarding\AutoTranslatedTranslationsInterface;
use Tobento\App\Translation\Web\Onboarding\PublishedTranslationsInterface;
use Tobento\App\Translation\Web\SavedTranslationStatus;
use Tobento\App\Translation\Web\Test\Factory;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Service\MachineTranslator\Exception\TranslateException;
use Tobento\Service\MachineTranslator\MachineTranslatorInterface;
use Tobento\Service\MachineTranslator\NullMachineTranslator;
use Tobento\App\Translation\Web\SavedTranslations;

class OnboardingTest extends TestCase
{
    protected function createOnboarding(
        null|TranslationRepositoryInterface $translationRepository = null,
        bool $withMachineTranslator = false,
    ): OnboardingInterface {

        $machineTranslator = null;

        if ($withMachineTranslator) {
            $machineTranslator = new class implements MachineTranslatorInterface {
                public function name(): string { return 'translations'; }
                public function translate(string $text, string $locale): string {
                    return 'Test: '.$text;
                }
                public function translateMany(array $texts, string $locale): array {
                    return array_map(fn ($t) => 'Test: '.$t, $texts);
                }
            };
        }

        return new Onboarding(
            translationRepository: $translationRepository ?? Factory::createTranslationRepository(),
            machineTranslator: $machineTranslator,
            autoTranslateInChunksOf: 5,
        );
    }

    public function testImplementsInterface()
    {
        $onboarding = $this->createOnboarding();
        $this->assertInstanceOf(OnboardingInterface::class, $onboarding);
    }

    public function testMachineTranslatorMethod()
    {
        $onboarding = $this->createOnboarding(withMachineTranslator: true);
        $this->assertInstanceOf(MachineTranslatorInterface::class, $onboarding->machineTranslator());
    }

    public function testSupportsAutoTranslateReturnsFalseWithoutTranslator()
    {
        $onboarding = $this->createOnboarding(withMachineTranslator: false);
        $this->assertFalse($onboarding->supportsAutoTranslate());
    }

    public function testSupportsAutoTranslateReturnsFalseWithNullMachineTranslator()
    {
        $onboarding = new Onboarding(
            translationRepository: Factory::createTranslationRepository(),
            machineTranslator: new NullMachineTranslator(),
        );

        $this->assertFalse($onboarding->supportsAutoTranslate());
    }

    public function testSupportsAutoTranslateReturnsTrueWithTranslator()
    {
        $onboarding = $this->createOnboarding(withMachineTranslator: true);
        $this->assertTrue($onboarding->supportsAutoTranslate());
    }

    public function testCreateTranslationEntriesReturnsCreatedTranslations()
    {
        $repo = Factory::createTranslationRepository([
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'en',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Hello',
                'translated_by' => null,
            ]
        ]);

        $onboarding = $this->createOnboarding(translationRepository: $repo);

        $created = $onboarding->createTranslationEntries('fr', 'root');

        $this->assertInstanceOf(CreatedTranslationsInterface::class, $created);
        $this->assertSame('fr', $created->locale());
        $this->assertSame('root', $created->appId());
        $this->assertInstanceOf(SavedTranslations::class, $created->saved());
        $this->assertCount(1, $created->saved());
        
        $entity = $created->saved()->all()[0]->entity();
        $this->assertSame('', $entity->translation());
        $this->assertTrue($entity->isTranslationMissing());
    }
    
    public function testCreateTranslationEntriesDoesNotCreateDuplicates()
    {
        $repo = Factory::createTranslationRepository([
            // EN source
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'en',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Hello',
                'translated_by' => null,
            ],
            // FR already exists → onboarding must NOT create another
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Bonjour',
                'translated_by' => 'human',
                'is_translation_missing' => false,
            ],
        ]);

        $onboarding = $this->createOnboarding(translationRepository: $repo);

        $created = $onboarding->createTranslationEntries('fr', 'root');

        // Should not create anything new
        $this->assertCount(1, $created->saved());

        $saved = $created->saved()->all()[0];

        // It must be SKIPPED, not CREATED
        $this->assertSame(SavedTranslationStatus::SKIPPED, $saved->status());
        $this->assertSame('Existing translation differs - human override detected', $saved->skippedReason());
    }
    
    public function testCreateTranslationEntriesRespectsAppIdFiltering()
    {
        $repo = Factory::createTranslationRepository([
            // EN entry for app "root"
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'en',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Hello',
                'translated_by' => null,
            ],
            // EN entry for a different app "other"
            [
                'app_id' => 'other',
                'resource_name' => 'messages',
                'resource_locale' => 'en',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Hello from other app',
                'translated_by' => null,
            ],
        ]);

        $onboarding = $this->createOnboarding(translationRepository: $repo);

        $created = $onboarding->createTranslationEntries('fr', 'root');

        $this->assertCount(1, $created->saved());

        $entity = $created->saved()->all()[0]->entity();

        $this->assertSame('root', $entity->appId());
        $this->assertSame('fr', $entity->resourceLocale());
        $this->assertSame('hello', $entity->message());
        $this->assertSame('', $entity->translation());
        $this->assertTrue($entity->isTranslationMissing());
    }

    public function testAutoTranslateEntriesReturnsEmptyWhenNotSupported()
    {
        $onboarding = $this->createOnboarding(withMachineTranslator: false);

        $auto = $onboarding->autoTranslateEntries('fr', 'root');

        $this->assertInstanceOf(AutoTranslatedTranslationsInterface::class, $auto);
        $this->assertCount(0, $auto->saved());
    }

    public function testAutoTranslateEntriesTranslatesWhenSupported()
    {
        $repo = Factory::createTranslationRepository([
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'en',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Hello',
                'origin_translation' => '',
                'translated_by' => 'system',
                'origin_translated_by' => null,
                'is_translation_missing' => true,
                'status' => 'imported',
                'user_id' => null,
            ]
        ]);

        $onboarding = $this->createOnboarding(
            translationRepository: $repo,
            withMachineTranslator: true,
        );
        
        $created = $onboarding->createTranslationEntries('fr', 'root');

        $auto = $onboarding->autoTranslateEntries('fr', 'root');

        $this->assertInstanceOf(AutoTranslatedTranslationsInterface::class, $auto);
        $this->assertCount(1, $auto->saved());

        $saved = $auto->saved()->all()[0];

        // Assert translation was actually changed
        $this->assertSame('Test: hello', $saved->entity()->translation());

        // Assert translated_by was set correctly
        $this->assertSame('translations', $saved->entity()->translatedBy());
    }
    
    public function testAutoTranslateSkipsNonMissingTranslations()
    {
        $repo = Factory::createTranslationRepository([
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Bonjour', // not missing
                'translated_by' => 'human',
                'is_translation_missing' => false,
            ]
        ]);

        $onboarding = $this->createOnboarding(
            translationRepository: $repo,
            withMachineTranslator: true,
        );

        $auto = $onboarding->autoTranslateEntriesBy(
            where: [],
            orderBy: [],
            limit: [],
        );

        $this->assertCount(1, $auto->saved());
        $saved = $auto->saved()->all()[0];

        $this->assertSame(SavedTranslationStatus::SKIPPED, $saved->status());
        $this->assertSame('Translation already exists', $saved->skippedReason());
    }
    
    public function testAutoTranslateSkipsWhenMachineTranslationFails()
    {
        $repo = Factory::createTranslationRepository([
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => '',
                'translated_by' => null,
                'is_translation_missing' => true,
            ]
        ]);

        // Create a failing translator inline
        $machineTranslator = new class implements MachineTranslatorInterface {
            public function name(): string { return 'translations'; }
            public function translate(string $text, string $locale): string {
                throw new TranslateException('Simulated failure');
            }
            public function translateMany(array $texts, string $locale): array {
                throw new TranslateException('Simulated failure');
            }
        };

        $onboarding = new Onboarding(
            translationRepository: $repo,
            machineTranslator: $machineTranslator,
            autoTranslateInChunksOf: 5,
        );

        $auto = $onboarding->autoTranslateEntriesBy(
            where: [],
            orderBy: [],
            limit: [],
        );

        $this->assertCount(1, $auto->saved());
        $saved = $auto->saved()->all()[0];

        $this->assertSame(SavedTranslationStatus::SKIPPED, $saved->status());
        $this->assertStringStartsWith('Machine translation failed', $saved->skippedReason());
    }
    
    public function testAutoTranslateSkipsWhenBatchResultMissingText()
    {
        $repo = Factory::createTranslationRepository([
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => '',
                'translated_by' => null,
                'is_translation_missing' => true,
            ]
        ]);

        // Translator that returns NULL for each translated text
        $machineTranslator = new class implements MachineTranslatorInterface {
            public function name(): string { return 'translations'; }
            public function translate(string $text, string $locale): string {
                return null; // not used, but safe
            }
            public function translateMany(array $texts, string $locale): array {
                return array_fill(0, count($texts), null); // simulate missing batch results
            }
        };

        $onboarding = new Onboarding(
            translationRepository: $repo,
            machineTranslator: $machineTranslator,
            autoTranslateInChunksOf: 5,
        );

        $auto = $onboarding->autoTranslateEntriesBy(
            where: [],
            orderBy: [],
            limit: [],
        );

        $this->assertCount(1, $auto->saved());
        $saved = $auto->saved()->all()[0];

        $this->assertSame(SavedTranslationStatus::SKIPPED, $saved->status());
        $this->assertSame('Missing translated text in batch result', $saved->skippedReason());
    }
    
    public function testAutoTranslateEntriesRespectsAppIdFiltering()
    {
        $repo = Factory::createTranslationRepository([
            // EN source for app "root"
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'en',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Hello',
                'translated_by' => null,
                'is_translation_missing' => false,
            ],
            // FR missing entry for app "root"
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => '',
                'translated_by' => null,
                'is_translation_missing' => true,
            ],
            // FR entry for other app → must be ignored
            [
                'app_id' => 'other',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => '',
                'translated_by' => null,
                'is_translation_missing' => true,
            ],
        ]);

        $onboarding = $this->createOnboarding(
            translationRepository: $repo,
            withMachineTranslator: true,
        );

        $auto = $onboarding->autoTranslateEntriesBy(
            where: ['app_id' => 'root'],
            orderBy: [],
            limit: [],
        );

        // Expect 2 results: one SKIPPED (EN), one UPDATED (FR)
        $this->assertCount(2, $auto->saved());

        $saved = $auto->saved()->all();

        $this->assertSame(SavedTranslationStatus::SKIPPED, $saved[0]->status());
        $this->assertSame('Translation already exists', $saved[0]->skippedReason());

        $this->assertSame(SavedTranslationStatus::UPDATED, $saved[1]->status());
        $this->assertSame('Test: hello', $saved[1]->entity()->translation());
    }

    public function testPublishTranslationsReturnsPublishedTranslations()
    {
        // Seed one translation that can be published
        $repo = Factory::createTranslationRepository([
            [
                'app_id' => 'root',
                'resource_name' => 'messages',
                'resource_locale' => 'fr',
                'resource_group' => 'default',
                'resource_priority' => 0,
                'resource_filename' => 'messages.php',
                'message' => 'hello',
                'translation' => 'Bonjour',
                'translated_by' => 'human',
                'is_translation_missing' => false,
            ],
        ]);

        $onboarding = $this->createOnboarding(translationRepository: $repo);

        $published = $onboarding->publishTranslations('fr', 'root');

        $this->assertInstanceOf(PublishedTranslationsInterface::class, $published);
        $this->assertSame('fr', $published->locale());
        $this->assertSame('root', $published->appId());
        $this->assertInstanceOf(SavedTranslations::class, $published->saved());

        $savedItems = $published->saved()->all();
        $this->assertCount(1, $savedItems);

        $entity = $savedItems[0]->entity();

        // This is the important part
        $this->assertSame('published', $entity->status());
    }
}