<?php

require_once 'data_handlers/data_handler.interf.php';

abstract class OneStepDataHandler implements DataHandlerInterface {

    protected $Request = false;
    protected $ConfigGraph = false;
    protected $DataGraph = false;
    protected $SparqlWriter = false;
    protected $SparqlEndpoint = false;
    protected $viewerUri = false;
    protected $endpointUrl = '';
    protected $viewQuery = '';
    protected $list_of_item_uris = null;

    function __construct(DataHandlerParams $dataHandlerParams){
        $this->Request = $dataHandlerParams->Request;
        $this->ConfigGraph = $dataHandlerParams->ConfigGraph;
        $this->DataGraph = $dataHandlerParams->DataGraph;
        $this->viewerUri = $dataHandlerParams->viewerUri;
        $this->SparqlWriter = $dataHandlerParams->SparqlWriter;
        $this->SparqlEndpoint = $dataHandlerParams->SparqlEndpoint;
        $this->endpointUrl = $dataHandlerParams->endpointUrl;
    }

    //the loadData method is left as abstract

    function getItemURIList(){
    	return $this->list_of_item_uris;
    }

    function getViewer() {
        // [2017.06.25] R.Kerber
        // Changed below from '$this->$viewerUri' to '$this->viewerUri'. Presumably the latter is
        // correct. But without a description of what this is supposed to do, it is conceivable that
        // the previous version was intentional.
//        return $this->$viewerUri;
        return $this->viewerUri;
    }

    function getViewQuery(){
    	return $this->viewQuery;
    }

    function getSelectQuery(){
    	return '';
    }
}


?>
