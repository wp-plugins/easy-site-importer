=== Easy Site Importer ===
Contributors: awfowler
Donate link: https://werejuicy.com
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: import, scrape, spider, copy, migrate, scraper, crawler
Requires at least: 3.8
Tested up to: 4.1
Stable tag: 1.0

Easily copy and import content from any site by spidering, scraping and then processing the text, images and meta content from another site.

== Description ==

Easy site importer makes the process of migrating from any web site a much simplier, easy and less time consuming process.
Simply enter the URL of a site and Easy Site Importer will automatically scan the target website to find sections of the site which have content to be scraped.
Specify which main HTML content block contains the main content for the site and if required an additonal start and end string within this main content block.

* Automatically scan the site to identify the main content blocks for scraping
* Spider the site automatically to find all the pages with content
* Filter the html to remove inline CSS, Class, Span or Div tags
* Identify and replace hard coded absolute URLs 
* Import any images found into the media uploads directory and change the image tags 
* Import SEO title and description tags into wordpress SEO YOAST plugin


== Installation ==

1. Upload the `site-importer` folder to the `/wp-content/plugins/` directory of your wordpress site
2. Activate the Easy Site Importer plugin through the 'Plugins' menu in WordPress
3. Configure the plugin by going to the `Site Importer` menu that appears in your admin menu


== Frequently Asked Questions ==
= How do I spider more than 100 pages on a site =
It is possible to increase this limit depending on how responsive the site is, and if your web server has its settings to not time out.
Increase the limit setting within the spider_site function, You may need to increase the php exectution time within php.ini or by adding the line set_time_limit(200); 


== Changelog ==

1.0.0
* Initial release.