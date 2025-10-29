<?php

/**
 * Utility script to convert PHP 7.4 syntax to PHP 5.6 compatible syntax.
 *
 * - Removes typed properties
 * - Removes scalar/nullable parameter type hints
 * - Removes return type declarations
 *
 * Usage: php tools/php56-downgrade.php
 */

$baseDir = dirname(__DIR__);

$directories = array($baseDir . '/src',$baseDir . '/admin',$baseDir . '/public',$baseDir . '/tests',$baseDir . '/test',$baseDir // for root level php files like rm-pagbank.php
);

$processed = 0;

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->getExtension() !== 'php') {
            continue;
        }

        $filePath = $fileInfo->getPathname();
        $code = file_get_contents($filePath);
        $original = $code;

        // Remove typed properties (public $foo = 'bar';)
        $code = preg_replace_callback(
            '/(public|protected|private)\s+(static\s+)?(\??[A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\s+(&?\$[A-Za-z_][A-Za-z0-9_]*)(\s*(?:=[^;]*;|;))/m',
            function ($matches) {
                $visibility = $matches[1];
                $static = isset($matches[2]) ? $matches[2] : '';
                $variable = $matches[4];
                $tail = $matches[5];

                return rtrim($visibility . ' ' . $static) . ' ' . $variable . $tail;
            },$code
        );

        // Remove nullable parameter type hints ($var )
        $code = preg_replace('/(?<=\(|,)\s*\?[A-Za-z_\\\\][A-Za-z0-9_\\\\]*\s+(&?\$)/', ' $1',$code);

        // Remove scalar parameter type hints
        $code = preg_replace('/(?<=\(|,)\s*(?:string|int|float|bool)\s+(&?\$)/i', ' $1',$code);

        // Normalize spacing after removing type hints
        $code = preg_replace('/(\(|,)\s+(&?\$[A-Za-z_][A-Za-z0-9_]*)/', '$1$2',$code);

        // Remove return type declarations for definitions ending with body
        $code = preg_replace('/(function\s+[^\(]+\([^\)]*\)(?:\s+use\s*\([^\)]*\))?)\s*:\s*[^\{;]+\{/', '$1 {',$code);

        // Remove return type declarations for abstract/interface methods
        $code = preg_replace('/(function\s+[^\(]+\([^\)]*\)(?:\s+use\s*\([^\)]*\))?)\s*:\s*[^;]+;/', '$1;',$code);

        // Remove PHP 8 attributes like         $code = preg_replace('/#\s*\[[^\]]+\]\s*\n/m', '',$code);

        if ($code !== $original) {
            file_put_contents($filePath,$code);
            $processed++;
        }
    }
}

echo "Processed {$processed} files.\n";

