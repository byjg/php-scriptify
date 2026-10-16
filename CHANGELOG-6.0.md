# Changelog for version 6.0

## Overview

Version 6.0 represents a major evolution of the project, including a complete rebranding from "PHP Daemonize" to "Scriptify" to better reflect its broader capabilities. This release introduces new features, improves type safety, and modernizes the codebase while introducing breaking changes that require migration steps.

## New Features

### Interactive PHP Terminal
- **TerminalCommand**: Added a new interactive PHP terminal that allows you to execute PHP code with your project's autoloader
- Supports environment variable loading through `--env` parameter
- Includes namespace alias parsing and resolution for enhanced code handling
- Supports preload files for initializing imports, variables, and helper functions via `--preload` parameter
- Features autocomplete functionality for improved developer experience

### Enhanced Template System
- Migrated to Jinja templating engine (via `byjg/jinja-php`)
- All service templates converted from `.tpl` to `.jinja` format for better maintainability
- Improved template resolution and autoload handling

### Documentation Improvements
- Complete documentation restructure and enhancement
- Added new documentation for the interactive terminal feature
- Improved consistency across all documentation pages
- Better examples and use cases

## Bug Fixes

- Fixed PHP 8.4 deprecations by adding explicit nullable types
- Enhanced error handling across multiple components
- Improved type annotations throughout the codebase

## Breaking Changes

| Before (5.x) | After (6.x) | Description |
|-------------|------------|-------------|
| Package: `byjg/php-daemonize` | Package: `byjg/scriptify` | Complete package rename |
| Namespace: `ByJG\Daemon` | Namespace: `ByJG\Scriptify` | Root namespace changed |
| Binary: `scripts/daemonize` | Binary: `scripts/scriptify` | Executable script renamed |
| Class: `ByJG\Daemon\Daemonize` | Class: `ByJG\Scriptify\Scriptify` | Main class renamed |
| Exception: `DaemonizeException` | Exception: `ScriptifyException` | Exception class renamed |
| PHP: `>=8.1 <8.4` | PHP: `>=8.3 <8.6` | Minimum PHP version raised to 8.3 |
| Templates: `.tpl` format | Templates: `.jinja` format | Template file format changed |
| N/A | Dependency: `byjg/jinja-php` ^6.0 | New required dependency |
| N/A | Extension: `ext-readline` | New required extension |

## Upgrade Path from 5.x to 6.x

### 1. Update Composer Dependencies

```bash
# Remove the old package
composer remove byjg/php-daemonize

# Install the new package
composer require byjg/scriptify
```

### 2. Update Namespace References

Replace all namespace imports in your code:

**Before:**
```php
use ByJG\Daemon\Daemonize;
use ByJG\Daemon\DaemonizeException;
use ByJG\Daemon\Caller;
use ByJG\Daemon\Runner;
```

**After:**
```php
use ByJG\Scriptify\Scriptify;
use ByJG\Scriptify\ScriptifyException;
use ByJG\Scriptify\Caller;
use ByJG\Scriptify\Runner;
```

### 3. Update Binary Script References

If you have shell scripts or documentation referencing the binary:

**Before:**
```bash
vendor/bin/daemonize call ...
```

**After:**
```bash
vendor/bin/scriptify call ...
```

### 4. Update PHP Version Requirement

Ensure your environment meets the new PHP version requirement:

```bash
# Verify PHP version
php -v

# Must be PHP 8.3 or higher (up to but not including 8.6)
```

### 5. Update Custom Templates (if applicable)

If you have custom service templates:
- Rename template files from `.tpl` to `.jinja` extension
- Update template syntax to use Jinja2 format
- Templates now use `byjg/jinja-php` for rendering

### 6. Verify Extension Requirements

Ensure the `readline` extension is installed:

```bash
php -m | grep readline
```

If not installed, install it based on your system:

```bash
# Debian/Ubuntu
sudo apt-get install php-readline

# macOS (usually included)
# Windows: enable in php.ini
```

### 7. Test Your Installation

After migration, run the following tests:

```bash
# Test basic functionality
vendor/bin/scriptify --version

# Run your existing service installations
vendor/bin/scriptify services

# If you have tests, run them
vendor/bin/phpunit
```

### 8. Update Installed Services

If you previously installed services using version 5.x:

```bash
# Uninstall old services (using the old binary if still available)
vendor/bin/daemonize uninstall <service-name>

# Or manually remove service files from:
# - /etc/systemd/system/
# - /etc/init.d/
# - /etc/cron.d/
# - /etc/init/ (upstart)

# Reinstall using the new binary
vendor/bin/scriptify install <service-type> <class> <method> <service-name>
```

## Configuration Changes

### Minimum Requirements
- PHP: 8.3+ (previously 8.1+)
- New extension: `ext-readline` (required)
- New dependency: `byjg/jinja-php` ^6.0

### Development Requirements
- PHPUnit: ^10.5|^11.5 (previously ^9.6)
- Psalm: ^5.9|^6.13 (previously ^5.9)
- RestServer: ^6.0 (previously ^5.0)

## Notes

- The project has been completely rebranded to better reflect its expanded capabilities
- All existing functionality from 5.x is preserved under the new names
- Service templates have been modernized but generate functionally equivalent services
- The migration is primarily a renaming exercise with minimal code changes required
- GitHub Actions and CI/CD workflows have been updated for compatibility

## Migration Support

If you encounter issues during migration:

1. Check that all namespace references are updated
2. Verify PHP version compatibility
3. Ensure all required extensions are installed
4. Review custom code for deprecated patterns
5. Consult the updated documentation at the repository

For issues or questions, please file an issue on the GitHub repository.
