<?php


interface Viewer {
	
	public function applyViewerAndBuildDataGraph($itemMap);
	
	public function getViewQuery() ;
	
}

?>