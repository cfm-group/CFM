<?php

/**&
  @module_content
    uuid: a414c192-5364-47d2-a94f-de6db9a90a7e
    name: Core Authentication Settings
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
class SetListUserModule implements UserModuleInterface
{
    use FormTools;

    const MOD_UUID = '46894ca5-aa62-4436-9c1a-f11b4837b955';
    const MOD_NAME = 'Registred Users';
    const MOD_PARENT = UserSettingsGroup::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return [];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        $result = '';
        $cls = UserSettingsGroup::getAuthenticationClass();
        foreach ($cls::listUsers($args) as $username) {
            $result .= '<li>' . static::escapeData($username) . '</li>';
        }
            
        return '<ul>' . $result . '</ul>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
    }
}

class SetupFirstUserModule implements UserModuleInterface
{
    use FormTools;

    const MOD_UUID = 'a9d79596-45ba-4773-9b1f-c1b7ede28018';
    const MOD_NAME = 'Create first user';
    const MOD_PARENT = SetupGroup::MOD_UUID;

    public static $FORM_FIELDS = [
        'new_username' => [
            't_str',
            'c_not_empty' => [],
            'c_domain' => ['from' => 'post'],
            'c_limit' => [
                'mode' => 'len',
                'minq' => 4,
                'maxq' => 12,
            ],
            'o_field_schema' => [
                'type' => 'text',
                'placeholder' => 'username',
                'attrs' => [
                    'autocomplete' => 'off',
                    'minlength' => 4,
                    'maxlength' => 12,
                ],
            ],
        ],
        'new_password' => [
            't_str',
            'c_not_empty' => [],
            'c_domain' => ['from' => 'post'],
            'c_limit' => [
                'mode' => 'len',
                'minq' => 8,
                'maxq' => 64,
            ],
            'o_field_schema' => [
                'type' => 'password',
                'placeholder' => 'password',
                'attrs' => [
                    'minlength' => 8,
                    'maxlength' => 64,
                ],
            ],
            'o_prevent_export' => ['display', 'preserve'],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $username = static::valueGet($args, 'new_username');
        $password = static::valueGet($args, 'new_password');
        if (!$username || !$password)
            return [
                'status' => -1,
                'msg' => 'Username or password does not meet the requirements'
            ];

        $cls = UserSettingsGroup::getAuthenticationClass();
        $result = $cls::addUser($args, $username, $password);
        if ($result['status'] < 0)
            return ['status' => -2, 'msg' => $result['msg']];

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            static::fieldGet($args, 'new_username')
            .static::fieldGet($args, 'new_password');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
    }
}

class SetAddUserModule extends SetupFirstUserModule implements UserModuleInterface
{
    const MOD_UUID = 'c4cee578-fdde-43ed-aa39-d3707619f3d9';
    const MOD_NAME = 'Add new user';
    const MOD_PARENT = UserSettingsGroup::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        $result = parent::process($args, $prnt_args);
        if ($result['status'] !== 0)
            return $result;

        $result = $args->cfgSave();
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => 'Unable to add new user'];

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            parent::form($args)
            . static::submitGet('Create');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);

        return 'New user successfully created';
    }
}

class SetRemoveUserModule implements UserModuleInterface
{
    use FormTools;

    const MOD_UUID = '354bf30d-f16e-4175-8db6-0a4bf9984652';
    const MOD_NAME = 'Remove user';
    const MOD_PARENT = UserSettingsGroup::MOD_UUID;

    public static $FORM_FIELDS = [
        'del_username' => [
            't_str',
            'c_not_empty' => [],
            'c_domain' => ['from' => 'post'],
            'c_limit' => [
                'mode' => 'len',
                'minq' => 4,
                'maxq' => 12,
            ],
            'o_field_schema' => [
                'type' => 'text',
                'placeholder' => 'username',
                'attrs' => [
                    'autocomplete' => 'off',
                    'minlength' => 4,
                    'maxlength' => 12,
                ],
            ],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        $username = static::valueGet($args, 'del_username');
        if (!$username)
            return ['status' => -1, 'msg' => 'Invalid username'];

        $cls = UserSettingsGroup::getAuthenticationClass();
        if ($cls::currentUser($args) === $username)
            return ['status' => -2, 'msg' => 'Unable to delete current user'];

        $result = $cls::delUser($args, $username);
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => $result['msg']];

        $result = $args->cfgSave();
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => 'Unable to add new user'];

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            static::fieldGet($args, 'del_username')
            . static::submitGet('Remove');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);

        return 'User successfully removed';
    }
}

class UserSettingsGroup extends GroupModule implements UserModuleInterface
{
    const MOD_UUID = '56861e1a-845b-4ac6-90a7-5e712ab946ac';
    const MOD_NAME = 'User settings';
    const MOD_PARENT = SettingsModule::MOD_UUID;

    public static $AUTH_CLS = null;

    public static function getAuthenticationClass()/*: string*/
    {
        if (is_null(static::$AUTH_CLS))
            throw new Exception('Authorization class is doesn\'t set');

        return static::$AUTH_CLS;
    }
}

ModIndex::addModule(UserSettingsGroup::class);
ModIndex::addModule(SetListUserModule::class);
ModIndex::addModule(SetAddUserModule::class);
ModIndex::addModule(SetRemoveUserModule::class);
ModIndex::addModule(SetupFirstUserModule::class);

ModIndex::addParent(SetAddUserModule::class, FormProtectModule::MOD_UUID);
ModIndex::addParent(SetRemoveUserModule::class, FormProtectModule::MOD_UUID);
