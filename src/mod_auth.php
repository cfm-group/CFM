<?php

/**&
  @module_content
    uuid: a11190f6-fee2-4a5b-9687-8edd9c3e1b5b
    name: Complex Auth module
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
class CoreAuthenticationModule implements
    UserModuleInterface,
    MiddlewareInterface,
    ConfigProviderInterface
{
    use FormTools;

    const MOD_UUID = '21bd5bf0-1bf9-4d87-bce1-734d5426b7fb';
    const MOD_NAME = 'Core Authentication module';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'ca_login' => [
            't_str',
            'c_not_empty' => [],
            'o_field_schema' => [
                'placeholder' => 'login'
            ],
        ],
        'ca_password' => [
            't_str',
            'c_not_empty' => [],
            'o_field_schema' => [
                'type' => 'password',
                'placeholder' => 'password',
            ],
            'o_prevent_export' => ['preserve', 'display'],
        ],
    ];
    public static $HASH_ALGO = 'sha256';
    public static $SECRET = 'zc59FaCRgqkvLX8KkfOrfo0iwXGCMJro';
    public static $C_EPOCH_DT = 3600; // Current epoch
    public static $P_EPOCH_DT = 604800; // Persistant epoch
    public static $USER_TEMPLATE = [
        'pwd_hash' => ''
    ];

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        if (!array_key_exists('current_user', $_COOKIE)
            || !array_key_exists('key_nonce', $_COOKIE)
        ) {
            $args->getStack()->resetFromUUID(static::MOD_UUID);

            return false;
        }

        $username = $_COOKIE['current_user'];
        $currentAuthorized = false;
        $currentKey = static::createCurrentKey(
            $username,
            $_COOKIE['key_nonce']
        );
        if (array_key_exists('current_key', $_COOKIE))
            $currentAuthorized = static::hashCompare(
                $currentKey,
                $_COOKIE['current_key']
            );

        $persistantAuthorized = false;
        $persistantKey = static::createPersistantKey(
            $username,
            $_COOKIE['key_nonce']
        );
        if (array_key_exists('persistant_key', $_COOKIE))
            $persistantAuthorized = static::hashCompare(
                $persistantKey,
                $_COOKIE['persistant_key']
            );

        if (!($currentAuthorized || $persistantAuthorized)) {
            $args->getStack()->resetFromUUID(static::MOD_UUID);

            return false;
        }

        if (!$currentAuthorized)
            setcookie(
                'current_key',
                $currentKey,
                time() + static::$C_EPOCH_DT
            );

        if (!$persistantAuthorized)
            setcookie(
                'persistant_key',
                $persistantKey,
                time() + static::$P_EPOCH_DT
            );

        $args->cfgVSet(static::class, 'current_user', $username);

        return true;
    }

    public static function hashCompare(/*string*/ $a, /*string*/ $b)
    {
        $len = strlen($a);
        if ($len !== strlen($b))
            return false;

        $status = 0;
        for ($i = 0; $i < $len; $i++)
            $status |= ord($a[$i]) ^ ord($b[$i]);

        return $status === 0;
    }

    public static function createKey(
        /*string*/ $username,
        /*string*/ $nonce,
        /*int*/ $epochTime
    )/*: string*/ {
        return hash_hmac(
            static::$HASH_ALGO,
            $username . '_' . $nonce,
            static::$SECRET . '_' . floor(time() / $epochTime)
        );
    }

    public static function createCurrentKey(
        /*string*/ $username,
        /*string*/ $nonce
    )/*: string*/ {
        return static::createKey($username, $nonce, static::$C_EPOCH_DT);
    }

    public static function createPersistantKey(
        /*string*/ $username,
        /*string*/ $nonce
    )/*: string*/ {
        return static::createKey($username, $nonce, static::$P_EPOCH_DT);
    }

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $login = static::valueGet($args, 'ca_login');
        $password = static::valueGet($args, 'ca_password');

        if (!$login || !$password)
            return;

        $users = $args->cfgVGet(static::class, 'users', []);
        if (!array_key_exists($login, $users))
            return ['status' => -1, 'msg' => 'Invalid login/password'];

        $current = $users[$login];
        if (!password_verify($password, $current['pwd_hash']))
            return ['status' => -1, 'msg' => 'Invalid login/password'];

        $nonce = dechex(mt_rand(0, PHP_INT_MAX));
        $currentKey = static::createCurrentKey($login, $nonce);
        $persistantKey = static::createPersistantKey($login, $nonce);

        return [
            'status' => 0,
            'login' => $login,
            'key_nonce' => $nonce,
            'current_key' => $currentKey,
            'persistant_key' => $persistantKey,
        ];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            '<border class="stack">'
                . static::fieldGet($args, 'ca_login')
                . static::fieldGet($args, 'ca_password')
                . '<input type="submit" value="Log in">'
            . '</border>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);

        $pTime = time() + static::$P_EPOCH_DT;
        $cTime = time() + static::$C_EPOCH_DT;
        setcookie('current_user', $curr_args['login'], $pTime, '/');
        setcookie('key_nonce', $curr_args['key_nonce'], $pTime, '/');
        setcookie('current_key', $curr_args['current_key'], $cTime, '/');
        setcookie('persistant_key', $curr_args['persistant_key'], $pTime, '/');
        header('Refresh: 0');

        return '<border>Successfull authorization. Reload the page</border>';
    }

    public static function currentUser(ArgsStore $args)/*: array*/
    {
        return $args->cfgVGet(static::class, 'current_user');
    }

    public static function listUsers(ArgsStore $args)/*: array*/
    {
        return array_keys(
            $args->cfgVGet(static::class, 'users')
        );
    }

    public static function addUser(
        ArgsStore $args,
        /*string*/ $username,
        /*string*/ $password
    )/*: array*/ {
        $users = $args->cfgVGet(static::class, 'users');
        if (array_key_exists($username, $users))
            return ['status' => -1, 'msg' => 'User already exists'];

        $user = static::$USER_TEMPLATE;
        $user['pwd_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $users[$username] = $user;

        if (!$args->cfgVSet(static::class, 'users', $users, true))
            return ['status' => -2, 'msg' => 'Unable to add new user'];

        return [
            'status' => 0,
            'user' => $user,
        ];
    }

    public static function delUser(
        ArgsStore $args,
        /*string*/ $username
    )/*: array*/ {
        $users = $args->cfgVGet(static::class, 'users');
        if (!array_key_exists($username, $users))
            return ['status' => -1, 'msg' => 'Unknown user'];

        unset($users[$username]);
        
        if (!$args->cfgVSet(static::class, 'users', $users, true))
            return ['status' => -2, 'msg' => 'Unable to remove user'];

        return ['status' => 0];
    }

    public static function configGet()/*: array*/
    {
        return [
            'current_user' => null,
            'users' => [
                'admin' => [
                    'pwd_hash' => '$2y$10$bZYpKrvScznAVgjTISqxTedA/w9cy1iX2NMVPgAWHsTfZFAhrT0s2',
                ],
            ],
        ];
    }
}

