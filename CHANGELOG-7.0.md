# Changelog - Version 7.0

> **Status: in development.** This document tracks changes landing on the `7.0` branch.
> Nothing here is released yet, and the contents may still change.

## Breaking Changes

- `symfony/console` is now `^7.4 || ^8.0`, up from `^5.4|^6.2|^7.0`.

  Console 8.0 removed `Application::add()`. Its replacement, `addCommand()`, only
  exists from 7.4 on, so spanning both would mean picking the method at runtime.
  Raising the floor to 7.4 keeps the console bootstrap a plain call.

  The lower bounds this drops were already unusable on this branch: console 5.4
  conflicts with `psr/log >= 3`, which `byjg/restserver ^7.0` requires, and
  6.x through 7.3 conflict with the `symfony/yaml` 8.x the toolchain pulls in.

## New

- Classes can now be resolved through a [PSR-11](https://www.php-fig.org/psr/psr-11/)
  container, so a class with constructor dependencies can be scriptified without
  being changed.

  The protocol is the `--bootstrap` file: if it *returns* a `ContainerInterface`,
  Scriptify asks it for the class and falls back to `new` for anything the container
  does not know. `run` and the `install` check resolve identically, so `install`
  never rejects a class `run` can execute. See [docs/container.md](docs/container.md).

  Nothing changes without opting in: the default bootstrap, `vendor/autoload.php`,
  returns Composer's `ClassLoader`, which is not a container.

- Resolution is strict once a container is offered: a class the container does not
  hold is refused, not built with `new`. Falling back would be a guess, and a class
  whose dependencies are all optional would run without the collaborators the
  container holds -- working, wrong and silent. Without a container nothing changed:
  `new`, as always.

- When `new` is the path and the constructor takes arguments, the failure is now a
  `ScriptifyException` naming the class, instead of an `ArgumentCountError` thrown
  from inside `Runner` with a stack trace pointing at the wrong place.

- `install` no longer instantiates the class it is checking. The check asks whether
  the class and the method exist, which `method_exists()` answers from the class
  name; building the object pulled in its whole dependency graph -- database
  connections included -- to install a service that had not run yet.

- A class can be named with forward slashes -- `Some/Name/Space/MyClass::method` --
  as well as with backslashes. A backslash is the shell's escape character, so the
  documented form has to be quoted, and doubled again whenever the command passes
  through another layer: inside a `composer.json` script a single separator becomes
  four characters, which is a fine place for a typo to hide. A forward slash is not
  valid in a PHP identifier, so the conversion cannot destroy a legitimate name.

  `--showdocs` now prints the slash form, so the usage line it emits can be pasted
  anywhere without adjusting the quoting.

- `psr/container` is now a declared dependency. It was already in the tree through
  `symfony/service-contracts`, but `Runner` names the interface in its public
  signature now, so relying on a transitive dependency would be luck.

## Requirements

- PHP 8.3, 8.4, 8.5 and 8.6 are now supported: `"php": ">=8.3 <8.7"`.
  The previous `<8.6` upper bound excluded PHP 8.6, since `<8.6` is exclusive.

### ByJG dependencies

- `byjg/jinja-php` is now `^7.0`.
- `byjg/restserver` is now `^7.0`.

While 7.0 is unreleased these resolve to `7.0.x-dev` from each component's
`7.0` branch, via `minimum-stability: dev` with `prefer-stable: true`.

## Toolchain

- PHPUnit updated to `^12.5`.
- Psalm is installed as `psalm/phar` instead of `vimeo/psalm`.

  `vimeo/psalm` lists the PHP versions it supports and no published release includes
  8.6, so as a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. `psalm/phar` requires only `php ^8.2` and bundles its own
  dependencies, so it installs on every PHP version in the matrix and cannot conflict
  with the project's. Psalm itself still refuses to *run* on 8.6, which is why the
  Psalm job uses 8.5. `composer psalm` runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5.

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
