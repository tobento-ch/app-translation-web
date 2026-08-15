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
 
namespace Tobento\App\Translation\Web\Test\Addon;

use Tobento\Service\MachineTranslator\MachineTranslatorInterface;

trait MachineTranslatorAddon
{
    /**
     * Enables machine translation for tests with a configurable fake translator.
     *
     * @param null|callable $translatorFactory Returns MachineTranslatorInterface
     * @param bool $withAcl Whether ACL is enabled for machine translation
     */
    protected function withMachineTranslator(
        null|callable $translatorFactory = null,
        bool $withAcl = false,
    ): void {
        $translatorFactory ??= function(): MachineTranslatorInterface {
            return new class implements MachineTranslatorInterface
            {
                public function name(): string
                {
                    return 'translations';
                }
                public function translate(string $text, string $locale): string {
                    return 'Test: '.$text;
                }
                public function translateMany(array $texts, string $locale): array
                {
                    return array_map(
                        fn (string $text): string => 'Test: '.$text,
                        $texts
                    );
                }
            };
        };

        $this->fakeConfig()
            ->with('machine-translator.features', [
                new \Tobento\App\MachineTranslator\Feature\Translate(withAcl: $withAcl),
            ])
            ->with('machine-translator.translators', [
                'translations' => $translatorFactory,
            ]);
    }
}