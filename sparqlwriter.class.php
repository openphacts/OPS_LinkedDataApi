<?php
require_once 'graphs/vocabularygraph.class.php';
require_once 'ops_ims.class.php';
require_once 'virtuosoformatter.class.php';
class SparqlWriter {
    
    private $_config;
    private $_request;
    private $_parameterPropertyMapper;
    
    var $_unknownPropertiesFromRequestParameter = array();
    var $_unknownPropertiesFromConfig = array();
    
    function __construct($config, $request, $parameterPropertyMapper){
        $this->_config = $config;
        $this->_request = $request;
        $this->_parameterPropertyMapper = $parameterPropertyMapper;
    }
    
    private function addPrefixesToQuery($query){
        $prefixesString='';
        $prefixes = $this->getConfigGraph()->getPrefixesFromLoadedTurtle();
        preg_match_all('/([a-zA-Z_\-]+)\:[a-zA-Z0-9_\-]+/', $query, $matches);
        
        foreach($matches[1] as $prefix){
            if(isset($prefixes[$prefix])){
                $ns = $prefixes[$prefix];
                $prefixesString.="PREFIX {$prefix}: <{$ns}>\n";
                unset($prefixes[$prefix]);
            }
        }
        return $prefixesString.$query;
    }
    
    function getLimit(){
        $maxPageSize = $this->getConfigGraph()->getMaxPageSize();
        $requestedPageSize = $this->_request->getParam('_pageSize');
        $endpointDefaultPageSize = $this->getConfigGraph()->getEndpointDefaultPageSize();
        $apiDefaultPageSize = $this->getConfigGraph()->getApiDefaultPageSize();
        if($maxPageSize && $requestedPageSize > $maxPageSize) return $apiDefaultPageSize;
        else if($requestedPageSize) return $requestedPageSize;
        else if($endpointDefaultPageSize) return $endpointDefaultPageSize;
        else if($apiDefaultPageSize) return $apiDefaultPageSize;
        else return 10;
    }
    
    function getDefaultSelectLangs(){
        $requestedDefaultLangs = $this->_request->getParam('_lang');
        $endpointDefaultLangs = $this->getConfigGraph()->getEndpointDefaultLangs();
        $apiDefaultLangs = $this->getConfigGraph()->getApiDefaultLangs();
        if ($requestedDefaultLangs) return explode(',', $requestedDefaultLangs);
        else if($endpointDefaultLangs) return explode(',', $endpointDefaultLangs);
        else if($apiDefaultLangs) return explode(',', $apiDefaultLangs);
        else return null;
    }
    
    function filterValueToSparqlTerm($val, $langs, $propertyUri){
        $varNames = $this->getConfigGraph()->variableNamesInValue($val);
        $bindings = $this->getConfigGraph()->getAllProcessedVariableBindings();
#Antonis botch
	if (!$varNames AND isset($bindings['uri'])){
		$varNames[]='uri';
		$bindings['uri']['type'] = RDFS.'Resource' ;
	}
        if($varNames){
            foreach($varNames as $varName){
                if(isset($bindings[$varName])){
                    $binding = $bindings[$varName];
                    return array($this->variableBindingToSparqlTerm($binding, $propertyUri));
                } else {
                    throw new ConfigGraphException("The variable {$varName} has no binding");
                }
            }
        } else if($uri = $this->getConfigGraph()->getUriForVocabPropertyLabel($val)){
            $namespaces = $this->getConfigGraph()->getPrefixesFromLoadedTurtle();
            return array($this->qnameOrUri($uri, $namespaces));
        } else {
            $literal =  '"""'.$val.'"""';
            return $this->addDatatypeOrLangToLiteral($literal, $propertyUri, $langs);
        }
    }
    
    private function addDatatypeOrLangToLiteral($literal, $propertyUri=false, $langs=null){
        if($propertyUri){
            if($propertyRange = $this->getConfigGraph()->getVocabPropertyRange($propertyUri) AND $propertyRange!=RDFS_LITERAL){
                $literal .= '^^<'.$propertyRange.'>';
                return array($literal);
            } else {
                return $this->addLangToLiteral($literal, $langs);
            }
        } else {
          return $this->addLangToLiteral($literal, $langs);
        }
    }
    
    private function addLangToLiteral($literal, $langs){
        if ($langs){
          $literals = array();
          foreach($langs as $lang) {
            $literals[] = $literal.'@'.$lang;
          }
          return $literals;
        } else {
          return array($literal);
        }
    }
    
    private function fillQueryTemplate($template, $bindings){
        foreach($bindings as $name => $props){
#Antonis botch
	    if ($name == 'uri') {
		$props['type'] = RDFS.'Resource';
	    }
            $sparqlVal = $this->variableBindingToSparqlTerm($props);
            $sparqlVar = '?'.$name;
            logDebug("SPARQL Variable binding: {$sparqlVal} = {$sparqlVar}");
            //replace all variables with values 
            //(but not variables that simply start with this variable name)
            $template = preg_replace('/\\'.$sparqlVar.'([^_a-zA-Z0-9])/', $sparqlVal.'$1', $template); # the \ is to escape the ? and needs \\ because it is escape char in php ...
        }
        return $template;
    }
    
