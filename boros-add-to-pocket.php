<?php
/**
 * Plugin Name: Boros Add to Pocket
 * Plugin URI:  https://alexkoti.com
 * Description: Add URL to Pocket via API. Created because the official Chrome extension stopped working 😢.
 * Version:     1.0.3
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
 * @todo
 * - transform function boros_add_to_pocket() into class
 * - results page:
 *   - add tags interface
 *     - add tags javascript
 *     - add tags php request
 * - Uninstall/Delete hooks
 * - Internationalization
 * 
 */



/**
 * Primary ajax action
 * 
 */
class Boros_AddToPocket {

    /**
     * Action
     * 
     * @todo value to 'batp'
     * 
     */
    protected static $ajax_action = 'temp';

    /**
     * URL args
     * 
     */
    protected $url_args = array();

    /**
     * Icons SVG
     * 
     */
    protected $icons = array(
        'pocket' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M21.7684 2H2.31579C1.05263 2 0 2.95079 0 4.19094V11.2598C0 17.6673 5.38947 23 12.0421 23C18.6526 23 24 17.6673 24 11.2598V4.19094C24 2.95079 22.9895 2 21.7684 2Z" fill="#EF4056"/>
            <path fill-rule="evenodd" clip-rule="evenodd" d="M18.5749 10.9349L13.0682 16.52C12.7848 16.8691 12.3394 17 12.0154 17C11.6105 17 11.2056 16.8691 10.8817 16.52L5.45602 10.9349C4.88916 10.2804 4.80818 9.18956 5.45602 8.49142C6.06337 7.88055 7.07563 7.79328 7.68298 8.49142L12.0154 12.9857L16.4289 8.49142C16.9957 7.79328 18.008 7.88055 18.5749 8.49142C19.1417 9.18956 19.1417 10.2804 18.5749 10.9349Z" fill="white"/>
        </svg>',
        'tag' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bookmark-fill" viewBox="0 0 16 16">
            <path d="M2 2v13.5a.5.5 0 0 0 .74.439L8 13.069l5.26 2.87A.5.5 0 0 0 14 15.5V2a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"></path>
        </svg>',
        'star' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star" viewBox="0 0 16 16">
            <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.565.565 0 0 0-.163-.505L1.71 6.745l4.052-.576a.525.525 0 0 0 .393-.288L8 2.223l1.847 3.658a.525.525 0 0 0 .393.288l4.052.575-2.906 2.77a.565.565 0 0 0-.163.506l.694 3.957-3.686-1.894a.503.503 0 0 0-.461 0z"></path>
        </svg>',
        'star_fill' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star-fill" viewBox="0 0 16 16">
            <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"></path>
        </svg>',
    );

    /**
     * Define ajax_actions
     * 
     */
    public function __construct(){
        $action = self::$ajax_action = self::get_ajax_action();
        add_action( "wp_ajax_nopriv_{$action}", array($this, 'redirect_login') );
        add_action( "wp_ajax_{$action}", array($this, 'add_url') );
        add_action( "wp_ajax_{$action}_fav", array($this, 'add_fav') );
    }

    /**
     * Redirecto to login
     * 
     */
    public function redirect_login(){
        wp_redirect( wp_login_url( add_query_arg([]) ) );
        die();
    }

    /**
     * Return ajax_action
     * 
     */
    public static function get_ajax_action(){
        if( defined('BOROS_POCKET') && isset(BOROS_POCKET['ajax_action']) ){
            $ajax_action = BOROS_POCKET['ajax_action'];
        }
        else{
            $ajax_action = get_option('batp_ajax_action', self::$ajax_action);
        }
        return $ajax_action;
    }

    /**
     * Proccess logged request
     * 
     */
    public function add_url(){
        $this->request( 'add_url' );
    }

    public function add_fav(){
        $this->request( 'add_fav' );
    }

    protected function request( $action ){
        
        $this->check_url();

        $this->check_tokens();

        switch( $action ){
            case 'add_url':
                $this->request_add_url();
                break;

            case 'add_fav':
                $this->request_add_fav();
                break;

            default;
                break;
        }

        die();
    }

    /**
     * Check if URL is defined or show the bookmarklet page
     * 
     */
    protected function check_url(){
        if( empty($_GET['url']) ){
            $body = sprintf(
                '%s<br><h1>Add to Pocket Bookmarklet</h1>
                <p>%s</p>
                <p><img src="%sbookmarklet.gif" alt="bookmarklet" class="img howto"></p>',
                $this->icons['pocket'], 
                boros_add_to_pocket_bookmarklet(), 
                plugins_url( '/', __FILE__ )
            );

            $this->html_output( 'Add to Pocket Bookmarklet', $body, 'bookmarklet' );
            die();
        }
    }

