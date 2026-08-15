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
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\Service\Console\AbstractCommand;
use Tobento\Service\Console\InteractorInterface;

class CollectTranslationsCommand extends AbstractCommand
{
    /**
     * The signature of the console command.
     */
    public const SIGNATURE = '
        translations:collect | Collect translations from all collectors or only selected ones.
        {--collectorId[] : Run only specific collectors by ID.}
    ';

    /**
     * Handle the command.
     *
     * @param InteractorInterface $io
     * @param AppInterface $app
     * @param CollectorsInterface $collectors
     * @return int The exit status code: 
     *     0 SUCCESS
     *     1 FAILURE If some error happened during the execution
     *     2 INVALID To indicate incorrect command usage e.g. invalid options
     */
    public function handle(
        InteractorInterface $io,
        AppInterface $app,
        CollectorsInterface $collectors,
    ): int {
        // Filter collectors if IDs were provided:
        $collectorIds = $io->option(name: 'collectorId');
        
        if (!empty($collectorIds)) {
            $collectors = $collectors->only(...$collectorIds);
        }
        
        // Collecting translations:
        $results = [];
        $rows = [];
        
        foreach($collectors as $collector) {
            $collectedTranslations = $collector->collect($app);
            
            if ($io->isVerbose('v')) {
                $results[$collector->id()] = $collectedTranslations;
            }
            
            $savedTranslations = $collectedTranslations->saved();
            $createdTranslations = $savedTranslations->created();
            $updatedTranslations = $savedTranslations->updated();
            $skippedTranslations = $savedTranslations->skipped();
            
            $rows[] = [
                $collector->id(),
                $collector->name(),
                $createdTranslations->count(),
                $updatedTranslations->count(),
                $skippedTranslations->count(),
            ];
        }

        // Summary table:
        $io->table(
            headers: ['Collector ID', 'Collector Name', 'created', 'updated', 'skipped'],
            rows: $rows,
        );
        
        // Verbose: show all collected translations data by collector
        if ($io->isVerbose('v')) {
            foreach($results as $collectorId => $collectedTranslations) {
                $savedTranslations = $collectedTranslations->saved();

                $io->info(sprintf('Collector: %s', $collectorId));
                
                foreach($savedTranslations as $savedTranslation) {
                    $io->info(json_encode(
                        $savedTranslation->entity()->toArray(),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                    ));
                }
            }
        }
        
        return static::SUCCESS;
    }
}