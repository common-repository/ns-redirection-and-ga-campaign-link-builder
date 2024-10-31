<?php
/*
	Plugin Name: NS Redirection and GA Campaign Link Builder
	Plugin URI: http://neversettle.it
	Description: Easily create Google Analytics Campaign URLs with all the proper parameters and wire them to friendly URLs for use in landing pages, newsletters, plugins, forums, etc. with built in redirection all in one plugin!  
	Text Domain: ns-redirection-campaign-builder
	Author: Never Settle
	Author URI: http://neversettle.it
	Version: 1.0.1
	Tested up to: 4.9.8
	License: GPLv2 or later
*/

/*
	Copyright 2014 Never Settle (email : dev@neversettle.it)
	
	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly!
}

require_once(plugin_dir_path(__FILE__).'ns-sidebar/ns-sidebar.php');

// TODO: rename this class
class ns_redirection_campaign_builder {
	
	var $path;              // path to plugin dir
	var $wp_plugin_page;    // url to plugin page on wp.org
	var $wp_plugin_slug;    // slug on wp.org
	var $ns_plugin_page;    // url to pro plugin page on ns.it
	var $ns_plugin_name;    // friendly name of this plugin for re-use throughout
	var $ns_plugin_slug;    // slug name of this plugin for re-use throughout
	var $social_desc;       // title for social sharing buttons
	var $admin_notices;     // html string of notification(s) to display in admin area
	var $table;             // name of custom plugin table for storing redirects
	var $db_version;        // version of plugin database schema
	
	function __construct(){
		global $wpdb;
		$this->path = plugin_dir_path( __FILE__ );
		// TODO: update to actual
		$this->wp_plugin_page = "http://wordpress.org/plugins/ns-redirection-and-ga-campaign-link-builder/";
		$this->wp_plugin_slug = "ns-redirection-and-ga-campaign-link-builder";
		// TODO: update to link builder generated URL or other public page or redirect
		$this->ns_plugin_page = "http://neversettle.it/";
		$this->ns_plugin_name = "NS Redirection and GA Campaign Link Builder";
		$this->ns_plugin_shortname = "NS Link Builder";
		$this->ns_plugin_slug = "ns-redirection-campaign-builder";
		$this->ns_plugin_ref = "ns_redirection_campaign_builder";		
		$this->db_table = $wpdb->prefix . $this->ns_plugin_ref;
		$this->db_version = "1.0";
		
		add_action( 'plugins_loaded', array($this, 'setup_plugin') );
		add_action( 'admin_init', array($this,'perform_actions') );
		add_action( 'admin_enqueue_scripts', array($this, 'admin_assets') );
		add_action( 'admin_menu', array($this, 'register_settings_page') );
		add_action( 'parse_request', array($this,'do_redirect') );

		if( get_option($this->ns_plugin_ref.'_db_version') != $this->db_version ){
			$this->configure_db();
		}
	}

	function configure_db(){
		global $wpdb;
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );		
		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
		  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}
		if ( ! empty( $wpdb->collate ) ) {
		  $charset_collate .= " COLLATE {$wpdb->collate}";
		}
		$sql = "CREATE TABLE $this->db_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			friendly_url varchar(255) NOT NULL,
			campaign_url varchar(255) NOT NULL,
			hits bigint(20) DEFAULT 0 NOT NULL,
			active bool DEFAULT TRUE NOT NULL,
			UNIQUE KEY id (id)
		) $charset_collate;";
		
		dbDelta( $sql );
		update_option( $this->ns_plugin_ref.'_db_version', $this->db_version );
	}
	
	/*********************************
	 * NOTICES & LOCALIZATION
	 */
	 
	 function setup_plugin(){
	 	load_plugin_textdomain( $this->ns_plugin_slug, false, $this->path."lang/" ); 
	 }

	function admin_assets($page){
		global $wpdb;
	 	wp_register_style( $this->ns_plugin_slug, plugins_url("css/ns-custom.css",__FILE__), false, '1.0.0.2' );
	 	wp_register_script( $this->ns_plugin_slug, plugins_url("js/ns-custom.js",__FILE__), array('jquery','jquery-ui-tooltip'), '1.0.0.2', true );
	 	wp_localize_script( $this->ns_plugin_slug, 'ns_link_builder_data', array(
	 		'explain_copy_msg' => __('Success! Values from this redirect have been pre-filled into the "Add Redirect" form below so all you need to do is tweak those with any changes you want to make and hit the "Save" button to save the copy.'),
	 		'confirm_delete_msg' => __('Are you sure you want to delete this redirect? Any links in emails, blog posts, etc. which used this friendly link will now be broken.',$this->ns_plugin_ref),
	 		'missing_utm_msg' => __('Oops, you still need to enter a campaign',$this->ns_plugin_ref),
	 		'missing_friendly_url_msg' => __('Oops, you still need to enter an SEO-friendly URL',$this->ns_plugin_ref),
	 		'used_friendly_url_msg' => __('It looks like you have already set up a redirect for that friendly URL.',$this->ns_plugin_ref),
	 		'used_friendly_urls' => array_map( 'untrailingslashit', $wpdb->get_col("SELECT friendly_url FROM $this->db_table") )
		));
		if( strpos($page, $this->ns_plugin_ref) !== false  ){
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_style( $this->ns_plugin_slug );
			wp_enqueue_script( 'thickbox' );
			wp_enqueue_script( $this->ns_plugin_slug );
		}		
	}
	
	/**********************************
	 * SETTINGS PAGE
	 */

	function register_settings_page(){
		add_submenu_page(
			'options-general.php',
			__($this->ns_plugin_name, $this->ns_plugin_slug),
			__($this->ns_plugin_shortname, $this->ns_plugin_slug),
			'manage_options',
			$this->ns_plugin_ref,
			array( $this, 'show_settings_page' )
		);
	}
	
	function show_settings_page(){
		global $wpdb;
		?>
		<div class="wrap">
			
			<!-- BEGIN Left Column -->
			<div class="ns-col-left">

				<h1><?php $this->plugin_image( 'logo.png', $this->ns_plugin_name ); ?></h1>
				
				<h2><?php _e('Existing Campaign Redirects',$this->ns_plugin_ref); ?></h2>
				<?php echo $this->admin_notices; ?>
				<table class="wp-list-table widefat ns-redirects-table">
					<thead>						
						<tr>
							<th colspan="5"><?php _e('Campaign Name',$this->ns_plugin_ref); ?></th>
							<th colspan="6"><?php _e('Friendly URL',$this->ns_plugin_ref); ?></th>
							<th colspan="6"><?php _e('Target URL',$this->ns_plugin_ref); ?></th>
							<th colspan="2"><?php _e('Hits',$this->ns_plugin_ref); ?></th>
							<th colspan="3"><?php _e('Duplicate',$this->ns_plugin_ref); ?></th>
							<th colspan="3"><?php _e('Delete',$this->ns_plugin_ref); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach( $wpdb->get_results("SELECT * FROM $this->db_table") as $redirect ): ?>
						<tr>
							<?php
							$delete_url = admin_url("/options-general.php?page={$this->ns_plugin_ref}&action=delete&id={$redirect->id}&nonce=".wp_create_nonce($this->ns_plugin_ref));
							$campaign_string = parse_url($redirect->campaign_url,PHP_URL_QUERY);
							parse_str( $campaign_string, $campaign_params );
							?>
							<td colspan="5"><?php echo isset($campaign_params['utm_campaign'])? $campaign_params['utm_campaign'] : ''; ?></td>
							<td colspan="6">
								<a href="<?php echo $redirect->friendly_url; ?>"><?php echo $redirect->friendly_url; ?></a>
								<div class="ns-tooltip-content">
									<strong><?php _e('Starting Friendly URL (pasted in emails, etc.)',$this->ns_plugin_ref); ?></strong>
									<hr/>
									<?php echo $redirect->friendly_url; ?>
								</div>
							</td>
							<td colspan="6">
								<a href="<?php echo $redirect->campaign_url; ?>"><?php echo $redirect->campaign_url; ?></a>
								<div class="ns-tooltip-content">
									<strong><?php _e('Final Target URL',$this->ns_plugin_ref); ?></strong>
									<hr/>
									<?php echo $redirect->campaign_url; ?>
									<hr/>
									<?php if(isset($campaign_params['utm_campaign'])): ?><?php _e('Campaign Name',$this->ns_plugin_ref); ?>: <?php echo $campaign_params['utm_campaign']; ?><br/><?php endif; ?>
									<?php if(isset($campaign_params['utm_source'])): ?><?php _e('Campaign Source',$this->ns_plugin_ref); ?>: <?php echo $campaign_params['utm_source']; ?><br/><?php endif; ?>
									<?php if(isset($campaign_params['utm_medium'])): ?><?php _e('Campaign Medium',$this->ns_plugin_ref); ?>: <?php echo $campaign_params['utm_medium']; ?><br/><?php endif; ?>
									<?php if(isset($campaign_params['utm_term'])): ?><?php _e('Campaign Term',$this->ns_plugin_ref); ?>: <?php echo $campaign_params['utm_term']; ?><br/><?php endif; ?>
									<?php if(isset($campaign_params['utm_content'])): ?><?php _e('Campaign Content',$this->ns_plugin_ref); ?>: <?php echo $campaign_params['utm_content']; ?><br/><?php endif; ?>
								</div>
							</td>
							<td colspan="2"><?php echo $redirect->hits; ?></td>
							<td colspan="3"><a class="button ns-copy-redirect"><?php _e('Duplicate',$this->ns_plugin_ref); ?></a></td>
							<td colspan="3"><a class="button ns-delete-redirect" href="<?php echo $delete_url; ?>"><?php _e('Delete',$this->ns_plugin_ref); ?></a></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				
				<h2><?php _e('Add New Campaign Redirect',$this->ns_plugin_ref); ?></h2>
				<form name="ctm" action="<?php echo admin_url("/options-general.php?page=$this->ns_plugin_ref"); ?>" method="post">
					<div class="stuffbox ns-stuffbox">
						<table border="0" cellpadding="0" cellspacing="5" style="width: 100%;" width="100%">
							<tbody style="width: 100%;">
								<tr style="width: 100%;">
									<td class="label"><strong>SEO Friendly URL</strong>: <span class="required">*</span></td>
									<td colspan="6">
										<input name="redirect[friendly_url]" size="50" id="friendly_url" type="text" value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/'; ?>" style="width: 100%;">
										<p class="description"><?php _e('This is the short, friendly, public url that you\'ll use everywhere like email campaigns, forum posts, etc.',$this->ns_plugin_ref); ?></p>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
					<p class="ns-spacer"><?php _e('Will redirect to',$this->ns_plugin_ref); ?>&dArr;</p>
					
					<input name="redirect[campaign_url]" size="70" id="campaign_url" type="text" style="width: 100%;" onclick="this.select()" placeholder="<?php _e('http:// ... this will automatically show the full campaign link as you fill out the fields below.',$this->ns_plugin_ref); ?>" readonly>	
					<div class="stuffbox ns-stuffbox">
						<table border="0" cellpadding="0" cellspacing="5" style="width: 100%;" width="100%">
							<tbody style="width: 100%;">
								<tr style="width: 100%;">
									<td class="label"><strong><?php _e('Target URL',$this->ns_plugin_ref); ?></strong>: <span class="required">*</span></td>
									<td colspan="6">
										<input name="website" size="50" tabindex="1" type="text" value="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/'; ?>" style="width: 100%;" onchange="ns_link_builder.createURL();">
										<p class="description"><?php _e('This is the final page url (without Google Analytics "utm" params) that you want visitors to end up on.',$this->ns_plugin_ref); ?></p>
									</td>
								</tr>
								<tr style="width: 100%;">
									<td class="label"><strong><?php _e('Campaign Name',$this->ns_plugin_ref); ?></strong>: <span class="required">*</span></td>
									<td><input name="utm_campaign" size="25" tabindex="2" type="text" style="width: 100%;"></td>
									<td><?php $this->plugin_image('help.png','Use to identify a specific product promotion or strategic campaign (product, promo code, sale name, etc.)','ns-help-handle'); ?></td>
									<td width="15px">&nbsp;</td><td></td><td></td><td></td>
								</tr>
								<tr style="width: 100%;">
									<td class="label"><strong><?php _e('Campaign Source',$this->ns_plugin_ref); ?></strong>: <span class="required">*</span></td>
									<td><input name="utm_source" size="25" tabindex="3" type="text" style="width: 100%;"></td>
									<td><?php $this->plugin_image('help.png','Use to identify a search engine, newsletter name, or other source (referrer, site name, affiliate name, plugin name, etc.)','ns-help-handle'); ?></td>
									<td width="15px">&nbsp;</td>
									<td class="label"><strong><?php _e('Campaign Term',$this->ns_plugin_ref); ?></strong>: </td>
									<td><input name="utm_term" size="25" tabindex="5" type="text" style="width: 100%;"></td>
									<td><?php $this->plugin_image('help.png','OPTIONAL - use to identify any paid keywords.','ns-help-handle'); ?></td>
								</tr>
								<tr style="width: 100%;">
									<?php // TODO: PRO FEATURE make this into a configurable drop-down select to enforce consistency ?>
									<td nowrap="nowrap" class="label"><strong><?php _e('Campaign Medium',$this->ns_plugin_ref); ?></strong>: <span class="required">*</span></td>
									<td><input name="utm_medium" size="25" tabindex="4" type="text" style="width: 100%;"></td>
									<td><?php $this->plugin_image('help.png','Use to identify a type of media (ad, affiliate, app, cpc, email, website, etc.)','ns-help-handle'); ?></td>
									<td width="15px">&nbsp;</td>
									<td class="label"><strong><?php _e('Campaign Content',$this->ns_plugin_ref); ?></strong>: </td>
									<td><input name="utm_content" size="25" tabindex="6" type="text" style="width: 100%;"></td>
									<td><?php $this->plugin_image('help.png','OPTIONAL - use for A/B testing and content-targeted ads (to differentiate ads or links that point to the same URL).','ns-help-handle'); ?></td>
								</tr>
							</tbody>
						</table>
					</div>	
					<input type="hidden" name="nonce" value="<?php echo wp_create_nonce($this->ns_plugin_ref); ?>" />
					<input type="hidden" name="action" value="save" />
					<?php submit_button('Save Campaign URL Redirect'); ?>
				</form>

				<h2><?php _e('Help and Guidance',$this->ns_plugin_ref); ?></h2>
				<?php _e('Where do I find my data in Google Analytics?',$this->ns_plugin_ref); ?>
				<a href="<?php echo plugins_url("/images/ga-campaigns.jpg",__FILE__); ?>" class="thickbox"><?php _e('Click HERE.',$this->ns_plugin_ref); ?></a>
				<br />
				<?php _e('Where can I learn more about Google Campaign URLs? <a href="https://support.google.com/analytics/answer/1247851?hl=en&ref_topic=1032998" target="_blank">Click HERE</a>.',$this->ns_plugin_ref); ?>				
			</div>
			<!-- END Left Column -->
						
			<!-- BEGIN Right Column -->			
			<div class="ns-col-right">
				<h3>Thanks for using <?php echo $this->ns_plugin_name; ?></h3>
				<?php ns_sidebar::widget( 'subscribe' ); ?>
				<?php ns_sidebar::widget( 'rate', array('text'=>'Has this plugin helped you out? Give back with a 5-star rating!','plugin_slug'=>$this->wp_plugin_slug) ); ?>
				<?php ns_sidebar::widget( 'share', array('plugin_url'=>'http://wordpress.org/plugins/ns-redirection-and-ga-campaign-link-builder/','plugin_desc'=>'Easily create Google Analytics Campaign URLs with all the proper parameters and wire them to friendly URLs','text'=>'Would anyone else you know enjoy NS Redirection and GA Campaign Link Builder?') ); ?>
				<?php ns_sidebar::widget( 'donate' ); ?>
				<?php ns_sidebar::widget( 'featured'); ?>
				<?php ns_sidebar::widget( 'links', array('ns-redirection') ); ?>
				<?php ns_sidebar::widget( 'random'); ?>
				<?php ns_sidebar::widget( 'support' ); ?>
			</div>
			<!-- END Right Column -->
				
		</div>
		<?php
	}
	
	/*************************************
	 * FUNCTIONALITY
	 */
	 
	function perform_actions(){
		global $wpdb;				
		// perform save/delete actions
		if( isset($_GET['page']) && $_GET['page']==$this->ns_plugin_ref && isset($_REQUEST['action']) ){
			if( !isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'],$this->ns_plugin_ref) ){
				echo '<div class="error"><p>'.__('Invalid nonce.',$this->ns_plugin_ref).'</p></div>';
			}
			else{ 
				switch( $_REQUEST['action'] ){
					case 'save':
						if( isset($_REQUEST['redirect']['friendly_url']) && isset($_REQUEST['redirect']['campaign_url']) ){
							$_REQUEST['redirect']['friendly_url'] = untrailingslashit($_REQUEST['redirect']['friendly_url']);
							$wpdb->insert( $this->db_table, $_REQUEST['redirect'] );
							$this->admin_notices .= '<div class="updated"><p>'.__('Redirect successfully saved.',$this->ns_plugin_ref).'</p></div>';
						}
						break;
					case 'delete':
						if( isset($_REQUEST['id']) ){
							$wpdb->delete( $this->db_table, array('id'=>$_REQUEST['id']) );
							$this->admin_notices .= '<div class="updated"><p>'.__('Redirect removed.',$this->ns_plugin_ref).'</p></div>';
						}
						break;
				}
			}
		}
	}
	
	function do_redirect(){
		global $wpdb;
		$current_url = untrailingslashit( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']?'https://':'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$redirect_data = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $this->db_table WHERE friendly_url=%s",$current_url) );
		if( !is_null($redirect_data) ){
			$wpdb->update( $this->db_table, array('hits'=>$redirect_data->hits+1), array('id'=>$redirect_data->id) );
			wp_redirect( $redirect_data->campaign_url, 301 );
			exit;
		}
	}
	
	/*************************************
	 * UITILITY
	 */
	 
	 function plugin_image( $filename, $alt='', $class='' ){
	 	echo "<img src='".plugins_url("/images/$filename",__FILE__)."' alt='$alt' title='$alt' class='$class' />";
	 }
	 
	 function array_value( $key, $array ){
	 	return $array[$key];
	 }
	
}

new ns_redirection_campaign_builder();
