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

use Tobento\App\Translation\Web\TranslationEntityFactory;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\App\Translation\Web\TranslationStorageRepository;
use Tobento\Service\Storage\InMemoryStorage;

class Factory
{
    public static function createTranslationRepository(array $translations = []): TranslationRepositoryInterface
    {
        $repository = new TranslationStorageRepository(
            storage: new InMemoryStorage([]),
            table: 'translations',
            translationEntityFactory: new TranslationEntityFactory(),
            messageLocale: 'en',
        );
        
        foreach($translations as $translation) {
            $repository->create($translation);
        }
        
        return $repository;
    }
}