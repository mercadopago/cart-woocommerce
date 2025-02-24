<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPhpSets()
    ->withPaths(['src'])
    ->withDeadCodeLevel(1)
    ->withCodeQualityLevel(1)
    ->withCodingStyleLevel(1);
