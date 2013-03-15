<?php


class ParameterPropertyMapper{
    
    private $_config;
    
    function __construct($config){
        $this->_config = $config;
    }
    
    function paramNameToPropertyNames($name){
        #remove min-/max-
        $nameArray = $this->splitPrefixAndName($name);
        $name = $nameArray['name'];
        #split on dot
        $splitNames = explode('.', $name);
        return $splitNames;
    }
    
    function mapParamNameToProperties($name){
        $splitNames = $this->paramNameToPropertyNames($name);
        $list = array();
        foreach($splitNames as $sn){
            $uri = $this->_config->getUriForVocabPropertyLabel($sn);
            $list[$sn] = $uri;
        }
        return $list;
    }
    
    function prefixFromParamName($name){
        $a = $this->splitPrefixAndName($name);
        return $a['prefix'];
    }
    
    private function splitPrefixAndName($name){
        $prefixes = array('min', 'max', 'minEx', 'maxEx', 'name', 'exists', 'lang', 'true', 'false');
        foreach($prefixes as $prefix){
            if(strpos($name, $prefix.'-')===0){
                $name =  substr($name, strlen($prefix.'-'));
                return array(
                        'name' => $name,
                        'prefix' => $prefix,
                );
            }
        }
        return array('name' => $name, 'prefix' => false);
    }
    
    
    
    
}

?>