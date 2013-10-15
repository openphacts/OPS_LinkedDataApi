<?php

require_once 'data_handlers/data_handler.interf.php';

abstract class OneStepDataHandler implements DataHandlerInterface {
    
    protected $Request = false;
    protected $ConfigGraph = false;
    protected $DataGraph = false;
    protected $SparqlWriter = false;
    protected $SparqlEndpoint = false;
    protected $viewerUri = false;
    protected $viewQuery = '';
    protected $list_of_item_uris = null;
    
    function __construct($Request, $ConfigGraph, $DataGraph, $Viewer, $SparqlWriter, $SparqlEndpoint){
        $this->Request = $Request;
        $this->ConfigGraph = $ConfigGraph;
        $this->DataGraph = $DataGraph;
        $this->viewerUri = $Viewer;
        $this->SparqlWriter = $SparqlWriter;
        $this->SparqlEndpoint = $SparqlEndpoint;
    }
    
    //the loadData method is left as abstract
    
    function getItemURIList(){
    	return $this->list_of_item_uris;
    }
    
    function getViewer(){
    	return $this->$viewerUri;
    }
    
    function getViewQuery(){
    	return $this->viewQuery;
    }
    
    function getSelectQuery(){
    	return '';
    }
}


?>