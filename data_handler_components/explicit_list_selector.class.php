<?php
require_once 'exceptions.inc.php';

require_once 'data_handler_components/selector.interf.php';

class ExplicitListSelector implements Selector{
	
	private $Request;
	
	function __construct($Request){
		$this->Request = $Request;
	}
	
	public function getItemList(){
		$list=array();
		foreach($this->Request->getUnreservedParams() as $k => $v){
			if ($k = 'uri_list') {
				$uri = strtok($v, '|');
				while ($uri !== false) {
					$list[]=$uri;
					$uri = strtok('|');
				}
			}
		}
		return $list;
	}
	
	public function getSelectQuery(){
		return '';
	}
	
}

?>