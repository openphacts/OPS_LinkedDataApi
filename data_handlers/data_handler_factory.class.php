<?php

require_once 'data_handlers/2step_data_handler.class.php';
require_once 'data_handlers/item_data_handler.class.php';
require_once 'data_handlers/external_service_data_handler.class.php';
require_once 'data_handler_components/sparql_selector.class.php';
require_once 'data_handler_components/explicit_list_selector.class.php';
require_once 'data_handler_components/single_expansion_viewer.class.php';
require_once 'data_handler_components/multiple_expansion_viewer.class.php';

class DataHandlerFactory{
	
	public static function createListDataHandler($Request, $ConfigGraph, $DataGraph, $viewerUri, $SparqlWriter, $SparqlEndpoint, $endpointUrl){		
		$sparqlSelector = new SparqlSelector($Request, $SparqlWriter, $SparqlEndpoint);
		$singleExpansionViewer = new SingleExpansionViewer($Request, $ConfigGraph, $DataGraph, $SparqlWriter, $SparqlEndpoint, $viewerUri, $endpointUrl);
		
		return new TwoStepDataHandler($sparqlSelector, $singleExpansionViewer);
	}
	
	public static function createBatchDataHandler($Request, $DataGraph, $viewerUri, $SparqlWriter, $SparqlEndpoint, $endpointUrl){
		$explicitListSelector = new ExplicitListSelector($Request);
		$multipleExpansionViewer = new MultipleExpansionViewer($Request, $DataGraph, $SparqlWriter, $SparqlEndpoint, $viewerUri, $endpointUrl);
		
		return new TwoStepDataHandler($explicitListSelector, $multipleExpansionViewer);
	}
	
	public static function createIntermediateExpansionDataHandler($Request, $DataGraph, $viewerUri, $SparqlWriter, $SparqlEndpoint, $endpointUrl){
		
		$sparqlSelector = new SparqlSelector($Request, $SparqlWriter, $SparqlEndpoint);
		$multipleExpansionViewer = new MultipleExpansionViewer($Request, $DataGraph, $SparqlWriter, $SparqlEndpoint, $viewerUri, $endpointUrl);
		
		return new TwoStepDataHandler($sparqlSelector, $multipleExpansionViewer);
	}
	
	public static function createItemDataHandler($Request,
				$ConfigGraph, $DataGraph, $viewerUri,
				$SparqlWriter, $SparqlEndpoint, $endpointUrl){
		new ItemDataHandler($Request,
				$ConfigGraph, $DataGraph, $viewerUri,
				$SparqlWriter, $SparqlEndpoint,
				$endpointUrl);
	}
	
	public static function createExternalServiceDataHandler($Request,
				$ConfigGraph, $DataGraph, $viewerUri,
				$SparqlWriter, $SparqlEndpoint, $endpointUrl){
		return new ExternalServiceDataHandler($Request,
				$ConfigGraph, $DataGraph, $viewerUri,
				$SparqlWriter, $SparqlEndpoint,
				$endpointUrl);
	}
	
}

?>