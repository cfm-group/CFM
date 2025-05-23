<?php

/**&
  @module_content
    uuid: cbb1d654-396f-49fc-b575-6ca687b7899c
    name: Core Setup
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
&*/
class SetupGroup extends GroupModule implements
    UserModuleInterface,
    ConfigProviderInterface
{
    const MOD_UUID = 'd642a9dc-e298-483b-adc4-849d925b6287';
    const MOD_NAME = 'Setup';
    const MOD_PARENT = null;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($args->cfgVGet(static::class, 'setup_complete'))
            return ['status' => -1, 'msg' => 'Already setted up'];

        return ['status' => 0];
    }

    public static function configGet()/*: array*/
    {
        return [
            'setup_complete' => false,
        ];
    }
}

class SetupModule extends PlainGroupModule implements
    UserModuleInterface,
    MiddlewareInterface
{
    use FormTools;

    const MOD_UUID = 'cb990db7-99ef-4aa6-adee-6e1b3a959f19';
    const MOD_NAME = 'Setup';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'setup_perform' => [
            't_int',
            'c_not_empty' => [],
            'o_field_schema' => [
                'placeholder' => 'sqlite:///var/...',
                'label' => ['DSN (Host)', 'new_line'],
            ],
        ],
    ];

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        if (!$args->cfgVGet(SetupGroup::class, 'setup_complete')) {
            $args->getStack()->resetFromUUID(static::MOD_UUID);
            return false;
        }

        return true;
    }

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if (!static::valueGet($args, 'setup_perform'))
            return ['status' => 1];

        if ($args->cfgVGet(SetupGroup::class, 'setup_complete'))
            return ['status' => -1, 'msg' => 'Already setted up'];

        foreach (ModIndex::getParents(SetupGroup::MOD_UUID) as $uuid => $cls) {
            $result = ModIndex::fastCall($cls, $args);

            if ($result['status'] !== 0)
                return $result;
        }

        $args->cfgVSet(SetupGroup::class, 'setup_complete', true, true);

        $result = $args->cfgSave();
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => 'Unable to complete setup'];

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            SetupGroup::form($args)
            . '<span class="stack">'
                . static::buttonGet('setup_perform', 1, 'Finish setup')
            . '</span>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);
        if ($curr_args['status'] === 1)
            return;

        http_response_code(303);
        header('Location: ');

        return
            '<border>'
                . 'Installation completed successfully. Reload the page'
            . '</border>';
    }
}

ModIndex::addModule(SetupModule::class);
ModIndex::addModule(SetupGroup::class);
RuntimeConfig::addDefault(SetupGroup::class);
