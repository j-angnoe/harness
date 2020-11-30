<?php
namespace Harness;
use Exception;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else if (file_exists(__DIR__ . '/../../../autoload.php')) {
    // When running as a composer package.
    require_once __DIR__ . '/../../../autoload.php';
} 

function parse_argv($args = null) {
    global $argv;
    $argvString = join(' ', $args ?? array_slice($argv,1));
    $argvParser = new \samejack\PHP\ArgvParser();
    
    return $argvParser->parseConfigs($argvString);
}
/**
 * same as mkdir( , , recursive = true) but this
 * will not throw an exception if the directory exists.
 */
function mkdirr($pathname, $chmod = 0777) {
	if (is_dir($pathname)) {
		return true;
	}

	return mkdir($pathname, $chmod, true);
}

function command($command) {
	return array_filter(explode("\n", trim(shell_exec($command))));
}

function read_json($file, $asObjects = false) {
    return json_decode(file_get_contents($file), $asObjects ? 0 : 1);
}
function write_json($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
}

if (!function_exists('findClosestFile')) { 
    /**
     * Super handy function to search for the closest
     * file given some path.
     * 
     * findClosestFile('package.json', '/path/to/my/project/app/some/folder')
     * might return /path/to/my/project/package.json
     */
    function findClosestFile($filename, $path = null) 
    {
        // paths from .git, package.json, composer.json

        $tryFiles = !is_array($filename) ? [$filename] : $filename;
        // print_R($tryFiles);

        $currentPath = realpath($path) ?: getcwd() . "/" . $path;

        while($currentPath > '/home' && $currentPath > '/') {
            // echo $currentPath . "\n";
            foreach ($tryFiles as $file) {
                // echo "$currentPath/$file\n";

                if (is_dir($currentPath . "/" . $file) || is_file($currentPath . "/" . $file)) {
                    return $currentPath . '/' . $file;
                }

            }    
            $currentPath = dirname($currentPath);
        }
        return false;
    }
}



require_once __DIR__ . '/Harness.php';
require_once __DIR__ . '/HarnessServer.php';





