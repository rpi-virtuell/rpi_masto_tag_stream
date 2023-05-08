<?php
/*
Plugin Name: RPI-Masto-Tag-Stream
Plugin URI: https://rpi-virtuell.de
Description: Importiere Mastodon-Beiträge mit einem bestimmten Tag in WordPress.
Version: 1.0
Author: Joachim Happel
Author URI: https://
*/

// Funktion zum Abrufen von Daten von der Mastodon-API
function rpi_masto_tag_stream_get_mastodon_data($url, $accessToken) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $accessToken
    ));
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}

// Funktion zum Hinzufügen der Option-Seite
function rpi_masto_tag_stream_add_options_page() {
    add_options_page(
        'RPI-Masto-Tag-Stream Einstellungen',
        'RPI-Masto-Tag-Stream',
        'manage_options',
        'rpi_masto_tag_stream_settings',
        'rpi_masto_tag_stream_render_options_page'
    );
}
add_action('admin_menu', 'rpi_masto_tag_stream_add_options_page');

add_shortcode('mastodon-stream', 'rpi_masto_tag_stream_get_data');

// Funktion zum Rendern der Option-Seite
function rpi_masto_tag_stream_render_options_page() {
    ?>
    <div class="wrap">
        <h2>RPI-Masto-Tag-Stream Einstellungen</h2>
        <form method="post" action="options.php">
            <?php settings_fields('rpi_masto_tag_stream_options'); ?>
            <?php do_settings_sections('rpi_masto_tag_stream_settings'); ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Funktion zum Registrieren der Einstellungen und Felder
function rpi_masto_tag_stream_register_settings() {
    register_setting('rpi_masto_tag_stream_options', 'rpi_masto_tag_stream_instance_url');
    register_setting('rpi_masto_tag_stream_options', 'rpi_masto_tag_stream_access_token');

    add_settings_section(
        'rpi_masto_tag_stream_section',
        'Mastodon API Einstellungen',
        'rpi_masto_tag_stream_section_callback',
        'rpi_masto_tag_stream_settings'
    );

    add_settings_field(
        'rpi_masto_tag_stream_instance_url',
        'Mastodon Instanz URL',
        'rpi_masto_tag_stream_instance_url_callback',
        'rpi_masto_tag_stream_settings',
        'rpi_masto_tag_stream_section'
    );

    add_settings_field(
        'rpi_masto_tag_stream_access_token',
        'Zugriffstoken',
        'rpi_masto_tag_stream_access_token_callback',
        'rpi_masto_tag_stream_settings',
        'rpi_masto_tag_stream_section'
    );
}
add_action('admin_init', 'rpi_masto_tag_stream_register_settings');

// Callback-Funktion für die Einstellungen-Sektion
function rpi_masto_tag_stream_section_callback() {
    echo 'Gib die Mastodon API Einstellungen ein:';
}

// Callback-Funktion für das Instanz-URL-Feld
function rpi_masto_tag_stream_instance_url_callback() {
    $instanceUrl = get_option('rpi_masto_tag_stream_instance_url');
    echo '<input type="text" name="rpi_masto_tag_stream_instance_url" value="' . esc_attr($instanceUrl) . '" />';
}

// Callback-Funktion für das Zugriffstoken-Feld
function rpi_masto_tag_stream_access_token_callback() {
    $accessToken = get_option('rpi_masto_tag_stream_access_token');
    echo '<input type="text" name="rpi_masto_tag_stream_access_token" value="' . esc_attr($accessToken) . '" />';
}

/**
 * Funktion zum Lesen der Beiträge zu einem Tag
 * @param $atts
 * @param $content
 * @return void
 *
 * Shortcode [mastodon-stream tag="relilab"]
 */
function rpi_masto_tag_stream_get_data($atts, $content){
    // Mastodon API-Einstellungen abrufen
    $instanceUrl = get_option('rpi_masto_tag_stream_instance_url');
    $accessToken = get_option('rpi_masto_tag_stream_access_token');

    // Überprüfen, ob die Einstellungen vorhanden sind
    if (empty($instanceUrl) || empty($accessToken)) {
        return;
    }

    // Gewünschten Tag festlegen
    $tag = $atts['tag'];

    // API-Endpunkt zum Abrufen der Beiträge mit dem gewünschten Tag
    $apiUrl = $instanceUrl . '/api/v1/timelines/tag/' . $tag;

    // Beiträge von Mastodon abrufen
    $posts = rpi_masto_tag_stream_get_mastodon_data($apiUrl, $accessToken);

    foreach ($posts as $p) {

        $post = new stdClass();
        $post->status_id = $p['id'];
        $post->post_date = $p['created_at'];
        $post->post_content = $p['content'];

        $post->url = $p['url'];
        /*
        $post->account =  $p['account']['id'];
        $post->account_username =  $p['account']['username'];
        $post->account_url =  $p['account']['url'];
        $post->account_avatar =  $p['account']['avatar'];

        if(isset($p['media_attachments'][0]['url']))
            $post->image = $p['media_attachments'][0]['url'];
        else{
            $post->image = '';
        }

        if(isset($p['card']['title'])){
            $post->card_title = $post['card']['title'];
            $post->card_url = $post['card']['url'];
        }else{
            $post->card_title = '';
            $post->card_url = '';
        }
*/
       echo  rpi_masto_tag_stream_template($post);


    }
    //var_dump($posts);
}

function rpi_masto_tag_stream_template($post){

    return '<iframe src="'.$post->url.'/embed" 
                class="mastodon-embed" style="max-width: 100%; border: 0px none; overflow: hidden;" 
                allowfullscreen="allowfullscreen" scrolling="no" width="400" height="492"></iframe>';
}

// Funktion zum Importieren der Beiträge
function rpi_masto_tag_stream_import_posts() {
    // Mastodon API-Einstellungen abrufen
    $instanceUrl = get_option('rpi_masto_tag_stream_instance_url');
    $accessToken = get_option('rpi_masto_tag_stream_access_token');

    // Überprüfen, ob die Einstellungen vorhanden sind
    if (empty($instanceUrl) || empty($accessToken)) {
        return;
    }

    // Gewünschten Tag festlegen
    $tag = 'dein_tag';

    // API-Endpunkt zum Abrufen der Beiträge mit dem gewünschten Tag
    $apiUrl = $instanceUrl . '/api/v1/timelines/tag/' . $tag;

    // Beiträge von Mastodon abrufen
    $posts = rpi_masto_tag_stream_get_mastodon_data($apiUrl, $accessToken);

    // Importiere Beiträge in WordPress
    foreach ($posts as $post) {
        // Wichtige Felder extrahieren
        $postId = $post['id'];
        $postCreatedAt = $post['created_at'];
        $postContent = $post['content'];
        $postUrl = $post['url'];

        // Erstelle einen neuen WordPress-Beitrag
        $newPost = array(
            'post_title' => $postContent,
            'post_content' => $postContent,
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_date' => $postCreatedAt,
            'post_name' => sanitize_title($postContent),
            'guid' => $postUrl
        );

        // Füge den Beitrag zu WordPress hinzu
        $postId = wp_insert_post($newPost);

        // Überprüfe, ob der Beitrag erfolgreich importiert wurde
        if (!is_wp_error($postId)) {
            echo 'Beitrag importiert: ' . $postId . '<br>';
        } else {
            echo 'Fehler beim Importieren des Beitrags: ' . $postId->get_error_message() . '<br>';
        }
    }
}




// Hook-Funktion zum Ausführen des Imports
function rpi_masto_tag_stream_schedule_import() {
    wp_schedule_event(time(), 'daily', 'rpi_masto_tag_stream_import_posts');
}
add_action('wp', 'rpi_masto_tag_stream_schedule_import');

// Hook-Funktion zum Entfernen des Imports
function rpi_masto_tag_stream_remove_schedule() {
    wp_clear_scheduled_hook('rpi_masto_tag_stream_import_posts');
}
register_deactivation_hook(__FILE__, 'rpi_masto_tag_stream_remove_schedule');
