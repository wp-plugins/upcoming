<?php
class XMLToArray  { 

	var $parser; 
	var $node_stack = array(); 

	function XMLToArray($xmlstring="") { 
		if ($xmlstring) return($this->parse($xmlstring)); 
		return(true); 
	} 

	function parse($xmlstring="") { 
		$this->parser = xml_parser_create(); 
		xml_set_object($this->parser, $this); 
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false); 
		xml_set_element_handler($this->parser, "startElement", "endElement"); 
		xml_set_character_data_handler($this->parser, "characterData"); 
		$this->node_stack = array(); 
		$this->startElement(null, "root", array()); 
		xml_parse($this->parser, $xmlstring); 
		xml_parser_free($this->parser); 
		$rnode = array_pop($this->node_stack); 
		return($rnode); 
	} 

	function startElement($parser, $name, $attrs) { 
		$node = array(); 
		$node["_NAME"] = $name; 
		foreach ($attrs as $key => $value) { 
			$node[$key] = $value; 
		} 
		$node["_DATA"] = ""; 
		$node["_ELEMENTS"] = array(); 
		array_push($this->node_stack, $node); 
	} 

	function endElement($parser, $name) { 
		$node = array_pop($this->node_stack); 
		$node["_DATA"] = trim($node["_DATA"]); 
		$lastnode = count($this->node_stack); 
		array_push($this->node_stack[$lastnode-1]["_ELEMENTS"], $node); 
	} 

	function characterData($parser, $data) { 
		$lastnode = count($this->node_stack); 
		$this->node_stack[$lastnode-1]["_DATA"] .= $data; 
	} 
}
?>