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

$ob_size = ini_get('output_buffering');
if ($ob_size && $ob_size < (16*1024)) {
    file_put_contents('php://stderr', 
        'Warning: Maximum output buffering size is set to ' . $ob_size . ' bytes. ' . PHP_EOL .
        'This may cause `Headers already sent errors` when working with larger documents. ' . PHP_EOL . 
        'You may increase this limit (in php.ini) to above 16K for instance.'
    );
}
if (!$result) {
    $content = $object->bootstrapContent;
    $title = $object->data['name'] ?? basename(getcwd());

    // Render the first .layout file it finds in object->includePaths
    foreach ($object->glob('*.layout') as $layout) {
        include $layout;    
        return;
    } 
    header('Content-type: text/plain');
    echo $content;
    
} else {
    return true;
}