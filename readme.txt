=== Easy Site Importer ===
Contributors: awfowler
Donate link: https://werejuicy.com
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: import, scrape, spider, copy, migrate, scraper, crawler
Requires at least: 3.8
Tested up to: 4.1
Stable tag: 1.0

Easily copy and import content from any site by spidering, scraping and then processing the text, images and meta content from any other site.

== Description ==

Easy site importer makes the process of migrating from any web site a much simpler, easy and less time consuming process.
Simply enter the URL of a site and Easy Site Importer will automatically scan the target website to find sections of the site which have content to be scraped.
Specify which main HTML content block contains the main content for the site and if required an additional start and end string within this main content block.

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
Increase the limit setting within the spider_site function, You may need to increase the php execution time within php.ini or by adding the line set_time_limit(200); 

== Screenshots ==

1. Simply enter the web URL of the site you would like to copy and click update to automatically scan the sites HTML structure for places where you main content may be held. In addition a start and end piece of html can be entered to properly identify it
2. Sample scrape settings for a typical site
3. Filter the HTML when it is imported into the site to make adjustments to the HTML
4. Preview the filtered HTML so you can see which bits are being replaced or removed
5. Import the images into the wordpress media library and amend the HTML to the new path. You can also import the titles and meta description into Yoast if it is installed
6. Review the pages before importing them into wordpress

== Changelog ==

0.9.0

* Initial release.