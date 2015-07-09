<?php
/**
 * @package Easy Site Importer
 * @version 1.0.1
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
	* @var array The database results of all the scrapes
	*/
	var $scrapes =  array();
	
	/**
	* @var integer The limit of how many pages to spider from the site
	*/
	var $limit =  60;

	function __construct() {
		global $wpdb;
		$table=$wpdb->prefix . self::$db_table;
		if( $wpdb->get_var( 'SHOW TABLES LIKE \''.$table.'\' ' ) == $table ) {
			$this->scrapes = $wpdb->get_results('SELECT * FROM '.$table);
		}
		
		add_action( 'admin_menu', array(&$this, 'site_importer_menu') );
		add_action( 'admin_init', array(&$this, 'site_importer_init') );
		add_action( 'wp_ajax_esi_update_option', array($this, 'esi_update_option_callback') );
		// add_action( 'wp_ajax_nopriv_esi_update_option', 'esi_update_option' );
	}
	
	/**
	* Setup the table within the db for the details from a spider and scrape
	*/	
	public static function activate() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		global $wpdb;
		$table = $wpdb->prefix . self::$db_table;
		$sql = 'CREATE TABLE ' . $table . ' (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			url varchar(250) DEFAULT \'\' NOT NULL,
			category varchar(100) DEFAULT \'\' NOT NULL,
			title varchar(250) DEFAULT \'\' NOT NULL,
			description varchar(250) DEFAULT \'\' NOT NULL,
			keywords varchar(250) DEFAULT \'\' NOT NULL,
			post_name varchar(250) DEFAULT \'\' NOT NULL,
			pre_text text NOT NULL,
			post_text text NOT NULL,
			post_type varchar(100) DEFAULT \'page\' NOT NULL,
			display_text text NOT NULL,
			images mediumblob NOT NULL,
			UNIQUE KEY id (id)
		); '.$wpdb->get_charset_collate();
		$results=dbDelta( $sql );
		if (substr($results[$table], 0, 7) !='Created'){
			print '<div class="error"><p>Error Creating table ('.$table.') - '.$results[$table].'</p></div>';
		}
		add_option( 'tutor', 'on');
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
	
	public function esi_update_option_callback() {
		update_option( 'tutor',$_POST['setting']);
	}
	

	/**
	* Setup the settings fields for the form and in the database
	*/
	public function site_importer_init() {
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
				$file_headers = @get_headers($this->domain.$this->URL);
				if ((!$file_headers)||($file_headers[0] != 'HTTP/1.1 404 Not Found')) {
					$this->HTML=file_get_contents($this->domain.$this->URL);
					$spider=new spider($this->domain, $this->URL);
					$spider->calculate_scrape_details($this->HTML);
					$this->meta_structure=$spider->div_meta;
				}				
			}
		}
		
		
		add_settings_section('scrape_url', 'Site crawler settings', array(&$this, 'site_url_details'), 'site_spider');  
		// $extra='<input type="submit" value="Update HTML Blocks" class="button button-primary" id="submit2" name="submit" disabled="disabled"> &nbsp;'; // 'change' => 'document.getElementById(\'submit2\').disabled = false'
		add_settings_field('scrapeURL', 'Website URL', array(&$this, 'text_field'), 'site_spider', 'scrape_url', array( 'name' => 'scrapeURL', 'label_for' => 'Website URL'));	
		add_settings_field('scrapeDepth', 'Max depth', array(&$this, 'text_field'), 'site_spider', 'scrape_url', array( 'name' => 'scrapeDepth', 'label_for' => 'Max depth' ,'tutor' => 'This field indicates how far into the site structure from the &quot;Website URL&quot; to crawl.<br/><br/><img src="'. plugins_url('/img/structure.png',dirname(__FILE__)).'" width="70" height="47" alt="Site Structure" />'));	
		
		add_settings_section('scrape_details', 'Scrape settings', array(&$this, 'scrape_details'), 'site_scrape');  
		add_settings_field('mainHTMLBlock', 'Main HTML block', array(&$this, 'select_box'), 'site_scrape', 'scrape_details', array( 'name' => 'mainHTMLBlock', 'label_for' => 'Main HTML block', 'options' => $this->meta_structure, 'options_name' => 'name', 'default' => '<body>','tutor' => 'These have been calculated from the Website URL and should only contain divs/sections which contain text content'));	
		
		if (get_option('includeStart') == '1'){$checked='checked="checked"';}else{$checked='';}
		$extra='<label for="Include the start HTML">Include the start HTML</label>'.$this->checkbox_field(array( 'name' => 'includeStart', 'label_for' => 'Include the start HTML', 'tutor' => 'If ticked this will scrape this HTML in as well, otherwise it will strip it'), true );
		
		add_settings_field('startHTML', 'Start of HTML to scrape', array(&$this, 'text_field'), 'site_scrape', 'scrape_details', array( 'name' => 'startHTML', 'label_for' => 'Start of HTML to scrape', 'extra' => $extra, 'tutor' => 'Within the Main HTML block specify the start of the html to start scraping'));	
		
		if (get_option('includeEnd') == '1'){$checked='checked="checked"';}else{$checked='';}
		$extra='<label for="Include the end HTML">Include the End HTML</label>'.$this->checkbox_field(array( 'name' => 'includeEnd', 'label_for' => 'Include the end HTML', 'tutor' => 'If ticked this will scrape this HTML in as well, otherwise it will strip it'), true);
		add_settings_field('endHTML', 'End of HTML to scrape', array(&$this, 'text_field'), 'site_scrape', 'scrape_details', array( 'name' => 'endHTML', 'label_for' => 'End of HTML to scrape', 'extra' => $extra, 'tutor' => 'Within the Main HTML block specifty where to stop scraping the content'));	
		
		add_settings_section('filter_details', 'HTML adjustments', array(&$this, 'filter_details'), 'site_scrape');  
		add_settings_field('stripCSS', 'Strip inline CSS', array(&$this, 'checkbox_field'), 'site_scrape', 'filter_details', array( 'name' => 'stripCSS', 'label_for' => 'Strip inline CSS', 'tutor' => 'Tick this box to remove any inline CSS during the scraping process eg.<h1 style="color:blue"> would become <h1>'));
		add_settings_field('stripClass', 'Strip all classes', array(&$this, 'checkbox_field'), 'site_scrape', 'filter_details', array( 'name' => 'stripClass', 'label_for' => 'Strip all classes' , 'tutor' => 'Classes may not be relevant once imported so tick here to have them removed eg.<h1 class"old_class_name"> would become <h1>' ));		
		add_settings_field('stripDiv', 'Strip all div tags', array(&$this, 'checkbox_field'), 'site_scrape', 'filter_details', array( 'name' => 'stripDiv', 'label_for' => 'Strip all div tags', 'tutor' => 'Tick here to have Divs removed eg.<div id="col1"> welcome </div> would become &quot;welcome&quot;'));
		add_settings_field('stripSpan', 'Strip all span tags', array(&$this, 'checkbox_field'), 'site_scrape', 'filter_details', array( 'name' => 'stripSpan', 'label_for' => 'Strip all span tags', 'tutor' => 'Tick here to have spans removed eg.<span class="red"> welcome </span> would become &quot;welcome>&quot;'));
		add_settings_field('replaceDomain', 'Remove scraped domain name from images and links', array(&$this, 'checkbox_field'), 'site_scrape', 'filter_details', array( 'name' => 'replaceDomain', 'label_for' => 'Remove scraped domain name from images and links', 'tutor' => 'If the site has absolute links to the old domain then these can be replace eg. www.oldsite.com/img/logo.png would become /img/logo.png'));
		
		add_settings_section('wordpress_settings', 'Wordpress Settings', false, 'site_importer');  
		add_settings_field('postType', 'Import into post type', array(&$this, 'select_box'), 'site_importer', 'wordpress_settings', array( 'name' => 'postType', 'label_for' => 'Import into post type', 'options_name' => 'name', 'options' => array(array('name' => 'post')), 'default' => 'page', 'tutor' => 'Set the post type to import items into either the Posts or as Pages'));
		add_settings_field('postNameRemove', 'Remove string from name', array(&$this, 'text_field'), 'site_importer', 'wordpress_settings', array( 'name' => 'postNameRemove', 'label_for' => 'Remove string from name', 'options_name' => 'name', 'tutor' => 'This string will be removed from the created page or post name eg. index or index,blog,en-gb '));
		
		add_settings_section('image_settings', 'Image Settings', array(&$this, 'image_settings'), 'site_importer');  
		add_settings_field('importLocal', 'Import images on spidering domain', array(&$this, 'checkbox_field'), 'site_importer', 'image_settings', array( 'name' => 'importLocal', 'label_for' => 'Import images on spidering domain', 'tutor' => 'Images held within the Website URL will be imported into the media library and the HTML will be changed to point to the new image'));
		add_settings_field('importRemote', 'Import images on other domains', array(&$this, 'checkbox_field'), 'site_importer', 'image_settings', array( 'name' => 'importRemote', 'label_for' => 'Import remote images', 'tutor' => 'Images found on any domain apart from the main Website URL will be imported into the media library and the HTML will be changed to point to the new image'));
		add_settings_field('copyDuplicates', 'Copy duplicate images', array(&$this, 'checkbox_field'), 'site_importer', 'image_settings', array( 'name' => 'copyDuplicates', 'label_for' => 'Copy duplicate images', 'tutor' => 'If this image name appears in the media library then tick if you still want the image copied'));
		
				add_settings_section('seo_settings', 'Seo Settings', array(&$this, 'seo_settings'), 'site_importer');
		$seo_plugin='';
		if (is_plugin_active('wordpress-seo/wp-seo.php')){
			$seo_plugin.='Yoast SEO plugin';
		}
		if (is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php')){
			if ($seo_plugin!=''){$seo_plugin.=' and the ';}
			$seo_plugin.='All in one SEO pack plugin';
		}
		if (is_plugin_active('add-meta-tags/add-meta-tags.php')){
			if ($seo_plugin!=''){$seo_plugin.=' and the ';}
			$seo_plugin.='Add Meta Tags SEO plugin';
		}
		if ( $seo_plugin!='' ) {
			add_settings_field('importTitle', 'Import the title tag', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importTitle', 'label_for' => 'Import the title tag', 'disabled' => false , 'tutor' => 'Import the title tag from the remote site directly into the '.$seo_plugin.' for the page'));
			add_settings_field('importDescription', 'Import the meta description', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importDescription', 'label_for' => 'Import the meta description', 'disabled' => false, 'tutor' => 'Import the meta description tag from the remote site directly into the '.$seo_plugin.' for the page'));
		}else{
			add_settings_field('importTitle', 'Import the title tag', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importTitle', 'label_for' => 'Import the title tag', 'disabled' => true, 'tutor' => 'No SEO plugins have been detected so the title tag can not be imported'));
			add_settings_field('importDescription', 'Import the meta description', array(&$this, 'checkbox_field'), 'site_importer', 'seo_settings', array( 'name' => 'importDescription', 'label_for' => 'Import the meta description', 'disabled' => true, 'tutor' => 'No SEO plugins have been detected so the Meta Description tag can not be imported'));
		}
		register_setting('site_spider', 'scrapeURL', array(&$this, 'check_site_url'));
		register_setting('site_spider', 'scrapeDepth', array(&$this, 'check_scrape_depth'));
		register_setting('site_scrape', 'mainHTMLBlock');
		register_setting('site_scrape', 'startHTML', array(&$this, 'check_start_html_included'));
		register_setting('site_scrape', 'endHTML', array(&$this, 'check_end_html_included'));
		register_setting('site_scrape', 'includeStart');
		register_setting('site_scrape', 'includeEnd');
		
		register_setting('site_scrape', 'stripCSS');
		register_setting('site_scrape', 'stripClass');
		register_setting('site_scrape', 'stripDiv');
		register_setting('site_scrape', 'stripSpan');
		register_setting('site_scrape', 'replaceDomain');
		
		register_setting('site_importer', 'importLocal');
		register_setting('site_importer', 'importRemote');
		register_setting('site_importer', 'copyDuplicates');
		register_setting('site_importer', 'postType');
		register_setting('site_importer', 'postNameRemove');
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
		
		print '<div id="esi_main"><h1>'.__('Easy Site Importer', 'easy-site-importer').'</h1>';
		print '<p>'.__('Site importer will spider, scrape and copy another site into wordpress', 'easy-site-importer').'</p>';
		$tabs = array( 
			'home' =>  __('About', 'easy-site-importer'),
			'spider_settings' =>  __('Crawl Settings', 'easy-site-importer'),
			'scrape' =>  __('HTML Filter', 'easy-site-importer'),
			'settings' =>  __('Import Settings', 'easy-site-importer'),
			'test' =>  __('Test Settings', 'easy-site-importer'),
			'spider' =>  __('Spider Site', 'easy-site-importer'),
			'review' =>  __('Import Content', 'easy-site-importer'),
		);
		if (isset($_GET['tab'])){
			$current=$_GET[ 'tab' ];
		}else{
			$current='';
		}
		if ($this->domain==''){
			$blackboard_text='<b>STEP 1</b><br/>You will need to click on the \'Crawl Settings\' tab and enter the site you want to copy from  in the \'Website URL\' Box ';
			$disabled_tabs=array('scrape','settings','test','spider','review');
		}elseif(count($this->scrapes)==0){
			if (get_option('mainHTMLBlock') == '' || (get_option('mainHTMLBlock') == '<body>' &&  get_option('startHTML') == '')){
				$blackboard_text='<b>STEP 2</b><br/>Select a \'Main HTML block\' from within the \'HTML Filter\' tab this will indicate where the HTML content is within the page ';
			}else{
				$blackboard_text='<b>STEP 3</b><br/>Spider and scrape the site by clicking on \'Spider site\' tab ';
			}
			$disabled_tabs=array('review');
		}else{
			$blackboard_text='';
			$disabled_tabs=array();
		}
		print '<h2 class="nav-tab-wrapper">';
		foreach( $tabs as $tab => $name ){
			$class='';
			if  ( $tab == $current ){
				$class=' nav-tab-active';
			}
			if (in_array($tab,$disabled_tabs)){
				$class.=' disabled';
			}
			print '<a class="nav-tab'.$class.'" id="esitab_'.$tab.'" href="?page=site_importer&tab='.$tab.'">'.$name.'</a>';
		}
		print '</h2>';		
		switch ($current) :
			case 'spider_settings' :
				$this->site_spider_settings();
				break;
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
		if ($blackboard_text==''){
			$blackboard_text= 'Tutorial messages will be displayed here if required. Hover over a form field to for more info about it. Click on the tutor icon to switch it on/off';
		}
		if (get_option('tutor')=='off'){
			$blackboard_display='none';
		}else{
			$blackboard_display='block';
		}
		print '<img src="'. plugins_url('/img/tutor.png',dirname(__FILE__)).'" id="tutor" width="69" height="69" alt="Web Tutor" 
			onclick="var e = document.getElementById(\'blackboard\');
			if(e.style.display == \'block\'){e.style.display = \'none\';var data = {action: \'esi_update_option\', setting: \'off\' }}
			else{e.style.display = \'block\';var data = {action: \'esi_update_option\', setting: \'on\' }} ;
			jQuery.post(ajaxurl, data, function(response) {});"/>';
		print '<div id="blackboard" onclick="this.style.display = \'none\'; var data = {action: \'esi_update_option\', setting: \'off\' }; jQuery.post(ajaxurl, data, function(response) {});" style="display: '.$blackboard_display.'"><p id="tutor_default">'.__($blackboard_text, 'easy-site-importer').'</p><p id="tutor_mouse"></p></div>';
		print '</div>';
	}

	
	/**
	* Displays the main page on the site 
	*/	
	public function main_page() {
		print '<div id="esi_nain"><h2>Easy Site Importer</h2>';
		print'		<p>Easy site importer makes the process of migrating from any web site a much simplier, easy and less time consuming process.
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
				<div class="white center section">
					<h3>Getting Started</h3>
					<img src="'. plugins_url('/img/instructions.png',dirname(__FILE__)).'" width="500" height="928" alt="First Steps with easy site importer"/>
				</div>
				<h3>Limitations</h3>
				<b>It will only spider '.$this->limit.' page from the target site</b><br />
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
	public function site_spider_settings() {
		print '<div id="esi_options1">
			<h2><span class="fa fa-cogs"></span> Site spider crawling</h2>

			<p>The crawler will spider the site to copy and get all the web pages available up to the limit of '.$this->limit.' pages.</p>
			<form method="post" action="options.php">';
				do_settings_sections( 'site_spider' );
				settings_fields( 'site_spider' ); // hidden values
				submit_button(); 
		print '</form></div><div id="fade" class="black_overlay"></div>';
	}
	
	/**
	* Displays the main settings form on the settings page
	*/	
	public function site_scrape_settings() {
		print '<div id="esi_options1">
			<h2><span class="fa fa-cogs"></span> Scraper and HTML Filter settings</h2>

			<p>The scraper will then extract the content from the pages which have been crawled</p>
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
		$table=$wpdb->prefix . self::$db_table;
		if( $wpdb->get_var( 'SHOW TABLES LIKE \''.$table.'\' ' ) != $table ) {
			print '<div class="error"><p>The results database table('.$table.') does not exist attempting to recreate it</p></div>';
			$this->activate();
		}
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
		print '<div id="loading"><p id="loadingp">Spidering<br/>pages</p></div>';
		print '<script>document.getElementById(\'loading\').style.display = \'block\';</script>';
		flush();
		
		
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
		
		$strip_attributes=array();
		if ( get_option('stripCSS') == '1'){$strip_attributes[]='style';}
		if ( get_option('stripClass') == '1'){$strip_attributes[]='class';}
		$params=array(
				'mainHTMLBlock' => get_option('mainHTMLBlock'),
				'startHTML' => get_option('startHTML'), 
				'endHTML' => get_option('endHTML'),
				'includeStart' => get_option('includeStart'),
				'includeEnd' => get_option('includeEnd'),
				'removeElements' => $remove_element,
				'stripElements' => $strip_element, 
				'stripAttributes' => $strip_attributes,
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
		print '<script>document.getElementById(\'loadingp\').innerHTML = "Analysing Pages";</script>';
		flush();
		$urlData=$spider->formatted_page_info($spider->output);
		if ($spider_type == 'test'){
			if (count($spider->formatted_output)>0){
				array_rand($spider->formatted_output, 1);
				foreach($spider->formatted_output as $page){
					$import_row='';
					$info_row='';
					foreach($page as $key => $meta){
						if ($key == 'Images'){
							$import_row.='<tr><th>'.$key.'</th><td>'.count($meta).' images</td></tr>';
						}elseif ($key != 'URL' && $key != 'Category' && $key != 'Scrape' && $key != 'Filter' && $key != 'Formatted'){
							if ($key == 'Title' || $key == 'description'){
								$import_row.='<tr><th>'.$key.'</th><td>'.$meta.'</td></tr>';
							}else{
								$info_row.='<tr><th>'.$key.'</th><td>'.$meta.'</td></tr>';
							}
						}
					}
					print '<h3>Page info for '.$page['URL'].'</h3>';
					print '<table class="white">';
					print '<tr><th colspan="2" class="grey">Page Info</th></tr>'.$info_row;
					print '<tr><th colspan="2" class="grey">Import Data</th></tr>'.$import_row;
					print '<tr><th colspan="2" class="grey">Key</th></tr>';
					print '<th class="red" width="150">red</th><td>items will be filtered out when imported into the site</td></tr>';
					print '<th class="green">Green</th><td>item have been amended from the original</td></tr>';
					print '</table>';
				}
				print '<h3>Scraped HTML sample</h2><div id="formatted_html">'.$page['Formatted'].'</div>';
			}else{
				print '<div class="error"><p>Unable to display details for page '.$this->domain.$this->URL.' no pages found('.count($spider->formatted_output).')<br/>';
				print 'There are '.count($spider->error).' reported errors/warnings<br/>';
				foreach($spider->error as $error){
					if (isset($error['error']) && $error['error']!=''){
						print 'ERROR - '.$error['error'];
						if (isset($error['page'])){
							print 'page('.$error['page'].')';
						}
						print '<br/>';
					}else{
						print '<b>'.$error['type'].'</b> - '. $error[0].'<br/>';
					}
				}
				print '</p></div>';
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
			$post_type=get_option('postType');
			$removeString=get_option('postNameRemove');
			foreach($spider->formatted_output  as $page_URL => $URL){
				$keywords='';
				$description='';
				$title='';
				$images='';
				if (isset($URL['keywords'])){$keywords=$URL['keywords'];}
				if (isset($URL['description'])){$description=$URL['description'];}
				if (isset($URL['Title'])){$title=$URL['Title'];}
				if (isset($URL['Images'])){$images=serialize($URL['Images']);}
				
				if ( $removeString != ''){
					if (strpos($removeString,',') !== false) {
						$removeArray=explode(',',$removeString);
						$post_name=str_replace($removeArray,'',$this->get_post_name($URL['URL']));
					}else{
						$post_name=str_replace($removeString,'',$this->get_post_name($URL['URL']));	
					}
				}else{
					$post_name=$this->get_post_name($URL['URL']);			
				}
				
				$SQL='INSERT INTO `'.$wpdb->prefix . self::$db_table.'` ';
				$SQL.='(`url`, `category`, `title`, `description`, `keywords`, `pre_text`, `post_text`, `display_text`, `images`, `post_name`, `post_type`) ';
				$SQL.=' VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )';
				// print $SQL."\n<br />";
				$wpdb->query( $wpdb->prepare($SQL, array($URL['URL'], $URL['Category'], $title, $description, $keywords, $URL['Scrape'], $URL['Filter'], $URL['Formatted'],$images,$post_name,$post_type)));
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
			print '<script>var tab=document.getElementById(\'esitab_review\'); tab.style.pointerEvents = \'auto\'; tab.style.color= \'#555555\'</script>';
		}
		print '<script>document.getElementById(\'loading\').style.display = \'none\';</script>';
		
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
		
		$post_type=get_option('postType');
		if ($post_type=='page'){
			$pages = get_pages(array('post_status' => 'publish'));
		}else{
			$pages = get_posts(array('post_type'=>$post_type, 'post_status' => 'publish'));
		}
		$page_name=array();
		foreach ( $pages as $page){
			$version=substr($page->post_name, -2);
			if (substr($page->post_name, -2, -1)=='-' && $page->post_title == substr($page->post_name, 0, -2) && is_numeric (substr($page->post_name, -1))){
				$page_names[]=substr($page->post_name, 0, -2);
			}elseif (substr($page->post_name, -3, -2)=='-' && $page->post_title == substr($page->post_name, 0, -3) && is_numeric (substr($page->post_name, -2))){
				$page_names[]=substr($page->post_name, 0, -3);
			}else{
				$page_names[]=$page->post_name;
			}
		}
		// print_r($page_names);
		
		$query_images = new WP_Query( array('post_type' => 'attachment', 'post_mime_type' =>'image', 'post_status' => 'inherit', 'posts_per_page' => -1) );
		$images = array();
		foreach ( $query_images->posts as $image) {
			$images[]=  basename(str_replace(array('-',' '),'',$image->guid));
		}
		// print_r($images);
		
		$divs='';
		$table='';
		$count=1;
		foreach ($this->scrapes as $scrape){
			$post_name=$scrape->post_name;
			$import=true;
			
			// title and desciption layout 
			$title_title=$scrape->title;
			$description_title=$scrape->description;
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
			if ((is_plugin_active('wordpress-seo/wp-seo.php'))||(is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php'))||(is_plugin_active('add-meta-tags/add-meta-tags.php'))){
				if (get_option('importTitle')!='1'){
					$title_class='class="grey"';
					$title_title.=' &#013 ** not imported as switched off in the import settings';
				}
				if (get_option('importDescription')!='1'){
					$description_class='class="grey"';
					$description_title.=' &#013 ** not imported as switched off in the import settings';
				}
			}else{
				$title_class='class="grey"';
				$description_class='class="grey"';
				$title_title.=' ** not imported as there is no SEO plugin installed to import it into';
				$description_title.=' &#013 ** not imported as there is no SEO plugin installed to import it into';
			}
						
			$image_array=unserialize($scrape->images);
			if (count($image_array) == 0 ){
				$image_text='None';
				$image_class='class="orange"';
				$image_title='No images found on this page';
			}else{
				$image_text=count($image_array).' images';
				$image_class='class="green"';
				$image_title='';
				foreach ( $image_array as $image){
					$basename=str_replace(array('-',' ','%20'),'',basename($image['source']));
					if (in_array($basename,$images)){
						$image_class='class="red"';
						$image_title.='&downdownarrows;';
					}
					$image_title.=$image['source'].'&#013';
				}				
			}
			if (get_option('importLocal')=='1' || get_option('importRemote')=='1'){
			
			}else{
				$image_class='class="grey"';
				$image_title.=' ** disabled in import settings';
			}
			
		
			if (strlen($scrape->post_text) < 4){
				$post_text_class='class="red"';
				$import=false;
			}else{
				$post_text_class='class="green"';
			}
			
			if (in_array($post_name, $page_names)){
				$post_name_class='class="red"';
				$post_name_text='This '.$post_type.' slug already exist so cannot be imported';
				$import=false;				
			}else{
				$post_name_class='class="green"';
				$post_name_text=$post_type.' slug does not exist for this '.$post_type.' so it can be imported';;
			}
			
			$filtered_characters=strlen($scrape->post_text) - strlen($scrape->pre_text);
			if ($filtered_characters==0){
				$filtered_characters=number_format(strlen($scrape->post_text));
				$filtered_text='(Not filtered)';
			}else{
				$filtered_characters=number_format(strlen($scrape->post_text));
				$filtered_text='(filtered)';
			}
			$table.='<tr>
					<td><a href="'.$this->domain.'/'.$scrape->url.'" target="_blank">'.$scrape->url.'</a></td>
					<td '.$title_class.' title="'.$title_title.'">'.$title_text.'</td>
					<td '.$description_class.' title="'.$description_title.'">'.$description_text.'</td>
					<td '.$image_class.' title="'.$image_title.'">'.$image_text.'</td>
					<td '.$post_text_class.' title="'.$filtered_text.'" >'.$filtered_characters.' chars</td>
					<td '.$post_name_class.' title="'.$post_name_text.'" >'.$post_name.'</td>
					<td> <a href = "javascript:void(0)" onclick = "document.getElementById(\'light'.$count.'\').style.display=\'block\';document.getElementById(\'closeimg'.$count.'\').style.display=\'block\';document.getElementById(\'fade\').style.display=\'block\'">
						<button type="button" class="right">Preview</button><a>
					</td>';
					
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
		print '<table class="white" id="results_table">';
		print '<tr><th>Orginal URL</th><th>Title</th><th>Description</th><th>Images</th><th>Text</th><th>'.ucwords($post_type).' Name</th><th>Preview</th><th>Import</th></tr>';
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
				'post_name'	=> 	$scrape->post_name, 
				'post_title'	=> 	$scrape->post_name, 
				'post_status'   => 	'publish', 
				'post_type'	=>	$scrape->post_type, 
				'post_content'  => 	$scrape->post_text, 
				'post_author'   => 	1
			);	
			// print_r($post);
			$new_id=wp_insert_post($post);
			if ($new_id == 0){
				print 'ERROR failed to insert post';
			}else{
				print '<div class="updated"><p>Successfully inserted '.$scrape->post_type.' ('.$new_id.')</p></div>';	
				$image_array=unserialize($scrape->images);
				if (count($image_array) != 0 ){
					if ( ! function_exists( 'wp_handle_upload' ) ){require_once( ABSPATH . 'wp-admin/includes/file.php' );}
					require_once( ABSPATH . 'wp-admin/includes/image.php' );
					$wp_upload_dir = wp_upload_dir();
					foreach ( $image_array as $image ) {
						if (substr($image['source'], 0, 4)=='http'){
							$source_name=$image['source'];
						}elseif (substr($image['source'], 0, 3)=='www'){
							$source_name='http://'.$image['source'];
						}elseif (substr($image['source'], 0, 1)=='/'){
							$source_name=$this->domain.$image['source'];
						}else{
							$source_name=$this->domain.'/'.$image['source'];
						}
						$dest_name=str_replace(array(' ','$','&',"'"),array('-','','',''),$image['destination']);
						if(copy($source_name,$dest_name)){							
							$wp_filetype = wp_check_filetype( basename( $dest_name ), null );
							if (in_array($wp_filetype['ext'], array('jpg','gif','png','bmp','jpeg','ico'))){
								$attachment = array(
									'guid' => $wp_upload_dir['url'] . '/' . basename( $dest_name ),
									'post_mime_type' => $wp_filetype['type'],
									'post_title' => preg_replace('/\.[^.]+$/', '', basename( $image['destination'] )),
									'post_content' => '',
									'post_status' => 'inherit'
								);
								$attach_id = wp_insert_attachment( $attachment, $dest_name);
								$attach_data = wp_generate_attachment_metadata( $attach_id, $dest_name );
								// if (!$attach_data){print 'Failed to generate_attachment-metadata('.$dest_name.')from('.$source_name.')'."<br/>\n";}
								$success=wp_update_attachment_metadata( $attach_id, $attach_data );
								// if (!$success){print 'Failed to update_attachment-metadata';}
								
								print '<div class="updated"><p>Successfully copied '.$source_name.' to '.$image['destination']."(".$attach_id.')</p></div>';
							}
						}else{
							print '<div class="error"><p>Error copying file from '.$source_name.' to '.$image['destination'].'</p></div>';
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
				if ( is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') ) {
					if ( $import_title == '1' && strlen($scrape->title) != 0){
						 if(update_post_meta($new_id,'_aioseop_title', $scrape->title)){
							print '<div class="updated">Successfully updated the title for All in one SEO pack</p></div>';
						 }else{
							print '<div class="error"><p>ERROR updating the title for All in one SEO pack</p></div>';
						 }	
					}
					if ( $import_description == '1' && strlen($scrape->description) != 0){
						if(update_post_meta($new_id,'_aioseop_description', $scrape->description)){
							print '<div class="updated">Successfully updated the description for All in one SEO pack</p></div>';
						 }else{
							print '<div class="error"><p>ERROR updating the description for All in one SEO pack</p></div>';
						 }
					}
				}
				if ( is_plugin_active('add-meta-tags/add-meta-tags.php') ) {
					if ( $import_title == '1' && strlen($scrape->title) != 0){
						 if(update_post_meta($new_id,'_amt_title', $scrape->title)){
							print '<div class="updated">Successfully updated the title for Add Meta Tags plugin</p></div>';
						 }else{
							print '<div class="error"><p>ERROR updating the title for Add Meta Tags plugin</p></div>';
						 }	
					}
					if ( $import_description == '1' && strlen($scrape->description) != 0){
						if(update_post_meta($new_id,'_amt_description', $scrape->description)){
							print '<div class="updated">Successfully updated the description for Add Meta Tags plugin</p></div>';
						 }else{
							print '<div class="error"><p>ERROR updating the description for Add Meta Tags plugin</p></div>';
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
		print '<p>Enter the page website URL for the site you would like to import from which would usually be the homepage. The max depth is how far into the site structure to spider and would usuall be set to between 1 and 10.</p>';
	}

	/**
	* Displays the text in the Scrape details section of the settings page
	*/	
	public function scrape_details() {
		print '<p>Web scraping refers to the process of extracting the HTML from a web page. Usually you would not want to scrape the header or footer of a page so you will need to specify where in the html the main content is held.</p>
			<p><a href = "javascript:void(0)" onclick = "document.getElementById(\'light2\').style.display=\'block\';document.getElementById(\'closeimg2\').style.display=\'block\';document.getElementById(\'fade\').style.display=\'block\'">
				Example Settings
			</a></p>
			<div class="esi_table">';
		$sections=count($this->meta_structure);
		if ($sections==0){
			print '<p>Error we do not seem to be able to find any sections/divs within your HTML. This will be caused by the site structure being very simple, or we were unable to find the site at the moment.</p>';
		}else{
			print '<table><caption>Site Analysis for '.$this->domain.$this->URL.'</caption<tr><th>Section</th><th>Amount of Words</th><th>Snippet</th></tr>';
			for ($x = 0; $x < $sections; $x++) {
				if ($x<5 || $this->meta_structure[$x]['words']>50){
					print '<tr><td>'.str_replace(array('<','>'),array('&lt;','&gt;'),$this->meta_structure[$x]['name']).'</td><td>'.$this->meta_structure[$x]['words'].'</td><td>'.$this->meta_structure[$x]['snippet'].'</td></tr>';	
				}
			}
			print '</table>';
		}
		print 	'</div>';
		print	'<a href = "javascript:void(0)" onclick = "document.getElementById(\'light2\').style.display=\'none\';document.getElementById(\'closeimg2\').style.display=\'none\';document.getElementById(\'fade\').style.display=\'none\'">
			<img src="'. plugins_url('/img/fancy_close.png',dirname(__FILE__)).'" width="30" height="30" class="close" id="closeimg2" alt="Close this window" /></a>
			<div id="light2" class="white_content"><img src="'. plugins_url('/img/scrape_settings.png',dirname(__FILE__)).'" width="700" height="400" alt="Scrape Settings"/></div>';
				
	}
	
	/**
	* Displays the text in the filter details section of the settings page
	*/	
	public function filter_details() {
		print '<p>Enter the details for HTML to be amended and changed when it is scraped from the site</p>';
	}

	/**
	* Displays the text in the image section of the settings page
	*/		
	public function image_settings() {
		print '<p>Images can be imported from the scraped site or even remote images on other sites can be imported into the uploads area</p>';
	}	

	/**
	* Displays the text in the SEO section of the settings page
	*/	
	public function seo_settings() {
		$seo_text='';
		if (is_plugin_active('wordpress-seo/wp-seo.php')){
			$seo_text.='<img src="//ps.w.org/wordpress-seo/assets/icon-128x128.png?rev=974614" width="64" height="64" alt"Yoast" title="Yoast Detected" /> '; 
		}
		if (is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php')){
			$seo_text.='<img src="//ps.w.org/all-in-one-seo-pack/assets/icon-128x128.png?rev=979908" width="64" height="64" alt="All in one SEO pack" title="All is on SEO pack detected" /> ';
		}
		if (is_plugin_active('add-meta-tags/add-meta-tags.php')){
			$seo_text.='<img src="//ps.w.org/add-meta-tags/assets/icon-128x128.png?rev=1090139" width="64" height="64" alt="All in one SEO pack" title="Add Meta Tags detected" /> ';
		}
		if ( $seo_text!='' ) {
			print '<p>You have plugins installed so additional fields can be imported from the scraped site<br/><b class="top">SEO Plugins Detected </b> &nbsp; ' .$seo_text.'</p>';
		}else{
			print '<p>You do not have any SEO plugins where we can import the title and description.</p>';
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
		print '<input class="esi_input'.$class.'" name="'.$details['name'].'" '.$javascript.' type="text" value="'.esc_attr(get_option($details['name'])).'" id="'.$details['label_for'].'" ';
		if (isset($details['tutor'])){
			$js_over='document.getElementById(\'tutor_default\').style.display=\'none\'; document.getElementById(\'tutor_mouse\').innerHTML=\''.str_replace(array('"','<','&lt;img'),array("\'",'&lt;','<img'),$details['tutor']).'\'; ';
			$js_out='document.getElementById(\'tutor_default\').style.display=\'block\'; document.getElementById(\'tutor_mouse\').innerHTML=\'\'; ';
			print 'onmouseover="'.$js_over.'" onfocus="'.$js_over.'" onmouseout="'.$js_out.'" onblur="'.$js_over.'" ';
		}
		print '/> ';
		print $error_text;
		if (isset($details['extra'])){print '</td><td>'.$details['extra'];}
		
	} 

	/**
	* Displays a checkbox field in a form
	* @param array $details The details of the checkbox field
	* @param boolean $det The details of the checkbox field
	*/
	function checkbox_field($details, $return=false) {
		$value=get_option($details['name']);
		if ($value == '1'){
			$checked='checked="checked"';
		}else{
			$checked='';
		}			
		if(isset($details['disabled']) && $details['disabled']){
			$checked.=' disabled="disabled"';
		}
		$text='<input name="'.$details['name'].'" type="checkbox" value="1" id="'.$details['label_for'].'" '.$checked.' ';
		if (isset($details['tutor'])){
			$js_over='document.getElementById(\'tutor_default\').style.display=\'none\'; document.getElementById(\'tutor_mouse\').textContent=\''.str_replace(array('"','<','>'),array("\'",'&lt;','&gt;'),$details['tutor']).'\'; ';
			$js_out='document.getElementById(\'tutor_default\').style.display=\'block\'; document.getElementById(\'tutor_mouse\').textContent=\'\'; ';
			$text.='onmouseover="'.$js_over.'" onfocus="'.$js_over.'" onmouseout="'.$js_out.'" onblur="'.$js_over.'" ';
		}
		$text.='/> ';
		if ($return){
			return $text;
		}else{
			print $text;
		}
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
		print '<select class="esi_input" id="' . $details['label_for'] . '" name="' . $details['name'] . '" ';
		if (isset($details['tutor'])){
			$js_over='document.getElementById(\'tutor_default\').style.display=\'none\'; document.getElementById(\'tutor_mouse\').textContent=\''.str_replace(array('"','<','>'),array("\'",'&lt;','&gt;'),$details['tutor']).'\'; ';
			$js_out='document.getElementById(\'tutor_default\').style.display=\'block\'; document.getElementById(\'tutor_mouse\').textContent=\'\'; ';
			print 'onmouseover="'.$js_over.'" onfocus="'.$js_over.'" onmouseout="'.$js_out.'" onblur="'.$js_over.'" ';
		}
		print '>';
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
			if (substr($input, 0, 7)!='http://'){ $input='http://'.$input;}
			$file_headers = @get_headers($input);
			// print_r($file_headers);
			
			if ((!$file_headers)||($file_headers[0] == 'HTTP/1.1 404 Not Found')) {
				add_settings_error('scrapeURL', esc_attr( 'settings_updated' ), 'Unable to find the URL of the site entered', 'error');
			}else{
				if ($file_headers[0] =='HTTP/1.1 301 Moved Permanently'){
					foreach($file_headers as $header){
						if (substr($header, 0, 9)=='Location:'){
							$input=trim(substr($header,9));
						}
					}
					add_settings_error('scrapeURL', 'settings_updated', 'URL 301 redirects. So changed the URL to this value', 'error');
				}else{
					add_settings_error('scrapeURL', 'settings_updated', 'URL is valid and the site exists('.$input.')', 'updated');
				}
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
				add_settings_error('startHTML', 'settings_updated', 'Found within the main HTML block', 'updated');
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
				add_settings_error('endHTML', 'settings_updated', 'Found within the main HTML block', 'updated');
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
