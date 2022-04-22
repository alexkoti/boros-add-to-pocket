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
 * - options in wp-config constant: 
 *   - custom ajax action name
 *   - add tags
 * - results page:
 *   - better design
 *   - add tags interface
 *     - add tags javascript
 *     - add tags php request
 * - admin page:
 *   - option: custom ajax action name
 *   - option: delete tokens
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
        boros_add_to_pocket_bookmarklet();
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
    if( defined('BOROS_POCKET') ){
        $url_args = [
            'consumer_key' => BOROS_POCKET['consumer_key'],
            'access_token' => BOROS_POCKET['access_token'],
        ];
    }
    else{
        $url_args = [
            'consumer_key' => get_option('batp_consumer_key'),
            'access_token' => get_option('batp_access_token'),
        ];
    }

    if( empty($url_args['consumer_key']) || empty($url_args['access_token']) ){
        wp_die('Add to Pocket: API keys not set.');
    }

    // test force error on add
    //$url_args['access_token'] = 'force-error';

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

    $response_code    = wp_remote_retrieve_response_code( $data );
    $response_message = wp_remote_retrieve_response_message( $data );
    $response_body    = json_decode( wp_remote_retrieve_body( $data ) );

    $body  = array();
    $title = '';

    if( $response_code == 200 ){
        $title = 'Added to Pocket';
        $body[] = '<h1>Added to Pocket</h1>';
        foreach( $response_body->action_results as $result ){
            //pre($result, 'result', false);
            if( isset($result->images) ){
                foreach( $result->images as $index => $image ){
                    if( $index == 1 ){
                        $body[] = sprintf('<img src="%s" class="item-image" alt="%s">', $image->src, $result->title);
                    }
                }
            }
            $body[] = sprintf(
                '<h2 class="item-title">
                    <img src="https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=24&url=%s" alt="favicon"> 
                    <a href="%s">%s</a>
                </h2>', 
                $result->normal_url, 
                $result->normal_url,
                $result->title
            );
            $body[] = sprintf('<p class="item-url">%s</p>', $result->normal_url);
            $body[] = sprintf('<hr><p class="item-excerpt">%s</p>', $result->excerpt);
        }
    }
    else{
        $title  = 'Request error';
        $body[] = '<h1>Add to Pocket</h1>';
        $body[] = '<h2>Request error</h2>';
        $body[] = sprintf('<p>%s %s</p>', $response_code, $response_message);
    }

    printf('<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>%s</title>
        <style>
        body {font-family:monospace;margin:0 auto;max-width:550px;}
        .item-image {border:1px solid;display:block;margin:0 auto 1rem;padding:10px;}
        .item-title {font-size:18px;line-height:26px;}
        img {vertical-align:text-bottom;}
        </style>
    </head>
    <body>
        %s
    </body>
    </html>', $title, implode('', $body));

    die();
}



/**
 * Bookmarklet
 * 
 */
