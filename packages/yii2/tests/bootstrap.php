<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/**
 * yiisoft/yii2's composer.json only autoloads the `yii\...` namespace via
 * PSR-4 — the global `Yii` class alias in Yii.php is not declared as a
 * Composer "files" autoload target, so every Yii2 app/test bootstrap must
 * require it explicitly.
 */
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';
