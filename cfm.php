<?php

/**&
@module_content
  uuid: f725e1a1-d115-4c86-8743-8b6f6875f221
  id: creds
  name: Credits
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
# Copyright 2024-2025 trashlogic
# License: GNU AGPLv3
# SPDX-License-Identifier: AGPL-3.0-only

# Codename: Crystal File Manager
# Author: trashlogic
# GPG Key FP: 40AC0FCA09058D53546DBDC46E37526E230EAE9E
# Target PHP version: PHP 5 (>= 5.5.0), PHP 7, PHP 8
# Tested with:
#  - GNU/Linux (x86_64) (Apache, PHP Development Server)
# Optional/Required extensions:
#  - json       (required)
#  - iconv      (required)
#  - hash       (optional)
/**&
@module_content
  uuid: 30146e5d-0afd-4757-9fe1-e14507e9542c
  name: Microcore module
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
interface EnvCheckInterface
{
    public static function checkEnv()/*: array*/;
}

interface BasicModuleInterface
{
    // const MOD_UUID = '';
    // const MOD_NAME = '';
    // const MOD_PARENT = null;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/;
}

interface AlterModuleInterface extends BasicModuleInterface
{
    public static function preDisplay(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/;
}

interface MachineModuleInterface extends BasicModuleInterface
{
    public static function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/;
}

interface RawModuleInterface extends BasicModuleInterface
{
    public static function displayRaw(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?Generator*/;
}

interface UserModuleInterface extends BasicModuleInterface
{
    public static function form(ArgsStore $args)/*: ?string*/;

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/;
}

interface GadgetModuleInterface
{
    public static function gadget(ArgsStore $args)/*: ?string*/;
}

interface ServiceInterface
{
    // public static $SERVICE_STAGES = [
    //    ServiceManager::SERVICE_STAGE_PROCESS => [self::class, 'exampleStage']
    //];

    // public static function exampleStage(
    //     ArgsStore $args,
    //     /*string*/ $stage,
    //     /*string|int*/ $id
    // )/*: mixed*/
}

interface MiddlewareInterface
{
    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/;
}

final class ArgsStore implements ArrayAccess
{

    protected /*array*/ $calls = [];
    protected /*array*/ $domains = [];
    protected /*array*/ $domainKeys = [];

    public function domainAdd(
        array $data,
        /*string|int*/ $name
    )/*: bool*/ {
        if (is_null($name) || $this->isDomainExists($name))
            return false;

        $this->domains[$name] = [];
        foreach ($data as $key => $value) {
            if (
                array_key_exists($key, $this->domainKeys)
                || is_null($value)
                || $value === ''
                // || empty($value)
            )
                continue;

            $this->domainKeys[$key] = $name;
            $this->domains[$name][$key] = $value;
        }

        return true;
    }

    public function callAdd(/*string*/ $name, /*callable*/ $call)/*: bool*/
    {
        $this->calls[$name] = $call;
    }

    public function isCallAvailable(/*string*/ $name)/*: bool*/
    {
        return array_key_exists($name, $this->calls);
    }

    public function __call(/*string*/ $name, array $arguments)/*: mixed*/
    {
        if (!$this->isCallAvailable($name))
            throw new RuntimeException('Unknown call: ' . $name);

        // PHP5 compat
        // return $this->calls[$name](...$arguments);
        return call_user_func_array($this->calls[$name], $arguments);
    }

    public function domainExport(/*string*/ $name)/*: array*/
    {
        if (!$this->isDomainExists($name))
            return [];

        return $this->domains[$name];
    }

    public function isDomainExists(/*string|int*/ $name)/*: bool*/
    {
        return array_key_exists($name, $this->domains);
    }

    public function domainFromOffset(/*mixed*/ $offset)/*: ?string*/
    {
        if (!array_key_exists($offset, $this->domainKeys))
            return;

        return $this->domainKeys[$offset];
    }

    public function get(
        /*mixed*/ $key,
        /*mixed*/ $default = null,
        /*?string*/ $domain = null
    )/*: mixed*/ {
        $keyDomain = $this->domainFromOffset($key);
        if (is_null($keyDomain) || ($domain && $keyDomain != $domain))
            return $default;

        return $this->domains[$domain][$key];
    }

