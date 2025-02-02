<?php

require('mod_microcore.php');
require('mod_infuser.php');

if ($argc != 2) {
    echo "Invalid target file\n";
    exit(1);
}

$args = new ArgsStore();
Infuser::defuseToFile($args, $argv[1], './defuse/');
