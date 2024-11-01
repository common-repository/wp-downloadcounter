<?php
/*
Plugin Name: Download Counter
Plugin URI: http://projects.bovendeur.org/2007/07/06/download-counter/
Description: This plugin implements download counters for all possible URL's.
Author: Erwin Bovendeur
Version: 1.01
Author URI: http://projects.bovendeur.org/
*/

register_activation_hook('wp-downloadcounter/downloadcounter-config.php', array('DownloadCounter', 'activate'));

add_action('admin_menu', array('DownloadCounter', 'option_page'));

add_action('request', array('DownloadCounter', 'download_file'));
add_action('admin_print_scripts', array('DownloadCounter', 'options_head'));
add_filter('generate_rewrite_rules', array('DownloadCounter', 'generate_rewrite_rules'));
add_filter('query_vars', array('DownloadCounter', 'query_variables'));
add_filter('the_content', array('DownloadCounter', 'edit_content'));

global $wpdb, $wp_query;
define(DOWNLOADTABLE, $wpdb->prefix . 'downloadstats');
define(DOWNLOADTRACKTABLE, $wpdb->prefix . 'downloadtracking');

$downloadcounter = new DownloadCounter();

class DownloadCounter{
	function options_head() {
		global $plugin_page;
		if ($plugin_page == 'wp-downloadcounter/downloadcounter-options.php') {
			wp_enqueue_script('listman');
		}
	}

	function option_page() {
			add_management_page("DownloadCounter", "Downloads", 9, dirname(__FILE__).'/downloadcounter-options.php');
	}

	function activate() {
		global $wpdb;

		@include_once(ABSPATH . '/wp-admin/install-helper.php');

		$query = 'CREATE TABLE `' . DOWNLOADTABLE . '` (
				`download_id` BIGINT UNSIGNED AUTO_INCREMENT, 
				`download_name` VARCHAR (64) NOT NULL, 
				`download_url` VARCHAR (255) NOT NULL, 
				`download_added` DATETIME NOT NULL, 
				`download_count` BIGINT UNSIGNED DEFAULT \'0\' NOT NULL, 
				`download_last` DATETIME, 
				PRIMARY KEY(`download_id`), 
				UNIQUE(`download_name`)
			)';

		maybe_create_table(DOWNLOADTABLE, $query);

