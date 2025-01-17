<?php

chdir(substr(__DIR__,0,-5));

define('IS_CRON',true);

//load core
require('includes/application_core.php');

//include ext plugins
require('plugins/ext/application_core.php');

//load app lagn
if(is_file($v = 'includes/languages/' . CFG_APP_LANGUAGE))
{
	require($v);
}

if(is_file($v = 'plugins/ext/languages/' . CFG_APP_LANGUAGE))
{
	require($v);
}

$app_users_cache  = users::get_cache();

$currencies = new currencies;
$currencies->update(CFG_CURRENCIES_UPDATE_MODULE);
