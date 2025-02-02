<?php

/**
 * --- Part
 * --- Header
 * \/**& // Header trigger
 *   @module_content // Command
 * --- Command arguments
 *     uuid: 
 *     name: Example
 *     author: Example
 *     license: AGPL-3.0-only
 *     verm: 1
 * --- /Command arguments
 * &*\/ // Header exit
 * --- /Header
 * 
 * --- Part body
 * class Example ...
 * --- /Part body
 * 
 * --- /Part
 */

/**&
  @module_content
    uuid: edcabf11-4f6c-4f39-b510-9f1fe099ddd9
    name: Infuser module
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
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

    public static function cmdAdd(/*string*/ $cls)/*: bool*/
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
        foreach (static::defuse($args, $sourcePath) as $key => $data) {
            $targetFile = $targetPath . DIRECTORY_SEPARATOR . $key . '.php';
            $handle = fopen($targetFile , 'wb');
            if ($handle === false)
                continue;

            foreach (static::infuseGen([$data]) as $line)
                fwrite($handle, $line);

            fclose($handle);
        }
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
