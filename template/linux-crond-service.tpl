# /etc/cron.d/#SVCNAME#: DO NOT REMOVE OR CHANGE THIS LINE - PHP_SCRIPTIFY

SHELL=/bin/bash

* * * * *     root     #ENVCMDLINE# #PHPPATH# #SCRIPTIFYSERVICE# run "#CLASS#" --bootstrap "#BOOTSTRAP#" --rootdir "#ROOTPATH#" #CONSOLEARGS# 

