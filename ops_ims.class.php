<?php

class OpsIms {

    function expandQuery ( $query , $input_uri ) {
        $variables = array('?cw_uri' , '?ocrs_uri' , '?db_uri' , '?chembl_uri' , '?uniprot_uri' , '?pw_uri' , '?aers_uri');
        
        $url = IMS_ENDPOINT;
        $url .= urlencode($query) ;
        $params='';
        $output = $query ;
        foreach ($variables as &$var) {
            if (strstr($query , $var) != false) {
                $params.= ", {$var}";
            }
        }
        if ($params !='') {
	    $params=substr($params, 2);
            $url .= '&inputURI=' . urlencode($input_uri) ;
            $url .= '&parameter=' ;
            $url .= urlencode($params);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch,CURLOPT_HTTPHEADER,array (
            "Accept: application/xml"
                    ));
            $response = curl_exec($ch);
            curl_close($ch);

            //echo $query;
            //echo '<br><br>';
	    //echo $url;
            $output = simplexml_load_string($response)->expandedQuery ;
        }
        return $output ;
    }
 
}

?>
