<?php

namespace harness;
use Exception;

require_once __DIR__ . '/includes.php';

error_log($_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER['REQUEST_URI']);

$object = new Harness($_ENV['TOOL_DIR'] ?? getcwd());

$server = new HarnessServer($object);
$server->setErrorHandlers();

$object->bootstrap();
$result = $server->dispatch();
if (!$result) {
    $content = $object->bootstrapContent;
    $title = $object->data['name'] ?? basename(getcwd());

    // Render the first .layout file it finds in object->includePaths
    foreach ($object->glob('*.layout') as $layout) {
        echo '<!-- using layout ' . $layout . '-->';
        include $layout;    
        return;
    } 
    header('Content-type: text/plain');
    echo $content;
    
} else {
    return true;
}