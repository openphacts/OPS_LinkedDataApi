<?php
class LinkedDataApiRequest {
    
    var $formatExtension = null;        
    var $pathWithoutExtension = null;
    var $unreservedParams = null;
    var $params = null;
    var $orderedUriWithoutApiKeys = null;
    var $orderedUriNoExtensionReservedParams = null;
    
    static $reservedParams = array(
        '_search', # a free text search query
        '_metadata', # is a comma separated list of names of metadata graphs to show: site,formats,views,all,execution
        '_view',
        '_properties',
        '_template',
        '_format',
        '_page', # is a number; the page that should be viewed
        '_pageSize', # is a number; the number of items per page
        '_sort', # is a comma-separated list of property paths to values that should be sorted on. A - prefix on a property path indicates a descending search
        '_where',# is a "GroupGraphPattern?":http://www.w3.org/TR/rdf-sparql-query/#GroupPatterns (without the wrapping {}s)
        '_orderBy',# is a space-separated list of OrderConditions
        '_select',#
        '_lang', # is a comma-separated list of languages
        '_callback', # for JSONP
        'callback', # for JSONP
	'app_id',
	'app_key',
        );
    
    function __construct(){
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
        {
        	$this->ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'];
        }
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
        {
        	$this->ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        }
        $this->uri = $this->getUri();
    }
    
    public static function eliminateDebugParams(){
        $params = array('XDEBUG_SESSION_START', 'KEY');
        foreach($params as $param){
            $regex= '/([&?])'.$param.'=[^&]+(&|$)/';
            $_SERVER['REQUEST_URI'] = rtrim(preg_replace($regex, '\1', $_SERVER['REQUEST_URI']), '?&');
            $_SERVER['QUERY_STRING'] = rtrim(preg_replace($regex, '\1', $_SERVER['QUERY_STRING']), '?&');
            unset($_REQUEST[$param]);
        }   
    }
    
    function getParams(){
        if ($this->params!=null){
            return $this->params;
        }
        if(!empty($_SERVER['QUERY_STRING'])){
            $this->params = queryStringToParams($_SERVER['QUERY_STRING']);            
        } else {
            $this->params = array();
        }
        return $this->params;
    }
    
    function hasNoCacheHeader(){
        if(
            (isset($_SERVER['HTTP_CACHE_CONTROL']) AND $_SERVER['HTTP_CACHE_CONTROL']=='no-cache')
            OR
            (isset($_SERVER['HTTP_PRAGMA']) AND $_SERVER['HTTP_PRAGMA']=='no-cache')
        ){
            return true;
        } else {
            return false;
        }
    }
    
    function hasUnrecognisedReservedParams(){
        $params = $this->getParams();
        foreach($params as $k => $v){
            if($k[0]=='_' AND !in_array($k, self::$reservedParams)){
                return $k;
            }
        }
        return false;
    }
    
    function getUnreservedParams(){
        if ($this->unreservedParams!=null){
            return $this->unreservedParams;
        }
        
        $params = $this->getParams();
        $this->unreservedParams = array();
        foreach($params as $k => $v){
            if($k[0]!=='_' AND $k!=='callback' AND $v!=='' AND $k!=='app_id' AND $k!=='app_key'){
                $this->unreservedParams[$k] = $v;
            }
        }
        
        uksort($this->unreservedParams, 'strcasecmp');
        return $this->unreservedParams;
    }
    
    function getOrderedUriWithoutApiKeys(){
        if ($this->orderedUriWithoutApiKeys!=null){
            return $this->orderedUriWithoutApiKeys;
        }
    
        $this->orderedUriWithoutApiKeys = $this->getPath();
        $params = $this->getParams();
        if (count($params) > 0){
            $this->orderedUriWithoutApiKeys .= '?';
            uksort($params, 'strcasecmp');//sort the parameters lexicographically, case-insensitive by parameter name

            foreach ($params as $paramName => $paramValue){
                if ($paramName!=='app_id' AND $paramName!=='app_key'){
                    if (substr($this->orderedUriWithoutApiKeys, -1) != '?'){
                        $this->orderedUriWithoutApiKeys .= '&';
                    }

                    $this->orderedUriWithoutApiKeys .= $paramName.'='.$paramValue;
                }
            }
        }
        
        return $this->orderedUriWithoutApiKeys;
    }
    
