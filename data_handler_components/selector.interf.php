<?php

interface Selector{
	
    /**
     * Returns a map between variable names and lists of items
     * All selectors will return a map at least with the key 'item'
     * Optionally keys for expansionVariables can also appear i.e. 'compound_chembl' 
     */
	public function getItemMap();
	
	public function getSelectQuery();
}

?>