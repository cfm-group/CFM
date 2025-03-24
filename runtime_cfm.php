<?php

/**&
@module_content
  uuid: 2b201fee-186c-4b30-b6f7-e451e50d382b
  name: CFM build-less microcore
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/

require('src/core/mod_microcore.php');
require('src/core/mod_infuser.php');
require('src/core/mod_rt_config.php');

/**&
@runtime_config
&*/

/**&
@module_content
  uuid: 509a1c14-82f6-4401-9046-db7cb76ea44a
  name: CFM build-less runtime
  verm: 1
  author: trashlogic
  license: AGPL-3.0-only
  required: 1
&*/

$root = '.';
$modules = require_once('src/specific/cfm/runtime_mods.php');

foreach ($modules as $path => $mods) {
    require_once($root . DIRECTORY_SEPARATOR . $path);
}
