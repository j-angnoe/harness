<?php
namespace harness;
use Exception;

require_once __DIR__ . '/includes.php';

// Dus, gezien vanuit bookmark omdat we nog linken naar /core.js en /core.css
define('HARNESS_DIR', dirname(__DIR__));
define("HARNESS_ROUTER", substr(__DIR__ . '/router.php', strlen(HARNESS_DIR)+1));

function openInBrowser($url, $options = []) { 
    // @fixme - browserOpts is ignored.
    if (isset($_ENV['HARNESS_BROWSER_COMMAND'])) { 
        system($_ENV['HARNESS_BROWSER_COMMAND'] . " $url");
    } else { 
        system("firefox $url || open $url");
    }
}

function getDefaultHarnessPath() {
    $harnessSettingsFile = findClosestFile('harness-settings.json');
    if ($harnessSettingsFile) {
        $harnessSettings = read_json($harnessSettingsFile);
    }
    return $harnessSettings['@default'] ?? false;
}

function start_webserver ($path = '.', $opts = []) {
    $path = realpath($path);
    if ($path && !is_dir($path)) {
        $path = dirname($path);
    }
    if (!$path) {
        throw new Exception('Invalid path given: '. func_get_arg(0));
    }
    

    mkdirr('/tmp/harness');

    $toolPath = $opts['tool'] ?? $path;
    $package = read_json($toolPath . '/package.json') ?? [];

    $bookmarks_processes = [];
    if (file_exists("/tmp/harness/processes.json")) { 
        $bookmarks_processes = read_json("/tmp/harness/processes.json");

        foreach ($bookmarks_processes as $index => $p) {

            if (($p['args'] ?? []) === [$path, $opts]) {
                if (file_exists("/proc/" . $p['pid'])) {
                    $port = $p['port'];
                    $url = "http://localhost:$port";
                    
                    echo "already running at $url (pid: " . $p['pid'].")\n";

                    sleep(1);

                    openInBrowser("$url");

                    return;
                } else {
                    unset($bookmarks_processes[$index]);
                }
            }
        }
    }
    $port = $opts['port'] ?? $package['harness']['port'] ?? rand(31000,32000);

    if ($opts['no-browser'] ?? false) {
        $openBrowser = fn() => '';
    } else {
        $browserOpts = isset($opts['new-window']) ? ['--new-window'] : [];
        $url = $opts['url'] ?? '';
        $openBrowser = fn() => openInBrowser("http://localhost:$port/$url", $browserOpts);
    }

    $bookmarks_processes[] = [
        'pid' => getmypid(),
        'args' => [$path, $opts],
        'created_at' => date('Y-m-d H:i:s'),
        'port' => $port
    ];
    
    write_json('/tmp/harness/processes.json', array_values($bookmarks_processes));

    $openBrowser();

    // Ik wil die accepted / closing log berichten kwijt.
    // maar $pipes = " | grep -v " werkt niet...
    $pipes = '';
    $env = '';

    if ($opts['harness'] ?? false) {
        if (file_exists($opts['harness'])) {
            $defaultHarness = realpath($opts['harness']);
        }
        
        $harnessSettings = read_json(findClosestFile('harness-settings.json'));
        $harnessSettings['harnesses']['@shipped'] = __DIR__ . '/../default-harness';
        $harnessSettings['harnesses']['none'] = __DIR__ . '/../no-harness';
        
        if ($harnessSettings['harnesses'][$opts['harness']] ?? false) {
            $defaultHarness = $harnessSettings['harnesses'][$opts['harness']];
        } else {
            throw new Exception('Dont know any `'. $opts['harness'] .'` default harness. Maybe check `harness settings` and configure one.');
        }

    } else if (isset($_ENV['HARNESS_DEFAULT_HARNESS_PATH'])) {
        echo "Using env variable for default harness: " . $_ENV['HARNESS_DEFAULT_HARNESS_PATH'] . "\n";
        $defaultHarness = $_ENV['HARNESS_DEFAULT_HARNESS_PATH'];
    } else {
        $defaultHarness = getDefaultHarnessPath();
    }

    // @fixme - shipped default harness wont run in phar mode...
    // because glob cannot handle the phar:// protocol.
    if (!realpath($defaultHarness)) {
        // Use shipped harness path.
        echo "Using shipped default harness\n";
        $defaultHarness = __DIR__ . '/../default-harness';
    }

    $_ENV['HARNESS_DEFAULT_HARNESS_PATH'] = $defaultHarness;
    $env = "HARNESS_DEFAULT_HARNESS_PATH=$defaultHarness $env";

    if ($opts['docker'] ?? false) { 
        error_log("--docker option has been dropped.");
        exit(1);
    }
    if ($opts['tool'] ?? false) {
        $env = "TOOL_DIR='{$opts['tool']}' $env";
    }    
    if (isset($opts['argv']) && $opts['argv']) {
        $env .= " ARGV=" . escapeshellarg(json_encode($opts['argv']));
        error_log('Passing argv to webserver: ' . json_encode($opts['argv']));
    }

    $cmd = "cd $path; $env php -d variables_order=EGPCS -S localhost:$port " . __DIR__ . "/router.php $pipes";
    system($cmd);
}

