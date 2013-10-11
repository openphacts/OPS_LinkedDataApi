<?php


abstract class DataHandler {
    
    protected $Request = false;
    protected $ConfigGraph = false;
    protected $DataGraph = false;
    protected $SparqlWriter = false;
    protected $SparqlEndpoint = false;
    protected $viewer = false;
    protected $viewQuery = '';
    protected $list_of_item_uris = null;
    
    function __construct($Request, $ConfigGraph, $DataGraph, $Viewer, $SparqlWriter, $SparqlEndpoint){
        $this->Request = $Request;
        $this->ConfigGraph = $ConfigGraph;
        $this->DataGraph = $DataGraph;
        $this->viewer = $Viewer;
        $this->SparqlWriter = $SparqlWriter;
        $this->SparqlEndpoint = $SparqlEndpoint;
    }
    
    abstract protected function loadData();
    
    function getItemURIList(){//TODO should be called in addMetadata
    	return $this->list_of_item_uris;
    }
    
    function getViewer(){
    	return $this->viewer;
    }
    
    function getViewQuery(){
    	return $this->viewQuery;
    }
}


?>