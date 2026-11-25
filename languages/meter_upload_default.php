<?php
$dictionary=array(
'MU_PROVIDER'=>'Provider',
'MU_ENDPOINT'=>'API endpoint',
'MU_ACCOUNT'=>'Account ID',
'MU_TOKEN'=>'Access token (not shown again)',
'MU_METERS'=>'Meter codes, one per line: code=description',
'MU_ENABLED'=>'Enable upload',
'MU_TEST'=>'Test connection',
'MU_HINT'=>'Get the endpoint and token from your provider personal account. Nothing is sent until api() is called.',
'MU_ERROR'=>'Request failed',
'MU_STATUS'=>'Upload status',
'MU_LAST_RESULT'=>'Last result',
'MU_UNIT' => 'Unit',
'MU_SYNC' => 'Sync queue',
);
foreach($dictionary as $k=>$v){if(!defined('LANG_'.$k))define('LANG_'.$k,$v);}
