---
sidebar_key: scriptify
tags: [php, cli]
---

# Scriptify - CLI Script Transformer

Run any existing PHP method from the command line, or install it as a system service, without writing a command class.

[![Sponsor](https://img.shields.io/badge/Sponsor-%23ea4aaa?logo=githubsponsors&logoColor=white&labelColor=0d1117)](https://github.com/sponsors/byjg)
[![Build Status](https://github.com/byjg/php-scriptify/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-scriptify/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](https://opensource.byjg.com)
[![Install MCP Server](https://img.shields.io/badge/Install-MCP_Server-8A2BE2?logo=modelcontextprotocol&logoColor=white)](https://opensource.byjg.com/docs/ai/mcpserver-byjg-docs/)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-scriptify/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-scriptify.svg)](https://opensource.byjg.com/license/)
[![GitHub release](https://img.shields.io/github/release/byjg/php-scriptify.svg)](https://github.com/byjg/php-scriptify/releases/)

## Why

To call your code from a shell, a cron job or a daemon, frameworks make you write a command
class for each task: parse the arguments, build the dependencies, call the method. The
method you wanted to run already exists.

Scriptify calls it directly:

```bash
scriptify run "App/Service/Report::rebuild" --arg 2026-10      # once, from the shell
scriptify install --template=crond nightly-report \
    --class "App/Service/Report::rebuild" --rootdir /srv/app   # as a cron job or systemd service
```

- **No glue code.** The arguments are passed to the method's parameters, and with a PSR-11
  container the class gets its constructor dependencies.
- **From shell to service in one step.** `install` writes the systemd, cron, upstart or
  init.d service with the full command line.
- **A command line for your app.** `Launcher` maps short names (`php app report:rebuild`) to
  methods, and `scriptify terminal` opens a REPL with your autoloader.

## Features

Scriptify allows you to:
- Call any PHP method from the command line
- Install PHP classes/methods as system services (daemon, cron, etc.)
- Call REST endpoints from shell scripts
- Open an interactive PHP terminal with your project's autoloader
- Pass environment variables to your scripts

## Documentation

- [Call a PHP method from the command line](docs/script.md)
- [Resolve classes through a PSR-11 container](docs/container.md)
- [Give your application a command line: Launcher](docs/launcher.md)
- [Install a PHP class/method as a service](docs/install.md)
- [Call a REST endpoint from the command line](docs/endpoint.md)
- [Interactive PHP terminal](docs/terminal.md)
- [Show method documentation](docs/showdocs.md)
- [Environment variables](docs/environment.md)
- [Play with the service](docs/play.md)

## Installation

Scriptify does not need to be associated with your PHP project.

### Install Locally (Recommended)

```bash
composer require "byjg/scriptify"
```

### Install Globally

```bash
composer global require "byjg/scriptify"
sudo ln -s /root/.composer/vendor/bin/scriptify /usr/local/bin/scriptify

# If you want to share this installation with other users, consider using:
chmod a+x /root
# Note: The root directory will remain unreadable for them, but they can execute scriptify
```

## Running Tests

```bash
vendor/bin/phpunit
```

----
[Open source ByJG](https://opensource.byjg.com)