<?php
/*
Plugin Name: RPI-Masto-Tag-Stream
Plugin URI: https://github.com/rpi-virtuell/rpi_masto_tag_stream
Description: Stelle Mastodon-Beiträge mit einem bestimmten Tag in WordPress. Shortcode exa,ple: [mastodon-stream tag="relilab" limit=4]
Version: 1.0
Author: Joachim Happel
Author URI: https://github.com/rpi-virtuell/johappel
*/

// Funktion zum Abrufen von Daten von der Mastodon-API
function rpi_masto_tag_stream_get_mastodon_data($tag, $accessToken, $limit=10)
{
    $instanceUrl = get_option('rpi_masto_tag_stream_instance_url');
    $url = $instanceUrl . '/api/v1/timelines/tag/' . $tag .'?limit='.$limit;
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
function rpi_masto_tag_stream_add_options_page()
{
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
function rpi_masto_tag_stream_render_options_page()
{
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
function rpi_masto_tag_stream_register_settings()
{
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
function rpi_masto_tag_stream_section_callback()
{
    echo 'Gib die Mastodon API Einstellungen ein:';
}

// Callback-Funktion für das Instanz-URL-Feld
function rpi_masto_tag_stream_instance_url_callback()
{
    $instanceUrl = get_option('rpi_masto_tag_stream_instance_url');
    echo '<input type="text" name="rpi_masto_tag_stream_instance_url" value="' . esc_attr($instanceUrl) . '" />';
}

// Callback-Funktion für das Zugriffstoken-Feld
function rpi_masto_tag_stream_access_token_callback()
{
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
function rpi_masto_tag_stream_get_data($atts, $content)
{
    // Mastodon API-Einstellungen abrufen
    $instanceUrl = get_option('rpi_masto_tag_stream_instance_url');
    $accessToken = get_option('rpi_masto_tag_stream_access_token');

    // Überprüfen, ob die Einstellungen vorhanden sind
    if (empty($instanceUrl) || empty($accessToken)) {
        return;
    }

    // Gewünschten Tag festlegen
    $tag = $atts['tag'];
    $limit = isset($atts['limit'])? $atts['limit'] : 5;

    // API-Endpunkt zum Abrufen der Beiträge mit dem gewünschten Tag


    // Beiträge von Mastodon abrufen
    $posts = rpi_masto_tag_stream_get_mastodon_data($tag, $accessToken, $limit);

    ob_start();
    ?>
    <style>
        .rpi-masto-feed{
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            grid-column-gap: 30px;
        }
        .rpi-masto-feed .masto-entry{
            padding: 15px;
            background-color: #c0c0c0;
            margin-bottom: 30px;
            display: grid;
            grid-template-rows: auto 1fr auto 20px;
        }
        .rpi-masto-feed .acc .p-author{
            display: grid;
            grid-template-columns: auto 90px;
            grid-column-gap: 5px;
            background-color: #777;
            padding: 10px;
            border: 1px solid #000;
        }
        .rpi-masto-feed .acc a{
            color: #fff;
        }
        .rpi-masto-feed .acc .detailed-status__display-name{
            display: grid;
            grid-template-columns: 80px auto;
            grid-column-gap: 10px;
        }
        .rpi-masto-feed .masto-entry{
            max-width: 100%;
            overflow: hidden;
        }
        .rpi-masto-feed .display-name__account{
            font-size: small;
        }
        .rpi-masto-feed .masto-post{
            border: 1px solid #fff;
            padding: 10px;

        }.rpi-masto-feed .masto-content{
            font-size: small;
        }
        footer .link {
            font-size: small;
            margin-left: auto;
            margin-right: auto;
            display: block;
        }
        @media screen and (max-width: 600px) {
            .rpi-masto-feed{
                grid-template-columns: 1fr;
            }
        }


    </style>
    <div class="rpi-masto-feed">
    <?php

    foreach ($posts as $p) {


       // echo'<pre>';var_dump($p); die();

        $post = new stdClass();
        $post->status_id = $p['id'];
        $post->post_date = date('d.m.Y',strtotime($p['created_at']));
        $post->post_content = $p['content'];


        $post->url = $p['url'];

        $post->account =  $p['account']['id'];
        $post->account_username =  $p['account']['username'];
        $post->account_display_name =  $p['account']['display_name'];
        $post->account_url =  $p['account']['url'];
        $post->account_avatar =  $p['account']['avatar'];

        $post->image = '';

        if(strpos($post->post_content, 'vimeo.com/')>0){
           // echo '<pre>';var_dump($p);
        }

        if(isset($p['media_attachments']) && isset($p['media_attachments'][0])){
            //var_dump($p['media_attachments']);
            $media = ($p['media_attachments'][0]);
            if('image' == $media['type']){
                $post->image = '<img src="'.$media['url'].'">';
            }elseif ('gifv' == $media['type']){
                $post->image = apply_filters('the_content',$media['url']);
            }

        }



        if(isset($p['card']['title'])){
            $post->card_title = $p['card']['title'];
            $post->card_url = $p['card']['url'];
            $post->card_html = $p['card']['html'];


        }else{
            $post->card_title = '';
            $post->card_url = '';
            $post->card_html = '';
        }

        rpi_masto_tag_stream_template($post);


    }
    ?>
    </div>
    <?php
    return ob_get_clean();
}

function rpi_masto_tag_stream_template(stdClass $post)
{

    ?>
    <div class="masto-entry">
        <header class="acc">
            <div class="p-author h-card">
                <a class="detailed-status__display-name u-url" target="_blank" rel="noopener"
                   href="<?php echo $post->account_url; ?>">
                    <div class="detailed-status__display-avatar">
                        <img alt="" class="account__avatar u-photo"
                             src="<?php echo $post->account_avatar; ?>" style="max-width: 80px">
                    </div>
                    <div class="display-name">
                        <strong class="display-name__html p-name emojify"><?php echo $post->account_display_name; ?></strong>
                        <span class="display-name__account">@<?php echo $post->account_username; ?> am <?php echo $post->post_date?></span>
                    </div>
                </a>
                <div>
                    <a class="button logo-button" target="_new" href="<?php echo $post->account_url; ?>">Folgen</a>
                </div>
            </div>

        </header>
        <article class="masto-post">
            <div class="masto-content">
                <?php echo  $post->post_content; ?>
                <a class="detailed-status__display-name u-url" target="_blank" rel="noopener"
                   href="<?php echo $post->url; ?>">
                    <?php echo  $post->image; ?>
                    <?php echo  $post->card_html; ?>
                </a>
            </div>

        </article>
        <div class="gost"></div>
        <footer><a class="link" target="_blank" rel="noopener"
                   href="<?php echo $post->url; ?>">Beitrag am Originalort öffnen</a></footer>
    </div>
    <?php
/*
    return '<div><iframe src="' . $post->url . '/embed"
                class="mastodon-embed" style="max-width: auto; border: 0px none; overflow: hidden;"
                allowfullscreen="allowfullscreen" scrolling="no" width="400" height="492"></iframe></div>';
*/
}

// Funktion zum Importieren der Beiträge
function rpi_masto_tag_stream_import_posts()
{
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
function rpi_masto_tag_stream_schedule_import()
{
    wp_schedule_event(time(), 'daily', 'rpi_masto_tag_stream_import_posts');
}

add_action('wp', 'rpi_masto_tag_stream_schedule_import');

// Hook-Funktion zum Entfernen des Imports
function rpi_masto_tag_stream_remove_schedule()
{
    wp_clear_scheduled_hook('rpi_masto_tag_stream_import_posts');
}

register_deactivation_hook(__FILE__, 'rpi_masto_tag_stream_remove_schedule');
