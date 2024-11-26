<?php

return (new \PhpCsFixer\Config())
    ->setFinder(
        (new \PhpCsFixer\Finder())
            ->in(__DIR__ . "/lib")
            ->in(__DIR__ . "/test")
    );
