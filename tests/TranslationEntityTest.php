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

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tobento\App\Translation\Web\TranslationEntity;
use Tobento\App\Translation\Web\TranslationEntityInterface;

class TranslationEntityTest extends TestCase
{
    public function testImplementsInterface()
    {
        $entity = new TranslationEntity([]);
        $this->assertInstanceOf(TranslationEntityInterface::class, $entity);
    }

    public function testAttributesAreReturned()
    {
        $data = ['id' => 5, 'message' => 'hello'];
        $entity = new TranslationEntity($data);

        $this->assertSame($data, $entity->attributes());
    }

    public function testAllGettersReturnCorrectValues()
    {
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-02 12:00:00');

        $entity = new TranslationEntity([
            'id' => 10,
            'app_id' => 'app123',
            'user_id' => 42,
            'status' => 'published',
            'resource_name' => 'messages',
            'resource_locale' => 'en',
            'resource_group' => 'general',
            'resource_priority' => 5,
            'resource_filename' => 'messages.php',
            'message' => 'hello',
            'translation' => 'Hello World',
            'is_translation_missing' => false,
            'translated_by' => 'system',
            'origin_translation' => 'Hello',
            'origin_translated_by' => 'vendor',
            'is_modified' => true,
            'notes' => 'Important note',
            'meta' => ['foo' => 'bar'],
            'created_at' => $createdAt,
            'date_updated' => $updatedAt,
        ]);

        $this->assertSame(10, $entity->id());
        $this->assertSame('app123', $entity->appId());
        $this->assertSame(42, $entity->userId());
        $this->assertSame('published', $entity->status());
        $this->assertSame('messages', $entity->resourceName());
        $this->assertSame('en', $entity->resourceLocale());
        $this->assertSame('general', $entity->resourceGroup());
        $this->assertSame(5, $entity->resourcePriority());
        $this->assertSame('messages.php', $entity->resourceFilename());
        $this->assertSame('hello', $entity->message());
        $this->assertSame('Hello World', $entity->translation());
        $this->assertFalse($entity->isTranslationMissing());
        $this->assertSame('system', $entity->translatedBy());
        $this->assertSame('Hello', $entity->originTranslation());
        $this->assertSame('vendor', $entity->originTranslatedBy());
        $this->assertTrue($entity->isModified());
        $this->assertSame('Important note', $entity->notes());
        $this->assertSame(['foo' => 'bar'], $entity->meta());
        $this->assertSame($createdAt, $entity->createdAt());
        $this->assertSame($updatedAt, $entity->updatedAt());
    }

    public function testDefaultValuesWhenMissing()
    {
        $entity = new TranslationEntity([]);

        $this->assertSame(0, $entity->id());
        $this->assertSame('', $entity->appId());
        $this->assertNull($entity->userId());
        $this->assertSame('', $entity->status());
        $this->assertSame('', $entity->resourceName());
        $this->assertSame('', $entity->resourceLocale());
        $this->assertSame('', $entity->resourceGroup());
        $this->assertSame(0, $entity->resourcePriority());
        $this->assertNull($entity->resourceFilename());
        $this->assertSame('', $entity->message());
        $this->assertSame('', $entity->translation());
        $this->assertFalse($entity->isTranslationMissing());
        $this->assertNull($entity->translatedBy());
        $this->assertNull($entity->originTranslation());
        $this->assertNull($entity->originTranslatedBy());
        $this->assertFalse($entity->isModified());
        $this->assertNull($entity->notes());
        $this->assertSame([], $entity->meta());
        $this->assertNull($entity->createdAt());
        $this->assertNull($entity->updatedAt());
    }

    public function testTypeCoercion()
    {
        $entity = new TranslationEntity([
            'id' => '5',
            'resource_priority' => '7',
            'is_translation_missing' => '1',
            'is_modified' => '0',
        ]);

        $this->assertSame(0, $entity->id()); // invalid type → 0
        $this->assertSame(7, $entity->resourcePriority());
        $this->assertTrue($entity->isTranslationMissing());
        $this->assertFalse($entity->isModified());
    }

    public function testToArrayReturnsAllNormalizedValues()
    {
        $createdAt = new DateTimeImmutable();
        $updatedAt = new DateTimeImmutable();

        $entity = new TranslationEntity([
            'id' => 1,
            'app_id' => 'app',
            'user_id' => 5,
            'status' => 'published',
            'resource_name' => 'messages',
            'resource_locale' => 'en',
            'resource_group' => 'general',
            'resource_priority' => 3,
            'resource_filename' => 'file.php',
            'message' => 'hello',
            'translation' => 'Hello',
            'is_translation_missing' => false,
            'translated_by' => 'system',
            'origin_translation' => 'Hello',
            'origin_translated_by' => 'vendor',
            'is_modified' => true,
            'notes' => 'note',
            'meta' => ['a' => 'b'],
            'created_at' => $createdAt,
            'date_updated' => $updatedAt,
        ]);

        $array = $entity->toArray();

        $this->assertSame(1, $array['id']);
        $this->assertSame('app', $array['app_id']);
        $this->assertSame(5, $array['user_id']);
        $this->assertSame('published', $array['status']);
        $this->assertSame('messages', $array['resource_name']);
        $this->assertSame('en', $array['resource_locale']);
        $this->assertSame('general', $array['resource_group']);
        $this->assertSame(3, $array['resource_priority']);
        $this->assertSame('file.php', $array['resource_filename']);
        $this->assertSame('hello', $array['message']);
        $this->assertSame('Hello', $array['translation']);
        $this->assertFalse($array['is_translation_missing']);
        $this->assertSame('system', $array['translated_by']);
        $this->assertSame('Hello', $array['origin_translation']);
        $this->assertSame('vendor', $array['origin_translated_by']);
        $this->assertTrue($array['is_modified']);
        $this->assertSame('note', $array['notes']);
        $this->assertSame(['a' => 'b'], $array['meta']);
        $this->assertSame($createdAt, $array['created_at']);
        $this->assertSame($updatedAt, $array['date_updated']);
    }

    public function testMagicGetAndIsset()
    {
        $entity = new TranslationEntity([
            'foo' => 'bar',
            'baz' => 123,
        ]);

        $this->assertSame('bar', $entity->foo);
        $this->assertSame(123, $entity->baz);

        $this->assertTrue(isset($entity->foo));
        $this->assertFalse(isset($entity->missing));
    }
}