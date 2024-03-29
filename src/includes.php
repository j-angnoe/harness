<?php
namespace Harness;
use Exception;


if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else if (file_exists(__DIR__ . '/../../../autoload.php')) {
    // When running as a (global) composer package.
    $oldLevel = error_reporting();
    
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    require_once __DIR__ . '/../../../autoload.php';
    error_reporting($oldLevel);
} 

require_once 'bridge.php';

function parse_argv($args = null) {
    global $argv;

    $args = [];
    for($i=0;$i<count($argv);$i++) { 
        $arg = $argv[$i];
        if (substr($arg,0,2) === '--' && strlen($arg) > 2) {
            if (strpos($arg,'=') !== false) {
                list($arg, $value) = explode('=', substr($arg, 2), 2);
                $args[$arg] = $value;
            } else {
                // Please note the i++
                $args[substr($arg,2)] = $argv[$i++];
            }
        }
    }
    return $args;
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
    function findClosestFile($filename, $paths = null) 
    {
        // paths from .git, package.json, composer.json

        $tryFiles = !is_array($filename) ? [$filename] : $filename;
        // print_R($tryFiles);

        if ($paths) { 
            $paths = is_array($paths) ? $paths : [$paths];
            $paths = array_map(function($path) { 
                return realpath($path) ?: getcwd() . "/" . $path;
            }, $paths);
        } else {
            $paths = explode(PATH_SEPARATOR, ini_get('include_path'));
            $paths = array_map('realpath', $paths);

        }
        $paths = array_filter($paths);

        foreach ($paths as $currentPath) { 
            // Dont go all the way down to root level.
            while(strlen($currentPath)>4 && $currentPath > '/') {
                // echo $currentPath . "\n";
                foreach ($tryFiles as $file) {
                    // echo "$currentPath/$file\n";

                    if (is_dir($currentPath . "/" . $file) || is_file($currentPath . "/" . $file)) {
                        return $currentPath . '/' . $file;
                    }

                }    
                $currentPath = dirname($currentPath);
            }
        }
        return false;
    }
}



require_once __DIR__ . '/Harness.php';
require_once __DIR__ . '/HarnessServer.php';





