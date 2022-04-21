<?php 
namespace Harness;
use Exception;
require_once __DIR__ . '/includes.php';

class Harness {
    var $path;
    var $data;
    var $bootstrapContent;
    var $includePaths = [];

    function __construct($path) {
        $this->path = $path;

        if (file_exists($this->path . '/package.json')) { 
            $this->data = read_json($this->path . '/package.json');
        } else {
            $this->data = [];
        }

        if (isset($_ENV['HARNESS_INCLUDE_PATHS'])) {
            
        }
        $this->includePaths = [
            $this->path
        ];

        $defaultHarness = $_ENV['HARNESS_DEFAULT_HARNESS_PATH'] ?? false;
        
        if ($defaultHarness) {
            // If default-harness is a relative path, calculate 
            // the absolute path relative to the location of my package.json
            $this->defaultHarnessPath = realpath($defaultHarness) ?: realpath(__DIR__ .'/../' . $defaultHarness);

            if (!$this->defaultHarnessPath) {
                throw new Exception("Default harness path \`$defaultHarness\` could not be found.");
            }

            array_unshift(
                $this->includePaths, 
                $this->defaultHarnessPath
            );
        }

    }

    function glob($patterns) {
        $patterns = is_array($patterns) ?: func_get_args();

        foreach ($patterns as $p) {
            foreach ($this->includePaths as $includePath) {
                yield from glob($includePath . '/' . $p);
            }
        }
    }
    function fileExists($file, $strict = false) { 
        $paths = $strict ? [$this->path] : $this->includePaths;
        foreach ($paths as $includePath) {
            if ( file_exists($includePath . '/' . $file) ) {
                return true;
            }
        }
        return false;
    }

    function include($file) {
        $file = realpath($file);

        $this->__includeCache = $this->__includeCache ?? [];
        if (!isset($this->__includeCache[$file])) { 
            $this->__includeCache[$file] = include_once $file;
        } 
        return $this->__includeCache[$file];
    }
    function bootstrap() {
        ob_start();
        
        foreach (explode(PATH_SEPARATOR, ini_get('include_path')) as $p) { 
            if (file_exists("$p/vendor/autoload.php")) { 
                require_once "$p/vendor/autoload.php";
            }
        }
        
        if (file_exists($this->path . '/vendor/autoload.php')) {
            require_once $this->path . '/vendor/autoload.php';
        }


        // Load includes and *.inc.php from the main directory.
        // so /includes.php will be loaded
        // /folder/includes.php will not be loaded.
        foreach ($this->glob('*.inc.php', 'includes.php') as $includes) {
            $this->include($includes);
        }
        
        // Load all html files from root directory.
        foreach ($this->glob('*.html') as $html_files) {                       
            $this->include($html_files);
        }

        $this->bootstrapContent = ob_get_clean();
    }

    
    function loadController($name) {
        /**
         * supports:
         * - files that return new class,
         * - files that declare a class
         */
        $constructController = function ($file) {
            if (class_exists($file)) {
                return new $file;
            } 

            ob_start(); 
            if (file_exists($file)) {
                $result = $this->include($file);
            } else if (file_exists($file .'.php')) {
                $result = $this->include($file .'.php');
            } else {
                throw new Exception(__METHOD__ . ' file ' . $file .' not found.');
            }
            ob_get_clean();
            if (is_object($result)) {
                return $result;
            } else {
                $c = get_declared_classes();
                $lastClass = end($c);
                return new $lastClass;
            }
        };

        if ($name == '$default') {
            if (class_exists('controller')) {
                $name = 'controller';
            } else if (file_exists('controller.php')) {
                $name = 'controller';
            } 
        }

        return $constructController($name);
    }

    function exceptionHandler($ex) { 
        // @fixme - only expose this
        // when APP_DEBUG or something is set.
        
        if (php_sapi_name() !== 'cli') { 
            header('HTTP/1.1 500 Internal server error');
        }

        $frames = $ex->getTrace();
        
        $isFirst = true;
        $newFrames = [];
        foreach ($frames as $idx => &$fr) {
            if (isset($fr['file'])) {
                if (dirname($fr['file']) === __DIR__) {
                    continue;
                }
                $line = $fr['line'];
                $file = $fr['file'];
                $lines = [];

                $code_context = '';

                if (strpos($file, "eval()'d code") > 0) {
                    $file = 'eval()\'d code';
                    if (isset($GLOBALS['last_evalled_code'])) { 
                        $lastCode = $GLOBALS['last_evalled_code'] ?? [];
                        $lines = array_map(function($l) { return $l."\n"; }, explode("\n", array_pop($lastCode)));
                    } else {
                        $lines = [];
                    }
                } elseif (file_exists($file)) {
                    $lines = file($file);
                }

                if ($lines) { 
                    $code_context = '';
                    for ($i = max(0, $line - 5); $i < min($line + 5, count($lines)); $i++) { 
                        $code_context .= ($i+1).': ' . $lines[$i];
                    }
                } 
                $newFrames[] = [
                    'file' => $file,
                    'line' => $line,
                    'code' => $code_context,
                    'content' => join("", $lines)
                ];
            }
        }
        if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-type: application/json');
            echo json_encode(['error' => $ex->getMessage() . ' ' . $ex->getTraceAsString(), 'nice_trace' => $newFrames]);
        } else {
            echo $ex->getMessage();
            print_r(array_map(function($f) {
                unset($f['content']);
                return $f;
            }, $newFrames));
        }
        exit(1);
    }
    function errorHandler($errno, $errmsg, $errfile, $errline) {
        $this->exceptionHandler(new Exception($errmsg . ' (errno: ' . $errno.') at '. $errfile . ' on line ' . $errline));

    }
    function setErrorHandlers() { 
        ini_set('display_errors', 'on');
        error_reporting(E_ALL ^ E_NOTICE);
        set_exception_handler([$this, 'exceptionHandler']);    
        set_error_handler([$this, 'errorHandler']);
    }

    function resource($path) {
        return $path;
    }
}
