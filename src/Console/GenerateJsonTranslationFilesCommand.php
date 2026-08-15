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

namespace Tobento\App\Translation\Web\Console;

use Tobento\App\AppInterface;
use Tobento\App\Translation\Web\Strategy\FileResources;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;
use Tobento\App\Translation\Web\TranslationEntityInterface;
use Tobento\App\Translation\Web\TranslationRepositoryInterface;
use Tobento\Apps\AppsInterface;
use Tobento\Service\Console\AbstractCommand;
use Tobento\Service\Console\InteractorInterface;
use Tobento\Service\Dir\DirInterface;
use Tobento\Service\FileCreator\FileCreator;
use Tobento\Service\FileCreator\FileCreatorException;
use Tobento\Service\Storage\ItemsInterface;

class GenerateJsonTranslationFilesCommand extends AbstractCommand
{
    /**
     * The signature of the console command.
     */
    public const SIGNATURE = '
        translations:generate-json | Generate translations in json format for the FileResources strategy.
        {--appId[] : Only generate files for the specified app IDs}
    ';

    /**
     * Handle the command.
     *
     * @param InteractorInterface $io
     * @param AppInterface $app
     * @return int The exit status code: 
     *     0 SUCCESS
     *     1 FAILURE If some error happened during the execution
     *     2 INVALID To indicate incorrect command usage e.g. invalid options
     */
    public function handle(
        InteractorInterface $io,
        AppInterface $app,
    ): int {
        // Single-app mode
        if (! $app->has(AppsInterface::class)) {
            return $this->generateFilesForApp($io, $app);
        }
        
        // Multi-app mode
        $apps = $app->get(AppsInterface::class);
        
        // Boot all apps first (required for sub-apps)
        foreach ($apps->all() as $appBoot) {
            $appBoot->app()->booting();
        }

        $inputAppIds = $io->option(name: 'appId');
        $appIds = !empty($inputAppIds) ? $inputAppIds : $apps->ids();
        
        // Now process each app
        $statusCodes = [];
        
        foreach ($appIds as $appId) {
            $application = $apps->get($appId)->app();
            $apps->bootingApp($application);

            $statusCodes[] = $this->generateFilesForApp($io, $application);
        }

        // Restore main app context
        $apps->bootingApp($app);
        
        // Return status code
        if (in_array(static::INVALID, $statusCodes, true)) {
            return static::INVALID;
        }
        
        if (in_array(static::FAILURE, $statusCodes, true)) {
            return static::FAILURE;
        }
        
        return static::SUCCESS;
    }

    /**
     * Generates all JSON translation files for the given app.
     *
     * @param InteractorInterface $io Console output helper.
     * @param AppInterface $app The application whose translations should be processed.
     * @return int Status code (0 = success, 1 = failure, 2 = invalid).
     */
    protected function generateFilesForApp(InteractorInterface $io, AppInterface $app): int
    {
        $io->comment(sprintf('App (%s): Starting to generate files.', $app->id()));
        
        // Check required services
        if (! $app->has(TranslationsPublishInterface::class)) {
            $io->info('Nothing generated: TranslationsPublishInterface not found.');
            return static::SUCCESS;
        }
        
        if (! $app->has(TranslationRepositoryInterface::class)) {
            $io->info('Nothing generated: TranslationRepositoryInterface not found.');
            return static::SUCCESS;
        }
        
        $strategy = $app->get(TranslationsPublishInterface::class);
        
        if (! $strategy instanceof FileResources) {
            $io->info('Nothing generated: FileResources strategy not active.');
            return static::SUCCESS;
        }
        
        $dirName = $strategy->dirName();
        
        if (! $app->dirs()->has($dirName)) {
            $io->info(sprintf('Nothing generated: App dir "%s" not found.', $dirName));
            return static::SUCCESS;
        }
        
        $dir = $app->dirs()->getDir($dirName);
        $translationRepository = $app->get(TranslationRepositoryInterface::class);
        
        // Loop until no modified translations remain
        $maxLoops = 10;
        $loops = 0;
        
        do {
            $modified = $translationRepository->findAllModified(appId: $app->id());
            
            if ($modified->count() === 0) {
                break;
            }

            $this->generateFilesFromTranslations($io, $translationRepository, $modified, $dir);
            
            $loops++;

            if ($loops >= $maxLoops) {
                $io->warning(sprintf(
                    'Publish loop reached max iterations (%d). Stopping to avoid infinite loop.',
                    $maxLoops
                ));
                break;
            }
        } while (true);
        
        return static::SUCCESS;
    }
    
