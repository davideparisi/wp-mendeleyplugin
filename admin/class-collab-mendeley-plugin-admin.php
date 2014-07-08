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
if ( ! class_exists( "Client" ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/vendor/autoload.php';
}
define( 'AUTHORIZE_ENDPOINT', "https://api-oauth2.mendeley.com/oauth/authorize" );
define( 'TOKEN_ENDPOINT', "https://api-oauth2.mendeley.com/oauth/token" );

date_default_timezone_set( get_option( 'timezone_string' ) != '' ? get_option( 'timezone_string' ) : 'Europe/Rome' );

if (!class_exists("citeproc")) {
    include_once('includes/CiteProc.php');
}

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

    protected $client = null;

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

		$plugin = CollabMendeleyPlugin::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
        $this->options = $this->get_options();
		$this->callback_url = admin_url('options-general.php?page=' . $this->plugin_slug );


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
		add_action( 'admin_action_set_keys', array( $this, 'store_keys' ) );
		add_filter( '@TODO', array( $this, 'filter_method_name' ) );

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
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), CollabMendeleyPlugin::VERSION );
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
		// @TODO: check if auth code is present already
        if ( $_SERVER['REQUEST_METHOD'] == 'GET' && isset( $_GET['code'] ) ) {
            $this->get_access_token();
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
	 * NOTE:     Actions are points in the execution of a page or process
	 *           lifecycle that WordPress fires.
	 *
	 *           Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    1.0.0
	 */
	public function store_keys() {
        if ( ! isset($this->options['client_id']) && ! isset($this->options['client_secret'])) {
            $client_id = $_POST['client-id'];
            $client_secret = $_POST['client-secret'];
            $this->options['client_id'] = $client_id;
            $this->options['client_secret'] = $client_secret;
            $this->update_options( $this->options );
        }
        $this->send_authorization_request();
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

    /*------------------------------------------------------------------------------
     *
     * Private Functions
     *
     -----------------------------------------------------------------------------*/

    /**
     * Update options array with db data (if present)
     *
     * @return null
     */
    private function get_options() {
        // if $options is already present return $options
        if ( isset( $this->options ) ) {
            return $this->options;
        }
        // check if options are in the db and store them in $this->options
        $tmp_options = get_option( $this->plugin_slug );
        if ( isset( $tmp_options ) ) {
            return $tmp_options;
        } else {
            // otherwise initialize to an empty array
            $this->options = array();
            add_option( $this->plugin_slug, $this->options );
            return $this->options;
        }

    }

    private function send_authorization_request() {
	    if ( ! isset( $this->client ) ) {
		    $this->set_client();
	    }
        $auth_url = $this->client->getAuthenticationUrl( AUTHORIZE_ENDPOINT, $this->callback_url );
        $auth_url .= '&scope=all';
        wp_redirect( $auth_url );
        exit();
    }



    private function get_access_token() {

	    if ( ! isset( $this->client ) ) {
		    $this->set_client();
	    }
	    if ( ! isset( $this->options['access_token'] ) ) {
            $this->send_access_token_request();
	    }
        if ( time() > $this->options['expire_time'] ) {
            $this->refresh_access_token();
        }
        $this->client->setAccessToken( $this->options['access_token'] );
        $response = $this->client->fetch('https://api-oauth2.mendeley.com/oapi/library/documents/authored/');

	    if ( $response['code'] != '200' ) {
            var_dump($response, $response['result']);
        }

        $data = $response['result'];
        $document_ids = $data['document_ids'];
        $documents = array();
        foreach ( $document_ids as $doc ) {
            $response = $this->client->fetch('            https://api-oauth2.mendeley.com/oapi/library/documents/' . $doc . '/');
            $documents[$doc] = $response;
        }
        add_option($this->plugin_slug . '_documents', $documents);
        // $json = json_encode($data);
        // @TODO: do something with the response
    }

    /**
     * Simple wrapper for the update_option wordpress function
     *
     * @param $options
     */
    private function update_options( $options ) {
	    // #TODO: check if db options are stale and then update
        update_option( $this->plugin_slug, $options );
    }

    private function set_client() {
        $this->client = new \OAuth2\Client( $this->options['client_id'], $this->options['client_secret'] );
    }

	private function store_access_token( $result ) {
		foreach ( $result as $k => $v ) {
			$this->options[ $k ] = $v;
		}
		$expire_time = time() + $this->options['expires_in'];
        $this->options['expire_time'] = $expire_time;

		$this->update_options( $this->options ); // save access token to db
	}

    private function send_access_token_request() {
        $code = $_GET['code']; // get the auth code from $_GET array
        $params = array('code' => $code, 'redirect_uri' => $this->callback_url ); // set request parameters
        $response = $this->client->getAccessToken(TOKEN_ENDPOINT, 'authorization_code', $params); // get the access token
        $this->store_access_token( $response['result'] );
    }

    private function  refresh_access_token() {
        $code = $_GET['code']; // get the auth code from $_GET array
        $params = array('code' => $code, 'redirect_uri' => $this->callback_url, 'refresh_token' => $this->options['refresh_token'] ); // set request parameters
        $response = $this->client->getAccessToken(TOKEN_ENDPOINT, 'refresh_token', $params); // get the access token
        $this->store_access_token( $response['result'] );
    }

}
