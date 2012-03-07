<?php
session_start();
ini_set("display_errors", "On");
error_reporting(3);

include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'model/tables.php';
$postgisdb = $parts[3];
?>
<!DOCTYPE html>
<html>
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
	
	<link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
	<linjk href="/js/bootstrap/css/bootstrap-responsive.css" rel="stylesheet">
	<linhk href="http://twitter.github.com/bootstrap/assets/css/docs.css" rel="stylesheet">
	<style>
	 body {
        padding-top: 60px;
        padding-bottom: 40px;
	}
	</style>
 </head>