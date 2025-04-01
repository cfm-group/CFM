<?php

/**&
  @module_content
    uuid: add45d38-919f-4da5-b76b-7b924aaefa79
    name: Core module
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (ini_get('display_errors') == 0)
        return true;

    echo
    '<pre>'
        . '<font color="red">Error:</font>'
        . '<br>[No.'
            . intval($errno) . '|'
            . htmlspecialchars($errfile) . ':'
            . intval($errline) . '] '
            . htmlspecialchars($errstr)
    . '</pre>';

    return true;
});

function display_exception(Throwable $ex) {
    echo '<pre><font color="red">Exception:</font><br>' . htmlspecialchars($ex) . '</pre>';
}
set_exception_handler('display_exception');

trait FormTools
{
    /**
     * Field Type Look Up Table
     */
    protected static $FT_LUT = [ 
        't_str' => [self::class, 'fieldTypeStr'],
        't_int' => [self::class, 'fieldTypeInt'],
        't_bool' => [self::class, 'fieldTypeBool'],
        't_file_plain' => [self::class, 'fieldTypeFilePlain'],
        // 't_file_array' => [self::class, 'fieldTypeFileArray'],
    ];
    /**
     * ValueGet Look-Up Table
     *
     * - Spec attributes execute from top to bottom
     * - Prefix definition
     *  = t_ - type
     *  = c_ - check
     *  = d_ - decode
     *  = o_ - other
     */
    public static $VG_LUT = [
        'c_not_empty' => [self::class, 'specElemNotEmpty'],
        /**
         * Spec arguments:
         * - List containing forbidden values
         */
        'c_forbidden' => [self::class, 'specElemForbidden'],
        'c_not_equal' => [self::class, 'specElemForbidden'],
        /**
         * Spec arguments:
         * - List containing allowed values
         */
        'c_allowed' => [self::class, 'specElemAllowed'],
        'c_possbile' => [self::class, 'specElemAllowed'],
        /**
         * Spec arguments:
         * - mode
         *  - len - length of string
         *  - cnt - array elements count
         *  - val - integer value
         * - max - Maximum (<) value length
         * - min - Minimum (>) value length
         * - maxq - Maximum-equal (<=) value length
         * - minq - Minimum-equal (>=) value length
         */
        'c_limit' => [self::class, 'specElemLimit'],
        'c_domain' => [self::class, 'specCheckDomain'],
        'd_json' => [self::class, 'specElemJsonDecode'],
    ];
    /**
     * FieldGet Look-Up Table
     */
    public static $FG_LUT = [ 
        'd_json' => [self::class, 'specElemJsonEncode'],
    ];
    // public static $FORM_FIELDS = [
    //     'example_field' => [
    //         't_bool',
    //         'c_not_empty' => [],
    //         'c_forbidden' => ['forbidden_value'],
    //         'c_allowed' => ['allowed_value'],
    //         'c_limit' => ['mode' => 'len', 'maxq' => 5],
    //         'o_default_value' => 'example default value',
    //         'o_field_schema' => [
    //             'type' => 'checkbox', // Special: textarea, checkbox
    //             'template' => '<textarea name="{name}" {attrs}>{value}</textarea>',
    //             'placeholder' => 'Example value',
    //             'label' => ['Example field', 'new_line'],
    //             'attrs' => [
    //                 'id' => 'example',
    //             ],
    //         ],
    //         'o_prevent_export' => ['preserve', 'display'],
    //     ],
    // ];
    /**
     * - In $INTRINSIC_FIELDS global values are defined
     * - $INTRINSIC_FIELDS are present in all classes that
     *   are using FormFields trait.
     */
    public static $INTRINSIC_FIELDS = [];

    public static function getSpec(
        /*string*/ $key,
        array $fields = []
    )/*: array*/ {
        if (array_key_exists($key, $fields))
            return $fields[$key];
        else if (
            property_exists(static::class, 'FORM_FIELDS')
            && array_key_exists($key, static::$FORM_FIELDS)
        )
            return static::$FORM_FIELDS[$key];
        else if (array_key_exists($key, static::$INTRINSIC_FIELDS))
            return static::$INTRINSIC_FIELDS[$key];

        return null;
    }

    public static function valueGetBySpec(
        array $spec,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $default = null
    )/*: mixed*/ {
        if (!array_key_exists($spec[0], static::$FT_LUT))
            return $default;
        if (is_null($default) && array_key_exists('o_default_value', $spec))
            $default = $spec['o_default_value'];
        if (!isset($args[$key]))
            return $default;

        $result = $default;

        // PHP5 compat
        $call = static::$FT_LUT[$spec[0]];
        if (PHP_VERSION_ID < 50600
            && is_array($call)
            && $call[0] == FormTools::class
        )
            $call[0] = static::class;

        if (!$call($args, $key, $args[$key], $result))
            return $default;

        return static::specProcess($args, static::$VG_LUT, $spec, $key, $result, $default);
    }

    public static function valueGet(
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $default = null
    )/*: mixed*/ {
        $spec = static::getSpec($key);
        if (!$spec)
            return $default;

        return static::valueGetBySpec($spec, $args, $key, $default);
    }

    protected static function specProcess(
        ArgsStore $args,
        array $lut,
        array $spec,
        /*string*/ $key,
        /*mixed*/ $result,
        /*mixed*/ $default = null
    )/*: mixed*/ {
        foreach ($spec as $elem => $elemParams) {
            if (!array_key_exists($elem, $lut))
                continue;

            // PHP5 compat
            $call = $lut[$elem];
            if (PHP_VERSION_ID < 50600
                && is_array($call)
                && $call[0] == FormTools::class
            )
                $call[0] = static::class;

            if (!$call($elemParams, $args, $key, $result, $result))
                return $default;
        }

        return $result;
    }

    protected static function buildAttrs(array $attrs)/*: string*/
    {
        $result = '';
        foreach (static::escapeData($attrs) as $attKey => $attValue) {
            $result .= ' ' . $attKey . '="' . $attValue . '"';
        }

        return $result;
    }

    public static function fieldGetBySpec(
        array $spec,
        ArgsStore $args,
        /*string*/ $key,
        array $attrs = [],
        array $customReplace = []
    )/*: string*/ {
        $schema = [];
        if (array_key_exists('o_field_schema', $spec))
            $schema = $spec['o_field_schema'];

        $type = 'text';
        if (array_key_exists('type', $schema))
            $type = $schema['type'];

        switch ($type) {
            case 'textarea':
                $template = '<textarea name="{name}" {attrs}>{value}</textarea>';
                break;
            default:
                $template = '<input name="{name}" value="{value}" {attrs}>';
                break;
        }
        if (array_key_exists('template', $schema))
            $template = $schema['template'];

        if (array_key_exists('label', $schema))
            $template =
            '<label class="label'
                . (in_array('new_line', $schema['label'])
                    ? ' nl_label'
                    : ''
                )
                . '">'
                . $template
                . $schema['label'][0]
            . '</label>';

        $value = null;
        if (!(
            array_key_exists('o_prevent_export', $spec)
            && in_array('display', $spec['o_prevent_export'])
        ))
            $value = static::valueGetBySpec($spec, $args, $key, null, true);

        if (array_key_exists('attrs', $schema))
            $attrs += $schema['attrs'];

        if ($type == 'checkbox') {
            if (isset($args[$key]))
                $attrs['checked'] = '';
            // :\
            // ArgsStore non-empty check mitigation
            if (is_null($value) || $value === '')
                $value = 1;
        }

        $value = static::specProcess($args, static::$FG_LUT, $spec, $key, $value);
        $value = static::escapeData($value);

        if (!array_key_exists('type', $attrs))
            $attrs['type'] = $type;

        if (
            !array_key_exists('placeholder', $attrs)
            && array_key_exists('placeholder', $schema)
        )
            $attrs['placeholder'] = $schema['placeholder'];

        return strtr(
            $template,
            $customReplace + [
                '{name}' => $key,
                '{value}' => strval($value),
                '{attrs}' => static::buildAttrs($attrs),
            ]
        );
    }

    public static function fieldGet(
        ArgsStore $args,
        /*string*/ $key,
        array $attrs = [],
        array $customReplace = []
    )/*: string*/ {
        $spec = static::getSpec($key);
        if (!$spec)
            return '';

        return static::fieldGetBySpec(
            $spec,
            $args,
            $key,
            $attrs,
            $customReplace
        );
    }

    public static function submitGet(/*string*/ $data)/*: string*/
    {
        return static::buttonGet('proc', static::MOD_UUID, $data);
    }

    public static function buttonGet(
        /*string*/ $name,
        /*mixed*/ $value,
        /*string*/ $data,
        array $attrs = [],
        /*string*/ $type = 'submit'
    )/*: string*/ {
        $attrsRes = '';
        if (count($attrs) > 0)
            $attrsRes = static::buildAttrs($attrs);

        return '<button '
            . 'type="' . $type . '" '
            . ($name != '' ? 'name="' . $name . '" ' : '')
            . ($value != '' ? 'value="' . static::escapeData($value) . '"' : '')
            . $attrsRes
        . '>' . static::escapeData($data) . '</button>';
    }

    protected static function selectGet(
        Iterator $source,
        /*string*/ $name,
        /*?string*/ $select = null,
        /*?string*/ $firstNone = null
    )/*: string*/ {
        $result = '<select name="' . $name . '">';
        if (!is_null($firstNone))
            $result .= '<option value="">' . $firstNone . '</option>';

        foreach($source as $key => $value) {
            $result .=
                '<option '
                    . ($select === $key
                        ? 'selected="" '
                        : ''
                    )
                    . 'value="' . static::escapeData($key) . '"'
                    . '>' . static::escapeData($value) . '</option>';
        }
        $result .= '</select>';

        return $result;
    }

    protected static function radioGet(
        ArgsStore $args,
        /*string*/ $key,
        /*string|int*/ $value
    )/*: string*/ {
        return
            '<input '
            . 'type="radio" '
            . 'name="' . static::escapeData($key) . '" '
            . 'value="' . static::escapeData($value) . '" '
            . (static::valueGet($args, $key) === $value ? 'checked="" ' : '')
            . '>';
    }

    protected static function tableGet(
        Iterator $data,
        /*?Iterator*/ $keys = null,
        /*bool*/ $secure = true
    )/*: string*/ {
        $result = '';

        $result .= '<table>';
        $show_header = true;
        foreach ($data as $row) {
            if ($show_header) {
                $show_header = false;

                $result .= '<thead><tr>';
                foreach ((is_null($keys) ? array_keys($row) : $keys) as $key) {
                    if ($secure)
                        $key = static::escapeData($key);

                    $result .= '<th>' . $key . '</th>';
                }
                $result .= '</tr></thead><tbody>';
            }

            $result .= '<tr>';
            foreach ($row as $value) {
                if ($secure)
                    $value = static::escapeData($value);

                $result .= '<td>' . $value . '</td>';
            }
            $result .= '</tr>';
        }
        $result .= '</tbody>'
            . '</table>';

        return $result;
    }

    public static function errorGet(
        array $data,
        /*string*/ $prefix = '',
        /*bool*/ $decorate = true
    )/*: string*/ {
        $result =
            $prefix
            . '(' . $data['status'] . ') '
            . static::escapeData($data['msg']);
        
        if ($decorate)
            $result = '<border>' . $result . '</border>';

        return $result;
    }

    protected static function escapeData(/*mixed*/ $data)/*: mixed*/
    {
        if (is_string($data))
            return htmlspecialchars($data);
        else if (!is_array($data))
            return $data;

        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($key))
                $key = htmlspecialchars($key);

            if (is_array($value))
                $value = static::escapeData($value);
            else if (is_string($value))
                $value = htmlspecialchars($value);

            $result[$key] = $value;
        }

        return $result;
    }

    protected static function specElemNotEmpty(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        if (empty($value))
            return false;

        $result = $value;

        return true;
    }

    protected static function specElemForbidden(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        if (in_array($value, $elemParams))
            return false;

        $result = $value;

        return true;
    }

    protected static function specElemAllowed(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        if (!in_array($value, $elemParams))
            return false;

        $result = $value;

        return true;
    }

    protected static function specElemLimit(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        if (!array_key_exists('mode', $elemParams))
            return false;

        switch ($elemParams['mode']) {
            case 'len':
                $n = strlen($value);
                break;
            case 'cnt':
                $n = count($value);
                break;
            case 'val':
                $n = intval($value);
                break;
            default:
                return false;
        }

        if (
            array_key_exists('eq', $elemParams) && $n != $elemParams['eq']
            || array_key_exists('max', $elemParams) && $n >= $elemParams['max']
            || array_key_exists('min', $elemParams) && $n <= $elemParams['min']
            || array_key_exists('maxq', $elemParams) && $n > $elemParams['maxq']
            || array_key_exists('minq', $elemParams) && $n < $elemParams['minq']
        )
            return false;

        $result = $value;

        return true;
    }

    protected static function specCheckDomain(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        if (!array_key_exists('from', $elemParams))
            return false;

        $domain = $args->domainFromOffset($key);
        if ($domain !== $elemParams['from'])
            return false;

        $result = $value;

        return true;
    }

    protected static function specElemJsonDecode(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        $depth = 512;
        if (array_key_exists('depth', $elemParams))
            $depth = intval($elemParams['depth']);

        $flags = 0;
        if (array_key_exists('flags', $elemParams))
            $flags = intval($elemParams['flags']);

        $value = json_decode($value, true, $depth, $flags);
        if (empty($value))
            return false;

        $result = $value;

        return true;
    }

    protected static function specElemJsonEncode(
        array $elemParams,
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: bool*/ {
        $depth = 512;
        if (array_key_exists('depth', $elemParams))
            $depth = intval($elemParams['depth']);

        $flags = 0;
        if (array_key_exists('flags', $elemParams))
            $flags = intval($elemParams['flags']);

        if (is_null($value))
            return false;

        $value = json_encode($value, $flags, $depth);
        if ($value === false)
            return false;

        $result = $value;

        return true;
    }

    protected static function fieldTypeStr(
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: mixed*/ {
        if (!is_scalar($value))
            return false;

        $result = @strval($value);

        return true;
    }

    protected static function fieldTypeInt(
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: mixed*/ {
        if (!is_scalar($value))
            return false;

        $result = @intval($value);

        return true;
    }

    protected static function fieldTypeBool(
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: mixed*/ {
        if (!is_scalar($value))
            return false;

        $result = @boolval($value);

        return true;
    }

    protected static function fieldTypeFilePlain(
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: mixed*/ {
        if (!(
            $args->domainFromOffset($key) === 'files'
            && !is_array($args[$key]['error'])
        ))
            return false;

        $result = $value;

        return true;
    }

    protected static function fieldTypeFileArray(
        ArgsStore $args,
        /*string*/ $key,
        /*mixed*/ $value,
        /*mixed*/ &$result
    )/*: mixed*/ {
        return false;
    }
}

trait SecurityTools
{
    protected static function getRandBytes(/*int*/ $n)/*: string*/
    {
        $result = '';
        if (PHP_VERSION_ID < 70000) {
            mt_srand();
            for ($i = 0; $i < $n; $i++)
                $result .= dechex(mt_rand(0, 255));
        } else {
            $result = bin2hex(random_bytes($n));
        }

        return $result;
    }

    public static function hashCompare(/*string*/ $a, /*string*/ $b)/*: bool*/
    {
        $len = strlen($a);
        if ($len !== strlen($b))
            return false;

        $status = 0;
        for ($i = 0; $i < $len; $i++)
            $status |= ord($a[$i]) ^ ord($b[$i]);

        return $status === 0;
    }
}

final class ServiceManager
{
    const SERVICE_STAGE_START = 'start';
    const SERVICE_STAGE_PROCESS = 'process';
    const SERVICE_STAGE_DISPLAY = 'display';
    const SERVICE_STAGE_STOP = 'stop';

    protected /*array*/ $services = [];
    protected /*ArgsStore*/ $args;

    public function __construct(
        ArgsStore $args,
        array $defaultServices = []
    ) {
        $args->callAdd('smStageInvoke', [$this, 'stageInvoke']);
        $args->callAdd('smAddCall', [$this, 'addCall']);

        $this->args = $args;
        foreach ($defaultServices as $id => $class) {
            $this->addClass($class);
        }

        $this->stageInvoke(static::SERVICE_STAGE_START);
    }

    public function __destruct()
    {
        $this->stageInvoke(static::SERVICE_STAGE_STOP);
    }

    public function addClass(/*string*/ $class)/*: bool*/
    {
        if (!is_subclass_of($class, ServiceInterface::class, true))
            return false;

        foreach ($class::$SERVICE_STAGES as $stage => $call) {
            if (array_key_exists($stage, $this->services))
                $this->services[$stage] = [];

            $this->services[$stage][$class] = $call;
        }

        return true;
    }

    public function addCall(/*Closure*/ $call, /*string*/ $stage)/*: void*/
    {
        if (array_key_exists($stage, $this->services))
            $this->services[$stage] = [];

        $this->services[$stage][] = $call;
    }

    public function stageInvoke(/*string*/ $stage)/*: mixed*/
    {
        if (!array_key_exists($stage, $this->services))
            return false;

        foreach ($this->services[$stage] as $id => $call)
            $call($this->args, $stage, $id);

        return true;
    }
}

class ModStack implements Iterator
{
    protected /*array*/ $stack = [];
    protected /*array*/ $procStore = [];
    protected /*array*/ $procState = [];
    protected /*int*/ $size = 0;
    protected /*int*/ $pointer = 0;
    protected /*bool*/ $runable = false;
    protected /*string*/ $runable_reason = '';

    protected /*?string*/ $rlm = null; // Root-Level Module

    public function __construct(
        ArgsStore $args,
        array $stack = [],
        /*string*/ $rlm_uuid = ''
    ) {
        $args->callAdd(
            'getStack',
            function () {
                return $this;
            }
        );
        $args->callAdd(
            'getState',
            function (/*string*/ $uuid, /*mixed*/ $default = null) {
                if (!array_key_exists($uuid, $this->procState))
                    return $default;

                return $this->procState[$uuid];
            }
        );
        $args->callAdd(
            'setState',
            function (/*string*/ $uuid, /*mixed*/ $value) {
                return $this->procState[$uuid] = $value;
            }
        );

        $this->reset($stack);
        $this->setRLM($rlm_uuid);
    }

    public static function fromCls(/*string*/ $cls)/*: array*/
    {
        $stack = new static($stack);
        $tlm_cls = $stack->resetFromCls($cls);

        return [$tlm_cls, $stack];
    }

    public static function fromUUID(/*string*/ $uuid)/*: array*/
    {
        $stack = new static($stack);
        $tlm_cls = $stack->resetFromUUID($uuid);

        return [$tlm_cls, $stack];
    }

    public function resetFromUUID(/*string*/ $uuid)/*: string*/
    {
        if (!array_key_exists($uuid, ModIndex::$TI))
            return;

        $this->resetFromCls(ModIndex::$TI[$uuid]);
    }

    public function resetFromCls(/*string*/ $cls)/*: string*/
    {
        list($tlm_cls, $stack) = ModIndex::getTLM($cls);
        $this->reset($stack);

        return $tlm_cls;
    }

    public function reset(array $stack)/*: void*/
    {
        $this->stack = $stack;
        $this->size = count($stack);
        $this->pointer = $this->size - 1;
        $this->runable = $this->valid();
        $this->runable_reason = '';

        if (!$this->runable) {
            $this->runable_reason = 'Invalid call';
            return;
        }

        foreach ($this as $cls) {
            if (!is_subclass_of($cls, EnvCheckInterface::class, true))
                continue;

            list($this->runable, $this->runable_reason) = $cls::checkEnv();
            if (!$this->runable)
                break;
        }
    }

    protected function restore()/*: bool*/
    {
        $this->rewind();

        return $this->isRunable();
    }

    public function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if (!$this->restore())
            return;

        foreach ($this as $cls) {
            $curr_args = $cls::process($args, $prnt_args);
            assert(!is_array($curr_args), 'Invalid process call result');

            $this->procStore[$this->currentUUID()] = [$curr_args, $prnt_args];
            $prnt_args = $curr_args;
        }

        return $curr_args;
    }

    public function reProcess(
        ArgsStore $args,
        /*string*/ $uuid
    )/*: ?array*/ {
        if (!array_key_exists($uuid, $this->procStore))
            return;

        $cls = ModIndex::$TI[$uuid];
        $prnt_args = $this->procStore[$uuid][1];

        $curr_args = $cls::process($args, $prnt_args);
        $this->procStore[$uuid] = [$curr_args, $prnt_args];

        return $curr_args;
    }

    public function form(ArgsStore $args)/*: string*/
    {
        if (!$this->restore())
            return;
        if ($this->currentUUID() == $this->getRLM())
            return 'RLM execution is forbidden';

        $cls = $this->current();
        if (!is_subclass_of($cls, UserModuleInterface::class, true))
            return 'This module doesn\'t support user interface display';

        return $cls::form($args);
    }

    public function nextDisplay(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = [],
        /*bool*/ &$valid = false
    )/*: ?string*/ {
        if (!$this->valid())
            return;
        if ($this->currentUUID() == $this->getRLM())
            return;

        $cls = $this->current();
        if (!is_subclass_of($cls, UserModuleInterface::class, true))
            return;

        $cls_args = $this->currentArgs($args);
        if (is_null($cls_args))
            return;

        $this->next();
        $valid = $this->valid();

        return $cls::display($args, $cls_args, $curr_args);
    }

    public function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if (!$this->restore())
            return;

        return $this->nextDisplay($args, $curr_args, $prnt_args);
    }

    protected function alterDisplay(
        /*string*/ $display_cls,
        /*string*/ $display_call,
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: mixed*/ {
        if (!$this->restore())
            return;

        $cls = $this->getTarget();
        if (!is_subclass_of($cls, $display_cls, true))
            return;

        $cls_args = $this->targetArgs($args);
        if (is_null($cls_args))
            return;

        return $cls::{$display_call}($args, $cls_args, $curr_args);
    }

    public function preDisplay(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        return static::alterDisplay(
            AlterModuleInterface::class,
            'preDisplay',
            $args,
            $curr_args,
            $prnt_args
        );
    }

    public function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/ {
        return static::alterDisplay(
            MachineModuleInterface::class,
            'displayArray',
            $args,
            $curr_args,
            $prnt_args
        );
    }

    public function displayRaw(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?Generator*/ {
        return static::alterDisplay(
            RawModuleInterface::class,
            'displayRaw',
            $args,
            $curr_args,
            $prnt_args
        );
    }

    public function setRLM(/*string*/ $uuid)/*: bool*/
    {
        if (!array_key_exists($uuid, ModIndex::$TI))
            return false;
        if (!is_subclass_of(
            ModIndex::$TI[$uuid],
            UserModuleInterface::class,
            true
        ))
            return false;

        $this->rlm = $uuid;

        return true;
    }

    public function getRLM()/*: ?string*/
    {
        return $this->rlm;
    }

    public function targetUUID()/*: ?string*/
    {
        if (empty($this->stack))
            return;

        return $this->stack[0];
    }

    public function getTarget()/*: ?string*/
    {
        $uuid = $this->targetUUID();
        if (is_null($uuid))
            return;

        return ModIndex::$TI[$uuid];
    }

    public function targetArgs(ArgsStore $args)/*: ?array*/
    {
        return $this->argsByUUID(
            $args,
            $this->targetUUID()
        );
    }

    public function isRunable()/*: bool*/
    {
        return $this->runable;
    }

    public function isRunableReason()/*: ?string*/
    {
        return $this->runable_reason;
    }

    public function currentArgs(ArgsStore $args)/*: ?array*/
    {
        return $this->argsByUUID(
            $args,
            $this->currentUUID()
        );
    }

    protected function argsByUUID(ArgsStore $args, /*?string*/ $uuid)/*: ?array*/
    {
        if (!array_key_exists($uuid, $this->procStore))
            return;

        return $this->procStore[$uuid][0];
    }

    public function currentTLM()/*: ?string*/
    {
        if ($this->size <= 0)
            return;

        return $this->stack[$this->size - 1];
    }

    #[\ReturnTypeWillChange]
    public function current()/*: mixed*/
    {
        return ModIndex::$TI[$this->currentUUID()];
    }

    public function currentUUID()/*: string*/
    {
        if ($this->pointer == -1)
            throw new Exception();

        return $this->stack[$this->pointer];
    }

    #[\ReturnTypeWillChange]
    public function key()/*: mixed*/
    {
        return $this->pointer;
    }

    #[\ReturnTypeWillChange]
    public function next()/*: void*/
    {
        $this->pointer--;
    }

    #[\ReturnTypeWillChange]
    public function rewind()/*: void*/
    {
        $this->pointer = $this->size - 1;
    }

    #[\ReturnTypeWillChange]
    public function valid()/*: bool*/
    {
        return $this->pointer >= 0;
    }
}

class ModIndex
{
    use FormTools;

    // Top-Level Modules
    public static $TLM = [];
    // Parents List
    public static $PL = [];
    // Total index
    public static $TI = [
        SetUserStackModule::MOD_UUID => SetUserStackModule::class,
    ];

    public static function addParent(
        /*string*/ $cls,
        /*string*/ $uuid
    )/*: int*/ {
        if (!array_key_exists($uuid, static::$TI))
            return -2;
        if (!array_key_exists($uuid, static::$PL))
            static::$PL[$uuid] = [];

        static::$PL[$uuid][] = $cls::MOD_UUID;

        return 0;
    }

    public static function addModule(
        /*string*/ $cls,
        array $parents = []
    )/*: int*/ {
        if (!is_subclass_of($cls, BasicModuleInterface::class, true))
            return -1;

        if ($cls::MOD_PARENT) {
            $status = static::addParent($cls, $cls::MOD_PARENT);
            if ($status < 0)
                return $status;
        }
        static::$TI[$cls::MOD_UUID] = $cls;

        foreach ($parents as $uuid)
            static::addParent($cls, $uuid);

        return 0;
    }

    public static function makeTLM(/*string*/ $cls)/*: int*/
    {
        if (!is_subclass_of($cls, BasicModuleInterface::class, true))
            return -1;
        if (!array_key_exists($cls::MOD_UUID, static::$TI))
            return -2;

        static::$TLM[$cls::MOD_UUID] = $cls;

        return 0;
    }

    public static function fastCall(
        /*string*/ $cls,
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        list($tlm, $stack) = static::getTLM($cls);

        foreach (array_reverse($stack) as $uuid) {
            $cls = static::$TI[$uuid];
            $prnt_args = $cls::process($args, $prnt_args);
        }

        return $prnt_args;
    }

    public static function getTLM(/*string*/ $cls)/*: array*/
    {
        if (!is_subclass_of($cls, BasicModuleInterface::class, true))
            return [null, []];

        $stack = [];
        $prnt = $cls::MOD_UUID;
        while ($prnt) {
            if (in_array($prnt, $stack))
                throw new Exception('Invalid parent tree for ' . $cls);

            $cls = static::$TI[$prnt];
            $stack[] = $prnt;
            $prnt = $cls::MOD_PARENT;
        };

        return [$cls, $stack];
    }

    public static function getParents(/*string*/ $uuid)/*: Generator*/
    {
        if (!array_key_exists($uuid, ModIndex::$PL))
            return;

        foreach (ModIndex::$PL[$uuid] as $uuid) {
            yield $uuid => ModIndex::$TI[$uuid];
        }
    }

    public static function getFormFields()/*: array*/
    {
        $result = static::$INTRINSIC_FIELDS;
        foreach (static::$TI as $class) {
            if (!property_exists($class, 'FORM_FIELDS'))
                continue;
            
            $result += $class::$FORM_FIELDS;
        }

        return $result;
    }

    public static function getCurrentValues(ArgsStore $args)/*: array*/
    {
        $result = [];
        foreach (static::getFormFields() as $key => $spec) {
            if (
                !isset($args[$key])
                || (
                    array_key_exists('o_prevent_export', $spec)
                    && in_array('preserve', $spec['o_prevent_export'])
                )
            )
                continue;

            $value = static::valueGetBySpec($spec, $args, $key, '', true);
            if (is_array($value))
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);

            $result[$key] = $value;
        }

        return $result;
    }
}

class MwChains implements MiddlewareInterface
{
    public static $GLOBAL_CHAIN = [
        // [SetUserStackModule::class, []],
        // [MwChains::class, [
        //     'chain' => [
        //         [ProcessRqMw::class, []],
        //     ],
        //     'resolver' => [self::class, 'resolveTrue'],
        // ]],
        // [IntAlterDisplayMw::class, []],
        // [ExtAlterDisplayMw::class, ['type' => 'json', 'key' => 'json']],
        // [ExtAlterDisplayMw::class, ['type' => 'raw', 'key' => 'raw']],
    ];
    public static $CHAINS = [
        // 'default' => [
        //     [SetRLMMw::class, ['rlm_uuid' => Main::MOD_UUID]],
        //     [UserDisplayMw::class, []],
        // ],
    ];

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        if (!array_key_exists('chain', $curr_args))
            return true;

        $lastMw = [];
        try {
            $status = static::invokeChain($args, $curr_args['chain'], $lastMw);
        } catch (Exception $e) {
            if (!array_key_exists('exception_resolver', $curr_args))
                throw $e;

            $status = $curr_args['exception_resolver'](
                $args,
                $curr_args,
                $e,
                $lastMw
            );
        }

        if (array_key_exists('resolver', $curr_args))
            return $curr_args['resolver']($args, $curr_args, $status, $lastMw);

        return $status < 0;
    }

    public static function invokeChain(
        ArgsStore $args,
        /*array*/ $chain,
        /*array*/ &$lastMw = []
    )/*: int*/ {
        foreach ($chain as $mw) {
            list($cls, $cls_args) = $lastMw = $mw;
            if (!is_subclass_of($cls, MiddlewareInterface::class, true))
                throw new Exception('Internal Server Error. Miss-configuration');

            if (!$cls::invokeMw($args, $cls_args))
                return 1;
        }

        return 0; 
    }

    public static function invokeFullChain(
        ArgsStore $args,
        /*string*/ $name
    )/*: int*/ {
        $result = static::invokeChain($args, static::$GLOBAL_CHAIN);
        if ($result != 0)
            return $result;

        if (!array_key_exists($name, static::$CHAINS))
            return -2;

        return static::invokeChain($args, static::$CHAINS[$name]);
    }

    protected static function resolveTrue(
        ArgsStore $args,
        array $curr_args,
        /*int*/ $status,
        /*array*/ &$lastMw = []
    )/*: bool*/ {
        return true;
    }

    protected static function resolveFalse(
        ArgsStore $args,
        array $curr_args,
        /*int*/ $status,
        /*array*/ &$lastMw = []
    )/*: bool*/ {
        return false;
    }

    protected static function resolveInvert(
        ArgsStore $args,
        array $curr_args,
        /*int*/ $status,
        /*array*/ &$lastMw = []
    )/*: bool*/ {
        return !$status;
    }
}

class SetUserStackModule implements BasicModuleInterface, MiddlewareInterface
{
    use FormTools;

    const MOD_UUID = 'd830b952-9b15-4a4b-8646-1772566fae01';
    const MOD_NAME = 'Set user specified proc';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'proc' => [
            't_str',
            'c_not_empty' => [],
            'c_limit' => ['mode' => 'len', 'maxq' => 36],
        ],
    ];

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        $proc = static::valueGet($args, 'proc');
        if (is_null($proc) && array_key_exists('default', $curr_args))
            $proc = $curr_args['default'];

        if (
            array_key_exists('alias', $curr_args)
            && array_key_exists($proc, $curr_args['alias'])
        )
            $proc = $curr_args['alias'][$proc];

        $args->getStack()->resetFromUUID($proc);

        return true;
    }

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return [
            'current' => static::valueGet($args, 'proc'),
        ];
    }
}

