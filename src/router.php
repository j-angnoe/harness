<?php

namespace harness;
use Exception;

require_once __DIR__ . '/includes.php';


error_log($_SERVER["REQUEST_METHOD"] . ' ' . $_SERVER['REQUEST_URI']);
if (isset($_ENV['ARGV'])) { 
    $args = json_decode($_ENV['ARGV']);
    array_unshift($args, '(php)');

    $GLOBALS['argv'] = $args;
}

$object = new Harness($_ENV['TOOL_DIR'] ?? getcwd());


$server = new HarnessServer($object);
$server->setErrorHandlers();

$object->bootstrap();
$result = $server->dispatch();

if (!$result) {
    $content = $object->getBootstrapContent();
    $title = $object->data['name'] ?? basename(getcwd());

    // Render the first .layout file it finds in object->includePaths
    foreach ($object->glob('*.layout') as $layout) {
        include $layout;    
        return;
    } 
    header('Content-type: text/plain');
    $object->outputBootstrapContent();
} else {
    return true;
}