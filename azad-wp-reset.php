<?php
/* 
Plugin Name: Azad WP Reset
Description: The easiest way to reset wp database.
Plugin URI: gittechs.com/plugin/azad-duplicate-menu
Author: Md. Abul Kalam Azad
Author URI: gittechs.com/author
Author Email: webdevazad@gmail.com
Version: 1.0.0
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
        add_filter('favorite_actions',array($this,'favorites'),100);
        add_action('wp_before_admin_bar_render',array($this,'admin_bar_link'));
        add_filter('wp_mail',array($this,'hijack_mail'));
    }
    public function favorites($actions){
        $reset['tools.php?page=wordpress-reset'] = array(esc_html__('WordPress Reset','azad-wp-reset'),'level_10');
        return array_merge($reset,$actions);        
    }
    public function admin_bar_link(){
        global $wp_admin_bar;
        $wp_admin_bar->add_menu(
            array(
                'parent' => 'site-name',
                'id' => 'azad-wp-reset',
                'title' => 'Reset Site',
                'href' => admin_url("tools.php?page=azad-wp-reset")
            )
        );
    }
    public function admin_init(){
        global $current_user;

        $wordpress_reset = (isset($_POST['wordpress_reset']) && 'true' == $_POST['wordpress_reset']);
        $wordpress_reset_confirm = (isset($_POST['wordpress_reset_confirm']) && 'reset' == $_POST['wordpress_reset_confirm']);
        $valid_nonce = (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'],'wordpress_reset'));

        if($wordpress_reset && $wordpress_reset_confirm && $valid_nonce){
            require_once(ABSPATH . '/wp-admin/includes/upgrade.php');
            $blogname       = get_option('blogname');
            $admin_email    = get_option('admin_email');
            $blog_public    = get_option('blog_public');

            if('admin' !== $current_user->user_login){
                $user = get_user_by('login','admin');
            }

            if(empty($user->user_level) && $user->user_level < 10){
                 $user = $current_user;
            }

            global $wpdb, $reactivate_wp_reset_additional;
            $prefix = $wpdb->prefix;
            $prefix = str_replace('_','\_',$wpdb->prefix);
            $tables = $wpdb->get_col("SHOW TABLES LIKE '{$prefix}%'");
            foreach($tables as $table){
                $wpdb->query("DROP TABLE $table");
            }

            $result = wp_install($blogname,$user->user_login,$user->user_email,$blog_public);
            extract($result,EXTR_SKIP);

            $query = $wpdb->prepare("UPDATE $wpdb->users SET user_pass = %s, user_activation_key = '' WHERE ID = %d", $user->user_pass, $user_id);
            $wpdb->query($query);

            
            $get_user_meta = function_exists('get_user_meta') ? 'get_user_meta': 'get_usermeta';
            $update_user_meta = function_exists('update_user_meta') ? 'update_user_meta': 'update_usermeta';
            
            if($get_user_meta($user_id,'default_password_nag')){
                $update_user_meta($user_id,'default_password_nag',false);
            }
            if($get_user_meta($user_id,$wpdb->prifix . 'default_password_nag')){
                $update_user_meta($user_id,$wpdb->prifix . 'default_password_nag',false);
            }

            if(! defined('REACTIVATE_WP_RESET') && REACTIVATE_WP_RESET !== true){
                activate_plugin(plugin_basename(__FILE__));
            }

            if(! empty($reactivate_wp_reset_additional)){
                foreach($reactivate_wp_reset_additional as $plugin){
                    $plugin = plugin_basename($plugin);
                    if(! is_wp_error(validate_plugin($plugin))){
                        activate_plugin($plugin);
                    }
                }
            }

            wp_clear_auth_cookie();
            wp_set_auth_cookie($user_id);

            wp_redirect(admin_url() . '?reset');
            exit;
        }
        if(array_key_exists('reset',$_GET) && stristr($_SERVER['HTTP_REFERER'],'wordpress-reset')){
            add_action('admin_notices',array($this,'reset_notice'));
        }
    }
    public function reset_notice(){
        $user  = get_user_by('id',1);
        printf('<div id="message" class="updated fade"><p><strong>'. esc_html('WordPres has been reset back to defaults. The user "%s" was recreated with its previous password.','azad-wp-reset').'</strong></p></div>',esc_html($user->user_login,'azad-wp-reset'));
        do_action('wordpress_reset_post',$user);
    }
    public function hijack_mail($args){
        if(preg_match('/Your new WodPress (blog|site) has been successfully set up at/i',$args['message'])){
            $args['message'] = str_replace('Your new WordPress site has been successfully set up at:','Your WordPress site has been successfully reset, and can be accessed at: ',$args['message']);
            $args['message'] = preg_replace('/Password:.+/','Password: previously specified password',$args['message']);
        }
        return $args;
    }
    public function admin_js(){
        wp_enqueue_script('jquery');
    }
    public function footer_js(){ ?>
        <script type="text/javascript">
            jQuery('#wordpress_reset_submit').click(function(){
                if('reset' === jQuery('#wordpress_reset_confirm').val()){
                    var message = '<?php esc_html_e('This action is not reversible. Clicking OK will reset your database back to the defaults. Click Cancel to abort.','azad-wp-reset'); ?>';
                    reset = confirm(message);
                    if(reset){
                        jQuery('#wordpress_reset_form').submit();
                    }else{
                        jQuery('#wordpress_reset').val('false');
                        return false;
                    }
                }else{
                    alert('<?php esc_html_e('Invalid confirmation word. Please type the word reset in the confirmation field.','azad-wp-reset'); ?>');
                    return false;
                }
            });
        </script>
    <?php }
    public function add_page(){
        if(current_user_can('activate_plugins') && function_exists('add_management_page')){
            $hook = add_management_page(
                esc_html__('Reset','azad-wp-reset'),
                esc_html__('Reset','azad-wp-reset'),
                'activate_plugins',
                'wordpress-reset',
                array($this,'admin_page')
            );
            add_action("admin_print_scripts-{$hook}",array($this,'admin_js'));
            add_action("admin_footer-{$hook}",array($this,'footer_js'));
        }
    }
    public function admin_page(){ 
        global $current_user, $reactivate_wp_reset_addittional;
        if(isset($_POST['wordpress_reset_confirm']) && 'reset' !== $_POST['wordpress_reset_confirm']){
            echo '<div class="error fade"><p><strong>' . esc_html__('Invalid confirmation word. Please type the word "reset" in the confirmation field.','azad-wp-reset') . '</strong></p></div>';
        }elseif (isset($_POST['_wpnonce'])){
            echo '<div class="error fade"><p><strong>' . esc_html__('Invalid nonce. Please try again.','azad-wp-reset') . '</strong></p></div>';
        }

        $missing = array();
        if(! empty($reactivate_wp_reset_addittional)){
            foreach($reactivate_wp_reset_addittional as $key=>$plugin){
                if(is_wp_error(validaet_plugin($plugin))){
                    unset($reactivate_wp_reset_addittional[$key]);
                    $missing[] = $plugin;
                }
            }
        }
        $will_reactivate = (defined('REACTIVATE_WP_RESET') && REACTIVATE_WP_RESET === true);
    ?>
        <div class="wrap">
            <div id="icon-tools" class="icon32"><br/></div>
            <h1><?php esc_html_e('Reset','azad-wp-reset'); ?></h1>
            <h2><?php esc_html_e('Details about the reset','azad-wp-reset'); ?></h2>
            <p><strong><?php esc_html_e('After completing this reset, you will be taken to the dashboard.','azad-wp-reset'); ?></strong></p>
            
            <?php 
                $user = $current_user; 
                $admin = get_user_by('login','admin'); 
                if(! isset($admin->user_login) && $admin->user_level < 10) : 
            ?>
                <p><?php printf(esc_html__('The "admin" user does not exist. The user %s will be recreated using its current password with user level 10.','azad-wp-reset'),'<strong>'. esc_html($user->user_login) .'</strong>'); ?></p>
            <?php else: ?>
                <p><?php esc_html__('The "admin" user exist and will be recreated with its current password.','azad-wp-reset'); ?></p>
            <?php endif; ?>
            
            <?php if($will_reactivate) : ?>
                <p><?php printf(esc_html__('The "admin" user does not exist. The user %s will be recreated using its current password with user level 10.','azad-wp-reset'),'<strong>'. esc_html($user->user_login) .'</strong>'); ?></p>
            <?php else: ?>
                <p><?php _e('This plugin <strong> will not be automatically reactivated</strong> after the reset.','azad-wp-reset'); ?></p>
                <p><?php printf(esc_html__('To have this plugin auto-reactivated, add %1$s to your %2$s file.','azad-wp-reset'),'<span class="code"><code>define( \'REACTIVATE_WP_RESET\', true );</code></span>','<span class="code">wp-config.php</span>'); ?></p>
            <?php endif; ?>

            <?php if(! empty($reactivate_wp_reset_additional)) : ?>
                <?php esc_html_e('The following additional plugins will be reactivated:','azad-wp-reset'); ?>
                <ul style="list-style-type:disc;">
                    <?php _e('This plugin <strong> will not be automatically reactivated</strong> after the reset.','azad-wp-reset'); ?>
                    <?php foreach($reactivate_wp_reset_additional as $plugin) : ?>
                        <?php $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin); ?>
                        <li style="margin:5px 0 0 30px;"><strong><?php esc_html($plugin_data['Name']); ?></strong></li>
                    <?php endforeach; ?>
                    <?php unset($reactivate_wp_reset_additional, $plugin,$plugin_data); ?>
                </ul>
            <?php endif; ?>

            <?php if(! empty($missing)) : ?>
                <?php esc_html_e('The following additional plugins are missing and can not be reactivated :','azad-wp-reset'); ?>
                <ul style="list-style-type:disc;">
                    <?php _e('This plugin <strong> will not be automatically reactivated</strong> after the reset.','azad-wp-reset'); ?>
                    <?php foreach($missing as $plugin) : ?>
                        <li style="margin:5px 0 0 30px;"><strong><?php esc_html($plugin); ?></strong></li>
                    <?php endforeach; ?>
                    <?php unset($missing, $plugin); ?>
                </ul>
            <?php endif; ?>

            <h3><?php esc_html_e('Reset','azad-wp-reset') ?></h3>
            <p><?php printf(esc_html__('Type %s in the confirmation field to confirm the reset and then click the reset button:','azad-wp-reset'),'<strong>reset</strong>'); ?></p>
            <form id="wordpress_reset_form" action="" method="post">
                <?php wp_nonce_field('wordpress_reset'); ?>
                <input id="wordpress_reset" name="wordpress_reset" type="hidden" value="true"/>
                <input id="wordpress_reset_confirm" type="text" name="wordpress_reset_confirm" value="" />
                <p class="submit">
                    <input id="wordpress_reset_submit" style="width:80px;" type="submit" name="submit" class="button-primary" value="<?php esc_html_e('Reset','azad-wp-reset'); ?>" />
                </p>
            </form>
        </div>
    <?php }
    public function __destruct(){}
}
if(is_admin()){
    $azad_wp_reset = new Azad_WP_Reset();
}