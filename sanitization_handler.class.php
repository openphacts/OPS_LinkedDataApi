<?php
require_once 'graphs/vocabularygraph.class.php';
require_once 'ops_ims.class.php';
require_once 'virtuosoformatter.class.php';


class SanitizationHandler{
    
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
    
    function hasValidURIParameters(){
        $unreservedParams = $this->_request->getUnreservedParams();
        foreach($unreservedParams as $k => $v){
            $propertyNamesWithUris = $this->_parameterPropertyMapper->mapParamNameToProperties($k);
            foreach($propertyNamesWithUris as $variableUri){
                $range = $this->_config->get_first_resource($variableUri, RDFS.'range');
                if($range && $range==RDFS.'Resource'){
                    $valid = $this->validateURI($v);
                    if (!$valid){
                        return FALSE;
                    }
                }
            }
        }
        
        return TRUE;
    }
    
    /*
     * Example: https://www.example.com/foo/?bar=baz&inga=42&quux
     * We get the following 'matches' array:
     * [0]=> "https://www.example.com/foo/?bar=baz&inga=42&quux"
      [1]=> "https:"
      [2]=> "https"
      [3]=> "//www.example.com"
      [4]=> "www.example.com"
      [5]=> "/foo/"
      [6]=> "?bar=baz&inga=42&quux"
      [7]=> "bar=baz&inga=42&quux"
     */
    function validateURI($uri){
        $supportedSchemes=array('http', 'https', 'ftp', 'doi', 'mailto', 'attachment', 'about', 'cvs', 'dns', 'file', 'geo', 'git', 'ldap', 'ldaps', 'res', 'sftp', 'smb', 'ssh', 'svn');
        $pattern='@^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?@';
        preg_match($pattern, $uri, $matches);
    
        $scheme = $matches[2];
        if (empty($scheme)){
            $uri = urldecode($uri);
            preg_match($pattern, $uri, $matches);
            $scheme = $matches[2];
        }
    
        if (empty($scheme) || !in_array($scheme, $supportedSchemes)){
            return FALSE;
        }
    
        if (!startsWith($matches[3], '//')){
            return FALSE;
        }
        
        if (count($matches)==8 && !startsWith($matches[6], '?')){
            return FALSE;
        }
    
        return TRUE;
    }
    
    function getUnknownPropertiesFromRequest(){
        if($this->hasUnknownPropertiesFromRequest()){
            return $this->_unknownPropertiesFromRequestParameter;
        } else {
            return false;
        }
    }
    
    function hasUnknownPropertiesFromRequest(){
    
        if(!empty($this->_unknownPropertiesFromRequestParameter)){
            return true;
        }
        
        $unreservedParams = $this->_request->getUnreservedParams();
        foreach($unreservedParams as $k => $v){
            $propertyNames = $this->_parameterPropertyMapper->paramNameToPropertyNames($k);
            $propertyNamesWithUris = $this->_parameterPropertyMapper->mapParamNameToProperties($k);
            foreach($propertyNames as $pn){
                if(empty($propertyNamesWithUris[$pn]) && $pn!=='XDEBUG_SESSION_START' && $pn!=='KEY' ){
                    $this->_unknownPropertiesFromRequestParameter[]=$pn;
                }
            }
        }
        
        $this->checkSortParams();
    
        try{
            $chain = $this->_config->getRequestPropertyChainArray();
        } catch (UnknownPropertyException $e){
            $this->_unknownPropertiesFromRequestParameter[]=$e->getMessage();
        }
    
        if(!empty($this->_unknownPropertiesFromRequestParameter)){
            return true;
        }
    
        return false;
    }
    
    function getUnknownPropertiesFromConfig(){
        if(!empty($this->_unknownPropertiesFromConfig)){  
            return $this->_unknownPropertiesFromConfig;
        } else {
            return $this->hasUnknownPropertiesFromConfig();
        }
    }
    
    function hasUnknownPropertiesFromConfig($viewerUri=false){
    
        if(!empty($this->_unknownPropertiesFromConfig)){
            return true;
        }
    
        $filters = $this->_config->getAllFilters();
        foreach($filters as $filter){
            $paramsArray = queryStringToParams($filter);
            foreach(array_keys($paramsArray) as $paramName){
                $propertyNames = $this->_parameterPropertyMapper->paramNameToPropertyNames($paramName);
                $propertyNamesWithUris = $this->_parameterPropertyMapper->mapParamNameToProperties($paramName);
                foreach($propertyNames as $pn){
                    if(empty($propertyNamesWithUris[$pn])){
                        $this->_unknownPropertiesFromConfig[]=$pn;
                    }
                }
            }
        }
        
        $this->checkSortParams();
    
        if($viewerUri){
            try{
                $chain = $this->_config->getViewerDisplayPropertiesValueAsPropertyChainArray($viewerUri);
            } catch (Exception $e){
                $this->_unknownPropertiesFromConfig[]=$e->getMessage();
            }
        }
        if(!empty($this->_unknownPropertiesFromConfig)){
            return true;
        }
    
        return false;
    }
    
    function checkSortParams(){
        if ($sort=$this->_request->getParam('_sort')){
            $this->checkSortParamsFromSource($sort, 'request');
        } else if($sort = $this->_config->getSort()){
            $this->checkSortParamsFromSource($sort, 'config');
        }
    }
    
    function checkSortParamsFromSource($sort, $source){
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
    
        foreach($propertyLists as $propertyList){
            $properties = $propertyList['property-list'];
            foreach($properties as $name => $uri){
                if(!$uri){
                    if($source == 'request') $this->_unknownPropertiesFromRequestParameter[]=$name;
                    else if($source == 'config') $this->_unknownPropertiesFromConfig[]=$name;
                    else throw new Exception("source parameter for sortToOrderBy must be 'request' or 'config'");
                }
            }
        }
    }
}

?>