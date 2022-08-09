<?php 
namespace Harness;
use Exception;
require_once __DIR__ . '/includes.php';
class HarnessServer {
    function __construct(Harness $object) {
        $this->object = $object;
    }

    function setErrorHandlers() {
       $this->object->setErrorHandlers();
    }

    function handlePost() {

        if (preg_match('~multipart/form-data~', $_SERVER['CONTENT_TYPE'])) { 
            $controller = '$default';
            $method = 'harnessUpload';
            $args = [];
            $post = $_POST;
            if (isset($_POST['rpc'])) { 
                if (is_array($_POST['rpc'])) { 
                    @list($controller, $method, $args) = $_POST['rpc'];
                } else { 
                    @list($controller, $method, $args) = explode('@', $_POST['rpc']);
                }
            } else {
                $controller = '$default';
                $method = 'harnessUpload';
                $args = [];
                $post['rpc'] = [$controller, $method];
            }

            $files = [];
            foreach ($_FILES as $name => $unit) { 
                if (is_array($unit['name'])) { 
                    $keys = array_keys($unit);
                    for ($i=0; $i < count($unit['name']); $i++) { 
                        $tmpFile = [];
                        error_log('iterate ' . $i);
                        foreach ($keys as $k) { 
                            $tmpFile[$k] = $unit[$k][$i];
                        }
                        $files[] = $tmpFile;
                    }
                } else {
                    $files[] = $unit;
                }
            }
            $args[] = $files;
        } elseif ($_SERVER['CONTENT_TYPE'] == 'application/x-www-form-urlencoded' && isset($_POST['rpc'])) { 
            $post['rpc'] = json_decode($_POST['rpc'],1);
            list($controller, $method, $args) = $post['rpc'];
        } else { 
            $post = json_decode(file_get_contents('php://input'), 1);
            list($controller, $method, $args) = $post['rpc'];
        }

        

        $controller = $this->object->loadController($controller);

        if (!$controller || !is_object($controller)) {
            throw new Exception('Controller not found or its not an object: ' . $post['rpc'][0]);
        }

        if (method_exists($controller, $method)) {
            $result = call_user_func_array([$controller, $method], $args);
        } else {
            throw new Exception($post['rpc'][0] . ' has no method ' . $method .  ', please use one of the following: ' . join("\n", get_class_methods($controller)));
        }

        if (is_iterable($result) && !is_array($result)) {
            $newResult = [];
            foreach ($result as $r) {
                $newResult[]= $r;
            }
            $result = $newResult;
        }
        $this->sendJson($result);    
    }

    function sendJson($data) {
        $headers = join('', headers_list());
        if (stripos('content-type: ', $headers) !== false) { 
            exit(1);
        }

        $result = json_encode($data);

        if ($result === false) {
            header('Content-type: text/plain');
            exit('JSON Encoding of result failed: ' . json_last_error_msg() . ' ' . print_r($data, true));
        }
        header('Content-type: application/json');
        exit($result);
    }

