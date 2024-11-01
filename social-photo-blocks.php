<?php
/*
*	Plugin Name: Social photo blocks
*	Plugin URI:  http://wordpress.org/plugins/social-photo-grid
*	Description: Plugin provides gutenberg block, shortcode & widget of recent photos from your social accounts.
*	Author: Sergiy Dzysyak
*	Version: 1.2
*	Author URI: http://erlycoder.com/
*	Text Domain: social-photo-blocks
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}


if( !class_exists('Social_Photo_Blocks') ){
	class Social_Photo_Blocks {
		/**
		 * Constructor
		 * Sets hooks, actions, shortcodes, filters.
		 *
		 */
		function __construct(){
			$this->redirect_uri = plugin_dir_url( __FILE__ )."oauth_redirect.php";
			
			if(class_exists('Memcache')){ 
				$this->mc = new Memcache; 
				
				$mc_server = (defined('MEMCACHE_SERVER'))?MEMCACHE_SERVER:'localhost';
				$mc_port = (defined('MEMCACHE_PORT'))?MEMCACHE_PORT:11211;
				$this->mc->addServer($mc_server, $mc_port); 
			}
			
			load_plugin_textdomain( 'social-photo-blocks', false, basename( __DIR__ ) . '/languages' );

			add_filter( 'cron_schedules', array($this, 'custom_time_cron') );
			
			add_action( 'token_renew_hook', array($this, 'refreshTockenLong'), 10);
			if (!wp_next_scheduled('token_renew_hook')) {
				wp_schedule_event( time(), 'every_month', 'token_renew_hook');
			}

			add_action( 'init', array( $this, 'init_scripts_and_styles' ) );
			register_activation_hook( __FILE__, [$this, 'plugin_install']);
			register_deactivation_hook( __FILE__, [$this, 'plugin_uninstall']);
			
			add_filter('get_media_list', array($this, 'get_media_list'), 10, 2);
			add_shortcode( 'sp_grid', array($this, 'Social_Photo_Grid_shortcode'));
			add_shortcode( 'sp_slider', array($this, 'Social_Photo_Slider_shortcode'));
			
			add_action( 'enqueue_block_assets', array($this, 'block_assets') );
			
			if(is_admin()){
				add_action('admin_init', array($this, 'admin_init'));
				add_action('admin_menu', array( $this, 'plugin_setup_menu'));
				$plugin = plugin_basename( __FILE__ );
				add_filter( "plugin_action_links_$plugin", array( $this, 'plugin_add_settings_link') );
				add_action( 'wp_ajax_cache_refresh', array( $this, 'refresh_lists_cache') );
				add_action( 'wp_ajax_renew_token', array( $this, 'renew_token_manually') );
			}else{
				//add_action( 'wp_enqueue_scripts', array($this, 'plugin_styles') );
			}
		}

		/**
		 * Create schedule interval in 1 month.
		 */
		function custom_time_cron( $schedules ) {

			$schedules['every_month'] = array(
					'interval'  => 2419200, //2419200 seconds in 4 weeks
					'display'   => esc_html__( 'Every Month', 'social-photo-blocks' )
			);
		
			return $schedules;
		}
		
		/**
		 * Plugin settings link.
		 * 
		 */
		function plugin_add_settings_link( $links ) {
			$settings_link = '<a href="options-general.php?page=Social_Photo_Blocks">' . __( 'Settings', 'social-photo-blocks') . '</a>';
			array_push( $links, $settings_link );
		  	return $links;
		}
		
		/**
		 * Plugin menu options.
		 * 
		 */
		function plugin_setup_menu(){
			add_options_page( __("Social photo blocks", 'social-photo-blocks'), __("Social photo blocks", 'social-photo-blocks'),  "manage_options", "Social_Photo_Blocks", array($this, "plugin_settings"));
		}

		/**
		 * Method is calling Instagramm api, receiving access token and saves it to database. Token is active for 1 hour only.
		 */
		private function getTockenShort(){
			$apiData = array(
				'client_id'       => get_option('InstagramClientID'),
				'client_secret'   => get_option('InstagramClientSecret'),
				'grant_type'      => 'authorization_code',
				'redirect_uri'    => $this->redirect_uri,
				'code'            => $_GET['code'] // Value is passed to external API and do not require validation or sanitization
			  );

			  $apiHost = 'https://api.instagram.com/oauth/access_token';

			  $args = array(
				  'body' => $apiData,
				  'timeout' => '5',
				  'redirection' => '5',
				  'httpversion' => '1.0',
				  'blocking' => true,
				  'headers' => array('Accept'=>'application/json'),
				  'cookies' => array()
			  );

			  $jsonData = wp_remote_retrieve_body(wp_remote_post( $apiHost, $args ));

			  $user = @json_decode($jsonData, true);

			  if(!empty($user['access_token'])){ 
				update_option('InstagramAccessToken', $user['access_token']); 
				update_option('InstagramUserId', $user['user_id']); 

				return ['result'=>'ok'];
			  }else{
				return ['result'=>'error'];
			  }
		}

		/**
		 * Method calls Instagram API to get long living token. Token is active for 60 days.
		 */
		private function getTokenLong(){
			$_secret = get_option('InstagramClientSecret');
			$_token = get_option('InstagramAccessToken');
			$url = "https://graph.instagram.com/access_token?grant_type=ig_exchange_token&client_secret={$_secret}&access_token={$_token}";
			$jsonData = wp_remote_retrieve_body(wp_remote_get($url));

			$token = @json_decode($jsonData, true);

			if(!empty($token['access_token'])){ 
				update_option('InstagramAccessTokenLong', $token['access_token']); 

				return ['result'=>'ok', 'token'=>$token['access_token']];
			}else{
				return ['result'=>'error'];
			}			
		}

		/**
		 * Method calls Instagram API to refresh logn living token. Should be refreshed at least once in 60 days.
		 */
		public function refreshTockenLong(){
			$_token = get_option('InstagramAccessTokenLong');
			$url = "https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token={$_token}";
			$jsonData = wp_remote_retrieve_body(wp_remote_get($url));

			$token = @json_decode($jsonData, true);

			if(!empty($token['access_token'])){ 
				update_option('InstagramAccessTokenLong', $token['access_token']); 

				return ['result'=>'ok', 'token'=>$token['access_token']];
			}else{
				return ['result'=>'error'];
			}			
		}

		/**
		 * Plugin settings page. Includes basic settings and layout options.
		 * 
		 */
		function plugin_settings(){
			global $wpdb;

			// $_GET['code'] value is passed to external API and do not require validation or sanitization
			if(!empty($_GET['code'])){
				$res = $this->getTockenShort();

				if($res['result'] == 'ok'){
					$res = $this->getTokenLong();

					if($res['result'] == 'ok'){
						?><meta http-equiv="refresh" content="0; url=options-general.php?page=Social_Photo_Blocks&tab=config" /><?php
						exit();
					}else{
						?>
						<div class="error notice">
							<p><?php _e( 'There has been an error during the authorization', 'social-photo-blocks' ); ?></p>
						</div>
						<?php
					}
				}else{
					?>
					<div class="error notice">
						<p><?php _e( 'There has been an error during the authorization', 'social-photo-blocks' ); ?></p>
					</div>
					<?php
				}
			}
			
			//  $_GET[ 'tab' ] content is compared to constant string value/values lower in the code and do not require any validation or sanitization. 
			$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'config';
			
			?>
	<!-- Create a header in the default WordPress 'wrap' container -->
	<div class="wrap">
	 
		<h1><?php _e("Social photo blocks", 'social-photo-blocks'); ?></h1>
		
		<h2 class="nav-tab-wrapper">
		    <a href="?page=Social_Photo_Blocks&tab=config" class="nav-tab <?php echo $active_tab == 'config' ? 'nav-tab-active' : ''; ?>"><?php _e("Configuration", 'social-photo-blocks'); ?></a>
		</h2>
		
		<?php settings_errors('insta-grid-plugin-config-group'); ?>

		<form method="post" action="options.php">
		<?php if( $active_tab == 'config' ) { ?>
		<?php settings_fields( 'insta-grid-plugin-config-group' ); ?>
		<?php do_settings_sections( 'insta-grid-plugin-config-group' ); ?>
		<h2><?php _e("Instagram account settings", 'social-photo-blocks'); ?></h2>
		<p><?php //_e("For security reasons we suggest to define these valuse in your wp-config.php, as follows:", 'social-photo-blocks'); ?></p>
		<p><?php _e("Follow", 'social-photo-blocks'); ?> <a href="https://developers.facebook.com/docs/instagram-basic-display-api/getting-started" target="_blank"><?php _e("instructions", 'social-photo-blocks'); ?></a> <?php _e("to create facebook graph application and connect it to your Instagram account", 'social-photo-blocks'); ?>.</p>
		<p><?php _e("You should define the following OAuth redirect URI while configuring", 'social-photo-blocks'); ?> <?php _e("Instagram Basic Display", 'social-photo-blocks'); ?>:<br/>
		<code>
			<?php echo $this->redirect_uri; ?>
		</code>
		</p>
		<table class="form-table">
			<tr>
				<td width="15%"><?php _e("Instagram App ID", 'social-photo-blocks'); ?></td>
				<td width="20%"><input type="text" name="InstagramClientID" id="InstagramClientID" placeholder="<?php _e("Client ID", 'social-photo-blocks'); ?>" value="<?php echo esc_attr( get_option('InstagramClientID') ); ?>"/></td>
				<td><?php _e("Can be created from Facebook application/Products/Instagram/Basic Display", 'social-photo-blocks'); ?>.</td>
		    </tr>
		    <tr>
				<td width="15%"><?php _e("Instagram App Secret", 'social-photo-blocks'); ?></td>
				<td><input type="text" name="InstagramClientSecret" id="InstagramClientSecret" placeholder="<?php _e("Client Secret", 'social-photo-blocks'); ?>" value="<?php echo esc_attr( get_option('InstagramClientSecret') ); ?>"/></td>
				<td><?php _e("Same as above", 'social-photo-blocks'); ?>.</td>
		    </tr>
		    <?php 
		    	
		    if((!empty(get_option('InstagramClientID')))&&(get_option('InstagramClientSecret'))){
				$url = "https://www.instagram.com/oauth/authorize?client_id=".get_option('InstagramClientID')."&redirect_uri=".urlencode($this->redirect_uri)."&scope=user_profile,user_media&response_type=code";
		    ?>
			<?php if(!empty(get_option("InstagramAccessToken"))){ ?>
			<tr>
				<td width="15%"><?php _e("Instagram Access Token", 'social-photo-blocks'); ?></td>
				<td><input readonly="readonly" type="text" name="InstagramAccessToken" id="InstagramAccessToken" placeholder="<?php _e("Access Token", 'social-photo-blocks'); ?>" value="<?php echo esc_attr( get_option('InstagramAccessTokenLong') ); ?>"/></td>
				<td><?php _e("Active access token", 'social-photo-blocks'); ?>.</td>
		    </tr>
			<tr>
				<td width="15%"><?php _e("Instagram User Id", 'social-photo-blocks'); ?></td>
				<td><input readonly="readonly" type="text" name="InstagramUserId" id="InstagramUserId" placeholder="<?php _e("User Id", 'social-photo-blocks'); ?>" value="<?php echo esc_attr( get_option('InstagramUserId') ); ?>"/></td>
				<td><?php _e("Active User Id", 'social-photo-blocks'); ?>.</td>
		    </tr>
		    <?php } ?>
		    <tr>
				<td colspan="2"><a href="<?php echo $url;?>"><?php _e("Login With Instagram", 'social-photo-blocks'); ?></a></td>
				<td><?php _e("Login to get or refresh Access Token and enable front-end", 'social-photo-blocks'); ?>.</td>
		    </tr>

			<tr>
				<td width="15%"><?php _e("Renew Instagram long living token", 'social-photo-blocks'); ?></td>
				<td><button type="button" onclick="javascript: jQuery.post(ajaxurl, {action: 'renew_token'}, function(response) { if(response['result']=='ok'){ jQuery('#InstagramAccessToken').val(response['token']); jQuery('#token_ok').show().delay('5000').fadeOut('slow'); } }); "><?php _e("Renew token", 'social-photo-blocks'); ?></a></button> <span id="token_ok" style="color: green; display: none;"> <?php _e("Token renewed", 'social-photo-blocks'); ?></span></td>
				<td><?php _e("Token is renewed once a month and active for 60 days", 'social-photo-blocks'); ?>.</td>
		    </tr>
		    
		    <tr>
				<td width="15%"><?php _e("Refresh list of Instagram images", 'social-photo-blocks'); ?></td>
				<td><button type="button" onclick="javascript: jQuery.post(ajaxurl, {action: 'cache_refresh'}, function(response) { if((response=='ok0')||(response=='ok')){ jQuery('#status_ok').show().delay('5000').fadeOut('slow'); } }); "><?php _e("Refresh cache", 'social-photo-blocks'); ?></a></button> <span id="status_ok" style="color: green; display: none;"> <?php _e("Cache refreshed", 'social-photo-blocks'); ?></span></td>
				<td><?php _e("List is downloaded from Instagram once per hour", 'social-photo-blocks'); ?>.</td>
		    </tr>
		    
		    <tr>
				<td colspan="3">
					<?php _e("Grid short code example", 'social-photo-blocks'); ?>:<br/>
					<code>[sp_grid cols='3' rows='3' width='100%' align='center']</code>
				</td>
		    </tr>
			<tr>
				<td colspan="3">
					<?php _e("Slider short code example", 'social-photo-blocks'); ?>:<br/>
					<code>[sp_slider width='50%' height='400px' align='center' autostart='1' loop='1' total="10" delay='5']</code>
				</td>
		    </tr>
		    <?php } ?>
		</table>
		<?php } ?>
		
		
		<?php submit_button(); ?>

	</form>
		 
	</div><!-- /.wrap -->
	<?php 
		}
		
		/**
		 * Plugin save settings.
		 * 
		 */
		public static function admin_init() {
			register_setting( 'insta-grid-plugin-config-group', 'InstagramClientID');
			register_setting( 'insta-grid-plugin-config-group', 'InstagramClientSecret');
		}
		
		/**
		 * Enqueue block styles.
		 *
		 */		
		function block_assets(){
			wp_enqueue_style(
				'social-photo-blocks-plugin/social-photo-grid-plugin-editor',
				plugins_url( 'accets/basic.css', __FILE__ ),
				array( 'wp-edit-blocks' )
			);

			/*
			wp_enqueue_style(
				'insta-grid-plugin/insta-slider-plugin-editor',
				plugins_url( 'accets/slider.css', __FILE__ ),
				array( 'wp-edit-blocks' )
			);
			*/
		}
		
		/**
		 * Init plugin. Init scripts, styles and blocks.
		 */		
		function init_scripts_and_styles(){
			wp_register_script(
				'social_photo-slider',
				plugins_url( 'js/social-photo-slider.js', __FILE__ )
			);
			
			wp_enqueue_script('social_photo-slider');


			wp_register_script(
				'social-photo-blocks',
				plugins_url( 'js/blocks.js', __FILE__ ),
				array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-edit-post', 'wp-data', 'wp-editor' )
			);
			
			if ( function_exists( 'register_block_type' ) ){
				register_block_type('social-photo-blocks/social-photo-grid', array(
					'editor_script' => 'social-photo-blocks',
					//'editor_style'  => 'social-photo-blocks-editor-style',
					//'style'         => 'social-photo-blocks-frontend-style',
					'render_callback' => [$this, 'Social_Photo_Grid_shortcode'],
					'attributes' => array(
						'cols' => array(
							'type' => 'string'
						),
						'rows' => array(
							'type' => 'string'
						),
						'width' => array(
							'type' => 'string'
						),
						'align' => array(
							'type' => 'string'
						)
					)
				) );
			}
			
			if ( function_exists( 'register_block_type' ) ) {
				register_block_type('social-photo-blocks/social-photo-slider', array(
					'editor_script' => 'social-photo-blocks',
					//'editor_style'  => 'social-photo-blocks-editor-style',
					//'style'         => 'social-photo-blocks-frontend-style',
					'render_callback' => [$this, 'Social_Photo_Slider_shortcode'],
					'attributes' => array(
						'loop' => array(
							'type' => 'bool'
						),
						'autostart' => array(
							'type' => 'bool'
						),
						'height' => array(
							'type' => 'string'
						),
						'width' => array(
							'type' => 'string'
						),
						'delay' => array(
							'type' => 'integer'
						),
						'total' => array(
							'type' => 'integer'
						),
						'align' => array(
							'type' => 'string'
						)
					)
				) );
			}
			
			if ( function_exists( 'wp_set_script_translations' ) ) {
				wp_set_script_translations( 'social-photo-blocks', 'social-photo-blocks' );
			}
			
		}
		
		/**
		 * Get cached Instagram data or reload it from Instagram API.
		 *
		 * @param string $name - name of the dataset
		 * @param string $url - API call url
		 */		
		private function getCached($name, $url){
			if(class_exists('Memcache')){ 
				$json = $this->mc->get($name);
				if(empty($json)){
					$json = wp_remote_retrieve_body(wp_remote_get($url));
					$this->mc->set($name, $json, 0, 900);
				}
				
				return $json;
			}else{
				$path = wp_upload_dir()['basedir']."/cache/{$name}.json";
				
				if((!file_exists($path)) || ((time()-filemtime($path))>900)){
					$json = wp_remote_retrieve_body(wp_remote_get($url));
					file_put_contents($path, $json);
					chmod($path, 0666);
				}else{
					$json = file_get_contents($path);
				}
				
				return $json;
			}
		}
		
		/**
		 * Get instagam data list.
		 *
		 * @param string $view_type - name of the dataset.
		 * @param string $keyword - not in use yet.
		 * @return string - JSON of the dataset.
		 */		
		function get_media_list($view_type, $keyword=''){
			switch($view_type){
			case 'recent':
				$url = "https://graph.instagram.com/me/media?fields=id,caption,media_url,thumbnail_url,media_type,permalink&limit=50&access_token=".get_option('InstagramAccessTokenLong');
				$json = $this->getCached("Social_Photo_Grid_recent", $url);
			
				return $json;
			break;
			default:
				return 'error';
			}
			
		}

		/**
		 * AJAX call from admin interface refreshes Instagram data cache.
		 * 
		 */
		function renew_token_manually(){
			$res = $this->refreshTockenLong();
			
			header('Content-Type: application/json');
			echo json_encode($res);
			exit();
		}
		
		/**
		 * AJAX call from admin interface refreshes Instagram data cache.
		 * 
		 */
		function refresh_lists_cache(){
			apply_filters('get_media_list', 'recent');
			
			echo "ok";
			exit();
		}
		
		/**
		 * Plugin short code rendering - grid layout.
		 * Block code server-side rendering.
		 * 
		 * @param array $attrs - short code attributes.
		 * @return string - Rendered HTML code.
		 */
		function Social_Photo_Grid_shortcode($attrs){
			$cfg = shortcode_atts( array(
				'cols' => '3',
				'rows' => '3',
				'width' => '100%',
				'align' => 'center',
			), $attrs );
			
			switch($cfg['align']){
			case 'left':
				$cfg['align'] = "0 auto 0 0";
			break;
			case 'right':
				$cfg['align'] = "0 0 0 auto";
			break;
			case 'center':
			default:
				$cfg['align'] = "0 auto";
			}
			
			$res = apply_filters('get_media_list', 'recent');
			
			$rows = json_decode($res, true)['data'];
			
			$images = array(); $count = 0; $tot = $cfg['rows']*$cfg['cols'];
			if(is_array($rows)) foreach($rows as $row) if($row['media_type']=='IMAGE'){
				$images[] = array('media_url'=>$row['media_url'], 'title'=>@$row['caption'], 'type'=> @$row['media_type'], 'link'=>@$row['permalink']);
				$count++;
				if($count>=$tot){  break; }
			}
			
			ob_start();
			include plugin_dir_path(__FILE__)."/tpl/recent_basic.php";

			return ob_get_clean();
		}
		
		/**
		 * Plugin short code rendering - slider layout.
		 * Block code server-side rendering.
		 * 
		 * @param array $attrs - short code attributes.
		 * @return string - Rendered HTML code.
		 */
		function Social_Photo_Slider_shortcode($attrs){
			$cfg = shortcode_atts( array(
				'autostart' => '1',
				'loop' => '1',
				'width' => '100%',
				'height' => '400px',
				'effects' => 'center',
				'delay' => '5',
				'total' => 10,
				'align' => 'center',
			), $attrs );

			switch($cfg['align']){
				case 'left':
					$cfg['align'] = "0 auto 0 0";
				break;
				case 'right':
					$cfg['align'] = "0 0 0 auto";
				break;
				case 'center':
				default:
					$cfg['align'] = "0 auto";
				}
			
			$res = apply_filters('get_media_list', 'recent');
			
			$rows = json_decode($res, true)['data'];
			
			$images = array(); $count = 0;
			if(is_array($rows)) foreach($rows as $row) if($row['media_type']=='IMAGE'){
				$images[] = array('media_url'=>$row['media_url'], 'title'=>@$row['caption'], 'type'=> @$row['media_type'], 'link'=>@$row['permalink']);
				$count++;
				if($count>=$cfg['total']){  break; }
			}
			
			ob_start();
			include plugin_dir_path(__FILE__)."/tpl/recent_slider.php";

			return ob_get_clean();
		}
		
		/**
		 * Plugin install routines. Check for dependencies.
		 * 
		 * Installation routines.
		 */
		public function plugin_install() {
			if(!is_dir(wp_upload_dir()['basedir']."/cache")){
				if(is_writable(wp_upload_dir()['basedir'])){
					mkdir(wp_upload_dir()['basedir']."/cache");
					chmod(wp_upload_dir()['basedir']."/cache", 0777);
				}else{
					wp_die('Sorry, but this plugin requires /wp-content/uploads folder exist and be writable.');
				}
			}
		}
		
		public function plugin_uninstall() {
		}
	}
	
	$social_photo_blocks_init = new Social_Photo_Blocks();
}

