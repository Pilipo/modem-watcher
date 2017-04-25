<?php
require "src/ModemWatcher.php";

$watcher = new \Pilipo\HomeTools\ModemWatcher();
$watcher->signal;
$watcher->status;
