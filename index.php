<?php
define("asi_allowed_entrypoint", true);

$_ENV["basepath"] = __DIR__;

if (!file_exists(__DIR__."/app/config.standard.php") OR !defined("asi_configuration_loaded")) { //Installation wird gestartet
	include(__DIR__."/app/code/includes/routing.install.php");
	exit(1);
} else {
	include(__DIR__."/app/config.standard.php");
}

include(__DIR__."/app/code/includes/standard.php");
include(__DIR__."/app/code/includes/lastseen.php");
include(__DIR__."/app/code/includes/routing.php");