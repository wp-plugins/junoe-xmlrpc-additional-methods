=== Junoe XML-PRC Additionals ===
Contributors: tohokuaiki
Donate link: 
Tags: xmlrpc
Requires at least: 3.4.0
Tested up to: 4.2.2
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin supply additional XML-RPC methods to your WordPress.And some shortcode tag.

== Description ==

= XMLRPC =
Supplied methods are below.

* wp.JdeleteAllPage : delete all pages
* wp.JpluginInfo : return installed WordPress and this plugin information.
* wp.JcheckAdminAccount : check account has administrator privilege or not.
* wp.JgetAllPageByJSON : get all page content by json
* wp.JaddNewBlog : create new blog
* wp.JupdateBlog : edit blog information
* wp.JdeleteBlog : delete blog
* wp.JpluginActivate : activate plugin
* wp.JgetPostBySlug : get post id by slug
* wp.JgetPageBySlug : get page id by slug
* wp.JuploadFile : upload file with enable overwriting
* wp.JsetSiteToppage : set site toppage option

= shortcode =
* confluence-emoticon : emulate confluence emoticons. arguments : name accepts "(y), (n), (i), (/), (x), (!), (+), (-), (off), (\*y), (\*), (\*r), (\*g), (\*b)"


== Installation ==

1. Upload `junoe-xmlrpc-additionals` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==
...

== Screenshots ==
...

== Changelog ==
...
= 1.0.4 =
* add shortcode. Icons are from [emojione.com](http://emojione.com/ "emojione").
* change to directory structure.

= 1.0.3 =
* wp.JpluginInfo return WordPress name,version,is multi site mode or not and this plugin version.
* wp.JdeleteAllPage targets not onry publushed pages but also private pages.

= 1.0.2 =
* add  methods. wp.JuploadFile, JgetPostBySlug , JsetSiteToppage

= 1.0.1 =
* bug fix

= 1.0.0 =
* First release.

== Upgrade Notice ==
...

