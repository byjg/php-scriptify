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
- Psalm moved out of `require-dev` into its own manifest, `tools/psalm/composer.json`.

  Psalm enumerates the PHP versions it supports and no published release lists
  8.6. As a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. It now installs separately, only for the Psalm job.

  `composer psalm` still works — it bootstraps the tool and runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor, and needs `sebastian/diff ^9.0`, which stable Psalm 6.16.1 rejects —
  a combination that silently resolves Psalm to an unreleased `6.x-dev` branch.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5 and installs Psalm from `tools/psalm`.

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
