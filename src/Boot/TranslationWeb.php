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
 
namespace Tobento\App\Translation\Web\Boot;

use Tobento\App\AppInterface;
use Tobento\App\Boot;
use Tobento\App\Boot\Config;
use Tobento\App\Migration\Boot\Migration;
use Tobento\App\Translation\Web\Collector\Collectors;
use Tobento\App\Translation\Web\Collector\CollectorsInterface;
use Tobento\App\Translation\Web\Strategy\TranslationsPublishInterface;

class TranslationWeb extends Boot
{
    public const INFO = [
        'boot' => [
            'installs and loads translation-web config',
            'implements translation-web interfaces',
            'publishes translations from implemented strategy',
            'boots features',
        ],
    ];

    public const BOOT = [
        Config::class,
        Migration::class,
        \Tobento\App\Database\Boot\Database::class,
    ];

    /**
     * Boot application services.
     *
     * @param Config $config
     * @param Migration $migration
     * @return void
     */
    public function boot(
        Config $config,
        Migration $migration,
    ): void {
        // Migration:
        $migration->install(\Tobento\App\Translation\Web\Migration\TranslationWeb::class);
        
        // Load the config:
        $config = $config->load('translation-web.php');
        
        // Interfaces:
        foreach($config['interfaces'] ?? [] as $interface => $implementation) {
            $this->app->set($interface, $implementation);
        }
        
        // publish translations as early as possible:
        $this->app->get(TranslationsPublishInterface::class)->publish($this->app);
        
        // Migrate log repositories:
        $migration->install(\Tobento\App\Translation\Web\Migration\TranslationRepositories::class);
        
        // Collectors
        $this->app->set(CollectorsInterface::class, static function () use ($config) {
            $collectors = $config['collectors'] ?? [];
            return new Collectors(...$collectors);
        });
        
        // Features:
        foreach($config['features'] ?? [] as $feature) {
            if (is_string($feature)) {
                $feature = $this->app->make($feature);
            }
            
            $this->app->boot($feature);
        }
    }
}