    /**
     * Generates JSON translation files for the given modified translations,
     * grouped by their target filename.
     *
     * @param InteractorInterface $io Console output helper.
     * @param TranslationRepositoryInterface $translationRepository Repository used to reset modified flags.
     * @param ItemsInterface $modifiedTranslations Collection of modified translation records.
     * @param DirInterface $dir Target directory for generated files.
     * @return void
     */
    protected function generateFilesFromTranslations(
        InteractorInterface $io,
        TranslationRepositoryInterface $translationRepository,
        ItemsInterface $modifiedTranslations,
        DirInterface $dir,
    ): void {
        $groups = [];

        foreach ($modifiedTranslations as $translation) {
            $file = $this->determineFileForTranslation($translation);
            $locale = $translation->resourceLocale();
            
            if ($locale === '') {
                continue;
            }

            $groups[$locale][$file][] = $translation;
        }
        
        foreach ($groups as $locale => $files) {
            foreach ($files as $file => $translations) {
                try {
                    $this->resetModified($translationRepository, $translations);
                    $this->writeFile($dir, $locale, $file, $translations);
                } catch (FileCreatorException $e) {
                    $io->error(sprintf(
                        'Could not write translation file "%s" for locale "%s": %s',
                        $file,
                        $locale,
                        $e->getMessage()
                    ));
                }
            }
        }
    }
    
    /**
     * Determines the file for the gien translation.
     *
     * @param TranslationEntityInterface $translation
     * @return string
     * @throws FileCreatorException
     */
    protected function determineFileForTranslation(TranslationEntityInterface $translation): string
    {
        $resource = $translation->resourceName();
        $locale = $translation->resourceLocale();
        $filename = (string)$translation->resourceFilename();
        
        // If file-based resource, keep original filename
        if ($filename !== '') {
            return $filename;
        }
        
        // Default resource (*)
        if ($resource === '*') {
            return sprintf('%s.generated.json', $locale);
        }
        
        // Named resource (shop, routes, etc.)
        return sprintf('%s-%s.generated.json', $locale, $resource);     
    }
    
    /**
     * Write file with translations.
     *
     * @param DirInterface $dir
     * @param string $locale
     * @param string $file
     * @param array<TranslationEntityInterface> $translations
     * @return void
     * @throws FileCreatorException
     */
    protected function writeFile(DirInterface $dir, string $locale, string $file, array $translations): void
    {
        $data = [];
        
        foreach ($translations as $t) {
            $key = $t->message();
            $value = $t->translation();
            
            if ($key === '' || $value === '') {
                continue;
            }
            
            // Skip non-published translation
            if ($t->status() !== 'published') {
                continue;
            }
            
            $data[$key] = $value;
        }
        
        $path = $dir->dir() . $locale . '/' . $file;

        // If no published translations remain, delete the file
        if (empty($data)) {
            if (is_file($path)) {
                unlink($path);
            }
            return;
        }
        
        // Sort keys alphabetically
        ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
        
        // Encode JSON
        try {
            $json = json_encode(
                $data,
                JSON_THROW_ON_ERROR
                | JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );
        } catch (\JsonException $e) {
            throw new FileCreatorException(
                message: 'Failed to encode translations to JSON: '.$e->getMessage(),
                previous: $e,
            );
        }
        
        // Write file
        new FileCreator()
            ->content($json)
            ->create(
                file: $path,
                handling: FileCreator::CONTENT_NEW,
                modeFile: 0644,
                modeDir: 0755,
            );
    }
    
    /**
     * Resets modified translations.
     *
     * @param TranslationRepositoryInterface $translationRepository
     * @param array<TranslationEntityInterface> $translations
     * @return void
     */
    protected function resetModified(
        TranslationRepositoryInterface $translationRepository,
        array $translations,
    ): void {
        $translationRepository->resetModified(translations: $translations);
    }
}