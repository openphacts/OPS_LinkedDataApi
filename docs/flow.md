---
layout: default
---

# Description of Flow

User issues a HTTP call, such as:
```
URL="https://beta.openphacts.org/2.1/compound?uri=http%3A%2F%2Fwww.conceptwiki.org%2Fconcept%2F38932552-111f-4a4e-a46a-4ed1d7bdf9d5&app_id=11a22&app_key=def456&_format=json"
curl -X GET --header "Accept: application/json" $URL
```
(changed the values of app_id and app_key)

The HTTP request will be processed by the top-level `index.php` file.

The flow in `index.php`:

* First, the memcache cache is checked.  If the request is in the cache, the cached result is returned and processing ends.
* Loop thru all of the RDF *.ttl files in the 'api-config-files' directory.
* For each file, load the rdf/ttl file into an RDF Graph, store in the variable $ConfigGraph.
    * Note: the $ConfigGraph object also contains the $Request object.
* If the config of $ConfigGraph matches the $Request, you have the right config. do:
    * construct a $Response object from the $Request and $ConfigGraph
    * call $Response->process()
    * exit the loop.
* call $Response->serve()
* cache the $Request --> $Response pair.


## class LinkedDataApiRequest

The code is in file lda-request.class.php.

The LinkedDataApiRequest object contains the `$Request` string plus methods for parsing and slicing and dicing the request into various parts. All of the logic for computing the result is driven by the LinkedDataApiResponse class.

## class LinkedDataApiResponse

The code is in file lda-response.class.php.

Primary entry points are the `process()` and `serve()` methods.

- `process()` computes the result
- `serve()` handles converting the result to the desired serialization format (e.g., JSON, RDF, TSV, HTML).

### function process()

Main flow:
- Create a SparqlWriter, a Viewer, a SparqlService, a DataHandler, and then load the data:

Selected Statements:
```
    $sparqlWriter = new SparqlWriter($this->ConfigGraph, $this->Request, $this->ParameterPropertyMapper);
    $viewerUri = $this->getViewer();
    $this->SparqlEndpoint = new SparqlService($sparqlEndpointUri, $credentials, $this->HttpRequestFactory);

    $dataHandlerParams = new DataHandlerParams($this->Request,
        										$this->ConfigGraph, $this->DataGraph, $viewerUri,
        										$sparqlWriter, $this->SparqlEndpoint,
        										$this->endpointUrl);

    $this->dataHandler = DataHandlerFactory::createXXXDataHandler($dataHandlerParams);
    // XXX is one of five types of data handlers, depending on type of endpoint.

   	$this->dataHandler->loadData();
```

## DataHandler

Hierarchy of DataHandlers:

- OneStepDataHandler
    - ItemDataHandler
    - ExternalServiceDataHandler
- TwoStepDataHandler

A TwoStepDataHandler consists of two steps: a _Selector_ object and a _Viewer_ object.

## Selector

A _Selector_ obtains a list of items (i.e., URIs or literal values) to be added to the eventual _real_ SPARQL query.

Types of Selector:

- RequestSelector
    - Extracts the list of items from the HTTP Request.
- SparqlSelector
    - Issues a SPARQL query to obtain the list of items.
    - Obtains this SPARQL query via `SparqlWriter->getSelectQueryForUriList()`.

Doc for function _Selector.getItemMap()_:
```
    /**
     * Returns a map between variable names and lists of items
     * All selectors will return a map at least with the key 'item'
     * Optionally keys for expansionVariables can also appear i.e. 'compound_chembl'
     */
```

## Viewer

Types of Viewer:

- SingleExpansionViewer
- MultipleExpansionViewer

Methods of Viewer:

- function getViewQuery();
    - SingleExpansionViewer uses SparqlWriter->getViewQueryForUriList(..)
    - MultipleExpansionViewer uses SparqlWriter->getViewQueryForBatchUriList(..)
- function applyViewerAndBuildDataGraph($itemMap);
    - called as second step by TwoStepDataHandler->loadData( $selector->getItemMap() ).


## class SparqlWriter

Several methods for creating various kinds of SPARQL queries.

Calls OpsIms->expandQuery(...) and OpsIms->expandBatchQuery(...).

_SparqlWriter_ appears to be the only user of class OpsIms, and thus presumably of IMS & QueryExpander (as far as I can tell).


## class _SparqlService_

Part of the 'moriarity' library. Used to execute SPARQL queries.

Methods:

- query(...)
- graph(...)
    - execute a SPARQL query that returns an RDF Graph.


## class OpsIms

Class for using IMS and/or QueryExpander.

Methods:

- expandQuery(...)
- expandBatchQuery(...)