		$query = 'CREATE TABLE `' . DOWNLOADTRACKTABLE . '` (
				`tracking_id` BIGINT UNSIGNED AUTO_INCREMENT, 
				`download_id` BIGINT UNSIGNED NOT NULL, 
				`tracking_referer` VARCHAR (255), 
				`tracking_ip` VARCHAR (16) NOT NULL, 
				`tracking_date` DATETIME NOT NULL,
				`user_id` BIGINT UNSIGNED DEFAULT 0 NOT NULL,
				PRIMARY KEY(`tracking_id`), 
				INDEX(`download_id`)
			)';

		maybe_create_table(DOWNLOADTRACKTABLE, $query);
		maybe_add_column(DOWNLOADTRACKTABLE, 'user_id', 'alter table ' . DOWNLOADTRACKTABLE . ' add column user_id bigint unsigned default 0 not null');

		// Set options
		if (get_option('downloadstats_slug') === false) {
			add_option('downloadstats_slug', 'downloads');
		}
		if (get_option('downloadstats_pretty_urls') === false) {
			add_option('downloadstats_pretty_urls', '1');
		}		
	}

	function generate_rewrite_rules($wp_rewrite) {
		$rules = array(
				get_option('downloadstats_slug') . '/(.+)$' => 'index.php?download=' . $wp_rewrite->preg_index(1)
			);

		$wp_rewrite->rules = $rules + $wp_rewrite->rules;
	}

	function query_variables($public_query_vars) {
		$public_query_vars[] = 'download';
		return $public_query_vars;
	}

	function download_file($query_vars) {
		if (isset($query_vars['download'])) {
			global $wpdb, $downloadcounter;

			$query = 'select download_id, download_url from ' . DOWNLOADTABLE . ' where download_name="' . addslashes($query_vars['download']) . '"';
			$url = $wpdb->get_row($query, 0);
			if ($url) {
				$local_url = false;
				$parts = parse_url($url->download_url);
				if (strpos($url->download_url, get_option('siteurl')) == 0 && strlen($parts['query']) == 0) {
					$local_url = true;

					// Find local path for remote path...
					$pathinfo = pathinfo($parts['path']);

					// Get real file path...
					$path = realpath(ABSPATH . str_replace(get_option('siteurl'), '', $url->download_url));

					$content_type = $downloadcounter->get_mime_type($path);

					if (file_exists($path)) {
						// Get filesize
						$filesize = filesize($path);

						header('Content-Type: ' . $content_type);
						header('Content-Disposition: attachment; filename=' . $pathinfo['basename']);
						header('Content-Length: ' . $filesize);

						//Modified by NiuRay (http://www.niuray.com/wp/wp-downloadcounter-bug-fix/) from http://cn.php.net/readfile
						header('Content-Transfer-Encoding: binary');
						header('Expires: 0');
						header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
						ob_clean();
						flush();

						$is_safe_mode = ini_get('safe_mode');

						if (!$is_safe_mode)
						{
							set_time_limit(0);
						}

						@readfile($path);

						if (!$is_safe_mode)
						{
							set_time_limit(ini_get('max_execution_time'));
						}
					} else {
						$local_url = false;
					}
				}

				$user = wp_get_current_user();
				$query = 'update ' . DOWNLOADTABLE . ' set download_count=download_count+1, download_last=now() where download_name="' . addslashes($query_vars['download']) . '"';
				$wpdb->query($query);

				$query = 'insert into ' . DOWNLOADTRACKTABLE . ' (download_id, tracking_referer, tracking_ip, tracking_date, user_id) values (' . $url->download_id . ', "' . addslashes($_SERVER['HTTP_REFERER']) . '", "' . addslashes($_SERVER['REMOTE_ADDR']) . '", now(), ' . $user->ID . ')';
				$wpdb->query($query);

				if (!$local_url) {
					header('Location: ' . $url->download_url);
				}
				die;
			}
		}
		return $query_vars;
	}

	function get_mime_type($path) {
		$ext = array_pop(explode('.', $path));
		switch($ext) {
			case 'js' :
				$content_type = 'application/x-javascript';
				break;
			case 'json' :
				$content_type = 'application/json';
				break;
			case 'css' :
				$content_type = 'text/css';
				break;
			case 'xml' :
				$content_type = 'application/xml';
				break;
			case 'doc' :
			case 'docx' :
				$content_type = 'application/msword';
				break;
			case 'xls' :
			case 'xlt' :
			case 'xlm' :
			case 'xld' :
			case 'xla' :
			case 'xlc' :
			case 'xlw' :
			case 'xll' :
				$content_type = 'application/vnd.ms-excel';
				break;
			case 'ppt' :
			case 'pps' :
				$content_type = 'application/vnd.ms-powerpoint';
				break;
			case 'rtf' :
				$content_type = 'application/rtf';
				break;
			case 'pdf' :
				$content_type = 'application/pdf';
				break;
			case 'html' :
			case 'htm' :
			case 'php' :
				$content_type = 'text/html';
				break;
			case 'txt' :
				$content_type = 'text/plain';
				break;
			case 'mpeg' :
			case 'mpg' :
			case 'mpe' :
				$content_type = 'video/mpeg';
				break;
			case 'mp3' :
				$content_type = 'audio/mpeg3';
				break;
			case 'wav' :
				$content_type = 'audio/wav';
				break;
			case 'aiff' :
			case 'aif' :
				$content_type = 'audio/aiff';
				break;
			case 'avi' :
				$content_type = 'video/msvideo';
				break;
			case 'wmv' :
				$content_type = 'video/x-ms-wmv';
				break;
			case 'mov' :
				$content_type = 'video/quicktime';
				break;
			case 'tar' :
				$content_type = 'application/x-tar';
				break;
			case 'swf' :
				$content_type = 'application/x-shockwave-flash';
				break;
			case 'zip':
				$content_type = 'application/x-zip';
				break;
			case 'jpg':
			case 'jpe':
			case 'jpeg':
				$content_type = 'image/jpeg';
				break;
			case 'tiff':
			case 'bmp':
			case 'png':
			case 'gif':
			case 'xpm':
				$content_type = 'image/' . $ext;
				break;
			default:
				$content_type = false;
				if (function_exists('finfo_open')) {
					$finfo = @finfo_open(FILEINFO_MIME);
					if ($finfo) {
						$content_type = @finfo_file($finfo, $path);
						@finfo_close($finfo);
					}
				}
				if ($content_type === false && function_exists('mime_content_type')) {
					$content_type = @mime_content_type($path);
				}
				if ($content_type === false) {
					$content_type = @exec('file -ib ' . $path);
				}
				if ($content_type === false) {
					$content_type = 'application/force-download';
				}
				break;
		}
		return $content_type;
	}

	function create_url($name) {
		$site = get_option('home');
		$download_slug = get_option('downloadstats_slug');
		$pretty_links = get_option('downloadstats_pretty_urls');
		return ($pretty_links) ? $site . '/' . $download_slug . '/' . $name : $site . '/?download=' . $name;
	}

	function is_local_file($url) {
		if ($url == null) {
			global $wpdb;

			$query = 'select download_id, download_url from ' . DOWNLOADTABLE . ' where download_name="' . addslashes($query_vars['download']) . '"';
			$url = $wpdb->get_row($query, 0);
			$url = $url->download_url;
		}
		if ($url) {
			$parts = parse_url($url);
			$pos = strpos($url, get_option('siteurl'));
			return ($pos == 0 && $pos !== false && strlen($parts['query']) == 0);
		}
		return false;
	}

	function urlmtime($url) {
		return strtotime($this->urlheader($url, 'last-modified'));
	}

	function urlsize($url) {
		return strtotime($this->urlheader($url, 'content-length'));
	}

	var $headers = Array();

	function urlheader($url, $header) {
		if (array_key_exists($url, $this->headers)) {
			$metadata = $this->headers[$url];
		} else {
			$response = '';
			$fp = fopen($url, 'r');
			if (!$fp) {
				return;
			}
		   
			$metadata = stream_get_meta_data($fp);
			$this->headers[$url] = $metadata;
		}

		foreach($metadata['wrapper_data'] as $response) {
			$resp = strtolower($response);
			// case: redirection
			if (strpos($resp, 'location: ') === 0) {
				$newUri = substr($response, 10);
				fclose($fp);
				return urlmtime($newUri);
			}
			// case: last-modified
			else if (strpos($resp, $header . ': ') === 0) {
				$response = substr($response, strlen($header) + 2);
				break;
			}
		}
		@fclose($fp);
		return $response;
	}

	function get_filesize($url) {
		if ($this->is_local_file($url)) {
			$path = realpath(ABSPATH . str_replace(get_option('siteurl'), '', $url));
			$size = filesize($path);
		} else {
			$size = $this->urlsize($url);
		}
		return $size;
	}

	function get_lastmodified($url) {
		// Get the last update date & time
		if ($this->is_local_file($url)) {
			$path = realpath(ABSPATH . str_replace(get_option('siteurl'), '', $url));
			$last_updated = filemtime($path);
		} else {
			$last_updated = $this->urlmtime($url);
		}
		return $last_updated;
	}

	function format_size($size) {
		if($size / 1073741824 > 1) 
			return round($size/1048576, 1) . ' ' . __('GB', 'wp-downloadcounter');
		else if ($size / 1048576 > 1)
			return round($size/1048576, 1) . ' ' . __('MB', 'wp-downloadcounter');
		else if ($size / 1024 > 1)
			return round($size/1024, 1) . ' ' . __('kB', 'wp-downloadcounter');
		else
			return round($size, 1) . ' ' . __('B', 'wp-downloadcounter');
	}

	function edit_content($content) {
		global $downloadcounter;
		if (preg_match_all('/\[download(counter|updated|size|lastdownloaded)?\(([^\)]+)\)\]/u', $content, $matches)) {

			$filenames = array_unique($matches[2]);

			// Make sure the optional format tag filenames are mentioned first...
			sort($filenames);
			$filenames = array_reverse($filenames);
			foreach($filenames as $filename) {
				$format = get_option('date_format');
				$orgfilename = $filename;
				if (strpos($filename, ',') !== false) {
					$splitted = explode(',', $filename);
					$filename = trim($splitted[0]);
					$format = trim($splitted[1]);
				}

				$what_to_get = DOWNLOAD_URL | DOWNLOAD_AMOUNT | DOWNLOAD_LASTDOWNLOADED;
				if (strpos($content, '[downloadsize(' . $orgfilename) !== false) {
					$what_to_get |= DOWNLOAD_SIZE;
				}
				if (strpos($content, '[downloadupdated(' . $orgfilename) !== false) {
					$what_to_get |= DOWNLOAD_LASTMODIFIED;
				}

				$download_info = download_information($filename, $what_to_get);
				$content = str_replace('[download(' . $filename . ')]', $download_info['url'], $content);
				$content = str_replace('[downloadcounter(' . $filename . ')]', $download_info['amount'], $content);
				$content = str_replace('[downloadlastdownloaded(' . $orgfilename . ')]', date($format, $download_info['lastdownloaded']), $content);
				if (($what_to_get & DOWNLOAD_SIZE) > 0) {
					if ($format != 'false') {
						$download_info['size'] = $downloadcounter->format_size($download_info['size']);
					}
					$content = str_replace('[downloadsize(' . $orgfilename . ')]', $download_info['size'], $content);
				}
				if (($what_to_get & DOWNLOAD_LASTMODIFIED) > 0) {
					$content = str_replace('[downloadupdated(' . $orgfilename . ')]', date($format, $download_info['lastmodified']), $content);
				}
			}
		}

		return $content;
	}
}

