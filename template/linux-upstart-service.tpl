# DO NOT REMOVE OR CHANGE THIS LINE - PHP_SCRIPTIFY #
description "#DESCRIPTION#"
author      "Scriptify"

# used to be: start on startup
# until we found some mounts weren't ready yet while booting:
start on runlevel [2345]
stop on shutdown

# Automatically Respawn:
respawn
respawn limit 99 5

script
    echo -n $"Starting $NAME: "
    source #ENVIRONMENT#
    #PHPPATH# #SCRIPTIFYSERVICE# run "#CLASS#" --bootstrap "#BOOTSTRAP#" --rootdir "#ROOTPATH#" #CONSOLEARGS# --daemon
end script
