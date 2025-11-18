<?php
// Example preload file for Scriptify terminal
// This file will be executed before the interactive terminal starts

// Define commonly used namespace imports
use DateTime;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;

// You can also define helper variables
$today = new DateTime();

// Or define helper functions
function dd($var) {
    var_dump($var);
    die();
}

function dump($var) {
    var_dump($var);
}
