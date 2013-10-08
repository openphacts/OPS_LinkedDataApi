<?php

require_once 'data_handler.class.php';

class ItemDataHandler extends DataHandler{
    
    private var list_of_item_uris = false;
    private var pageUri = false;
    
    function __construct($Request, $ConfigGraph, $DataGraph, $Viewer) {
        parent::__construct($Request, $ConfigGraph, $DataGraph, $Viewer);
    }
    
    protected function loadData(){
        $uri = $this->ConfigGraph->getCompletedItemTemplate();
        $this->list_of_item_uris = array($uri);
        
        $this->viewQuery  = $this->SparqlWriter->getViewQueryForUri($uri, $this->viewer);
        if (LOG_VIEW_QUERIES) {
            logViewQuery($this->Request, $this->viewQuery);
        }
        
        $response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);
        $pageUri = $this->Request->getUriWithoutPageParam();
        if($response->is_success()){
            $rdf = $response->body;
            $this->DataGraph->add_rdf($rdf);

            if ($this->DataGraph->is_empty()){
                logError("Data not found in the triple store");
                throw new EmptyResponseException();
            }

            #	    echo $uri;
            $this->DataGraph->add_resource_triple($pageUri, FOAF.'primaryTopic', $uri);
            $label = $this->DataGraph->get_first_literal($uri, SKOS.'prefLabel');
            #            if(!empty($label) || $label = $this->DataGraph->get_label($uri)){
            #              $this->DataGraph->add_literal_triple($pageUri, RDFS_LABEL, $label);
            #            }

            $this->DataGraph->add_resource_triple($uri , FOAF.'isPrimaryTopicOf', $pageUri);
            $this->DataGraph->add_resource_triple($this->Request->getUri(), API.'definition', $this->endpointUrl);
            if($datasetUri = $this->ConfigGraph->getDatasetUri()){
                #            	$this->DataGraph->add_resource_triple($pageUri, VOID.'inDataset', $datasetUri);
                $voidRequest = $this->HttpRequestFactory->make('GET', $datasetUri);
                $voidRequest->set_accept(PUELIA_RDF_ACCEPT_MIMES);
                $voidResponse = $voidRequest->execute();
                if($voidResponse->is_success()){
                    $voidGraph = new SimpleGraph();
                    $base = array_shift(explode('#',$datasetUri));
                    $voidGraph->add_rdf($voidResponse->body, $base) ;
                    if($licenseUri = $voidGraph->get_first_resource($datasetUri, DCT.'license')){
                        $this->DataGraph->add_resource_triple($this->Request->getUri(), DCT.'license', $licenseUri);
                    } else {
                        logDebug($datasetUri.' has no dct:license');
                    }
                } else {
                    logDebug("VoID document could not be fetched from {$datasetUri}");
                }
            }
        } else {
            logError("Endpoint returned {$response->status_code} {$response->body} View Query <<<{$this->viewQuery}>>> failed against {$this->SparqlEndpoint->uri}");
            $this->setStatusCode(HTTP_Internal_Server_Error);
            $this->errorMessages[]="The SPARQL endpoint used by this URI configuration did not return a successful response.";
        }
        
        $this->pageUri = $pageUri;
    }

    function getItemURIList(){//TODO should be called in addMetadata
        return $this->list_of_item_uris;
    }
    
    function getPageUri(){
        return $this->pageUri;
    }
    
}

?>