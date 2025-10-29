<?php
/**
 * Script simples para verificar sintaxe PHP 5.6
 */

function checkPhpSyntax($file) {
    $output = array();
    $return_var = 0;
    $command = "php -l " . escapeshellarg($file) . " 2>&1";
    exec($command,$output,$return_var);
    
    if ($return_var !== 0) {
        return array(
            'file' => $file,
            'error' => implode("\n",$output),
            'success' => false
        );
    }
    
    return array(
        'file' => $file,
        'error' => null,
        'success' => true
    );
}

function getAllPhpFiles($dir) {
    $files = array();
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

echo "Verificando sintaxe PHP 5.6 em todos os arquivos do plugin...\n";
$pluginDir = dirname(__FILE__);
$phpFiles = getAllPhpFiles($pluginDir);

$errorsFound = 0;
$errorFiles = array();

foreach ($phpFiles as $file) {
    $result = checkPhpSyntax($file);
    if (!$result['success']) {
        echo "  [ERRO] " . $result['file'] . "\n";
        echo "    " . $result['error'] . "\n";
        $errorsFound++;
        $errorFiles[] = $result['file'];
    } else {
        echo "  [OK] " . $result['file'] . "\n";
    }
}

echo "\n--- Resumo ---\n";
if ($errorsFound > 0) {
    echo "Foram encontrados " . $errorsFound . " arquivos com erros de sintaxe PHP 5.6.\n";
    echo "Arquivos com erros:\n";
    foreach ($errorFiles as $file) {
        echo "- " . $file . "\n";
    }
} else {
    echo "Nenhum erro de sintaxe PHP 5.6 encontrado em " . count($phpFiles) . " arquivos.\n";
}
?>

