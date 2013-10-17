<?php


require_once 'data_handler_components/viewer.interf.php';
require_once 'data_handler_components/pagination_behavior.class.php';

class MultipleExpansionViewer implements Viewer {
	
	protected $Request;
	protected $DataGraph;
	protected $SparqlWriter;
	protected $SparqlEndpoint;
	protected $viewerUri;
	protected $viewQuery;
	protected $pageUri;
	protected $endpointUrl;
	
	private $paginationBehavior = false;
	
	function __construct($dataHandlerParams, $enablePagination=PAGINATION_OFF){
		$this->Request = $dataHandlerParams->Request;
		$this->DataGraph = $dataHandlerParams->DataGraph;
		$this->SparqlWriter = $dataHandlerParams->SparqlWriter;
		$this->SparqlEndpoint = $dataHandlerParams->SparqlEndpoint;
		$this->viewerUri = $dataHandlerParams->viewerUri;
		$this->endpointUrl = $dataHandlerParams->endpointUrl;
		
		if ($enablePagination==PAGINATION_ON){
		    $this->paginationBehavior = new PaginationBehavior($dataHandlerParams);
		}
	}
	
	public function applyViewerAndBuildDataGraph($itemList){
		logDebug("Viewer URI is $this->viewerUri");
		$array  = $this->SparqlWriter->getViewQueryForBatchUriList($itemList, $this->viewerUri);
		//TODO should do a check on array
		$this->viewQuery  = $array['expandedQuery'];
		if (LOG_VIEW_QUERIES) {
			logViewQuery( $this->Request, $this->viewQuery);
		}
		$response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);
		
		if($response->is_success()){
			$this->buildDataGraphFromIMSAndTripleStore($response, $array['imsRDF'], $itemList);
		}
		else {
			logError("Endpoint returned {$response->status_code} {$response->body} View Query <<<{$this->viewQuery}>>> failed against {$this->SparqlEndpoint->uri}");
			throw new ErrorException("The SPARQL endpoint used by this URI configuration did not return a successful response.");
		}
	}
	
	private function buildDataGraphFromIMSAndTripleStore($tripleStoreResponse, $imsRDF, $list){
		$rdf = $tripleStoreResponse->body;
		if(isset($tripleStoreResponse->headers['content-type'])){
			if(strpos($tripleStoreResponse->headers['content-type'], 'turtle')){
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
		
		$this->DataGraph->add_turtle($imsRDF);
		
		if ($this->paginationBehavior){
		    $this->pageUri = $this->paginationBehavior->addListMetadataToDataGraph($list);
		}
		else{
		    $this->pageUri = $this->addListMetadataToDataGraph($list);
		}
	}
	
	protected function addListMetadataToDataGraph($list){
	    $listUri = $this->Request->getUriWithoutParam(array('_view', '_page'), 'strip extension');
	   
	    $this->DataGraph->add_resource_triple($listUri, API.'definition', $this->endpointUrl);
	    $this->DataGraph->add_resource_triple($listUri, RDF_TYPE, API.'List');
	    $this->DataGraph->add_literal_triple($listUri, DCT.'modified', date("Y-m-d\TH:i:s"), null, XSD.'dateTime' );
	    $rdfListUri = '_:itemsList';
	    $this->DataGraph->add_resource_triple($listUri, API.'items', $rdfListUri);
	    $this->DataGraph->add_resource_triple($rdfListUri, RDF_TYPE, RDF_LIST);
	    foreach($list as $no => $resourceUri){
	        $nextNo = ($no+1);
	        $nextList = (($no+1) == count($list))? RDF_NIL : '_:itemsList'.$nextNo;
	        $this->DataGraph->add_resource_triple($rdfListUri, RDF_FIRST, $resourceUri);
	        $this->DataGraph->add_resource_triple($rdfListUri, RDF_REST, $nextList);
	        $rdfListUri = $nextList;
	    }
	    
	    return $listUri;
	}
	
	public function getViewQuery(){
		return $this->viewQuery;
	}
	
	public function getPageUri(){
		return $this->pageUri;
	}
	
}

?>