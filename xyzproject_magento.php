<?php
/**
 * Extension Name: XYZProject Magento
 * Version: 1.0
 * Author: Hafiz Adnan Hussain
 * Author URI: http://www.doergroup.com
 **/
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
class xyzproject_magento
{
    public $_customer_data;
    public $_navigation;
    public $_minicart;
    /**
     * Set global needed to distinguish WP and Magento for functions.php's __() in Magento
     */
    public function __construct() {
        $GLOBALS['wp_magento'] = true;
        $this->initialize();
    }
    /**
     * run initialization as a callback (run method)
     */
    public function initialize() {
        if (is_admin()) {
            //create new top-level menu
            add_action('admin_menu', array(&$this, 'magento_create_menu'));
            //call register settings function
            add_action( 'admin_init', array(&$this, 'register_magento_plugin_settings'));
        } else {
            add_action('after_setup_theme', array(&$this, 'run'));
        }
    }
    /**
     * Call Mage::app(), and fetch all needed data.
     * Save the date to class variables for later retrieval
     */
    public function run() {
        $this->init_magento();
        $this->_customer_data = $this->get_customer_info();
        $this->_navigation = $this->get_top_nav();
    }
    public function get_top_nav() {
        $layout = Mage::getSingleton('core/layout');
        $header = $layout->createBlock('xyzproject/catalog_navigation')->setTemplate('page/html/megamenu.phtml');
        return $header->toHtml();
    }
    /**
     * Load Magento with Mage::app() and get session
     */
    private function init_magento() {
        $magentoPath = get_option('magento_path');
        if ($magentoPath) {
            require_once $magentoPath . '/app/Mage.php';
            // start Magento and session
            Mage::app();
            Mage::getSingleton('core/session', array('name' => 'frontend'));
            Mage::getSingleton("checkout/session");
        }
    }
    /**
     * Get customer data
     * @return array of customer data
     */
    private function get_customer_info() {
        $result = array();
        $customerSession = Mage::getSingleton('customer/session');
        $customer = $customerSession->getCustomer();
        $isLoggedIn = $customerSession->isLoggedIn();
        $result['is_logged_in'] = $isLoggedIn;
        if ($isLoggedIn) {
            $this->process_customer($customer);
            $result['customer_info'] = array(
                'name' => $customer->getName(),
            );
        } else {
            // if there is no Magento user, log out the wordpress one, too, if not admin
            $current_user = wp_get_current_user();
            if ($current_user->ID) {
                $roles = $current_user->roles;
                if(count(array_intersect(array("administrator", "editor", "author"), $current_user->roles)) == 0) {
                    wp_logout();
                }
            }
        }
        return $result;
    }
    /**
     * Process current customer and login/logout/create wp user if needed
     * @param $customer
     */
    private function process_customer($customer) {
        $email = $customer->getEmail();
        // magento is logged in, but Wordpress is not
        if (!is_user_logged_in()) {
            $user = get_user_by('email', $email);
            if($user) {
                // log user in
                $user_id = $user->ID;
                wp_set_current_user($user_id, $user->user_login);
                wp_set_auth_cookie($user_id);
                do_action('wp_login', $user->user_login);
            } else {
                $password = wp_generate_password(12, false);
                $userId = wp_create_user($email, $password, $email);
                if (!is_wp_error($userId)) {
                    wp_set_current_user($userId, $email);
                    wp_set_auth_cookie($userId);
                    do_action('wp_login', $email);
                }
            }
        // both Magento and Wordpress are logged in
        // check if everything is valid
        } else {
            $current_user = wp_get_current_user();
            $roles = $current_user->roles;
            if ($email != $current_user->user_email && !in_array("administrator", $current_user->roles)) {
                wp_logout();
            }
        }
    }
    /**
     * Get static block from Magento
     * @return Mage_Cms_Block_Block|string
     */
    public function get_static_block($identifier) {
        $result = "";
        $layout = Mage::getSingleton('core/layout');
        $block = $layout->createBlock('cms/block')->setBlockId($identifier)->toHtml();
        if ($block) {
            $result = $block;
        }
        return $result;
    }
//    /**
//     * Get top navigation static block
//     * @return string
//     */
   private function get_top_navigation_info() {
       $result = "";
       $topNavigationBlock = get_option('top_navigation_block');
       if (!$topNavigationBlock) {
           $topNavigationBlock = "top_navigation";
       }
       $layout = Mage::getSingleton('core/layout');
       $navigation = $layout->createBlock('cms/block')->setBlockId($topNavigationBlock)->toHtml();
       if ($navigation) {
           $result = $navigation;
       }
       return $result;
   }
    /**
     * get needed data to display on WP side
     * @return array of Magento data
     */
    public function get_data() {
        $result = array();
        $result['customer']         = $this->_customer_data;
        $result['top_navigation']   = $this->_navigation;
        $result['minicart']         = $this->_minicart;
        return $result;
    }
    /**
     * Create menu in WP administration
     */
    public function magento_create_menu() {
        add_menu_page('Magento Settings', 'Magento Settings', 'administrator', __FILE__, array(&$this, 'magento_settings_page') , plugins_url('/xyzproject-magento/Magentoicon.png', __FILE__) );
    }
    /**
     * register fields to be saved in WP database
     */
    public function register_magento_plugin_settings() {
        register_setting('magento-settings-group', 'magento_path');
    }
    /**
     * Render wp menu in administration
     */
    public function magento_settings_page() {
        echo    '<div class="wrap">
                    <h2>Your Plugin Name</h2>
                    <form method="post" action="options.php">';
        settings_fields( "magento-settings-group");
        do_settings_sections("magento-settings-group");
        echo                '<table class="form-table">
                            <tr valign="top">
                                <th scope="row">Magento path</th>
                                <td><input type="text" name="magento_path" value="' . esc_attr(get_option('magento_path')) . '" /></td>
                            </tr>
                        </table>';
        submit_button();
        echo            '</form>
                </div>';
    }
}
$time_start = microtime_float();
$magento = new xyzproject_magento();
$time_end = microtime_float();
$time = $time_end - $time_start;
var_dump($time * 100);
/**
 * used to fetch a part of shared data
 * @param $type String (customer | header | footer)
 * @return null
 */
function get_mage_data($type) {
    global $magento;
    $result = null;
    $data = $magento->get_data();
    if (isset($data[$type])) {
        $result = $data[$type];
    }
    return $result;
}
/**
 * @param $plugin string
 */
function is_cache_plugin_active() {
    $a = get_option('active_plugins', array());
    return in_array('cached/cache.php', get_option('active_plugins', array()));
}
if(is_cache_plugin_active() && function_exists('add_cacheaction')) {
    define( 'AUTO_MINICART_TAG', 'XYZPROJECT_WP_MINICART_TAG' ); // Change this to a secret placeholder tag
    if ( AUTO_MINICART_TAG != '' ) {
        function auto_minicart( &$cachedata = 0 ) {
            if ( defined( 'AUTO_OB_TEXT' ) ) {
                return str_replace( AUTO_MINICART_TAG, AUTO_OB_TEXT, $cachedata );
            }
            
            if ( $cachedata === 0 ) { // called directly from the theme so store the output
                define( 'AUTO_OB_TEXT', $text );
            } else {
                // called via the wpsc_cachedata filter. We only get here in cached pages in wp-cache-phase1.php
                return str_replace( AUTO_MINICART_TAG, $text, $cachedata );
            }
        }
    }
}