    function getOrderedUriWithoutExtensionAndReservedParams(){
        if ($this->orderedUriNoExtensionReservedParams!=null){
            return $this->orderedUriNoExtensionReservedParams;
        }
    
        $this->orderedUriNoExtensionReservedParams = $this->getPathWithoutExtension();
        $params = $this->getUnreservedParams();
        if (count($params) > 0){
            $this->orderedUriNoExtensionReservedParams .= '?';

            foreach ($params as $paramName => $paramValue){
                if (substr($this->orderedUriNoExtensionReservedParams, -1) != '?'){
                    $this->orderedUriNoExtensionReservedParams .= '&';
                }

                $this->orderedUriNoExtensionReservedParams .= $paramName.'='.$paramValue;
            }
        }
        
        return $this->orderedUriNoExtensionReservedParams;
    }
    
    function getParam($k){
        $params = $this->getParams();
        if(isset($params[$k])){
            return $params[$k];
        }
        else{
            return null;
        }
    }
    
    function getInstallSubDir(){
        return $this->_pathIntersect(dirname(__FILE__), $_SERVER['REQUEST_URI']);
    }
    
    function getBase(){
        return 'http://'.$_SERVER['SERVER_NAME'];
    }

    function getServerName(){
      return $_SERVER['SERVER_NAME'];
    }

    function getBaseAndSubDir(){
        return $this->getBase().$this->getInstallSubDir();
    }
            
    function getUri(){
        return $this->removeEmptyParams($this->getBase().$_SERVER['REQUEST_URI']);
    }
    
    function removeEmptyParams($uri){
        $replaced = rtrim(preg_replace('/([&?])[^=]+=(&|$)/i', '\1', $uri), '?&');
        if ($replaced == $uri) {
          return $uri;
        } else {
          return $this->removeEmptyParams($replaced);
        }
    }
    
