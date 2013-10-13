<?php


interface Viewer {
	
	public function applyViewerAndBuildDataGraph($itemList);
	
	public function getViewQuery() ;
	
}

?>