<?php
/*
+----------------------------------------------------------------------+
|                                                                      |
| WordPress 2.2 Plugin: WP-DownloadCounter 1.0                         |
| Copyright (c) 2007 Erwin Bovendeur                                   |
|                                                                      |
| File Written By:                                                     |
| - Erwin Bovendeur                                                    |
| - http://projects.bovendeur.org                                      |
|                                                                      |
| File Information:                                                    |
| - DownloadCounter Management page                                    |
| - wp-content/plugins/wp-downloadcounter/downloadcounter-options.php  |
|                                                                      |
+----------------------------------------------------------------------+
*/

# Variables
#

$base_name = plugin_basename('wp-downloadcounter/downloadcounter-options.php');
$base_page = 'edit.php?page='.$base_name;

# Localize plugin
#

load_plugin_textdomain('wp-downloadcounter', 'wp-content/plugins/wp-downloadcounter/lang');

function show_pages() {
	global $amount_of_pages, $current_page;
	if ($amount_of_pages > 1) {
?>
	<table style="padding: 7px 15px 9px 10px; vertical-align: top; width: 100%;">
		<tr>
			<td align="right"><?php
	for ($i = 1; $i <= $amount_of_pages; $i++) {
		if ($i != 1) {
			echo ' | ';
		}
		if ($i == $current_page) {
			echo $i;
		} else {
			echo '<a href="' . $_SERVER['REQUEST_URI'] . '&amp;page_id=' . $i . '">' . $i . '</a>';
		}
	}
	?></td>
		</tr>
	</table>

<?php
	}
}

function calculate_pages($amount) {
	global $items_per_page, $amount_of_pages, $current_page;
	$items_per_page = get_option('downloadstats_items_per_page', 500);
	if ($items_per_page == 0) {
		$amount_of_pages = 1;
		$current_page = 1;
	} else {
		$amount_of_pages = ceil($amount / (float) $items_per_page);
		$current_page = (int) $_GET['page_id'];
		if ($current_page < 1) {
			$current_page = 1;
		} else if ($current_page > $amount_of_pages) {
			$current_page = $amount_of_pages;
		}
	}
}

# Delete a download
#

if ($_POST['submit']) {
	if ($_POST['config'] == 1) {
		update_option('downloadstats_pretty_urls', (int) $_POST['pretty_links']);

		$download_slug = $_POST['download_slug'];
		if (substr($download_slug, 0, 1) == '/') {
			$download_slug = substr($download_slug, 1);
		}
		update_option('downloadstats_slug', $download_slug);
		update_option('downloadstats_items_per_page', (int) $_POST['items_per_page']);

		$text = __('Configuration saved', 'wp-downloadcounter');
	} else {
		$download_name = addslashes($_POST['download_name']);
		$download_url = addslashes($_POST['download_url']);

		if (isset($_POST['download_id'])) {
			$query = 'UPDATE ' . DOWNLOADTABLE . ' set download_name="' . $download_name . '", download_url="' . $download_url . '" where download_id=' . $_POST['download_id'];
		} else {
			$query = 'INSERT INTO ' . DOWNLOADTABLE . ' (download_name, download_url, download_added) values ("' . $download_name . '", "' . $download_url . '", now())';
		}
		$wpdb->query($query);

	// wp_redirect doesn't work here, since data is already sent to the browser.
		$text = (isset($_POST['download_id'])) ? __('Download %s changed', 'wp-downloadcounter') : __('Download %s added', 'wp-downloadcounter');
	}

	?>
		<script language="JavaScript">
		location.href = "<?php echo $base_page; ?>&text=<?php echo urlencode(sprintf($text, $_POST['download_name'])); ?>";
		</script>
	<?php
		die;
}

