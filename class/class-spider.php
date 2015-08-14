<?php
/**
* Class to Spider and scape a site
* @package Site Importer
* @version 1.0.2
*/

function sortWords($a, $b) {
	return $b['words'] - $a['words'];
}

class spider{	
	/**
	* @var string Domain root
	*/
	var $root='';

	/**
	* @var string The root file system path
	*/
	var $unix_path='';

	/**
	* @var integer Limit of valid pages to spider
	*/
	var $limit=100000;
	
	/**
	* @var integer amount of valid pages spidered
	*/
	var $spidered=0;
	
	/**
	* @var boolean Set to true to display warning and status messages
	*/
	var $verbose=false;
	
	/**
	* @var array Pages Spidered
	*/
	var $pages= array();  
	
	/**
	* @var array Completed Pages Spidered where all the pages underneath them have been spidered 
	*/
	var $completed_pages= array();  
	
	/**
	* @var string The name of the file used for caching
	*/
	var $file_name = '';  

	/**
	* @var array Pages found for output
	*/
	var $output= array();
	
	/**
	* @var array Details about a html page stored in an array
	*/
	var $meta= array(); 
	
	/**
	* @var array Pages found for output flattened and sorted
	*/
	var $formatted_output= array(); 
	
	/**
	* @var integer The current depth in the spidering 
	*/
	var $depth=0;
	
	/**
	* @var boolean Crawl pages from this domain only
	*/
	var $this_domain=true;

	/**
	* @var boolean If we have set a filter object 
	*/
	var $isFilter= false;  

	/**
	* @var array html to ignore at the start of the page for a particular depth
	*/
	var $ignore_start = false;

	/**
	* @var array html to ignore at the end of the page for a particular depth
	*/
	var $ignore_end = false;

	/**
	* @var array The maximum depth within the page structure to crawl
	*/
	var $max_depth = 99;
	
	/**
	* @var string The current Indent for the processed HTML
	*/
	var $current_page = '';

	/**
	* @var array The parameters seting how the scrape will work
	*/
	var $scrape_params = array();
	
	/**
	* @var string The processed HTML
	*/
	var $proc_HTML = '';
	
	/**
	* @var array The images to copy
	*/
	var $images_copy = '';

	/**
	* @var array Details about the html structure including the words inside each div and section
	*/
	var $div_meta = array();
	
	/**
	* @var array Keys to delete from the div meta where there are duplicates
	*/
	var $delete_keys = array();
	
	/**
	* @var string The current Indent for the processed HTML
	*/
	var $proc_indent = '';
	
	/**
	* @var array Excluded pages from the crawling
	*/
	var $exclude_crawl_page = array();
	
	/**
	* @var array Excluded querys
	*/
	var $exclude_query = array();
	
	/**
	* @var array List of regex that will be included in the list
	*/
	var $include_pages = false;
	
	/**
	* @var string that has to be included in the HTML page for it to be saved 
	*/
	var $include_pages_HTML = false;
	
	/**
	* @var string that has to be not included in the HTML page for it to be saved 
	*/
	var $include_pages_HTML_not = false;
	
	/**
	* @var array List of regex that will be exluded in the list
	*/
	var $exclude_page = false;

	/**
	* @var array List of regex that will be included from the crawl
	*/
	var $include_crawl = false;
	
	/**
	* @var array List of regex that will be exclude from the crawl
	*/
	var $exclude_crawl = false;
	
	/**
	* @var array The warnings and error messages
	*/
	var $error = array();
	
	/**
	* @var array List of invalid characters to be stripped out of the HTML page
	*/
	var $invalid_chars;
	
	/**
	* @var array List of blank strings corresponding to the invalid characters (for str_replace)
	*/
	var $blank;
	
	/**
	* @var array List of blank strings corresponding to the invalid characters (for str_replace)
	*/
	var $ensure_slash=false;
	
	/**
	* @var string The version of php;
	*/
	var $php_version=0;
	
	/**
	* @var array curl_header the headers returned from the curl request
	*/
	var $curl_header= array();
	
	/**
	* Constructor function to set the default variables
	* @param string $root The main URL to spidered
	* @param string $start The starting page within the site
	* @param string $unix_path
	* @return array The An array of field names
	*/
	public function __construct( $root, $start, $unix_path=false, $verbose=false ){
		$this->php_version=PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
		$this->root=trim($root);
		if ( substr($this->root, -1)!='/' ){
			$this->ensure_slash=true;
		}
		if ( $verbose ){
			$this->verbose=true;
			print 'Verbose is set to true <br />';
		}
		if ( $unix_path) {
			$this->unix_path=$unix_path.'/';
		}
		// print 'Set unix_path to('.$this->unix_path.')';
		$this->file_name=str_replace(array('.','http://'),'',$root).'.txt';
		/*if ( file_exists($this->unix_path.'cp'.$this->file_name)) {
			$this->pages=unserialize(file_get_contents($this->unix_path.'cp'.$this->file_name));
		   	$this->output=unserialize(file_get_contents($this->unix_path.'output'.$this->file_name));
		   	$this->spidered=count($this->output);
		   	if ( $verbose ){print 'Found Cached File ('.$this->spidered.")<br />\n";}
		}else{
			if ( $verbose ){print $this->unix_path.'cp'.$this->file_name.' Not found<br />';}
			
			$this->output=array('1' => array('0' => $start)); 
			$this->pages=array($start);
		}*/

		for ($ascii = 0; $ascii <= 9; $ascii++ ){
			$this->invalid_chars[]=chr($ascii);
			$this->blank[]='';
		}
		for ($ascii = 11; $ascii < 32; $ascii++ ){
			$this->invalid_chars[]=chr($ascii);
			$this->blank[]='';
		}
		/*for ($ascii = 127; $ascii <= 255; $ascii++ ){
			$this->invalid_chars[]=chr($ascii);
			$this->blank[]='';
		}
		*/
	}
	
