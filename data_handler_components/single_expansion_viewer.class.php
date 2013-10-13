<?php

require_once 'data_handler_components/viewer.interf.php';

class SingleExpansionViewer implements Viewer {
	
	private $Request;
	private $ConfigGraph;
	private $DataGraph;
	private $SparqlWriter;
	private $SparqlEndpoint;
	private $viewerUri;
	private $viewQuery;
	private $pageUri;
	
	function __construct($Request, $ConfigGraph, $DataGraph, $SparqlWriter, $SparqlEndpoint, $viewerUri){
		$this->Request = $Request;
		$this->ConfigGraph = $ConfigGraph;
		$this->DataGraph = $DataGraph;
		$this->SparqlWriter = $SparqlWriter;
		$this->SparqlEndpoint = $SparqlEndpoint;
		$this->viewerUri = $viewerUri;
	}
	
	public function applyViewerAndBuildDataGraph($list){
		logDebug("Viewer URI is $this->viewerUri");
		$this->viewQuery  = $this->SparqlWriter->getViewQueryForUriList($list, $this->viewerUri);
		if (LOG_VIEW_QUERIES) {
			logViewQuery( $this->Request, $this->viewQuery);
		}
		
		$response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);
		if($response->is_success()){
			$rdf = $response->body;
			if(isset($response->headers['content-type'])){
				if(strpos($response->headers['content-type'], 'turtle')){
					$this->DataGraph->add_turtle($rdf);
				} else {
					$this->DataGraph->add_rdf($rdf);
				}
			} else {
				$this->DataGraph->add_rdf($rdf);
			}

			if ($this->DataGraph->is_empty()){
				throw new EmptyResponseException("Data not found in the triple store");
			}

			$listUri = $this->Request->getUriWithoutParam(array('_view', '_page'), 'strip extension');
			//$this->listUri = $listUri;
			$this->pageUri = $this->Request->getUriWithPageParam();
			$currentPage = $this->Request->getPage();
			$this->DataGraph->add_resource_triple($listUri, API.'definition', $this->endpointUrl);
			$this->DataGraph->add_resource_triple($listUri, RDF_TYPE, API.'List');
			$this->DataGraph->add_resource_triple($this->pageUri, RDF_TYPE, API.'Page');
			if($label = $this->ConfigGraph->getPageTitle()){
				$this->DataGraph->add_literal_triple($this->pageUri, RDFS_LABEL, $label);
			}
			$this->DataGraph->add_resource_triple($listUri, DCT.'hasPart', $this->pageUri);
			$this->DataGraph->add_resource_triple($this->pageUri, DCT.'isPartOf', $listUri);
			$this->DataGraph->add_resource_triple($this->pageUri, XHV.'first', $this->Request->getUriWithPageParam(1));
			if(count($list) >= $this->SparqlWriter->getLimit()){
				$this->DataGraph->add_resource_triple($this->pageUri, XHV.'next', $this->Request->getUriWithPageParam($currentPage+1));
			}
			if($currentPage > 1){
				$this->DataGraph->add_resource_triple($this->pageUri, XHV.'prev', $this->Request->getUriWithPageParam($currentPage-1));
			}
			$this->DataGraph->add_literal_triple($this->pageUri, OPENSEARCH.'itemsPerPage', $this->SparqlWriter->getLimit(), null, XSD.'integer');
			$this->DataGraph->add_literal_triple($this->pageUri, OPENSEARCH.'startIndex', $this->SparqlWriter->getOffset(), null, XSD.'integer');
			$this->DataGraph->add_literal_triple($this->pageUri, DCT.'modified', date("Y-m-d\TH:i:s"), null, XSD.'dateTime' );
			$rdfListUri = '_:itemsList';

			$this->DataGraph->add_resource_triple($this->pageUri, API.'items', $rdfListUri);
			$this->DataGraph->add_resource_triple($rdfListUri, RDF_TYPE, RDF_LIST);
			foreach($list as $no => $resourceUri){
				$nextNo = ($no+1);
				$nextList = (($no+1) == count($list))? RDF_NIL : '_:itemsList'.$nextNo;
				$this->DataGraph->add_resource_triple($rdfListUri, RDF_FIRST, $resourceUri);
				$this->DataGraph->add_resource_triple($rdfListUri, RDF_REST, $nextList);
				$rdfListUri = $nextList;
			}

		} else {
			logError("Endpoint returned {$response->status_code} {$response->body} View Query <<<{$this->viewQuery}>>> failed against {$this->SparqlEndpoint->uri}");
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