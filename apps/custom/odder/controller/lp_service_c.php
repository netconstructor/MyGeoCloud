<?php
$plannr = $_GET['plannr'];
$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","plannr='{$plannr}'");
$row = $response['data'][0];

echo "<span style='font-size:18px'>Lokalplan nr. {$plannr}</span>";