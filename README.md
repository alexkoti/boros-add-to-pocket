# Boros Add to Pocket

WordPress plugin to add URL to Pocket via API. Created because the official Chrome extension stopped working 😢.

## Create a Pocket App
Visit https://getpocket.com/developer/, create new app and copy the "consumer key" 

## wp-config.php installation:
Generate API keys via curl or Postman (ref: https://getpocket.com/developer/docs/authentication)

Register constant BOROS_POCKET in wp-config.php:
define( 'BOROS_POCKET', array('consumer_key' => 'XXXX', 'access_token' => 'XXXX') );

Access ajax address to get the bookmarklet: 
SITE.com/wp-admin/admin-ajax.php?action=batp

## wp-admin installation:
Acess "Settings > Add to Pocket"

1) Save Consumer key field
2) Click "Obtain a Request Token" button
3) Click the "Authorize App" link and confirm authorization in Pocket page. You will be redirected back to WordPress
4) Click "Obtain a Access Token" button

All steps are autosaved.
