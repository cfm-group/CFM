<?php

/**&
  @module_content
    uuid: 2dd9d09b-60c9-4a4c-bdec-c11aeeb88348
    name: Filesystem core
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/
trait CoreSizeConv
{
    public function getNormSize()/*: double*/
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        
        $size = $this->getSize();
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
        $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        
        $size = $this->getSize();
        $n = 0;
        while ($size > 1024) {
            $size /= 1024;
            $n++;
        }

        return round($size, 1) . $units[$n];
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
        if (!file_exists($path)) {
            $this->okReason = 'Invalid path';
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
