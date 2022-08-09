# Harness

Quick prototyping, create a directory, create some html, php and stylesheets
and fiddle with your prototype in the browser.

Your prototype may make use of Vue (2.6), VueRouter, <a href="//github.com/j-angnoe/vue-blocks">vue-blocks</a> 
and it will contain an api bridge to call functions on your defined php objects.

## Example

```php 
<!-- myprotoype/index.html -->
<template url="/">
    <div>
        <h1>My Prototype</h1>
        <p>Retrieved data:</p>
        <pre>{{serverData}}</pre>
    </div>
    <script>
        export default {
            async mounted() {
                var arg1 = 'arg1';
                var arg2 = 'some value';
                this.serverData = await api.controller.getMyData(arg1, arg2);
            }
        }
    </script>
</template>

<?php 
class controller {
    function getMyData($arg1, $arg2) {
        return [
            'you have sent us: ' . $arg1 . ' and ' . $arg2
        ];
    }
}
?>
```

## Installation

### Via composer
```sh
# Add to project
composer require j-angnoe/harness
# Use inside project:
vendor/bin/harness

# Or install globally
composer global require j-angnoe/harness:@dev

# Make sure the global composer path is inside path
PATH="$PATH:~$HOME/.config/composer/vendor/bin";
```

### Installation method 2: harness.phar
```sh
# Get the harness executable
wget https://github.com/j-angnoe/toolkit/raw/master/harness/build/harness.phar

# Make it executable
chmod +x harness.phar

# Create a symlink ins\ /usr/local/bin so you can access it anywhere from the commandline
sudo ln -sf harness.phar /usr/local/bin/harness 

# Now get yourself a harness and register it as default harness.
# for instance https://github.com/j-angnoe/toolkit/tree/master/vue-default-harness
cd /path/to/my-default-harness;
harness register;
```

## Usage 


```sh 
# Spin up a webserver that will serve your directory
harness [directory]  

# Execute a controller method
harness exec [controller] [method] [...args]
harness exec [filename] [method] [...args]
harness run  # alias for exec

# Building `bundle.js` files for your tools
npm install -g parcel;      # Parcel is a nice companion for prototyping
cd my-tool;
harness build .     # one time build
harness watch .     # continious building 

# Creating a harness tool
harness init my-tool;
cd my-tool;
# start working on your tool

harness register;       # Run this inside a default harness you want to use as default.
harness settings;       # Open the harness-settings.json file in `code`

# Harnass options 
harnass [directory]
    --port        # Which port to run
    --no-browser  # Dont open a browser window
    --tool        # Specify tool directory (instead of assuming [directory] is a tool)
```

## Embedding tools in existing projects

Some insights into how to embed can be found in [docs/embedding.md](docs/embedding.md)

## Requirements
- linux (developed on ubuntu, tested on mac).
- php 7.4
- a browser (either firefox or via mac's open command) or set environment variable HARNESS_BROWSER_COMMAND
- code (launch editor for harness settings)

### Nice to haves
- the fd command
- parcel (npmjs.org/parcel) for automagic bundle building

### Handling oploads
When a POST (multipart/form-data) to /upload is encountered, harness will try to 
call a static method `harnessUpload` on your default controller.

```php
// Clientside:
// Create a form that posts to `/upload`
<form method="POST" enctype="multipart/form-data" action="/upload">
    <input type="file" name="file" multiple>
    <input type="submit" value="Upload">
</form>

// Server side:

/** 
 * Create a function called `harnessUpload` 
 **/
function harnessUpload($arrayOfUploadedFiles = null) { 
    foreach ($arrayOfUploadedFiles as $file) { 
        // do your moving magic: 
        // move_uploaded_file($file['tmp_name'], '/somewhere/' . $file['name']); 
    }
}
/**
 * The arrayOfUploadedFiles is an array and supports single and multiple uploads.
 * Each element of $files is an associative array with these keys:
 * - name, type, tmp_name, size, error (see PHP Uploaded Files for more info).
 * 
 * Of course you may ignore this argument and work directly with PHP's $_FILES global
 * if you so desire. 
 **/
```

## Serving and downloading files
Harness will link /download/FILENAME to Controller::harnessDownload($filename) 
and will link /dist/FILENAME to Controller::harnessServe($filename)

These functions must return a valid filename to be served by Harness.
Harness will do some mime-magick for you so you dont have to. 
If the function fail to provide a valid (readable) file then an HTTP 404
will be sent.

```php
// server side:
class Controller { 
    function harnessDownload($file) { 
        if (file_exists('./my-secret-files/' . $file)) {
            return './my-secret-files/' . $file;
        }
    }
    function harnessServe($file) { 
       return $this->harnessDownload($file);
    }
}

// client side:

<a href="/download/awesome-file.txt" target="_blank">Download</a>

Awesome image: 
<img src="/dist/my-image.png">

```

/download and /dist are pretty much the same. /download will also add a Content-Disposition
header. If you want download and dist to serve the same files, implement the 2 functions
as shown in the example above. 