    public function exists(
        /*mixed*/ $offset,
        /*?string*/ $domain = null
    )/*: bool*/ {
        if (is_null($domain))
            return $this->offsetExists($offset);
        else
            return $this->domainFromOffset($offset) === $domain;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists(/*mixed*/ $offset)/*: bool*/
    {
        return !is_null($this->domainFromOffset($offset));
    }

    #[\ReturnTypeWillChange]
    public function offsetGet(/*mixed*/ $offset)/*: mixed*/
    {
        $name = $this->domainFromOffset($offset);
        if (is_null($name))
            return;

        return $this->domains[$name][$offset];
    }

    #[\ReturnTypeWillChange]
    public function offsetSet(/*mixed*/ $offset, /*mixed*/ $value)/*: void*/
    {
        $name = $this->domainFromOffset($offset);
        if (is_null($name))
            return;

        $this->domains[$name][$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset(/*mixed*/ $offset)/*: void*/
    {
        $name = $this->domainFromOffset($offset);
        if (is_null($name))
            return;

        unset($this->domains[$name][$offset]);
        unset($this->domainKeys[$offset]);
    }
}

trait EncodingTools
{
    protected static function decodeData(
        /*mixed*/ $data,
        /*string*/ $source = null,
        /*bool*/ $encode = false
    )/*: mixed */ {
        $target = ini_get('default_charset');
        if (!$source)
            return $data;
        if ($source == $target)
            return $data;

        if ($encode)
            list($source, $target) = [$target, $source];

        return static::iconvArray($source, $target, $data);
    }

    protected static function toCSV(
        Iterator $iter,
        /*?array*/ $keys = null
    )/*: string*/ {
        $result .= '';
        foreach (static::toCSVGen($iter, $keys) as $row) {
            $result .= $row;
        }

        return $result;
    }

    protected static function toCSVGen(
        Iterator $iter,
        /*?array*/ $keys = null
    )/*: string*/ {
        $call = function ($row)/*: string*/ {
            $result = '';
            $first = true;
            foreach ($row as $value) {
                if (
                    strpos($value, ';') !== false
                    || strpos($value, "\n") !== false
                    || strpos($value, "\r") !== false
                )
                    $value = '"' . str_replace('"', '""', $value) . '"';

                $result .= ($first ? '' : ';') . $value;
                $first = false;
            }

            return $result . "\n";
        };

        $show_header = true;
        foreach ($iter as $row) {
            if ($show_header) {
                $show_header = false;
                yield $call(is_null($keys) ? array_keys($row) : $keys);
            }

            yield $call($row);
        }
    }

    protected static function iconvArray(
        /*string*/ $source,
        /*string*/ $target,
        /*string|array*/ $data
    )/*: mixed*/ {
        if (is_string($data))
            return @iconv($source, $target, $data);
        else if (!is_array($data))
            return $data;

        $result = [];
        foreach ($data as $key => $value) {
            $rkey = $key;
            if (is_string($key)) {
                $rkey = @iconv($source, $target, $key);
                if ($key === false)
                    $rkey = 'ICV_ERR{' . base64_encode($key) . '}';
            }

            $rvalue = $value;
            if (is_array($value))
                $rvalue = static::iconvArray($source, $target, $value);
            else if (is_string($value)) {
                $rvalue = @iconv($source, $target, $value);
                if ($rvalue === false)
                    $rvalue = 'ICV_ERR{' . base64_encode($value) . '}';
            }

            $result[$rkey] = $rvalue;
        }

        return $result;
    }
}
/**&
@module_content
  uuid: add45d38-919f-4da5-b76b-7b924aaefa79
  name: Core module
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
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
        /*bool*/ $decorate = true,
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
        /*string*/ $uuid,
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
        /*string*/ $uuid,
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
        /*bool*/ $tlm = false,
        array $parents = []
    )/*: int*/ {
        if (!is_subclass_of($cls, BasicModuleInterface::class, true))
            return -1;

        if ($cls::MOD_PARENT) {
            $status = static::addParent($cls, $cls::MOD_PARENT);
            if ($status < 0)
                return $status;
        } else if ($tlm) {
            static::$TLM[$cls::MOD_UUID] = $cls;
        }
        static::$TI[$cls::MOD_UUID] = $cls;

        foreach ($parents as $uuid)
            static::addParent($cls, $uuid);

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
                return -1;

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
/**&
@module_content
  uuid: edcabf11-4f6c-4f39-b510-9f1fe099ddd9
  name: Infuser module
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
interface BasicFuserInterface
{
    // const NAME = '';
    // const CMD = '';

    public function isSustainable()/*: bool*/;

    public function isRequired()/*: bool*/;

    public function noHeader()/*: bool*/;

    public function partIdGet()/*: ?string*/;

    public function partNameGet()/*: ?string*/;

    public function partAttrsGet()/*: ?array*/;

    public function attrGet(/*string*/ $key)/*: mixed*/;

    public function headerGet()/*: Generator*/;

    public function dataGet()/*: Generator*/;

    public function readTick(Infuser $inf, string $data)/*: void*/;
}

class Infuser
{
    const STM_HEAD_TRIGGER = '/**&';
    const STM_HEAD_EXIT = '&*/';
    const STM_CMD_TRIGGER = '@';

    public static $STM_CMD_INDEX = [
        ModContentFuser::CMD => ModContentFuser::class,
    ];

    // protected /*string*/ $path = '';
    // protected /*array*/ $cache = [];

    // protected /*?string*/ $currentCmd = null;
    // protected /*bool*/ $inDescription = false;

    // public function __construct(ArgsStore $args, /*string*/ $path)
    // {
        
    // }

    public static function addCmd(/*string*/ $cls)/*: bool*/
    {
        if (!is_subclass_of($cls, BasicFuserInterface::class, true))
            return false;

        static::$STM_CMD_INDEX[$cls::CMD] = $cls;

        return true;
    }

    public static function defuseToFile(
        ArgsStore $args,
        /*string*/ $sourcePath,
        /*string*/ $targetPath
    )/*: array*/ {
        $n = 0;
        $result = [];
        foreach (static::defuse($args, $sourcePath) as $key => $data) {
            $targetFile =
                $targetPath
                . DIRECTORY_SEPARATOR
                . $n  . '_' . $key . '.php';
            $handle = fopen($targetFile , 'wb');
            if ($handle === false)
                throw new Exception('Unable to open file to write mod');

            foreach (static::infuseGen([$data]) as $line)
                fwrite($handle, $line);

            $n++;
            $result[] = $targetFile;
            fclose($handle);
        }

        return $result;
    }

    public static function infuseGen(array $content = [])/*: Generator*/
    {
        yield "<?php\n\n";
        foreach ($content as $data) {
            if (!$data->noHeader()) {
                yield static::STM_HEAD_TRIGGER . "\n"
                    . '@' . $data::CMD . "\n";

                foreach ($data->headerGet() as $line)
                    yield $line;

                yield static::STM_HEAD_EXIT . "\n";
            }

            foreach ($data->dataGet() as $line)
                yield $line;
        }
    }

    public static function infuse(array $content = [])/*: string*/
    {
        $result = '';
        foreach (static::infuseGen($content) as $line)
            $result .= $line;

        return $result;
    }

    public static function infuseToFile(
        /*string*/ $path,
        array $content = []
    )/*: bool*/ {
        $handle = fopen($path, 'wb');
        if ($handle === false)
            return false;

        foreach (static::infuseGen($content) as $line) {
            fwrite($handle, $line);
        }
        fclose($handle);

        return true;
    }

    public static function defuse(ArgsStore $args, /*string*/ $path)/*: array*/
    {
        if (!file_exists($path))
            throw new Exception('Invalid target module path: ' . $path);

        $result = [];
        $fuser = null;
        $handle = fopen($path, 'rb');
        if ($handle === false)
            throw new Exception('Unable to open source file to read');

        $call = function ($fuser) use (&$result) {
            if (!$fuser->isSustainable())
                return;

            $id = $fuser->partIdGet();
            if (array_key_exists($id, $result))
                throw new Exception('Id Collision: ' . $id);

            $result[$id] = $fuser;
        };

        $inDescription = false;
        while (($line = fgets($handle)) !== false) {
            if ($inDescription)
                $line = trim($line);

            if (strpos($line, static::STM_HEAD_TRIGGER) === 0) {
                $inDescription = true;
                continue;
            } else if (strpos($line, static::STM_HEAD_EXIT) === 0) {
                $inDescription = false;
                continue;
            }

            if ($inDescription && $line[0] == static::STM_CMD_TRIGGER) {
                if ($fuser)
                    $call($fuser);

                $fuser = null;
                $cmd = substr($line, 1);
                if (!array_key_exists($cmd, static::$STM_CMD_INDEX))
                    continue;

                $fuser = new static::$STM_CMD_INDEX[$cmd]($args);
            }

            if (!$fuser)
                continue;

            $fuser->readTick($inDescription, $line);
        }
        if ($fuser)
            $call($fuser);

        fclose($handle);

        return $result;
    }

    // public function inDescription()/*: bool*/
    // {
    //     return $this->inDescription;
    // }
}

abstract class AbstractFuser
{
    protected /*ArgsStore*/ $args;

    public function __construct(ArgsStore $args)
    {
        $this->args = $args;
    }

    public function readTick(/*bool*/ $inDescription, string $data)/*: void*/
    {
        if ($inDescription)
            return $this->descReadTick($data);

        return $this->contReadTick($data);
    }

    abstract protected function descReadTick(string $data)/*: void*/;

    abstract protected function contReadTick(string $data)/*: void*/;
}

class ModContentFuser extends AbstractFuser implements BasicFuserInterface
{
    const NAME = 'Module content fuser';
    const CMD = 'module_content';

    protected /*?string*/ $uuid = null;
    protected /*?string*/ $id = null;
    protected /*?string*/ $name = null;
    protected /*?string*/ $verm = null;
    protected /*?string*/ $author = null;
    protected /*?string*/ $license = null;
    protected /*bool*/ $required = false;
    protected /*bool*/ $no_header = false;
    protected /*array*/ $data = [];

    protected static $ALLOWED_KEYS = [
        'uuid' => 'strval',
        'id' => 'strval',
        'name' => 'strval',
        'verm' => 'intval',
        'author' => 'strval',
        'license' => 'strval',
        'required' => 'boolval',
        'no_header' => 'boolval',
    ];

    public function __construct(ArgsStore $args, array $state = [])
    {
        foreach (['data'] + array_keys(static::$ALLOWED_KEYS) as $key) {
            if (!array_key_exists($key, $state))
                continue;

            $this->{$key} = $state[$key];
        }
    }

    public function isSustainable()/*: bool*/
    {
        return !is_null($this->id) || !is_null($this->uuid);
    }

    public function isRequired()/*: bool*/
    {
        return $this->required;
    }

    public function noHeader()/*: bool*/
    {
        return boolval($this->no_header);
    }

    public function partIdGet()/*: ?string*/
    {
        if (!is_null($this->id))
            return $this->id;
        if (!is_null($this->uuid))
            return 'mod:' . $this->uuid;
    }

    public function partAttrsGet()/*: ?array*/
    {
        return array_keys(static::$ALLOWED_KEYS);
    }

    public function attrGet(/*string*/ $key)/*: mixed*/
    {
        if (!array_key_exists($key, static::$ALLOWED_KEYS))
            return;

        return $this->{$key};
    }

    public function partNameGet()/*: ?string*/
    {
        return $this->name;
    }

    public function getVerm()/*: int*/
    {
        return $this->verm;
    }

    public function getAuthor()/*: ?string*/
    {
        return $this->author;
    }

    public function getLicense()/*: ?string*/
    {
        return $this->license;
    }

    public function headerGet()/*: Generator*/
    {
        if ($this->no_header)
            return;

        foreach (static::$ALLOWED_KEYS as $key => $call) {
            $value = $this->{$key};
            if (!$value)
                continue;

            yield '  ' . $key . ': ' . $value . "\n";
        }
    }

    public function dataGet()/*: Generator*/
    {
        foreach ($this->data as $data)
            yield $data;
    }

    protected function descReadTick(string $data)/*: void*/
    {
        $data = explode(':', $data);
        if (count($data) != 2)
            return;

        $data = array_map('trim', $data);
        list($key, $value) = $data;
        if (array_key_exists($key, static::$ALLOWED_KEYS))
            $this->{$key} = static::$ALLOWED_KEYS[$key]($value);
    }

    protected function contReadTick(string $data)/*: void*/
    {
        $this->data[] = $data;
    }
}
/**&
@module_content
  uuid: 5881a360-d9b6-461d-b8ba-6a82f7197527
  name: RT Config Tools
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
interface ConfigProviderInterface
{
    public static function configGet()/*: array*/;
}

class RuntimeConfig implements MiddlewareInterface
{
    protected static /*array*/ $DEFAULT_RT_CONFIG = [];
    protected static /*array*/ $DEFAULT_CONFIGS = [];

    public /*array*/ $runtimeConfig = [];
    public /*array*/ $currentConfig = [];

    public function __construct(ArgsStore $args, array $config = null)
    {
        $args->callAdd('cfgVGet', [$this, 'get']);
        $args->callAdd('cfgVSet', [$this, 'set']);
        $args->callAdd('cfgDump', [$this, 'dump']);
        $args->callAdd(
            'cfgIGet',
            function () {
                return $this;
            }
        );
        $args->callAdd(
            'pathCurrent',
            function () {
                return $_SERVER['SCRIPT_FILENAME'];
            }
        );
        $this->args = $args;

        if (is_null($config))
            $config = static::$DEFAULT_RT_CONFIG;

        $this->runtimeConfig = $config;
    }

