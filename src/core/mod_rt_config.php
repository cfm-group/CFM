<?php

/**&
  @module_content
    uuid: 5881a360-d9b6-461d-b8ba-6a82f7197527
    name: RT Config Tools
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
interface ConfigProviderInterface
{
    public static function configGet()/*: array*/;
}

class RuntimeConfig implements MiddlewareInterface
{
    protected static /*array*/ $DEFAULT_RT_CONFIG = [];
    protected static /*array*/ $DEFAULT_CONFIGS = [];

    protected ArgsStore $args;

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
