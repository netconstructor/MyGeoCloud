<?php
session_start();

header('Content-Type:text/xml; charset=UTF-8', TRUE);
header('Connection:close', TRUE);

include "../conf/main.php";
include('../inc/user_name_from_uri.php');

include "../libs/phpgeometry_class.php";
include "../libs/functions.php";
include "../model/users.php";
include "../model/tables.php";
include "../model/versions.php";
include("../model/settings_viewer.php"); // we need to get pw for http authentication

include "../libs/PEAR/XML/Unserializer.php";
include "../libs/PEAR/XML/Serializer.php";
include "../libs/PEAR/Cache_Lite/Lite.php";

include 'convertgeom.php';
include '../libs/oauth/EpiCurl.php';
include '../libs/oauth/EpiOAuth.php';
include '../libs/oauth/EpiTwitter.php';
include 'explodefilter.php';

include '../libs/FirePHPCore/FirePHP.class.php';
include '../libs/FirePHPCore/fb.php';

include '../model/geometry_columns.php';


//$userFromUri = "mhoegh"; // for testing

logfile::write($userFromUri."\n\n");

if (!$gmlNameSpace) {
	$gmlNameSpace = $userFromUri;
}
if (!$gmlNameSpaceUri) {
	$gmlNameSpaceUri = "http://twitter/{$userFromUri}";
}


// We connect to the users db
$postgisdb = $userFromUri;
$srs=$srsFromUri;
$postgisschema = $schemaFromUri;



$postgisObject = new postgis();
//$user = new users($userFromUri);
//$version = new version($user);

$geometryColumnsObj = new GeometryColumns();



function microtime_float()
{
	list($utime, $time) = explode(" ", microtime());
	return ((float)$utime + (float)$time);
}
$startTime = microtime_float();

//ini_set("display_errors", "On");

$thePath= "http://".$_SERVER['SERVER_NAME'].$_SERVER['REDIRECT_URL'];
//$thePath= "http://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
$server="http://".$_SERVER['SERVER_NAME'];
$BBox=null;
//end added
$currentTable=null;
$currentTag=null;
$gen=array();
$gen[0]="";
$level=0;
$depth=0;
$tables=array();
$fields=array();
$wheres=array();
$limits=array();

logfile::write("\nRequest\n\n");
logfile::write($HTTP_RAW_POST_DATA."\n\n");

$unserializer_options = array (
	'parseAttributes' => TRUE,
	'typeHints' => FALSE
);
$unserializer = &new XML_Unserializer($unserializer_options);

/*$HTTP_RAW_POST_DATA = '<Transaction service="WFS" version="1.0.0">
 <Insert><plannr_planid><the_geom><MultiPolygon srsName="900913"><polygonMember><Polygon><outerBoundaryIs><LinearRing><coordinates>1097259.127828,7544536.183181 1086252.1957569,7543313.1907287 1090226.9212271,7538574.0949759 1097259.127828,7544536.183181</coordinates></LinearRing></outerBoundaryIs></Polygon></polygonMember></MultiPolygon></the_geom><planid></planid><plannr></plannr></plannr_planid></Insert>
 <Insert><martin><the_geom><MultiPolygon srsName="900913"><polygonMember><Polygon><outerBoundaryIs><LinearRing><coordinates>1097259.127828,7544536.183181 1086252.1957569,7543313.1907287 1090226.9212271,7538574.0949759 1097259.127828,7544536.183181</coordinates></LinearRing></outerBoundaryIs></Polygon></polygonMember></MultiPolygon></the_geom><planid></planid><plannr></plannr></martin></Insert>
 </Transaction>';*/

/*$HTTP_RAW_POST_DATA = '<?xml version="1.0"?><DescribeFeatureType  version="1.1.0"  service="WFS"  xmlns="http://www.opengis.net/wfs"  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"  xsi:schemaLocation="http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/wfs.xsd">    <TypeName>california_coastline</TypeName></DescribeFeatureType>';
 */

