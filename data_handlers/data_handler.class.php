<?php


abstract class DataHandler {
    
    protected var $request = false;
    protected var $configGraph = false;
    protected var $dataGraph = false;
    protected var $viewer = false;
    protected var $viewQuery = '';
    
    function __construct($Request, $ConfigGraph, $DataGraph, $Viewer){
        $this->request = $Request;
        $this->configGraph = $ConfigGraph;
        $this->dataGraph = $DataGraph;
        $this->viewer = $Viewer;
    }
    
    abstract protected function loadData();
    
}


?>