class Social_Photo_Grid_Widget extends WP_Widget {

	/**
	 * Sets up a new Instagram Grid widget instance.
	 *
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'Social_Photo_Grid_Widget',
			'description' => __( 'Social photo grid.' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'Social_Photo_Grid_Widget', __( 'Social photo grid' ), $widget_ops );
		$this->alt_option_name = 'Social_Photo_Grid_Widget';
	}

	/**
	 * Outputs the content for the current widget instance.
	 *
	 * @param array $args     Display arguments including 'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$Social_Photo_Grid_rows = ( ! empty( $instance['Social_Photo_Grid_rows'] ) ) ? absint( $instance['Social_Photo_Grid_rows'] ) : 5;
		$Social_Photo_Grid_cols = ( ! empty( $instance['Social_Photo_Grid_cols'] ) ) ? absint( $instance['Social_Photo_Grid_cols'] ) : 5;
		
		
		echo $args['before_widget'];
		if(!empty($instance['Social_Photo_Grid_title'])) echo '<h4 class="widget-title">'.$instance['Social_Photo_Grid_title'].'</h4>';
		echo do_shortcode("[Social_Photo_Grid cols='{$Social_Photo_Grid_cols}' rows='{$Social_Photo_Grid_rows}' width='100%' align='center']");	
		
		echo $args['after_widget'];
	}

	/**
	 * Handles updating the settings for the current widget instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['Social_Photo_Grid_rows'] = (int) $new_instance['Social_Photo_Grid_rows'];
		$instance['Social_Photo_Grid_cols'] = (int) $new_instance['Social_Photo_Grid_cols'];
		$instance['Social_Photo_Grid_title'] = (string) $new_instance['Social_Photo_Grid_title'];
		return $instance;
	}

	/**
	 * Outputs the settings form for the widget.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$cols    = isset( $instance['Social_Photo_Grid_cols'] ) ? absint( $instance['Social_Photo_Grid_cols'] ) : 3;
		$rows    = isset( $instance['Social_Photo_Grid_rows'] ) ? absint( $instance['Social_Photo_Grid_rows'] ) : 3;
		$title    = isset( $instance['Social_Photo_Grid_title'] ) ? $instance['Social_Photo_Grid_title'] : "Instagram";
?>
		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_title' ); ?>"><?php _e( 'Title:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_title' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_title' ); ?>" type="text" value="<?php echo $title; ?>"/></p>

		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_cols' ); ?>"><?php _e( 'Number of columns to show:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_cols' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_cols' ); ?>" type="number" step="1" min="1" value="<?php echo $cols; ?>" size="3" /></p>
		
		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_rows' ); ?>"><?php _e( 'Number of rows to show:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_rows' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_rows' ); ?>" type="number" step="1" min="1" value="<?php echo $rows; ?>" size="3" /></p>


<?php
	}
}

class Social_Photo_Slide_Widget extends WP_Widget {

	/**
	 * Sets up a new Instagram Slider widget instance.
	 *
	 */
	public function __construct() {
		$widget_ops = array(
			'classname' => 'Social_Photo_Slide_Widget',
			'description' => __( 'Social photo slider.' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'Social_Photo_Slide_Widget', __( 'Social photo slider' ), $widget_ops );
		$this->alt_option_name = 'Social_Photo_Slide_Widget';
	}

	/**
	 * Outputs the content for the current widget instance.
	 *
	 * @param array $args     Display arguments including 'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! isset( $args['widget_id'] ) ) {
			$args['widget_id'] = $this->id;
		}

		$cfg = shortcode_atts( array(
			'autostart' => '1',
			'loop' => '1',
			'width' => '100%',
			'height' => '400px',
			'effects' => 'center',
			'delay' => '5',
			'total' => 10,
		), $attrs );

		$insta_autostart = ( ! empty( $instance['Social_Photo_Slide_autostart'] ) ) ? absint( $instance['Social_Photo_Slide_autostart'] ) : '1';
		$insta_loop = ( ! empty( $instance['Social_Photo_Slide_loop'] ) ) ? absint( $instance['Social_Photo_Slide_loop'] ) : '1';
		$insta_width = ( ! empty( $instance['Social_Photo_Slide_width'] ) ) ? absint( $instance['Social_Photo_Slide_width'] ) : '100%';
		$insta_height = ( ! empty( $instance['Social_Photo_Slide_height'] ) ) ? absint( $instance['Social_Photo_Slide_height'] ) : '400px';
		$insta_effects = ( ! empty( $instance['Social_Photo_Slide_effects'] ) ) ? absint( $instance['Social_Photo_Slide_effects'] ) : 'center';
		$insta_delay = ( ! empty( $instance['Social_Photo_Slide_delay'] ) ) ? absint( $instance['Social_Photo_Slide_delay'] ) : '5';
		$insta_total = ( ! empty( $instance['Social_Photo_Slide_total'] ) ) ? absint( $instance['Social_Photo_Slide_total'] ) : '10';
		
		
		echo $args['before_widget'];
		if(!empty($instance['Social_Photo_Slide_title'])) echo '<h4 class="widget-title">'.$instance['Social_Photo_Slide_title'].'</h4>';
		echo do_shortcode("[Social_Photo_Slider autostart='{$insta_autostart}' loop='{$insta_loop}' width='{$insta_width}' height='{$insta_height}' effects='{$insta_effects}' delay='{$insta_delay}' total='{$insta_total}']");	
		
		echo $args['after_widget'];
	}

	/**
	 * Handles updating the settings for the current widget instance.
	 *
	 * @param array $new_instance New settings for this instance as input by the user via WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings to save.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['Social_Photo_Slide_autostart'] = (int) $new_instance['Social_Photo_Slide_autostart'];
		$instance['Social_Photo_Slide_loop'] = (int) $new_instance['Social_Photo_Slide_loop'];
		$instance['Social_Photo_Slide_delay'] = (int) $new_instance['Social_Photo_Slide_delay'];
		$instance['Social_Photo_Slide_total'] = (int) $new_instance['Social_Photo_Slide_total'];

		$instance['Social_Photo_Slide_title'] = (string) $new_instance['Social_Photo_Slide_title'];
		$instance['Social_Photo_Slide_width'] = (string) $new_instance['Social_Photo_Slide_width'];
		$instance['Social_Photo_Slide_height'] = (string) $new_instance['Social_Photo_Slide_height'];
		$instance['Social_Photo_Slide_effects'] = (string) $new_instance['Social_Photo_Slide_effects'];
		return $instance;
	}

	/**
	 * Outputs the settings form for the widget.
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$autostart = ( ! empty( $instance['Social_Photo_Slide_autostart'] ) ) ? absint( $instance['Social_Photo_Slide_autostart'] ) : '1';
		$loop = ( ! empty( $instance['Social_Photo_Slide_loop'] ) ) ? absint( $instance['Social_Photo_Slide_loop'] ) : '1';
		$width = ( ! empty( $instance['Social_Photo_Slide_width'] ) ) ? absint( $instance['Social_Photo_Slide_width'] ) : '100%';
		$height = ( ! empty( $instance['Social_Photo_Slide_height'] ) ) ? absint( $instance['Social_Photo_Slide_height'] ) : '400px';
		$effects = ( ! empty( $instance['Social_Photo_Slide_effects'] ) ) ? absint( $instance['Social_Photo_Slide_effects'] ) : 'center';
		$delay = ( ! empty( $instance['Social_Photo_Slide_delay'] ) ) ? absint( $instance['Social_Photo_Slide_delay'] ) : '5';
		$total = ( ! empty( $instance['Social_Photo_Slide_total'] ) ) ? absint( $instance['Social_Photo_Slide_total'] ) : '10';

		$title    = isset( $instance['Social_Photo_Slide_title'] ) ? $instance['Social_Photo_Slide_title'] : "Instagram";
?>
		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_title' ); ?>"><?php _e( 'Title:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_title' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_title' ); ?>" type="text" value="<?php echo $title; ?>"/></p>

		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_width' ); ?>"><?php _e( 'Slider width:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_width' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_width' ); ?>" type="text" value="<?php echo $width; ?>"/></p>

		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_height' ); ?>"><?php _e( 'Slider height:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_height' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_height' ); ?>" type="text" value="<?php echo $height; ?>"/></p>

		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_autostart' ); ?>"><?php _e( 'Autostart slider:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_autostart' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_autostart' ); ?>" type="checkbox" value="1" <?php if($autostart) echo "checked='checked'"; ?>/></p>

		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Grid_loop' ); ?>"><?php _e( 'Loop the slider:' ); ?></label><br/>
		<input class="widefat" id="<?php echo $this->get_field_id( 'Social_Photo_Grid_loop' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Grid_loop' ); ?>" type="checkbox" value="1" <?php if($autostart) echo "checked='checked'"; ?>/></p>

		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Slide_delay' ); ?>"><?php _e( 'Slides delay:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'Social_Photo_Slide_delay' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Slide_delay' ); ?>" type="number" step="1" min="1" value="<?php echo $delay; ?>" size="3" /></p>
		
		<p><label for="<?php echo $this->get_field_id( 'Social_Photo_Slide_total' ); ?>"><?php _e( 'Number of slides to show:' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'Social_Photo_Slide_total' ); ?>" name="<?php echo $this->get_field_name( 'Social_Photo_Slide_total' ); ?>" type="number" step="1" min="1" value="<?php echo $total; ?>" size="3" /></p>


<?php
	}
}

// register My_Widget
add_action( 'widgets_init', function(){
	register_widget( 'Social_Photo_Grid_Widget' );
	register_widget( 'Social_Photo_Slide_Widget' );
});
		
?>
