<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;


return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->paths([
		__DIR__ . '/src',
		__DIR__ . '/www',
	]);

	$rectorConfig->importNames();
	$rectorConfig->parallel();
	$rectorConfig->cacheDirectory(__DIR__ . '/temp/rector');

	// Define what rule sets will be applied
	$rectorConfig->import(LevelSetList::UP_TO_PHP_81);
	$rectorConfig->import(SetList::CODE_QUALITY);

	$rectorConfig->phpVersion(PhpVersion::PHP_81);
};
