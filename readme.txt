=== WP-DownloadCounter ===
Contributors: r-win
Donate link: http://projects.bovendeur.org/2007/07/06/download-counter/
Tags: download, download counter, counter
Requires at least: 2.0.2
Tested up to: 2.7.3
Stable tag: 1.1

Since WordPress does offer attaching downloads to pages, but doesn't keep track of the amount of downloads, this plugin was written.

== Description ==

Since WordPress does offer attaching downloads to pages, but doesn't keep track of statistical information about these downloads, I wrote this plugin.
I should mention statistical information is a big word for just another download counter, since that is main purpose of this plugin.

Features:

* Keeps track of the amount of downloads of a certain file.
* Keeps track of the last download time
* Allows you to reset the counter
* Allows you to add counters to both internal and external urls
* Can use fancy urls (like /downloads/file.zip)
* Can print the amount of downloads, the filesize and the last modified date in a post.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the content of the zip-file to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate to the Permalink Settings and press Save Changes or disable Pretty Links in the DownloadCounter Options (under Manage->Downloads)
1. Add you downloads to the Downloads page under the Manage menu
1. Alter you posts to use the tags as shown in the *Using tags in your posts* section

== Frequently Asked Questions ==

== Screenshots ==

1. Shows the download counter in action.
2. The source code of the post shown at screenshot-1. The used post tags are clearly visible in this screen.
3. Displays the configuration and a list of available downloads.
4. Displays a complete list of the downloads, including referrer, ip address, download date and the logged in user (if any).

== Arbitrary section ==

**Using tags in your posts**

When writing a post or a page, you may want to add the URL or the amount of downloads in your post. Using [ download(downloadname) ] (without the spaces) will be replaced with the download url, [ downloadcounter(downloadname) ] will be replaced with the amount of downloads, [ downloadsize(downloadname) ] will print the size of the download (in GB, MB, kB or B), [ downloadupdated(downloadname) ] will print the last modified date, using the WordPress setting you've specified for the date format. When creating a link, you could, for instance, use <a href="[ download(file.zip) ]">Download</a> (of course, again, without the spaces within the [] brackets).

Finally, when using downloadsize of downloadupdated, you can specify one extra argument. With downloadsize, you  can add ,false to prevent the usage of the GB, MB, kB or B postfixes. So, for example, using [ downloadsize(file.zip, false) ] will just display the size in bytes. With downloadupdated, you can enter a PHP date format string as extra argument. For example, [ downloadupdated(file.zip, d-m-Y) ] will display a date like 27-11-2008.

**Retrieving download information from PHP**

Since version 0.6 is it possible to get information about the downloads using PHP, so you can use this information directly in your templates or in you own plugins. Only one function is important at the moment:

`download_information($download_name, $return_information = DOWNLOAD_URL | DOWNLOAD_AMOUNT)`

This function will return an array with the requested information. You can specify what information to return by the argument $return_information. The file downloadcounter-options.php contains the correct define statements which can be used. Currently only URL, Amount, Size and Last Modified Date are available.

Using the code

`$info = download_information(wp-downloadcounter.zip, DOWNLOAD_URL | DOWNLOAD_AMOUNT | DOWNLOAD_SIZE | DOWNLOAD_LASTMODIFIED);
var_dump($info);`

returns:

`array(4) { ["url"]=> string(73) "http://projects.bovendeur.org/downloads/wp-downloadcounter.zip" ["amount"]=> string(4) "1878" ["size"]=> int(11006) ["lastmodified"]=> int(1228157426) } `