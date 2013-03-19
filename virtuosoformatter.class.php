<?php

class VirtuosoFormatter {

	function formatQuery ( $query ) {
		$output = $query ;
		if (stristr($query , "CONSTRUCT") != false 
		  && stristr($query , "SELECT") != false 
		  && stristr(substr($query, 0 , stripos($query , "SELECT")) , "WHERE") == false) {
			$output = substr($output , 0 , strpos($query , "}")+1) . " WHERE { " . substr($output , strpos($query , "}")+1) . "}";
		}
		$output = preg_replace('/\([ ]*GROUP_CONCAT[ ]*\([ ]*DISTINCT/i' , '( sql:GROUP_DIGEST (' , $output);
		$output = preg_replace('/GROUP_CONCAT/i' , 'sql:GROUP_CONCAT' , $output);
		$output = preg_replace('/;[ ]*SEPARATOR[ ]*=[ ]*/i' , ', ' , $output);
		$output = preg_replace("/[ ]*sql:GROUP_DIGEST[ ]*\([ ]*[ a-z\?,_]*, [\"'] , [\"']/i" , '$0 , 1000 , 1' , $output);
		$output = preg_replace('#(\?[a-z,]*_uri[ ]*=[ ]*)(<http://.*>)#iU' , '$1 IRI($2)' , $output);
		$output = preg_replace('/\([ ]*COUNT[ ]*\([ ]*\?/' , '( COUNT (DISTINCT ?' , $output);
		return $output ;
	}
 
}

?>
