---
sidebar_position: 5
---

# Call a REST endpoint from the command line

Calling an endpoint from the command line without actually doing an HTTP Request is straightforward.

Your endpoint must be implemented using any PHP framework that handles the HTTP request and response.

e.g.:
 - byjg/restserver
 - slimphp/slim
 - zendframework/zend-expressive
 - symfony/http-foundation
 - etc.

Let's take a look at an example using the `byjg/restserver` framework, and with the controller file `app.php`:

```php
<?php
use ByJG\RestServer\OutputProcessor\JsonOutputProcessor;
use ByJG\RestServer\Route\Route;
use ByJG\RestServer\Route\RouteList;
use ByJG\RestServer\Server;

require_once __DIR__ . '/vendor/autoload.php';

$routeDefinition = new RouteList();
$routeDefinition->addRoute(Route::get("/testclosure")
    ->withOutputProcessor(JsonOutputProcessor::class)
    ->withClosure(function ($response, $request) {
        $response->write([
            "result" => "OK",
            "arg" => $request->query("arg")
        ]);
    })
);

$restServer = new Server();
$restServer->handle($routeDefinition);
```

The example above creates a simple REST endpoint `/testclosure` that returns a JSON with the argument `arg`.

To call this endpoint from the command line, you can use the following command:

```bash
scriptify call \
    /testclosure \
    --controller "app.php" \
    --http-get "arg=value1"
```
