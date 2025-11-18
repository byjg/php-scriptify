---
sidebar_position: 6
---

# Environment Variables

Scriptify supports several environment variables to customize its behavior:

| Variable                 | Description                                                                                              |
|--------------------------|----------------------------------------------------------------------------------------------------------|
| `SCRIPTIFY_MEMORY_LIMIT` | Sets the `memory_limit` for the scriptify process. If not set, no memory limit is applied (unlimited).   |
| `SCRIPTIFY_BOOTLOADER`   | Specifies the path to a custom autoloader file. Used by the `terminal` command and other features.       |

## Usage Examples

### Setting Memory Limit

```bash
# Set memory limit to 256MB
export SCRIPTIFY_MEMORY_LIMIT=256M
scriptify run "\\My\\Class::method"

# Or inline
SCRIPTIFY_MEMORY_LIMIT=512M scriptify run "\\My\\Class::method"
```

### Custom Autoloader Path

```bash
# Use a custom autoloader location
export SCRIPTIFY_BOOTLOADER=/path/to/custom/autoload.php
scriptify terminal
```

## Service Environment Variables

When you install a service using `scriptify install`, you can pass environment variables that will be available to your service:

```bash
scriptify install --template=systemd myservice \
    --class "\\My\\Class::method" \
    --env APP_ENV=production \
    --env DATABASE_URL=mysql://localhost/db
```

These variables are stored in `/etc/scriptify/{service}.env` and automatically loaded when the service runs.

### Accessing Service Variables in Terminal

Load a service's environment variables in the interactive terminal:

```bash
scriptify terminal myservice
```

This makes all the service's environment variables available in your interactive session.
