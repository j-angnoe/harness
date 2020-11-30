<?php

namespace Harness;

class Embed {
    function __construct($toolPath) {
        // test
        $this->object = new Harness($toolPath);
    }
    function dispatch() {
        $server = new HarnessServer($this->object);
        // $server->setErrorHandlers();

        $this->object->bootstrap();
        $this->server = $server;
        return $server->dispatch();
    }
    function getContent() {
        return $this->object->bootstrapContent;
    }
    function getApiBridge() {
        $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return $this->server->getApiBridge($uriPath.'?api=');
    }
    
}
