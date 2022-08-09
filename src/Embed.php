<?php

namespace Harness;
require_once __DIR__ . '/includes.php';
class Embed {
    function __construct($toolPath, $baseUri) {
        // test
        $this->object = new Harness($toolPath);
        $this->baseUri = $baseUri;
    }

    function dispatch() {
        $server = new HarnessServer($this->object);
        // $server->setErrorHandlers();

        list(,$uri) = array_pad(explode($this->baseUri, $_SERVER['REQUEST_URI'], 2),2, '');
        $uri = '/' . ltrim($uri, '/');

        $_ENV['HARNESS_EMBEDDED'] = true;

        $this->object->bootstrap();
        $this->server = $server;
        return $server->dispatch($uri);
    }
    function getContent() {
        return $this->object->getBootstrapContent();
    }
    function getApiBridge() {
        $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $this->server->getApiBridge($uriPath.'?api=');
    }
    function resource($path) {
      list($realBase) = array_pad(explode($this->baseUri, $_SERVER['REQUEST_URI'], 2),2, '');

      return rtrim($realBase, '/') . $this->baseUri . '/' . ltrim($path, '/');
    }

    function fileExists(...$args) {
        return $this->object->fileExists(...$args);
    }

    function glob(...$args) {
        return $this->object->glob(...$args);
    }

}