    /**
     * check if the tokens are defined/valid
     * 
     */
    protected function check_tokens(){
        if( defined('BOROS_POCKET') && isset(BOROS_POCKET['consumer_key']) && isset(BOROS_POCKET['access_token']) ){
            $this->url_args = [
                'consumer_key' => BOROS_POCKET['consumer_key'],
                'access_token' => BOROS_POCKET['access_token'],
            ];
        }
        else{
            $this->url_args = [
                'consumer_key' => get_option('batp_consumer_key'),
                'access_token' => get_option('batp_access_token'),
            ];
        }
    
        if( empty($this->url_args['consumer_key']) || empty($this->url_args['access_token']) ){
            wp_die('Add to Pocket: API keys not set.');
        }
    }

    /**
     * Execute the request to add URL to pocket
     * 
     */
    protected function request_add_url(){

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
        
        // test force error on add
        //$this->url_args['access_token'] = 'force-error';

        /**
         * Build request URL
         * 
         */
        $pocket_url = add_query_arg($this->url_args, 'https://getpocket.com/v3/send');
    
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
            $class = 'result';
            $body[] = sprintf(
                '<header>
                    <h1>%sAdded!</h1> 
                    <div class="actions"><span class="fav">%s</span> <span class="tag">%s</span></div>
                    <div class="tags-list"><span>Lorem</span> <span>Ipsum</span> <span>Dolor Sit</span></div>
                </header>', 
                $this->icons['pocket'],
                $this->icons['star'],
                $this->icons['tag'],
                $this->icons['pocket']
            );
            foreach( $response_body->action_results as $result ){
                //pre($result, 'result', false);
    
                // check images
                if( isset($result->top_image_url) ){
                    $body[] = sprintf('<img src="%s" class="img item-image" alt="%s">', $result->top_image_url, $result->title);
                }
                elseif( isset($result->images) ){
                    foreach( $result->images as $index => $image ){
                        if( $index == 1 ){
                            $body[] = sprintf('<img src="%s" class="img item-image" alt="%s">', $image->src, $result->title);
                        }
                    }
                }
    
                $body[] = sprintf(
                    '<h2 class="item-title" data-item_id="%s">
                        <img src="https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&size=24&url=%s" alt="favicon"> 
                        <a href="%s" target="_blank">%s</a>
                    </h2>', 
                    $result->item_id,
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
            $body[] = sprintf('<h1>%sAdd to Pocket</h1>', $this->icons['pocket']);
            $body[] = '<h2>Request error</h2>';
            $body[] = sprintf('<p>%s %s</p>', $response_code, $response_message);
            $class = 'error';
        }

        $this->html_output( $title, implode('', $body), $class );
    }

    /**
     * CSS
     * 
     */
    protected function css(){
        ob_start();
        ?>
        <style>
        body {font-family:monospace;font-size:14px;margin:1rem auto;padding:0 1rem;max-width:550px;}
        body.bookmarklet, body.error {text-align:center;}
        header {display:flex;justify-content:space-between;flex-wrap:wrap;align-items:center;margin:1rem 0;}
        h1 {margin: 0;}
        h1 svg {margin: 0 10px -2px 0;}
        .actions {display:flex;display:none;}
        .actions span {color:#ef4056;cursor:pointer;width:22px;height:22px;margin-left:10px;}
        .actions .disabled {pointer-events:none;opacity:0.5;}
        .actions svg {width:100%;height:100%;}
        .tags-list {display:flex;justify-content:flex-end;gap:5px;width:100%;display:none;}
        .tags-list span {background:#ccc;border-radius:12px;cursor:pointer;font-size:80%;padding:4px 10px;}
        .item-image {display:block;margin:0 auto 1rem;max-height:300px;}
        .item-title {font-size:18px;}
        .item-title img {vertical-align: text-bottom;}
        .item-url {word-wrap:break-word;}
        .button-secondary {background:#ef4056;border-radius:4px;color:#fff;display:inline-block;padding:5px 10px;text-decoration:none;}
        .img {background: rgba(0, 0, 0, 0.07);border: 1px solid #ccc;padding:0.3rem;max-width:calc(100% - 0.6rem - 2px);}
        .howto {margin:20px 0;width:330px;}
        </style>
        <?php
        $input = ob_get_contents();
        ob_end_clean();
        return $input;
    }

    protected function js(){
        ob_start();
        ?>
        <script src="<?php echo site_url('/wp-includes/js/jquery/jquery.min.js'); ?>" id="jquery-core-js"></script>
        <script>
        jQuery(document).ready(function($){
            console.log('batp');
            $('.actions .fav').on('click', function(){
                var item_id = $('.item-title').attr('data-item_id');
                console.log('add fav:' + item_id);
                $(this).addClass('disabled');
            });
        });
        </script>
        <?php
        $input = ob_get_contents();
        ob_end_clean();
        return $input;
    }

    /**
     * HTML template output
     * 
     */
    protected function html_output( $title, $body, $class = '' ){
        printf('<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>%s</title>
            %s
        </head>
        <body class="%s">
            %s
            %s
        </body>
        </html>', $title, $this->css(), $class, $body, $this->js());
    }
}
$batp = new Boros_AddToPocket();



/**
 * Bookmarklet
 * 
 * @todo move to Boros_AddToPocket as a static function
 * 
 */
function boros_add_to_pocket_bookmarklet(){
    $action   = Boros_AddToPocket::get_ajax_action();
    $ajax_url = add_query_arg('action', $action, admin_url('admin-ajax.php'));
    $popup    = ", 'add-to-pocket', 'scrollbars=no,resizable=no,status=no,location=no,toolbar=no,menubar=no,width=600,height=600,left=100,top=100'";
    $link     = "javascript:{window.open('{$ajax_url}&url='+encodeURIComponent(window.location.href){$popup})}";
    $bookmark = sprintf('Drag this link to the bookmarks bar: <a href="%s" class="button-secondary">+ add to pocket</a>', $link);
    return $bookmark;
}



/**
 * Init Admin controls if constant not defined
 * 
 * @todo move to Boros_AddToPocket construct
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
     * Whitelist of options names allowed to be saved in ajax requests
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
        add_action( 'removable_query_args', array($this, 'remove_args_after_save') );
        add_action( 'admin_init', array($this, 'register_settings') );
        add_action( 'admin_menu', array($this, 'add_menu_page') );
        add_action( 'wp_ajax_batp_get_request_token', array($this, 'get_request_token') );
        add_action( 'wp_ajax_batp_get_access_token', array($this, 'get_access_token') );
        add_action( 'wp_ajax_batp_update_option', array($this, 'update_option') );
    }

    /**
     * Remove batp_auth_status querystring, after saving option page
     * 
     */
    function remove_args_after_save( $args ){
        $args[] = 'batp_request_status';
        return $args;
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
            'batp_ajax_action'   => get_option('batp_ajax_action'),
        );

        /**
         * Check if is redirect from Pocket authorization page, but check previous value(in case of reloading page with GET param)
         * 
         */
        if( isset($_GET['batp_request_status']) && $_GET['batp_request_status'] == 'app_authorized' && $this->options['batp_auth_status'] != 'access_created' ){
            update_option('batp_auth_status', 'app_authorized');
            $this->options['batp_auth_status'] = 'app_authorized';
        }

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
            array(
                'type'  => 'text',
                'name'  => 'batp_ajax_action',
                'label' => 'Custom ajax_action name',
                'extra' => '<br>Define a custom action, replace the default value <code>batp</code>',
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
                if( empty($option) && !in_array($args['field_name'], array('batp_consumer_key', 'batp_ajax_action')) ){
                    $disabled = 'readonly';
                }

                if( $args['field_name'] == 'batp_request_token' && $this->options['batp_auth_status'] != 'token_generated' ){
                    $disabled = 'readonly';
                }

                if( $args['field_name'] == 'batp_access_token' && $this->options['batp_auth_status'] != 'app_authorized' ){
                    $disabled = 'readonly';
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
                else{
                    echo $args['extra'];
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

                if( empty($this->options['batp_request_token']) || $this->options['batp_auth_status'] != 'token_generated' ){
                    $disabled = 'disabled';
                }

                printf(
                    '<a href="https://getpocket.com/auth/authorize?request_token=%s&redirect_uri=%s" id="authorize-link" class="button-secondary %s">Authorize App</a>', 
                    $this->options['batp_request_token'], 
                    urlencode( site_url(add_query_arg('batp_request_status', 'app_authorized')) ), 
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
                    '<p>%s <span class="info">🛈 how to</span><img src="%sbookmarklet-admin.gif" alt="bookmarklet"></p>', 
                    boros_add_to_pocket_bookmarklet(), 
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
        if( $this->options['batp_auth_status'] == 'app_authorized' ){
            echo '<span><br>Curent Request Token already used, please request another one.</span>';
        }
    }

    /**
     * Get Access Token
     * 
     */
    protected function access_token_button(){
        $request_token        = $this->options['batp_request_token'];
        $authorization_status = $this->options['batp_auth_status'];
        $disabled             = 'disabled';
        if( !empty($request_token) && $authorization_status == 'app_authorized' ){
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
            'redirect_uri' => site_url(add_query_arg([])),
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
     * @todo set autoload 'no'
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
                update_option( 'batp_auth_status', 'token_generated' );
            }
            elseif( $option_name == 'batp_access_token' ){
                update_option( 'batp_auth_status', 'access_created' );
            }
            wp_send_json_success(array('message' => 'Option updated'));
        }
        else{
            wp_send_json_error(array('message' => 'Request failure'));
        }
    }
}


