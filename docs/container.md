---
sidebar_position: 2
---

# Resolve classes through a PSR-11 container

By default Scriptify builds the object with `new`. That is enough for a class with
no constructor dependencies, and it is why Scriptify needs no configuration at all
in the common case. A class that *does* take constructor arguments, however, fails:

```plaintext
Too few arguments to function Some\Name\Space\MyExistingClass::__construct(),
0 passed and exactly 1 expected
```

If your application already builds that class through a [PSR-11](https://www.php-fig.org/psr/psr-11/)
container, Scriptify can ask the container first.

:::info This has two halves
One half lives in **your application** (a bootstrap file that returns the container)
and the other in the **command line** (pointing `--bootstrap` at it). Neither does
anything on its own — a bootstrap nobody points at is never loaded, and a
`--bootstrap` that returns no container falls back to `new`.
:::

## Part 1 — in your application: return the container

Scriptify loads the file given in `--bootstrap`. If that file **returns** an object
implementing `Psr\Container\ContainerInterface`, Scriptify uses it to build your
class. There is nothing to install, extend or register: it is a plain PHP file whose
return value happens to be a container.

```php title="scriptify-bootstrap.php"
<?php

require_once __DIR__ . '/vendor/autoload.php';

return $container;   // any PSR-11 container
```

How you get `$container` is your application's business. Two common shapes:

```php title="Building it in place (PHP-DI)"
<?php

require_once __DIR__ . '/vendor/autoload.php';

return (new DI\ContainerBuilder())
    ->addDefinitions(__DIR__ . '/config/di.php')
    ->build();
```

```php title="Handing over the one your framework already has"
<?php

require_once __DIR__ . '/vendor/autoload.php';

return \ByJG\Config\Config::getContainer();
```

The bootstrap is an ordinary script, so anything else the container needs can happen
before the `return` — setting an environment variable, choosing a profile, loading a
`.env` file.

### Your class has to be registered

Offering a container is a statement: *build my objects this way*. Scriptify takes it
literally. It asks `has($className)`, and a class the container does not hold is
**refused** — it is not quietly built with `new` instead:

```plaintext
Cannot build Some\Name\Space\MyExistingClass: the container has no entry for
"Some\Name\Space\MyExistingClass". Register it, or drop the container from the
--bootstrap file to have Scriptify build it with new.
```

That is deliberate, and it holds even for a class `new` could have built. Guessing
would be worse than it looks: a class whose dependencies are all *optional* —
`__construct(?LoggerInterface $logger = null)` — would be built, would run, and would
run without the collaborators the container would have injected. Working, wrong, and
silent. Refusing turns that into one line at the start.

So if your container registers services by pattern or by explicit binding, make sure
the class you are scriptifying is actually covered. This is the single most common
reason for "I added the container and nothing changed".

If some of your classes belong in the container and others do not, use two bootstrap
files — one that returns the container, one that only requires the autoloader — and
point `--bootstrap` at whichever fits the class you are running.

## Part 2 — on the command line: point to it

```bash
scriptify run \
    "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --bootstrap "scriptify-bootstrap.php" \
    --arg value1
```

`--bootstrap` is relative to `--rootdir`, which defaults to the current directory.

The class can also be named with forward slashes — `Some/Name/Space/MyExistingClass`
— which needs no shell escaping and reaches the container under the same id. See
[Call a PHP method from the command line](script.md).

The same applies when installing a service:

```bash
sudo scriptify install --template=systemd myservice \
    --class "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --bootstrap "scriptify-bootstrap.php" \
    --rootdir "/path/to/root"
```

The installed service runs this same command line, so the daemon resolves its class
exactly as your test run did.

The installation check itself builds nothing. It asks only whether the class and the
method exist, which reflection answers from the class name — so installing a service
never opens the database connections, queues or clients your constructor would, for a
service that has not started yet. It also means `install` never rejects a class over
how it would be built; that is decided when it runs.

## What happens, exactly

| Situation | How the object is built |
|---|---|
| No `--bootstrap` given (default `vendor/autoload.php`) | `new $className()` |
| Bootstrap returns something that is not a PSR-11 container | `new $className()` |
| No container, and the constructor takes arguments | stops, saying no container was offered |
| Container returned, `has($className)` is **true** | `$container->get($className)` |
| Container returned, `has($className)` is **false** | stops, naming the id it looked up |

In short: no container means `new`, exactly as before; a container means the
container, with no second guess.

The class name is normalised before the lookup: Scriptify's own convention writes it
with a leading backslash (`"\\Some\\Class::method"`), and a PSR-11 id does not
carry one.

Errors thrown by `get()` are **not** caught either. If the entry exists but is
misconfigured, you see your container's own error, at the point where it happened.

## Nothing changes if you do not use it

The default bootstrap, `vendor/autoload.php`, returns Composer's `ClassLoader`, which
is not a container. Existing commands, services and scripts keep behaving exactly as
before.
