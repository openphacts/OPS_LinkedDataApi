<?php


class DataHandlerParams {

    public $Request = false;
    public $ConfigGraph = false;
    public $DataGraph = false;
    public $SparqlWriter = false;
    public $SparqlEndpoint = false;
    public $viewerUri = false;
    public $endpointUrl = false;

    function __construct(LinkedDataApiRequest $Request,
                         ConfigGraph $ConfigGraph,
                         LinkedDataApiGraph $DataGraph,
                         $viewerUri,
                         SparqlWriter $SparqlWriter,
                         SparqlService $SparqlEndpoint,
                         $endpointUrl){
        $this->Request = $Request;
        $this->ConfigGraph = $ConfigGraph;
        $this->DataGraph = $DataGraph;
        $this->viewerUri = $viewerUri;
        $this->SparqlWriter = $SparqlWriter;
        $this->SparqlEndpoint = $SparqlEndpoint;
        $this->endpointUrl = $endpointUrl;
    }

}