    public static function invokeMw(ArgsStore $args, array $curr_args)/*: bool*/
    {
        if (!$args->isCallAvailable('cfgVSet'))
            return true;

        foreach ($curr_args as $cls => $data)
            foreach ($data as $key => $value)
                $args->cfgVSet($cls, $key, $value);

        return true;
    }

    public static function defaultRuntimeSet(array $config)/*: void*/
    {
        static::$DEFAULT_RT_CONFIG = $config;
    }

    public static function addDefault(/*string*/ $cls)/*: bool*/
    {
        if (!is_subclass_of($cls, ConfigProviderInterface::class, true))
            return false;

        static::$DEFAULT_CONFIGS[$cls] = $cls::configGet();
    }

    public static function defaultExists(
        /*string*/ $cls,
        /*string*/ $key
    )/*: bool*/ {
        return array_key_exists($cls, static::$DEFAULT_CONFIGS)
            && array_key_exists($key, static::$DEFAULT_CONFIGS[$cls]);
    }

    public function runtimeExists(
        /*string*/ $cls,
        /*string*/ $key
    )/*: bool*/ {
        return array_key_exists($cls, $this->runtimeConfig)
            && array_key_exists($key, $this->runtimeConfig[$cls]);
    }

    public function currentExists(
        /*string*/ $cls,
        /*string*/ $key
    )/*: bool*/ {
        return array_key_exists($cls, $this->currentConfig)
            && array_key_exists($key, $this->currentConfig[$cls]);
    }

    public function get(
        /*string*/ $cls,
        /*string*/ $key,
        /*mixed*/ $default = null
    )/*: mixed*/ {
        if (!static::defaultExists($cls, $key))
            return $default;
        if ($this->currentExists($cls, $key))
            return $this->currentConfig[$cls][$key];
        if (!$this->runtimeExists($cls, $key))
            return static::$DEFAULT_CONFIGS[$cls][$key];

        return $this->runtimeConfig[$cls][$key];
    }

    public function set(
        /*string*/ $cls,
        /*string*/ $key,
        /*mixed*/ $value,
        /*bool*/ $toRuntime = false
    )/*: bool*/ {
        if (!static::defaultExists($cls, $key))
            return false;
        if ($toRuntime)
            $data = &$this->runtimeConfig;
        else
            $data = &$this->currentConfig;

        if (!array_key_exists($cls, $data))
            $data[$cls] = [];

        $data[$cls][$key] = $value;

        return true;
    }

    public function dump()/*: array*/
    {
        return $this->runtimeConfig;
    }

    public function dumpToPart(/*string*/ $file)/*: array*/
    {
        if (!is_writable($file))
            return [
                'status' => -1,
                'msg' => 'Settings can\'t be saved. Current file is Read-Only',
            ];

        $mods = Infuser::defuse($this->args, $file);
        if (!array_key_exists('rt_config', $mods))
            return [
                'status' => -2,
                'msg' => 'Current file doesn\'t specify Runtime Config section',
            ];

        if (!Infuser::infuseToFile($file, $mods))
            return [
                'status' => -3,
                'msg' => 'Unable to remove infuse or write file'
            ];

        if (extension_loaded('Zend OPcache'))
            opcache_invalidate($file, true);

        return ['status' => 0];
    }
}

class RuntimeConfigFuser implements BasicFuserInterface
{
    const NAME = 'Runtime config fuser';
    const CMD = 'runtime_config';

    protected /*ArgsStore*/ $args;

    public function __construct(ArgsStore $args)
    {
        $this->args = $args;
    }

    public function isSustainable()/*: bool*/
    {
        return $this->args->isCallAvailable('cfgIGet');
    }

    public function isRequired()/*: bool*/
    {
        return true;
    }

    public function noHeader()/*: bool*/
    {
        return false;
    }

    public function partIdGet()/*: ?string*/
    {
        return 'rt_config';
    }

    public function partNameGet()/*: ?string*/
    {
        return 'Runtime config';
    }

    public function partAttrsGet()/*: ?array*/
    {
        return;
    }

    public function attrGet(/*string*/ $key)/*: mixed*/
    {
        return null;
    }

    public function headerGet()/*: Generator*/
    {
        yield '';
    }

    public function dataGet()/*: Generator*/
    {
        $data = json_encode($this->args->cfgDump());

        yield 'RuntimeConfig::defaultRuntimeSet('
                . 'json_decode(\'' . $data . '\', true)'
            . ");\n";
    }

    public function readTick(/*bool*/ $inDescription, string $data)/*: void*/
    {
    }
}

Infuser::addCmd(RuntimeConfigFuser::class);
/**&
@runtime_config
&*/
RuntimeConfig::defaultRuntimeSet(json_decode('[]', true));
/**&
@module_content
  uuid: cbb1d654-396f-49fc-b575-6ca687b7899c
  name: Core Setup
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
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
        if ($args->cfgVGet(SetupGroup::class, 'setup_complete'))
            return ['status' => -1, 'msg' => 'Already setted up'];

        foreach (ModIndex::getParents(SetupGroup::MOD_UUID) as $uuid => $cls) {
            $result = ModIndex::fastCall($cls, $args);

            if ($result['status'] !== 0)
                return $result;
        }

        $args->cfgVSet(SetupGroup::class, 'setup_complete', true, true);

        $result = $args->cfgIGet()->dumpToPart($args->pathCurrent());
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => 'Unable to complete setup'];

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return SetupGroup::form($args);
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
/**&
@module_content
  uuid: b397d6d1-68bb-4e8c-88ce-d787f43f117c
  name: Current version
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
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
/**&
@module_content
  uuid: 3bb19b4f-c49b-46cf-9fb0-09e3bc68c73f
  name: CSRF Form protection
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
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
/**&
@module_content
  uuid: a11190f6-fee2-4a5b-9687-8edd9c3e1b5b
  name: Complex Auth module
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
class CoreAuthenticationModule implements
    UserModuleInterface,
    MiddlewareInterface,
    ConfigProviderInterface
{
    use SecurityTools;
    use FormTools;

    const MOD_UUID = '21bd5bf0-1bf9-4d87-bce1-734d5426b7fb';
    const MOD_NAME = 'Core Authentication module';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'ca_username' => [
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
        'ca_password' => [
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
    public static $HASH_ALGO = 'sha256';
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

        $secret = $args->cfgVGet(static::class, 'secret');
        $username = $_COOKIE['current_user'];

        $currentAuthorized = false;
        $currentKey = static::createCurrentKey(
            $secret,
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
            $secret,
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

        // ini_set('open_basedir', getcwd());

        return true;
    }

    public static function createKey(
        /*string*/ $secret,
        /*string*/ $username,
        /*string*/ $nonce,
        /*int*/ $epochTime
    )/*: string*/ {
        return hash_hmac(
            static::$HASH_ALGO,
            $username . '_' . $nonce,
            $secret . '_' . floor(time() / $epochTime)
        );
    }

    public static function createCurrentKey(
        /*string*/ $secret,
        /*string*/ $username,
        /*string*/ $nonce
    )/*: string*/ {
        return static::createKey(
            $secret,
            $username,
            $nonce,
            static::$C_EPOCH_DT
        );
    }

    public static function createPersistantKey(
        /*string*/ $secret,
        /*string*/ $username,
        /*string*/ $nonce
    )/*: string*/ {
        return static::createKey(
            $secret,
            $username,
            $nonce,
            static::$P_EPOCH_DT
        );
    }

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $username = static::valueGet($args, 'ca_username');
        $password = static::valueGet($args, 'ca_password');
        if (!$username && !$password)
            return ['status' => 1];
        if (!$username || !$password)
            return ['status' => -1, 'msg' => 'Invalid username/password'];

        $users = $args->cfgVGet(static::class, 'users', []);
        if (!array_key_exists($username, $users))
            return ['status' => -1, 'msg' => 'Invalid username/password'];

        $current = $users[$username];
        if (!password_verify($password, $current['pwd_hash']))
            return ['status' => -1, 'msg' => 'Invalid username/password'];

        $nonce = static::getRandBytes(8);
        $secret = $args->cfgVGet(static::class, 'secret');
        $currentKey = static::createCurrentKey($secret, $username, $nonce);
        $persistantKey = static::createPersistantKey($secret, $username, $nonce);

        return [
            'status' => 0,
            'username' => $username,
            'key_nonce' => $nonce,
            'current_key' => $currentKey,
            'persistant_key' => $persistantKey,
        ];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            '<border class="stack">'
                . static::fieldGet($args, 'ca_username')
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
        if ($curr_args['status'] === 1)
            return;

