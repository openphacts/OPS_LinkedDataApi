<?php

require_once 'data_handler_components/multiple_expansion_viewer.class.php';

define ('PAGINATION_OFF', 0);
define ('PAGINATION_ON', 1);

class PaginationBehavior {
    
    private $Request;
    private $ConfigGraph;
	private $DataGraph;
	private $SparqlWriter;
	private $endpointUrl;
    
    function __construct($dataHandlerParams){
        $this->Request = $dataHandlerParams->Request;
        $this->ConfigGraph = $dataHandlerParams->ConfigGraph;
		$this->DataGraph = $dataHandlerParams->DataGraph;
		$this->SparqlWriter = $dataHandlerParams->SparqlWriter;
		$this->endpointUrl = $dataHandlerParams->endpointUrl;
    }
    
    function addListMetadataToDataGraph($list){
        $listUri = $this->Request->getUriWithoutParam(array('_view', '_page'), 'strip extension');
        
        $pageUri = $this->Request->getUriWithPageParam();
        $currentPage = $this->Request->getPage();
        $this->DataGraph->add_resource_triple($listUri, API.'definition', $this->endpointUrl);
	$lens = $this->Request->getParam('_lens');
	if ($lens == '') $lens='Default';
	$this->DataGraph->add_literal_triple($listUri, OPS_API.'/activeLens', $lens);
	$this->DataGraph->add_resource_triple($listUri, VOID.'linkPredicate', SKOS.'exactMatch');
        $this->DataGraph->add_resource_triple($listUri, RDF_TYPE, API.'List');
        $this->DataGraph->add_resource_triple($pageUri, RDF_TYPE, API.'Page');
        if($label = $this->ConfigGraph->getPageTitle()){
            $this->DataGraph->add_literal_triple($pageUri, RDFS_LABEL, $label);
        }
	if ( strcasecmp($this->SparqlWriter->getLimit(), 'all') != 0) {
        	$this->DataGraph->add_resource_triple($listUri, DCT.'hasPart', $pageUri);
        	$this->DataGraph->add_resource_triple($pageUri, DCT.'isPartOf', $listUri);
        	$this->DataGraph->add_resource_triple($pageUri, XHV.'first', $this->Request->getUriWithPageParam(1));
        	if(count($list) >= $this->SparqlWriter->getLimit()){
        	    $this->DataGraph->add_resource_triple($pageUri, XHV.'next', $this->Request->getUriWithPageParam($currentPage+1));
        	}
        	if($currentPage > 1){
        	    $this->DataGraph->add_resource_triple($pageUri, XHV.'prev', $this->Request->getUriWithPageParam($currentPage-1));
	        }
	}
        //$this->DataGraph->add_literal_triple($pageUri, OPENSEARCH.'itemsPerPage', $this->SparqlWriter->getLimit(), null, XSD.'integer');
        $this->DataGraph->add_literal_triple($pageUri, OPENSEARCH.'startIndex', $this->SparqlWriter->getOffset(), null, XSD.'integer');
        $this->DataGraph->add_literal_triple($pageUri, DCT.'modified', date("Y-m-d\TH:i:s"), null, XSD.'dateTime' );
        $rdfListUri = '_:itemsList';
        
        $this->DataGraph->add_resource_triple($pageUri, API.'items', $rdfListUri);
        $this->DataGraph->add_resource_triple($rdfListUri, RDF_TYPE, RDF_LIST);
        foreach($list as $no => $resourceUri){
            $nextNo = ($no+1);
            $nextList = (($no+1) == count($list))? RDF_NIL : '_:itemsList'.$nextNo;
            $this->DataGraph->add_resource_triple($rdfListUri, RDF_FIRST, $resourceUri);
            $this->DataGraph->add_resource_triple($rdfListUri, RDF_REST, $nextList);
            $rdfListUri = $nextList;
        }
        $this->DataGraph->add_literal_triple($pageUri, OPENSEARCH.'itemsPerPage', $nextNo, null, XSD.'integer');
        return $pageUri;
    }
    
}