class ProcessRqMw implements MiddlewareInterface
{
    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        $stack = $args->getStack();
        if (!$stack->isRunable())
            return true;

        $args->smStageInvoke(ServiceManager::SERVICE_STAGE_PROCESS);
        $stack->process($args, $curr_args);

        return true;
    }
}

class SetRLMMw implements MiddlewareInterface
{
    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        if (!$args->getStack()->setRLM($curr_args['rlm_uuid']))
            throw new Exception('Unable to set RLM: ' . $curr_args['rlm_uuid']);

        return true;
    }
}

/**
 * Displays RLM
 */
class UserDisplayMw implements MiddlewareInterface
{
    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        $rlm = $args->getStack()->getRLM();
        if (!$rlm)
            throw new Exception('RLM is undefined');

        $args->smStageInvoke(ServiceManager::SERVICE_STAGE_DISPLAY);

        // PHP5 compat
        $cls = ModIndex::$TI[$rlm];
        $cls::display($args, []);

        return true;
    }
}

class DisplayFormers
{
    public static $DISPLAY_FORMERS = [
        'displayArray' => [self::class, 'displayJSON'],
        'displayRaw' => [self::class, 'displayRaw'],
    ];

    public static function displayJSON(
        ArgsStore $args,
        array $curr_args,
        /*?array*/ $result
    )/*: bool*/ {
        if (is_null($result))
            return false;

        header('Content-Type: application/json');
        echo json_encode($result);

        return true;
    }