        $pTime = time() + static::$P_EPOCH_DT;
        $cTime = time() + static::$C_EPOCH_DT;
        setcookie('current_user', $curr_args['username'], $pTime, '/');
        setcookie('key_nonce', $curr_args['key_nonce'], $pTime, '/');
        setcookie('current_key', $curr_args['current_key'], $cTime, '/');
        setcookie('persistant_key', $curr_args['persistant_key'], $pTime, '/');

        http_response_code(303);
        header('Location: ');

        return '<border>Successfull authorization. Reload the page</border>';
    }

    public static function currentUser(ArgsStore $args)/*: ?string*/
    {
        return $args->cfgVGet(static::class, 'current_user');
    }

    public static function currentUserData(ArgsStore $args)/*: ?array*/
    {
        $username = static::currentUser($args);
        if (is_null($username))
            return;

        $args->cfgVGet(static::class, 'users')[$username];
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
            return ['status' => -2, 'msg' => 'Unable apply changes'];

        return ['status' => 0, 'user' => $user];
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
            return ['status' => -2, 'msg' => 'Unable apply changes'];

        return ['status' => 0];
    }

    public static function updUser(
        ArgsStore $args,
        /*string*/ $username,
        /*array*/ $data
    )/*: array*/ {
        $users = $args->cfgVGet(static::class, 'users');
        if (!array_key_exists($username, $users))
            return ['status' => -1, 'msg' => 'Unknown user'];

        $users[$username] = $data;

        if (!$args->cfgVSet(static::class, 'users', $users, true))
            return ['status' => -2, 'msg' => 'Unable apply changes'];
    }

    public static function configGet()/*: array*/
    {
        return [
            'secret' => null,
            'current_user' => null,
            'users' => [],
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

        http_response_code(303);
        header('Location: ');

        return '<border>Successfull logout. Reload the page</border>';
    }
}

class SetupSessionSecretModule implements BasicModuleInterface
{
    use SecurityTools;

    const MOD_UUID = '7ae13f6b-7e94-4697-b31d-9039a5772831';
    const MOD_NAME = 'Setup Session Secret';
    const MOD_PARENT = SetupGroup::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $args->cfgVSet(
            CoreAuthenticationModule::class,
            'secret',
            static::getRandBytes(32),
            true
        );

        return ['status' => 0];
    }
}

ModIndex::addModule(CoreAuthenticationModule::class);
ModIndex::addModule(CoreLogoutModule::class);
ModIndex::addModule(SetupSessionSecretModule::class);
RuntimeConfig::addDefault(CoreAuthenticationModule::class);
/**&
@module_content
  uuid: 2dd9d09b-60c9-4a4c-bdec-c11aeeb88348
  name: Filesystem core
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
trait CheckPathName
{
    protected static function checkPathName(/*string*/ $name)/*: bool*/
    {
        return !(
            strlen($name) == 0
            || $name === '.'
            || $name === '..'
            || stripos($name, '/') !== false
            || stripos($name, '\\') !== false
        );
    }
}

trait CoreSizeConv
{
    protected static function normalizeSize(/*int*/ $size)/*: string*/
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        $n = 0;
        while ($size > 1024) {
            $size /= 1024;
            $n++;
        }

        return round($size, 1) . $units[$n];
    }
}

class CoreFileInfo extends SplFileInfo
{
    use CoreSizeConv;

    protected $mime_cache = null;

    public function getMIME()/*: ?string*/
    {
        if ($this->mime_cache)
            return $this->mime_cache;

        $path = $this->getRealPath();
        if (!$this->isReadable() || !$path)
            throw new RuntimeException('Unable to get mime type');

        try {
            $this->mime_cache = mime_content_type($path);
        } catch(Exception) {
        }

        return $this->mime_cache;
    }

    public function getNormMtime()/*: string*/
    {
        return date('d M Y H:i', $this->getMTime());
    }

    public function getNormSize()/*: double*/
    {
        return static::normalizeSize(
            $this->getSize()
        );
    }

    public function getNormPerms()/*: string*/
    {
        $p = $this->getPerms();
        $t = $this->isDir();
        $result = '';
        $s = $p >> 9;
        for ($i = 2; $i >= 0; $i--) {
            $pc = $p >> $i * 3;
            $sc = ($s >> $i) & 1;
            $result .=
                (($pc >> 2) & 1 ? 'r' : '-')
                . (($pc >> 1) & 1 ? 'w' : '-')
                . ($i
                    ? ($pc & 1
                        ? ($sc ? 's' : 'x')
                        : ($sc ? 'S' : '-')
                    )
                    : ($pc & 1
                        ? ($sc ? 't' : 'x')
                        : ($sc ? 'T' : '-')
                    )
                );
        }

        return [
            1 => 'p', 2 => 'c',
            4 => 'd', 6 => 'b',
            8 => '-', 10 => 'l',
            12 => 's',
        ][$p >> 12] . $result;
    }

    public function getFullPerms()/*: string*/
    {
    }
}

class CoreSearchFilter extends FilterIterator
{
    protected /*string*/ $needle = null;

    public function __construct(
        Iterator $iterator,
        /*string*/ $needle
    ) {
        parent::__construct($iterator);

        $this->setNeedle($needle);
    }

    public function setNeedle($needle)/*: void*/
    {
        $this->needle = $needle;
    }

    public function accept(): bool
    {
        $name = $this
            ->getInnerIterator()
            ->current()
            ->getFilename();

        if (empty($this->needle) || $name == '.' || $name == '..')
            return true;

        return stripos($name, $this->needle) !== false;
    }
}

class CoreFSIter extends ArrayIterator
{
    use CoreSizeConv;

    public static $DEFAULT_SORT_VALUES = [
        'filename' => [
            'name' => 'File name',
            'display_call' => 'getFilename',
            'raw_call' => 'getFilename',
            'sort_call' => 'getFilename',
        ],
        'size' => [
            'name' => 'Size',
            'display_call' => 'getNormSize',
            'raw_call' => 'getSize',
            'sort_call' => 'getSize',
        ],
        'mime' => [
            'name' => 'MIME',
            'display_call' => 'getMIME',
            'raw_call' => 'getMIME',
            'sort_call' => 'getMIME',
        ],
        'mtime' => [
            'name' => 'Mod. Time',
            'display_call' => 'getNormMtime',
            'raw_call' => 'getMTime',
            'sort_call' => 'getMTime',
        ],
        'perms' => [
            'name' => 'Perms',
            'display_call' => 'getNormPerms',
            'raw_call' => 'getPerms',
        ],
        'ctime' => [
            'name' => 'Creation Time',
            'display_call' => 'getCTime',
            'raw_call' => 'getCTime',
            'sort_call' => 'getCTime',
        ],
        'atime' => [
            'name' => 'Access Time',
            'display_call' => 'getATime',
            'raw_call' => 'getATime',
            'sort_call' => 'getATime',
        ],
        'owner' => [
            'name' => 'Owner',
            'display_call' => 'getOwner',
            'raw_call' => 'getOwner',
            'sort_call' => 'getOwner',
        ],
        'group' => [
            'name' => 'Group',
            'display_call' => 'getGroup',
            'raw_call' => 'getGroup',
            'sort_call' => 'getGroup',
        ],
    ];
    public static $FILE_CLASS = CoreFileInfo::class;

    protected /*bool*/ $ok = false;
    protected /*string*/ $okReason = null;

    public function __construct(string $path)
    {
        if (!is_dir($path)) {
            $this->okReason = 'Unable to traverse non-directory';
            return;
        }
        if (!is_readable($path)) {
            $this->okReason = 'Permission denied. Unable to open file or directory';
            return;
        }
        try {
            $fsIter = new FilesystemIterator(
                $path,
                FilesystemIterator::KEY_AS_PATHNAME
                | FilesystemIterator::CURRENT_AS_FILEINFO
                // | FilesystemIterator::SKIP_DOTS
            );
            $fsIter->setInfoClass(static::$FILE_CLASS);
            parent::__construct(
                iterator_to_array($fsIter)
            );

            $this->ok = true;
        } catch (Exception $e) {
            $this->ok = false;
            $this->okReason = $e->getMessage();
        }
    }

    public function getNormSize()/*: double*/
    {
        return static::normalizeSize(
            $this->getSize()
        );
    }

    public function isOk()/*: bool*/
    {
        return $this->ok;
    }

    public function okReason()/*: string*/
    {
        return $this->okReason;
    }

    public function sort(/*string*/ $method, /*bool*/ $orderASC = true)
    {
        if (!$this->isOk())
            return $this;
        if (!array_key_exists($method, static::$DEFAULT_SORT_VALUES))
            return $this;

        $method = static::$DEFAULT_SORT_VALUES[$method];
        if (!array_key_exists('sort_call', $method))
            return $this;

        $call = $method['sort_call'];
        $this->uasort(
            function(SplFileInfo $a, SplFileInfo $b) use ($call, $orderASC) {
                list($a, $b) = ($orderASC ? [$a, $b] : [$b, $a]);
                try {
                    return (is_string($a->$call())
                        ? strnatcmp($a->$call(), $b->$call())
                        : $b->$call() - $a->$call());
                } catch (Exception) {
                    return 0;
                }
            }
        );

        return $this;
    }

