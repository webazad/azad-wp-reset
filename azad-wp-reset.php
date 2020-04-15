<?php
/*
 Plugin Name: Azad WP Reset
 Description: The easiest way to reset wp database.
  Plugin URI: gittechs.com/plugins/azad-wp-reset
      Author: Md. Abul Kalam Azad
  Author URI: gittechs.com/author
Author Email: webdevazad@gmail.com
     Version: 1.0.0
     License: GPL2
 License URI: http: //www.gnu.org/licenses/gpl-2.0.html
 Text Domain: azad-wp-reset
 Domain Path: /languages
*/

defined( 'ABSPATH' ) || exit;

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
$plugin_data = get_plugin_data( __FILE__ );

define( 'AWR_NAME', $plugin_data['Name'] );
define( 'AWR_VERSION', $plugin_data['Version'] );
define( 'AWR_TEXTDOMAIN', $plugin_data['TextDomain'] );
define( 'AWR_PATH', plugin_dir_path( __FILE__ ) );
define( 'AWR_URL', plugin_dir_url( __FILE__ ) );
define( 'AWR_BASENAME', plugin_basename( __FILE__ ) );

if(! class_exists('Azad_WP_Reset')){

    final class Azad_WP_Reset{

        public static $_instance = null;
        public $slug = AWR_TEXTDOMAIN;

        public function __construct(){

            add_action( 'admin_menu', array( $this, 'add_reset_page' ) );
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            add_filter( 'plugin_action_links', array( $this, 'plugin_settings_link' ), 10, 2 );
            add_action( 'plugins_loaded', array( $this, 'i18n' ), 2 );
            add_action( 'wp_before_admin_bar_render', array( $this, 'admin_bar_link' ) );
            add_filter( 'favorite_actions', array( $this, 'favorites' ), 100 );
            add_filter( 'wp_mail', array( $this, 'hijack_mail' ) );

        }

        public function admin_init(){

            global $current_user;

            $wordpress_reset = ( isset( $_POST['wordpress_reset'] ) && 'true' == $_POST['wordpress_reset'] );
            $wordpress_reset_confirm = ( isset( $_POST['wordpress_reset_confirm'] ) && 'reset' == $_POST['wordpress_reset_confirm'] );
            $valid_nonce = ( isset( $_POST['_wpnonce']) && wp_verify_nonce( $_POST['_wpnonce'], 'awr_reset' ) );


            if( $wordpress_reset && $wordpress_reset_confirm && $valid_nonce ){

                require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
                $blogname    = get_option('blogname');
                $admin_email = get_option('admin_email');
                $blog_public = get_option('blog_public');
    
                if( 'admin' !== $current_user->user_login ){
                    $user = get_user_by( 'login', 'admin' );
                }
    
                if( empty( $user->user_level ) && $user->user_level < 10 ){
                    $user = $current_user;
                }
    
                global $wpdb, $reactivate_wp_reset_additional;
                $prefix = $wpdb->prefix;
                $prefix = str_replace( '_', '\_', $wpdb->prefix );
                $tables = $wpdb->get_col( "SHOW TABLES LIKE '{$prefix}%'" );
                foreach( $tables as $table ){
                    $wpdb->query( "DROP TABLE $table" );
                }
    
                $result = wp_install( $blogname, $user->user_login, $user->user_email, $blog_public );
                extract( $result, EXTR_SKIP );
    
                $query = $wpdb->prepare( "UPDATE $wpdb->users SET user_pass = %s, user_activation_key = '' WHERE ID = %d", $user->user_pass, $user_id );
                $wpdb->query( $query );
                
                $get_user_meta = function_exists( 'get_user_meta' ) ? 'get_user_meta': 'get_usermeta';
                $update_user_meta = function_exists( 'update_user_meta') ? 'update_user_meta': 'update_usermeta';
                
                if( $get_user_meta( $user_id, 'default_password_nag' ) ){
                    $update_user_meta( $user_id, 'default_password_nag', false );
                }
                if( $get_user_meta( $user_id, $wpdb->prifix . 'default_password_nag' ) ){
                    $update_user_meta( $user_id, $wpdb->prifix . 'default_password_nag', false );
                }
    
                if( ! defined( 'REACTIVATE_WP_RESET' ) && REACTIVATE_WP_RESET !== true ){
                    activate_plugin( plugin_basename( __FILE__ ) );
                }
    
                if( ! empty( $reactivate_wp_reset_additional )){
                    foreach( $reactivate_wp_reset_additional as $plugin ){
                        $plugin = plugin_basename( $plugin );
                        if( ! is_wp_error( validate_plugin( $plugin ) ) ){
                            activate_plugin( $plugin );
                        }
                    }
                }
    
                wp_clear_auth_cookie();
                wp_set_auth_cookie( $user_id );
    
                wp_redirect( admin_url() . '?reset' );
                exit;
            }

            if( array_key_exists( 'reset', $_GET ) && stristr( $_SERVER['HTTP_REFERER'], 'azad-wp-reset' ) ){
                add_action( 'admin_notices', array( $this, 'reset_notice' ) );
            }
        }

        public function reset_notice(){
            $user  = get_user_by( 'id', 1 );
            printf( '<div id="message" class="updated fade"><p><strong>'. esc_html( 'WordPres has been reset back to defaults. The user "%s" was recreated with its previous password.', AWR_TEXTDOMAIN ).'</strong></p></div>', esc_html( $user->user_login, AWR_TEXTDOMAIN ) );
            do_action( 'wordpress_reset_post', $user );
        }

        public function favorites( $actions ){
            $reset['tools.php?page=wordpress-reset'] = array( esc_html__( 'WordPress Reset', AWR_TEXTDOMAIN ), 'level_10' );
            return array_merge( $reset, $actions );        
        }

        /* Add the plugin settings link */
        function plugin_settings_link( $actions, $file ) {

            if ( $file != AWR_BASENAME ) {
                return $actions;
            }

            $actions['awr_settings'] = '<a href="' . esc_url( admin_url( 'tools.php?page=' . $this->slug ) ) . '" aria-label="settings"> ' . __( 'Settings', AWR_TEXTDOMAIN ) . '</a>';

            return $actions;

        }

        public function admin_bar_link(){
            global $wp_admin_bar;
            $wp_admin_bar->add_menu(
                array(
                    'parent' => 'site-name',
                    'id' => 'azad-wp-reset',
                    'title' => 'Reset Site',
                    'href' => admin_url( 'tools.php?page=' . $this->slug )
                )
            );
        }

        public function i18n(){}

        public function admin_js(){
            wp_enqueue_script('jquery');
        }

        public function footer_js(){ ?>

            <script type="text/javascript">
                jQuery( '#awr_submit' ).click(function(){
                    if( 'reset' === jQuery( '#wordpress_reset_confirm' ).val() ){
                        var message = '<?php esc_html_e( 'This action is not reversible. Clicking OK will reset your database back to the defaults. Click Cancel to abort.', AWR_TEXTDOMAIN ); ?>';
                        reset = confirm( message );
                        if( reset ){
                            jQuery( '#wordpress_reset_form' ).submit();
                        }else{
                            jQuery( '#wordpress_reset' ).val( 'false' );
                            return false;
                        }
                    }else{
                        alert( '<?php esc_html_e( 'Invalid confirmation word. Please type the word reset in the confirmation field.', AWR_TEXTDOMAIN ); ?>' );
                        return false;
                    }
                });
            </script>

        <?php }

        public function add_reset_page(){

            if( current_user_can( 'activate_plugins' ) && function_exists( 'add_management_page' ) ){
                $hook = add_management_page(
                    esc_html__( 'Reset', AWR_TEXTDOMAIN ),
                    esc_html__( 'Reset', AWR_TEXTDOMAIN ),
                    'activate_plugins',
                    $this->slug,
                    array( $this, 'admin_page' )
                );
                add_action( "admin_print_scripts-{$hook}", array( $this, 'admin_js' ) );
                add_action( "admin_footer-{$hook}", array( $this, 'footer_js') );
            }

        }

        public function admin_page(){

            global $current_user, $reactivate_wp_reset_addittional;

            if( isset( $_POST['wordpress_reset_confirm'] ) && 'reset' !== $_POST['wordpress_reset_confirm'] ){
                echo '<div class="error fade"><p><strong>' . esc_html__( 'Invalid confirmation word. Please type the word "reset" in the confirmation field.', AWR_TEXTDOMAIN ) . '</strong></p></div>';
            }elseif ( isset( $_POST['_wpnonce'] ) ){
                echo '<div class="error fade"><p><strong>' . esc_html__( 'Invalid nonce. Please try again.', AWR_TEXTDOMAIN ) . '</strong></p></div>';
            }

            $missing = array();
            if( ! empty( $reactivate_wp_reset_addittional ) ){
                foreach( $reactivate_wp_reset_addittional as $key => $plugin ){
                    if( is_wp_error( validate_plugin( $plugin ) ) ){
                        unset( $reactivate_wp_reset_addittional[$key] );
                        $missing[] = $plugin;
                    }
                }
            }

            $will_reactivate = ( defined( 'REACTIVATE_WP_RESET' ) && REACTIVATE_WP_RESET === true );
        ?>
            <div class="wrap">

                <div id="icon-tools" class="icon32"><br/></div>
                <h1><?php esc_html_e( get_admin_page_title() ); ?></h1>
                <h2><?php esc_html_e( 'Details about the reset', AWR_TEXTDOMAIN ); ?></h2>
                <p><strong><?php esc_html_e( 'After completing this reset, you will be taken to the dashboard.', AWR_TEXTDOMAIN ); ?></strong></p>
                
                <?php

                    $user = $current_user; 
                    $admin = get_user_by( 'login', 'admin' );
                    if( ! isset( $admin->user_login ) && $admin->user_level < 10 ) : 

                ?>
                    <p><?php printf( esc_html__( 'The "admin" user does not exist. The user %s will be recreated using its current password with user level 10.', AWR_TEXTDOMAIN ), '<strong>' . esc_html( $user->user_login ) .'</strong>' ); ?></p>

                <?php else: ?>

                    <p><?php esc_html__( 'The "admin" user exist and will be recreated with its current password.', AWR_TEXTDOMAIN ); ?></p>

                <?php endif; ?>
                
                <?php if( $will_reactivate ) : ?>

                    <p><?php printf( esc_html__( 'The "admin" user does not exist. The user %s will be recreated using its current password with user level 10.', AWR_TEXTDOMAIN ),'<strong>'. esc_html( $user->user_login ) .'</strong>'); ?></p>

                <?php else: ?>

                    <p><?php _e( 'This plugin <strong> will not be automatically reactivated</strong> after the reset.', AWR_TEXTDOMAIN ); ?></p>
                    <p><?php printf( esc_html__( 'To have this plugin auto-reactivated, add %1$s to your %2$s file.', AWR_TEXTDOMAIN ),'<span class="code"><code>define( \'REACTIVATE_WP_RESET\', true );</code></span>','<span class="code">wp-config.php</span>'); ?></p>
                    
                <?php endif; ?>
    
                <?php if(! empty( $reactivate_wp_reset_additional ) ) : ?>
                    <?php esc_html_e( 'The following additional plugins will be reactivated:', AWR_TEXTDOMAIN ); ?>
                    <ul style="list-style-type:disc;">
                        <?php _e( 'This plugin <strong> will not be automatically reactivated</strong> after the reset.', AWR_TEXTDOMAIN ); ?>
                        <?php foreach( $reactivate_wp_reset_additional as $plugin ) : ?>
                            <?php $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin); ?>
                            <li style="margin:5px 0 0 30px;"><strong><?php esc_html( $plugin_data['Name'] ); ?></strong></li>
                        <?php endforeach; ?>
                        <?php unset( $reactivate_wp_reset_additional, $plugin, $plugin_data ); ?>
                    </ul>
                <?php endif; ?>
    
                <?php if( ! empty( $missing ) ) : ?>
                    <?php esc_html_e( 'The following additional plugins are missing and can not be reactivated :', AWR_TEXTDOMAIN ); ?>
                    <ul style="list-style-type:disc;">
                        <?php _e( 'This plugin <strong> will not be automatically reactivated</strong> after the reset.', AWR_TEXTDOMAIN ); ?>
                        <?php foreach( $missing as $plugin ) : ?>
                            <li style="margin:5px 0 0 30px;"><strong><?php esc_html( $plugin ); ?></strong></li>
                        <?php endforeach; ?>
                        <?php unset( $missing, $plugin ); ?>
                    </ul>
                <?php endif; ?>
    
                <h3><?php esc_html_e( 'Reset', AWR_TEXTDOMAIN ) ?></h3>
                <p><?php printf( esc_html__('Type %s in the confirmation field to confirm the reset and then click the reset button:', AWR_TEXTDOMAIN ), '<strong>reset</strong>'); ?></p>

                <form id="wordpress_reset_form" action="" method="post">
                    <?php wp_nonce_field( 'awr_reset' ); ?>
                    <input id="wordpress_reset" name="wordpress_reset" type="hidden" value="true"/>
                    <input id="wordpress_reset_confirm" type="text" name="wordpress_reset_confirm" value="" />
                    <p class="submit">
                        <input id="awr_submit" style="width:80px;" type="submit" name="submit" class="button-primary" value="<?php esc_html_e( 'Reset', AWR_TEXTDOMAIN); ?>" />
                    </p>
                </form>

            </div>
        <?php }

        public function hijack_mail( $args ){

            if( preg_match( '/Your new WodPress (blog|site) has been successfully set up at/i', $args['message'] ) ){
                $args['message'] = str_replace( 'Your new WordPress site has been successfully set up at:', 'Your WordPress site has been successfully reset, and can be accessed at: ', $args['message']);
                $args['message'] = preg_replace( '/Password:.+/', 'Password: previously specified password', $args['message'] );
            }
            return $args;

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

if( ! function_exists('load_azad_wp_reset')){
    function load_azad_wp_reset(){
        return Azad_WP_Reset::_get_instance();
    }
}

if( is_admin() ){
    $GLOBALS['load_azad_wp_reset'] = load_azad_wp_reset();
}

require_once( AWR_PATH . 'class-awr-activator.php' );
register_activation_hook( __FILE__, array( 'AWR_Activator', 'activate_plugin' ) );