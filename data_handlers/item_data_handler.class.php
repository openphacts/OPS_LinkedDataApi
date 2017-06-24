<?php

require_once 'lda.inc.php';
require_once 'data_handlers/1step_data_handler.class.php';

class ItemDataHandler extends OneStepDataHandler{

    private $pageUri = false;


    function __construct($dataHandlerParams) {
        parent::__construct($dataHandlerParams);
    }

    function loadData(){
        $uri = $this->ConfigGraph->getCompletedItemTemplate();
        $this->list_of_item_uris = array($uri);

        $this->viewQuery  = $this->SparqlWriter->getViewQueryForUri($uri, $this->viewerUri);
        if (LOG_VIEW_QUERIES) {
            logViewQuery($this->Request, $this->viewQuery);
        }

        $response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);
        $this->pageUri = $this->Request->getUriWithoutPageParam();
        if($response->is_success()){
            $rdf = $response->body;
	    $rdf = preg_replace("/&#39;/", "'", $rdf);
	    $rdf = preg_replace("/&#[a-z0-9]*;/", " ", $rdf);
            $this->DataGraph->add_rdf($rdf);

            if ($this->DataGraph->is_empty()){
                throw new EmptyResponseException("Data not found in the triple store");
            }
	    //logDebug('Virtuoso response: '.$rdf);
	    //logDebug('ARC2 contents: '.$this->DataGraph->to_turtle());
            $this->DataGraph->add_resource_triple($this->pageUri, FOAF.'primaryTopic', $uri);

            $this->DataGraph->add_resource_triple($uri , FOAF.'isPrimaryTopicOf', $this->pageUri);
            $this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);
            $lens = $this->Request->getParam('_lens');
            if ($lens == '') $lens='Default';
            $this->DataGraph->add_literal_triple($this->Request->getUri(), OPS_API.'/activeLens', $lens);
            $this->DataGraph->add_resource_triple($this->Request->getUri(), VOID.'linkPredicate', SKOS.'exactMatch');
        } else {

          logSparqlError("VIEW query in ItemDataHandler.loadData()",
              $response, $this->viewQuery, $this->SparqlEndpoint->uri);

            throw new ErrorException("The SPARQL endpoint used by this URI configuration did not return a successful response.");
        }
    }

    function getPageUri(){
        return $this->pageUri;
    }

}

?>