    public function dirsOnTop()
    {
        $this->uasort(
            function(SplFileInfo $a, SplFileInfo $b) {
                try {
                    $aIsDir = $a->isDir();
                    $bIsDir = $b->isDir();
                    if ($aIsDir && $bIsDir)
                        return 0;
                    if ($aIsDir)
                        return -1;
                    if ($bIsDir)
                        return 1;
                } catch (Exception) {
                }

                return 0;
            }
        );

        return $this;
    }

    public function limit(int $offset = 0, int $limit = 0)/*: ArrayIterator*/
    {
        if (!$limit || !$this->isOk())
            return $this;
        if ($offset >= count($this))
            $offset = count($this) - 1;

        return parent::__construct(
            iterator_to_array(new LimitIterator($this, $offset, $limit))
        );
    }

    public function match(
        /*string*/ $filter,
        /*bool*/ $isRegex = false
    )/*: ArrayIterator*/ {
        if (!$this->isOk())
            return $this;
        if (empty($filter))
            return $this;

        try {
            return parent::__construct(
                iterator_to_array(
                    ($isRegex
                        ? new RegexIterator(
                            $this, $filter, RegexIterator::MATCH
                        )
                        : new CoreSearchFilter(
                            $this, $filter
                        )
                    )
                )
            );
        } catch (InvalidArgumentException $e) {
            return $this;
        }
    }

    public function getSize()/*: int*/
    {
        $result = 0;
        foreach ($this as $fileInfo) {
            try {
                $result += $fileInfo->getSize();
            } catch (Exception) {
            }
        }

        return $result;
    }
}
/**&
@module_content
  uuid: 6497a112-7fc9-4eb7-888a-f0b20eb49057
  name: File viewer
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/

class FileViewDeleteModule implements UserModuleInterface
{
    use FormTools;

    const MOD_UUID = '04ba0fb6-73c2-424f-8dce-014e3f56bf79';
    const MOD_NAME = 'Delete file';
    const MOD_PARENT = FileViewModule::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        if (!unlink($prnt_args['path']))
            return ['status' => -3, 'msg' => 'Unable to delete file'];

        $args->getStack()->reProcess($args, FileViewModule::MOD_UUID);

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return static::buttonGet('proc', static::MOD_UUID, 'Delete', [
            'style' => 'border-color: red;',
            'title' => 'This operation is irreversable',
            'onclick' => 'return confirm(\'Definitely delete current directory?\nCurrent folder and ITS CONTENT will be deleted\');'
        ]);
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', true);

        return 'File successfully deleted';
    }
}

class FileDownloadModule implements
    UserModuleInterface,
    AlterModuleInterface,
    RawModuleInterface
{
    use FormTools;

    const MOD_UUID = 'b6c6b05b-e525-4a24-bf21-99cd75bc8f87';
    const MOD_NAME = 'Download File';
    const MOD_PARENT = FileViewModule::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        return $prnt_args;
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        // :\
        $path = FileViewModule::valueGet($args, 'file');

        return
            '<a '
                . 'href="?'
                    .  http_build_query([
                        'proc' => 'download',
                        'file' => $path
                    ])
                . '" '
                . 'target="_blank" '
            . '>'
                . '<button type="button">Download</button>'
            . '</a>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', true);

        return 'View file in raw mode';
    }

    public static function preDisplay(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return;

        return 'displayRaw';
    }

    public static function displayRaw(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?Generator*/ {
        if ($curr_args['status'] < 0)
            return;

        $chunkSize = 1048576;
        $file = $curr_args['file'];
        $size = $file->getSize();
        $mime = $file->getMIME();
        $name = addslashes($file->getFilename());

        header("Accept-Ranges: bytes");

        $handle = $file->openFile();
        if (!array_key_exists('HTTP_RANGE', $_SERVER)) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $size);
            header('Content-Disposition: attachment; filename="' . $name . '"');

            while(!$handle->eof())
                yield $handle->fread($chunkSize);
        
            return;
        }

        list($units, $ranges) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if ($units != 'bytes') {
            http_response_code(416);
            return;
        }

        $sizeI = $size - 1;
        $rangesValid = [];
        $ranges = array_map('trim', explode(',', $ranges, 128));
        foreach($ranges as $range) {
            list($from, $to) = explode('-', $range);
            $fromI = intval($from);
            $toI = intval($to);

            if ($fromI < 0 || $toI < 0 || $fromI > $sizeI || $toI > $sizeI)
                continue;

            if ($from !== '' && $to !== '') {
                if ($fromI > $toI)
                    continue;
            } else if ($from !== '') {
                $toI = $sizeI;
            } else if ($to !== '' && $toI > 0) {
                $fromI = $sizeI - $toI;
                $toI = $sizeI;
            } else {
                continue;
            }
            
            $rangesValid[] = [$fromI, $toI];
        }

        $call = function($handle, $length) use ($chunkSize) {
            $current = 0;
            while ($current < $length) {
                $currentChunk = $length - $current;
                $currentChunk = ($currentChunk < $chunkSize
                    ? $currentChunk
                    : $chunkSize
                );
                $current += $currentChunk;

                yield $handle->fread($currentChunk);
            }
        };

        $count = count($rangesValid);
        if ($count == 0) {
            http_response_code(416);
            return;
        }

        http_response_code(206);
        if ($count == 1) {
            $range = $rangesValid[0];
            $length = $range[1] - $range[0] + 1;

            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $length);
            header('Content-Range: bytes ' . $range[0] . '-' . $range[1] . '/' . $size);

            $handle->fseek($range[0]);

            foreach ($call($handle, $length) as $data)
                yield $data;
        } else {
            $headers = [];
            $boundary = time();
            $totalLength = 0;

            foreach ($rangesValid as &$range) {
                $range[2] = $range[1] - $range[0] + 1;
                $range[3] =
                    "\r\n--" . $boundary
                    . "\r\nContent-Type: " . $mime
                    . "\r\nContent-Range: bytes " . $range[0] . '-' . $range[1] . '/' . $size
                    . "\r\n"
                    . "\r\n";
                $totalLength += strlen($range[3]) + $range[2];
            }

            header('Content-Type: multipart/byteranges; boundary=' . $boundary);
            header('Content-Length: ' . $totalLength);

            foreach ($rangesValid as $lrange) {
                yield $lrange[3];

                $handle->fseek($lrange[0]);
                foreach ($call($handle, $lrange[2]) as $data)
                    yield $data;
            }
        }
    }
}

class FileViewModule extends PlainGroupModule implements UserModuleInterface
{
    use FormTools;

    const MOD_UUID = 'fd5d1e37-93bd-4622-8951-62e6561d5602';
    const MOD_NAME = 'File viewer';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'file' => [
            't_str',
            'c_not_empty' => [],
            'o_default_value' => '.',
            'o_field_schema' => [
                'placeholder' => 'File path',
                'attrs' => [
                    'id' => 'path',
                    'rows' => 1,
                    'readonly' => '',
                    'autofocus' => '',
                    'style' => 'text-align: center; flex-grow: 1;',
                    'autocomplete' => 'off',
                ],
            ],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $path = static::valueGet($args, 'file');
        if (!is_file($path))
            return ['status' => -1, 'msg' => 'File not found'];
        if (!is_readable($path))
            return [
                'status' => -2,
                'msg' => 'Permission denied. Unable to read file'
            ];

        $file = new CoreFileInfo($path);

        return [
            'status' => 0,
            'path' => $path,
            'file' => $file,
        ];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        $path = dirname(static::valueGet($args, 'file', '.'));
        if ($path === false)
            $path = '.';

        return
            '<border class="no_border" style="display: flex; justify-content: middle;">'
                . static::fieldGet($args, 'file')
                . '<a '
                    . 'target="_parent"'
                    . 'href="?' .
                        http_build_query([
                            'path' => $path,
                        ])
                    . '"'
                    . '>'
                        . '<button type="button">▲</button>'
                . '</a>'
            . '</border>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);

        $moduleResult = $args->getStack()->nextDisplay($args, []);

        return
            // $args->getStack()->nextDisplay($args, [])
            // parent::display($args, $curr_args, $prnt_args)
            ($moduleResult
                ? '<border class="result">'
                    . $moduleResult
                . '</border>'
                : ''
            )
            . parent::form($args);
            // . '<input disabled="" type="text" name="file_new_name" placeholder="New name">'
            // . '<button disabled="" type="submit" name="" value="rename">'
            //     . 'Rename'
            // . '</button>'
            // . '<button disabled="" type="submit" name="" value="copy">'
            //     . 'Move/Copy'
            // . '</button>';
    }
}