// Post method is used
if ($HTTP_RAW_POST_DATA) {
	//$forUseInSpatialFilter = $HTTP_RAW_POST_DATA; // We store a unaltered version of the raw request
	$HTTP_RAW_POST_DATA = dropNameSpace($HTTP_RAW_POST_DATA);
	logfile::write($HTTP_RAW_POST_DATA."\n\n");

	$status = $unserializer->unserialize($HTTP_RAW_POST_DATA);
	$arr = $unserializer->getUnserializedData();
	$request = $unserializer->getRootName();
	//print_r($arr);
	switch ($request){
		case "GetFeature":
			if (!is_array($arr['Query'][0])){
				$arr['Query'] = array(0 => $arr['Query']);
			}
			for ($i=0;$i<sizeof($arr['Query']);$i++){
				if (!is_array($arr['Query'][$i]['PropertyName'])) {
					$arr['Query'][$i]['PropertyName'] = array(0 => $arr['Query'][$i]['PropertyName']);
				}
			}
			$HTTP_FORM_VARS["REQUEST"] = "GetFeature";
			foreach ($arr['Query'] as $queries) {
				$HTTP_FORM_VARS["TYPENAME"].= $queries['typeName'].",";
				if ($queries['PropertyName'][0]) {
					foreach ($queries['PropertyName'] as $PropertyNames) {
						// We check if typeName is prefix and add it if its not
						if (strpos($PropertyNames, ".")) {
							$HTTP_FORM_VARS["PROPERTYNAME"].= $PropertyNames.",";
						}
						else {
							$HTTP_FORM_VARS["PROPERTYNAME"].= $queries['typeName'].".".$PropertyNames.",";
						}
					}
				}
				if (is_array($queries['Filter']) && $arr['version']=="1.0.0") {
					@$checkXml = simplexml_load_string($queries['Filter']);
					if($checkXml===FALSE) {
						makeExceptionReport("Filter is not valid");
					}
					$wheres[$queries['typeName']] = parseFilter($queries['Filter'],$queries['typeName']);
				}
			}
			$HTTP_FORM_VARS["TYPENAME"] = dropLastChrs($HTTP_FORM_VARS["TYPENAME"], 1);
			$HTTP_FORM_VARS["PROPERTYNAME"] = dropLastChrs($HTTP_FORM_VARS["PROPERTYNAME"], 1);
			break;
		case "DescribeFeatureType":
			$HTTP_FORM_VARS["REQUEST"] = "DescribeFeatureType";
			$HTTP_FORM_VARS["TYPENAME"] = $arr['TypeName'];
			//if (!$HTTP_FORM_VARS["TYPENAME"]) $HTTP_FORM_VARS["TYPENAME"] = $arr['typeName'];
			break;
		case "GetCapabilities":
			$HTTP_FORM_VARS["REQUEST"] = "GetCapabilities";
			break;
		case "Transaction":
			$HTTP_FORM_VARS["REQUEST"] = "Transaction";
			if (isset($arr["Insert"])) {
				$transactionType = "Insert";
			}
			if ($arr["Update"]) {
				$transactionType = "update";
			}
			if ($arr["Delete"]) $transactionType = "Delete";

			break;
	}
}
// Get method is used
else {
	if (sizeof($_GET) > 0) {
		logfile::write($_SERVER['QUERY_STRING']."\n\n");
		$HTTP_FORM_VARS = $_GET;
		$HTTP_FORM_VARS = array_change_key_case($HTTP_FORM_VARS,CASE_UPPER);// Make keys case
		if ($HTTP_FORM_VARS['FILTER']) {
			@$checkXml = simplexml_load_string($HTTP_FORM_VARS['FILTER']);
			if($checkXml===FALSE) {
				makeExceptionReport("Filter is not valid");
			}
			//$forUseInSpatialFilter = $HTTP_FORM_VARS['FILTER'];
			$status = $unserializer->unserialize(dropNameSpace($HTTP_FORM_VARS['FILTER']));
			$arr = $unserializer->getUnserializedData();
			$wheres[$HTTP_FORM_VARS['TYPENAME']] = parseFilter($arr,$HTTP_FORM_VARS['TYPENAME']);
		}
	}
	else {
		$HTTP_FORM_VARS = array("");
	}
}

//HTTP_FORM_VARS is set in script if POST is used
$HTTP_FORM_VARS = array_change_key_case($HTTP_FORM_VARS,CASE_UPPER);// Make keys case
$HTTP_FORM_VARS["TYPENAME"] = dropNameSpace($HTTP_FORM_VARS["TYPENAME"]);
$tables = explode(",",$HTTP_FORM_VARS["TYPENAME"]);
$properties = explode(",", dropNameSpace($HTTP_FORM_VARS["PROPERTYNAME"]));
$featureids = explode(",", $HTTP_FORM_VARS["FEATUREID"]);
$bbox = explode(",", $HTTP_FORM_VARS["BBOX"]);

