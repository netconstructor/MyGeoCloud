<?php
session_start();
include 'conf/main.php';
include 'libs/oauth/EpiCurl.php';
include 'libs/oauth/EpiOAuth.php';
include 'libs/oauth/EpiTwitter.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/users.php';
include 'model/databases.php';
include 'model/classes.php';
include 'model/wmslayers.php';
include 'model/settings_viewer.php'; // we need to get pw for http authentication
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
 
$_SESSION['screen_name'] = $parts[2];

if ($parts[1]=="store" || $parts[1]=="editor") {
	$db = new databases();
	if (!$parts[2]) {
		die("<script>window.location='/?db=false'</script>");
	}
	if (!$db->doesDbExist($parts[2])) {
		if ($db->doesDbExist(postgis::toAscii($parts[2],NULL,"_"))) {
			die("<script>window.location='/store/".postgis::toAscii($parts[2])."'</script>");
		}
		else{
			die("<script>window.location='/?db=false'</script>");
		}
	}
	include("inc/oauthcheck.php");
}
?>
<!DOCTYPE html>
<html >
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
	<script type="text/javascript">

	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', 'UA-28178450-1']);
	  _gaq.push(['_setDomainName', 'mygeocloud.com']);
	  _gaq.push(['_trackPageview']);
	
	  (function() {
	    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	
	</script>