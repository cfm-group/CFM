<?php

/**&
  @module_content
    uuid: 78ba657f-42cd-48ca-a710-62b6a2e06080
    id: fused_config
    name: CFM's Fused Config
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
&*/
define("CODENAME", "Crystal File Manager");
define("VER_MAJOR", 1);
define("VER_MINOR", 0);
define("VER_PATCH", 0);
define("FIRST_YEAR", 2024);
define("LAST_YEAR", 2025);
define(
    "VERSION",
    (VER_MAJOR || VER_MINOR || VER_PATCH
        ? VER_MAJOR . '.' . VER_MINOR . '.' . VER_PATCH
        : 'Canary-' . dechex(filemtime(__FILE__))
    )
);
ini_set('display_errors', 0);

MwChains::$GLOBAL_CHAIN = [
    [MwChains::class, [
        'chain' => [
            [RuntimeConfig::class, [
                CFMMain::class => [
                    'show_menu' => false,
                    'show_cred' => false,
                ],
            ]],
            [SetupModule::class, []],
            [CoreAuthenticationModule::class, []],
            [RuntimeConfig::class, [
                CFMMain::class => [
                    'show_menu' => true,
                    'show_cred' => true,
                ],
            ]],
            [SetUserStackModule::class, [
                'alias' => [
                    'view' => FileViewModule::MOD_UUID,
                    'download' => FileDownloadModule::MOD_UUID,
                    'delete' => FileViewDeleteModule::MOD_UUID,
                ],
                'default' => TreeViewModule::MOD_UUID,
            ]],
        ],
        'resolver' => [MwChains::class, 'resolveTrue'],
    ]],
    [MwChains::class, [
        'chain' => [
            [FormProtectModule::class, []],
            [ProcessRqMw::class, []],
        ],
        'resolver' => [MwChains::class, 'resolveTrue'],
    ]],
    [IntAlterDisplayMw::class, []],
    [ExtAlterDisplayMw::class, ['type' => 'json', 'key' => 'json']],
    [ExtAlterDisplayMw::class, ['type' => 'raw', 'key' => 'raw']],
];
MwChains::$CHAINS = [
    'default' => [
        [SetRLMMw::class, ['rlm_uuid' => CFMMain::MOD_UUID]],
        [UserDisplayMw::class, []],
    ],
];

ModIndex::addParent(CoreLogoutModule::class, CFMMain::MOD_UUID);
ModIndex::addParent(FormProtectModule::class, CFMMain::MOD_UUID);

ModIndex::makeTLM(TreeViewModule::class);
ModIndex::makeTLM(SettingsModule::class);

UserSettingsGroup::$AUTH_CLS = CoreAuthenticationModule::class;
