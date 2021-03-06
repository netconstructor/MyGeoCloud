<?php
session_start();
include '../../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';

$postgisdb = $parts[6]; // We change the db to user
 
switch($_REQUEST['type'])
{
	case "xml":
		header('Content-Type: text/xml');
	break;
	
	case "json":
		header('Content-Type: text/plain');
	break;
	case "text":
		header('Content-Type: text/plain');
	break;
	default:
		header('Content-Type: text/html');
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

include_once("libs/JSON.php");
include_once("PEAR/XML/Serializer.php");

$postgisObject = new postgis();

$json = &new Services_JSON();

$serializer_options = array (
		   'addDecl' => TRUE,
   		   'encoding' => 'UTF-8',
		   'indent' => '  ',
		   'rootName' => 'response',
		); 
$serializer = &new XML_Serializer($serializer_options);
?>
