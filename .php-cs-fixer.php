<?php

return (new PhpCsFixer\Config())
    ->setRules(array_fill_keys([
        'nullable_type_declaration_for_default_null_value',
    ], true))
    ->setFinder(PhpCsFixer\Finder::create()->in(__DIR__));
