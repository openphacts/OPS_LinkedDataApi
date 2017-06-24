<?php

require_once 'data_handler_components/viewer.interf.php';
require_once 'data_handler_components/pagination_behavior.class.php';

class SingleExpansionViewer implements Viewer {

	private $Request;
	private $ConfigGraph;
	private $DataGraph;
	private $SparqlWriter;
	private $SparqlEndpoint;
	private $viewerUri;
	private $viewQuery;
	private $pageUri;
	private $endpointUrl;

	private $paginationBehavior = false;

	function __construct($dataHandlerParams){
		$this->Request = $dataHandlerParams->Request;
		$this->ConfigGraph = $dataHandlerParams->ConfigGraph;
		$this->DataGraph = $dataHandlerParams->DataGraph;
		$this->SparqlWriter = $dataHandlerParams->SparqlWriter;
		$this->SparqlEndpoint = $dataHandlerParams->SparqlEndpoint;
		$this->viewerUri = $dataHandlerParams->viewerUri;
		$this->endpointUrl = $dataHandlerParams->endpointUrl;

		$this->paginationBehavior = new PaginationBehavior($dataHandlerParams);
	}

	public function applyViewerAndBuildDataGraph($itemMap){
		logDebug("Viewer URI is $this->viewerUri");
		$this->viewQuery  = $this->SparqlWriter->getViewQueryForUriList($itemMap['item'], $this->viewerUri);
		if (LOG_VIEW_QUERIES) {
			logViewQuery( $this->Request, $this->viewQuery);
		}

		$response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);
		if($response->is_success()){
			$rdf = $response->body;
			$rdf = preg_replace("/&#39;/", "'", $rdf);
			$rdf = preg_replace("/&#[a-z0-9]*;/", " ", $rdf);
			if(isset($response->headers['content-type'])){
				if(strpos($response->headers['content-type'], 'turtle')){
					$this->DataGraph->add_turtle($rdf);
				} else {
					$this->DataGraph->add_rdf($rdf);
				}
			} else {
				$this->DataGraph->add_rdf($rdf);
			}
			//logDebug("Virtuoso response");
			//logDebug($rdf);
			//logDebug("ARC2 contents");
			//logDebug($this->DataGraph->to_turtle());
			if ($this->DataGraph->is_empty()){
				throw new EmptyResponseException("Data not found in the triple store");
			}

			$this->pageUri = $this->paginationBehavior->addListMetadataToDataGraph($itemMap['item']);

		} else {

          logSparqlError("VIEW query in SingleExpansionViewer.applyViewerAndBuildDataGraph()",
              $response, $this->viewQuery, $this->SparqlEndpoint->uri);

			throw new ErrorException("The SPARQL endpoint used by this URI configuration did not return a successful response.");
		}
	}

	public function getViewQuery()	{
		return $this->viewQuery;
	}

	public function getPageUri(){
		return $this->pageUri;
	}

}


?>