	/**
	* deletes any temporary files if this class is finished with
	*/
	public function __destruct() {
		if ( $this->spidered==0 ){
			if ( $this->verbose ){print 'Deleteing temp files';}
			/*if ( file_exists($this->unix_path.'cp'.$this->file_name) ){
				unlink($this->unix_path.'cp'.$this->file_name);
				unlink($this->unix_path.'output'.$this->file_name);
			}*/
		}
   	}
   	
	/**
	* Caches the results in a file to aid with spidering large sites
	*/
	function cacheResults( ){
		/*$written=file_put_contents($this->unix_path.'cp'.$this->file_name,serialize($this->completed_pages));
		file_put_contents($this->unix_path.'output'.$this->file_name,serialize($this->output));
		print '<br />caching results('.strlen(serialize($this->completed_pages)).' , '.$written.') to('.$this->unix_path.'cp'.$this->file_name.')'."<br />\n";
		*/
	}

	
	/**
	* Sets the limit to stop spidering valid pages
	* @param integer $limit
	*/
	function set_limit( $limit ){
		$this->limit=$limit;
	}

	/**
	* Sets the html at the correct depth to ignore eg. to ignore the head of a page for depth to set to array('2' => '</head>');
	* @param array $exclude
	*/
	function max_depth( $depth ){
		$this->max_depth=$depth;
	}
	
	/**
	* Sets the html at the correct depth to ignore eg. to ignore the head of a page for depth to set to array('2' => '</head>');
	* @param array $exclude
	*/
	function ignore_HTML_start_depth( $ignore ){
		$this->ignore_start=$ignore;
	}
	
	/**
	* Sets the pages array to exclude from crawl
	* @param array $exclude
	*/
	function ignoreHTMLEndDepth( $ignore ){
		$this->ignore_end=$ignore;
	}
	
	/**
	* Sets the pages array to exclude from crawl
	* @param array $exclude
	*/
	function exclude_crawl_pages( $exclude ){
		$this->exclude_crawl_page=$exclude;
	}
	
	/**
	* Sets the url queries array to exclude from crawl
	* @param array $exclude
	*/
	function exclude_URL_queries( $exclude ){
		$this->exclude_query=$exclude;
	}

	/**
	* Sets the regex array to exclude from crawl
	* @param array $exclude
	*/
	function exclude_crawl_pattern( $exclude ){
		$this->exclude_crawl=$exclude;
	}

	/**
	* Sets the regex array to include in crawl
	* @param array $exclude
	*/
	function include_crawlPattern( $include ){
		$this->include_crawl=$include;
	}

	/**
	* Sets the regex array of pages exclude
	* @param array $exclude
	*/
	function exclude_pagePattern( $exclude ){
		$this->exclude_page=$exclude;
	}
	
	/**
	* Sets the regex array of pages  include 
	* @param array $exclude
	*/
	function include_pagesPattern( $include ){
		$this->include_pages=$include;
	}

	/**
	* Sets the regex array of the html within the page to be saved
	* @param array $exclude
	*/
	function include_pages_HTML( $include ){
		$this->include_pages_HTML=$include;
	}
	
	/**
	* Sets the regex array of the html within the page to be saved
	* @param array $exclude
	*/
	function include_pages_HTML_not( $include ){
		$this->include_pages_HTML_not=$include;
	}
	
	/**
	* Sets the parameters to be used in the scrape
	* @param array $params
	*/
	function scrape_params( $params ){
		$this->scrape_params=$params;
	}
	
	/**
	* Get the links from a HTML page
	* @param string $str The HTML page
	* @return array An array of all the links from the page
	*/
	function get_page_links( $page ){
		// /<a\s[^>]*href=(\"??)(http[^\" >]*?)\\1[^>]*>(.*)<\/a>/siU
		// /<a\s[^>]*href\s*=\s*(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU
		// '| href=["\']'.$this->root.'\/(.*)["\'].*>.*\<\/a>|Uis'
		preg_match_all('/<a\s[^>]*href=([\"\']??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU',$page,$returns);
		return $returns[2] ;
	}
	
