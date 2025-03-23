<?php

/**&
  @module_content
    uuid: 30146e5d-0afd-4757-9fe1-e14507e9542c
    name: Microcore module
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
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
