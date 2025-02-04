<?php

require('src/mod_microcore.php');
require('src/mod_infuser.php');

if ($argc != 2) {
    echo "Invalid target file\n";
    exit(1);
}

if (!is_dir('./defuse/') && !mkdir('./defuse/'))
    throw new Exception('Unable to create module target directory');

$args = new ArgsStore();
Infuser::defuseToFile($args, $argv[1], './defuse/');
