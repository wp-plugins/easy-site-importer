=== Easy Site Importer ===
Contributors: awfowler
Donate link: http://werejuicy.com/donations/
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Tags: import, scrape, spider, copy, migrate, scraper, crawler
Requires at least: 3.8
Tested up to: 4.2
Stable tag: 1.0.1

Easily copy and import content from any site by spidering, scraping and then processing the text, images and meta content easily

== Description ==

Easy site importer makes the process of migrating from any web site a much simpler, easy and a less time consuming process.
Simply enter the URL of a site and Easy Site Importer will automatically scan the target website to find sections of the site which have content to be scraped.
Specify which main HTML content block contains the main content for the site and if required an additional start and end string within this main content block.

* Automatically scan the site to identify the main content blocks for scraping
* Spider the site automatically to find all the pages with content
* Filter the html to remove inline CSS, Class, Span or Div tags
* Identify and replace hard coded absolute URLs 
* Import any images found into the media uploads directory and change the image tags 
* Import SEO title and description tags into wordpress SEO YOAST, All in One SEO Pack and Add Meta plugin


== Installation ==

1. Upload the `site-importer` folder to the `/wp-content/plugins/` directory of your wordpress site
2. Activate the Easy Site Importer plugin through the 'Plugins' menu in WordPress
3. Configure the plugin by going to the `Site Importer` menu that appears in your admin menu


== Frequently Asked Questions ==

= How do I spider more pages on a site =
It is possible to increase this limit depending on how responsive the site is, and if your web server can deal with the extra execution time.
Increase the limit setting within the class-site-importer-admin.php file, You may need to increase the php execution time within php.ini or by adding the line set_time_limit(200); 

= I get the error 'unexpected T_FUNCTION' =
This error is caused when using old versions of PHP which are less than version 5.3 and this plugin only works on versions of 5.3+.
You are probably using version 5.2 or older of PHP which will be well over 4 years old and has now no longer supported by PHP http://php.net/eol.php.
If you need to check your version of PHP you can try checking it by uploading the following code to the server <?php phpinfo(); ?>


== Screenshots ==

1. Simply enter the web URL of the site you would like to copy and click update to automatically scan the sites HTML structure for places where you main content may be held. In addition a start and end piece of html can be entered to properly identify it
2. Sample scrape settings for a typical site
3. Filter the HTML when it is imported into the site to make adjustments to the HTML
4. Preview the filtered HTML so you can see which bits are being replaced or removed
5. Import the images into the wordpress media library and amend the HTML to the new path. You can also import the titles and meta description into Yoast if it is installed
6. Review the pages before importing them into wordpress

== Changelog ==

= 0.9.0 =
* Initial release.

= 1.0.0 =
Added Support for All in One SEO and Add Meta plugins
Added Tutor
Added option to import into a page or a post
Visual tweaks
Display of image results lists them within the titl

= 1.0.1 =
Fixed issue with constructor class doing to much work 
Fixed issues with warnings being displayed
Add some more text to help explain error situations
Added ability to remove text from the post/page name