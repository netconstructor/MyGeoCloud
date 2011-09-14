<?php
class Settings_viewer extends postgis {
	function __construct() {
		parent::__construct();
	}
	private function getArray(){
		$sql = "SELECT viewer FROM settings.viewer";
		$arr = $this->fetchRow($this->execQuery($sql),"assoc");
		return (array)json_decode($arr['viewer']);
	}
	public function update_extent($layer){
		$arr = $this->getArray();
		$arr['default_extent'] = $layer;
		$sql = "UPDATE settings.viewer SET viewer='".json_encode($arr)."'";
		$this -> execQuery($sql,"PDO","transaction");
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['message'] = "Default extent updated";
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		return $response;
	}
	public function get() {
		$arr = $this->getArray();
		if (!$this->PDOerror) {
	 		$response['success'] = true;
	 		$response['data'] = $arr;
		}
		else {
			$response['success'] = false;
			$response['message'] = $this->PDOerror;
		}
		//print_r($response);
		return $response;
		
	}	
}