ModIndex::addModule(FileViewModule::class);
ModIndex::addModule(FileDownloadModule::class);
ModIndex::addModule(FileViewDeleteModule::class);
/**&
@module_content
  uuid: f5adf74b-8cf6-410e-9573-1e97c6524d43
  name: Filesystem tree view
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
class TreeCreateFileModule implements UserModuleInterface
{
    use FormTools;
    use CheckPathName;

    const MOD_UUID = '50f46b22-ab83-4521-aaa4-1a8e74bd5ba8';
    const MOD_NAME = 'Create empty file';
    const MOD_PARENT = TreeDirOpsGroup::MOD_UUID;

    public static $FORM_FIELDS = [
        'new_file_name' => [
            't_str',
            'c_not_empty' => [],
            'o_field_schema' => [
                'placeholder' => 'file name'
            ],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        $foldername = static::valueGet($args, 'new_file_name');
        if (!static::checkPathName($foldername))
            return ['status' => -5, 'msg' => 'Invalid file name'];

        $path = $prnt_args['path'];
        $target = $path . DIRECTORY_SEPARATOR . $foldername;
        if (!is_writable($path))
            return ['status' => -7, 'msg' => 'Parent directory isn\'t writable'];
        if (!touch($target))
            return ['status' => -8, 'msg' => 'Unable to create file'];

        $args->getStack()->reProcess($args, TreeDirOpsGroup::MOD_PARENT);

        return [
            'status' => 0,
            'path' => $target,
        ];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            static::fieldGet($args, 'new_file_name')
            . static::submitGet('Create');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', false);

        return
            'File created successfully. '
            . '<a '
                . 'href="?'
                .  http_build_query([
                    'proc' => 'view',
                    'file' => $curr_args['path']
                ]) . '" '
                . 'target="_blank" '
            . '>Go to file</a>';
    }

    public static function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/ {
        return $curr_args;
    }
}

class TreeCreateDirectoryModule implements UserModuleInterface
{
    use FormTools;
    use CheckPathName;

    const MOD_UUID = '1b247248-c533-425d-8937-6e545aabbe92';
    const MOD_NAME = 'Create directory';
    const MOD_PARENT = TreeDirOpsGroup::MOD_UUID;

    public static $FORM_FIELDS = [
        'new_directory_name' => [
            't_str',
            'c_not_empty' => [],
            'o_field_schema' => [
                'placeholder' => 'folder name'
            ],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        $foldername = static::valueGet($args, 'new_directory_name');
        if (!static::checkPathName($foldername))
            return ['status' => -5, 'msg' => 'Invalid forlder name'];

        $path = $prnt_args['path'];
        $target = $path . DIRECTORY_SEPARATOR . $foldername;
        if (is_file($target))
            return ['status' => -6, 'msg' => 'File with this name already exists'];
        if (file_exists($target))
            return ['status' => 10, 'msg' => 'Folder already exists'];
        if (!is_writable($path))
            return ['status' => -7, 'msg' => 'Parent directory isn\'t writable'];
        if (!mkdir($target))
            return ['status' => -8, 'msg' => 'Unable to create directory'];

        $args->getStack()->reProcess($args, TreeDirOpsGroup::MOD_PARENT);

        return [
            'status' => 0,
            'path' => $target,
        ];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            static::fieldGet($args, 'new_directory_name')
            . static::submitGet('Create');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', false);

        return
            'Folder created successfully. '
            . static::buttonGet('path', $curr_args['path'], 'Go to folder');
    }

    public static function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/ {
        return $curr_args;
    }
}

class TreeDeleteDirectoryModule implements UserModuleInterface
{
    use FormTools;
    use CheckPathName;

    const MOD_UUID = 'e5410a89-cecf-4a70-9a80-15c5f949256a';
    const MOD_NAME = 'Delete current directory';
    const MOD_PARENT = TreeUnsafeOpsGroup::MOD_UUID;

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        if (!static::deleteFolderContent($prnt_args['path']))
            return ['status' => -3, 'msg' => 'Unable to fully delete folder'];

        $args->getStack()->reProcess($args, TreeViewModule::MOD_UUID);

        return ['status' => 0, 'deleted' => $delete];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return static::buttonGet('proc', static::MOD_UUID, 'Delete', [
            'style' => 'border-color: red;',
            'title' => 'This operation is irreversable',
            'onclick' => 'return confirm(\'Definitely delete current directory?\nCurrent folder and ITS CONTENT will be deleted\');'
        ]);
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', true);

        return 'Folder successfully deleted';
    }

    public static function deleteFolderContent(/*string*/ $path)/*: bool*/
    {
        if (!is_dir($path))
            return false;
        
        $list = scandir($path, SCANDIR_SORT_NONE);
        if ($list === false)
            return false;

        $status = true;
        foreach ($list as $file) {
            if ($file == '..' || $file == '.')
                continue;

            $result = true;
            $file = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file)) {
                if (!(static::deleteFolderContent($file) && rmdir($file)))
                    $result = false;
            } else {
                unlink($file);
            }
        }

        if ($status)
            $status = rmdir($path);

        return $status;
    }
}

