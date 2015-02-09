<?php
/**
 * @package Easy Site Importer
 * @version 0.9
 */
/*
Copyright 2015 Alex W Fowler  (email : alex@werejuicy.com)
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as  published by the Free Software Foundation.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


// var $esi_db_version = '1.0';


class easy_site_importer{
	/**
	* @var string The name of the table where the import pages are stored
	*/
	private static $db_table =  'easysiteimporter';
	
	/**
	* @var array Information about the page structure divs, sections and articles
	*/
	var $meta_structure =  array();

	/**
	* @var string The domain name of the site to be spidered and scraped
	*/
	var $domain =  '';

	/**
	* @var string The page and query of the site to be spidered and scraped
	*/
	var $URL =  '';
	
	/**
	* @var string The HTML from the main page of the scrape
	*/
	var $HTML =  '';
	
	/**
	* @var integer The limit of how many pages to spider from the site
	*/
	var $limit =  100;

	
	function __construct() {
		$URL=get_option('scrapeURL');
		if ($URL != ''){
			$path_parts=parse_url($URL);
			if ($path_parts){
				$this->domain=$path_parts['scheme'].'://'.$path_parts['host'];
				$this->URL='';
				if (isset($path_parts['path'])){$this->URL.=$path_parts['path'];}
				if (isset($path_parts['query']) && $path_parts['query'] != ''){$this->URL.='?'.$path_parts['query'];}
				if ( ! function_exists( 'request_filesystem_credentials' ) ){require_once( ABSPATH . 'wp-admin/includes/file.php' );}
				/*$creds = request_filesystem_credentials($this->domain.$this->URL, '', true, false, null);
				global $wp_filesystem;
				WP_Filesystem();
				$wp_filesystem->get_contents($this->domain.$this->URL);*/
				$this->HTML=file_get_contents($this->domain.$this->URL);
				$spider=new spider($this->domain, $this->URL);
				$spider->calculate_scrape_details($this->HTML);
				$this->meta_structure=$spider->div_meta;
			}
		}
		add_action( 'admin_menu', array(&$this, 'site_importer_menu') );
		add_action( 'admin_init', array(&$this, 'site_importer_init') );
	}

	/**
	* Setup the table within the db for the details from a spider and scrape
	*/	
	public static function activate() {
		global $wpdb;
		$sql = 'CREATE TABLE ' .$wpdb->prefix . self::$db_table . ' (
			id mediumint(9) NOT NULL AUTO_INCREMENT, 
			url varchar(250) DEFAULT \'\' NOT NULL, 
			category varchar(100) DEFAULT \'\' NOT NULL, 
			title varchar(250) DEFAULT \'\' NOT NULL, 
			description varchar(250) DEFAULT \'\' NOT NULL, 
			keywords varchar(250) DEFAULT \'\' NOT NULL, 
			pre_text text NOT NULL, 
			post_text text NOT NULL, 
			display_text text NOT NULL,
			images mediumblob NOT NULL, 
			UNIQUE KEY id (id)
		) '.$wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	* Delete the scraping table if deactivated
	*/
        public static function deactivate() {
		global $wpdb;
	    	$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . self::$db_table);
        }	

	
	public function site_importer_menu() {
		add_options_page( 'Site Importer Options', 'Easy Site Importer', 'manage_options', 'site_importer', array(&$this, 'site_importer_options') );
	}
	

	/**
	* Setup the settings fields for the form and in the database
	*/
	public function site_importer_init() {
		
		add_settings_section('scrape_url', 'Site crawler settings', array(&$this, 'site_url_details'), 'site_scrape');  
		add_settings_field('scrapeURL', 'Website URL', array(&$this, 'text_field'), 'site_scrape', 'scrape_url', array( 'name' => 'scrapeURL', 'label_for' => 'Website URL', 'change' => 'document.getElementById(\'submit2\').disabled = false', 'extra' => '<input type="submit" value="Update HTML Blocks" class="button button-primary" id="submit2" name="submit" disabled="disabled">'));	
		add_settings_field('scrapeDepth', 'Max depth', array(&$this, 'text_field'), 'site_scrape', 'scrape_url', array( 'name' => 'scrapeDepth', 'label_for' => 'Max depth'));	
		
		add_settings_section('scrape_details', 'Scrape settings', array(&$this, 'scrape_details'), 'site_scrape');  
		add_settings_field('mainHTMLBlock', 'Main HTML block', array(&$this, 'select_box'), 'site_scrape', 'scrape_details', array( 'name' => 'mainHTMLBlock', 'label_for' => 'Main HTML block', 'options' => $this->meta_structure, 'options_name' => 'name', 'default' => '<body>'));	
		add_settings_field('startHTML', 'Start of HTML to scrape', array(&$this, 'text_field'), 'site_scrape', 'scrape_details', array( 'name' => 'startHTML', 'label_for' => 'Start of HTML to scrape'));	
		add_settings_field('endHTML', 'End of HTML to scrape', array(&$this, 'text_field'), 'site_scrape', 'scrape_details', array( 'name' => 'endHTML', 'label_for' => 'End of HTML to scrape'));	
		add_settings_field('includeStartEnd', 'Include the start HTML', array(&$this, 'checkbox_field'), 'site_scrape', 'scrape_details', array( 'name' => 'includeStart', 'label_for' => 'Include the start HTML'));
		add_settings_field('includeEnd', 'Include the end HTML', array(&$this, 'checkbox_field'), 'site_scrape', 'scrape_details', array( 'name' => 'includeEnd', 'label_for' => 'Include the end HTML'));
		
		add_settings_section('filter_details', 'HTML adjustments', array(&$this, 'filter_details'), 'site_importer');  
		add_settings_field('stripCSS', 'Strip inline CSS', array(&$this, 'checkbox_field'), 'site_importer', 'filter_details', array( 'name' => 'stripCSS', 'label_for' => 'Strip inline CSS'));
		add_settings_field('stripClass', 'Strip all classes', array(&$this, 'checkbox_field'), 'site_importer', 'filter_details', array( 'name' => 'stripClass', 'label_for' => 'Strip all classes'));		
		add_settings_field('stripDiv', 'Strip all div tags', array(&$this, 'checkbox_field'), 'site_importer', 'filter_details', array( 'name' => 'stripDiv', 'label_for' => 'Strip all div tags'));
		add_settings_field('stripSpan', 'Strip all span tags', array(&$this, 'checkbox_field'), 'site_importer', 'filter_details', array( 'name' => 'stripSpan', 'label_for' => 'Strip all span tags'));
		add_settings_field('replaceDomain', 'Remove scraped domain name from images and links', array(&$this, 'checkbox_field'), 'site_importer', 'filter_details', array( 'name' => 'replaceDomain', 'label_for' => 'Remove scraped domain name from images and links'));
		
		add_settings_section('image_settings', 'Image Settings', array(&$this, 'image_settings'), 'site_importer');  
		add_settings_field('importLocal', 'Import images on spidering domain', array(&$this, 'checkbox_field'), 'site_importer', 'image_settings', array( 'name' => 'importLocal', 'label_for' => 'Import images on spidering domain'));
		add_settings_field('importRemote', 'Import remote images', array(&$this, 'checkbox_field'), 'site_importer', 'image_settings', array( 'name' => 'importRemote', 'label_for' => 'Import remote images'));
		
		
		add_settings_section('seo_settings', 'Seo settings', array(&$this, 'seo_settings'), 'site_importer'); 
		if ( is_plugin_active('wordpress-seo/wp-seo.php') ) {
			add_settings_field('importTitle', 'Import the title tag', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importTitle', 'label_for' => 'Import the title tag', 'disabled' => false));
			add_settings_field('importDescription', 'Import the meta description', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importDescription', 'label_for' => 'Import the meta description', 'disabled' => false));
		}else{
			add_settings_field('importTitle', 'Import the title tag', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importTitle', 'label_for' => 'Import the title tag', 'disabled' => true));
			add_settings_field('importDescription', 'Import the meta description', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importDescription', 'label_for' => 'Import the meta description', 'disabled' => true));
		}
		register_setting('site_scrape', 'scrapeURL', array(&$this, 'check_site_url'));
		register_setting('site_scrape', 'scrapeDepth', array(&$this, 'check_scrape_depth'));
		register_setting('site_scrape', 'mainHTMLBlock');
		register_setting('site_scrape', 'startHTML', array(&$this, 'check_start_html_included'));
		register_setting('site_scrape', 'endHTML', array(&$this, 'check_end_html_included'));
		register_setting('site_scrape', 'includeStart');
		register_setting('site_scrape', 'includeEnd');
		
		register_setting('site_importer', 'stripCSS');
		register_setting('site_importer', 'stripClass');
		register_setting('site_importer', 'stripDiv');
		register_setting('site_importer', 'stripSpan');
		register_setting('site_importer', 'replaceDomain');
		
		register_setting('site_importer', 'importLocal');
		register_setting('site_importer', 'importRemote');
		
		register_setting('site_importer', 'importTitle');
		register_setting('site_importer', 'importDescription');
	}

	/**
	* Display the main admin settings panel
	*/			
	public function site_importer_options() {		
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.','esi' ) );
		}
		$tabs = array( 'home' => 'About', 'scrape' => 'Crawl & Scrape Settings', 'settings' => 'Import Settings', 'test' => 'Test Settings', 'spider' => 'Spider &amp; Scrape Site', 'review' => 'Import Content');
		if (isset($_GET[ 'tab' ])){
			$current=$_GET[ 'tab' ];
		}else{
			$current='general';
		}
		print '<h1>Easy Site Importer</h1>';
		print '<p>Site importer will spider, scrape and copy another site into wordpress</p>';
		print '<h2 class="nav-tab-wrapper">';
		foreach( $tabs as $tab => $name ){
			if  ( $tab == $current ){
				$class=' nav-tab-active';
			}else{
				$class='';
			}
			print '<a class="nav-tab'.$class.'" href="?page=site_importer&tab='.$tab.'">'.$name.'</a>';
		}
		print '</h2>';		
		switch ($current) :
			case 'scrape' :
				$this->site_scrape_settings();
				break;
			case 'settings' :
				$this->site_importer_settings();
				break;			
			case 'test' :
				$this->spider_site('test');			
				break;
			case 'spider' :
				$this->spider_site('all');			
				break;
			case 'review' :
				$this->display_results();
				break;
			case 'import' :
				$this->import_pages();
				$this->display_results();
				break;
			default:
				$this->main_page();
		endswitch;
	}
	
	/**
	* Displays the main page on the site 
	*/	
	public function main_page() {
		print '<div id="esi_nain">
				<h2>Easy Site Importer</h2>
				<p>Easy site importer makes the process of migrating from any web site a much simplier, easy and less time consuming process.
				Simply enter the URL of a site and Easy Site Importer will automatically scan the target website to find sections of the site which have content to be scraped.
				Specify which main HTML content block contains the main content for the site and if required an additonal start and end string within this main content block.</p>
				<h3>Main Features</h3>
				<ul>
					<li>Automatically scan the site to identify the main content blocks for scraping</li>
					<li>Filter the html to remove inline CSS, Class, Span or Div tags</li>
					<li>Identify and replace hard coded absolute URLs</li>
					<li>Import any images found into the media uploads directory and change the image tags</li>
					<li>Import SEO title and description tags into wordpress SEO YOAST plugin</li>
				</ul>
				<div class="white">
					<h3>Getting Started</h3>
					<img src="'. plugins_url('/img/instructions.png',dirname(__FILE__)).'" width="500" height="928" />
				</div>
				<h3>Limitations</h3>
				<b>It will only spider 100 page from the target site</b><br />
				To deal with this issue on much larger sites try one of the following :- 
				<ul>
					<li>Spider the site in chunks so that you do one section at a time</li>
					<li>Increase the limit by updating it within the /class/class-site-importer-admin.php file but this is likely to encounter PHP limits</li>
					<li>Upgrade to the Pro version</li>
				</ul>
				<b>There is no bulk import feature</b><br />
				Easy site importer was not designed to spider large sites so this feature was not implemented and it was considered something which if not used properly could accidentally import incorrect pages into someones site.
				
				
			</div>';
	}
	/**
	* Displays the main settings form on the settings page
	*/	
	public function site_scrape_settings() {
		print '<div id="esi_options1">
			<h2><span class="fa fa-cogs"></span> Site crawling and scraper settings</h2>

			<p>The crawler will spider the site to copy and get all the web pages available up to the limit of '.$this->limit.' pages. The scraper will then extract the content from the pages which have been crawled</p>
			<form method="post" action="options.php">';
				do_settings_sections( 'site_scrape' );
				settings_fields( 'site_scrape' ); // hidden values
				submit_button(); 
		print '</form></div><div id="fade" class="black_overlay"></div>';
	}
	
	
	/**
	* Displays the main settings form on the settings page
	*/	
	public function site_importer_settings() {
		print '<div id="esi_options2">
			<h2><span class="fa fa-cogs"></span> Site Importer Settings</h2>
			<p>Here are all the settings for how the HTML is filtered and then imported into wordpress</p>
			
			<form method="post" action="options.php">';
				do_settings_sections( 'site_importer' );
				settings_fields( 'site_importer' ); // hidden values
				submit_button(); 
		print '</form></div>';
	}

	/**
	* Lets spider the site
	* @param string $spider_type Set to either spider the site or just one page for testing
	*/
	public function spider_site( $spider_type ){
		print '<div id="esi_spider">';

		global $wpdb;
		$error='';
		if ($this->domain == ''){
			$error='<p>The main domain name is not set so can not spider this site. Enter a Website URL within the \'Crawling and Scraping settings\' tab</p>';
		}
		if (get_option('mainHTMLBlock') == ''){
			$error.='<p>The main HTML Block is not set so can locate the part of the pages that you which to scrape in. Select a main HTML block from the drop down list within the \'Crawling and Scraping settings\' tab</p>';
		}
		$depth=get_option('scrapeDepth');
		if ($depth  == '' || $depth == '0'){
			$error.='<p>The max depth setting is not set correctly. Set this to a number such as 5 within the \'Crawling and Scraping settings\' tab</p>';	
		}
		if ($error != ''){
			print '<h2><span class="fa fa-ambulance"></span> Unable to spider the site as this is not configured correctly</h2><div class="error">'.$error.'</div>';
			return;
		}
		
		if ($spider_type == 'test'){
			print '<h2><span class="fa fa-question-circle"></span> Test spider for '.$this->domain.$this->URL.'</h2>';
			$depth=1;
			$limit=1;
		}else{
			$depth=get_option('scrapeDepth');
			print '<h2><span class="fa fa-cloud-download"></span> Spider Site '.$this->domain.$this->URL.' with a max depth '.$depth.'</h2>
				<p>This page will spider and scrape the site and add all the results to a database</p>';
			$limit=$this->limit;
		}
		
		// $exclude_pages=array('', '/', $URL, '/latedeals/', '/beach-and-seaside-holidays/', '/villas-with-pools/', '/holiday-cottages/', '/short-breaks/');
		// $exclude_crawl_pattern=array('/^http/i', '/^www/i', '/^\/content/i', '/^\/category/i', '/^\/blog/i', '/^\/travel_guide/i', '/^\/resources/i', '/^\/null/i', '/^void/i', '/^\/short-breaks/i', '/[a-zA-Z0-9_]\z/i', '/yn.1\/\z/i');
		// $spider->exclude_crawl_pages($exclude_pages);
		// $spider->exclude_crawl_pattern($exclude_crawl_pattern);
		// $spider->ignore_HTML_start_depth(array(1 => 'Holiday Rentals by Town'));
		// $spider->ignore_HTML_end_depth(array(1 => 'class=\'footer\''));

		$remove_element=array('form');
		$strip_element=array('');
		if (get_option('stripDiv') == '1'){$strip_element[]='div';}
		if (get_option('stripSpan') == '1'){$strip_element[]='span';}
		$params=array(
				'mainHTMLBlock' => get_option('mainHTMLBlock'),
				'startHTML' => get_option('startHTML'), 
				'endHTML' => get_option('endHTML'),
				'includeStart' => get_option('includeStart'),
				'includeEnd' => get_option('includeEnd'),
				'stripCSS' => get_option('stripCSS'), 
				'stripClass' => get_option('stripClass'),
				'removeElements' => $remove_element,
				'stripElements' => $strip_element, 
				'replaceDomain' => get_option('replaceDomain'),
				'importLocal' => get_option('importLocal'), 
				'importRemote' => get_option('importRemote'), 
		);
		$spider=new spider($this->domain, $this->URL, get_home_path().'/wp-content/plugins/site-importer/log', FALSE);
		$spider->max_depth($depth);	
		$spider->set_limit($limit);	
		$spider->scrape_params($params);
		$spider->crawl($this->domain.$this->URL);

		$amount=count($spider->output, COUNT_RECURSIVE) - count($spider->output);
		// print_r($spider->output);
		// die();
		$urlData=$spider->url_info($spider->output);
		if ($spider_type == 'test'){
			if (count($spider->formatted_output)>0){
				array_rand($spider->formatted_output, 1);
				foreach($spider->formatted_output as $page){
					print '<h3>Displaying details for page '.$page['URL'].'</h3>';
					print '<table>';
					foreach($page as $key => $meta){
						if ($key == 'Images'){
							print '<tr><th>'.$key.'<th><td>'.count($meta).' images</td></tr>';
						}elseif ($key != 'URL' && $key != 'Category' && $key != 'Scrape' && $key != 'Filter' && $key != 'Formatted'){
							print '<tr><th>'.$key.'<th><td>'.$meta.'</td></tr>';
						}
					}
					print '</table>';
				}
				print '<h3>Scraped HTML sample</h2>
					<p><span class="red">red</span> items will be filtered out when imported into the site</p>
					<p><span class="green">Green</span> item have been amended from the original</p>
					<div id="formatted_html">'.$page['Formatted'].'</div>';
			}else{
				print '<div class="error"><p>Unable to display ('.count($spider->formatted_output).')</p></div>';
			}
			
		}else{
			print '<h3>Spidering results</h3>';
			print 'Found <b>'.$amount.'</b> URLS from('.$this->domain.$this->URL.')';
			if (count($spider->formatted_output) != $amount) {
				print ' but only <b>'.count($spider->formatted_output).'</b> had the type of structure to be imported';
				print '('.htmlentities(get_option('startHTML')).') '."\n<br />";
			}
			$wpdb->query('TRUNCATE '.$wpdb->prefix . self::$db_table);
			$correct_pages='';
			foreach($spider->formatted_output  as $page_URL => $URL){
				$keywords='';
				$description='';
				$title='';
				$images='';
				if (isset($URL['keywords'])){$keywords=$URL['keywords'];}
				if (isset($URL['description'])){$description=$URL['description'];}
				if (isset($URL['Title'])){$title=$URL['Title'];}
				if (isset($URL['Images'])){$images=serialize($URL['Images']);}
				$SQL='INSERT INTO `'.$wpdb->prefix . self::$db_table.'` 
					(`url`, `category`, `title`, `description`, `keywords`, `pre_text`, `post_text`, `display_text`, `images`) 
					VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s )';
				// print $SQL."\n<br />";
				$wpdb->query( $wpdb->prepare($SQL, array($URL['URL'], $URL['Category'], $title, $description, $keywords, $URL['Scrape'], $URL['Filter'], $URL['Formatted'],$images)));
				if ($URL['URL'] == ''){$URL['URL']=$this->domain.$URL['URL'];}
				$correct_pages.='Found <a href="'.$this->domain.$URL['URL'].'" target="_blank">'.$URL['URL'].'</a><br />';
			}
			
			print '<h3>Spidering details</h3><p></p>';
			$no_valid_html='';
			$no_html_block='';
			foreach($spider->error as $message){
				if ($message['type'] == 'error'){
					if ($message['page'] == ''){$message['page']=$this->domain.$message['page'];}
					if ($message['errno'] == '1'){
						$no_valid_html.='Not Valid Page - <a href="'.$this->domain.$message['page'].'" target="_blank">'.$message['page'].'</a><br />';
					}elseif ($message['errno'] == '2'){
						$no_html_block.='No HTML block <a href="'.$this->domain.$message['page'].'" target="_blank">'.$message['page'].'</a><br />';
					}else{
						// print 'ERROR ('.$message['errno'].') - '.$message['error'].' <a href="'.$this->domain.$message['page'].'" target="_blank">'.$message['page'].'</a><br />';
					}
				}
				if ($message['type'] == 'warning'){
					// print 'Warning '.$message['error']."(".$message['page'].")<br/>";
				}
			}
			if ($no_valid_html != '') {print '<p><b>The following pages are not valid</b></p>'.$no_valid_html;}
			if ($no_html_block != '') {print '<p><b>The following pages have no main HTML block</b></p>'.$no_html_block;}
			if ($correct_pages != '') {print '<p><b>The following pages are correct</b></p>'.$correct_pages;}

			print '<br />Finished spidering site and you can <a href="?page=site_importer&tab=review">review and import the results here</a>';
		}
		print '</div>';
	}

	/**
	* Format the URL so that it does not have a slash (/) at the front or back
	* @param string URL The url to be formatted
	*/	
	public function get_post_name( $url ){
		$url=str_replace('/','',$url);
		$path_parts = pathinfo($url);
		if (isset($path_parts['extension'])){
			$url=str_replace('.'.$path_parts['extension'],'',$url);
		}
		if ($url == ''){$url='home';}
		return($url);
	}

	/**
	* Displays the results of the scrape on the review tab of the admin page
	*/	
	public function display_results() {
		print '<div id="esi_import">
		<h2><span class="fa fa-database"></span> Import into wordpress database</h2>
		<p>Import the scraped results into wordpress by clicking on the import button for the pages you want. If the page appears to already exist within wordpress you will need to delete it before it can be imported</p>';
		global $wpdb;
		$pages = get_pages();
		foreach ( $pages as $page){
			$version=substr($page->post_name, -2);
			if (($page->post_title == substr($page->post_name, 0, -2)) && ($version == '-2' || $version == '-3')){ 
				$page_names[]=substr($page->post_name, 0, -2);
			}else{
				$page_names[]=$page->post_name;
			}
		}	
		$scrapes = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix . self::$db_table);
		$divs='';
		$table='';
		$count=1;
		foreach ( $scrapes as $scrape){
			$post_name=$this->get_post_name($scrape->url);
			$import=true;
			
			if (strlen($scrape->title) == 0 ){
				$title_text='None';
				$title_class='class="orange"';
			}else{
				$title_text=strlen($scrape->title).' chars';
				$title_class='class="green"';
			}
			
			if (strlen($scrape->description) == 0 ){
				$description_text='None';
				$description_class='class="orange"';
			}else{
				$description_text=strlen($scrape->description).' chars';
				$description_class='class="green"';
			}
			
			$image_array=unserialize($scrape->images);
			if (count($image_array) == 0 ){
				$image_text='None';
				$image_class='class="orange"';
			}else{
				$image_text=count($image_array).' images';
				$image_class='class="green"';
			}
		
			if (strlen($scrape->post_text) < 4){
				$post_text_class='class="red"';
				$import=false;
			}else{
				$post_text_class='class="green"';
			}
			
			if (in_array($post_name, $page_names)){
				$post_name_class='class="red"';
				$post_name_text='This page already exist so cannot be imported';
				$import=false;				
			}else{
				$post_name_class='class="green"';
				$post_name_text='';
			}
			
			$filtered_characters=strlen($scrape->post_text) - strlen($scrape->pre_text);
			if ($filtered_characters==0){
				$filtered_text=number_format(strlen($scrape->post_text)).' chars (Not filtered)';
			}else{
				$filtered_text=number_format(strlen($scrape->post_text)).' chars (filtered)';
			}
			$table.='<tr>
					<td><a href="'.$this->domain.'/'.$scrape->url.'" target="_blank">'.$scrape->url.'</a></td>
					<td '.$title_class.' title="'.$scrape->title.'">'.$title_text.'</td>
					<td '.$description_class.' title="'.$scrape->description.'">'.$description_text.'</td>
					<td '.$image_class.' >'.$image_text.'</td>
					<td '.$post_text_class.'>'.$filtered_text.'<a href = "javascript:void(0)" onclick = "document.getElementById(\'light'.$count.'\').style.display=\'block\';document.getElementById(\'closeimg'.$count.'\').style.display=\'block\';document.getElementById(\'fade\').style.display=\'block\'">
						<button type="button" class="right">Preview</button><a></td>
					<td '.$post_name_class.' title="'.$post_name_text.'" >'.$post_name.'</td>';
			if ($import){
					$table.='<td><a href="?page=site_importer&tab=import&scrape_id='.$scrape->id.'"><button type="button">Import Page</button></a></td>';
			}else{
					$table.='<td></td>';
			}
			$table.='</tr>';
			$divs.='<a href = "javascript:void(0)" onclick = "document.getElementById(\'light'.$count.'\').style.display=\'none\';document.getElementById(\'closeimg'.$count.'\').style.display=\'none\';document.getElementById(\'fade\').style.display=\'none\'">
				<img src="'. plugins_url('/img/fancy_close.png',dirname(__FILE__)).'" width="30" height="30" class="close" id="closeimg'.$count.'" alt="Close this window" /></a>
				<div id="light'.$count.'" class="white_content">'.$scrape->display_text.'</div>';
			$count++;
		}
		print '<h3>Scrape Results</h3>';
		print '<table>';
		print '<tr><th>URL</th><th>Title</th><th>Description</th><th>Images</th><th>Text to import</th><th>Post Name</th></tr>';
		print $table.'</table>'.$divs.'<div id="fade" class="black_overlay"></div>';
		
		print '</div>';		
	}

	/**
	* Imports the pages into the wordpress post database
	*/	
	public function import_pages() {
	
		print '<div id="esi_import">
			<h2><span class="fa fa-download"></span> Importing </h2>';
	
		global $wpdb;
		$path_parts=parse_url(get_option('scrapeURL'));
		$import_title=get_option('importTitle');
		$import_description=get_option('importDescription');
		
		if (isset($_GET[ 'scrape_id' ])){
			$id=$_GET[ 'scrape_id' ];
		}else{
			print 'This functionality is not enabled at the moment';
			$id='-1';
		}
		
		
		$scrapes = $wpdb->get_results( ( $wpdb->prepare( 'SELECT * FROM '.$wpdb->prefix . self::$db_table." WHERE id = %d", $id ) ) );
		foreach ( $scrapes as $scrape ) {
			/*
			'post_content'   => [ <string> ] // The full text of the post.
			'post_name'      => [ <string> ] // The name (slug) for your post
			'post_title'     => [ <string> ] // The title of your post.
			'post_status'    => [ 'draft' | 'publish' | 'pending'| 'future' | 'private' | custom registered status ] // Default 'draft'.
			'post_type'      => [ 'post' | 'page' | 'link' | 'nav_menu_item' | custom post type ] // Default 'post'.
			'post_author'    => [ <user ID> ] // The user ID number of the author. Default is the current user ID.
			'ping_status'    => [ 'closed' | 'open' ] // Pingbacks or trackbacks allowed. Default is the option 'default_ping_status'.
			'post_parent'    => [ <post ID> ] // Sets the parent of the new post, if any. Default 0.
			'menu_order'     => [ <order> ] // If new post is a page, sets the order in which it should appear in supported menus. Default 0.
			'to_ping'        => // Space or carriage return-separated list of URLs to ping. Default empty string.
			'pinged'         => // Space or carriage return-separated list of URLs that have been pinged. Default empty string.
			'post_password'  => [ <string> ] // Password for post, if any. Default empty string.
			'guid'           => // Skip this and let Wordpress handle it, usually.
			'post_content_filtered' => // Skip this and let Wordpress handle it, usually.
			'post_excerpt'   => [ <string> ] // For all your post excerpt needs.
			'post_date'      => [ Y-m-d H:i:s ] // The time post was made.
			'post_date_gmt'  => [ Y-m-d H:i:s ] // The time post was made, in GMT.
			'comment_status' => [ 'closed' | 'open' ] // Default is the option 'default_comment_status', or 'closed'.
			'post_category'  => [ array(<category id>, ...) ] // Default empty.
			'tags_input'     => [ '<tag>, <tag>, ...' | array ] // Default empty.
			'tax_input'      => [ array( <taxonomy> => <array | string> ) ] // For custom taxonomies. Default empty.
			'page_template'  => [ <string> ] // Requires name of template file, eg template.php. Default empty.
			*/
			$post = array(
				'post_name'	=> 	$this->get_post_name($scrape->url), 
				'post_title'	=> 	$this->get_post_name($scrape->url), 
				'post_status'   => 	'publish', 
				'post_type'	=>	'page', 
				'post_content'  => 	$scrape->post_text, 
				'post_author'   => 	1
			);	
			// print_r($post);
			$new_id=wp_insert_post($post);
			if ($new_id == 0){
				print 'ERROR failed to insert post';
			}else{
				print '<div class="updated"><p>Successfully inserted page ('.$new_id.')</p></div>';	
				$image_array=unserialize($scrape->images);
				if (count($image_array) != 0 ){
					if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
					$wp_upload_dir = wp_upload_dir();
					foreach ( $image_array as $image ) {
						if(copy($this->domain.'/'.$image['source'],$image['destination'])){							
							$filename = $image['destination'];
							$wp_filetype = wp_check_filetype( basename( $filename ), null );
							if (in_array($wp_filetype['ext'], array('jpg','gif','png','bmp','jpeg','ico'))){
								$attachment = array(
									'guid' => $wp_upload_dir['url'] . '/' . basename( $filename ),
									'post_mime_type' => $wp_filetype['type'],
									'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
									'post_content' => '',
									'post_status' => 'inherit'
								);
								$attach_id = wp_insert_attachment( $attachment, $filename);
								$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
								wp_update_attachment_metadata( $attach_id, $attach_data );
								
								print '<div class="updated"><p>Successfully copied '.$this->domain.'/'.$image['source'].' to '.$image['destination']."(".$attach_id.')</p></div>';
							}
						}else{
							print '<div class="error"><p>Error copying file from '.$this->domain.'/'.$image['source'].' to '.$image['destination'].'</p></div>';
						}
					}
				}			
				
				if ( is_plugin_active('wordpress-seo/wp-seo.php') ) {
					if ( $import_title == '1' && strlen($scrape->title) != 0){
						 if(update_post_meta($new_id,'_yoast_wpseo_title', $scrape->title)){
						 	print '<div class="updated">Successfully updated the title for YOAST</p></div>';
						 }else{
						 	print '<div class="error"><p>ERROR updating the title for YOAST</p></div>';
						 }	
					}
					if ( $import_description == '1' && strlen($scrape->description) != 0){
						if(update_post_meta($new_id,'_yoast_wpseo_metadesc', $scrape->description)){
							print '<div class="updated">Successfully updated the description for YOAST</p></div>';
						 }else{
							print '<div class="error"><p>ERROR updating the description for YOAST</p></div>';
						 }
					}
				}
			}
		
		}
	}
	
	/**
	* Displays the text in the Site URL (scrape) section of the settings page
	*/	
	public function site_url_details() {
		print 'Enter the page website URL for the site you would like to import from which would usually be the homepage. The max depth is how far into the site structure to spider and would usuall be set to between 1 and 10.';
	}

	/**
	* Displays the text in the Scrape details section of the settings page
	*/	
	public function scrape_details() {
		print 'Web scraping refers to the process of extracting the HTML from a web page. Usually you would not want to scrape the header or footer of a page so you will need to specify where in the html the main content is held.
			<p><a href = "javascript:void(0)" onclick = "document.getElementById(\'light2\').style.display=\'block\';document.getElementById(\'closeimg2\').style.display=\'block\';document.getElementById(\'fade\').style.display=\'block\'">
				Example Settings
			<a></p>
			<a href = "javascript:void(0)" onclick = "document.getElementById(\'light2\').style.display=\'none\';document.getElementById(\'closeimg2\').style.display=\'none\';document.getElementById(\'fade\').style.display=\'none\'">
			<img src="'. plugins_url('/img/fancy_close.png',dirname(__FILE__)).'" width="30" height="30" class="close" id="closeimg2" alt="Close this window" /></a>
			<div id="light2" class="white_content"><img src="'. plugins_url('/img/scrape_settings.png',dirname(__FILE__)).'" width="700" height="400" alt="Scrape Settings"/></div>';
				
	}
	
	/**
	* Displays the text in the filter details section of the settings page
	*/	
	public function filter_details() {
		print 'Enter the details for HTML to be amended and changed when it is scraped from the site';
	}

	/**
	* Displays the text in the image section of the settings page
	*/		
	public function image_settings() {
		print 'Images can be imported from the scraped site or even remote images on other sites can be imported into the uploads area';
	}

	/**
	* Displays the text in the SEO section of the settings page
	*/	
	public function seo_settings() {
		if ( is_plugin_active('wordpress-seo/wp-seo.php') ) {
			print 'Since YOAST (Wordpress SEO) is active additional fields can be imported from the scraped site';
		}else{
			print 'YOAST(Wordpress SEO) is not installed and active so these settings can not be used';
		}
	}
	
	
	/**
	* Displays a text input field in a form
	* @param array $details The details of the text input field
	*/
	public function text_field($details) { 
		$error_text='';
		$class='';
		foreach(get_settings_errors() as $error){
			if ($error['setting'] == $details['name']){
				$error_text='<span class="'.$error['type'].'">'.$error['message'].'</span>';
				$class=' '.$error['type'];
			}
		}
		if (isset($details['change'])){$javascript='onchange="'.$details['change'].'"';}else{$javascript='';}
		print '<input class="esi_input'.$class.'" name="'.$details['name'].'" '.$javascript.' type="text" value="'.esc_attr(get_option($details['name'])).'" id="'.$details['label_for'].'" /> ';
		if (isset($details['extra'])){print '</td><td>'.$details['extra'];}
		print $error_text;
	} 

	/**
	* Displays a checkbox field in a form
	* @param array $details The details of the checkbox field
	*/
	function checkbox_field($details) {
		$value=get_option($details['name']);
		if ($value == '1'){
			$checked='checked="checked"';
		}else{
			$checked='';
		}			
		if(isset($details['disabled']) && $details['disabled']){
			$checked.=' disabled="disabled"';
		}
		print '<input name="'.$details['name'].'" type="checkbox" value="1" id="'.$details['label_for'].'" '.$checked.'/> ';
	}

	/**
	* Displays a checkbox field in a form
	* @param array $details The details of the checkbox field
	*/
	function select_box($details) {		
		$value=get_option($details['name']);
		$options='';
		$default_selected='selected="selected"';
		foreach ($details['options'] as $option) {
			$field=$option[$details['options_name']];
			if ($field == $value){
				$selected=' selected="selected"';
				$default_selected='';
			}else{
				$selected='';
			}
			$options .= '<option value="' . str_replace('"','&quot;',$field) . '"' . $selected . '>' . htmlentities($field) . '</option>';
		}
		print '<select class="esi_input" id="' . $details['label_for'] . '" name="' . $details['name'] . '">';
		print '<option value="' . $details['default'] .'" ' . $default_selected . '>' . htmlentities($details['default']) . '</option>';
		print $options;
		print '</select>';
	}

	/**
	* Checks the value of of the URL set by the user and if it can be accessed
	* @param string $input The value for URL set by the user
	* @return 
	*/	
	public function check_site_url($input) {
		if ($input != ''){
			$file_headers = @get_headers($input);
			if ((!$file_headers)||($file_headers[0] == 'HTTP/1.1 404 Not Found')) {
				add_settings_error('scrapeURL', esc_attr( 'settings_updated' ), 'Unable to find the URL of the site entered', 'error');
			}else{
				$path_parts=parse_url($input);
				add_settings_error('scrapeURL', 'settings_updated', 'URL is valid and the site exists', 'updated');
			}
		}
		return apply_filters('check_site_url', $input, $input );
	}
	
	/**
	* Checks that the start or end html is included within the main container div
	* @param string $input The value for URL set by the user
	* @return 
	*/	
	public function check_start_html_included($input) {
		$spider=new spider($this->domain, $this->URL);
		$HTML_block=$spider->get_main_html_block( $this->HTML, get_option('mainHTMLBlock'), $this->domain.$this->URL);
		
		if ($input != ''){
			if (strpos($HTML_block, $input) === false) {
				add_settings_error('startHTML', esc_attr( 'settings_updated' ), 'Unable to find the start HTML with the main HTML block', 'error');
			}else{
				$path_parts=parse_url($input);
				add_settings_error('startHTML', 'settings_updated', 'Start HTML found within the HTML block', 'updated');
			}
		}
		return apply_filters('check_html_included', $input, $input );
	}
	
	/**
	* Checks that the start or end html is included within the main container div
	* @param string $input The value for URL set by the user
	* @return 
	*/	
	public function check_end_html_included($input) {
		$spider=new spider($this->domain, $this->URL);
		$HTML_block=$spider->get_main_html_block( $this->HTML, get_option('mainHTMLBlock'), $this->domain.$this->URL);

		if ($input != ''){
			if (strpos($HTML_block, $input) === false) {
				add_settings_error('endHTML', esc_attr( 'settings_updated' ), 'Unable to find the end HTML with the main HTML block', 'error');
			}else{
				$path_parts=parse_url($input);
				add_settings_error('endHTML', 'settings_updated', 'End HTML found within the HTML block', 'updated');
			}
		}
		return apply_filters('check_html_included', $input, $input );
	}

	
	/**
	* Checks the value of scrape depth is numeric
	* @param integer $input The value for depth set by the user
	* @return 
	*/
	public function check_scrape_depth( $input) {
		if ($input != ''){
			$file_headers = @get_headers($input);
			if (!is_numeric($input)) {
				add_settings_error('scrapeDepth', esc_attr( 'settings_updated' ), 'The depth needs to be numeric value', 'error');
			}else{
				// add_settings_error('scrapeDepth', 'settings_updated', 'Updated Scape Depth', 'updated');
			}
		}else{
			$input=4;
		}
		return apply_filters('check_site_url', $input, $input );
	}

}
		
?>
