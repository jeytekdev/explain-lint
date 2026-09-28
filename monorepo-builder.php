<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\Config\MBConfig;

return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([
        __DIR__ . '/packages',
    ]);

    // Keeps the shared "jeytekdev/explain-lint" version constraint in sync
    // across packages/laravel and packages/doctrine composer.json files.
    $mbConfig->dataToAppend([
        'require' => [
            'jeytekdev/explain-lint' => 'self.version',
        ],
    ]);
};
