<?php

/**&
  @module_content
    uuid: 3ffb56ac-4362-4bd1-bbc8-99a47c88a8c3
    name: Core Settings Module
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
class SettingsModule extends PlainGroupModule implements UserModuleInterface
{
    const MOD_UUID = '6859a9c9-132d-447f-8b4f-4484064e877a';
    const MOD_NAME = 'Settings';
    const MOD_PARENT = null;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return [
            'status' => 0,
            'path' => $args->pathCurrent(),
        ];
    }
}

ModIndex::addModule(SettingsModule::class);
