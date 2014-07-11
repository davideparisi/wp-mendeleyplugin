<?php
/**
 * Collab Mendeley Plugin
 *
 * @package   CollabMendeleyPluginAdmin
 * @author    Davide Parisi <davideparisi@gmail.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2014 --
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-collab-mendeley-plugin.php`
 *
 * @package CollabMendeleyPluginAdmin
 * @author  Davide Parisi <davideparisi@gmail.com>
 */
/*if ( ! class_exists( "MendeleyApi" ) ) {
	require_once plugin_dir_path( __DIR__ ) . "includes/class-mendeley-api.php";
}*/


class CollabMendeleyPluginAdmin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	protected $options = null;

	//protected $client = null;

	protected $callback_url = '';


	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		/*
		 * @TODO :
		 *
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		$this->init();


		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		/*
		 * Define custom functionality.
		 *
		 * Read more about actions and filters:
		 * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		// add_action( 'admin_action_set_keys', array( $this, 'store_keys' ) );
		add_action( 'admin_action_request_token', array( $this, 'request_access_token' ) );
		add_filter( '@TODO', array( $this, 'filter_method_name' ) );

		add_action( 'admin_init', array( $this, 'initialize_options' ) );

	}

	public function init() {
		$plugin             = CollabMendeleyPlugin::get_instance();
		$this->plugin_slug  = $plugin->get_plugin_slug();
		$this->callback_url = admin_url( 'options-general.php?page=' . $this->plugin_slug );
		//$this->client       = new MendeleyApi();
		$this->options = $this->get_options();
		if ( isset( $this->options['access_token'] ) ) {
			$this->check_access_token();
		}
	}


	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		/*
		 * @TODO :
		 *
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug . '-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), CollabMendeleyPlugin::VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), CollabMendeleyPlugin::VERSION );
		}

	}

	/* ------------------------------------------------------------------------ *
    * Setting Registration
    * ------------------------------------------------------------------------ */

	public function default_keys_options() {
		$defaults = array(
			'client_id'     => '',
			'client_secret' => '',
		);

		return apply_filters( 'default_keys_options', $defaults );
	}

	public function initialize_options() {

		// check if multisite environment
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( false === get_site_option( $this->plugin_slug ) ) {
				add_site_option( $this->plugin_slug, apply_filters( 'default_keys_options', $this->default_keys_options() ) );
			}
		} else {
			if ( false === get_option( $this->plugin_slug ) ) {
				add_option( $this->plugin_slug, apply_filters( 'default_keys_options', $this->default_keys_options() ) );
			}
		}

		add_settings_section(
			'collab_mendeley_settings_section',
			'API Key Setting',
			array( $this, 'options_callback' ),
			$this->plugin_slug
		);

		add_settings_field(
			'client_id',
			'Client ID',
			array( $this, 'client_id_input_callback' ),
			$this->plugin_slug,
			'collab_mendeley_settings_section',
			array( 'Insert the client ID' )
		);

		add_settings_field(
			'client_secret',
			'Client Secret',
			array( $this, 'client_secret_input_callback' ),
			$this->plugin_slug,
			'collab_mendeley_settings_section',
			array( 'Insert the client secret' )
		);

		register_setting(
			$this->plugin_slug,
			$this->plugin_slug,
			array( $this, 'validate' )
		);
	}

	public function options_callback() {
		echo '<p class="description">Insert the <code>client ID</code> and <code>client secret</code> you have got from registering this plugin on <a href="http://dev.mendeley.com">Mendeley</a></p>';
	}

	public function client_id_input_callback( $args ) {
		$options = $this->get_options();
		$html = '<input type="text" id="client_id" name="' . $this->plugin_slug . '[client_id]" value="' . $options['client_id'] . '" />'; // readonly="'. (isset($options['client_id']) ? "true" : "false")  .'"
		echo $html;
	}

	public function client_secret_input_callback( $args ) {
		$options = $this->get_options();
		$html = '<input type="text" id="client_secret" name="' . $this->plugin_slug . '[client_secret]" value="' . $options['client_secret'] . '" />'; // readonly="'. (isset($options['client_id']) ? "true" : "false") .'"
		echo $html;
	}

	public function validate( $input ) {
		$output = array();
		foreach ( $input as $key => $value ) {
			if ( isset( $input[ $key ] ) ) {
				if ( $key == 'access_token' ) {
					$output[ $key ] = $input[ $key ];
				} else {
					$output[ $key ] = strip_tags( stripslashes( $input[ $key ] ) );
				}
			}
		}

		return apply_filters( 'validate', $output, $input );

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 *
		 * NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		 *
		 *        Administration Menus: http://codex.wordpress.org/Administration_Menus
		 */
		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'Collab Mendeley Plugin', $this->plugin_slug ),
			__( 'Mendeley Settings', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		if ( isset( $_GET['code'] ) ) {
			$this->store_access_token( $_GET['code'] );
		}
		include_once( 'views/admin.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}


	/**
	 * NOTE:     Filters are points of execution in which WordPress modifies data
	 *           before saving it or sending it to the browser.
	 *
	 *           Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */
	public function filter_method_name() {
		// @TODO: Define your filter hook callback here
	}

	public function request_access_token() {
		$options = $this->get_options();
		if ( $options['client_id'] === '' || $options['client_secret'] === '' ) {
			//@todo: do something if keys are void
			exit();
		}
		// get setted client instance
		$client = $this->set_up_client( $options );

		// Redirect to mendeley login page
		$client->start_authorization_flow();
	}

	public function store_access_token( $auth_code ) {
		$options      = $this->get_options();
		$client       = $this->set_up_client( $options );
		$access_token = $client->get_access_token( $auth_code );
		if ( $access_token['code'] === 200 ) {
			$options['access_token'] = $access_token;
			$access_token_data       = $options['access_token']['result'];
			$expire_time             = ( time() + $access_token_data['expires_in'] );
			$options['expire_time']  = $expire_time;
			$this->update_options( $options );
		}

	}

	public function check_access_token() {
		$options     = $this->get_options();
		$result      = $options['access_token']['result'];
		$expire_time = ( time() + $result['expire_in'] );
		if ( time() > $expire_time ) {
			$this->refresh_token();
		}
	}

	public function refresh_token() {
		$options       = $this->get_options();
		$client        = $this->set_up_client( $options );
		$result        = $options['access_token']['result'];
		$refresh_token = $result['refresh_token'];
		$client->set_client_id( $options['client_id'] );

		$client->set_client_secret( $options['client_secret'] );
		$client->set_callback_url( $this->callback_url );
		$client->init();
		$new_token               = $client->refresh_access_token( $refresh_token );
		$options['access_token'] = $new_token;
		$access_token_data       = $options['access_token']['result'];
		$expire_time             = ( time() + $access_token_data['expires_in'] );
		$options['expire_time']  = $expire_time;
		$this->update_options( $options );
	}

	/*------------------------------------------------------------------------------
	 *
	 * Private Functions/utilities
	 *
	 -----------------------------------------------------------------------------*/


	/**
	 * Update options array with db data (if present)
	 *
	 * @return null
	 */
	private function get_options() {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$opts = get_site_option( $this->plugin_slug );
		} else {
			$opts = get_option( $this->plugin_slug );
		}

		return $opts;
	}

	/**
	 * Simple wrapper for the update_option wordpress function
	 *
	 * @param $options
	 */
	private function update_options( $options ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			update_site_option( $this->plugin_slug, $options );
		} else {
			update_option( $this->plugin_slug, $options );
		}
	}


	/*private function mendeleyNames2CiteProcNames( $names ) {
		if ( ! $names ) {
			return $names;
		}
		$tmp_names = array();
		foreach ( $names as $rank => $name ) {
			$tmp_names[ $rank ]['given']  = $name['forename'];
			$tmp_names[ $rank ]['family'] = $name['surname'];
		}

		return $tmp_names;
	}

	private function mendeleyType2CiteProcType( $type ) {
		if ( ! isset( $this->type_map ) ) {
			$this->type_map = array(
				'Book'                   => 'book',
				'Book Section'           => 'chapter',
				'Journal Article'        => 'article-journal',
				'Magazine Article'       => 'article-magazine',
				'Newspaper Article'      => 'article-newspaper',
				'Conference Proceedings' => 'paper-conference',
				'Report'                 => 'report',
				'Thesis'                 => 'thesis',
				'Case'                   => 'legal_case',
				'Encyclopedia Article'   => 'entry-encyclopedia',
				'Web Page'               => 'webpage',
				'Working Paper'          => 'report',
				'Generic'                => 'chapter',
			);
		}

		return $this->type_map[ $type ];
	}

	private function pre_process( $doc ) {
		// stdClass for showing document
		$docdata         = new stdClass;
		$docdata->type   = $this->mendeleyType2CiteProcType( $doc['type'] );
		$docdata->author = $this->mendeleyNames2CiteProcNames( $doc['authors'] );
		$docdata->editor = $this->mendeleyNames2CiteProcNames( $doc['editors'] );
		$docdata->issued = (object) array( 'date-parts' => array( array( $doc['year'] ) ) );
		$docdata->title  = $doc['title'];
		if ( isset( $doc['published_in'] ) ) {
			$docdata->container_title = $doc['published_in'];
		}
		if ( isset( $doc['publication_outlet'] ) ) {
			$docdata->container_title = $doc['publication_outlet'];
		}
		if ( isset( $doc['journal'] ) ) {
			$docdata->container_title = $doc['journal'];
		}
		if ( isset( $doc['volume'] ) ) {
			$docdata->volume = $doc['volume'];
		}
		if ( isset( $doc['issue'] ) ) {
			$docdata->issue = $doc['issue'];
		}
		if ( isset( $doc['pages'] ) ) {
			$docdata->page = $doc['pages'];
		}
		if ( isset( $doc['publisher'] ) ) {
			$docdata->publisher = $doc['publisher'];
		}
		if ( isset( $doc['city'] ) ) {
			$docdata->publisher_place = $doc['city'];
		}
		if ( isset( $doc['url'] ) ) {
			$docdata->URL = $doc['url'];
		}
		if ( isset( $doc['doi'] ) ) {
			$docdata->DOI = $doc['doi'];
		}
		if ( isset( $doc['isbn'] ) ) {
			$docdata->ISBN = $doc['isbn'];
		}

		return $docdata;
	}*/

	private function set_up_client( $options ) {
		$client = MendeleyApi::get_instance();
		$client->set_up(
			$options['client_id'],
			$options['client_secret'],
			$this->callback_url
		);
		$client->init();

		return $client;
	}
}
