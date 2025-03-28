<?php

/**&
  @module_content
    uuid: c5748ec8-00e4-4b4d-bd49-f8a04e85fa52
    id: init
    name: Init
    author: trashlogic
    license: AGPL-3.0-only
    verm: 1
    required: true
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

    $page = (isset($args['page']) && is_string($args['page'])
        ? $args['page']
        : 'default'
    );
    if (MwChains::invokeFullChain($args, $page) < 0)
        http_response_code(404);
};
