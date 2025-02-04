<?php

if ($argc != 2) {
    echo "Invalid target file\n";
    exit(1);
}

require('src/mod_microcore.php');
require('src/mod_infuser.php');
require('src/mod_rt_config.php');

$args = new ArgsStore();
$rtcfg = new RuntimeConfig($args);
$modules = require_once('src/specific/cfm/1.0.0/runtime_mods.php');

function loadMods(ArgsStore $args, array $modulesList)/* array*/
{
    $result = [];
    foreach ($modulesList as $path => $list) {
        foreach (Infuser::defuse($args, $path) as $id => $mod) {
            if (array_key_exists($id, $result))
                throw new Exception('Id collission: ' . $id);

            if (!$list || array_key_exists($id, $list))
                $result[$id] = $mod;
        }
    }

    return $result;
}

$mods = loadMods($args, $modules);

Infuser::infuseToFile($argv[1], $mods);
