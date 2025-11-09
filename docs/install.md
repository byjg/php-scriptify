---
sidebar_position: 2
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