    public static function displayRaw(
        ArgsStore $args,
        array $curr_args,
        /*Generator*/ $result
    )/*: void*/ {
        if (is_null($result))
            return false;

        foreach ($result as $row) {
            echo $row;
        }

        return true;
    }
}

class ExtAlterDisplayMw extends DisplayFormers implements MiddlewareInterface
{
    public static $DISPLAY_TYPES = [
        'json' => 'displayArray',
        'raw' => 'displayRaw',
    ];

    /**
     * Arguments:
     *   Required: type
     *   Optional: key, value
     */
    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        if (!array_key_exists('type', $curr_args))
            return true;

        $type = $curr_args['type'];
        if (!array_key_exists($type, static::$DISPLAY_TYPES))
            return true;

        if (array_key_exists('key', $curr_args)) {
            $key = $curr_args['key'];
            if (!isset($args[$key]))
                return true;
            
            if (
                array_key_exists('value', $curr_args)
                && $args[$key] != $curr_args['value']
            )
                return true;
        }

        $call = static::$DISPLAY_TYPES[$type];
        $former = static::$DISPLAY_FORMERS[$call];
        $result = $args->getStack()->{$call}($args, []);
        if (is_null($result))
            http_response_code(501);
        else if ($former($args, $curr_args, $result))
            return false;

