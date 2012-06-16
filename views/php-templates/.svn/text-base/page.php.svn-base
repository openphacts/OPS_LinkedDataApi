<?php
require 'lib/moriarty/graphpath.class.php';
class PueliaPage {
 
    var $data;
    var $config;
    var $request;
    var $api;
    var $base;
    var $endpointType;
    var $uri=null;
 
    function __construct($pageUri, $data,$config,$request){
        $this->uri = $pageUri;
        $this->data = $data;
        $this->config = $config;
        $this->request = $request;
        $this->apiUri = $this->config->getApiUri();
        $this->base = $this->request->getBaseAndSubDir();
        $this->endpointType = $this->config->getEndpointType();
        $this->datasetUri = $this->config->get_first_resource($this->apiUri, API.'dataset');
        $this->datasetName = $this->config->get_label($this->datasetUri);
        $this->topic = new PueliaItem($data->get_first_resource($request->getUri(), FOAF.'primaryTopic'), $data, $this->config);
    }
    
    function getTitle(){
        return $this->config->get_label($this->apiUri);
    }
    
    function getDescription(){
        return $this->config->get_description($this->apiUri);
    }
    
    function getViewerLinksAndLabels(){
        $links = array();
        $viewerUris = $this->data->get_subject_property_values($this->uri, DCT.'hasVersion');
        foreach($viewerUris as $vUri){
            $name = $this->data->get_label($vUri['value']);
            $links[$vUri['value']] = $name;
        }
        return $links;
    }
    function getFormatLinksAndLabels(){
        $links = array();
        $formatUris = $this->data->get_subject_property_values($this->uri, DCT.'hasFormat');
        foreach($formatUris as $fUri){
            $name = $this->data->get_label($fUri['value']);
            $links[$fUri['value']] = $name;
        }
        return $links;
    }

    function getNext(){
      return $this->data->get_first_resource($this->uri, XHV.'next');
    }

    function getPrev(){

      return $this->data->get_first_resource($this->uri, XHV.'prev');
    }

    function getFirst(){
      return $this->data->get_first_resource($this->uri, XHV.'first');
    }

    function getEndpointLinks(){
        $endpointUris = $this->config->get_resource_triple_values($this->apiUri, API.'endpoint');
        $endpointLinks = array();
        foreach($endpointUris as $uri){
            if($this->config->has_resource_triple($uri, RDF_TYPE,  API.'ListEndpoint')){
                if($linkUri = $this->base.$this->config->get_first_literal($uri,API.'uriTemplate') AND !strpos($linkUri,'{')){
                    $endpointLinks[$linkUri] = $this->config->get_label($uri);
                }
            }
        }
        return $endpointLinks;
    }
    
    function getItems(){
        if($listUri = '_:itemsList'){
            //$this->data->get_first_resource($this->uri, API.'items')){
            $list = $this->data->get_list_values($listUri);
            $ObjectList = array();
            foreach($list as $uri) $ObjectList[]=new PueliaItem($uri, $this->data, $this->config);
            return $ObjectList;
        }
    }
    
}

class PueliaItem {
    
    var $uri, $data, $docUri; 
    var $img=false;
    var $label = false; 
    var $description=false; 
    var $isMappable= false;
    var $latitude = false;
    var $longitude=false;
    var $GraphPath = false;
    var $gotProperties = array();
    
    function __construct($uri, $data, $config){
        
        $this->uri = $uri;
        $this->docUri = $config->dataUriToEndpointItem($uri);
        if(!$this->docUri) $this->docUri = $this->uri;
        $this->config = $config;
        $this->data = $data;
        $this->description = $data->get_description($uri);
        $this->img = $this->getImg();
        $this->label = $this->data->get_label($uri);
        $this->latitude = $this->data->get_first_literal($uri, GEO.'lat');
        $this->longitude = $this->data->get_first_literal($uri, GEO.'long');
//	$this->GraphPath = new GraphPath();
    }

    function __get($name){
        foreach ($this->data->get_subject_properties($this->uri) as $prop) {
          if(substr($prop, (0-strlen($name)))==$name ) # is $name the localname of a property?
          {
            $this->gotProperties[]=$prop;
            $first = array_shift($this->data->get_subject_property_values($this->uri, $prop));
            return $first['value'];
          }
        }

      if(in_array($name, array_keys(get_object_vars($this)))) {
        return $this->$name;
      } else {
        return false;
      }
    }

    function __call($name, $arguments){
        
        if(in_array($name, get_class_methods($this))){
            call_user_method($name, $this, $arguments);
        } else if($uri = $this->$name){
            return new PueliaItem($this->$name, $this->data, $this->config);        
        } 
        else if(in_array($name, array_keys(get_object_vars($this)))) {
            return new PueliaItem($this->$name, $this->data, $this->config);
        } else {
            return new PueliaItem('_:false', $this->data, $this->config);
        }
    }

    function all($name){
      foreach ($this->data->get_subject_properties($this->uri) as $prop) {
          if(substr($prop, (0-strlen($name)))==$name ) # is $name the localname of a property?
          {
            $this->gotProperties[]=$prop;
            $objects = $this->data->get_subject_property_values($this->uri, $prop);
            $values= array();
            foreach($objects as $o) $values[]=$o['value'];
            return $values;
          }
        }

    }
  
    function classes($uriList){
      $types = array();
      foreach($uriList as $uri){
        $types = array_merge($this->data->get_resource_triple_values($uri, RDF_TYPE), $types);
      }
      return array_unique($types);
    }

    function filter($p, $o, $uriList=false){
      if(!$uriList){
        $uriList = $this->data->get_subjects();
      }
      $filteredUris = array();
      foreach($uriList as  $index => $s){
        if($vals = $this->data->get_subject_property_values($s, $p)){
          foreach($vals as $object){
            if($object['value']==$o) $filteredUris[]=$s;
          }
        }
      }
      return $filteredUris;
    }

    function has($properties){
      foreach($properties as $property){
        if($this->$property) return true;
      }
    }

    function getImg(){
        $imgProps = array(FOAF.'depiction', FOAF.'img', FOAF.'logo');
        foreach($imgProps as $p){
          if($img = $this->data->get_first_resource($this->uri, $p)){
                $this->gotProperties[]=$p;
                return $img;
            }
        }
    }
    function isMappable(){
       if(!empty($this->latitude) AND !empty($this->longitude)) return true;
	else return false; 
    }

    function otherPropertyValues(){
      $properties =  $this->data->get_subject_properties($this->uri);
      $otherProperties = array_diff($properties, $this->gotProperties);
      $otherPropertyValues = array();
      foreach($otherProperties as $prop){
        $otherPropertyValues[$prop] = $this->data->get_subject_property_values($this->uri, $prop);
      }
      return $otherPropertyValues;
    }

    function uri($name){
      foreach ($this->data->get_subject_properties($this->uri) as $prop) {
        if(substr($prop, (0-strlen($name)))==$name ) # is $name the localname of a property?
        {
          return $prop;
        }
      }
      return false;
    }

}

function isImg($uri){
    $uri = parse_url($uri, PHP_URL_PATH);
    if( $ext = array_pop(explode('.',$uri))) return in_array($ext,array('jpg','gif','png','jpeg','tiff','ico'));
    else return false;
}


?>