class TreeFileUploadModule implements
    UserModuleInterface,
    MachineModuleInterface
{
    use FormTools;
    use CheckPathName;

    const MOD_UUID = '7cf63f39-634f-4eed-945a-d038cc2b57aa';
    const MOD_NAME = 'Upload file';
    const MOD_PARENT = TreeDirOpsGroup::MOD_UUID;

    public static $FORM_FIELDS = [
        'upload_file' => [
            't_file_plain',
            'c_not_empty' => [],
            'o_field_schema' => [
                'type' => 'file',
            ],
            'o_prevent_export' => ['preserve', 'display'],
        ],
        'upload_append' => [
            't_bool',
            'c_not_empty' => [],
            'o_prevent_export' => ['preserve', 'display'],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        $file = static::valueGet($args, 'upload_file');
        if (is_null($file))
            return ['status' => -3, 'msg' => 'No file uploaded'];
        else if ($file['error'] !== 0)
            return ['status' => -4, 'msg' => 'File upload failed'];

        $filename = $file['name'];
        if (!static::checkPathName($filename))
            return ['status' => -5, 'msg' => 'Invalid file name'];

        $path = $prnt_args['path'];
        $targetFile = $path . DIRECTORY_SEPARATOR . $filename;
        $targetExists = file_exists($targetFile);

        if ($targetExists) {
            if (!is_writable($targetFile))
                return ['status' => -6, 'msg' => 'Target file isn\'t writable'];
            // pass the check
        } else if (!is_writable($path))
            return ['status' => -7, 'msg' => 'Target directory isn\'t writable'];

        if (!touch($target))
            return ['status' => -8, 'msg' => 'Unable to create file'];

        $append = static::valueGet($args, 'upload_append');
        if (!$append) {
            if (!move_uploaded_file($file['tmp_name'], $targetFile))
                return [
                    'status' => -9,
                    'msg' => 'Unable to move uploaded file',
                ];
        } else { 
            $from = fopen($file['tmp_name'], 'rb');
            $to = fopen($target, 'ab');
            if (!$from || !$to)
                return ['status' => -10, 'msg' => 'Unable to open files'];

            if (!stream_copy_to_stream($from, $to) === false)
                return ['status' => -11, 'msg' => 'Unable to append file'];
        }

        $args->getStack()->reProcess($args, TreeDirOpsGroup::MOD_PARENT);

        return [
            'status' => 0,
            'path' => $targetFile,
            'created' => $targetExists,
            'appended' => $append,
        ];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            '<border class="stack" style="display: inline-block;text-align: center;">'
                . static::fieldGet($args, 'upload_file')
                . 'Maximum file size: ' . ini_get('upload_max_filesize')
            . '</border>'
            . static::submitGet('Upload');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', false);

        return
            'File uploaded successfully. '
            . '<a '
                . 'href="?'
                .  http_build_query([
                    'proc' => 'view',
                    'path' => $curr_args['path']
                ]) . '" '
                . 'target="_blank" '
            . '>file</a>';
    }

    public static function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/ {
        return $curr_args;
    }
}

abstract class HiddenGroupModule
    extends GroupModule
    implements UserModuleInterface
{
    public static function form(ArgsStore $args)/*: ?string*/
    {
        $result =
            '<details class="stack" style="border-bottom: lightgray solid 1px;">'
                . '<summary class="border">' . static::MOD_NAME . '</summary>';
        foreach (static::moduleIter() as $uuid => $cls) {
            $result .=
                '<fieldset>'
                    . '<legend>' . $cls::MOD_NAME . '</legend>'
                    . $cls::form($args)
                . '</fieldset>';
        }
        $result .=
            '</details>';

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
            '<border class="result">'
                . $result
            . '</border>';
    }
}

class TreeDirOpsGroup extends HiddenGroupModule implements UserModuleInterface
{
    const MOD_UUID = '5b833d86-cc67-486d-b79d-f4275ac35bd0';
    const MOD_NAME = 'Directory Operations';
    const MOD_PARENT = TreeViewModule::MOD_UUID;
}

class TreeMassOpsGroup extends HiddenGroupModule implements UserModuleInterface
{
    const MOD_UUID = '070ae8a0-3792-4fca-aec0-d82647664026';
    const MOD_NAME = 'Mass Operations';
    const MOD_PARENT = TreeViewModule::MOD_UUID;
}

class TreeUnsafeOpsGroup extends HiddenGroupModule implements UserModuleInterface
{
    const MOD_UUID = '1c5b7e6f-bd6c-4dc8-9016-737c59306118';
    const MOD_NAME = 'Unsafe Operations';
    const MOD_PARENT = TreeViewModule::MOD_UUID;
}

class TreeViewModule implements UserModuleInterface, MachineModuleInterface
{
    use FormTools;
    use CoreSizeConv;

    const MOD_UUID = '8bf01fa7-207c-4cfd-aad8-df9de6ddab21';
    const MOD_NAME = 'File Tree View';
    const MOD_PARENT = null;

    public static $FORM_FIELDS = [
        'path' => [
            't_str',
            'c_not_empty' => [],
            'o_default_value' => '.',
            'o_field_schema' => [
                'placeholder' => 'File path',
                'attrs' => [
                    'id' => 'path',
                    'rows' => 1,
                    'autofocus' => '',
                    'style' => 'text-align: center; flex-grow: 1;',
                    'autocomplete' => 'off',
                ],
            ],
        ],
        'sort' => [
            't_str',
            'o_default_value' => 'filename:asc',
            'o_field_schema' => [
                'label' => ['Sort by'],
            ],
        ],
        'search' => [
            't_str',
            'o_field_schema' => [
                'placeholder' => 'Search',
            ],
        ],
        'limit' => [
            't_int',
            // 'c_not_equal' => [0],
            'c_limit' => ['mode' => 'val', 'minq' => 0, 'maxq' => 150],
            'o_default_value' => 50,
            'o_field_schema' => [
                'placeholder' => 'Page limit',
                'attrs' => [
                    'size' => 5,
                    'autocomplete' => 'off',
                ],
            ],
        ],
        'offset' => [
            't_int',
            'c_limit' => ['mode' => 'val', 'minq' => 0],
            'o_default_value' => 0,
        ],
    ];
    public static $TREE_DISPLAY_MOD = [
        'filename' => [self::class, 'treeModFilename'],
        'perms' => [self::class, 'treeModPerms'],
    ];
    public static $TREE_MAIN_INFO = [
        'filename' => [
            'body_cell_attrs' => ['style' => 'text-align: left;'],
        ],
        'size' => [
            'head_cell_attrs' => ['style' => 'flex: 0.5;'],
            'body_cell_attrs' => ['style' => 'flex: 0.5;'],
        ],
        'mtime' => [],
    ];
    public static $TREE_HIDN_INFO = [
        'perms' => [],
        'owner' => [],
        'group' => [],
        'mime' => [],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        $path = static::valueGet($args, 'path');
        $path = realpath($path);
        if ($path === false)
            return ['status' => -1, 'msg' => 'Invalid path'];

        $list = new CoreFSIter($path);
        if (!$list->isOk())
            return ['status' => -2, 'msg' => $list->okReason()];

        $sort = static::valueGet($args, 'sort');
        $sort = explode(':', $sort, 2);
        if (count($sort) == 2) {
            $list = $list->sort($sort[0], $sort[1] === 'asc');
        }

        $search = static::valueGet($args, 'search');
        if ($search)
            $list->match($search);

        $list = $list->dirsOnTop();
        $count = count($list);
        $normSize = $list->getNormSize();

        $offset = static::valueGet($args, 'offset');
        if ($offset > $count)
            $offset = $count;

        $limit = static::valueGet($args, 'limit');
        $offset = static::valueGet($args, 'offset');
        if ($limit || $offset)
            $list->limit($offset, $limit);

        return [
            'status' => 0,
            'path' => $path,
            'list' => $list,
            'count' => $count,
            'norm_size' => $normSize,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public static function form(ArgsStore $args)/*: string*/
    {
        $path = static::valueGet($args, 'path');
        $realpath = realpath($path);
        if ($realpath !== false)
            $path = $realpath;
        
        $path = dirname($path);

        return
            '<style>'
                . 'ctcell {'
                    . 'text-align: center;'
                    . 'flex: 1;'
                . '}'
                . '.ctrow,'
                . 'ctrow {'
                    . 'display: flex;'
                    . 'text-align: center;'
                    . 'margin-top: 2px;'
                    . 'padding-bottom: 2px;'
                    . ''
                . '}'
            . '</style>'
            . '<border class="no_border" style="display: flex; justify-content: middle;">'
                . static::fieldGet($args, 'path')
                . static::buttonGet('path', $path, '▲')
            . '</border>';
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args);

        $limit = $curr_args['limit'];
        $offset = $curr_args['offset'];
        $count = $curr_args['count'];
        
        $valueCall = function ($key, $fileInfo) use ($args) {
            $data = CoreFSIter::$DEFAULT_SORT_VALUES[$key];
            try {
                $value = $fileInfo->{$data['display_call']}();
                if (array_key_exists($key, static::$TREE_DISPLAY_MOD))
                    $value = static::$TREE_DISPLAY_MOD[$key](
                        $args,
                        $fileInfo,
                        $key,
                        $value
                    );
            } catch (Exception) {
                $value = '????';
            }

            return $value;
        };

        $buildCache = function (array $schema)/*: array*/
        {
            $cache = [];
            foreach (static::$TREE_MAIN_INFO as $key => $params) {
                $currentCache = [];

                foreach (
                    [
                        'head_cell_attrs',
                        'body_cell_attrs',
                        'hidn_cell_attrs'
                    ] as $cacheKey
                )
                    $currentCache[$cacheKey] = (
                        array_key_exists($cacheKey, $params)
                            ? static::buildAttrs($params[$cacheKey])
                            : ''
                    );

                $cache[$key] = $currentCache;
            }

            return $cache;
        };
        $mainCache = $buildCache(static::$TREE_MAIN_INFO);
        $hidnCache = $buildCache(static::$TREE_MAIN_INFO);

        $keysCall = function () use ($mainCache) {
            foreach (static::$TREE_MAIN_INFO as $key => $params) {
                $data = CoreFSIter::$DEFAULT_SORT_VALUES[$key];
                $cache = $mainCache[$key];

                yield 
                    '<ctcell ' .  $cache['head_cell_attrs'] . '>'
                        . $data['name']
                        . (array_key_exists('sort_call', $data)
                            ? '<div>'
                                . static::buttonGet('sort', $key . ':asc', '▲')
                                . static::buttonGet('sort', $key . ':desc', '▼')
                            . '</div>'
                            : ''
                        )
                    . '</ctcell>';
            }
        };
        $dataCall = function ($list) use (
            $args,
            $valueCall,
            $mainCache,
            $hidnCache
        ) {
            foreach ($list as $path => $fileInfo) {
                $mainResult = '';
                foreach (static::$TREE_MAIN_INFO as $key => $params) {
                    $cache = $mainCache[$key];
                    $mainResult .=
                        '<ctcell ' . $cache['body_cell_attrs'] . '>'
                            .'<mpre>'
                                . $valueCall($key, $fileInfo)
                            . '</mpre>'
                        . '</ctcell>';
                }

                $hiddenResult = '';
                foreach (static::$TREE_HIDN_INFO as $key => $params) {
                    $data = CoreFSIter::$DEFAULT_SORT_VALUES[$key];
                    $cache = $hidnCache[$key];
                    $hiddenResult .=
                        '<ctcell ' . $cache['hidn_cell_attrs'] . '>'
                            .'<mpre>'
                                . $data['name'] . "\n" . $valueCall($key, $fileInfo)
                            . '</mpre>'
                        . '</ctcell>';
                }

                $result =
                    '<details style="border-bottom: lightgray solid 1px;">'
                        . '<summary class="ctrow">'
                            . $mainResult
                            // . '<border>⠶</border>'
                        . '</summary>'
                        . '<border class="ctrow">' . $hiddenResult . '</border>'
                    . '</details>';

                yield $result;
            }
        };

        $pagesText =
            '<border style="font-family: monospace;">'
                . ($limit && $count > $limit
                    ? ceil($offset / $limit)
                        . ' / '
                        . ceil($count / $limit)
                    : '1 / 1'
                )
                . ' (' . $count . ' or ' . $curr_args['norm_size'] . ') '
                .  (($freeSpace = disk_free_space($curr_args['path'])) === false
                    ? '---'
                    : static::normalizeSize($freeSpace)
                )
                . ' Free'
            . '</border>';
        $mOffsetBtn = static::buttonGet('offset', $offset - $limit, '◀', (
            $limit && $offset > 0
                ? []
                : ['disabled' => '']
        ));
        $pOffsetBtn = static::buttonGet('offset', $offset + $limit, '▶', (
            $limit && $count >= ($offset + $limit)
                ? []
                : ['disabled' => '']
        ));

        $result =
            $mOffsetBtn
            . static::fieldGet($args, 'limit')
            . $pOffsetBtn
            . static::fieldGet($args, 'search')
            . '<input type="submit" value="Update">'
            . $pagesText
            . $args->getStack()->nextDisplay(
                $args,
                $curr_args,
                $prnt_args
            )
            // . ($nextResult
            //     ? '<border>' . $nextResult . '</border>'
            //     : ''
            // )
            . '<border class="stack">';
        
        foreach (ModIndex::getParents(static::MOD_UUID) as $uuid => $cls) {
            if (!is_subclass_of($cls, UserModuleInterface::class, true))
                continue;

            $result .= $cls::form($args);
        }

        $result .= '<ctrow>';
        foreach ($keysCall() as $data) {
            $result .= $data;
        }
        $result .= '</ctrow>';

        foreach ($dataCall($curr_args['list']) as $data) {
            $result .= $data;
        }
        $result .=
            '</border>'
            . '<div>'
                . '<a href="#menu"><input type="button" value="▲"></a>'
                . $mOffsetBtn
                . $pOffsetBtn
                . $pagesText
            . '</div>';

        return $result;
    }

    public static function displayArray(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?array*/ {
        if ($curr_args['status'] < 0)
            return $curr_args;

        $result = [];
        foreach ($curr_args['list'] as $path => $fileInfo) {
            foreach(CoreFSIter::$DEFAULT_SORT_VALUES as $key => $data) {
                $result[$path][$key] = $fileInfo->{$data['raw_call']}();
            }
        }

        return [
            'status' => 0,
            'data' => $result
        ];
    }

    protected static function treeModFilename(
        ArgsStore $args,
        SplFileInfo $fileInfo,
        /*string*/ $key,
        /*string*/ $value
    )/*: string*/ {
        $path = $fileInfo->getRealPath();
        if (!$path)
            $path = '';

        try {
            $mime = $fileInfo->getMIME();
        } catch (RuntimeException) {
            $mime = 'unknown';
        }

        $mime_data = explode('/', $mime, 2);
        $color = 'black';
        $categotyColor = [
            'directory' => 'lightgray',
            'text' => 'skyblue',
            'application' => 'coral',
            'video' => 'lightpink',
            'audio' => 'orange',
            'image' => 'yellowgreen',
            'message' => 'brown',
            'model' => 'chocolate',
            'multipart' => 'goldenrod',
        ];
        if (array_key_exists($mime_data[0], $categotyColor))
            $color = $categotyColor[$mime_data[0]];

        $failed = !$path || $mime == 'unknown';

        if ($mime == 'directory' || $failed)
            return static::buttonGet('path', $path, $value,
                [
                    'title' => 'Type: ' . $mime,
                    'style' => 'border-color: ' . $color . ';',
                ] + ($failed ? ['disabled' => ''] : [])
            );
        else
            return
                '<a '
                    . 'href="?'
                    .  http_build_query([
                        'proc' => 'view',
                        'file' => $path
                    ]) . '" '
                    // . 'target="_blank" '
                . '>'
                    . static::buttonGet('', '', $value,
                        [
                            'title' => 'Type: ' . $mime,
                            'style' => 'border-color: ' . $color . ';',
                        ] + ($failed ? ['disabled' => ''] : []),
                        'button'
                    )
                . '</a>';
    }

    protected static function treeModOwner(
        ArgsStore $args,
        SplFileInfo $fileInfo,
        /*string*/ $key,
        /*string*/ $value
    )/*: string*/ {
        $user = posix_getpwuid($value);

        return $user['name'] . ' (' . $user['uid'] . ') ';
    }

    protected static function treeModGroup(
        ArgsStore $args,
        SplFileInfo $fileInfo,
        /*string*/ $key,
        /*string*/ $value
    )/*: string*/ {
        $group = posix_getgrgid($value);

        return $group['name'] . ' (' . $group['gid'] . ') ';
    }

    protected static function treeModPerms(
        ArgsStore $args,
        SplFileInfo $fileInfo,
        /*string*/ $key,
        /*string*/ $value
    )/*: string*/ {
        try {
            return 
                $fileInfo->getNormPerms()
                . ' (' . substr(sprintf('%o', $fileInfo->getPerms()), -4) . ')';
        } catch (Exception) {
            return '(????) ?????????';
        }
    }
}

if (extension_loaded('posix')) {
    TreeViewModule::$TREE_DISPLAY_MOD['owner'] = [
        TreeViewModule::class,
        'treeModOwner'
    ];
    TreeViewModule::$TREE_DISPLAY_MOD['group'] = [
        TreeViewModule::class,
        'treeModGroup'
    ];
}

ModIndex::AddModule(TreeViewModule::class, true);

ModIndex::AddModule(TreeDirOpsGroup::class);
// ModIndex::AddModule(TreeMassOpsGroup::class);
ModIndex::AddModule(TreeUnsafeOpsGroup::class);
ModIndex::AddModule(TreeFileUploadModule::class);
ModIndex::AddModule(TreeCreateDirectoryModule::class);
ModIndex::AddModule(TreeDeleteDirectoryModule::class);
ModIndex::AddModule(TreeCreateFileModule::class);
/**&
@module_content
  uuid: 3ffb56ac-4362-4bd1-bbc8-99a47c88a8c3
  name: CFM settings
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
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
        $cls = SettingsModule::$AUTH_CLS;
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
        if (!$username && !$password)
            return ['status' => 1];
        if (!$username || !$password)
            return [
                'status' => -1,
                'msg' => 'Username or password does not meet the requirements'
            ];

        $cls = SettingsModule::$AUTH_CLS;
        $result = $cls::addUser($args, $username, $password);
        if ($result['status'] < 0)
            return ['status' => -2, 'msg' => $result['msg']];

        return ['status' => 0];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return
            static::fieldGet($args, 'new_username')
            .static::fieldGet($args, 'new_password')
            . static::submitGet('Create');
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

        $result = $args->cfgIGet()->dumpToPart($prnt_args['path']);
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => 'Unable to add new user'];

        return ['status' => 0];
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

        $cls = SettingsModule::$AUTH_CLS;
        if ($cls::currentUser($args) === $username)
            return ['status' => -2, 'msg' => 'Unable to delete current user'];

        $result = $cls::delUser($args, $username);
        if ($result['status'] < 0)
            return ['status' => -3, 'msg' => $result['msg']];

        $result = $args->cfgIGet()->dumpToPart($prnt_args['path']);
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
}

class SettingsModule extends PlainGroupModule implements UserModuleInterface
{
    const MOD_UUID = '6859a9c9-132d-447f-8b4f-4484064e877a';
    const MOD_NAME = 'Settings';
    const MOD_PARENT = null;

    public static $AUTH_CLS = CoreAuthenticationModule::class;

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

ModIndex::addModule(SettingsModule::class, true);
ModIndex::addModule(UserSettingsGroup::class);
ModIndex::addModule(SetListUserModule::class);
ModIndex::addModule(SetAddUserModule::class);
ModIndex::addModule(SetRemoveUserModule::class);
ModIndex::addModule(SetupFirstUserModule::class);
/**&
@module_content
  uuid: c271e92a-e358-4580-bd26-828f36b83a4d
  id: main
  name: CFM's main RLM
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
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
                . 'input[type=radio]:checked + label {'
                    . 'background-color: orange;'
                    . 'transition-duration: 0.1s;'
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
/**&
@module_content
  uuid: c5748ec8-00e4-4b4d-bd49-f8a04e85fa52
  id: init
  name: Init
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
$main = function ()/*: void*/
{
    $args = new ArgsStore();

    $rtcfg = new RuntimeConfig($args);
    $smmgr = new ServiceManager($args, ModIndex::$TI);
    $mstck = new ModStack($args);

    $args->domainAdd($_FILES, 'files');
    $args->domainAdd($_POST, 'post');
    $args->domainAdd($_GET, 'get');

    // $page = (isset($args['page']) && is_string($args['page'])
    //     ? $args['page']
    //     : 'default'
    // );
    $page = 'default';
    if (MwChains::invokeFullChain($args, $page) < 0)
        http_response_code(404);
};
/**&
@module_content
  uuid: 78ba657f-42cd-48ca-a710-62b6a2e06080
  id: fused_config
  name: CFM's Fused Config
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
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
ModIndex::addParent(
    FormProtectModule::class,
    CFMMain::MOD_UUID
);

ModIndex::addParent(
    FileViewDeleteModule::class,
    FormProtectModule::MOD_UUID
);
ModIndex::addParent(
    TreeDeleteDirectoryModule::class,
    FormProtectModule::MOD_UUID
);
/**&
@module_content
  uuid: b0461c65-cf71-4210-a462-0bedb4aee19e
  id: init_exec
  name: Init
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/
$main();
