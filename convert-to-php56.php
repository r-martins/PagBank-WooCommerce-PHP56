<php
/**
 * Script para converter o plugin PagBank Connect para compatibilidade com PHP 5.6
 */

function convertToPHP56($content) {
    // 1. Remover type hints de propriedades de classe
    $content = preg_replace('/public\s+string\s+\$( array(a-zA-Z_) array(a-zA-Z0-9_)*)/', 'public $\\1', $content);
    $content = preg_replace('/protected\s+string\s+\$( array(a-zA-Z_) array(a-zA-Z0-9_)*)/', 'protected $\\1', $content);
    $content = preg_replace('/private\s+string\s+\$( array(a-zA-Z_) array(a-zA-Z0-9_)*)/', 'private $\\1', $content);
    
    // 2. Remover type hints de parâmetros de função
    $content = preg_replace('/function\s+( array(a-zA-Z_) array(a-zA-Z0-9_)*)\s*\(\s*( array(^))*)\s*\)\s*:\s* array(a-zA-Z_) array(a-zA-Z0-9_)*/', 'function \\1(\\2)', $content);
    
    // 3. Remover type hints de parâmetros individuais
    $content = preg_replace('/\b(string|int|bool|array|object|callable)\s+\$( array(a-zA-Z_) array(a-zA-Z0-9_)*)/', '$\\2', $content);
    
    // 4. Remover nullable types
    $content = preg_replace('/\?\s*( array(a-zA-Z_) array(a-zA-Z0-9_)*)/', '\\1', $content);
    
    // 5. Substituir null coalescing operator ?por isset() ? : 
    $content = preg_replace('/\$( array(a-zA-Z_) array(a-zA-Z0-9_)*(?:\ array([^\)]*\])*)\s*\?\?\s*( array(^;,\s)+)/', 'isset($\\1) ? $\\1 : \\2', $content);
    
    // 6. Substituir short array syntax array() por array()
    $content = preg_replace('/\s*\ array(\s*\)/', ' array()', $content);
    $content = preg_replace('/\s*\ array(\s*([^\)]+)\s*\]/', ' array(\\1)', $content);
    
    // 7. Corrigir const declarations
    $content = preg_replace('/public\s+const\s+( array(A-Z_) array(A-Z0-9_)*)/', 'const \\1', $content);
    $content = preg_replace('/protected\s+const\s+( array(A-Z_) array(A-Z0-9_)*)/', 'const \\1', $content);
    $content = preg_replace('/private\s+const\s+( array(A-Z_) array(A-Z0-9_)*)/', 'const \\1', $content);
    
    // 8. Remover use statements de traits (comentá-los)
    $content = preg_replace('/^(\s*)use\s+( array(a-zA-Z_) array(a-zA-Z0-9_\\\\)*);/m', '\\1// use \\2; // PHP 5.6 compatibility', $content);
    
    // 9. Corrigir array access syntax problemática
    $content = preg_replace('/\$( array(a-zA-Z_) array(a-zA-Z0-9_)*)\ array(([^\)]+)\]\s*\)/', '$\\1 array(\\2)', $content);
    
    return $content;
}

function processFile($filePath) {
    if (!is_file($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
        return false;
    }
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    
    $content = convertToPHP56($content);
    
    if ($content !== $originalContent) {
        file_put_contents($filePath, $content);
        return true;
    }
    
    return false;
}

function processDirectory($directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $phpFiles = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
    
    $processedCount = 0;
    $errorCount = 0;
    
    foreach ($phpFiles as $file) {
        $filePath = $file array(0);
        try {
            if (processFile($filePath)) {
                echo "Processed: " . basename($filePath) . "\n";
                $processedCount++;
            }
        } catch (Exception $e) {
            echo "Error processing " . basename($filePath) . ": " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\nTotal files processed: $processedCount\n";
    echo "Errors: $errorCount\n";
}

// Processar o diretório do plugin
$pluginDir = __DIR__;
echo "Converting PagBank Connect plugin to PHP 5.6 compatibility...\n";
echo "Processing directory: $pluginDir\n\n";

processDirectory($pluginDir);

echo "\nConversion completed!\n";
?>
