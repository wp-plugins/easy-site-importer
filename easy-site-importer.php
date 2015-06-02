<?php
/**
 * @package Site_Importer
 * @version 1.0.1
 */
/*
Plugin Name: Easy Site Importer
Plugin URI: http://wordpress.org/plugins/easy-site-importer/
Description: Easy Site Importer allows you to spider, scrape and copy content from another site making it the perfect migration tool for wordpress 
Author: Alex W Fowler
Version: 1.0.1
Author URI: http://werejuicy.com/

Copyright 2015 Alex W Fowler  (email : alex@werejuicy.com)
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License, version 2, as  published by the Free Software Foundation.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

error_reporting(E_ALL);
require_once 'class/class-spider.php';
require_once 'class/class-site-importer-admin.php';
function esi_load_css(){
	wp_register_style('esi_styles', plugins_url( './css/esi-styles.css', __FILE__ ), array(), '1', 'all');
	wp_enqueue_style( 'esi_styles' );
	// wp_register_style('titan_one', 'http://fonts.googleapis.com/css?family=Titan+One', array(), '1', 'all');
	// wp_enqueue_style( 'titan_one' );
}
	
new easy_site_importer();
add_action( 'admin_enqueue_scripts','esi_load_css' );

register_activation_hook(__FILE__, array('easy_site_importer', 'activate'));
register_deactivation_hook(__FILE__, array('easy_site_importer', 'deactivate'));

?>
