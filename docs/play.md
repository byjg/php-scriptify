---
sidebar_position: 7
---

# Play with the service

Try out Scriptify with a pre-installed demo service.

## Demo Setup

Open two terminals.

In the first terminal, watch the output file:

```bash
touch /tmp/tryme.txt
tail -f /tmp/tryme.txt
```

In the second terminal, install and start the demo service:

```bash
sudo scriptify install --template=systemd tryme \
    --class "\\ByJG\\Scriptify\\Sample\\TryMe::process" \
    --bootstrap "vendor/autoload.php" \
    --rootdir "./"

sudo systemctl start tryme
```

If everything is working correctly, you'll see lines being added to the first terminal continuously.

## Managing the Demo Service

Stop the service:

```bash
sudo systemctl stop tryme
```

Check service status:

```bash
sudo systemctl status tryme
```

Remove the service:

```bash
sudo scriptify uninstall tryme
```

## What's Happening?

The `TryMe::process` method writes timestamps to `/tmp/tryme.txt` each time it runs. When installed as a systemd service with the `--daemon` flag, it runs continuously in the background.
