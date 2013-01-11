<?php
switch($this->ConfigGraph->getEndpointType()){
    case API.'ListEndpoint' : 
        $pageUri = $this->Request->getUriWithPageParam();
        break;
    default:    
    case API.'ItemEndpoint' : case API.'ExternalHTTPService':
        $pageUri = $this->Request->getUri();
        break;
}
echo $DataGraph->to_simple_xml($pageUri);
?>