<?php

/**&
  @module_content
    uuid: 6497a112-7fc9-4eb7-888a-f0b20eb49057
    name: File viewer
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
&*/

class FileViewDeleteModule implements UserModuleInterface
{
    use FormTools;

    const MOD_UUID = '04ba0fb6-73c2-424f-8dce-014e3f56bf79';
    const MOD_NAME = 'Delete file';
    const MOD_PARENT = FileViewModule::MOD_UUID;

    public static $FORM_FIELDS = [
        'delete_confirm' => [
            't_bool',
            'c_not_empty' => [],
            'o_default_value' => false,
            'o_prevent_export' => ['preserve', 'display'],
        ],
    ];

    public static function process(
        ArgsStore $args,
        array $prnt_args = []
    )/*: array*/ {
        if ($prnt_args['status'] < 0)
            return $prnt_args;

        $delete = static::valueGet($args, 'delete_confirm');
        if (!$delete)
            return ['status' => 0, 'deleted' => $delete];

        if (!unlink($prnt_args['path']))
            return ['status' => -3, 'msg' => 'Unable to delete file'];

        $args->getStack()->reProcess($args, FileViewModule::MOD_UUID);

        return ['status' => 0, 'deleted' => $delete];
    }

    public static function form(ArgsStore $args)/*: ?string*/
    {
        return static::submitGet('Delete');
    }

    public static function display(
        ArgsStore $args,
        array $curr_args,
        array $prnt_args = []
    )/*: ?string*/ {
        if ($curr_args['status'] < 0)
            return static::errorGet($curr_args, '', true);

        if ($curr_args['deleted'])
            return 'File successfully deleted';
        else
            return static::buttonGet('delete_confirm', 1, 'Definitely delete');
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

    public static function form(
        ArgsStore $args,
        array $prnt_args = []
    )/*: ?string*/ {
        return static::submitGet('Download');
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

class FileViewModule implements UserModuleInterface
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
            return [ 'status' => -1, 'msg' => 'File not found'];
        if (!is_readable($path))
            return [ 'status' => -2, 'msg' => 'Permission denied. Unable to read file'];

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
            ($moduleResult
                ? '<border class="result">'
                    . $moduleResult
                . '</border>'
                : ''
            )
            . '<a '
                . 'href="?'
                    .  http_build_query([
                        'proc' => 'download',
                        'file' => $curr_args['path']
                    ])
                . '" '
                . 'target="_blank" '
            . '>'
                . '<button type="button">Download</button>'
            . '</a>'
            . '<button type="submit" name="proc" value="delete">'
                . 'Delete'
            . '</button>'
            . '<input disabled="" type="text" name="file_new_name" placeholder="New name">'
            . '<button disabled="" type="submit" name="" value="rename">'
                . 'Rename'
            . '</button>'
            . '<button disabled="" type="submit" name="" value="copy">'
                . 'Move/Copy'
            . '</button>';
    }
}

ModIndex::addModule(FileViewModule::class);
ModIndex::addModule(FileDownloadModule::class);
ModIndex::addModule(FileViewDeleteModule::class);
