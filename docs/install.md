---
sidebar_position: 4
---

# Install a PHP class/method as a service

This option allows you to create a system service from any PHP class. 

## Test the class/method call from the command line
First, you need to test how ro call the method from the command line:

```bash
scriptify run \
    "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --rootdir "/path/to/root" \
    --arg "value1" \
    --arg "value2"
```

## Create the daemon process
If everything is ok, now you can "scriptify" this class (as root):

```php
scriptify install --template=systemd mydaemon \
    --class "\\Some\\Name\\Space\\MyExistingClass::someExistingMethod" \
    --rootdir "/path/to/root" \
    --arg "value1" \
    --arg "value2"
```

The service runs the whole command, written out with every option resolved: the PHP
binary, `scriptify run`, the class, `--bootstrap` (`vendor/autoload.php` when not given; a
bootstrap that returns a container works as in `run`), the absolute `--rootdir` and the arguments. Nothing depends on a default
at run time. The service also runs in the root directory.

To install a command of your own application (`./app payment:sweep`), see
[Launcher](launcher.md#install-a-command-as-a-service).

*note*: valid templates are:

- systemd (default)
- upstart
- initd
- crond


## Manage the daemon process

List all "scriptifyd" php classes:

```php
scriptify services --only-names
```

Start or stop the linux services:

```bash
sudo service mydaemon start  # or stop, status or restart
```



## Uninstalling

For uninstallation type:

```bash
scriptify uninstall mydamon
```
