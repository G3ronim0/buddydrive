<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BuddyDrive_Admin' ) ) :
/**
 * Loads BuddyDrive plugin admin area
 *
 * Inspired by bbPress 2.3
 *
 * @package BuddyDrive
 * @subpackage Admin
 * @since version (1.0)
 */
class BuddyDrive_Admin {

	/** Directory *************************************************************/

	/**
	 * @var string Path to the BuddyDrive admin directory
	 */
	public $admin_dir = '';

	/** URLs ******************************************************************/

	/**
	 * @var string URL to the BuddyDrive admin directory
	 */
	public $admin_url = '';

	/**
	 * @var string URL to the BuddyDrive admin styles directory
	 */
	public $styles_url = '';

	/**
	 * @var string URL to the BuddyDrive admin script directory
	 */
	public $js_url = '';

	/**
	 * @var the BuddyDrive settings page for admin or network admin
	 */
	public $settings_page ='';

	/**
	 * @var the notice hook depending on config (multisite or not)
	 */
	public $notice_hook = '';

	/**
	 * @var the user columns filter depending on config (multisite or not)
	 */
	public $user_columns_filter = '';

	/**
	 * @var the BuddyDrive hook_suffixes to eventually load script
	 */
	public $hook_suffixes = array();


	/** Functions *************************************************************/

	/**
	 * The main BuddyDrive admin loader
	 *
	 * @since version (1.0)
	 *
	 * @uses BuddyDrive_Admin::setup_globals() Setup the globals needed
	 * @uses BuddyDrive_Admin::includes() Include the required files
	 * @uses BuddyDrive_Admin::setup_actions() Setup the hooks and actions
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Admin globals
	 *
	 * @since version (1.0)
	 * @access private
	 *
	 * @uses buddydrive() to get some globals of plugin instance
	 * @uses bp_core_do_network_admin() to define the best menu (network)
	 */
	private function setup_globals() {
		$buddydrive = buddydrive();
		$this->admin_dir           = trailingslashit( $buddydrive->includes_dir . 'admin'  ); // Admin path
		$this->admin_url           = trailingslashit( $buddydrive->includes_url . 'admin'  ); // Admin url
		$this->styles_url          = trailingslashit( $this->admin_url   . 'css' ); // Admin styles URL*/
		$this->js_url              = trailingslashit( $this->admin_url   . 'js' );
		$this->settings_page       = bp_core_do_network_admin() ? 'settings.php' : 'options-general.php';
		$this->notice_hook         = bp_core_do_network_admin() ? 'network_admin_notices' : 'admin_notices' ;
		$this->user_columns_filter = bp_core_do_network_admin() ? 'wpmu_users_columns' : 'manage_users_columns';
		$this->requires_db_upgrade = buddydrive_get_db_number_version() < buddydrive_get_number_version();
	}

	/**
	 * Include required files
	 *
	 * @since version (1.0)
	 * @access private
	 */
	private function includes() {
		require( $this->admin_dir . 'buddydrive-settings.php'  );
		require( $this->admin_dir . 'buddydrive-items.php'  );
	}