    function dispatch($uri = null) {
        $uri = $uri ?? $_SERVER['REQUEST_URI'];


        if (!function_exists('bridge')) {
            // forward support for bridge.
            function bridge() { } 
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method == 'POST') {
            return $this->handlePost();
        }

        $uri = parse_url($uri, PHP_URL_PATH);
        if ($uri == '/__isalive__') {
            exit('yup');
        }

        if (strpos($uri, '/dist/') === 0) {
            $dcUri = urldecode($uri);
            if (is_file($this->object->path . $dcUri)) {
                return $this->serveFile($this->object->path . $dcUri);
            } else { 
                $controller = $this->object->loadController('$default');
                if ($controller && method_exists($controller, 'harnessServe')) { 
                    $file = $controller->harnessServe(substr($dcUri, strlen('/dist/')));
                    if ($file && file_exists($file)) { 
                        return $this->serveFile($file);
                    }
                }
            }

            error_log('return 404');
            header('HTTP/1.1 404 Not found (yet)');
            exit;
        }

        if (strpos($uri, '/download/') === 0) {
            $dcUri = urldecode($uri);
            $controller = $this->object->loadController('$default');
            if ($controller && method_exists($controller, 'harnessDownload')) { 
                $file = $controller->harnessDownload(substr($dcUri, strlen('/download/')));
                if ($file && file_exists($file)) { 
                    header('Content-disposition: attachment; filename="' . basename($file));
                    return $this->serveFile($file);
                }
            }
            error_log('return 404');
            header('HTTP/1.1 404 Not found (yet)');
            exit;
        }



        // starts with /harness/ ?
        if (strpos($uri, '/harness/') === 0) {
            $tryDirectories = ['/dist/', '/build/'];
            foreach ($tryDirectories as $dir) { 
                $file = $this->object->defaultHarnessPath . $dir . substr($uri, strlen('/harness/'));
                if (file_exists($file)) { 
                    return $this->serveFile($file);
                }
            }
            header('HTTP/1.1 404 Not Found');
            exit;
        }
    }
    function serveFile($file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        // copied from web phar...
        $mimes = array(
            'phps' => 2,
            'dtd' => 'text/plain',
            'rng' => 'text/plain',
            'txt' => 'text/plain',
            'xsd' => 'text/plain',
            'php' => 1,
            'inc' => 1,
            'avi' => 'video/avi',
            'bmp' => 'image/bmp',
            'css' => 'text/css',
            'gif' => 'image/gif',
            'htm' => 'text/html',
            'html' => 'text/html',
            'htmls' => 'text/html',
            'ico' => 'image/x-ico',
            'jpe' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'js' => 'application/x-javascript',
            'midi' => 'audio/midi',
            'mid' => 'audio/midi',
            'mod' => 'audio/mod',
            'mov' => 'movie/quicktime',
            'mp3' => 'audio/mp3',
            'mpg' => 'video/mpeg',
            'mpeg' => 'video/mpeg',
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'swf' => 'application/shockwave-flash',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'wav' => 'audio/wav',
            'xbm' => 'image/xbm',
            'xml' => 'text/xml',
        );
        
        if (isset($mimes[$ext]) && !is_numeric($mimes[$ext])) {
            header('Content-type: ' . $mimes[$ext]);
            readfile($file);
        }
        exit;
    }

    // Dispatch and apiBridge are a nice couple
    function getApiBridge($rootUrl = '') {
        $result = trim(<<<'JAVASCRIPT'

        window.bridge = function (controllerName, functionName, args) {
            var aborter = new AbortController;
            var cancelOptions = { signal: aborter.signal }
            
            var promise = axios.post(
                "{$rootUrl}api/" + (controllerName === '$default' ? '' : controllerName + '/') + functionName,
                { rpc: [controllerName, functionName, args] },
                { 
                    headers: { 'Accept': 'application/json+rpc' },
                    ...cancelOptions
                }
            );

            var result = promise
                .then(response => {
                    // remove(aborter);      
                    return response && response.data
                }, error => {
                    // remove(aborter);
                    return error;
                });

            result.abort = function () {
                console.log('aborted');
                aborter.abort();
            }
            
            return result;
        };
        window.bridge.running = [];
        window.bridge.abort = function () {
            window.bridge.running.map(a => {
                console.log('Aborting running unit');
                a && a.abort()
            });
        }
        window.api = new Proxy({},{
            get(obj, apiName) {
                var callDefaultApi = function (...args) { 
                    return window.bridge('$default',apiName, args);
                }
                return new Proxy(
                    callDefaultApi,
                    {
                    get(obj, functionName) {
                        callNamedApi = function (...args) {
                            return window.bridge(apiName, functionName, args);
                        };
                        callNamedApi.post = function(...args) { 
                            var form = document.createElement('form');
                            form.action = "{$rootUrl}api/" + apiName + "/" + functionName;
                            form.method = "POST";
                            form.target = "_blank";
                            form.style.display = 'none';

                            var input = document.createElement('input')
                            input.name = 'rpc';
                            input.value = JSON.stringify([apiName, functionName, args]);
                            form.appendChild(input);

                            var button = document.createElement('button')
                            button.innerHTML = 'submit';
                            form.appendChild(button);
                            setTimeout(() => {
                                form.submit();
                            }, 10);
                            document.body.appendChild(form);
                        }
                        
                        return callNamedApi;
                    }
                }
            );
            }
        });
        if (window.Vue) { 
            window.Vue.prototype.api = api;
        }
JAVASCRIPT);   

        $result = str_replace('{$rootUrl}', $rootUrl, $result);

        return $result;

    }

    function resource($path) {
        return $path;
    }
    
    function fileExists(...$args) {
        return $this->object->fileExists(...$args);
    }

    function glob(...$args) {
        return $this->object->glob(...$args);
    }

}