        return false;
    }
}

class IntAlterDisplayMw extends DisplayFormers implements MiddlewareInterface
{
    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        $stack = $args->getStack();
        $call = $stack->preDisplay($args, []);
        if (is_null($call))
            return true;
        if (!array_key_exists($call, static::$DISPLAY_FORMERS))
            throw new Exception('Unknown display call. Unable to find former');

        $former = static::$DISPLAY_FORMERS[$call];
        $result = $stack->{$call}($args, []);
        if (is_null($result))
            return true;
        else if ($former($args, $curr_args, $result))
            return false;

        return true;
    }
}

abstract class GroupModule implements UserModuleInterface
{
    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return $prnt_args;
    }

    public static function moduleIter()/*: Generator*/
    {
        foreach (ModIndex::getParents(static::MOD_UUID) as $uuid => $cls) {
            if (!is_subclass_of($cls, UserModuleInterface::class, true))
                continue;
            if (
                is_subclass_of($cls, EnvCheckInterface::class, true)
                && !$cls::checkEnv()[0]
            )
                continue;

            yield $uuid => $cls;
        }
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        $result =
            '<fieldset class="stack">'
                . '<legend>' . static::MOD_NAME . '</legend>';;
        foreach (static::moduleIter() as $uuid => $cls) {
            $result .=
                '<fieldset class="stack">'
                    . '<legend>' . $cls::MOD_NAME . '</legend>'
                    . $cls::form($args)
                . '</fieldset>';
        }
        $result .=
            '</fieldset>';

        return $result;
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        $stack = $args->getStack();
        if (!$stack->valid())
            return;

        $cls = $stack->current();
        $result = $stack->nextDisplay($args, $curr_args, $prnt_args, $valid);

        if ($valid)
            return $result;

        return
            '<fieldset id="result" class="result">'
                . '<legend>' . $cls::MOD_NAME . '</legend>'
                . $result
            . '</fieldset>';
    }
}

abstract class PlainGroupModule
    extends GroupModule
    implements UserModuleInterface
{

    public static function form(ArgsStore $args)/*: ?string*/
    {
        $result .= '';
        foreach (static::moduleIter() as $uuid => $cls) {
            $result .= $cls::form($args);
        }

        return $result;
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        $stack = $args->getStack();
        if (!$stack->valid())
            return;

        $cls = $stack->current();
        return $stack->nextDisplay($args, $curr_args, $prnt_args);
    }
}
