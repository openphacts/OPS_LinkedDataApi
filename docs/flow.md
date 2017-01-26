---
layout: default
---

# Description of Flow

User issues a HTTP call, such as:

```
URL="https://beta.openphacts.org/2.1/compound?uri=http%3A%2F%2Fwww.conceptwiki.org%2Fconcept%2F38932552-111f-4a4e-a46a-4ed1d7bdf9d5&app_id=11a22&app_key=def456&_format=json"
curl -X GET --header "Accept: application/json" $URL
```

(Note: contains fake values for 'app_id' and 'app_key')

The HTTP request will be processed by the top-level `index.php` file.

## file index.php

The flow in `index.php`:

1. First, the memcache cache is checked.  If the request is in the cache, the cached result is returned and processing ends.
2. Loop thru all of the RDF *.ttl files in the 'api-config-files' directory.
3. For each file, load the rdf/ttl file into an RDF Graph, store in the variable $ConfigGraph.
  * Note: the $ConfigGraph object also contains the $Request object.
4. If the config of $ConfigGraph matches the $Request, you have the right config. do:
  1. construct a $Response object from the $Request and $ConfigGraph
  2. call $Response->process()
  3. exit the loop.
5. call $Response->serve()
6. cache the $Request --> $Response pair.


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

$dataHandlerParams = new DataHandlerParams($this->Request, $this->ConfigGraph, $this->DataGraph,
                                           $viewerUri, $sparqlWriter,
                                           $this->SparqlEndpoint, $this->endpointUrl);

$this->dataHandler = DataHandlerFactory::createXXXDataHandler($dataHandlerParams);
// XXX is one of five factory methods to create a data handler, depending on type of endpoint.

$this->dataHandler->loadData();
```

The type of DataHandler created by DataHandlerFactory depends on the type of Endpoint of the matched
ConfigGraph.  Each ConfigGraph has an Endpoint of one of the following types:

- __ItemEndpoint__ -- return a single item (entity) or information about a single item.
- __ListEndpoint__ -- return value is a list of items or item information.
- __BatchEndpoint__ -- return information about a list of items (the input is a list).
- __IntermediateExpansionEndpoint__ -- (??) first query gets a list of items to run 2nd query on (??)
- __ExternalHTTPService__ -- delegate the operation to another web service, e.g., ConceptWiki, Chemistry Service, IMS.


## class DataHandlerFactory

5 static factory methods. The first 3 create a TwoStepDataHandler:

- createListDataHandler(.)
- createBatchDataHandler(.)
- createIntermediateExpansionDataHandler(.)
- createItemDataHandler(.)
- createExternalServiceDataHandler(.)


## interface DataHandler

Methods:

- loadData()

Type hierarchy of DataHandlers:

- OneStepDataHandler
  - ItemDataHandler
  - ExternalServiceDataHandler
- TwoStepDataHandler

A TwoStepDataHandler consists of a _Selctor_ and a _Viewer_.  The `loadData()` method is essentially:

```
  $viewer->applyViewerAndBuildDataGraph( $selctor->getItemMap() );
```


## interface Selector

A _Selector_ obtains a list of items (i.e., URIs or literal values) to be added to the eventual _real_ SPARQL query.

Methods:

- getItemMap()
  * Returns a map between variable names and lists of items
  * All selectors will return a map at least with the key 'item'
  * optionally, keys for expansionVariables can also appear i.e. 'compound_chembl'

Types of Selector:

- RequestSelector
  - Extracts the list of items from the HTTP Request.
- SparqlSelector
  - Issues a SPARQL query to obtain the list of items.
  - Obtains this SPARQL query via `SparqlWriter->getSelectQueryForUriList()`.


## interface Viewer

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


## class SparqlService

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