    private function variableBindingToSparqlTerm($props, $propertyUri=false){
        if(isset($props['type']) AND $props['type'] == RDFS.'Resource'){
            $sparqlVal = "<{$props['value']}>";
        } else {
            $sparqlVal = '"""'.$props['value'].'"""';
            if(isset($props['lang'])){
                $sparqlVal.='@'.$props['lang'];
            } else if(isset($props['datatype'])){
                $sparqlVal.='^^<'.$props['datatype'].'>';
            } else if(isset($props['type'])){
                $sparqlVal.='^^<'.$props['type'].'>';
            } else {
                $sparqlVal = $this->addDatatypeOrLangToLiteral($sparqlVal, $propertyUri);
                $sparqlVal = $sparqlVal[0];
            }
        }
        return $sparqlVal;
    }
    
    function getOffset(){
        $pageNo = $this->_request->getPage();
        return ($pageNo - 1 ) * $this->getLimit();
    }
    
    
    function getOrderBy(){
        $graphConditions = false;
        $orderBy = false;
        if($orderByRequestParam = $this->_request->getParam('_orderBy')){
            $orderBy = 'ORDER BY '.$orderByRequestParam;
        } else if($sort = $this->_request->getParam('_sort')){
            return $this->sortToOrderBy($sort, 'request');
        } else if($orderByConfig = $this->getConfigGraph()->getOrderBy()){
            $bindings = $this->getConfigGraph()->getAllProcessedVariableBindings();
            $orderByConfig = $this->fillQueryTemplate($orderByConfig, $bindings);
            $orderBy = 'ORDER BY '.$orderByConfig;
        } else if($sort = $this->getConfigGraph()->getSort()){
            return $this->sortToOrderBy($sort, 'config');
        }
        return array(
            'graphConditions' => $graphConditions,
            'orderBy' => $orderBy,
        );
    }
    