function versionBanner() {
    $pkg = read_json(__DIR__ . '/../package.json');
    echo "Harness v" . $pkg['version'] . "\n";
}

if ($argv[1]) {
    switch ($argv[1]) {
        case 'version':
            versionBanner();
        break;
        case '-?':
        case '--help':
        case 'help':
            readfile(__DIR__ . '/../README.md');
        break;
        case 'settings': 
            touch($_ENV['HOME'] . '/harness-settings.json');
            $harnessSettingsFile = findClosestFile(('harness-settings.json'));
            if (!$harnessSettingsFile) {
                throw new Exception('Cannot find a harness-settings.json anywhere. Try creating one first in $HOME for instance.');
            }
            system("code " . $harnessSettingsFile);
        break;
        case 'register':
        case 'register-harness':
        case 'register-default-harness':
            $path = realpath('./'. ($argv[2] ?? ''));
            touch($_ENV['HOME'] . '/harness-settings.json');
            $harnessSettingsFile = findClosestFile(('harness-settings.json'));
            if (!$harnessSettingsFile) {
                throw new Exception('Cannot find a harness-settings.json anywhere. Try creating one first in $HOME for instance.');
            }
            
            $harnessSettings = read_json($harnessSettingsFile) ?? [];
            $harnessSettings['harnesses'] = $harnessSettings['harnesses'] ?? [];
            
            $name = str_replace('-default-harness', '', basename($path));
            $harnessSettings['harnesses'][$name] = $path;
            $harnessSettings['@default'] = $path;

            echo "Writing changes to $harnessSettingsFile\n";
            write_json($harnessSettingsFile, $harnessSettings);

        break;
        case 'build':
        case 'watch':
                    
        // Automagic building of bundle when you have request a bundle.js file.
        if (realpath('bundle.js')) { 
            $options = '';
            $command = 'build';
            if ($argv[1] == 'watch') {
                $command = 'watch';
                $options = '--no-hmr';
            }
            if (empty($cmd = command("ps aux | grep 'parcel' | head -n -2 | grep " . escapeshellarg(realpath('bundle.js')) . " | grep -v 'ps aux'"))) {
                system("parcel $command " . realpath('bundle.js') . " $options --no-source-maps &");
            } else {
                echo "There is already a bundler running..";
                print_r($cmd);
            }
        } else {
            echo "Could not find a bundle.js file here..";
        }
        break;
        case 'link': 
            $package = findClosestFile('package.json');

            $package = read_json($package);

            $binFile = '/usr/local/bin/' . $package['name'];

            echo "You want to create $binFile?\n";
            
            if (fread(STDIN, 1) !== 'y') { 
                echo "Aborted...\n";
                exit(0);
            }

            $binContent = join(PHP_EOL, [
                '#!/usr/bin/env sh',
                realpath($argv[0]) . ' ' . getcwd()
            ]);

            file_put_contents($binFile, $binContent);

            chmod($binFile, 0700);

            echo "Created $binFile\n";
        break;
        case 'unlink':
            $package = findClosestFile('package.json');

            $package = read_json($package);

            $binFile = '/usr/local/bin/' . $package['name'];

            echo "You want to delete link to " . $binFile . "?";

            if (fread(STDIN, 1) !== 'y') { 
                echo "Aborted...\n";
                exit(0);
            }


            unlink($binFile);
        break;
        case 'init':
            if ($argv[2]) {
                mkdirr($argv[2]);
                chdir($argv[2]);
            } 
            $object = new Harness(getcwd());
            $source = getDefaultHarnessPath() . '/default/template';
            if (is_dir($source)) { 
                system("rsync --ignore-existing -razv $source/ .");
                $package = read_json('package.json');
                $package['name'] = basename(getcwd());
                write_json('package.json', $package);
                echo "$argv[2] was initialized.";
            } else {
                echo "The default harness does not include a template for new projects.";
            }
        break;
        case 'exec':
        case 'run':
            // @fixme: Refactor this default harness stuff, twas copy/pasted from start_webserver.
            if (isset($_ENV['HARNESS_DEFAULT_HARNESS_PATH'])) {
                echo "Using env variable for default harness: " . $_ENV['HARNESS_DEFAULT_HARNESS_PATH'] . "\n";
                $defaultHarness = $_ENV['HARNESS_DEFAULT_HARNESS_PATH'];
            } else {
                $defaultHarness = getDefaultHarnessPath();
            }
        
            // @fixme - shipped default harness wont run in phar mode...
            // because glob cannot handle the phar// protocol.
            if (!realpath($defaultHarness)) {
                // Use shipped harness path.
                echo "Using shipped default harness\n";
                $defaultHarness = __DIR__ . '/../default-harness';
            }
        
            $_ENV['HARNESS_DEFAULT_HARNESS_PATH'] = $defaultHarness;
            
            echo getcwd();
            $object = new Harness(getcwd());
            $object->setErrorHandlers();
            $object->bootstrap();
            if (file_exists($argv[2])) {
                $controller = $object->loadController($argv[2]);
                $cname = get_class($controller);
                $method = $argv[3];
                $args = array_slice($argv, 4);
            } else {
                $default = $object->loadController('$default');

                if (method_exists($default, $argv[2])) {
                    $cname = get_class($default); 
                    $controller = $default;
                    $method = $argv[2];
                    $args = array_slice($argv, 3);
                } else {
                    $cname = $argv[2];
                    $method = $argv[3];
                    $args = array_slice($argv, 4);
                    $controller = $object->loadController($argv[2]);
                }
            }
            if (!$controller) {
                exit("Error: Controller not found: {$argv[2]}");
            }
            if (!is_object($controller)) {
                exit("Error: Controller is not an object: {$argv[2]}");
            }
            echo "# Call to $cname::$method\n";

            $result = call_user_func_array([$controller, $method], $args);
            if (is_iterable($result) && !is_array($result)) {
                foreach ($result as $r) {
                    echo json_encode($r, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
                }
            } else {
                echo json_encode($result, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES);
            }
            echo "\n";
        break;
        default:
            $path = $argv[1];
            $opts = [];
            if (in_array('--', $argv)) { 

                $index = array_search('--', $argv);
                $argv2 = array_slice($argv, $index+1);
                $argv = array_slice($argv, 0, $index);
                $opts['argv'] = $argv2;
            }
            $opts += parse_argv(array_slice($argv,2));

            start_webserver($path, $opts);
    }
}