    function getPath(){
        return str_replace( '?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);
    }
    
	function getMetadataParam(){
 		$m = array_filter(explode(',', trim($this->getParam('_metadata'))));
		return $m;
	}

    function getPage(){
        if($page = $this->getParam('_page')){
            return $page;
        } else {
            return 1;
        }
    }
    
    function getView(){
        return $this->getParam('_view');
    }
    
    function getPathWithoutExtension(){
        if(!$this->pathWithoutExtension){
            if($this->hasFormatExtension()){
                $this->pathWithoutExtension = str_replace('.'.$this->getFormatExtension(), '', $this->getPath());                
            } else {
                $this->pathWithoutExtension = $this->getPath();
            }

        }
        return $this->pathWithoutExtension;
    }
    
    function hasFormatExtension(){
        $path = $this->getPath();
        $hasExtension =  preg_match('@^(.+?)\.([a-z]+)$@', $path, $m);
        if($hasExtension){
            $this->pathWithoutExtension = $m[1];
             $this->formatExtension = $m[2];
        }
        return $hasExtension==true;
    }
    
    function getFormatExtension(){
        if($this->hasFormatExtension()){
            return $this->formatExtension;
        } else {
            return false;
        }
    }
    
    function getAcceptHeader(){
        if(isset($_SERVER['HTTP_ACCEPT'])) return trim($_SERVER['HTTP_ACCEPT']);
        else return null;
    }
    
    function getAcceptTypes($paramTypes = array()){        
        $header = $this->getAcceptHeader();
        $mimes = explode(',',$header);
        $accept_mimetypes = array();

        //build map between mimetypes and associated weights
        foreach($mimes as $mime){
            $mime = trim($mime);
            $parts = explode(';q=', $mime);
            if(count($parts)>1){
                $accept_mimetypes[$parts[0]]=strval($parts[1]);
            }
            else {
                $accept_mimetypes[$mime]=1;
            }   
        }
        if (empty($accept_mimetypes)){
            $accept_mimetypes['*/*'] = 1;
        }

        $defaultTypes = array_merge(array('application/json', 'application/xml', 'text/turtle', 
                                            'application/rdf+xml', 'application/x-rdf+json', 
                                            'text/tab-separated-values', 'text/html', 'application/xhtml+xml'),
                                            $paramTypes);
        if (!empty($paramTypes)){
            array_unique($defaultTypes);
        }
        
        //expand the mimetype '*/*' to remaining values
        if (isset($accept_mimetypes['*/*'])){
            //$tempDefaults contains all the mimetypes which do not explicitly appear in the header
            foreach ($defaultTypes as $defaultType){
                if (!isset($accept_mimetypes[$defaultType])){
                    $accept_mimetypes[$defaultType] = $accept_mimetypes['*/*'];
                }
            }
            
            unset($accept_mimetypes['*/*']);
        }
        
        //give weight according to the order in the $defaultTypes array
        foreach($defaultTypes as $defaultType){
            $count_values = array_count_values($accept_mimetypes);
            $defaultVal = $accept_mimetypes[$defaultType];
            if($count_values[$defaultVal] > 1){
                $accept_mimetypes[$defaultType]=strval(0.001*($count_values[$defaultVal]-1)+$accept_mimetypes[$defaultType]);
            }
        }
        
        arsort($accept_mimetypes);//sort descending according to weight
        return array_keys($accept_mimetypes);
    }
    
    function hasAcceptTypes(){
        $acceptheader = $this->getAcceptHeader();
        if(empty($acceptheader)){
           return false; 
        } else {
            return true;
        }
    }
    
    function getUriWithoutPageParam(){
        return $this->getUriWithoutParam('_page');
    }
    
    function getUriWithoutViewParam(){
        return $this->getUriWithoutParam('_view');
    }
    
    function getUriWithoutBase(){
        return str_replace( $this->getBase(), '', $_SERVER['REQUEST_URI']);
    }
    
    function getUriWithoutParam($params, $stripextension=false){
        if(is_string($params)){
            $params = array($params);
        }
        if($stripextension){
            $uri = $this->getBase().$this->getPathWithoutExtension();
            if ($_SERVER['QUERY_STRING']) {
              $uri .= '?'.$_SERVER['QUERY_STRING'];
            }
        } else {
            $uri = $this->getUri();
        }
        foreach($params as $param){
          $regex= '/([&?])'.$param.'=[^&]+(&|$)/';
          $uri = rtrim(preg_replace($regex, '\1', $uri), '?&');
        }
        return $uri;
    }
   
    function getUriWithParam($paramname, $paramvalue=false, $defaultparamvalue=false){
           if(!$paramvalue) $paramvalue = $this->getParam($paramname);
           if(!$paramvalue) $paramvalue = $defaultparamvalue;
           $uri = $this->getUriWithoutParam($paramname);
           if(!strpos($uri, '?')){
                   $uri.='?';
           } else {
               $uri.='&';
           }
           $uri.=$paramname.'='.urlencode($paramvalue);
           return $uri;
       }
   
    
    function getUriWithViewParam($viewername=false){
        return $this->getUriWithParam('_view', $viewername);
    }
    
    function getUriWithPageParam($pageno=false, $defaultparamvalue=1){
        return $this->getUriWithParam('_page', $pageno, $defaultparamvalue);
    }
    
    function getRequestUriWithoutFormatExtension(){
        if(preg_match('@^(.+?)\.([a-z]+)(\?.+)?$@', $_SERVER['REQUEST_URI'], $m)){
            $ret = $m[1].$m[3];
        }
        else{//the uri does not have a format extension
            $ret = $_SERVER['REQUEST_URI'];
        }
        
        return $ret;
    }
    
    
    function getPageUriWithFormatExtension($uri, $extension){
        if(preg_match('@^(.+?)\.([a-z]+)(\?.+)?$@', $uri, $m)){
          return preg_replace('@^(.+?)\.([a-z]+)(\?.+)?$@', '$1.'.$extension.'$3', $uri);
        } else {
            if(strpos($uri, '?')){
                return str_replace('?', '.'.$extension.'?', $uri);
             } else {
                return $uri.'.'.$extension;
             }            
        }
    }
    
    function _pathIntersect($a, $b){
        if (strlen($a)<strlen($b)) list($b,$a) = array($a, $b);
    	$patha = explode('/', $a);
    	$pathb = explode('/', $b);
    	$intersect =  implode('/', array_intersect($patha, $pathb));
    	if(!empty($intersect)) while(strpos($b, $intersect)===false) $intersect = substr($intersect, 1);
    	return $intersect;
    }
    
}

?>