    private function sortToOrderBy($sort, $source){
        $sortPropNames = explode(',',$sort);
        $propertyLists = array();
        foreach($sortPropNames as $sortName){
            $ascOrDesc = ($sortName[0]=='-')? 'DESC' : 'ASC';
            $sortName = ltrim($sortName, '-');
            $propertyLists[]= array(
                    'sort-order' => $ascOrDesc,
                    'property-list'=> $this->_parameterPropertyMapper->mapParamNameToProperties($sortName),
                );
        }
        
        return $this->propertyNameListToOrderBySparql($propertyLists);
    }
    
    
    private function propertyNameListToOrderBySparql($propertyLists){
        $namespaces = $this->getConfigGraph()->getPrefixesFromLoadedTurtle();
        $sparql = '';
        $orderBy = "ORDER BY ";
        $variableNames = array();
        foreach($propertyLists as $propertiesListHash){
            $propertiesList = $propertiesListHash['property-list'];
            $sortOrder = $propertiesListHash['sort-order'];
            $propertyNames = array_keys($propertiesList);
            $counter=0;
            $name = $propertyNames[0];
            $varName = $name;
            $propUri = $propertiesList[$name];
            $propQnameOrUri = $this->qnameOrUri($propUri, $namespaces);
            $sparql.= "\n  ?item {$propQnameOrUri} ?{$name} .";
            $variableNames[$name] = $propUri;
            foreach($propertiesList as $name => $propUri){
                 if(isset($propertyNames[$counter+1])){ //if this ISN'T the last property
                     $nextName = $propertyNames[$counter+1];
                 } else if (count($propertyNames) ==1){
                     $orderBy.= $sortOrder.'(?'.$name.') ';
                     $varName = 'item';
                     $nextName = $propertyNames[0];
                 }
                 
                 $nextProp = $propertiesList[$nextName];
                 $nextPropQnameOrUri = $this->qnameOrUri($nextProp, $namespaces);
                 $nextVarName = $varName.'_'.$nextName;

                 if ( ($counter+1) < count($propertyNames)  ){ //if not last item
                     $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} .";  
                     $variableNames[$nextVarName] = $nextProp; 
                 }
                 
                 if ( ($counter+2) == count($propertyNames) ){
                     //if this is the last property in the chain, add to the order by
                     $orderBy.= $sortOrder.'(?'.$nextVarName.') ';
                 }
                 
                 $varName = $nextVarName;
                 $counter++;
             }
            
        }
        return array('graphConditions' => $sparql, 'orderBy' => $orderBy); 
    }
    
    
    function getSelectQueryForUriList(){
        if($query = $this->getExplicitSelectQuery()){
            return $this->addPrefixesToQuery($query);
        } else {
            return $this->getGeneratedSelectQuery();
        }
    }    
    
    private function getExplicitSelectQuery(){
        if($template = $this->getConfigGraph()->getSelectWhere()){
            $limit = $this->getLimit();
            $offset = $this->getOffset();
            $orderBy = $this->getOrderBy();
            if (empty($orderBy['orderBy']) AND stristr($template,'ORDER BY')===FALSE) {
                $orderBy['orderBy']='ORDER BY ?item';
            }
            $sparql= "SELECT DISTINCT ?item WHERE {" .  "{$template} } {$orderBy['orderBy']}";
            if (strcasecmp($limit,"all")!==0) {
                $sparql.="  LIMIT {$limit} OFFSET {$offset}";
            }
	    $lens_uri = $this->_request->getParam('_lens');
            $ops_uri = $this->_request->getParam('uri');
            $sparql = str_replace('?ops_item', '<'.$ops_uri.'>', $sparql);
            //$ims = new OpsIms();
	    //$sparql = $ims->expandQuery($this->addPrefixesToQuery($sparql), $ops_uri, $lens_uri);
	    $filterGraph = $this->getFilterGraph();
	    if (!empty($filterGraph)) {
	    	foreach ($filterGraph as $sparqlVar => $filterClause) {
			if (preg_match("/VALUES/", $filterClause)===1) {
				$sparql=preg_replace("/(WHERE.*?\{)/s", "$1 {$filterClause}", $sparql);
			}
                        else if ( preg_match("/GRAPH[^\}]*?\{[^\}]*?\\".$sparqlVar."/", $query)===1 ){
                                $query=preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
                        else {
                                $query=preg_replace("/(WHERE.*?)(GRAPH[^\}]*[^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
		}
	    }
	    $ims = new OpsIms();
            $sparql = $ims->expandQuery($this->addPrefixesToQuery($sparql), $ops_uri, $lens_uri);
	    //$sparql = preg_replace("/\*#\*/","}",$sparql);
            $formatter = new VirtuosoFormatter();
            #	    echo $formatter->formatQuery($ims->expandQuery($this->addPrefixesToQuery($sparql), $ops_uri));
            return $formatter->formatQuery($sparql);
        } else {
            return false;
        }

    }
    
    private function getGeneratedSelectQuery(){
        $GroupGraphPattern = $this->getGroupGraphPattern();
        $order = $this->getOrderBy();
        $limit = $this->getLimit();
        $offset = $this->getOffset();
        $fromClause = $this->getFromClause();
        $query = <<<_SPARQL_
SELECT DISTINCT ?item
{$fromClause}
WHERE {
{$GroupGraphPattern}
{$order['graphConditions']}
}
{$order['orderBy']}
LIMIT {$limit}
OFFSET {$offset}
_SPARQL_;
    return $this->addPrefixesToQuery($query);
    }
    
    private function getGroupGraphPattern(){
        $whereRequestParam              =  $this->_request->getParam('_where');
        $selectorConfigWhereProperty    = $this->getConfigGraph()->getSelectWhere();
        $bindings = $this->getConfigGraph()->getAllProcessedVariableBindings();
        $selectorConfigWhereProperty = $this->fillQueryTemplate($selectorConfigWhereProperty, $bindings);
        if(!empty($whereRequestParam)) $whereRequestParam = '{'.$whereRequestParam.'}';
        if(!empty($selectorConfigWhereProperty)) $selectorConfigWhereProperty = '{'.$selectorConfigWhereProperty.'}';
        $GGP = "{$whereRequestParam}\n{$selectorConfigWhereProperty}\n ";
        $filter = implode( '&', $this->getConfigGraph()->getAllFilters());
        foreach($this->_request->getUnreservedParams() as $k => $v){
            list($k, $v) = array(urlencode($k), urlencode($v));
            $filter.="&{$k}={$v}";
        }
        logDebug("Filter is: {$filter}");
        $params = queryStringToParams($filter);
        $langs = array();
        foreach($params as $k => $v) {
            if (strpos($k, 'lang-') === 0) {
                $langs[substr($k, 5)] = $v;
                unset($params[$k]);
            }
        }
        $GGP .= $this->paramsToSparql($params, $langs);
    
        $GGP = trim($GGP);
        if(empty($GGP)){
            $GGP = "\n  ?item ?property ?value .";
        }
    
        return $GGP;
    }
    
    private function paramsToSparql($paramsArray, $langArray=array()){
        $sparql = '';
        $filters = '';
        $namespaces = $this->getConfigGraph()->getPrefixesFromLoadedTurtle();
        $rdfsLabelQnameOrUri = $this->qnameOrUri(RDFS_LABEL, $namespaces);
        $defaultLangs = $this->getDefaultSelectLangs();
        foreach($paramsArray as $k => $v){

            $prefix = $this->_parameterPropertyMapper->prefixFromParamName($k);
            $propertiesList = $this->_parameterPropertyMapper->mapParamNameToProperties($k);
            $propertyNames = $this->_parameterPropertyMapper->paramNameToPropertyNames($k);
            $counter=0;
            $name = $propertyNames[0];
            $varName = $name;
            $nextVarName = '';
            $propUri = $propertiesList[$name];
            $propQnameOrUri = $this->qnameOrUri($propUri, $namespaces);
            $lastPropUri = array_pop(array_values($propertiesList));
            $langs = array_key_exists($k, $langArray) ? array($langArray[$k]) : $defaultLangs;
            $processedFilterValues = $this->filterValueToSparqlTerm($v, $langs, $lastPropUri);
            $nValues = count($processedFilterValues);

            if(count($propertyNames) > 1){
                $sparql.= "\n  ?item {$propQnameOrUri} ?{$name} . ";
            }

            foreach($propertiesList as $name => $propUri){
                if(isset($propertyNames[$counter+1]) OR count($propertyNames)==1){ //if this ISN'T the last property or is the only property
                    if(count($propertyNames)==1){
                        $varName = 'item';
                        $nextName = $propertyNames[0];
                        $nextVarName = $nextName;
                    } else {
                        $nextName = $propertyNames[$counter+1];
                        $nextVarName = $varName.'_'.$nextName;
                    }

                    $nextProp = $propertiesList[$nextName];
                    $nextPropQnameOrUri = $this->qnameOrUri($nextProp, $namespaces);

                    //need to cast $nextVarName to compare it with $processedFilterValue
                    $castNextVarName = $this->castOrderByVariable($nextVarName, $nextProp);

                    if ( (($counter+2) == count($propertyNames) OR count($propertyNames)==1)){ //if last item or only item
                        if (!$prefix) {
                            if ($nValues > 1) {
                                foreach($processedFilterValues as $position => $processedFilterValue) {
                                    if ($position) {
                                        $sparql .= "\n  UNION";
                                    }
                                    $sparql.="\n  { ?{$varName} {$nextPropQnameOrUri} {$processedFilterValue} . }";
                                }
                            } else {
                                $processedFilterValue = $processedFilterValues[0];
                                $sparql .= "\n  ?{$varName} {$nextPropQnameOrUri} {$processedFilterValue} . ";
                            }
                        } else if($prefix=='min') {
                            $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} . \n  FILTER (?{$nextVarName} >= {$processedFilterValues[0]})";
                        } else if($prefix=='max') {
                            $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} . \n  FILTER (?{$nextVarName} <= {$processedFilterValues[0]})";
                        } else if($prefix == 'minEx') {
                            $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} . \n  FILTER (?{$nextVarName} > {$processedFilterValues[0]})";
                        } else if($prefix == 'maxEx') {
                            $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} . \n  FILTER (?{$nextVarName} < {$processedFilterValues[0]})";
                        } else if($prefix == 'name') {
                            $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} .\n";
                            foreach($processedFilterValues as $position => $processedFilterValue) {
                                if ($nValues > 1) {
                                    $sparql.="\n  {";
                                }
                                $sparql.="\n  ?{$nextVarName} {$rdfsLabelQnameOrUri} {$processedFilterValue} . ";
                                if ($nValues > 1) {
                                    $sparql.="\n  } ";
                                    if ($position + 1 < $nValues) {
                                        $sparql.="\n UNION ";
                                    }
                                }
                            }
                        } else if($prefix == 'exists') {
                            if($v=="true"){
                                $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} [] . ";
                            } else {
                                $sparql.="\n  OPTIONAL { \n    ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} . \n  } \n  FILTER (!bound(?{$nextVarName})) ";
                            }
                        }
                    } else {
                        $sparql.="\n  ?{$varName} {$nextPropQnameOrUri} ?{$nextVarName} . ";
                    }
                    $varName = $nextVarName;
                }
                $counter++;
            }


        }
        return  $sparql;
    }
    
    private function castOrderByVariable($varName, $propertyUri){
        $xsdDatatypes = array(
            XSD."integer"  ,
            XSD."int"  ,
            XSD."decimal"  ,
            XSD."float"    ,
            XSD."double"   ,
            XSD."string"   ,
            XSD."boolean"  ,
            XSD."dateTime" ,
            );
        if($propertyRange = $this->getConfigGraph()->getVocabPropertyRange($propertyUri) AND in_array($propertyRange, $xsdDatatypes)){
            return "<{$propertyRange}>(?{$varName})";
        } else {
            return "?{$varName}";
        }
    }
            
    function getFilterGraph() {
    	$params = $this->_request->getParams();
    	$vars = $this->_config->getApiConfigVariableBindings();
    	$ep_vars = $this->_config->getEndpointConfigVariableBindings();
    	$filterGraph = array();
    	
    	$count=1;//TODO ??
    	foreach ($params as $param_name => $param_value) {
    		if ($param_name != 'uri' && $param_name != 'uri_list'){
    			foreach ($vars as $var_name => $var_props) {
    				if ($param_name==$var_name AND $param_value !=""){
    					$filterPredicate = $this->findSuperProperty($var_props['uri']);
					if ($filterPredicate === API.'graphFilter'){
						$this->getFilterGraphForGraphValue($param_value, $var_props, $ep_vars, $filterGraph);	
					}
    					elseif (stripos($param_value , "|")!== false){
    						$this->getFilterGraphForComposedParamValue($param_value, $filterPredicate, $var_props, $ep_vars, $filterGraph);
    					}
    					else {
    						$this->getFilterGraphForParamValue($param_value, $filterPredicate, $var_props, $ep_vars, $filterGraph);
    					}
    				}
    				elseif (stripos($param_name,"min-")!==false AND substr($param_name,4) == $var_name){
    					$this->getFilterForBoundaryValue($var_props, $var_name, $param_value, '>=', $filterGraph); 					
       					$count++;
    				}
    				elseif (stripos($param_name,"max-")!==false AND substr($param_name,4) == $var_name){
    					$this->getFilterForBoundaryValue($var_props, $var_name, $param_value, '<=', $filterGraph); 					
       					$count++;
    				}
    				elseif (stripos($param_name,"minEx-")!==false AND substr($param_name,6) == $var_name){
    					$this->getFilterForBoundaryValue($var_props, $var_name, $param_value, '>', $filterGraph); 					
       					$count++;
    				}
    				elseif (stripos($param_name,"maxEx-")!==false AND substr($param_name,6) == $var_name){
    					$this->getFilterForBoundaryValue($var_props, $var_name, $param_value, '<', $filterGraph);
    				  	$count++;
    				}
    			}
    		}
    	}
    	if (!empty($filterGraph)){
    		return $filterGraph;
    	}
    }

    private function getFilterGraphForGraphValue($param_value, $var_props, $ep_vars, &$filterGraph){
	if (isset($filterGraph[$var_props['sparqlVar']])) {
		$filterGraph[$var_props['sparqlVar']] .= "VALUES {$var_props['sparqlVar']} { <{$ep_vars[$param_value]['uri']}> }";
	}
	else {
                $filterGraph[$var_props['sparqlVar']] = "VALUES {$var_props['sparqlVar']} { <{$ep_vars[$param_value]['uri']}> }";
	}
    }
    
    private function getFilterForBoundaryValue($var_props, $var_name, $param_value, $relation, &$filterGraph){
    	$filterPredicate = $this->findSuperProperty($var_props['uri']);
	if (isset($filterGraph[$var_props['sparqlVar']])) {
    		$filterGraph[$var_props['sparqlVar']] .= "{ " . $var_props['sparqlVar'] . " <" . $filterPredicate . '> ?' . $var_name . " FILTER( ?" . $var_name . ' '.$relation.' ' . $param_value . ' ) *#*';
	}
	else {
		$filterGraph[$var_props['sparqlVar']] = "{ " . $var_props['sparqlVar'] . " <" . $filterPredicate . '> ?' . $var_name . " FILTER( ?" . $var_name . ' '.$relation.' ' . $param_value . ' ) *#*';
	}
    }
    
    private function getFilterGraphForComposedParamValue($param_value, $filterPredicate, $var_props, $ep_vars, &$filterGraph){
    	$token = strtok($param_value,'|');
    	$filter="{ " . $var_props['sparqlVar']  . " <" . $filterPredicate . '> "' . $token  . '"' . "*#*";
    	$token=strtok('|');
    	while ($token != false){
    		if (isset($ep_vars[$param_value]['uri'])) {
    			$token = '<' . $ep_vars[$token]['uri'] . '>';
    		}
		elseif (filter_var($param_value, FILTER_VALIDATE_URL) !== false) {
                	$param_value = '<' . $param_value . '>';
        	}
    		else {
    			if (is_numeric($token)) {
    				$token = '"' . $token  . '"^^<http://www.w3.org/2001/XMLSchema#float>';
    			}
    			else {
    				$token = '"' . $token  . '"';
    			}
    		}
    		$filter.="UNION { " . $var_props['sparqlVar']  . " <" . $filterPredicate . '> ' . $token  . "*#*";
    		$token=strtok('|');
    	}
	if (isset($filterGraph[$var_props['sparqlVar']])) {
		$filterGraph[$var_props['sparqlVar']].=$filter;
	}
	else {
		$filterGraph[$var_props['sparqlVar']]=$filter;
	}
    }

    private function getFilterGraphForParamValue($param_value, $filterPredicate, $var_props, $ep_vars, &$filterGraph){
        if (isset($ep_vars[$param_value]['uri'])) {
            $param_value = '<' . $ep_vars[$param_value]['uri'] . '>';
        }
        elseif (filter_var($param_value, FILTER_VALIDATE_URL) !== false) {
            $param_value = '<' . $param_value . '>';
        }
        else {
            if (is_numeric($param_value)) {
                $param_value = '"' . $param_value  . '"^^<http://www.w3.org/2001/XMLSchema#float>';
            }
            else {
                $param_value = '"' . $param_value  . '"';
            }
        }
	if (isset($filterGraph[$var_props['sparqlVar']])) {
	        $filterGraph[$var_props['sparqlVar']] .= '{ ' . $var_props['sparqlVar']  . '<' . $filterPredicate . '> ' . $param_value  . ". *#*";
	}
	else {
		$filterGraph[$var_props['sparqlVar']] = '{ ' . $var_props['sparqlVar']  . '<' . $filterPredicate . '> ' . $param_value  . ". *#*";
	}
    }

    private function findSuperProperty($variableURI){
        $current = $parent = $variableURI;
        while ($parent!==null){
            $current = $parent;
            $parent = $this->_config->get_first_resource($current, RDFS.'subPropertyOf');
        }

        return $current;
    }

    function getViewQueryForBatchUriList($uriList, $viewerUri) {
        if(($template = $this->_request->getParam('_template') OR $template = $this->_config->getViewerTemplate($viewerUri)) AND !empty($template)
                AND $whereGraph = $this->_config->getViewerWhere($viewerUri) AND !empty($whereGraph)){
            $query = $this->addPrefixesToQuery("CONSTRUCT { {$template}  } {$fromClause} WHERE { {$whereGraph} }");

            if ($ops_uri = $this->_request->getParam('uri') AND !empty($ops_uri)){
                $query = str_replace('?ops_item', '<'.$ops_uri.'>', $query);
            }
	    $filterGraph = $this->getFilterGraph();
	    if (!empty($filterGraph)) {
            	foreach ($filterGraph as $sparqlVar => $filterClause) {
                	if (preg_match("/VALUES/", $filterClause)===1) {
                        	$query=preg_replace("/(WHERE.*?\{)/s", "$1 {$filterClause}", $query);
                	}
                        else if ( preg_match("/GRAPH[^\}]*?\{[^\}]*?\\".$sparqlVar."/", $query)===1 ){
                                $query=preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
                        else {
                                $query=preg_replace("/(WHERE.*?)(GRAPH[^\}]*[^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
            	}
	    }
            //$query = preg_replace("/\*#\*/","}",$query);

            $ims = new OpsIms();
            return $ims->expandBatchQuery($query, $uriList, $this->_request->getParam('_lens'));
        }

    }
     
    function getViewQueryForUri($uri, $viewerUri){
        return $this->getViewQueryForUriList(array($uri), $viewerUri);
    }

    function getViewQueryForUriList($uriList, $viewerUri){

        $fromClause = $this->getFromClause();
        #Antonis botch
        $limit = $this->getLimit();
        if(($template = $this->_request->getParam('_template') OR $template = $this->_config->getViewerTemplate($viewerUri)) AND !empty($template)
                AND $whereGraph = $this->_config->getViewerWhere($viewerUri) AND !empty($whereGraph)
                AND $ops_uri = $this->_request->getParam('uri') AND !empty($ops_uri)){
            $query='Something went wrong';
            if ($this->_config->getEndpointType() == API.'ListEndpoint' AND strcasecmp($limit,"all")!==0 ) {
                $query = str_replace('?ops_item', '<'.$ops_uri.'>', $this->addPrefixesToQuery("CONSTRUCT { {$template}  } {$fromClause} WHERE { " .  $whereGraph  . " }"));
            }
            elseif ($this->_config->getEndpointType() == API.'ListEndpoint' AND strcasecmp($limit,"all")== 0 ) {
                $query = str_replace('?ops_item', '<'.$ops_uri.'>', $this->addPrefixesToQuery("CONSTRUCT { {$template}  } {$fromClause} WHERE { {$whereGraph} }"));
		$filterGraph = $this->getFilterGraph();
		if (!empty($filterGraph)) {
            	    foreach ($filterGraph as $sparqlVar => $filterClause) {
                	if (preg_match("/VALUES/", $filterClause)===1) {
                        	$query=preg_replace("/(WHERE.*?\{)/s", "$1 {$filterClause}", $query);
                	}
                        else if ( preg_match("/GRAPH[^\}]*?\{[^\}]*?\\".$sparqlVar."/", $query)===1 ){
                                $query=preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
                        else {
                                $query=preg_replace("/(WHERE.*?)(GRAPH[^\}]*[^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
            	    }
		}
            	//$query = preg_replace("/\*#\*/","}",$query);
            }
            else {
                $query = str_replace('?ops_item', '<'.$ops_uri.'>', $this->addPrefixesToQuery("CONSTRUCT { {$template}  } {$fromClause} WHERE { {$whereGraph} }"));
                $filterGraph = $this->getFilterGraph();
		if (!empty($filterGraph)) {
                    foreach ($filterGraph as $sparqlVar => $filterClause) {
                        if (preg_match("/VALUES/", $filterClause)===1) {
                                $query=preg_replace("/(WHERE.*?\{)/s", "$1 {$filterClause}", $query);
                        }
                        else if ( preg_match("/GRAPH[^\}]*?\{[^\}]*?\\".$sparqlVar."/", $query)===1 ){
                                $query=preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
                        else {
                                $query=preg_replace("/(WHERE.*?)(GRAPH[^\}]*[^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
                    }
		}
                //$query = preg_replace("/\*#\*/","}",$query);
            }

            $ims = new OpsIms();
            $expandedQuery = $ims->expandQuery($query, $ops_uri, $this->_request->getParam('_lens'));
            if ($this->_config->getEndpointType() == API.'ListEndpoint' AND strcasecmp($limit,"all")!==0) {
                $filterGraph = "VALUES ?item { ";
                foreach($uriList as $uri) {
                    $filterGraph .= " <{$uri}> ";
                }
                $filterGraph .= "}";
		$expandedQuery = preg_replace("/(WHERE.*?\{)/s","$1 
                        {$filterGraph}",$expandedQuery);
            }

            $formatter = new VirtuosoFormatter();
            return $formatter->formatQuery($expandedQuery);
        } else if(($template = $this->_request->getParam('_template') OR $template = $this->_config->getViewerTemplate($viewerUri)) AND !empty($template)){
            $query = $this->addPrefixesToQuery("CONSTRUCT { {$template} } {$fromClause} WHERE { {$this->_config->getViewerWhere($viewerUri)}  }");
            $filterGraph = $this->getFilterGraph();
	    if (!empty($filterGraph)) {
            	foreach ($filterGraph as $sparqlVar => $filterClause) {
                	if (preg_match("/VALUES/", $filterClause)===1) {
                    		$query=preg_replace("/(WHERE.*?\{)/s", "$1 {$filterClause}", $query);
                	}
                        else if ( preg_match("/GRAPH[^\}]*?\{[^\}]*?\\".$sparqlVar."/", $query)===1 ){
                                $query=preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
                        else {
                                $query=preg_replace("/(WHERE.*?)(GRAPH[^\}]*[^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                        }
            	}
	    }
            //$query = preg_replace("/\*#\*/","}",$query);
            $ims = new OpsIms();
            $expandedQuery = $ims->expandQuery($query, $ops_uri, $this->_request->getParam('_lens'));
            if (strstr($expandedQuery, "?item")!==FALSE AND strcasecmp($limit,"all")!==0) {
                $filterGraph = "VALUES ?item { ";
                foreach($uriList as $uri) {
                    $filterGraph .= " <{$uri}> ";
                }
		$filterGraph .= "}";
                $expandedQuery = preg_replace("/(WHERE.*?\{)/s","$1 
			{$filterGraph}",$expandedQuery);
            }
            elseif ($this->_config->getEndpointType() == API.'ListEndpoint' AND strcasecmp($limit,"all")== 0 ) {
                $query = str_replace('?ops_item', '<'.$ops_uri.'>', $this->addPrefixesToQuery("CONSTRUCT { {$template}  } {$fromClause} WHERE { {$whereGraph} }"));
		if (!empty($filterGraph)) {
                    foreach ($filterGraph as $sparqlVar => $filterClause) {
                    	if (preg_match("/VALUES/", $filterClause)===1) {
                        	$query=preg_replace("/(WHERE.*?\{)/s", "$1 {$filterClause}", $query);
                    	}
                    	else if ( preg_match("/GRAPH[^\}]*?\{[^\}]*?\\".$sparqlVar."/", $query)===1 ){
                        	$query=preg_replace("/(WHERE.*?GRAPH[^\}]*?\{)([^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
                    	}
			else {
				$query=preg_replace("/(WHERE.*?)(GRAPH[^\}]*[^\}]*?\\".$sparqlVar.")/s", "$1 {$filterClause} $2", $query, 1);
			}
                    }
		}
                //$query = preg_replace("/\*#\*/","}",$query);
		$ims = new OpsIms();
                $expandedQuery = $ims->expandQuery($query, $ops_uri, $this->_request->getParam('_lens'));
            }
            $formatter = new VirtuosoFormatter();
            return $formatter->formatQuery($expandedQuery);
        } else if($viewerUri==API.'describeViewer' AND strlen($this->_request->getParam('_properties')) === 0 ){
            return 'DESCRIBE <'.implode('> <', $uriList).'>'.$fromClause;
        } else {
            $namespaces = $this->getConfigGraph()->getPrefixesFromLoadedTurtle();
            $conditionsGraph = '';
            $whereGraph = '';
            $chains = $this->getViewerPropertyChains($viewerUri);
            $props = array();
            foreach($chains as $chain) {
                $props =  $this->mapPropertyChainToStructure($chain, $props);
            }
            foreach ($uriList as $position => $uri) {
                if ($position) {
                    $whereGraph .= " UNION\n";
                }
                $conditionsGraph .= "\n    # constructing properties of {$uri} \n";
                $whereGraph .= "\n  # identifying properties of {$uri} \n";
                $counter = 0;
                foreach ($props as $prop => $substruct) {
                    if ($counter) {
                        $whereGraph .= "UNION {\n";
                    } else {
                        $whereGraph .= "  {\n";
                    }
                    $propvar = $substruct['var'] . '_' . $position;
                    $invProps = false;

                    if ($prop == API.'allProperties') {
                        $triple = "    <{$uri}> {$propvar}_prop {$propvar} .\n";
                    } else {
                        if($invProps = $this->getConfigGraph()->getInverseOfProperty($prop)){
                            $inverseTriple="#Inverse Mappings \n\n";
                            foreach($invProps as $no => $invProp){
                                $invPropQnameOrUri = $this->qnameOrUri($invProp, $namespaces);
                                $inverseTriple.= "{\n   {$propvar} {$invPropQnameOrUri} <{$uri}>  . \n ";
                                if (array_key_exists('props', $substruct)) {
                                    $inverseTriple .= $this->mapPropertyStructureToWhereGraph($substruct, $position, $namespaces);
                                }
                                $inverseTriple .= "}";
                                if($no!=(count($invProps)-1)){
                                    $inverseTriple.= " UNION ";
                                }
                            }
                        }
                         
                        $propQnameOrUri = $this->qnameOrUri($prop, $namespaces);
                        $triple = "    <{$uri}> {$propQnameOrUri} {$propvar} .\n";
                    }

                    $whereGraph .= ($invProps)? $inverseTriple :  $triple;
                    $conditionsGraph .= $triple;
                    if (array_key_exists('props', $substruct)) {
                        if(!$invProps) $whereGraph .= $this->mapPropertyStructureToWhereGraph($substruct, $position, $namespaces);
                        $conditionsGraph .= $this->mapPropertyStructureToConstructGraph($substruct, $position, $namespaces);
                    }
                    $whereGraph .= "  } ";
                    $counter += 1;
                }
            }

            return $this->addPrefixesToQuery("CONSTRUCT { {$conditionsGraph}} $fromClause WHERE { {$whereGraph}\n}\n");

        }

    }
    
    private function getFromClause(){
        $graphNames = '';
        foreach($this->getConfigGraph()->getSparqlEndpointGraphs() as $graphUri){
            $graphNames.="FROM <{$graphUri}>\n";
        }
        return $graphNames;
    }
    
    /**
     * Builds the query that inserts data into a graph
     *
     * @param string $rdfData
     * @param string $graphName
     * @return string
     */
    function getInsertQueryForExternalServiceData($rdfData, $graphName){
        $query = "INSERT IN GRAPH <{$graphName}>{".$rdfData."}";
    
        return $query;
    }
    
    /**
     * Build query that retrieves all the information from a certain graph
     *
     * @param string $graphName
     * @param string $viewerUri
     * @return string
     */
    function getViewQueryForExternalService($graphName, $pageUri, $viewerUri){
        $pageUri = preg_replace('/\|/','%7C', $pageUri);

        //get the template query from the config
        $template = $this->_config->getViewerTemplate($viewerUri);
        //fill in pageUri
        $template = str_replace("{pageUri}", '<'.$pageUri.'>', $template);

        //get the where template from the config
        $whereGraphTemplate = $this->_config->getViewerWhere($viewerUri);
        //fill in the graph name
        $whereGraph = str_replace("{result_hash}", $graphName, $whereGraphTemplate);
        
        $query = "CONSTRUCT { {$template} } WHERE { {$whereGraph} }";
        $finalQuery = $this->addPrefixesToQuery($query);
        return $finalQuery;
    }
    
    private function mapPropertyStructureToWhereGraph($structure, $uriPosition, $namespaces) {
      $var = $structure['var'];
      $props = $structure['props'];
      $graph = '';
      foreach($props as $prop => $substruct) {
        $propvar = $substruct['var'] . '_' . $uriPosition;
        if ($prop == API.'allProperties') {
          $graph .= "    OPTIONAL { {$var}_{$uriPosition} {$propvar}_prop {$propvar} .";          
        } else {
          $propQnameOrUri = $this->qnameOrUri($prop, $namespaces);

                  if($invProps = $this->getConfigGraph()->getInverseOfProperty($prop)){
                    $inverseTriple="#Inverse Mappings \n\n";
                    foreach($invProps as $no => $invProp){
                      $invPropQnameOrUri = $this->qnameOrUri($invProp, $namespaces);
                      $graph .= "\n OPTIONAL { {$propvar} {$invPropQnameOrUri} {$var}_{$uriPosition} .";
                      if (array_key_exists('props', $substruct)) {
                        $graph .= $this->mapPropertyStructureToWhereGraph($substruct, $uriPosition, $namespaces);
                      }
                      $graph .= "}"; 
                    }
                }
            $graph .= "    OPTIONAL { {$var}_{$uriPosition} {$propQnameOrUri} {$propvar} .";
        }
        if (array_key_exists('props', $substruct)) {
          $graph .= $this->mapPropertyStructureToWhereGraph($substruct, $uriPosition, $namespaces);
        }
        $graph .= " }\n";
      }
      return $graph;
    }
    
    private function mapPropertyStructureToConstructGraph($structure, $uriPosition, $namespaces) {
      $var = $structure['var'];
      $props = $structure['props'];
      $graph = '';
      foreach($props as $prop => $substruct) {
        $propvar = $substruct['var'] . '_' . $uriPosition;
        if ($prop == API.'allProperties') {
          $graph .= "    {$var}_{$uriPosition} {$propvar}_prop {$propvar} .\n";
        } else {
          $propQnameOrUri = $this->qnameOrUri($prop, $namespaces);
/*          if($invProp = $this->getConfigGraph()->getInverseOfProperty($prop)){
              $invPropQnameOrUri = $this->qnameOrUri($invPropQnameOrUri, $namespaces);
              $graph .= "\n  {$propvar} {$propQnameOrUri} {$var}_{$uriPosition} . \n # inverse property mapping \n";
          } 
 */
          $graph .= "    {$var}_{$uriPosition} {$propQnameOrUri} {$propvar} .\n";
        }
        if (array_key_exists('props', $substruct)) {
          $graph .= $this->mapPropertyStructureToConstructGraph($substruct, $uriPosition, $namespaces);
        }
      }
      return $graph;
    }
    
    /*
    Creating a structure that looks like:
    array(
      "var" => "?s",
      "props" => array(
                   rdfs:label => array("var" => "?var_1"),
                   org:reportsTo => array(
                                      "var" => "?var_2",
                                      "props" => array(
                                         rdfs:label => array("var" => "?var_2_1")
                                      )
                                    )
                 )
    )
    */
    private function mapPropertyChainToStructure($chain, $structure, $varbase = '?var') {
      $prop = array_shift($chain);
      if (array_key_exists($prop, $structure)) {
        $varbase = $structure[$prop]['var'];
      } else {
        $varbase = $varbase . '_' . (count($structure) + 1);
        $structure[$prop] = array('var' => $varbase, 'props' => array());
      }
      if (count($chain) != 0) {
        $structure[$prop]['props'] = $this->mapPropertyChainToStructure($chain, $structure[$prop]['props'], $varbase);
      }
      return $structure;
    }
    
    private function getViewerPropertyChains($viewerUri){
        return array_merge($this->getConfigGraph()->getRequestPropertyChainArray(), $this->getConfigGraph()->getViewerDisplayPropertiesValueAsPropertyChainArray($viewerUri), $this->getConfigGraph()->getAllViewerPropertyChains($viewerUri));
    }
    
    private function qnameOrUri($uri, $prefixes) {
        $hash = strpos($uri, '#');
        if (!$hash) {
            $parts = explode('/', $uri);
            $localPart = $parts[count($parts) - 1];
            $namespace = substr($uri, 0, strlen($uri) - strlen($localPart));
        } else {
            $localPart = substr($uri, $hash + 1);
            $namespace = substr($uri, 0, $hash + 1);
        }
        foreach ($prefixes as $prefix=>$ns) {
            if ($ns == $namespace) {
                return $prefix.':'.$localPart;
            }
        }
        return '<'.$uri.'>';
    }
    
    function getConfigGraph(){
        return $this->_config;
    }
    
}
?>
