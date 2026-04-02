<?php

declare(strict_types=1);

/**
 * Test bootstrap — loads Yii2 autoloader for stub classes
 */

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load Yii2 (which registers its autoloader and defines Yii class)
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

// Load test stub classes
require __DIR__ . '/stubs/MockUserWithPrefix.php';
require __DIR__ . '/stubs/MockUserWithoutPrefix.php';
require __DIR__ . '/stubs/TestEntities.php';
require __DIR__ . '/stubs/StubWebApplication.php';
require __DIR__ . '/stubs/StubActiveRecord.php';