if ($_GET['action']) {
	$download_id = (int) $_GET['download_id'];
	switch($_GET['action']) {
		case 'delete':
			if ($download_id != 0) {
				// Delete the selected post
				do_action('delete_download', $download_id);
				$wpdb->query('DELETE FROM ' . DOWNLOADTABLE . ' WHERE download_id = ' . $download_id);
				$text = __('Download removed', 'wp-downloadcounter');
			}
			break;
		case 'reset':
			if ($download_id != 0) {
				do_action('reset_download', $download_id);
				$wpdb->query('UPDATE ' . DOWNLOADTABLE . ' SET download_count=0, download_last=NULL WHERE download_id = ' . $download_id);
				$wpdb->query('DELETE FROM ' . DOWNLOADTRACKTABLE . ' where download_id=' . $download_id);
				$text = __('Download counter reset', 'wp-downloadcounter');
			}
			break;
		case 'edit':
			if ($download_id != 0) {
				$download = $wpdb->get_row('select download_name, download_url from ' . DOWNLOADTABLE . ' where download_id=' . $download_id);
			}
		case 'add':
?>
<div class="wrap">
	<h2><?php _e(isset($download_id) ? 'Edit download' : 'Add download', 'wp-downloadcounter'); ?></h2>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
<?php if ($download_id != 0) { ?>
	<input type="hidden" name="download_id" value="<?php echo $download_id;?>" />
<?php } ?>
	<table class="widefat">
		<tr>
			<th scope="row" style="text-align: right;"><?php _e('Name', 'wp-downloadcounter'); ?></th>
			<td><input type="text" name="download_name" size="80"<?php if (isset($download)){ echo ' value="' . $download->download_name . '"'; } ?> /></td>
		</tr>
		<tr>
			<th scope="row" style="text-align: right;"><?php _e('URL', 'wp-downloadcounter'); ?></th>
			<td><input type="text" name="download_url" size="80"<?php if (isset($download)){ echo ' value="' . $download->download_url . '"'; } ?> /></td>
		</tr>
		<tr>
			<td></td>
<?php
			$button_text = isset($download) ? __('Save changes', 'wp-downloadcounter') : __('Save', 'wp-downloadcounter');
?>
			<td><input type="submit" class="button" name="submit" value="<?php echo $button_text;?>" /></td>
		</tr>
	</table>
	</form>
</div>
<?php
			exit;
		case 'view':
			$download = $wpdb->get_row('select download_name, download_count from ' . DOWNLOADTABLE . ' where download_id=' . $download_id, 0);

			calculate_pages($download->download_count);

			$query = 'select tracking_id, tracking_referer, tracking_ip, tracking_date, user_id from ' . DOWNLOADTRACKTABLE . ' where download_id=' . $download_id;
			if ($items_per_page > 0) {
				$query .= ' limit ' . ($current_page - 1)  * $items_per_page . ', ' . $items_per_page;
			}

			$results = $wpdb->get_results($query);
?>
<div class="wrap">
	<h2><?php printf(__('Downloads for %s', 'wp-downloadcounter'), $download->download_name); ?></h2>
	<h3><?php printf(__('Total downloads: %d', 'wp-downloadcounter'), $download->download_count); ?></h3>

<?php show_pages(); ?>

	<table class="widefat">
		<thead>
		<tr>
			<th scope="col"><?php _e('Referer', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('IP Address', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('Date', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('User', ''); ?></th>
		</tr>
		<thead>
		<tbody id="the-list">
		<?php foreach($results as $row) { 
					$class = ($class == 'alternate') ? '' : 'alternate';
			$title = get_userdata($row->user_id);
?>
		<tr id='tracking-<?php echo $row->tracking_id;?>' class='<?php echo $class;?>'>
			<td valign="middle"><?php echo $row->tracking_referer; ?></td>
			<td valign="middle"><?php echo $row->tracking_ip; ?></td>
			<td valign="middle"><?php echo $row->tracking_date; ?></td>
			<td valign="middle"><?php
				if ($row->user_id != 0) {
?>
<!--	<a href="user-edit.php?user_id=<?php echo $row->user_id; ?>"><?php echo get_avatar($row->user_id, 32); ?></a> -->
	<a href="user-edit.php?user_id=<?php echo $row->user_id; ?>"><?php echo $title->display_name; ?></a>
<?php
				}
			?></td>
		</tr>
	<?php } ?>
		</tbody>
	</table>

<?php show_pages(); ?>

</div>
<?php
			exit;
	}
}
?>

<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<div class="wrap">
	<h2><?php _e('Configuration', 'wp-downloadcounter'); ?></h2>
	<form method="post" action="<?php echo $base_page; ?>">
	<input type="hidden" name="config" value="1" />
	<table class="optiontable">
		<tr>
			<th scope="row" valign="top"><?php _e('Use pretty links', 'wp-downloadcounter'); ?></th>
			<td>
				<input type="checkbox" name="pretty_links" value="1" id="pretty_links"<?php if (get_option('downloadstats_pretty_urls') == 1) { echo ' checked="checked"'; } ?>/>
				<?php _e('When this checkbox is checked, the plugin will generate URL\'s using the slug defined below. When this checkbox in not checked, download links will be generated as /?download=&lt;name&gt;', 'wp-downloadcounter'); ?>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Download slug', 'wp-downloadcounter'); ?></th>
			<td>
				<input type="text" name="download_slug" id="download_slug" value="<?php echo get_option('downloadstats_slug'); ?>"/> <?php _e('After changing this value, you should update the Permalink structure at <a href="options-permalink.php">this</a> page, before the new download slug will work. Since the download URL\'s created by this plugin are already changed, your visitors will receive a not found page untill you update the Permalink structure.', 'wp-downloadcounter'); ?>
			</td>
		</tr>
		<tr>
			<th scope="row" valign="top"><?php _e('Items per page', 'wp-downloadcounter'); ?></th>
			<td>
				<input type="text" name="items_per_page" id="items_per_page" value="<?php echo get_option('downloadstats_items_per_page', 500); ?>"/> <?php _e('Specify the amount of logs you will see on each page.', 'wp-downloadcounter'); ?>
			</td>
		</tr>
	</table>
	<p class="submit"><input type="submit" class="button" name="submit" value="<?php _e('Save changes', 'wp-multiblog'); ?>" /></p>
	</form>
	<h2><?php _e('Available downloads', 'wp-downloadcounter'); ?></h2>
<?php
		$download = $wpdb->get_var('select count(*) from ' . DOWNLOADTABLE, 0, 0);

		calculate_pages($download);

		$query = 'select download_id, download_name, download_url, download_added, download_count, download_last from ' . DOWNLOADTABLE;
		if ($items_per_page > 0) {
			$query .= ' limit ' . ($current_page - 1)  * $items_per_page . ', ' . $items_per_page;
		}
		$download_result = $wpdb->get_results($query);

		if (count($download_result) == 0) {
?>
		<center><?php _e('There are no downloads available yet', 'wp-downloadcounter'); ?></center>
<?php
		} else {
?>

<?php show_pages(); ?>

	<table class="widefat">
		<thead>
		<tr>
			<th scope="col" style="text-align: center;"><?php _e('ID', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('Name', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('URL', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('Added on', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('Downloads', 'wp-downloadcounter'); ?></th>
			<th scope="col"><?php _e('Last downloaded', 'wp-downloadcounter'); ?></th>
			<th scope="col"></th>
			<th scope="col"></th>
			<th scope="col"></th>
			<th scope="col"></th>
		</tr>
		<thead>
		<tbody id="the-list">
		<?php foreach($download_result as $download_row) { 
					$class = ($class == 'alternate') ? '' : 'alternate';
?>
		<tr id='download-<?php echo $download_row->download_id;?>' class='<?php echo $class;?>'>
			<th scope="row" style="text-align: center;"><?php echo $download_row->download_id;?></th>
			<td><?php echo $download_row->download_name;?></td>
			<td><a href="<?php echo $download_row->download_url;?>"><?php _e('Without counter', 'wp-downloadcounter'); ?></a> | <a href="<?php echo DownloadCounter::create_url($download_row->download_name);?>"><?php _e('With counter', 'wp-downloadcounter'); ?></a></td>
			<td><?php echo $download_row->download_added;?></td>
			<td><?php echo $download_row->download_count;?></td>
			<td><?php echo $download_row->download_last;?></td>
			<td><?php if (current_user_can('administrator',$download_row->download_id)) { echo "<a href='$base_page&amp;action=view&amp;download_id=" . $download_row->download_id . "' class='edit'>" . __('View', 'wp-downloadcounter') . "</a>"; } ?></td>
			<td><?php if (current_user_can('administrator',$download_row->download_id)) { echo "<a href='$base_page&amp;action=reset&amp;download_id=" . $download_row->download_id . "' class='edit'>" . __('Reset', 'wp-downloadcounter') . "</a>"; } ?></td>
			<td><?php if ( current_user_can('administrator',$download_row->download_id) ) { echo "<a href='$base_page&amp;action=edit&amp;download_id=" . $download_row->download_id . "' class='edit'>" . __('Edit', 'wp-downloadcounter') . "</a>"; } ?></td>
			<td><?php if (current_user_can('administrator', $download_row->download_id)) { echo "<a href='" . wp_nonce_url("$base_page&amp;action=delete&amp;download_id=" . $download_row->download_id, 'delete-blog_' . $download_row->download_id) . "' class='delete' onclick=\"return deleteSomething( 'download', " . $download_row->download_id . ", '" . js_escape(sprintf(__("You are about to delete this download '%s'.\n'OK' to delete, 'Cancel' to stop.\n\nWarning: deleting a download will NOT delete the original file, only the link to the file!", 'wp-downloadcounter'), $download_row->download_name)) . "');\">" . __('Delete', 'wp-downloadcounter') . "</a>";} ?></td>
		</tr>
		<?php } ?>
		</tbody>
	</table>

<?php show_pages(); ?>

<?php
		}
?>
	<div align="center">
		<form method="get" action="edit.php">
			<input type="hidden" name="page" value="<?php echo $base_name; ?>" />
			<input type="hidden" name="action" value="add" />
			<p class="submit"><input type="submit" class="button" value="<?php _e('Add download', 'wp-downloadcounter'); ?> &raquo;" /></p>
		</form>
	</div>
</div>