class CoreAuthorizationModule implements
    BasicModuleInterface,
    MiddlewareInterface
{
    const MOD_UUID = '29794662-5ec3-4f90-95ed-3f2d27eaa0e1';
    const MOD_NAME = 'Core Authorization module';
    const MOD_PARENT = null;

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
    }

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
    }
}

class CoreLogoutModule implements
    UserModuleInterface,
    GadgetModuleInterface
{
    use FormTools;

    const MOD_UUID = '9a43cb83-a844-4dd8-88ff-b24c9dad0421';
    const MOD_NAME = 'Core Logout module';
    const MOD_PARENT = null;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return [
            'status' => 0,
            'keys' => [
                'current_user',
                'key_nonce',
                'current_key',
                'persistant_key'
            ],
        ];
    }

    public static function gadget(ArgsStore $args)/*: ?string*/
    {
        return static::submitGet('Logout');
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);

        foreach ($curr_args['keys'] as $key) {
            setcookie($key, '', 0, '/');
        }
        header('Refresh: 0');

        return '<border>Successfull logout. Reload the page</border>';
    }
}

ModIndex::addModule(CoreAuthenticationModule::class);
ModIndex::addModule(CoreLogoutModule::class, false, [Main::MOD_UUID]);
RuntimeConfig::defaultAdd(CoreAuthenticationModule::class);
