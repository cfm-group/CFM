<?php

/**&
  @module_content
    uuid: f5adf74b-8cf6-410e-9573-1e97c6524d43
    name: Filesystem tree view
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
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

        // if (!touch($target))
        //     return ['status' => -8, 'msg' => 'Unable to create file'];

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
                if (array_key_exists($key, static::$TREE_DISPLAY_MOD)) {
                    $call = static::$TREE_DISPLAY_MOD[$key];
                    $value = $call(
                        $args,
                        $fileInfo,
                        $key,
                        $value
                    );
                }
            } catch (Exception $e) {
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
        } catch (RuntimeException $e) {
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
        } catch (Exception $e) {
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

ModIndex::addModule(TreeViewModule::class);

ModIndex::addModule(TreeDirOpsGroup::class);
// ModIndex::addModule(TreeMassOpsGroup::class);
ModIndex::addModule(TreeUnsafeOpsGroup::class);
ModIndex::addModule(TreeFileUploadModule::class);
ModIndex::addModule(TreeCreateDirectoryModule::class);
ModIndex::addModule(TreeDeleteDirectoryModule::class);
ModIndex::addModule(TreeCreateFileModule::class);

ModIndex::addParent(TreeDeleteDirectoryModule::class, FormProtectModule::MOD_UUID);
