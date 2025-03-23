<?php

if ($argc != 2) {
    echo "Invalid arguments count\n";
    exit(1);
}

require('src/mod_microcore.php');
require('src/mod_infuser.php');
require('src/mod_rt_config.php');

$targetProject = $argv[1];
$targetSubdir = 'src/specific/' . $targetProject;
$targetFile = $targetProject . '.php';

if (!file_exists($targetSubdir)) {
    echo "Unknown project\n";
    exit(1);
}

$args = new ArgsStore();
$rtcfg = new RuntimeConfig($args);
$modules = require_once($targetSubdir . '/runtime_mods.php');

function loadMods(ArgsStore $args, array $modulesList)/*: array*/
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

Infuser::infuseToFile(
    $targetFile,
    loadMods($args, $modules)
);