	/**
	* Scrape a HTML page
	* @param string $page_HTML The the HTML page
	* @param string $page The URL of the page
	* @return string The main container block of HTML 
	*/
	function get_main_html_block( $page_HTML , $main_block, $page){
		if ($page_HTML==''){
			print 'Unable to find the HTML for page ('.$page.')';
			return;
		}
		$page_HTML=preg_replace("<!--(?!<!)[^\[>].*?-->",'',$page_HTML);
				
		// Locate main html block
		$pos=strpos($page_HTML,$main_block);
		if (!$pos){
			$pos=strpos($page_HTML,str_replace('"',"'",$main_block));
			if ($main_block=='<body>'){
				$pos=strpos($page_HTML,'<body');
			}
			if (!$pos){ 
				$this->error[] = array('type' =>'error', 'errno'=>'2', 'error' => '<b>ERROR</b> unable to find main html block specified '.htmlspecialchars($main_block).' page length =('.strlen($page_HTML).')', 'page' => $page);
			}
		}
		$page_HTML=substr($page_HTML,$pos);
		$elements=explode(' ',str_replace(array('<','>'),'',$main_block));
		$element_type=$elements[0];

		$start_count=1;
		$end_count=0;
		$offset=0;
		while($start_count != $end_count){
			$offset=strpos($page_HTML,'</'.$element_type, $offset);
			$offset=$offset+strlen($element_type)+3;
			$start_count=substr_count($page_HTML, '<'.$element_type, 0, $offset);
			$end_count=substr_count($page_HTML, '</'.$element_type, 0, $offset);
		}		
		return substr($page_HTML,0,$offset);
	}
	
	/**
	* Scrape a HTML page
	* @param string $page The URL of the HTML page
	* @param string $page_HTML The the HTML page
	* @return boolean set to true if the page was able to be scraped
	*/
	function scrape_page( $page, $page_HTML ){
		$this->current_page=$page;
		if ($page_HTML==''){
			print 'Unable to find html for page ('.$page.')';
		}
		$head_HTML=substr($page_HTML,0, strpos($page_HTML,'</head>'));
		file_put_contents('.head.htm',$head_HTML);
		preg_match("/\<title\>(.*)\<\/title\>/",$head_HTML,$title);
		if ( !isset($title[1])){
			$title[1]='';
		}
		$page_HTML=$this->get_main_html_block($page_HTML, $this->scrape_params['mainHTMLBlock'], $page);
		
		$scrape='';
		if ( $this->scrape_params['startHTML'] != '' ){
			$pos=strpos($page_HTML,$this->scrape_params['startHTML']);
			if ( $pos !== false ){
				if ($this->scrape_params['includeStart'] != '1'){
					$pos+=strlen($this->scrape_params['endHTML']);
				}
				$scrape=substr($page_HTML,$pos);
				if($this->scrape_params['endHTML']!=''){
					$end=strpos($scrape,$this->scrape_params['endHTML']);
					if (!$end){return false;}
					if ($this->scrape_params['includeEnd'] == '1'){
						$end+=strlen($this->scrape_params['endHTML']);
					}
					$scrape=substr($scrape,0,$end);
				}
			}else{
				$this->error[] = array('type' =>'warning', 'Warning no Start HTML specified', 'page' => $page);
			}	
		}else{
			$scrape=$page_HTML;
			$this->error[] = array('type' =>'warning', 'Warning no Start HTML specified', 'page' => $page);
		}
		
		if ($scrape == ''){
			$this->error[] = array('type' =>'error', 'errno'=>'1', 'error' => 'Error no valid HTML has been scrapped or 404 error page', 'page' => $page);
			return false;
		}
				
		$this->proc_HTML='';
		$this->images_copy=array();
		$formatted_HTML_ERROR=$this->format_HTML($scrape,'HTML');
		if ( $formatted_HTML_ERROR!='' ){
			// $this->error[] = array('type' =>'warning', 'error' => '<b>Warning</b> invalid HTML on scraped page - '.$formatted_HTML_ERROR, 'page' => $page);
		}
		$actual_HTML=$this->proc_HTML;
		$this->proc_HTML='';
		$this->format_HTML($scrape,'DISPLAY');
		$this->meta[$page]=array_merge(array('Title' => $title[1], 'Scrape' => $scrape, 'Filter' => $actual_HTML, 'Formatted' => $this->proc_HTML, 'Images' => $this->images_copy),get_meta_tags('.head.htm'));
		return true;	
	}
	
	
	
