<?php
require_once 'exceptions.inc.php';

require_once 'data_handler_components/selector.interf.php';

class RequestSelector implements Selector{
	
	private $Request;
	
	function __construct($Request){
		$this->Request = $Request;
	}
	
	public function getItemMap(){
		$itemMap=array();
		$itemMap['item'] = array();
		foreach($this->Request->getUnreservedParams() as $k => $v){
			if ($k = 'uri_list') {
				$uri = strtok($v, '|');
				while ($uri !== false) {
					$itemMap['item'][]=$uri;
					$uri = strtok('|');
				}
			}
		}
		return $itemMap;
	}
	
	public function getSelectQuery(){
		return '';
	}
	
}

?>