	/**
	 * Setup the admin hooks, actions and filters
	 *
	 * @since version (1.0)
	 * @access private
	 *
	 * @uses add_action() To add various actions
	 * @uses bp_core_admin_hook() to hook the right menu (network or not)
	 * @uses add_filter() To add various filters
	 */
	private function setup_actions() {
		// Bail if config does not match what we need
		if ( buddydrive::bail() )
			return;

		/** General Actions ***************************************************/

		add_action( bp_core_admin_hook(),                 array( $this, 'admin_menus'             )        ); // Add menu item to settings menu
		add_action( 'buddydrive_admin_head',              array( $this, 'admin_head'              )        ); // Add some general styling to the admin area
		add_action( $this->notice_hook,                   array( $this, 'activation_notice'       ),     9 ); // Checks for BuddyDrive Upload directory once activated
		add_action( 'buddydrive_admin_register_settings', array( $this, 'register_admin_settings' )        ); // Add settings
		add_action( 'admin_enqueue_scripts',              array( $this, 'enqueue_scripts'         ), 10, 1 ); // Add enqueued JS and CSS

		/** Filters ***********************************************************/

		// Modify BuddyDrive's admin links
		add_filter( 'plugin_action_links',               array( $this, 'modify_plugin_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );

		// Filters the user space left output to strip html tags
		add_filter( 'buddydrive_get_user_space_left',    'buddydrive_filter_user_space_left'         , 10, 2 );

		add_action( 'wp_ajax_buddydrive_upgrader', array( $this, 'do_upgrade' ) );

		// Allow plugins to modify these actions
		do_action_ref_array( 'buddydrive_admin_loaded', array( &$this ) );
	}

	/**
	 * Builds BuddyDrive admin menus
	 *
	 * @uses bp_current_user_can() to check for user's capability
	 * @uses add_submenu_page() to add the settings page
	 * @uses add_menu_page() to add the admin area for BuddyDrive items
	 * @uses add_dashboard_page() to add the BuddyDrive Welcome Screen
	 */
	public function admin_menus() {

		// Bail if user cannot manage options
		if ( ! bp_current_user_can( 'manage_options' ) )
			return;


		$this->hook_suffixes[] = add_submenu_page(
			$this->settings_page,
			_x( 'BuddyDrive', 'BuddyDrive Settings page title', 'buddydrive' ),
			_x( 'BuddyDrive', 'BuddyDrive Settings menu title', 'buddydrive' ),
			'manage_options',
			'buddydrive',
			'buddydrive_admin_settings'
		);

		$hook = add_menu_page(
			_x( 'BuddyDrive', 'BuddyDrive User Files Admin page title', 'buddydrive' ),
			_x( 'BuddyDrive', 'BuddyDrive User Files Admin menu title',  'buddydrive' ),
			'manage_options',
			'buddydrive-files',
			'buddydrive_files_admin',
			'div'
		);

		$this->hook_suffixes[] = $hook;

		// About
		$this->hook_suffixes[] = add_dashboard_page(
			__( 'Welcome to BuddyDrive',  'buddydrive' ),
			__( 'Welcome to BuddyDrive',  'buddydrive' ),
			'manage_options',
			'buddydrive-about',
			array( $this, 'about_screen' )
		);

		// Upgrade DB Screen
		if ( $this->requires_db_upgrade ) {
			$this->hook_suffixes['upgrade'] = add_dashboard_page(
				__( 'BuddyDrive Upgrades',  'buddydrive' ),
				__( 'BuddyDrive Upgrades',  'buddydrive' ),
				'manage_options',
				'buddydrive-upgrade',
				array( $this, 'upgrade_screen' )
			);
		}


		// Hook into early actions to load custom CSS and our init handler.
		add_action( "load-$hook", 'buddydrive_files_admin_load' );

		// Putting user edit hooks there, this way we're sure they will load at the right place
		add_action( 'edit_user_profile',          array( $this, 'edit_user_quota'           ), 10, 1 );
		add_action( 'edit_user_profile_update',   array( $this, 'save_user_quota'           ), 10, 1 );
		add_action( 'set_user_role',              array( $this, 'update_user_quota_to_role' ), 10, 2 );

		add_filter( $this->user_columns_filter,   array( $this, 'user_quota_column' )        );
		add_filter( 'manage_users_custom_column', array( $this, 'user_quota_row'    ), 10, 3 );

		if( is_multisite() ) {
			$hook_settings = $this->hook_suffixes[0];
			add_action( "load-$hook_settings", array( $this, 'multisite_upload_trick' ) );
		}

	}

	/**
	 * Loads some common css and hides the BuddyDrive about submenu
	 *
	 * @uses remove_submenu_page() to remove the BuddyDrive About submenu
	 */
	public function admin_head() {

		// Hide About page
		remove_submenu_page( 'index.php', 'buddydrive-about'   );

		if ( $this->requires_db_upgrade ) {
			remove_submenu_page( 'index.php', 'buddydrive-upgrade' );
		}

		$version = buddydrive_get_version();

		?>

		<style type="text/css" media="screen">
		/*<![CDATA[*/

			@font-face {
				font-family: 'buddydrive-dashicons';
				src: url(data:application/x-font-ttf;charset=utf-8;base64,AAEAAAALAIAAAwAwT1MvMg6R3isAAAC8AAAAYGNtYXAwVKBZAAABHAAAAExnYXNwAAAAEAAAAWgAAAAIZ2x5ZjIALEUAAAFwAAAAjGhlYWQBiNyzAAAB/AAAADZoaGVhB+8ETgAAAjQAAAAkaG10eAaIAGkAAAJYAAAAFGxvY2EAKABaAAACbAAAAAxtYXhwAAkAGAAAAngAAAAgbmFtZbVAQzcAAAKYAAABS3Bvc3QAAwAAAAAD5AAAACAAAwQAAZAABQAAApkCzAAAAI8CmQLMAAAB6wAzAQkAAAAAAAAAAAAAAAAAAAABAQAAAAAAAAAAAAAAAAAAAABAAADQAQPA/8D/wAPAAEAAAAABAAAAAAAAAAAAAAAgAAAAAAACAAAAAwAAABQAAwABAAAAFAAEADgAAAAKAAgAAgACAAEAINAB//3//wAAAAAAINAB//3//wAB/+MwAwADAAEAAAAAAAAAAAAAAAEAAf//AA8AAQAAAAAAAAAAAAIAADc5AQAAAAABAAAAAAAAAAAAAgAANzkBAAAAAAEAAAAAAAAAAAACAAA3OQEAAAAAAwBpAFoELQMuAAwAEQAVAAAlITI+AjUhFB4CMyUzFSM1EyEDIQEdAlsmQjEc/DwcMUIlAls9PXn8tDwDxFocMkEmJkEyHHk9PQJb/h0AAAABAAAAAQAA0YB/9l8PPPUACwQAAAAAAM8ezEQAAAAAzx7MRAAAAAAELQMuAAAACAACAAAAAAAAAAEAAAPA/8AAAASIAAAAAAQtAAEAAAAAAAAAAAAAAAAAAAAFAAAAAAAAAAAAAAAAAgAAAASIAGkAAAAAAAoAFAAeAEYAAQAAAAUAFgADAAAAAAACAAAAAAAAAAAAAAAAAAAAAAAAAA4ArgABAAAAAAABABIAAAABAAAAAAACAA4AVQABAAAAAAADABIAKAABAAAAAAAEABIAYwABAAAAAAAFABYAEgABAAAAAAAGAAkAOgABAAAAAAAKACgAdQADAAEECQABABIAAAADAAEECQACAA4AVQADAAEECQADABIAKAADAAEECQAEABIAYwADAAEECQAFABYAEgADAAEECQAGABIAQwADAAEECQAKACgAdQBkAGEAcwBoAGkAYwBvAG4AcwBWAGUAcgBzAGkAbwBuACAAMQAuADAAZABhAHMAaABpAGMAbwBuAHNkYXNoaWNvbnMAZABhAHMAaABpAGMAbwBuAHMAUgBlAGcAdQBsAGEAcgBkAGEAcwBoAGkAYwBvAG4AcwBHAGUAbgBlAHIAYQB0AGUAZAAgAGIAeQAgAEkAYwBvAE0AbwBvAG4AAAMAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=) format('truetype'),
					 url(data:application/font-woff;charset=utf-8;base64,d09GRk9UVE8AAARoAAoAAAAABCAAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAABDRkYgAAAA9AAAANoAAADacVIW4k9TLzIAAAHQAAAAYAAAAGAOkd4rY21hcAAAAjAAAABMAAAATDBUoFlnYXNwAAACfAAAAAgAAAAIAAAAEGhlYWQAAAKEAAAANgAAADYBiNyzaGhlYQAAArwAAAAkAAAAJAfvBE5obXR4AAAC4AAAABQAAAAUBogAaW1heHAAAAL0AAAABgAAAAYABVAAbmFtZQAAAvwAAAFLAAABS7VAQzdwb3N0AAAESAAAACAAAAAgAAMAAAEABAQAAQEBCmRhc2hpY29ucwABAgABADv4HAL4GwP4GAQeCgAJd/+Lix4KAAl3/4uLDAeLSxwEiPpUBR0AAAB9Dx0AAACCER0AAAAJHQAAANESAAYBAQoTFRcaH2Rhc2hpY29uc2Rhc2hpY29uc3UwdTF1MjB1RDAwMQAAAgGJAAMABQEBBAcKDUf+lA7+lA7+lA78lA73HPex5RX474sF74vc3IvvCP5YiwWLJ9w67osI+O/3DRXIi4tOTouLyAX3DfjvFf3gi0/8d/pYiwUO+pQU+pQViwwKAAAAAwQAAZAABQAAApkCzAAAAI8CmQLMAAAB6wAzAQkAAAAAAAAAAAAAAAAAAAABAQAAAAAAAAAAAAAAAAAAAABAAADQAQPA/8D/wAPAAEAAAAABAAAAAAAAAAAAAAAgAAAAAAACAAAAAwAAABQAAwABAAAAFAAEADgAAAAKAAgAAgACAAEAINAB//3//wAAAAAAINAB//3//wAB/+MwAwADAAEAAAAAAAAAAAAAAAEAAf//AA8AAQAAAAEAAJR3TYBfDzz1AAsEAAAAAADPHsxEAAAAAM8ezEQAAAAABC0DLgAAAAgAAgAAAAAAAAABAAADwP/AAAAEiAAAAAAELQABAAAAAAAAAAAAAAAAAAAABQAAAAAAAAAAAAAAAAIAAAAEiABpAABQAAAFAAAAAAAOAK4AAQAAAAAAAQASAAAAAQAAAAAAAgAOAFUAAQAAAAAAAwASACgAAQAAAAAABAASAGMAAQAAAAAABQAWABIAAQAAAAAABgAJADoAAQAAAAAACgAoAHUAAwABBAkAAQASAAAAAwABBAkAAgAOAFUAAwABBAkAAwASACgAAwABBAkABAASAGMAAwABBAkABQAWABIAAwABBAkABgASAEMAAwABBAkACgAoAHUAZABhAHMAaABpAGMAbwBuAHMAVgBlAHIAcwBpAG8AbgAgADEALgAwAGQAYQBzAGgAaQBjAG8AbgBzZGFzaGljb25zAGQAYQBzAGgAaQBjAG8AbgBzAFIAZQBnAHUAbABhAHIAZABhAHMAaABpAGMAbwBuAHMARwBlAG4AZQByAGEAdABlAGQAIABiAHkAIABJAGMAbwBNAG8AbwBuAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA) format('woff');
				font-weight: normal;
				font-style: normal;
			}

			body.wp-admin #adminmenu .toplevel_page_buddydrive-files .wp-menu-image:before,
			body.wp-admin .buddydrive-profile-stats:before {
				font-family: 'buddydrive-dashicons';
				speak: none;
				font-style: normal;
				font-weight: normal;
				font-variant: normal;
				text-transform: none;
				line-height: 1;
				/* Better Font Rendering =========== */
				-webkit-font-smoothing: antialiased;
				-moz-osx-font-smoothing: grayscale;
				content:"\d001";
			}

			body.wp-admin .buddydrive-profile-stats:before {
				font-size: 18px;
				vertical-align: bottom;
				margin-right: 5px;
			}

			body.wp-admin #adminmenu .toplevel_page_buddydrive-files .wp-menu-image {
				content: "";
			}


			body.wp-admin .buddydrive-badge {
				font: normal 150px/1 'buddydrive-dashicons' !important;
				/* Better Font Rendering =========== */
				-webkit-font-smoothing: antialiased;
				-moz-osx-font-smoothing: grayscale;

				color: #000;
				display: inline-block;
				content:'';
			}

			body.wp-admin .buddydrive-badge:before{
				content: "\d001";
			}

			.about-wrap .buddydrive-badge {
				position: absolute;
				top: 0;
				right: 0;
			}
				body.rtl .about-wrap .buddydrive-badge {
					right: auto;
					left: 0;
				}


		/*]]>*/
		</style>
		<?php
	}

	/**
	 * Creates the upload dir and htaccess file
	 *
	 * @uses buddydrive_get_upload_data() to get BuddyDrive upload datas
	 * @uses wp_mkdir_p() to create the dir
	 * @uses insert_with_markers() to create the htaccess file
	 */
	public function activation_notice() {
		// we need to eventually create the upload dir and the .htaccess file
		$buddydrive_upload = buddydrive_get_upload_data();

		if ( empty( $buddydrive_upload['dir'] ) || ! file_exists( $buddydrive_upload['dir'] ) ){
			bp_core_add_admin_notice( __( 'The main BuddyDrive directory is missing', 'buddydrive' ) );
		}

		$display_upgrade_notice = true;
		$current_screen = get_current_screen();

		if ( isset( $current_screen->id ) && in_array( $current_screen->id, $this->hook_suffixes ) ) {
			$display_upgrade_notice = false;
		}

		if ( $this->requires_db_upgrade && $display_upgrade_notice ) {
			bp_core_add_admin_notice( sprintf(
				__( 'BuddyDrive is almost ready. It needs to update some of the datas it is using. If you have not done a database backup yet, please do it <strong>before</strong> clicking on <a href="%s">this link</a>.', 'buddydrive' ),
				esc_url( add_query_arg( array( 'page' => 'buddydrive-upgrade' ), bp_get_admin_url( 'index.php' ) ) )
			), 'error' );
		}
	}

	/**
	 * Registers admin settings for BuddyDrive
	 *
	 * @uses buddydrive_admin_get_settings_sections() to get the settings section
	 * @uses buddydrive_admin_get_settings_fields_for_section() to get the fields
	 * @uses bp_current_user_can() to check for user's capability
	 * @uses add_settings_section() to add the settings section
	 * @uses add_settings_field() to add the fields
	 * @uses register_setting() to fianlly register the settings
	 */
	public static function register_admin_settings() {

		// Bail if no sections available
		$sections = buddydrive_admin_get_settings_sections();

		if ( empty( $sections ) )
			return false;

		// Loop through sections
		foreach ( (array) $sections as $section_id => $section ) {

			// Only proceed if current user can see this section
			if ( ! bp_current_user_can( 'manage_options' ) )
				continue;

			// Only add section and fields if section has fields
			$fields = buddydrive_admin_get_settings_fields_for_section( $section_id );
			if ( empty( $fields ) )
				continue;

			// Add the section
			add_settings_section( $section_id, $section['title'], $section['callback'], $section['page'] );

			// Loop through fields for this section
			foreach ( (array) $fields as $field_id => $field ) {

				// Add the field
				add_settings_field( $field_id, $field['title'], $field['callback'], $section['page'], $section_id, $field['args'] );

				// Register the setting
				register_setting( $section['page'], $field_id, $field['sanitize_callback'] );
			}
		}
	}

	/**
	 * Eqnueues scripts and styles if needed
	 *
	 * @param  string $hook the WordPress admin page
	 * @uses wp_enqueue_style() to enqueue the style
	 * @uses wp_enqueue_script() to enqueue the script
	 */
	public function enqueue_scripts( $hook = false ) {
		if ( in_array( $hook, $this->hook_suffixes ) ) {
			$min = '.min';
			if ( defined( 'SCRIPT_DEBUG' ) && true == SCRIPT_DEBUG )  {
				$min = '';
			}

			wp_enqueue_style( 'buddydrive-admin-css', $this->styles_url .'buddydrive-admin.css' );
		}

		if ( !empty( $this->hook_suffixes[1] ) && $hook == $this->hook_suffixes[1] && !empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'edit' ) {
			wp_enqueue_script ( 'buddydrive-admin-js', $this->js_url .'buddydrive-admin.js' );
			wp_localize_script( 'buddydrive-admin-js', 'buddydrive_admin', buddydrive_get_js_l10n() );
		}

		if ( isset( $this->hook_suffixes['upgrade'] ) && $hook === $this->hook_suffixes['upgrade'] ) {
			wp_register_script(
				'buddydrive-upgrader-js',
				$this->js_url . "buddydrive-upgrader{$min}.js",
				array( 'jquery', 'json2', 'wp-backbone' ),
				buddydrive_get_version(),
				true
			);
		}
	}

	/**
	 * Modifies the links in plugins table
	 *
	 * @param  array $links the existing links
	 * @param  string $file  the file of plugins
	 * @uses plugin_basename() to get the file name of BuddyDrive plugin
	 * @uses add_query_arg() to add args to the link
	 * @uses bp_get_admin_url() to build the new links
	 * @return array  the existing links + the new ones
	 */
	public function modify_plugin_action_links( $links, $file ) {

		// Return normal links if not BuddyPress
		if ( plugin_basename( buddydrive()->file ) != $file )
			return $links;

		// Add a few links to the existing links array
		return array_merge( $links, array(
			'settings' => '<a href="' . esc_url( add_query_arg( array( 'page' => 'buddydrive'       ), bp_get_admin_url( $this->settings_page ) ) ) . '">' . esc_html__( 'Settings', 'buddydrive' ) . '</a>',
			'about'    => '<a href="' . esc_url( add_query_arg( array( 'page' => 'buddydrive-about' ), bp_get_admin_url( 'index.php'          ) ) ) . '">' . esc_html__( 'About',    'buddydrive' ) . '</a>'
		) );
	}

	/**
	 * Displays the Welcome screen
	 *
	 * @uses buddydrive_get_version() to get the current version of the plugin
	 * @uses bp_get_admin_url() to build the url to settings page
	 * @uses add_query_arg() to add args to the url
	 */
	public function about_screen() {
		global $wp_version;
		$display_version = buddydrive_get_version();
		$settings_url = add_query_arg( array( 'page' => 'buddydrive'), bp_get_admin_url( $this->settings_page ) );
		?>
		<div class="wrap about-wrap">
			<h1><?php printf( __( 'BuddyDrive %s', 'buddydrive' ), $display_version ); ?></h1>
			<div class="about-text"><?php printf( __( 'Thank you for upgrading to the latest version of BuddyDrive! BuddyDrive %s is ready to manage the files and folders of your buddies!', 'buddydrive' ), $display_version ); ?></div>
			<div class="buddydrive-badge"></div>

			<h2 class="nav-tab-wrapper">
				<a class="nav-tab nav-tab-active" href="<?php echo esc_url(  bp_get_admin_url( add_query_arg( array( 'page' => 'buddydrive-about' ), 'index.php' ) ) ); ?>">
					<?php _e( 'About', 'buddydrive' ); ?>
				</a>
			</h2>

			<div class="headline-feature">
				<h3><?php esc_html_e( 'Meet the BuddyDrive Editor', 'buddydrive' ); ?></h3>

				<div class="featured-image">
					<img src="<?php echo esc_url( buddydrive_get_images_url() . '/buddydrive-editor.png' );?>" alt="<?php esc_attr_e( 'The BuddyDrive Editor', 'buddydrive' ); ?>">
				</div>

				<div class="feature-section">
					<h3><?php esc_html_e( 'BuddyDrive is now using the BuddyPress Attachments API!', 'buddydrive' ); ?></h3>
					<p><?php esc_html_e( 'Introduced in BuddyPress 2.3, BuddyDrive uses this API to manage user uploads the BuddyPress way. It gave birth to a new BuddyDrive Editor. Now, you and plugins can use it to easily share public files with your community members.', 'buddydrive' ); ?> <a href="https://github.com/imath/buddydrive/wiki/The-BuddyDrive-Editor"><?php esc_html_e( 'Learn more &rarr;', 'buddydrive' ); ?></a></p>
				</div>

				<div class="clear"></div>
			</div>

			<div class="feature-list">
				<h2><?php printf( __( 'The other improvements in %s', 'buddydrive' ), $display_version ); ?></h2>

				<div class="feature-section col two-col">
					<div>
						<h4><?php esc_html_e( 'Bulk-deleting files in the Administration screen', 'buddydrive' ); ?></h4>
						<p><?php _e( 'When the community administrator bulk-deletes files having different owners, each owner&#39;s quota will now be updated.', 'buddydrive' ); ?></p>

						<h4><?php esc_html_e( 'Representation of embed public image files.', 'buddydrive' ); ?></h4>
						<p><?php esc_html_e( 'When you share a link to a file into the activity stream, a private message, a post, a page, ..., BuddyDrive is catching this link to build some specific output.', 'buddydrive' ); ?></p>
						<p><?php esc_html_e( 'Now, if this link is about a public image, a thumbnail will be displayed next to the file title (and description if provided).', 'buddydrive' ); ?></p>
					</div>
					<div class="last-feature">
						<h4><?php esc_html_e( 'BuddyPress single group&#39;s latest activity', 'buddydrive' ); ?></h4>
						<p><?php esc_html_e( 'When a file is shared with the members of a group, the latest activity of the group will be updated.', 'buddydrive' ); ?></p>

						<h4><?php esc_html_e( 'Reassign deleted files', 'buddydrive' ); ?></h4>
						<p><?php esc_html_e( 'If you need to keep files when a user leaves your community (sad), you can use the following filter making sure to return the ID of a user having the bp_moderate capability.', 'buddydrive' ); ?></p>
						<p><code>buddydrive_set_owner_on_user_deleted</code></p>
					</div>
				</div>
			</div>

			<?php if ( ! empty( $_REQUEST['is_new_install' ] ) ) : ?>

			<div class="changelog">
				<h2 class="about-headline-callout"><?php esc_html_e( 'and always..', 'buddydrive' ); ?></h2>
				<div class="feature-section col two-col">
					<div>
						<h4><?php _e( 'User&#39;s BuddyDrive', 'buddydrive' ); ?></h4>
						<p>
							<?php _e( 'It lives in the member&#39;s page just under the BuddyDrive tab.', 'buddydrive' ); ?>
							<?php _e( 'The BuddyDrive edit bar allows the user to manage from one unique place his content.', 'buddydrive' ); ?>
							<?php _e( 'He can add new files, new folders, set their privacy settings, edit them and of course delete them at any time.', 'buddydrive' ); ?>
						</p>
						<img src="<?php echo buddydrive_get_plugin_url();?>/screenshot-1.png" style="width:90%">
					</div>

					<div class="last-feature">
						<h4><?php _e( 'BuddyDrive Uploader', 'buddydrive' ); ?></h4>
						<p>
							<?php _e( 'BuddyDrive uses WordPress HTML5 uploader and do not add any third party script to handle uploads.', 'buddydrive' ); ?>
							<?php _e( 'WordPress is a fabulous tool and already knows how to deal with attachments for its content.', 'buddydrive' ); ?>
							<?php _e( 'So BuddyDrive is managing uploads, the WordPress way!', 'buddydrive' ); ?>
						</p>
						<img src="<?php echo buddydrive_get_plugin_url();?>/screenshot-2.png" style="width:90%">
					</div>
				</div>
			</div>

			<div class="changelog">
				<div class="feature-section col two-col">
					<div>
						<h4><?php _e( 'BuddyDrive Folders', 'buddydrive' ); ?></h4>
						<p>
							<?php _e( 'Using folders is a convenient way to share a list of files at once.', 'buddydrive' ); ?>
							<?php _e( 'Users just need to create a folder, open it an add the files of their choice to it.', 'buddydrive' ); ?>
							<?php _e( 'When sharing a folder, a member actually shares the list of files that is attached to it.', 'buddydrive' ); ?>
						</p>
						<img src="<?php echo buddydrive_get_images_url();?>/folder-demo.png" style="width:90%">
					</div>

					<div class="last-feature">
						<h4><?php _e( 'BuddyDrive privacy options', 'buddydrive' ); ?></h4>
						<p>
							<?php _e( 'There are five levels of privacy for the files or folders.', 'buddydrive' ); ?>&nbsp;
							<?php _e( 'Depending on your BuddyPress settings, a user can set the privacy of a BuddyDrive item to:', 'buddydrive' ); ?>
						</p>
						<ul>
							<li><?php _e( 'Private: the owner of the item will be the only one to be able to download the file.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Password protected: a password will be required before being able to download the file.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Public: everyone can download the file.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Friends only: if the BuddyPress friendship component is active, a user can restrict a download to his friends only.', 'buddydrive' ); ?></li>
							<li><?php _e( 'One of the user&#39;s group: if the BuddyPress user groups component is active, and if the administrator of the group enabled BuddyDrive, a user can restrict the download to members of the group only.', 'buddydrive' ); ?></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="changelog">
				<div class="feature-section col two-col">
					<div>
						<h4><?php _e( 'Sharing BuddyDrive items', 'buddydrive' ); ?></h4>
						<p>
							<?php _e( 'Depending on the privacy option of an item and the activated BuddyPress components, a user can :', 'buddydrive' ); ?>
						</p>
						<ul>
							<li><?php _e( 'Share a public BuddyDrive item in his personal activity.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Share a password protected item using the private messaging BuddyPress component.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Alert his friends he shared a new item using the private messaging BuddyPress component.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Share his file in a group activity to inform the other members of the group.', 'buddydrive' ); ?></li>
							<li><?php _e( 'Copy the link to his item and paste it anywhere in the blog or in a child blog (in case of a multisite configuration). This link will automatically be converted into a nice piece of html.', 'buddydrive' ); ?></li>
						</ul>
					</div>

					<div class="last-feature">
						<h4><?php _e( 'Supervising BuddyDrive', 'buddydrive' ); ?></h4>
						<p>
							<?php _e( 'The administrator of the community can manage all BuddyDrive items from the backend of WordPress.', 'buddydrive' ); ?>
						</p>
						<img src="<?php echo buddydrive_get_plugin_url();?>/screenshot-4.png" style="width:90%">
					</div>
				</div>
			</div>

			<?php endif; ?>

			<div class="changelog">
				<div class="return-to-dashboard">
					<a href="<?php echo esc_url( $settings_url );?>" title="<?php esc_attr_e( 'Configure BuddyDrive', 'buddydrive' ); ?>"><?php esc_html_e( 'Go to the BuddyDrive Settings page', 'buddydrive' );?></a>
				</div>
			</div>

		</div>
	<?php
	}

	public function multisite_upload_trick() {
		remove_filter( 'upload_mimes', 'check_upload_mimes' );
		remove_filter( 'upload_size_limit', 'upload_size_limit_filter' );
	}

	/**
	 * Displays a field to customize the user's upload quota
	 *
	 * @since version 1.1
	 *
	 * @param  object $profileuser data about the user being edited
	 * @global $blog_id the id of the current blog
	 * @uses bp_get_root_blog_id() to make sure we're on the blog BuddyPress is activated on
	 * @uses  current_user_can() to check for edit user capability
	 * @uses ve_get_quota_by_user_id() to get user's quota (default to role's default)
	 * @uses esc_html_e() to sanitize translation before display.
	 * @return string html output
	 */
	public static function edit_user_quota( $profileuser ) {
		global $blog_id;

		if( $blog_id != bp_get_root_blog_id() )
			return;

		// Bail if current user cannot edit users
		if ( ! current_user_can( 'edit_user', $profileuser->ID ) )
			return;

		$user_quota = buddydrive_get_quota_by_user_id( $profileuser->ID );
		?>

		<h3><?php esc_html_e( 'User&#39;s BuddyDrive quota', 'bbpress' ); ?></h3>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="_buddydrive_user_quota"><?php esc_html_e( 'Space available', 'buddydrive' ); ?></label></th>
					<td>
						<input name="_buddydrive_user_quota" type="number" min="1" step="1" id="_buddydrive_user_quota" value="<?php echo $user_quota;?>" class="small-text" />
						<label for="_buddydrive_user_quota"><?php _e( 'MO', 'buddydrive' ); ?></label>
					</td>
				</tr>

			</tbody>
		</table>

		<?php
	}

	/**
	 * Saves the user's quota on profile edit
	 *
	 * @since version 1.1
	 *
	 * @param  integer $user_id (the on being edited)
	 * @global $wpdb the WordPress db class
	 * @global $blog_id the id of the current blog
	 * @uses bp_get_root_blog_id() to make sure we're on the blog BuddyPress is activated on
	 * @uses current_user_can() to check for edit user capability
	 * @uses get_user_meta() to get user's preference
	 * @uses bp_get_option() to get blog's preference
	 * @uses buddydrive() to get the old role global
	 * @uses update_user_meta() to save user's quota
	 */
	public static function save_user_quota( $user_id ) {
		global $wpdb, $blog_id;

		if( $blog_id != bp_get_root_blog_id() )
			return;

		if ( ! current_user_can( 'edit_user', $user_id ) )
			return;

		if( empty( $_POST['_buddydrive_user_quota'] ) )
			return;

		$user_roles = get_user_meta( $user_id, $wpdb->get_blog_prefix( bp_get_root_blog_id() ) . 'capabilities', true );
		$user_roles = array_keys( $user_roles );
		$user_role = is_array( $user_roles ) ? $user_roles[0] : bp_get_option('default_role');

		// temporarly setting old role
		buddydrive()->old_role = $user_role;


		update_user_meta( $user_id, '_buddydrive_user_quota', intval( $_POST['_buddydrive_user_quota'] ) );
	}

	/**
	 * Updates the user quota on role changed
	 *
	 * @since version 1.1
	 *
	 * @param  integer $user_id the id of the user being edited
	 * @param  string $role the new role of the user
	 * @global $blog_id the id of the current blog
	 * @uses bp_get_root_blog_id() to make sure we're on the blog BuddyPress is activated on
	 * @uses buddydrive() to get the old role global
	 * @uses bp_get_option() to get main blog option
	 * @uses update_user_meta() to save user's preference
	 */
	public static function update_user_quota_to_role( $user_id, $role ) {
		global $blog_id;

		if( $blog_id != bp_get_root_blog_id() )
			return;

		$buddydrive = buddydrive();

		$old_role = !empty( $buddydrive->old_role ) ? $buddydrive->old_role : false;

		if( isset( $_POST['_buddydrive_user_quota'] ) && $old_role == $role )
			return;

		$option_user_quota = bp_get_option( '_buddydrive_user_quota', 1000 );

		if( is_array( $option_user_quota ) )
			$user_quota = !empty( $option_user_quota[$role] ) ? $option_user_quota[$role] : 1000;
		else
			$user_quota = $option_user_quota;

		update_user_meta( $user_id, '_buddydrive_user_quota', $user_quota );
	}

	/**
	 * Adds a column to admin user listing to show drive usage
	 *
	 * @since version 1.1
	 *
	 * @param  array $columns the different column of the WP_List_Table
	 * @return array the new columns
	 */
	public static function user_quota_column( $columns = array() ) {
		$columns['user_quota'] = __( 'BuddyDrive Usage',  'buddydrive' );

		return $columns;
	}

	/**
	 * Displays the row data for our new column
	 *
	 * @since version 1.1
	 *
	 * @param  string  $retval
	 * @param  string  $column_name
	 * @param  integer $user_id
	 * @uses buddydrive_get_user_space_left() to calculate the disk usage
	 * @return string the user's drive usage
	 */
	public static function user_quota_row( $retval = '', $column_name = '', $user_id = 0 ) {

		if ( 'user_quota' === $column_name && ! empty( $user_id ) )
			$retval = buddydrive_get_user_space_left( false, $user_id ) .'%';

		// Pass retval through
		return $retval;
	}

	public function upgrade_screen() {
		global $wpdb;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'BuddyDrive Upgrade', 'buddydrive' ); ?></h1>
			<div id="message" class="fade updated buddydrive-hide">
				<p><?php esc_html_e( 'Thank you for your patience, you can now fully enjoy BuddyDrive!', 'buddydrive' ); ?></p>
			</div>
			<p>
		<?php
		$tasks = buddydrive_get_upgrade_tasks();

		if ( ! isset( $tasks ) || empty( $tasks ) ) {
			esc_html_e( 'No tasks to run. BuddyDrive is ready.', 'buddydrive' );
		} else {
			foreach ( $tasks as $key => $task ) {
				if ( ! empty( $task['count'] ) && 'upgrade_db_version' !== $task['action_id'] ) {
					$tasks[ $key ]['count'] = $wpdb->get_var( $task['count'] );

					// If nothing needs to be ugraded, remove the task.
					if ( empty( $tasks[ $key ]['count'] ) ) {
						unset( $tasks[ $key ] );
					} else {
						$tasks[ $key ]['message'] = sprintf( $task['message'], $tasks[ $key ]['count'] );
					}
				}
			}
			
			printf( _n( 'BuddyDrive is almost ready, please wait for the %s following task to proceed.', 'BuddyDrive is almost ready, please wait for the %s following tasks to proceed.', count( $tasks ), 'buddydrive' ), number_format_i18n( count( $tasks ) ) );
		}
		?>
			</p>
			<div id="buddydrive-upgrader"></div>
		</div>
		<?php
		// Add The Upgrader UI
		wp_enqueue_script ( 'buddydrive-upgrader-js' );
		wp_localize_script( 'buddydrive-upgrader-js', 'BuddyDrive_Upgrader', array(
			'tasks' => array_values( $tasks ),
			'nonce' => wp_create_nonce( 'buddydrive-upgrader' ),
		) );
		?>
		<script type="text/html" id="tmpl-progress-window">
			<div id="{{data.id}}">
				<div class="task-description">{{data.message}}</div>
				<div class="buddydrive-progress">
					<div class="buddydrive-bar"></div>
				</div>
			</div>
		</script>
		<?php
	}

	public function do_upgrade() {
		$error = array(
			'message'   => __( 'The task could not process due to an error', 'buddydrive' ),
			'type'      => 'error'
		);

		if ( empty( $_POST['id'] ) || 'buddydrive_upgrader' !== $_POST['action'] ) {
			wp_send_json_error( $error );
		}

		// Add the action to the error
		$error['action_id'] = $_POST['id'];

		// Check nonce
		if ( empty( $_POST['_buddydrive_nonce'] ) || ! wp_verify_nonce( $_POST['_buddydrive_nonce'], 'buddydrive-upgrader' ) ) {
			wp_send_json_error( $error );
		}

		// Check capability
		if ( ! bp_current_user_can( 'bp_moderate' ) ) {
			wp_send_json_error( $error );
		}

		$tasks = wp_list_pluck( buddydrive_get_upgrade_tasks(), 'callback', 'action_id' );

		$did = 0;

		// Upgrading the DB version
		if ( 'upgrade_db_version' === $_POST['id'] ) {
			$did = 1;
			update_option( '_buddydrive_db_version', buddydrive_get_number_version() );

		// Processing any other tasks
		} elseif ( isset( $tasks[ $_POST['id'] ] ) && function_exists( $tasks[ $_POST['id'] ] ) ) {
			$did = call_user_func_array( $tasks[ $_POST['id'] ], array( 20 ) );

			// This shouldn't happen..
			if ( 0 === $did ) {
				wp_send_json_error( array( 'message' => __( '%d item(s) could not be updated', 'buddydrive' ), 'type' => 'warning', 'action_id' => $_POST['id'] ) );
			}
		} else {
			wp_send_json_error( $error );
		}

		wp_send_json_success( array( 'done' => $did, 'action_id' => $_POST['id'] ) );
	}
}

endif;

/**
 * Launches the admin
 *
 * @uses buddydrive()
 */
function buddydrive_admin() {
	buddydrive()->admin = new BuddyDrive_Admin();
}

add_action( 'buddydrive_init', 'buddydrive_admin', 0 );