	/* Function to the process the attributes for a particular html element
	* @param array The list of the attributes
	* @param string The element which is being proccessed
	* @param string The type of output required eg. HTML
	* @return The formatted attributes for the html
	*/
	function process_attributes($attribute_array, $element, $output){
		$atts='';
		$uploads = wp_upload_dir();
		foreach($attribute_array as $attribute ){				
			if ( $element == 'img' && $attribute->name == 'src' ){
				$path_parts = pathinfo($attribute->value);
				$new_file_name=$uploads['path'].'/'.$path_parts['basename'];
				$new_file_url=$uploads['url'].'/'.$path_parts['basename'];
				if ( (substr($attribute->value, 0, strlen($this->root)) == $this->root)||($attribute->value[0] == '/')||($attribute->value[0] == '.') ){
					// local value
					if ( $this->scrape_params['importLocal'] ){
						if($output!='HTML' ){
							$atts.=' '.$attribute->name.'="<span class="green">'.$new_file_url.'</span>"';
						}else{
							$atts.=' '.$attribute->name.'="'.$new_file_url.'"';
						}
						// This will deal with the paths of the page having '.' or '..' and will deal with images which have them at the start of their path
						if (strpos($attribute->value,'..') !== false) {
							$parts=parse_url($this->current_page);							
							$dirs=explode('/',str_replace('//','/',$parts['path']));
							array_pop($dirs);
							foreach($dirs as $dir){
								if ($dir!='.'){
									if ($dir=='..'){
										if (count($dirs2)>0){
											array_pop($dirs2);
										}
									}else{
										$dirs2[]=$dir;
									}
								}
							}
							$dirs=array_reverse($dirs2);
							$image_paths=array_reverse(str_replace('//','/',explode('/',$attribute->value)));
							$source='';
							foreach($image_paths as $image_path){
								if ($image_path=='..'){
									$source=array_pop($dirs).'/'.$source;
								}else{
									if ($source==''){
										$source=rawurlencode($image_path);
									}else{
										$source=rawurlencode($image_path).'/'.$source;
									}
								}
							}	
						}else{
							$source=str_replace(array('./','//','p:/'),array('/','/','p://'),$attribute->value);
						}
						
						$this->images_copy[$new_file_name]=array('source' => $source, 'destination' => $new_file_name, 'type' => 'local');
					}else{
						$atts.=' '.$attribute->name.'="'.$attribute->value.'"';
					}
				}else{
					// remote value
					if ( $this->scrape_params['importRemote'] ){
						if($output!='HTML' ){
							$atts.=' '.$attribute->name.'="<span class="green">'.$new_file_url.'</span>"';
						}else{
							$atts.=' '.$attribute->name.'="'.$new_file_url.'"';
						}
						$this->images_copy[$new_file_name]=array('source' => $attribute->value, 'destination' => $new_file_name, 'type' => 'remote');
					}else{
						$atts.=' '.$attribute->name.'="'.$attribute->value.'"';
					}	
				}
			}elseif ( ($this->scrape_params['replaceDomain'] == '1')&&( $attribute->name == 'href' || $attribute->name == 'src') ){
				// Replace Domain name with /
				if($output!='HTML' ){
					$atts.=' '.$attribute->name.'="'.str_replace($this->root,'<span class="red">'.$this->root.'</span>',$attribute->value).'"';
				}else{
					$atts.=' '.$attribute->name.'="'.str_replace($this->root,'',$attribute->value).'"';
				}
			}else{
				if ( in_array($attribute->name,$this->scrape_params['stripAttributes']) ){
					if($output!='HTML' ){
						$atts.='<span class="red"> '.$attribute->name.'="'.$attribute->value.'"</span>';
					}
				}else{
					$atts.=' '.$attribute->name.'="'.$attribute->value.'"';
				}
			}		
		}
		return $atts;
	}
	
	/* Recursive function to walk through the HTML
	* @param string $node The DOM node to process
	* @param string $output The output type of the function either HTML or Display
	*/
	function process_HTML( $node, $output = 'HTML' ){
		$children = $node->childNodes;
		// Skip redundant nodes
		if ( $node->nodeName == '#document' || $node->nodeName == 'html' || $node->nodeName == 'body' ){
			if ( count($children)>0 ){
				foreach($children as $child ){
					$this->process_HTML($child, $output);
				}
			}
			return;
		}
		if (in_array($node->nodeName,$this->scrape_params['removeElements'])){
			return;
		}
		
		if ( $output == 'HTML' ){
			$start_tag='<';
			$end_tag='>';
			$new_line_chars=PHP_EOL;
			$indent_chars="\t";
		}else{
			$start_tag='&lt;';
			$end_tag='&gt;';
			$new_line_chars='<br />'."\n";
			$indent_chars='&nbsp;&nbsp;&nbsp;&nbsp;';
		}
		$eol_length=0-strlen($new_line_chars);
		$indent_chars_length=0-strlen($indent_chars);
		if ( in_array($node->nodeName,array('div','ul','table','section','article','form')) ){
			$indent=true;
		}else{
			$indent=false;
		}
		if ( $indent && (substr($this->proc_HTML, $eol_length)!=$new_line_chars) ){
			$own_line=$new_line_chars;
		}else{
			$own_line='';
		}
		
		if ( in_array($node->nodeName,array('li','h1','h2','h3','h4','h5','p')) && (substr($this->proc_HTML, $eol_length) != $new_line_chars) ){
			$new_line=$new_line_chars;
		}else{
			$new_line='';
		}
		
		if ( in_array($node->nodeName,array('img','br','input'))){
			$self_closing_slash='/';
		}else{
			$self_closing_slash='';
		}
		if ( isset($children)){
			$atts=$this->process_attributes($node->attributes, $node->nodeName, $output);
			if ( in_array($node->nodeName,$this->scrape_params['stripElements']) ){
				$print_div=false;
			}else{
				$print_div=true;
			}
			$this->proc_HTML.=$own_line.$new_line;
			if ( substr($this->proc_HTML, $eol_length) == $new_line_chars ){$this->proc_HTML.=$this->proc_indent;}
			if ( $print_div ){
				$this->proc_HTML.=$start_tag.$node->nodeName.$atts.$self_closing_slash.$end_tag.$own_line;
			}elseif($output!='HTML' ){
				$this->proc_HTML.='<span class="red">'.$start_tag.$node->nodeName.$atts.$self_closing_slash.$end_tag.'</span>'.$own_line;
			}
			if ( $indent ){$this->proc_indent.=$indent_chars;}
			foreach($children as $child ) { 				
				if ( $child->nodeType == XML_TEXT_NODE && strlen(trim($child->nodeValue)) > 0) {
					$this->proc_HTML.=trim($child->textContent, ENT_QUOTES );
				}
				$this->process_HTML($child, $output);	
			}
			$this->proc_HTML.=$own_line;
			if ( $indent ){$this->proc_indent=substr($this->proc_indent, 0 , $indent_chars_length);}
			if ( substr($this->proc_HTML, $eol_length) == $new_line_chars ){$this->proc_HTML.=$this->proc_indent;}
			if ( $self_closing_slash == '' ){
				if ( $print_div ){
					$this->proc_HTML.=$start_tag.'/'.$node->nodeName.$end_tag.$own_line.$new_line;
				}elseif($output!='HTML' ){
					$this->proc_HTML.='<span class="red">'.$start_tag.'/'.$node->nodeName.$end_tag.'</span>'.$own_line.$new_line;
				}
			}
		}
	}
	
