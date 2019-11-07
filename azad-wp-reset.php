<?php
/* 
Plugin Name: Azad WP Reset
Description: The easiest way to reset wp database.
Plugin URI: gittechs.com/plugin/azad-duplicate-menu
Author: Md. Abul Kalam Azad
Author URI: gittechs.com/author
Author Email: webdevazad@gmail.com
Version: 0.0.0.1
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: azad-wp-reset
Domain Path: /languages
*/
defined( 'ABSPATH' ) || exit;

require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
$plugin_data = get_plugin_data( __FILE__ );

define( 'awr_url', plugin_dir_url( __FILE__ ) );
define( 'awr_path', plugin_dir_path( __FILE__ ) );
define( 'awr_plugin', plugin_basename( __FILE__ ) );
define( 'awr_version', $plugin_data['Version'] );
define( 'awr_name', $plugin_data['Name'] );
class Azad_WP_Reset{
    public function __construct(){
        add_action('admin_menu',array($this,'add_page'));
        add_action('admin_init',array($this,'admin_init'));
    }
    public function admin_init(){
        
    }
    public function add_page(){
        if(current_user_can('activate_plugins') && function_exists('add_management_page')){
            $hook = add_management_page(
                esc_html__('Reset','azad-wp-reset'),
                esc_html__('Reset','azad-wp-reset'),
                'activate_plugins',
                'azad-wp-reset',
                array($this,'admin_page')
            );
        }
    }
    public function admin_page(){ ?>
        <div class="wrap">
            asdf
        </div>
    <?php }
    public function __destruct(){}
}
if(is_admin()){
    $azad_wp_reset = new Azad_WP_Reset();
}