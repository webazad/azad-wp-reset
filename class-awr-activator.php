<?php
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if(! class_exists('AWR_Activator')){

    class AWR_Activator{

        public static $_instance = null;

        public function __construct(){
            add_action( 'admin_init', array( $this, 'awr_safe_welcome_redirect' ) );
        }

        public function awr_safe_welcome_redirect(){

			if ( ! get_transient( 'welcome_redirect_awr' ) ) {
                return;
            }
            delete_transient( 'welcome_redirect_awr' );
            if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) {
                return;
            }
            wp_safe_redirect( add_query_arg(
                array(
                    'page' => AWR_TEXTDOMAIN
                    ),
                admin_url( 'admin.php' )
            ) );

        }

        public static function activate_plugin() {

            set_transient( 'welcome_redirect_awr', true, 60 );
			
            $awr_textdomain = get_option( AWR_TEXTDOMAIN );
            
			if( ! $awr_textdomain ){
                update_option( AWR_TEXTDOMAIN, time() );
            }
            
        }

        public static function _get_instance(){
            if( is_null( self::$_instance ) && ! isset( self::$_instance ) && ! ( self::$_instance instanceof self ) ){
                self::$_instance = new self();            
            }
            return self::$_instance;
        }

        public function __destruct(){}

    }
}

if(! function_exists( 'load_awr_activator' )){
    function load_awr_activator(){
        return AWR_Activator::_get_instance();
    }
}
$GLOBALS['load_awr_activator'] = load_awr_activator();