	/**
	* Format the HTML page 
	* @param string $HTML The HTML to format
	* @param string $display_type Return the results as HTML or HTML to display on the screen
	* @return array Any error messages when loading the HTML
	*/
	function format_HTML( $HTML,$display_type ){
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false; 
		$doc->substituteEntities = false;
		libxml_use_internal_errors(true);
		
		$HTML = preg_replace('/[\x1-\x8\xB-\xC\xE-\x1F]/', '', $HTML);	// Word characters
		if ( $this->php_version<5.4 ){$HTML=str_replace('&', '&amp;',$HTML);}	// Make the processor not see the html entities
		$doc->loadHTML(mb_convert_encoding($HTML, 'HTML-ENTITIES', 'UTF-8'));
		$error = libxml_get_last_error();
		libxml_use_internal_errors(false);
		libxml_clear_errors();
		$this->process_HTML($doc,$display_type);
		if ( $error ){
			return trim(htmlentities($error->message)). 'on line(' . $error->line . '),col(' . $error->column . ')';
		}else{
			return '';
		}		
	}
	
	/* Recursive function to walk through the HTML to identify the text elements of the page
	* @param string $node The DOM node to process
	* @return array An array of all the links from the page
	*/
	function count_text( $node ){
		$children = $node->childNodes;
		// Skip redundant nodes
		if ( $node->nodeName == '#document' || $node->nodeName == 'html' || $node->nodeName == 'body' ){
			if ( count($children)>0 ){
				foreach($children as $child ){
					$this->count_text($child);
				}
			}
			return;
		}
		
		if ( isset( $children )){
			$atts='';
			foreach($node->attributes as $attribute ){				
				$atts.=' '.$attribute->name.'="'.$attribute->value.'"';	
			}
			foreach($children as $child ) { 				
				$this->count_text($child);	
			}
			$words=str_word_count($node->nodeValue);
			if ($words > 2){
				$parent_atts='';
				foreach($node->parentNode->attributes as $attribute ){				
					$parent_atts.=' '.$attribute->name.'="'.$attribute->value.'"';	
				}
				$key = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $node->nodeName.$atts));
				if (array_key_exists($key, $this->div_meta)) {
					$this->delete_keys[]=$key;				
				}
				$this->div_meta[$key]=array(
					'name' => '<'.$node->nodeName.$atts.'>' , 
					'words' => $words , 
					'parent' => '<'.$node->parentNode->nodeName.$parent_atts.'>' , 
					'snippet' => substr(trim($node->nodeValue), 0 ,50) 
				);
			}
			//print '<'.$node->nodeName.$atts.'>'.$words."\n<br/>";
		}
	}	
	
	/**
	* Calculate the scrape details HTML page 
	* @paran string HTML The html to be processed
	* @return array An array of all the links from the page
	*/
	function calculate_scrape_details( $HTML ){
		$pos = strpos($HTML, '</head>');
		if ($pos === false) {$pos = strpos($HTML, '<body');}
		$HTML=substr($HTML,$pos+7);
		$HTML=strip_tags( $HTML , '<div><section><article>');
		if (trim($HTML) ==''){ return 'HTML page contains no content';}
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false; 
		$doc->substituteEntities = false;
		libxml_use_internal_errors(true);
		$doc->loadHTML(mb_convert_encoding($HTML, 'HTML-ENTITIES', 'UTF-8'));
		$error = libxml_get_last_error();
		libxml_use_internal_errors(false);
		libxml_clear_errors();
		$this->count_text($doc);
		foreach($this->delete_keys as $key){
			unset($this->div_meta[$key]);
		}
		
		usort($this->div_meta, 'sortWords');
		
		if ( $error ){
			return trim(htmlentities($error->message)). 'on line(' . $error->line . '),col(' . $error->column . ')';
		}else{
			return '';
		}			
	}

	/**
	* Recursive function to crawl a page and add all the links to an array
	* @param string $page The URL of the page
	* @param boolean $next If set to true will also index the next page in search results eg. <link rel="next" href="/egypt/pageid.2/" />
	* @return array An array of all the links from the page
	*/
	function crawl( $page, $next=false ){
		if ( $this->spidered < $this->limit ){
			$page_HTML =file_get_contents($page);
			// print '#'.$page.'size;'.strlen($page_HTML).';'.$this->depth.'#<br />'.$page_HTML.'xxxxx';
			
			$this->scrape_page($page,$page_HTML);
			$this->depth++;
			$next_crawl='';
			$page_parts=parse_url($page);
			if (!isset($page_parts['path'])){$page_parts['path']='/';}
			$path_parts_info = pathinfo($page_parts['path']);

			if ( $this->ignore_start or $this->ignore_end ){
				if ( array_key_exists($this->depth,$this->ignore_start) ){
					$start_pattern=$this->ignore_start[$this->depth];
				}else{
					$start_pattern='<html';
				}
				if ( array_key_exists($this->depth,$this->ignore_end) ){
					$end_pattern=$this->ignore_end[$this->depth];		
				}else{
					$end_pattern='</html>';			
				}
				if ( $start_pattern!='<html' or $end_pattern!='</html>' ){
					$page_HTML=$this->extract_string($page_HTML, $start_pattern, $end_pattern);
					if ( $this->verbose ){print 'Stripped page size is '.strlen($page_HTML).'<br />';}
				}
			}
			if ( $this->include_pages_HTML ){
				// include pages from being saved with if the html does not contain the text
				$write=false;
				if(strpos($page_HTML,$this->include_pages_HTML) === true ){
					$this->spidered++;
					$this->output[$this->depth][] = $page_parts['path'];
					if ( $this->verbose ){print 'Page Already found so not crawling<br />';}
					$page_HTML=false; // found page so stop crawling
				}
			}
			if ( $this->include_pages_HTML_not ){
				$write=false;
				if(strpos($page_HTML,$this->include_pages_HTML_not) === false ){
					$this->spidered++;					
					$this->output[$this->depth][] = $page_parts['path'];
					$page_HTML=false; // found page so stop crawling
					if ( $this->verbose ){print 'Page Already found so not crawling.<br />';}
				}
			}
			if ( $this->verbose ){print 'Crawl depth('.$this->spidered.')'.$page."\n<br />";}
			if($page_HTML ){
				$root_parts=parse_url($this->root);
				
				$links = $this->get_page_links($page_HTML);
				if ( $this->verbose ){print_r($links);}
				foreach($links as $link ){
					$valid_link=true;
					if ( $link == '' ||  substr($link,0,7)=='mailto:' ||  in_array($link,array('#','javascript:void(0)')) || $link[0] == '#' ){
						$valid_link=false;
					}else{
						$link_parts=parse_url($link);
						if ( !$link_parts ){print 'Error '.$link.' does not seem to be valid '."<br/>\n";}
						if ( !isset($link_parts['path'])){ 
							$formatted_link='/';
						}else{
							$formatted_link=trim($link_parts['path']);
						}
						
						while (substr($formatted_link, 0, 3) == '../'){
							$formatted_link=substr($formatted_link, 3);
							if (isset($path_parts_info['dirname'])){
								$dirs=explode('/', $page_parts['path']);
								if (count($dirs)>1){
									$actual_dir=$dirs[0].'/';
									$formatted_link=$actual_dir.$formatted_link;
									$page_parts['path']=substr($page_parts['path'],strlen($actual_dir));
								}
							}
						}
											
						$path_parts = pathinfo($formatted_link);
						
						if ( isset($path_parts['extension']) && in_array(strtolower($path_parts['extension']), array('jpg','jpeg','gif','png','pdf','doc','docx','xls','csv','xml','rss'))  ){
							$valid_link=false;
						}elseif ( ($this->this_domain==true) && (isset($link_parts['host'])) && $link_parts['host']!=$root_parts['host'] ){
							$valid_link=false;								
						}elseif(!isset($link_parts['path'])){
							// print 'Invalid link '.$link;
						}elseif(in_array($link_parts['path'],$this->exclude_crawl_page) ){
							$valid_link=false;
						}
						// print $link."<br /><br />";
						// print_r($link_parts);
					}					
					if ( $valid_link ){
						
						if ( $formatted_link[0]!='/' ){
							if ( (substr($formatted_link, 0, 4)!='http') && (substr($formatted_link, 0, 3)!='www') ){
								$formatted_dir = ($path_parts_info['dirname'] == '/' ? '/' : $path_parts_info['dirname'].'/');
								$formatted_link=$formatted_dir.$formatted_link;
							}
						}
						if ( (strlen($formatted_link)>1) && substr($formatted_link,-1) == '/' ){
							$formatted_link=substr($formatted_link,0,-1);
						}
						
						if ( isset($link_parts['query']) && $link_parts['query']!='' ){
							$querys=explode('&',str_replace('&amp;','&',$link_parts['query']));
							$count=0;
							foreach($querys as $query ){					
								$split=explode('=',$query);
								if ( !in_array($split[0], $this->exclude_query) ){
									// print '{'.$split[0].'}';
									if ( $count==0 ){
										$formatted_link.='?';
									}else{
										$formatted_link.='&';
									}
									$formatted_link.=$query;
									$count++;
								}
							}
						}
						if(!in_array($formatted_link,$this->pages) ){
							if ( $this->spidered < $this->limit ){
								$crawl=true;

								if ( $this->exclude_crawl ){
									// exclude pages from being crawled if their URL matches a pattern
									foreach($this->exclude_crawl as $regEx ){
										if(preg_match($regEx, $link_parts['path']) ){
											$crawl=false;
											if ( $this->verbose ){print 'Notice - Not including crawl page ('.$link_parts['path'].')due to ('.$regEx.')'."\n<br />";}
											break;
										}
									}
								}
								if ( ($crawl)&&($this->include_crawl) ){
									// include pages from being crawled only if their URL matches a pattern
									$crawl=false;
									foreach($this->include_crawl as $regEx ){
										if(preg_match($regEx, $link_parts['path']) ){
											$crawl=true;
											break;
										}									
									}
								}
								$write=true;								
								if ( $this->exclude_page ){
									// exclude pages from being saved with if their URL matches a pattern
									foreach($this->exclude_page as $regEx ){
										if(preg_match($regEx, $link_parts['path']) ){
											$write=false;
											if ( $this->verbose ){print 'Notice - Not included page('.$link_parts['path'].') due to ('.$regEx.')'."\n<br />";}
											break;
										}
									}
								}
								if ( ($write && $this->include_pages) ){
									// include pages from being saved with if their URL matches a pattern
									$write=false;
									foreach($this->include_pages as $regEx ){
										if(preg_match($regEx, $link_parts['path']) ){
											$write=true;
											break;
										}else{
											if ( $this->verbose ){print 'Notice- Not included page('.$link_parts['path'].') due to ('.$regEx.')'."\n<br />";}
										}
									}								
								}

								$this->pages[] = $formatted_link;
								if ( $write ){
									$this->spidered++;
									if ( $this->spidered % 1000 == 0) {
										$this->cacheResults();
									}
									if ( @is_array($this->output[$this->depth]) ){
										if ( !in_array($formatted_link, $this->output[$this->depth])) {
											$this->output[$this->depth][] = $formatted_link;
										}
									}else{
										$this->output[$this->depth][] = $formatted_link;
									}
									print '<script>document.getElementById(\'loadingp\').innerHTML = "Spidering<br/>Page '.$this->spidered.'";</script>';
									flush();
								}
								if ( $crawl && ($this->depth < $this->max_depth) ){
									if ( $this->ensure_slash ){
										if ( $formatted_link[0]!='/' ){
											$formatted_link='/'.$formatted_link;
										}
									}
									$this->crawl($this->root.$formatted_link);
								}
								$this->completed_pages[] = $formatted_link;

							}else{
								break;
							}
						}
					}						
				}
			}else{
				if ( $this->verbose ){print 'No HTML found <br />';}
			}
			$this->depth--;
			if ( $next_crawl!='' ){
				$this->crawl($next_crawl);
			}
		}
	}
	
	
	/**
	* Flatten the array and ensure extra info about the url is available
	* @param array $url_list The original array from the spider
	* @return array An array of all the links from the page
	*/
	function formatted_page_info( $url_list ){
		$formatted= array();
		array_walk_recursive($url_list, function($a) use (&$formatted) {$formatted[] = $a; });
		sort($formatted);
		$repeats=array();
		foreach($formatted as $URL ){
			$repeat='';
			$lastCount=0;
			for ($x = 3; $x <= strlen($URL); $x++) {
				$search=substr($URL, 0, $x);
				
				$count=0;
				$formatted2=$formatted;
				foreach($formatted2 as $URL2 ){
					$pos = strpos($URL2,$search);
					if ( $pos !== false && $pos==0 ){
					    $count++;
					}
				}	
				if ( $count>2 ){
					$repeat=$search;
					$lastCount=$count;
				}else{
					if ( $repeat!='' ){$repeats[$repeat]=$lastCount;}
					break;
				}
			}
			if ( !array_key_exists($this->root.$URL, $this->meta)) {
				if ( !$page_text=file_get_contents($this->root.$URL) ){
					$this->error[]=array('type' =>'error',  'errno'=>'3', 'error' => 'Warning - Unable to get the page content', 'page' => $this->root.$URL);
				}
				if ( !$this->scrape_page($this->root.$URL,$page_text) ){
					$this->error[]=array('type' =>'warning', 'error' =>  'Warning - Unable to find either the start or end HTML for page ', 'page' => $this->root.$URL);
				}
			}
			if ( array_key_exists($this->root.$URL, $this->meta)) {
				$this->formatted_output[$this->root.$URL]=array_merge(array('URL' => str_replace($this->root,'',$URL),'Category' => $repeat),$this->meta[$this->root.$URL]);
			}else{
				$this->error[]=array('type' =>'warning', 'error' => 'Warnning - Meta information does not exist', 'page' => $this->root.$URL);
			}
		}
		return $repeats;
	}
	
	/**
	* Scrape the HTML of a given web address using a curl request
	* Return the scraped code as a single string object.
	* @param $scrapeURL The URL 
	* @param $browser The browser user agent
	* @return $HTML The HTML of the URL
	*/ 
	function get_URL( $scrape_URL, $browser='Mozilla/5.0 (Windows NT 6.1; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0' ){
		$header = array();
		$header[0] = 'Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5';
		$header[] =  'Cache-Control: max-age=0';
		$header[] =  'Connection: keep-alive';
		$header[] = 'Keep-Alive: 300';
		$header[] = 'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7';
		$header[] = 'Accept-Language: en-us,en;q=0.5';
		$header[] = 'Pragma: ';
		$cookie_jar='./cookies.txt';
		
		$handle = curl_init();
		curl_setopt ($handle, CURLOPT_URL, $scrape_URL);
		curl_setopt ($handle, CURLOPT_USERAGENT, $browser);
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $header);
		// curl_setopt( $handle, CURLOPT_cookie_jar, $cookie_jar); 
		// curl_setopt( $handle, CURLOPT_COOKIEFILE, $cookie_jar);
		curl_setopt ($handle, CURLOPT_HEADER, 0);
		curl_setopt ($handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, true);
		$HTML = curl_exec ($handle);
		$this->curl_header=curl_getinfo($handle); 
		curl_close ($handle);
		unset($handle);

		$HTML=str_replace($this->invalid_chars,$this->blank,$HTML);
		return $HTML;
	}
	
	/**
	* Extract data within scraped html using the given tag pattern.
	* Return an array holding each scraped data object.
	* @param $HTML The html
	* @param $start_pattern The term found before the pattern begins
	* @param $end_pattern The term found after the pattern ends
	* @param $pattern The tag pattern on different lines eg. <tr\n<field>\n</td>\n<td
	*/ 
	function extract_string( $HTML, $start_pattern, $end_pattern, $pattern=false ){
		
		if ( $pos = strpos($HTML, $start_pattern) ){
			$HTML = substr($HTML, $pos+strlen($start_pattern));
		}elseif ( $this->verbose ){
			$this->error[]=array('error' => 'WARNING - Unable to find start HTML Text ('.$start_pattern.') length('.strlen($HTML).')', 'page' => $this->root.$URL);
		}
		if ( $pos = strpos($HTML, $end_pattern) ){
			$HTML = substr($HTML, 0, $pos);
		}elseif ( $this->verbose ){
			$this->error[]=array('error' => 'WARNING - Unable to find end HTML Text('.$end_pattern.')', 'page' => $this->root.$URL);
		}
		
		if ( !$pattern ){
			return($HTML);
		}else{
			$result = array();
			$model_array = explode ("\n", strtolower($pattern));
			if ( !$model_array[count($model_array) - 1] ){
				unset($model_array[count($model_array) - 1]);
			}

			$HTML_array = explode ("<", $HTML);
			$tag_amount = count($HTML_array);

			// Extract data within tags 
			for ($f = 0; $f < $tag_amount; $f++) {
				$tag = "<" . $HTML_array[$f];
				$close_position = strpos ($tag, ">");
				$value = substr($tag, $close_position + 1, strlen($tag) - $close_position);
				$tag = substr($tag,0,strlen($tag) - strlen($value));
				$HTML_array[$f] = strtolower($tag);
				$data[$f] = $value;
			}
			$pat = 0;
			$a_pat = array();
			for ($f=0; $f < $tag_amount; $f++) {
				if ( $model_array[$pat]=="<field>") {
					// Get data 
					$value = $data[$f-1];
					$value = str_replace (array("\t","\n","\r"), '', trim($value));
					if ( !$value ){$value = "{e}"; }

					array_push($a_pat,$data[$f-1]);
					$pat++;
					$f--;
				}else{
					if ( substr($model_array[$pat],0,1) == '<') {
						$result = strpos (' ' . $HTML_array[$f], $model_array[$pat],0);
					} else {
						$result = strpos (' ' . strtolower($data[$f]), $model_array[$pat],0);
					}

					if ( is_integer($result)) { $pat++; }
				}
				if ( $pat == count($model_array)-1) {
					$pat = 0;
					if ( count($a_pat) ){
						array_push($result, $a_pat);
					}
					$a_pat = array();
				}
			} 
			return $result;
		}
	}
}
?>