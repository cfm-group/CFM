<?php

/**&
  @module_content
    uuid: c271e92a-e358-4580-bd26-828f36b83a4d
    id: main
    name: CFM's main RLM
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
class CFMMain implements UserModuleInterface, ConfigProviderInterface
{
    use FormTools;

    const MOD_UUID = 'e901ceb9-9853-4999-aa4c-45b58c50c19f';
    const MOD_NAME = 'CFM Main';
    const MOD_PARENT = null;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return [];
    }

    public static function formDump(ArgsStore $args)/*: ?string*/
    {
        $result = '';
        $data = ModIndex::getCurrentValues($args);
        foreach(static::escapeData($data) as $key => $value) {
            $result .=
                '<input '
                    . 'type="hidden" '
                    . 'name="' . $key . '" '
                    . 'value="' . $value . '" '
                . '>';
        }

        return $result;
    }

    public static function gadget(ArgsStore $args)/*: ?string*/
    {
        $result = '';
        foreach (ModIndex::getParents(static::MOD_UUID) as $uuid => $cls) {
            if (!is_subclass_of($cls, GadgetModuleInterface::class, true)) 
                continue;

            $result .= $cls::gadget($args);
        }

        return $result;
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        $tlm = null;
        $stack = $args->getStack();
        if ($stack->isRunable())
            $tlm = $stack->currentTLM();

        $result =
            '<border id="menu" class="menu">'
                . static::formDump($args);
        foreach (ModIndex::$TLM as $uuid => $cls) {
            $env_compat = true;
            if (is_subclass_of($cls, EnvCheckInterface::class, true))
                list($env_compat, $env_reason) = $cls::checkEnv();

            $result .=
                '<input '
                    . 'type="radio" '
                    . 'name="proc" '
                    . 'id="' . $uuid . '" '
                    . 'value="' . $uuid . '" '
                    . (!$env_compat ? 'disabled="" ' : '')
                    . ($tlm === $uuid ? 'checked="" ' : '')
                . '>'
                . '<label '
                    . 'for="' . $uuid . '" '
                    . (!$env_compat ? 'title="' . $env_reason . '" ' : '')
                . '>'
                    . $cls::MOD_NAME
                . '</label>';
        }

        return $result
                . '<input type="submit" value="Go">'
                . static::gadget($args)
            . '</border>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        echo
        '<!DOCTYPE html>'
        . '<html>'
        . '<head>'
            . '<title>'
                . $args->cfgVGet(
                    static::class,
                    'title',
                    (defined('CODENAME') ? CODENAME : '')
                )
            . '</title>'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<style>'
                . '.result {'
                    . 'border-color: orange;'
                . '}'
                . '.menu {'
                    . 'display: flex;'
                    . 'justify-content: center;'
                    . 'padding: 1px;'
                . '}'
                . '.menu > label {'
                    . 'padding: 4px;'
                    . 'margin: 4px;'
                . '}'
                . '.menu > input[type=radio] {'
                    . 'display: none;'
                . '}'
                . '.content {'
                    . 'max-width: 40rem;'
                    . 'display: block;'
                    . 'margin-left: auto;'
                    . 'margin-right: auto;'
                . '}'
                . 'input[type=radio] + label {'
                    . 'border: solid 1px lightgray;'
                    . 'border-radius: 4px;'
                . '}'
                . 'input[type=radio]:checked + label {'
                    . 'background-color: orange;'
                    . 'transition-duration: 0.1s;'
                    . 'border: solid 1px transparent;'
                    . 'border-radius: 4px;'
                . '}'
                // . 'border > * {'
                //     . 'display: inline-block;'
                // . '}'
                . 'border > .stack {'
                    . 'vertical-align: top;'
                . '}'
                . 'border,'
                . '.border,'
                . 'fieldset {'
                    . 'border: solid 1px lightgray;'
                    . 'border-radius: 4px;'
                    . 'padding: 6px;'
                    . 'margin: 4px;'
                . '}'
                . 'lpre {'
                    . 'display: block;'
                    . 'font-family: monospace;'
                    . 'white-space: break-spaces;'
                    . 'word-break: break-word;'
                    . 'margin-top: 1rem;'
                    . 'margin-bottom: 1rem;'
                . '}'
                . 'mpre {'
                    // . 'display: block;'
                    . 'display: inline-block;'
                    . 'font-family: monospace;'
                    . 'white-space: break-spaces;'
                    . 'word-break: break-word;'
                    . 'vertical-align: sub;'
                . '}'
                . 'input, select, button {'
                    . 'border: solid 1px lightgray;'
                    . 'border-radius: 4px;'
                    . 'padding: 4px;'
                    . 'margin: 4px;'
                    . 'vertical-align: middle;'
                . '}'
                . '.no_border {'
                    . 'border: none;'
                    . 'padding: 0;'
                    . 'margin: 0;'
                . '}'
                . '.stack > input,'
                . '.stack > select,'
                . '.stack > button {'
                    . 'display: block;'
                    . 'margin-left: auto;'
                    . 'margin-right: auto;'
                    . 'padding: 4px;'
                . '}'
                . 'table {'
                    . 'display: table;'
                    . 'width: 100%;'
                    . 'border-collapse: collapse;'
                    . 'border: 1px solid lightgray;'
                . '}'
                . 'table td,'
                . 'table th {'
                    . 'border-bottom: 1px solid lightgray;'
                    . 'padding: 4px;'
                    . 'padding-top: 8px;'
                    . 'padding-bottom: 8px;'
                    . 'text-align: center;'
                    . 'width: 11rem;'
                . '}'
                . '.stack .label input {'
                    . 'display: inline-block;'
                . '}'
                . 'border {'
                    . 'display: block;'
                . '}'
                . '.label {'
                    . 'display: inline-block;'
                . '}'
                . '.label + .nl_label {'
                    . 'display: block;'
                . '}'
                . 'fieldset[class~=\'result\'] {'
                    . 'border-color: orangered;'
                . '}'
                . '@media (width <= 875px) {'
                    . 'form {'
                        . 'display: block;'
                    . '}'
                    . 'textarea {'
                        . 'width: 100%;'
                        . 'box-sizing: border-box;'
                    . '}'
                . '}'
            . '</style>'
        . '</head>'
        . '<body>'
            . '<form method="POST" enctype="multipart/form-data" id="app_form">'
                . ($args->cfgVGet(static::class, 'show_menu')
                    ? static::form($args)
                    : ''
                )
                . '<div class="content">'
                    . static::mainDisplay($args, [])
                . '</div>'
            . '</form>'
            . (defined('CODENAME') && $args->cfgVGet(static::class, 'show_cred')
                ? '<pre>'
                    . CODENAME . ' ' . VERSION . ' | '
                    . (FIRST_YEAR == LAST_YEAR
                        ? LAST_YEAR
                        : FIRST_YEAR . '-' . LAST_YEAR
                    )
                    . '<br>' . 'PHP ' . PHP_VERSION
                        . '[' . PHP_VERSION_ID . '] ' . '(' . PHP_OS . ')'
                    . '<br>'
                    . $_SERVER['SERVER_SOFTWARE']
                . '</pre>'
                : ''
            )
        . '</body>'
        . '</html>';
    }

    public static function mainDisplay(ArgsStore $args)/*: string*/
    {
        $stack = $args->getStack();
        $stack->rewind();
        if (!$stack->valid())
            return;

        if (!$stack->isRunable())
            return
                '<border>'
                    . '<pre style="color: red">Module is not available</pre>'
                    . '<pre>Reason: ' . $stack->isRunableReason() . '</pre>'
                . '</border>';

        return
            '<border class="no_border">'
                . $stack->form($args)
                . $stack->display($args, [])
            . '</border>';
    }

    public static function configGet()/*: array*/
    {
        return [
            'show_menu' => true,
            'show_cred' => true,
        ];
    }
}

ModIndex::addModule(CFMMain::class);
RuntimeConfig::addDefault(CFMMain::class);
