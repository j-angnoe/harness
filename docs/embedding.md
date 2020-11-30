# Embedding Harness tools in projects

When you created a standalone tool which you want to ship 
inside a project...

## Considerations

The project will probably supply the basic harness (scripts, styles and such)
Your tool will probably be adapted to the project's context. So, this simplifies
the process of embedding.

## Embedding in laravel

Here are some measures I took to embed a tool inside an existing laravel (v7)
project:

```php 
// inside routes/web.php

// I picked some route
Route::any('/calculator', function () {
    // I placed the module somewhere:
    $obj = new Harness\Embed(__DIR__ . '/../modules/calculator');

    if (!$obj->dispatch()) {
        // There was no POST handled, so display the html.

        // I created a harness `view` which i pass the server object.
        return view('harness')
            ->with('server', $obj)
        ;
    }
});
```

```php
// View harness.blade.php
@extends('layouts.app')

@section('content')
    <!-- normally: 
        <app></app>
        <template component="app"><router-view></router-view></template>
    -->

    <!-- but in this case section content is already 
         within a Vue bootstrapped element so do router-view.
     -->
  <router-view></router-view>
@endsection

<!-- Outside of vue context we declare our vue-blocks stuff -->
@section('components')
  @php echo $server->getContent() @endphp
  <script>
    @php echo $server->getApiBridge() @endphp
  </script>
@endsection

<!-- This specific project already has vue, axios, vue-blocks and vue-router in place -->
```