// Start HTTP basic authentication
//if(!$_SESSION["oauth_token"]) {
$auth = $postgisObject->getGeometryColumns($postgisschema.".".$HTTP_FORM_VARS["TYPENAME"],"authentication");
if ($auth=="Read/write") {
	include('../inc/http_basic_authen.php');
}
//}
// End HTTP basic authentication
print ("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
ob_start();
if (!(empty($properties[0]))) {
	foreach ($properties as $property) {
		$__u=explode(".", $property); // Is it "/" for get method?
		// We first check if typeName is namespace
		if ($__u[1]) {
			foreach ($tables as $table) {
				if ($table==$__u[0]) {
					$fields[$table].=$__u[1].",";
				}
			}
		}
		// No, typeName is not a part of value
		else {
			foreach ($tables as $table) {
				$fields[$table].=$property.",";
			}
		}

	}
}
if (!(empty($featureids[0]))) {
	foreach ($featureids as $featureid) {
		$__u=explode(".", $featureid);
		foreach ($tables as $table) {
			$primeryKey = $postgisObject->getPrimeryKey($postgisschema.".".$table);
			if ($table==$__u[0]) {
				$wheresArr[$table][]="{$primeryKey['attname']}={$__u[1]}";
			}
			$wheres[$table] = implode(" OR ",$wheresArr[$table]);
		}
	}
}
if (!(empty($bbox[0]))) {
	if (!(empty($featureids[0]))) {
		$wheres[$table].= " AND ";
	}
	foreach ($tables as $table) {
		if (!$bbox[4]) {
			$bbox[4] = $postgisObject->getGeometryColumns($postgisschema.".".$table, "srid");
		}
		$wheres[$table].= "intersects"
		."(public.transform(public.GeometryFromText('POLYGON((".$bbox[0]." ".$bbox[1].",".$bbox[0]." ".$bbox[3].",".$bbox[2]." ".$bbox[3].",".$bbox[2]." ".$bbox[1].",".$bbox[0]." ".$bbox[1]."))',"
		.gmlConverter::parseEpsgCode($bbox[4])
		."),".$postgisObject->getGeometryColumns($postgisschema.".".$table, "srid")."),"
		.$postgisObject->getGeometryColumns($postgisschema.".".$table, "f_geometry_column").")";

	}
}
//get the request
switch (strtoupper($HTTP_FORM_VARS["REQUEST"])) {
	case "GETCAPABILITIES":
		getCapabilities($postgisObject);
		break;
	case "GETFEATURE":
		if (!$gmlFeatureCollection) {
			$gmlFeatureCollection = "wfs:FeatureCollection";
		}
		print "<".$gmlFeatureCollection."\n";
		print "xmlns=\"http://www.opengis.net/wfs\"\n";
		print "xmlns:wfs=\"http://www.opengis.net/wfs\"\n";
		print "xmlns:gml=\"http://www.opengis.net/gml\"\n";
		print "xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n";
		print "xmlns:{$gmlNameSpace}=\"{$gmlNameSpaceUri}\"\n";

		if ($gmlSchemaLocation) {
			print "xsi:schemaLocation=\"{$gmlSchemaLocation}\"";
		}
		else {
			//print "xsi:schemaLocation=\"{$gmlNameSpaceUri} {$thePath}?REQUEST=DescribeFeatureType&amp;TYPENAME=".$HTTP_FORM_VARS["TYPENAME"]." http://www.opengis.net/wfs ".str_replace("server.php","",$thePath)."schemas/wfs/1.0.0/WFS-basic.xsd\"";
			print "xsi:schemaLocation=\"{$gmlNameSpaceUri} {$thePath}?REQUEST=DescribeFeatureType&amp;TYPENAME=".$HTTP_FORM_VARS["TYPENAME"]." http://www.opengis.net/wfs http://wfs.plansystem.dk:80/geoserver/schemas/wfs/1.0.0/WFS-basic.xsd\"";
		}
		print ">\n";
		doQuery("Select");
		print "</".$gmlFeatureCollection.">";

		break;
	case "DESCRIBEFEATURETYPE":
		getXSD($postgisObject);
		break;
	case "TRANSACTION":
		doParse($arr);
		break;
	default:
		makeExceptionReport("Don't know that request");
		break;
}


/**
 *
 *
 * @param unknown $postgisObject
 */
function getCapabilities($postgisObject) {
	global $srs;
	global $thePath;
	global $db;
	global $gmlNameSpace;
	global $gmlNameSpaceUri;
	global $cacheDir;
	global $postgisschema;
	include 'capabilities.php';
}


/**
 *
 *
 * @param unknown $postgisObject
 */
function getXSD($postgisObject) {
	global $server;
	global $depth;
	global $db;
	global $tables;
	global $gmlUseAltFunctions;
	global $gmlNameSpace;
	global $gmlNameSpaceUri;
	global $cacheDir;
	global $postgisschema;
	global $geometryColumnsObj;
	include 'XSD.php';
}


/**
 *
 *
 * @param unknown $queryType
 */
function doQuery($queryType) {
	global $currentTag;
	global $BBox;
	global $tables;
	global $fields;
	global $values;
	global $wheres;
	global $filters;
	global $limits;
	global $disjoints;
	global $notDisjoints;
	global $disjointCoords;
	global $notDisjointCoords;
	global $WKTfilters;
	global $filterPropertyNames;
	global $postgisObject;
	global $srs;
	global $useWktToGmlInPHP;
	global $postgisschema;
	global $tableObj;
	
	if (!$srs){
		makeExceptionReport("You need to specify a srid in the URL.");
	}

	switch ($queryType) {
		case "Select":
			foreach ($tables as $table) {
				$tableObj = new table($postgisschema.".".$table);
				$primeryKey = $tableObj->getPrimeryKey($postgisschema.".".$table);
				$sql="SELECT ";
					
				if (!(empty($fields[$table]))) {

					$fields[$table] = substr($fields[$table], 0, strlen($fields[$table]) - 1);
					$fieldsArr[$table] = explode(",",$fields[$table]);
				}
				else {
					foreach($postgisObject -> getMetaData($table) as $key=>$value) {
						if ($key!=$primeryKey['attname']) {
							$fieldsArr[$table][] = $key;
						}
					}
				}
				// We add "" around field names in sql, so sql keywords don't mess things up
				foreach($fieldsArr[$table] as $key=>$value) {
					$fieldsArr[$table][$key] = "\"{$value}\"";
				}
				$sql = $sql.implode(",",$fieldsArr[$table]).",{$primeryKey['attname']} as fid";

				foreach ($tableObj->metaData as $key => $arr) {
					if ($arr['type']=="geometry"){
						if ($useWktToGmlInPHP) {
							$sql = str_replace("\"{$key}\"","public.asText(public.transform(".$key.",".$srs.")) as ".$key, $sql);
						}
						else {
							$sql = str_replace("\"{$key}\"","asGml(public.transform(".$key.",".$srs.")) as ".$key, $sql);
								
						}
						$sql2 = "SELECT public.xmin(public.EXTENT(public.transform(".$key.",{$srs}))) AS TXMin,public.xmax(public.EXTENT(public.transform(".$key.",{$srs}))) AS TXMax, public.ymin(public.EXTENT(public.transform(".$key.",{$srs}))) AS TYMin,public.ymax(public.EXTENT(public.transform(".$key.",{$srs}))) AS TYMax ";

					}
				}
				$from = " FROM {$postgisschema}.{$table}";

				if ((!(empty($BBox)))||(!(empty($wheres[$table])))||(!(empty($filters[$table])))) {
					$from .= " WHERE ";
				}

				if (!(empty($wheres[$table]))) {
					$from .= "(" . $wheres[$table] . ")";// White spaces HAS TO BE THERE

				}

				if ((!(empty($BBox)))||(!(empty($wheres[$table])))) {
					//$from =dropLastChrs($from, 5);
					//$from.=")";
				}

				if (!(empty($limits[$table]))) {
					//$from .= " LIMIT " . $limits[$table];
				}
				doSelect($table, $sql, $sql2, $from);
			}
			break;
		default:
			break;
	}
}


/**
 *
 *
 * @param unknown $XMin
 * @param unknown $YMin
 * @param unknown $XMax
 * @param unknown $YMax
 */
function genBBox($XMin, $YMin, $XMax, $YMax) {
	global $depth;
	global $tables;
	global $db;
	global $srs;

	writeTag("open", "gml", "boundedBy", null, True, True);
	$depth++;
	writeTag("open", "gml", "Box", array("srsName"=>"EPSG:".$srs), True, True);
	$depth++;
	writeTag("open", "gml", "coordinates", array("decimal"=>".", "cs"=>",", "ts"=>" "), True, False);
	print $XMin.",".$YMin." ".$XMax.",".$YMax;
	writeTag("close", "gml", "coordinates", null, False, True);
	$depth--;
	writeTag("close", "gml", "Box", null, True, True);
	$depth--;
	writeTag("close", "gml", "boundedBy", null, True, True);
}


/**
 *
 *
 * @param unknown $table
 * @param unknown $sql
 * @param unknown $sql2
 * @param unknown $from
 */
function doSelect($table, $sql, $sql2, $from) {
	global $db;
	global $depth;
	global $postgisObject;
	global $srs;
	global $gmlNameSpaceUri;
	global $lf;
	global $gmlNameSpace;
	global $gmlNameSpaceGeom;
	global $gmlFeature;
	global $gmlGeomFieldName;
	global $gmlUseAltFunctions;
	global $defaultBoundedBox;
	global $cacheDir;
	global $startTime;
	global $useWktToGmlInPHP;
	global $thePath;
	global $HTTP_FORM_VARS;
	global $tableObj;

	if (!$gmlFeature[$table]) {
		$gmlFeature[$table] =$table;
	}
	if ($sql2)
	{
		$postgisObject->execQuery("BEGIN");
		$result = $postgisObject->execQuery($sql2.$from);
		//logfile::write($sql2.$from."\n");
		if ($postgisObject->numRows($result)==1)
		{

			while ($myrow = $postgisObject->fetchRow($result))
			{
				if (!(empty($myrow["txmin"])))
				{
					//added NR
					genBBox($myrow["txmin"], $myrow["tymin"], $myrow["txmax"], $myrow["tymax"]);
				}
				else {
					//return;
				}
			}
		}
		else
		print $defaultBoundedBox;
	}
	else
	{
		print $defaultBoundedBox;
	}
	$result = $postgisObject -> execQuery($sql.$from." LIMIT 10");
	if ($postgisObject->numRows($result)<1)
	{
		$sql = str_replace(",public.asText(public.transform(the_geom,25832)) as the_geom","",$sql);
		$from = str_replace("view","join",$from);
		$result = $postgisObject->execQuery($sql.$from);
	}
	logfile::write("SQL fired\n\n");
	logfile::write($sql.$from."\n");
	$totalTime = microtime_float()-$startTime;
	logfile::write("\nQuery time {$totalTime}\n");
	//foreach($postgisObject -> execQuery($sql.$from." LIMIT 10000") as $myrow) { //Iteration directly over result. Only PDO
	if($postgisObject->PDOerror){
		makeExceptionReport($postgisObject->PDOerror);
	}
	while ($myrow = $postgisObject->fetchRow($result)) {
		writeTag("open", "gml", "featureMember", null, True, True);
		$depth++;
		writeTag("open", $gmlNameSpace, $gmlFeature[$table], array("fid"=>"{$table}.{$myrow["fid"]}"), True, True);
		$depth++;
		$checkIfGeomHasPassed = false; // Check that geom field is written out only once.
		$numFields =  sizeof($myrow);
		$keys = array_keys($myrow);
		for ($i=0;$i < $numFields ;$i++) {
			$FieldName=$keys[$i];
			$FieldValue=$myrow[$FieldName];
			if (($tableObj->metaData[$FieldName]['type']!="geometry") && ($FieldName!="txmin")&&($FieldName!="tymin") && ($FieldName!="txmax") && ($FieldName!="tymax") && ($FieldName!="tymax") && ($FieldName!="oid")) {

				if ($gmlUseAltFunctions['altFieldValue']) {
					$FieldValue = altFieldValue($FieldName, $FieldValue);
				}

				if ($gmlUseAltFunctions['altFieldNameToUpper']) {
					$FieldName = altFieldNameToUpper($FieldName);
				}
				if ($gmlUseAltFunctions['changeFieldName']) {
					$FieldName = changeFieldName($FieldName);
				}

				//$FieldValue = htmlentities($FieldValue);
				$FieldValue = altUseCdataOnStrings($FieldValue);

				if ($FieldValue && ($FieldName!="fid" && $FieldName!="FID")) {
					writeTag("open", $gmlNameSpace, $FieldName, null, True, False);
					//$FieldType = pg_field_type($result, $i);
					echo $FieldValue;
					writeTag("close", $gmlNameSpace, $FieldName, null, False, True);
				}
			}
			elseif ($tableObj->metaData[$FieldName]['type'] == "geometry") {
				// Check if the geometry field use another name space and element name
				//if (!$gmlGeomFieldName[$table]) {
						$gmlGeomFieldName[$table] = $FieldName;
				//}
				if ($gmlNameSpaceGeom) {
					$tmpNameSpace = $gmlNameSpaceGeom;
				}
				else {
					$tmpNameSpace = $gmlNameSpace;
				}
				writeTag("open",  $tmpNameSpace, $gmlGeomFieldName[$table], null, True, True);
				$depth++;
				if ($useWktToGmlInPHP) {
					$__geoObj = geometryfactory::createGeometry($myrow[$FieldName], "EPSG:".$srs);
					echo $__geoObj->getGML();
					unset($__geoObj);
				}
				else {
					echo $myrow[$FieldName];
				}
				$depth--;
				writeTag("close",  $tmpNameSpace, $gmlGeomFieldName[$table], null, True, True);
			}
		}
		$depth--;
		writeTag("close", $gmlNameSpace, $gmlFeature[$table], null, True, True);
		$depth--;
		writeTag("close", "gml", "featureMember", null, True, True);
	}

	$totalTime = microtime_float()-$startTime;
	print "\n<!-- {$totalTime} -->";
	$postgisObject->execQuery("ROLLBACK");
}


/**
 *
 *
 * @param unknown $str
 * @param unknown $no
 * @return unknown
 */
function dropLastChrs($str, $no) {
	$strLen=strlen($str);
	return substr($str, 0, ($strLen)-$no);
}

/**
 *
 *
 * @param unknown $str
 * @param unknown $no
 * @return unknown
 */
function dropFirstChrs($str, $no) {
	$strLen=strlen($str);
	return substr($str, $no, $strLen);
}


/**
 *
 *
 * @param unknown $tag
 * @return unknown
 */
function dropNameSpace($tag) {
	//$tag = html_entity_decode($tag);
	$tag = gmlConverter::oneLineXML($tag);
	$tag = preg_replace('/ xmlns(?:.*?)?=\".*?\"/',"",$tag); // Remove xmlns with "
	$tag = preg_replace('/ xmlns(?:.*?)?=\'.*?\'/',"",$tag); // Remove xmlns with '
	$tag = preg_replace('/ xsi(?:.*?)?=\".*?\"/',"",$tag); // remove xsi:schemaLocation with "
	$tag = preg_replace('/ xsi(?:.*?)?=\'.*?\'/',"",$tag); // remove xsi:schemaLocation with '
	$tag = preg_replace('/ cs(?:.*?)?=\".*?\"/',"",$tag);  //
	$tag = preg_replace('/ cs(?:.*?)?=\'.*?\'/',"",$tag);
	$tag = preg_replace('/ ts(?:.*?)?=\".*?\"/',"",$tag);
	$tag = preg_replace('/ ts(?:.*?)?=\'.*?\'/',"",$tag);
	$tag = preg_replace('/ decimal(?:.*?)?=\".*?\"/',"",$tag);
	$tag = preg_replace('/ decimal(?:.*?)?=\'.*?\'/',"",$tag);
	$tag = preg_replace("/[\w-]*:/", "", $tag);// remove any namespaces
	return ($tag);
}


/**
 *
 *
 * @param unknown $field
 * @return unknown
 */
function altFieldNameToUpper($field) {
	return strtoupper($field);
}
function changeFieldName($field) {
	if ($field == "ref") {
		return "aendret_navn_paa_element";
	}
	if ($field == "skabt_af") {
		return "ref";
	}
	else {
		return $field;
	}
}
/**
 *
 *
 * @param unknown $field
 * @param unknown $value
 * @return unknown
 */
function altFieldValue($field, $value) {
	global $hostName;
	if ($value==-1 || $value==-3600)
	{
		$value=false;
	}
	if ($value) {
		if (substr($field, 0, 4)=="dato") {
			$value = date("Ymd", $value);
		}
		if ($field=="doklink") {
			if (substr($value, 0, 4)!="http")
			{
				$value = $hostName."/download".$value;
			}
		}
		$result = $value;
	}
	else {
		$result = false;
	}
	return $result;
}
/**
 *
 *
 * @param unknown $field
 * @param unknown $value
 * @return unknown
 */
function altUseCdataOnStrings($value) {
	if (!is_numeric($value) && ($value)) {
		//$value = "<![CDATA[".$value."]]>";
		$value = str_replace("&","&#38;",$value);
		$result = $value;
	}
	else {
		$result = $value;
	}
	return $result;
}

$totalTime = microtime_float()-$startTime;
logfile::write("\nTotal time {$totalTime}\n");
logfile::write("==================\n");
//echo "\n<!-- {$totalTime} -->";

function doParse($arr)
{

	global $postgisObject;
	global $user;
	global $version;
	global $postgisschema;
	global $parts;

	$serializer_options = array (
	   'indent' => '  ', 
	);
	$Serializer = &new XML_Serializer($serializer_options);
	foreach ($arr as $key => $featureMember)
	{
		if ($key == "Insert"){
			if (!is_array($featureMember[0]) && isset($featureMember)) {
				$featureMember = array(0 => $featureMember);
			}
			foreach($featureMember as $hey){
				foreach ($hey as $typeName => $feature)
				{
					if (is_array($feature)) {// Skip handles
						foreach ($feature as $field => $value)
						{
							$fields[] = $field;
							if (is_array($value)) { // Must be geom if array
								// We serialize the geometry back to XML for parsing
								$status = $Serializer->serialize($value);
								logfile::write($Serializer->getSerializedData()."\n\n");
								$gmlCon = new gmlConverter();
								$wktArr = $gmlCon -> gmlToWKT($Serializer->getSerializedData(),array());
								$values[] = array("{$field}" => $wktArr[0][0],"srid" => $wktArr[1][0]);
								unset($gmlCon);
								unset($wktArr);
								//logfile::write($Serializer->getSerializedData()."\n\n");
							}
							else {
								$values[] = pg_escape_string($value);
							}
						}
						$forSql['tables'][] = $typeName;
						$forSql['fields'][] = $fields;
						$forSql['values'][] = $values;

						$fields = array();
						$values = array();
						$field = "";
						$value = "";
						// Start HTTP basic authentication
						//if(!$_SESSION["oauth_token"]) {
						$auth = $postgisObject->getGeometryColumns($postgisschema.".".$typeName,"authentication");
						if ($auth=="Write" OR $auth=="Read/write") {
							include('../inc/http_basic_authen.php');
						}
						//	}
						// End HTTP basic authentication
					}
				}
			}
		}
		if ($key == "Update"){
			if (!is_array($featureMember[0]) && isset($featureMember)) {
				$featureMember = array(0 => $featureMember);
			}
			$fid = 0;
			foreach($featureMember as $hey){
				$the_geom = $postgisObject->getGeometryColumns($postgisschema.".".$hey['typeName'], "f_geometry_column");
				if (!is_array($hey['Property'][0]) && isset($hey['Property'])) {
					$hey['Property'] = array(0 => $hey['Property']);
				}
				foreach ($hey['Property'] as $pair)
				{
					$fields[$fid][] = $pair['Name'];
					if ($pair['Name'] == $the_geom) {
						// We serialize the geometry back to XML for parsing
						$status = $Serializer->serialize($pair['Value']);
						logfile::write($Serializer->getSerializedData()."\n\n");

						$gmlCon = new gmlConverter();
						$wktArr = $gmlCon -> gmlToWKT($Serializer->getSerializedData(),array());
						$values[$fid][] = (array("{$pair['Name']}" => current($wktArr[0]),"srid" => current($wktArr[1])));

						unset($gmlCon);
						unset($wktArr);
					}
					else {
						$values[$fid][] = $pair['Value'];
					}
				}
				$forSql2['tables'][$fid]= $hey['typeName'];
				$forSql2['fields']= $fields;
				$forSql2['values']= $values;
				$forSql2['wheres'][$fid] = parseFilter($hey['Filter'],$hey['typeName']);
				$fid++;
				// Start HTTP basic authentication
				//if(!$_SESSION["oauth_token"]) {
				$auth = $postgisObject->getGeometryColumns($postgisschema.".".$hey['typeName'],"authentication");
				if ($auth=="Write" OR $auth=="Read/write") {
					include('../inc/http_basic_authen.php');
				}
				//	}
				// End HTTP basic authentication
				}
				$pair = array();
				$values = array();
				$fields= array();
			}

			if ($key == "Delete"){
				if (!is_array($featureMember[0]) && isset($featureMember)) {
					$featureMember = array(0 => $featureMember);
				}
				foreach($featureMember as $hey){
					$forSql3['tables'][] = $hey['typeName'];
					$forSql3['wheres'][] = parseFilter($hey['Filter'],$hey['typeName']);
					// Start HTTP basic authentication
					//if(!$_SESSION["oauth_token"]) {
					$auth = $postgisObject->getGeometryColumns($postgisschema.".".$hey['typeName'],"authentication");
					if ($auth=="Write" OR $auth=="Read/write") {
						include('../inc/http_basic_authen.php');
					}
					//	}
					// End HTTP basic authentication
					}
				}
			}

			echo '<wfs:WFS_TransactionResponse
        version="1.0.0"
	service="WFS"
	xmlns:wfs="http://www.opengis.net/wfs"
	xmlns:ogc="http://www.opengis.net/ogc">';
			// First we loop through inserts
			if (sizeof($forSql['tables'])>0) for($i=0;$i<sizeof($forSql['tables']);$i++) {
				if ($postgisObject->getGeometryColumns($postgisschema.".".$forSql['tables'][$i],"editable")) {
					$the_geom = $postgisObject->getGeometryColumns($postgisschema.".".$forSql['tables'][$i], "f_geometry_column");
					$primeryKey = $postgisObject->getPrimeryKey($postgisschema.".".$forSql['tables'][$i]);
					//$metaData = $postgisObject -> getMetaData($forSql['tables'][$i]);
					$sql = "INSERT into {$postgisschema}.{$forSql['tables'][$i]} (";
					foreach($forSql['fields'][$i] as $field){
						$fields[] = "\"".$field."\"";
					}
					$sql.= implode(",",$fields);
					unset($fields);
					$sql.= ") VALUES(";
					foreach($forSql['values'][$i] as $key=>$value){
						if (is_array($value)) {
							fb($forSql['fields'][$i][$key]);
							$values[] = "public.transform(public.GeometryFromText('".current($value)."',".next($value)."),".$postgisObject -> getGeometryColumns($postgisschema.".".$forSql['tables'][$i], "srid").")";
						}
						elseif (!$value) {
							$values[] = "NULL";
						}
						else {
							$values[] = $postgisObject->quote($value);
						}
					}
					$sql.= implode(",",$values);
					unset($values);
					$sql.= ") RETURNING {$primeryKey['attname']} as gid,public.asText(public.ST_Centroid({$the_geom})) as {$the_geom};"; // The query will return the new key
					$sqls['insert'][] = $sql;
				}
				else {
					$notEditable[$forSql['tables'][0]] = true;
				}
			}
			// Second we loop through updates
			if (sizeof($forSql2['tables'])>0) for($i=0;$i<sizeof($forSql2['tables']);$i++) {
				//$metaData = $postgisObject -> getMetaData($forSql2['tables'][$i]);
				if ($postgisObject->getGeometryColumns($postgisschema.".".$forSql2['tables'][$i],"editable")) {
					$the_geom = $postgisObject->getGeometryColumns($postgisschema.".".$forSql2['tables'][$i], "f_geometry_column");
					$primeryKey = $postgisObject->getPrimeryKey($postgisschema.".".$forSql2['tables'][$i]);
					$sql = "UPDATE {$postgisschema}.{$forSql2['tables'][$i]} SET ";
					foreach ($forSql2['fields'][$i] as $key=>$field) {

						if (is_array($forSql2['values'][$i][$key])) { // is geometry
							fb($forSql2['fields'][$i][$key]);
							$value = "public.transform(public.GeometryFromText('".current($forSql2['values'][$i][$key])."',".next($forSql2['values'][$i][$key])."),".$postgisObject -> getGeometryColumns($postgisschema.".".$forSql2['tables'][$i], "srid").")";
						}
						elseif (!$forSql2['values'][$i][$key]) {
							$value = "NULL";
						}
						else
						{
							$value = $postgisObject->quote($forSql2['values'][$i][$key]); // We need to escape the string
						}
						if (!is_array($forSql2['values'][$i][$key])) { // is not geometry. Adding "" around field names
							$pairs[] = "\"".$field."\"=".$value;
						}
						else {
							$pairs[] = $field."=".$value;
						}
					}
					$sql.= implode(",",$pairs);
					$sql.= " WHERE {$forSql2['wheres'][$i]} RETURNING {$primeryKey['attname']} as gid,public.asText(public.ST_Centroid({$the_geom})) as {$the_geom};";
					unset($pairs);
					$sqls['update'][] = $sql;
				}
				else {
					$notEditable[$forSql2['tables'][0]] = true;
				}
			}
			// Third we loop through deletes
			if (sizeof($forSql3['tables'])>0) for($i=0;$i<sizeof($forSql3['tables']);$i++) {
				if ($postgisObject->getGeometryColumns($postgisschema.".".$forSql3['tables'][$i],"editable")) {
					$sqls['delete'][]= "DELETE FROM {$postgisschema}.{$forSql3['tables'][$i]} WHERE {$forSql3['wheres'][$i]};\n\n";
				}
				else {
					$notEditable[$forSql3['tables'][0]] = true;
				}
			}
			// We start sql BEGIN block
			$postgisObject -> connect("PDO");
			$postgisObject -> begin();

			// We fire the sqls
			if (isset($sqls)) foreach($sqls as $operation=>$sql) {
				foreach ($sql as $singleSql) {
					if ($operation=="insert" || $operation=="update") {
						$results[$operation][]= $postgisObject->execQuery($singleSql,"PDO","select"); // Returning PDOStatement object
					}
					else {
						$results[$operation]+= $postgisObject->execQuery($singleSql,"PDO","transaction"); // Returning interger
					}
					logfile::write("Sqls fired\n");
					logfile::write("{$singleSql}\n");
				}
			}
			// If a layer is not editable, PDOerror is set.
			if (sizeof($notEditable)>0) {
				$postgisObject->PDOerror[0] = "Layer not editable";
			}

			// WFS message
			echo '<wfs:Message>';
			echo '</wfs:Message>';


			// TransactionResult
			if (sizeof($postgisObject->PDOerror) == 0) {
				echo '<wfs:TransactionResult><wfs:Status><wfs:SUCCESS/></wfs:Status></wfs:TransactionResult>';
				$postgisObject->commit();
			}
			else {
				echo '<wfs:TransactionResult><wfs:Status><wfs:FAILURE/></wfs:Status></wfs:TransactionResult>';
				logfile::write("Error in\n");
				foreach($postgisObject->PDOerror as $str){
					logfile::write("{$str}\n");
				}
				logfile::write("ROLLBACK\n");
				$postgisObject->rollback();
				$results['insert'] = NULL; // Was object
				$results['update'] = NULL; // Was object
				$results['delete'] = 0;
				makeExceptionReport($postgisObject->PDOerror); // This output a exception and kills the script
			}
			// InsertResult
			if (sizeof($results['insert'])>0) {
				reset($forSql['tables']);
				echo '<wfs:InsertResults handle="mygeocloud-WFS-default-handle">';
				foreach ($results['insert'] as $res) {
					echo '<ogc:FeatureId fid="';
					echo current($forSql['tables']).".";
					$row = $postgisObject->fetchRow($res);
					echo $row['gid'];
					echo '"/>';
					//$version->set(current($forSql['tables']),"insert",$row[$postgisObject->getGeometryColumns(current($forSql['tables']), "f_geometry_column")]);
					next($forSql['tables']);
				}
				echo '</wfs:InsertResults>';
			}
			// UpdateResult
			if (sizeof($results['update'])>0) {
				reset($forSql2['tables']);
				echo '<wfs:UpdateResult>';
				foreach ($results['update'] as $res) {
					echo '<ogc:FeatureId fid="';
					echo current($forSql2['tables']).".";
					$row = $postgisObject->fetchRow($res);
					echo $row['gid'];
					echo '" />';
					//$version->set(current($forSql2['tables']),"update",$row[$postgisObject->getGeometryColumns(current($forSql['tables']), "f_geometry_column")]);
					next($forSql2['tables']);
				}
				echo '</wfs:UpdateResult>';
			}
			// TransactionSummary
			echo '<wfs:TransactionSummary>';
			if(isset($results)) foreach($results as $operation=>$result) {

				if ($operation == "insert") {
					echo "<wfs:totalInserted>".sizeof($result)."</wfs:totalInserted>";
				}
				if ($operation == "update") {
					echo "<wfs:totalUpdated>".sizeof($result)."</wfs:totalUpdated>";
				}
				if ($operation == "delete") {
					echo "<wfs:totalDeleted>".$result."</wfs:totalDeleted>";
				}
			}
			echo '</wfs:TransactionSummary>';
			echo '</wfs:WFS_TransactionResponse>';

			$postgisObject -> free($result);
		}

		function makeExceptionReport($value)
		{
			ob_get_clean();
			ob_start();

			echo '<ServiceExceptionReport
	   version="1.2.0"
	   xmlns="http://www.opengis.net/ogc"
	   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	   xsi:schemaLocation="http://www.opengis.net/ogc http://wfs.plansystem.dk:80/geoserver/schemas//wfs/1.0.0/OGC-exception.xsd">
	   <ServiceException>';
			if (is_array($value)) {
				print_r($value);
			} else {
				print $value;
			}
			echo '</ServiceException>
	</ServiceExceptionReport>';
			$data = ob_get_clean();
			echo $data;
			logfile::write($data);
			die();
		}
		$data = ob_get_clean();
		//logfile::write($data);
		echo $data;
