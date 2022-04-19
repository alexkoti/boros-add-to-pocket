<?php
/**
 * Plugin Name: Boros Add to Pocket
 * Plugin URI:  https://alexkoti.com
 * Description: Add URL to Pocket via API. Created because the official Chrome extension stopped working 😢.
 * Version:     1.0.0
 * Author:      Alex Koti
 * Author URI:  https://alexkoti.com
 * 
 */


/*
 * INSTRUCTIONS
 * Register constant in wp-config.php:
 * define( 'BOROS_POCKET', array('consumer_key' => 'XXXX', 'access_token' => 'XXXX') );
 * 
 * Access ajax address to get the bookmarklet: 
 * SITE.com/wp-admin/admin-ajax.php?action=batp
 * 
 * 
 * @todo
 * 
 * - error handling in wp_remote_post()
 * - options in wp-config constant: 
 *   - custom ajax action name
 *   - newtab
 *   - autoclose
 *   - add tags
 * - results page:
 *   - content
 *   - design
 *   - add tags interface
 *     - add tags javascript
 *     - add tags php request
 * - admin page:
 *   - auth:
 *     a) obtain request token ('code')
 *     b) authorize app
 *     c) obtain 'access_token'
 *   - option: add newtab
 *   - option: add autoclose
 *   - option: custom ajax action name
 *   - option: delete tokens
 *   - admin page css
 *   - admin page javascript
 * 
 * 
 */



/**
 * Primary action
 * 
 */
add_action( 'wp_ajax_batp', 'boros_add_to_pocket' );
function boros_add_to_pocket(){
    
    /*
     * Show bookmarklet if url not set
     * 
     */
    if( empty($_GET['url']) ){
        $ajax_url = add_query_arg('action', 'batp', admin_url('admin-ajax.php'));
        $popup    = ", 'add-to-pocket', 'scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=600,height=300,left=100,top=100'";
        $link     = "javascript:{window.open('{$ajax_url}&url='+encodeURIComponent(window.location.href){$popup})}";
        printf('Drag this link to the bookmarks bar: <a href="%s">+ add to pocket</a>', $link);
        die();
    }

    /*
     * Build json params
     * 
     */
    $params = [
        'actions' => [
            [
                'action' => 'add',
                'url'    => sanitize_url( $_GET['url'] ),
            ],
        ],
    ];

    /*
     * Request URL params
     * 
     */
    $url_args = [
        'consumer_key' => BOROS_POCKET['consumer_key'],
        'access_token' => BOROS_POCKET['access_token'],
    ];

    /**
     * Build request URL
     * 
     */
    $pocket_url = add_query_arg($url_args, 'https://getpocket.com/v3/send');

    /*
     * Make request to GetPocket API 
     * 
     */
    $data = wp_remote_post($pocket_url, [
        'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
        'body'        => json_encode($params),
        'method'      => 'POST',
        'data_format' => 'body',
    ]);

    echo '<pre>';
    print_r($data);
    echo '</pre>';

    die();
}



/**
 * Init Admin controls if constant not defined
 * 
 */
if( !defined('BOROS_POCKET') ){
    $boros_add_to_pocket = new Boros_Add_To_Pocket_Admin();
}



/**
 * Class admin page
 * 
 */
class Boros_Add_To_Pocket_Admin {

    /**
     * Hooks
     * 
     */
    public function __construct(){
        add_action( 'admin_init', array($this, 'register_settings') );
        add_action( 'admin_menu', array($this, 'add_menu_page') );
        add_action( 'wp_ajax_batp_api_request', array($this, 'api_request') );
        add_action( 'wp_ajax_batp_update_option', array($this, 'update_option') );
    }

    /**
     * Register all settings fields
     * 
     */
    final public function register_settings(){

        add_settings_section(
            'section_apis',
            'Add to Pocket',
            function( $args ){
                echo 'Seção';
            },
            'batp_api_keys'
        );

        $fields = array(
            array(
                'type'  => 'text',
                'name'  => 'batp_consumer_key',
                'label' => 'Consumer key',
            ),
            array(
                'type'  => 'text',
                'name'  => 'batp_test',
                'label' => 'Test field',
            ),
        );
        foreach( $fields as $field ){
            call_user_func( array($this, "add_setting_field_{$field['type']}"), $field );
        }
    }

    /**
     * Individual text fields, register and HTML
     * 
     */
    private function add_setting_field_text( $field ){
        register_setting( 'batp_api_keys', $field['name'] );
        add_settings_field(
            $field['name'], 
            $field['label'], 
            function( $args ){
                $option = get_option( $args['field_name'] );
                ?>
                <input
                    type="text"
                    id="<?php echo esc_attr( $args['label_for'] ); ?>"
                    name="<?php echo $args['field_name']; ?>"
                    value="<?php echo esc_attr( $option ); ?>">
                <?php
            }, 
            'batp_api_keys', 
            'section_apis',
            [
                'label_for'  => "{$field['name']}-id",
                'class'      => 'classe-html-tr',
                'field_name' => $field['name'],
            ]
        );
    }

    /**
     * Add admin menu item
     * 
     */
    final public function add_menu_page(){
        add_submenu_page( 'options-general.php', 'Add to Pocket', 'Add to Pocket', 'activate_plugins', 'batp-options', array($this, 'output') );
        add_action( 'admin_print_footer_scripts', array($this, 'footer') );
    }

    final public function footer(){

    }
    
    final public function output(){
        ?>
        <div class="wrap">
            <h1>Add To Pocket</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'batp_api_keys' ); ?>
                <?php do_settings_sections( 'batp_api_keys' ); ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function api_request(){

    }

    public function update_option(){

    }
}


