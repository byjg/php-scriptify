# DO NOT REMOVE OR CHANGE THIS LINE - PHP_SCRIPTIFY #
[Unit]
Description=#DESCRIPTION#

[Service]
Type=simple
EnvironmentFile=#ENVIRONMENT#
ExecStart=#PHPPATH# #SCRIPTIFYSERVICE# run "#CLASS#" --bootstrap "#BOOTSTRAP#" --rootdir "#ROOTPATH#" #CONSOLEARGS# --daemon
Nice=5

[Install]
WantedBy=multi-user.target
