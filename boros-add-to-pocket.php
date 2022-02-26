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


