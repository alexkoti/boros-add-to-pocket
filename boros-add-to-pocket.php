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
 * Redirect to login if not authenticated
 * 
 */
add_action( 'wp_ajax_nopriv_batp', function(){
    wp_redirect( wp_login_url( site_url( add_query_arg(array()) ) ) );
    die();
} );



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
        add_action( 'wp_ajax_batp_get_request_token', array($this, 'get_request_token') );
        add_action( 'wp_ajax_batp_get_access_token', array($this, 'get_access_token') );
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
                echo 'Add to Pocket configuration';
            },
            'batp_api_keys'
        );

        $fields = array(
            array(
                'type'  => 'text',
                'name'  => 'batp_consumer_key',
                'label' => 'Consumer key',
                'extra' => false,
            ),
            array(
                'type'  => 'text',
                'name'  => 'batp_request_token',
                'label' => 'Request Token',
                'extra' => array($this, 'authorization_button'),
            ),
            array(
                'type'  => 'authorize',
            ),
            array(
                'type'  => 'text',
                'name'  => 'batp_access_token',
                'label' => 'Access Token',
                'extra' => array($this, 'access_token_button'),
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
                if( is_callable($args['extra']) ){
                    call_user_func( $args['extra'] );
                }
            }, 
            'batp_api_keys', 
            'section_apis',
            [
                'label_for'  => "{$field['name']}-id",
                'class'      => 'classe-html-tr',
                'field_name' => $field['name'],
                'extra'      => $field['extra'],
            ]
        );
    }

    private function add_setting_field_authorize( $field ){
        add_settings_field(
            'authorize', 
            'Authorization', 
            function(){
                $authorized = get_option('batp_authorized');
                if( empty($authorized) ){
                    $request_token = get_option('batp_request_token');
                    if( !empty($request_token) ){
                        printf('<a href="https://getpocket.com/auth/authorize?request_token=%s&redirect_uri=%s" id="authorize-link">Authorize App</a>', $request_token, site_url(add_query_arg('batp_authorized', '1')));
                    }
                }
                else{
                    echo 'Already authorized';
                }
            }, 
            'batp_api_keys', 
            'section_apis'
        );
    }

    protected function authorization_button(){
        ?>
        <button type="button" class="button-secondary" id="authorization-button">Obtain a Request Token</button>
        <?php
    }

    protected function access_token_button(){
        ?>
        <button type="button" class="button-secondary" id="access-token-button">Obtain a Access Token</button>
        <?php
    }

    /**
     * Add admin menu item
     * 
     */
    final public function add_menu_page(){

        //if( isset($_GET['batp_authorized']) && $_GET['batp_authorized'] == 1 ){
        //    update_option( 'batp_authorized', true );
        //}

        add_submenu_page( 'options-general.php', 'Add to Pocket', 'Add to Pocket', 'activate_plugins', 'batp-options', array($this, 'output') );
        add_action( 'admin_print_footer_scripts', array($this, 'footer') );
    }

    final public function footer(){
        ?>
        <script>
        jQuery(document).ready(function($){
            $('#authorization-button').on('click', function(){
                console.log('authorization-button');
                var consumer_key = $('#batp_consumer_key-id').val();
                var data = {
                    action:       'batp_get_request_token',
                    consumer_key: consumer_key,
                }
                console.log(data);
                $.post( ajaxurl, data, function( response ){
                    console.log( response );
                    if( response.success == true ){
                        var request_token_field = $('#batp_request_token-id');
                        request_token_field.val( response.data.code );
                        batp_update_option( request_token_field );
                    }
                    else{
                        alert(response.data.message);
                    }
                });
            });

            function batp_update_option( elem ){
                var data = {
                    action:       'batp_update_option',
                    option_name:  elem.attr('name'),
                    option_value: elem.val(),
                }
                $.post( ajaxurl, data, function( response ){
                    console.log( response );
                    if( response.success == true ){
                        console.log( 'sucesso' );
                        if( elem.attr('id') == 'batp_request_token-id' ){
                            var url = new URL( $('#authorize-link').attr('href') );
                            var params = url.searchParams;
                            params.set('request_token', elem.val());
                            url.search = params.toString();
                            var new_url = url.toString();
                            $('#authorize-link').attr('href', new_url);
                        }
                    }
                    else{
                        alert(response.data.message);
                    }
                });
            }

            $('#access-token-button').on('click', function(){
                console.log('access-token-button');
                var consumer_key  = $('#batp_consumer_key-id').val();
                var request_token = $('#batp_request_token-id').val();
                var data = {
                    action:        'batp_get_access_token',
                    consumer_key:  consumer_key,
                    request_token: request_token,
                }
                console.log(data);
                $.post( ajaxurl, data, function( response ){
                    console.log( response );
                    if( response.success == true ){
                        var access_token_field = $('#batp_access_token-id');
                        access_token_field.val( response.data.access_token );
                        batp_update_option( access_token_field );
                    }
                    else{
                        alert(response.data.message);
                    }
                });
            });
        });
        </script>
        <?php
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

    public function get_request_token(){
        
        $consumer_key = $_POST['consumer_key'];

        $params = array(
            'consumer_key' => $consumer_key,
            'redirect_uri' => site_url(add_query_arg()),
        );

        $response = wp_remote_post('https://getpocket.com/v3/oauth/request', array(
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($params),
            'method'      => 'POST',
            'data_format' => 'body',
        ));

        if( wp_remote_retrieve_response_code( $response ) == 200 ){
            $code = str_replace( 'code=', '', wp_remote_retrieve_body($response) );
            wp_send_json_success(array('code' => $code));
        }
        else{
            wp_send_json_error(array('message' => 'Request failure'));
        }
    }
    
    public function get_access_token(){
        
        $consumer_key  = $_POST['consumer_key'];
        $request_token = $_POST['request_token'];

        $params = array(
            'consumer_key'  => $consumer_key,
            'code'          => $request_token,
        );

        $response = wp_remote_post('https://getpocket.com/v3/oauth/authorize', array(
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($params),
            'method'      => 'POST',
            'data_format' => 'body',
        ));

        if( wp_remote_retrieve_response_code( $response ) == 200 ){
            parse_str( wp_remote_retrieve_body($response), $response_values );
            pel(wp_remote_retrieve_body($response));
            pel($response_values);
            wp_send_json_success(array('access_token' => $response_values['access_token']));
        }
        else{
            wp_send_json_error(array('message' => 'Request access_token failure'));
        }
    }

    public function update_option(){

        $option_name  = $_POST['option_name'];
        $option_value = $_POST['option_value'];

        $updated = update_option( $option_name, $option_value );
        if( $updated === true ){
            wp_send_json_success(array('message' => 'Option updated'));
        }
        else{
            wp_send_json_error(array('message' => 'Request failure'));
        }
    }
}


