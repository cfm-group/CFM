<?php

/**&
  @module_content
    uuid: 3bb19b4f-c49b-46cf-9fb0-09e3bc68c73f
    name: CSRF Form protection
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
class FormProtectModule implements
    UserModuleInterface,
    MiddlewareInterface,
    GadgetModuleInterface,
    ConfigProviderInterface
{
    use FormTools;
    use SecurityTools;

    const MOD_UUID = '8f26af5a-2a93-40fa-9309-54fccaeb0635';
    const MOD_NAME = 'CSRF Form protection';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'form_key' => [
            't_str',
            'c_not_empty' => [],
            'c_domain' => ['from' => 'post'],
            'c_limit' => [
                'mode' => 'len',
                'eq' => 64,
            ],
            'o_field_schema' => [
                'type' => 'hidden',
            ],
            'o_prevent_export' => ['display', 'preserve'],
        ],
    ];
    public static $HASH_ALGO = 'sha256';

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        $cookieExists = array_key_exists('action_nonce', $_COOKIE);
        if (!$cookieExists) {
            $nonce = static::getRandBytes(8);
            setcookie('action_nonce', $nonce, 0, '/');
        } else {
            $nonce = $_COOKIE['action_nonce'];
        }

        $secret = $args->cfgVGet(static::class, 'secret');
        $currentKey = hash_hmac(static::$HASH_ALGO, $nonce, $secret);
        $args->cfgVSet(static::class, 'current_key', $currentKey);

        if (!array_key_exists(static::MOD_UUID, ModIndex::$PL))
            return true;

        $uuid = $args->getStack()->targetUUID();
        if (!in_array($uuid, ModIndex::$PL[static::MOD_UUID]))
            return true;

        if (!$cookieExists) {
            $args->getStack()->resetFromUUID(static::MOD_UUID);
            return false;
        }

        $passedKey = static::valueGet($args, 'form_key', '');
        if (!static::hashCompare($currentKey, $passedKey)) {
            $args->getStack()->resetFromUUID(static::MOD_UUID);
            return false;
        }

        $nonce = static::getRandBytes(8);
        $currentKey = hash_hmac(static::$HASH_ALGO, $nonce, $secret);

        setcookie('action_nonce', $nonce, 0, '/');
        $args->cfgVSet(static::class, 'current_key', $currentKey);

        return true;
    }

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return ['status' => 0];
    }

    public static function gadget(ArgsStore $args)/*: ?string*/
    {
        $key = $args->cfgVGet(static::class, 'current_key');

        return static::fieldGet($args, 'form_key', [], ['{value}' => $key]);
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            '<border style="text-align: center;">'
                . 'Form is outdated. Reload the page'
            . '</border>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
    }

    public static function configGet()/*: array*/
    {
        return [
            'current_key' => null,
            'secret' => null,
        ];
    }
}

class FormProtectSetupModule implements BasicModuleInterface
{
    use SecurityTools;

    const MOD_UUID = '8793ca41-4049-445c-a57b-625583b3f47a';
    const MOD_NAME = 'Form token secret Setup';
    const MOD_PARENT = SetupGroup::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $args->cfgVSet(
            FormProtectModule::class,
            'secret',
            static::getRandBytes(32),
            true
        );

        return ['status' => 0];
    }
}

ModIndex::addModule(FormProtectModule::class);
ModIndex::addModule(FormProtectSetupModule::class);
RuntimeConfig::addDefault(FormProtectModule::class);
