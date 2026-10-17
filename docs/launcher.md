---
sidebar_position: 2
---

# Give your application a command line: Launcher

`scriptify run` calls one method by its class name. An application usually wants more:
short command names (`./app payment:sweep`), a list of them, their help, and its own
objects, built by its PSR-11 container. That is `ByJG\Scriptify\Launcher`, a library
class you call from a script of your own.

## Describe the commands: a CommandProvider

```php title="src/Command/AppCommands.php"
<?php

namespace App\Command;

use ByJG\Scriptify\CommandProvider;

final class AppCommands implements CommandProvider
{
    public function commands(): array
    {
        return [
            // A class: each public method it declares is a command, in kebab-case:
            // payment:charge, payment:refund, payment:sweep, ...
            'payment' => PaymentCommands::class,

            // One method
            'cache:clear' => CacheService::class . '::clear',
        ];
    }
}
```

The targets are class references (`::class`), so a typo fails in the IDE and in static
analysis, and a rename is refactor-safe.

- Names are lowercase, `group:action`, using `a-z`, `0-9`, `:` and `-`.
- A class target exposes the public methods it declares itself: not the inherited,
  static or magic ones. A name produced twice is an error.
- The method must be public.

## Run them

```php title="app"
#!/usr/bin/env php
<?php
require __DIR__ . '/vendor/autoload.php';

$container = /* your PSR-11 container, or null */;

exit((new \ByJG\Scriptify\Launcher(new \App\Command\AppCommands(), $container))->run($argv));
```

```bash
./app list                      # the commands, with the first line of each PHPDoc
./app list --check              # a status per command, without running any (exit 1 if one is not OK)
./app payment:refund --help     # the method's PHPDoc and its usage: ./app payment:refund <paymentId>
./app payment:refund 42         # calls refund("42"): the values are the parameters, in order
./app payment:sweep --daemon    # calls it again every second, for a service
./app payment:refund 42 -v      # on failure, also print the stack trace
```

`list --check` gives each command one of: `OK`, `INVALID_NAME`, `RESERVED`,
`INVALID_TARGET`, `MISSING_CLASS`, `MISSING_METHOD`, `NOT_PUBLIC`, `NOT_IN_CONTAINER`, or `ERROR`
with the message when the provider or the container throws.
It builds nothing: reflection and the container's `has()` answer it.

## How the class is built

| Situation | How the object is built |
|---|---|
| No container | `new $className()` |
| No container, and the constructor requires arguments | stops, saying no container was offered |
| Container, `has($className)` is **true** | `$container->get($className)` |
| Container, `has($className)` is **false** | stops, naming the id it looked up |

Giving a container is a statement: *build my objects this way*. A class the container
does not hold is **refused**, not quietly built with `new`, even when `new` could build
it. A class whose dependencies are all optional would otherwise run without the
collaborators the container would have injected: working, wrong and silent.

```plaintext
Cannot build App\Command\PaymentCommands: the container has no entry for
"App\Command\PaymentCommands". Register it in the container.
```

Errors thrown by `get()` are not caught: a misconfigured entry shows the container's own
error, where it happened.

The bundled `scriptify run` follows the same rules with the container its `--bootstrap`
file returns, if any: see [Resolve classes through a PSR-11 container](container.md).

## Add commands of your own

A host with built-in commands extends `Launcher`:

- `dispatch()` runs its own commands, and calls `parent::dispatch()` for the rest;
- `isReserved()` returns true for their names, so the provider cannot take them
  (`list` is always reserved);
- `prepare()` consumes its global options before the command is read;
- `getContainer()` builds the container on demand, instead of passing it in;
- `scriptName()`, `usage()`, `listCommands()` and `report()` show them.

## Install a command as a service

`Scriptify::installCommand()` writes a service for a command line, as given. The command
must understand `--daemon` (every template but `crond`, which runs it once a minute), as
a Launcher does:

```php
Scriptify::installCommand(
    'app-payment-sweep',                                         // the service name
    PHP_BINARY . ' /srv/app/app payment:sweep',                  // the full command, paths absolute
    '/srv/app',                                                  // its working directory
    'systemd',                                                   // systemd, crond, upstart or initd
    'Sweep the payments',
    ['APP_ENV' => 'prod']                                        // written to /etc/scriptify/<name>.env
);
```

`Scriptify::uninstall('app-payment-sweep')` removes it. See
[Install a PHP class/method as a service](install.md).