function boros_add_to_pocket_bookmarklet( $echo = true ){
    $ajax_url = add_query_arg('action', 'batp', admin_url('admin-ajax.php'));
    $popup    = ", 'add-to-pocket', 'scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=600,height=500,left=100,top=100'";
    $link     = "javascript:{window.open('{$ajax_url}&url='+encodeURIComponent(window.location.href){$popup})}";
    $bookmark = sprintf('Drag this link to the bookmarks bar: <a href="%s" class="button-secondary">+ add to pocket</a>', $link);
    if( $echo == true ){
        echo $bookmark;
    }
    return $bookmark;
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
     * Whitelist of options names allowed to be saved
     * 
     */
    protected $allowed_options = array(
        'batp_consumer_key',
        'batp_request_token',
        'batp_access_token',
    );

    /**
     * Options values
     * 
     */
    protected $options = array();

    /**
     * Static messages counter
     * Used to define messages ids
     * 
     */
    protected $message_count = 0;

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
        
        /**
         * Retrieve all settings
         * 
         */
        $this->options = array(
            'batp_consumer_key'  => get_option('batp_consumer_key'),
            'batp_request_token' => get_option('batp_request_token'),
            'batp_access_token'  => get_option('batp_access_token'),
            'batp_auth_status'   => get_option('batp_auth_status'),
        );

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
                'type'    => 'message',
                'label'   => 'Step 1',
                'message' => 'Visit <a href="https://getpocket.com/developer/" target="_blank">https://getpocket.com/developer/</a>, create new app, copy the <code>consumer key</code>,  paste in the field below, and hit <em>Save Consumer Key</em>.',
            ),
            array(
                'type'  => 'text',
                'name'  => 'batp_consumer_key',
                'label' => 'Consumer key',
                'extra' => array($this, 'consumer_button'),
            ),
            array(
                'type'    => 'message',
                'label'   => 'Step 2',
                'message' => 'Click the <em>Obtain a Request Token</em> button and wait the key response.',
            ),
            array(
                'type'  => 'text',
                'name'  => 'batp_request_token',
                'label' => 'Request Token',
                'extra' => array($this, 'request_button'),
            ),
            array(
                'type'    => 'message',
                'label'   => 'Step 3',
                'message' => 'After Request Token, click <em>Authorize App</em> link and confirm authorization in Pocket page. You will be redirected back to this page.',
            ),
            array(
                'type'  => 'authorize',
            ),
            array(
                'type'    => 'message',
                'label'   => 'Step 4',
                'message' => 'After authorize app, click <em>Obtain a Access Token</em> and wait the response.<br>Note: every time you need generate a Access Token, you need to do Steps 2 and 3 before request a new Access Token.',
            ),
            array(
                'type'  => 'text',
                'name'  => 'batp_access_token',
                'label' => 'Access Token',
                'extra' => array($this, 'access_token_button'),
            ),
            array(
                'type'  => 'bookmarklet',
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
        global $plugin_page;

        /**
         * Always register settings
         * 
         */
        register_setting( 'batp_api_keys', $field['name'] );

        /**
         * Add settings fields only in plugin page
         * 
         */
        if( $plugin_page != 'batp-options' ){
            return;
        }

        add_settings_field(
            $field['name'], 
            $field['label'], 
            function( $args ){
                $option = $this->options[ $args['field_name'] ];
                $disabled = '';
                if( empty($option) && $args['field_name'] != 'batp_consumer_key' ){
                    $disabled = 'disabled';
                }
                
                // text field output
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text code" %s>',
                    esc_attr( $args['label_for'] ),
                    $args['field_name'],
                    esc_attr( $option ),
                    $disabled
                );

                // hidden nonce field output
                printf(
                    '<input type="hidden" name="nonce-%s" value="%s">',
                    $args['field_name'],
                    wp_create_nonce( $args['field_name'] )
                );

                if( is_callable($args['extra']) ){
                    call_user_func( $args['extra'] );
                }
            }, 
            'batp_api_keys', 
            'section_apis',
            [
                'label_for'  => "{$field['name']}-id",
                'class'      => 'batp-field-row',
                'field_name' => $field['name'],
                'extra'      => $field['extra'],
            ]
        );
    }

    /**
     * Row with static message
     * 
     */
    private function add_setting_field_message( $field ){
        $this->message_count++;
        add_settings_field(
            "message-{$this->message_count}", 
            $field['label'], 
            function( $args ){
                echo $args['message'];
            }, 
            'batp_api_keys', 
            'section_apis',
            [
                'class'   => 'batp-message-row',
                'message' => $field['message'],
            ]
        );
    }

    /**
     * Row with authorization link
     * The link is dynamically updated on request_token response
     * 
     */
    private function add_setting_field_authorize( $field ){
        add_settings_field(
            'authorize', 
            'Authorization', 
            function(){
                $disabled = '';
                $append   = '';

                if( empty($this->options['batp_request_token']) || $this->options['batp_auth_status'] != 'generated' ){
                    $disabled = 'disabled';
                }

                printf(
                    '<a href="https://getpocket.com/auth/authorize?request_token=%s&redirect_uri=%s" id="authorize-link" class="button-secondary %s">Authorize App</a>', 
                    $this->options['batp_request_token'], 
                    site_url(add_query_arg()), 
                    $disabled
                );
            }, 
            'batp_api_keys', 
            'section_apis',
            [
                'class' => 'batp-field-row',
            ]
        );
    }

    /**
     * Row with bookmarklet HTML, gif help and example constant code
     * 
     */
    private function add_setting_field_bookmarklet( $field ){
        $row_class = 'batp-bookmarklet-row';
        if( !empty($this->options['batp_consumer_key']) && !empty($this->options['batp_access_token'])  ){
            $row_class .= ' active';
        }

        add_settings_field(
            'bookmarklet', 
            'Bookmarklet', 
            function(){
                printf(
                    '<p>%s <span class="info">🛈 how to</span><img src="%sbookmarklet.gif" alt="bookmarklet"></p>', 
                    boros_add_to_pocket_bookmarklet( false ), 
                    plugins_url( '/', __FILE__ )
                );

                $consumer_key = !empty($this->options['batp_consumer_key']) ? $this->options['batp_consumer_key'] : '{CONSUMER_KEY}';
                $access_token = !empty($this->options['batp_access_token']) ? $this->options['batp_access_token'] : '{ACCESS_TOKEN}';
                $constant = "define( 'BOROS_POCKET', array('consumer_key' => '{$consumer_key}', 'access_token' => '{$access_token}') );";
                printf('<hr><p>Optional: add the following constant in your wp-config.php file: <br><code id="batp-constant-code">%s</code></p>', $constant);
                echo '<p>After that the current admin page will be disabled. Remove the constant to back the admin page.</p>';
            }, 
            'batp_api_keys', 
            'section_apis',
            [
                'class' => $row_class,
            ]
        );
    }

    /**
     * Button to save Consumer Key
     * This button is never disabled
     * 
     */
    protected function consumer_button(){
        ?>
        <button type="button" class="button-secondary" id="consumer-button">Save Consumer Key</button><span class="spinner"></span>
        <?php
    }

    /**
     * Get Request Token
     * 
     */
    protected function request_button(){
        $disabled = empty($this->options['batp_consumer_key']) ? 'disabled' : '';
        ?>
        <button type="button" class="button-secondary" id="request-button" <?php echo $disabled; ?>>Obtain a Request Token</button><span class="spinner"></span>
        <?php
    }

    /**
     * Get Access Token
     * 
     */
    protected function access_token_button(){
        $request_token        = $this->options['batp_request_token'];
        $authorization_status = $this->options['batp_auth_status'];
        $disabled             = 'disabled';
        if( !empty($request_token) && $authorization_status != 'authorized' ){
            $disabled = '';
        }
        ?>
        <button type="button" class="button-secondary" id="access-token-button" <?php echo $disabled; ?>>Obtain a Access Token</button><span class="spinner"></span>
        <?php
    }

    /**
     * Add admin menu item
     * 
     */
    final public function add_menu_page(){
        add_submenu_page( 'options-general.php', 'Add to Pocket', 'Add to Pocket', 'activate_plugins', 'batp-options', array($this, 'output') );
        add_action( 'admin_head', array($this, 'styles') );
        add_action( 'admin_print_footer_scripts', array($this, 'footer') );
    }

    /**
     * Styles on head
     * 
     */
    final public function styles(){
        ?>
        <style>
        .batp-field-row a.disabled {
            opacity: 0.7;
            pointer-events: none;
        }
        .batp-field-row .spinner {
            float: none;
            margin-top: 0;
        }
        .batp-message-row th,
        .batp-message-row td {
            padding-bottom: 0;
        }
        .batp-message-row + .batp-field-row th,
        .batp-message-row + .batp-field-row td {
            padding-top: 0;
        }
        .batp-bookmarklet-row {
            display: none;
        }
        .batp-bookmarklet-row.active {
            display: table-row;
        }
        .batp-bookmarklet-row .button-secondary {
            vertical-align: middle;
        }
        .batp-bookmarklet-row img {
            background: rgba(0, 0, 0, 0.07);
            border: 1px solid #8c8f94;
            margin: 10px 0 0;
            padding: 4px;
            width: 280px;
            display: none;
        }
        .batp-bookmarklet-row img.active {
            display: block;
        }
        .batp-bookmarklet-row .info {
            margin-left: 15px;
            cursor: pointer;
        }
        </style>
        <?php
    }

    /**
     * Javascript on footer
     * 
     */
    final public function footer(){
        ?>
        <script>
        jQuery(document).ready(function($){

            /**
             * Save consumer_key
             * 
             */
            $('#consumer-button').on('click', function(){
                var field = $('#batp_consumer_key-id');
                spinner( field, 'on' );
                $('#request-button, #batp_request_token-id').prop('disabled', true);
                batp_update_option(field, function(){
                    $('#request-button').prop('disabled', false);
                });
            });

            /**
             * Get request_token via ajax and update authorize link url
             * 
             */
            $('#request-button').on('click', function(){
                var field = $('#batp_request_token-id');
                spinner( field, 'on' );

                var link = $('#authorize-link');
                link.addClass('disabled');

                var data = {
                    action:       'batp_get_request_token',
                    consumer_key: $('#batp_consumer_key-id').val(),
                    security:     field.next('input[type="hidden"]').val(),
                }
                
                $.ajax({
                    type:    'POST',
                    url:     ajaxurl,
                    data:    data,
                    success: function( response ){
                        if( response.success == true ){
                            field.val( response.data.code );
                            batp_update_option(field, function(){
                                var url     = new URL( link.attr('href') );
                                var params  = url.searchParams;
                                params.set('request_token', field.val());
                                url.search  = params.toString();
                                var new_url = url.toString();
                                link.attr('href', new_url).removeClass('disabled');
                            });
                        }
                        else{
                            alert(response.data.message);
                            spinner( field, 'off', false );
                        }
                    },
                    error: function( XMLHttpRequest, textStatus, errorThrown ){
                        alert('Error in server response.');
                            spinner( field, 'off', false );
                    }
                });
            });

            /**
             * Get access_token via ajax
             * On success, show the bookmarklet row and update constant example code
             * 
             */
            $('#access-token-button').on('click', function(){
                var field = $('#batp_access_token-id');
                spinner( field, 'on' );
                
                var consumer_key  = $('#batp_consumer_key-id').val();
                var request_token = $('#batp_request_token-id').val();
                var data = {
                    action:        'batp_get_access_token',
                    consumer_key:  consumer_key,
                    request_token: request_token,
                    security:      field.next('input[type="hidden"]').val(),
                }
                
                $.ajax({
                    type:    'POST',
                    url:     ajaxurl,
                    data:    data,
                    success: function( response ){
                        console.log( response );
                        if( response.success == true ){
                            field.val( response.data.access_token );
                            batp_update_option(field, function(){
                                var constant_code = $('#batp-constant-code').text().replace('{ACCESS_TOKEN}', $('#batp_access_token-id').val());
                                $('#batp-constant-code').text(constant_code);
                                $('.batp-bookmarklet-row').addClass('active');
                            });
                        }
                        else{
                            alert(response.data.message);
                            spinner( field, 'off', false );
                        }
                    },
                    error: function( XMLHttpRequest, textStatus, errorThrown ){
                        alert('Error in server response.');
                        spinner( field, 'off', false );
                    }
                });
            });

            /**
             * Toggle help image visibility
             * 
             */
            $('.batp-bookmarklet-row .info').on('click', function(){
                $(this).next('img').toggleClass('active');
            });

            /**
             * Update option via ajax 
             * 
             */
            function batp_update_option( elem, callback ){

                var data = {
                    action:       'batp_update_option',
                    option_name:  elem.attr('name'),
                    option_value: elem.val(),
                    security:     elem.next('input[type="hidden"]').val(),
                }
                console.log(data);

                $.ajax({
                    type:    'POST',
                    url:     ajaxurl,
                    data:    data,
                    success: function( response ){
                        console.log( response );
                        if( response.success == true ){
                            callback();
                        }
                        else{
                            alert(response.data.message);
                        }
                        spinner( elem, 'off' );
                    },
                    error: function( XMLHttpRequest, textStatus, errorThrown ){
                        alert('Error in server response.');
                        spinner( elem, 'off' );
                    }
                });
            }

            /**
             * Update spinner indicator
             * Disable input and associated button, always enable button on response, but check if resquest success before
             * enable input.
             * @elem jquery element of target input. The spinner will be searched from his parent.
             * 
             */
            function spinner( elem, state, sucess = true ){
                var spinner = elem.parent().find('.spinner');
                var button  = elem.parent().find('button');
                if( state == 'on' ){
                    elem.prop('disabled', true);
                    button.prop('disabled', true);
                    spinner.addClass('is-active');
                }
                else{
                    if( sucess == true ){
                        elem.prop('disabled', false);
                    }
                    button.prop('disabled', false);
                    spinner.removeClass('is-active');
                }
            }
        });
        </script>
        <?php
    }
    
    /**
     * Output the admin page
     * 
     */
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

    /**
     * Save Request Token
     * 
     */
    public function get_request_token(){
        
        $consumer_key = $_POST['consumer_key'];

        $ref_check = check_ajax_referer( 'batp_request_token', 'security', false );
        if( $ref_check == false ){
            wp_send_json_error(array('message' => 'Security error'));
        }

        if( empty($consumer_key) ){
            wp_send_json_error(array('message' => 'Empty value'));
        }

        if( !current_user_can('manage_options') ){
            wp_send_json_error(array('message' => 'You do not have permission to request a new token'));
        }

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
            wp_send_json_error(array('message' => wp_remote_retrieve_header($response, 'x-error')));
        }
    }
    
    /**
     * Save Access Token
     * 
     */
    public function get_access_token(){
        
        $consumer_key  = $_POST['consumer_key'];
        $request_token = $_POST['request_token'];

        $ref_check = check_ajax_referer( 'batp_access_token', 'security', false );
        if( $ref_check == false ){
            wp_send_json_error(array('message' => 'Security error'));
        }

        if( empty($consumer_key) ){
            wp_send_json_error(array('message' => 'Empty Consumer Key'));
        }

        if( empty($request_token) ){
            wp_send_json_error(array('message' => 'Empty Request Token'));
        }

        if( !current_user_can('manage_options') ){
            wp_send_json_error(array('message' => 'You do not have permission to request a new token'));
        }

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
            wp_send_json_success(array('access_token' => $response_values['access_token']));
        }
        else{
            wp_send_json_error(array('message' => wp_remote_retrieve_header($response, 'x-error')));
        }
    }

    /**
     * Update single option
     * 
     */
    public function update_option(){

        $option_name  = $_POST['option_name'];
        $option_value = $_POST['option_value'];

        if( !in_array( $option_name, $this->allowed_options ) ){
            wp_send_json_error(array('message' => 'Option not allowed'));
        }

        $ref_check = check_ajax_referer( $option_name, 'security', false );
        if( $ref_check == false ){
            wp_send_json_error(array('message' => 'Security error'));
        }

        if( empty($option_value) ){
            wp_send_json_error(array('message' => 'Empty value'));
        }

        if( !current_user_can('manage_options') ){
            wp_send_json_error(array('message' => 'You do not have permission to edit this option'));
        }

        if( $this->options[$option_name] == $option_value ){
            wp_send_json_success(array('message' => 'Option value already saved'));
        }

        $updated = update_option( $option_name, $option_value );
        if( $updated === true ){
            if( $option_name == 'batp_request_token' ){
                update_option( 'batp_auth_status', 'generated' );
            }
            elseif( $option_name == 'batp_access_token' ){
                update_option( 'batp_auth_status', 'authorized' );
            }
            wp_send_json_success(array('message' => 'Option updated'));
        }
        else{
            wp_send_json_error(array('message' => 'Request failure'));
        }
    }
}


