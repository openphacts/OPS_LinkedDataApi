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

	function __construct(DataHandlerParams $dataHandlerParams, $enablePagination=PAGINATION_OFF){
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

	public function applyViewerAndBuildDataGraph($itemMap){
		logDebug("Viewer URI is $this->viewerUri");
		$itemList = $this->getInputListForExpansion($itemMap);

		$expansionData  = $this->SparqlWriter->getViewQueryForBatchUriList($itemMap['item'], $this->viewerUri, $itemList);
		if (isset($expansionData['expandedQuery'])){
			$expansionDataArray[]=$expansionData;
		} else {
			$expansionDataArray=$expansionData;
		}
		foreach ($expansionDataArray AS $key => $individualExpansionData) {
			$this->viewQuery  = $individualExpansionData['expandedQuery'];
			if (LOG_VIEW_QUERIES) {
				logViewQuery( $this->Request, $this->viewQuery);
			}
			$response = $this->SparqlEndpoint->graph($this->viewQuery, PUELIA_RDF_ACCEPT_MIMES);

			if($response->is_success()){
				$this->buildDataGraphFromIMSAndTripleStore($response, $individualExpansionData['imsRDF'], $itemMap['item']);
			}
			else {

              logSparqlError("VIEW query in MultipleExpansionViewer.applyViewerAndBuildDataGraph()",
                  $response, $this->viewQuery, $this->SparqlEndpoint->uri);

				throw new ErrorException("The SPARQL endpoint used by this URI configuration did not return a successful response.");
			}
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

		foreach ($this->DataGraph->get_subjects() as $subject) {
			foreach ($this->DataGraph->get_subject_properties($subject) as $property) {
				$this->DataGraph->remove_resource_triple($subject, $property, $subject);
			}
		}
		if ($this->paginationBehavior){
		    $this->pageUri = $this->paginationBehavior->addListMetadataToDataGraph($list);
		}
		else{
		    $this->pageUri = $this->addListMetadataToDataGraph($list);
		}
	}

	private function getInputListForExpansion($itemMap){
	    if (count($itemMap)> 1){
	        foreach ($itemMap as $key => $value){
	            if (strcmp($key, 'item')){
	                $inputList = $value;
	            }
	        }

	        if (empty($inputList)){
	            throw new ErrorException("Expansion Variable not found although > 1 items in the itemMap");
	        }
	    }

	    return $inputList;
	}

	protected function addListMetadataToDataGraph($list){
	    $listUri = $this->Request->getUriWithoutParam(array('_view', '_page'), 'strip extension');

	    $this->DataGraph->add_resource_triple($listUri, API.'definition', $this->endpointUrl);
	    $this->DataGraph->add_resource_triple($listUri, RDF_TYPE, API.'List');
	    $this->DataGraph->add_literal_triple($listUri, DCT.'modified', date("Y-m-d\TH:i:s"), null, XSD.'dateTime' );
	    $lens = $this->Request->getParam('_lens');
     	    if ($lens == '') $lens='Default';
            $this->DataGraph->add_literal_triple($listUri, OPS_API.'/activeLens', $lens);
            $this->DataGraph->add_resource_triple($listUri, VOID.'linkPredicate', SKOS.'exactMatch');
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