define('DOWNLOAD_URL', 1);
define('DOWNLOAD_AMOUNT', 2);
define('DOWNLOAD_SIZE', 4);
define('DOWNLOAD_LASTMODIFIED', 8);
define('DOWNLOAD_LASTDOWNLOADED', 16);
define('DOWNLOAD_CONTENTTYPE', 32);

/**
 * Retrieve information about the specified download.
 *
 * Using the return_information argument you can specify
 * what information should be returned. Default only the URL
 * and the amount of downloads will be returned. Please keep
 * in mind that retrieving the SIZE and/or last modified 
 * values will result in a performance penalty, especially
 * when the download points to a remote server.
 *
 * @since 0.6
 *
 * @param string $download_name The name of the download.
 * @param int $return_information Optional. What information to return.
 * @return array The information of the download.
 */
function download_information($download_name, $return_information = 3) {
	global $wpdb, $downloadcounter;
	$result = $wpdb->get_row('select download_count, download_url, download_last from ' . DOWNLOADTABLE . ' where download_name="' . $download_name . '"');
	if (($return_information & DOWNLOAD_URL) > 0) {
		$retval['url'] = $downloadcounter->create_url($download_name);
	}
	if (($return_information & DOWNLOAD_AMOUNT) > 0) {
		$retval['amount'] = $result->download_count;
	}
	if (($return_information & DOWNLOAD_SIZE) > 0) {
		$retval['size'] = $downloadcounter->get_filesize($result->download_url);
	}
	if (($return_information & DOWNLOAD_LASTMODIFIED) > 0) {
		$retval['lastmodified'] = $downloadcounter->get_lastmodified($result->download_url);
	}
	if (($return_information & DOWNLOAD_LASTDOWNLOADED) > 0) {
		$retval['lastdownloaded'] = strtotime($result->download_last);
	}
//	if (($return_information & DOWNLOAD_CONTENTTYPE) > 0) {
//		$retval['contenttype'] = 
//	}
	return $retval;
}
?>