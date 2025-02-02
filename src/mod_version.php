<?php

/**&
  @module_content
    uuid: b397d6d1-68bb-4e8c-88ce-d787f43f117c
    name: Current version
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
&*/
class Version implements MachineModuleInterface, AlterModuleInterface
{
    const MOD_UUID = '822f76e6-053f-42a7-971e-2355aef362b9';
    const MOD_NAME = 'Version';
    const MOD_PARENT = null;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return [
            'version' => VERSION,
            'ver_major' => VER_MAJOR,
            'ver_minor' => VER_MINOR,
            'ver_patch' => VER_PATCH,
        ];
    }

    public static function preDisplay(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        return 'displayArray';
    }

    public static function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/ {
        return $curr_args;
    }
}

ModIndex::addModule(Version::class);
