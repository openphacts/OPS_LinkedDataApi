<?php


abstract class DataHandler {
    
    protected $Request = false;
    protected $ConfigGraph = false;
    protected $DataGraph = false;
    protected $viewer = false;
    protected $viewQuery = '';
    
    function __construct($Request, $ConfigGraph, $DataGraph, $Viewer){
        $this->$Request = $Request;
        $this->$ConfigGraph = $ConfigGraph;
        $this->$DataGraph = $DataGraph;
        $this->viewer = $Viewer;
    }
    
    abstract protected function loadData();
    
}


?>