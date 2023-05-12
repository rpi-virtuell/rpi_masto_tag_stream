<?php
/*
Plugin Name: RPI-Masto-Tag-Stream
Plugin URI: https://github.com/rpi-virtuell/rpi_masto_tag_stream
Description: Stelle Mastodon-Beiträge mit einem bestimmten Tag in WordPress. Shortcode exa,ple: [mastodon-stream tag="relilab" limit=4 cols="2"]
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
    $tag    = $atts['tag'];
    $limit  = isset($atts['limit'])? $atts['limit'] : 5;
    $cols   = isset($atts['cols']) ? $atts['cols'] : 2;
    $grid_template_columns = '1fr ';
    for($i=0; $i < $cols-1; $i++){
        $grid_template_columns .= '1fr ';
    }

    // API-Endpunkt zum Abrufen der Beiträge mit dem gewünschten Tag


    // Beiträge von Mastodon abrufen
    $posts = rpi_masto_tag_stream_get_mastodon_data($tag, $accessToken, $limit);

    ob_start();
    rpi_masto_tag_the_post_style($grid_template_columns);
    ?>
    <div class="rpi-masto-feed">
    <?php

    foreach ($posts as $p) {


       // echo'<pre>';var_dump($p); die();

        $post = new stdClass();
        $post->status_id = $p['id'];
        $post->post_date = date('d.m.Y',strtotime($p['created_at']));
        $post->post_content = $p['content'];
        $post->post_exerpt = wp_trim_words(strip_tags($p['content']),15,' ...');


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



                $post->image = wp_video_shortcode([
                    'src'=>$media['url'],
                    'width'=>'400',
                    'loop'=>'yes',
                    'class'=>'rpi-masto-video'
                ]);


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

        rpi_masto_tag_the_post($post);


    }
    ?>
    </div>
    <?php
    return ob_get_clean();
}
function rpi_masto_tag_the_post_style($grid_template_columns='1fr'){
    ?>
    <style>
        .rpi-masto-feed{
            display: grid;
            grid-template-columns: <?php echo $grid_template_columns;?>;
            overflow: hidden;
            grid-column-gap: 20px;
        }
        .rpi-masto-feed .masto-entry {

            max-width: 100%;
            overflow: hidden;
            border-image: linear-gradient(to right, #ddd, #eee) 0.5;
            border: 10px solid #fff;
            margin-bottom: 0px;

        }
        .rpi-masto-feed .masto-entry.open #wrap{
            /*display: grid;*/
            grid-template-rows: 100px auto auto 20px;
            background-color: white;
        }
        .rpi-masto-feed .open{
            background-color: #fff;
            border-image: linear-gradient(to right, #5790ac, #91d6f8) 0.5;
            border-bottom: 0;
        }
        .rpi-masto-feed .acc{
            background-image: linear-gradient(to right, #5790ac, #70a7d4, #91d6f8);

        }
        .rpi-masto-feed .acc .p-author{
            display: grid;
            grid-template-columns: 100px auto;
            grid-column-gap: 5px;
            background-color: transparent;
            padding: 10px;

        }
        .rpi-masto-feed .acc .author-right-col{
            display: grid;
            grid-template-rows: 30px auto 15px;
            margin: -4px;

        }
        .follow-button{
            text-align: right;
        }
        .follow-button .button{

            background-color:  #0073aa;
            color:#fff;
            z-index: 10;
        }
        .follow-button .button:hover{
            background-color: #fff;
            color: #0073aa;
        }
        .display-name{
            text-align: center;
            color: #444;
            min-height: 50px;
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

        }
        .rpi-masto-feed .masto-content{
             font-size: small;
        }
        .rpi-masto-feed .wp-video {
            max-width: 100% !important;
            width: 100% !important;
        }

        footer .link {
            font-size: small;
            text-align: center;
            display: block;
        }
        @media screen and (max-width: 600px) {
            .rpi-masto-feed{
                grid-template-columns: 1fr;
            }
        }
        .sum-grid{
            display: grid;
            grid-template-columns: 60px auto;
        }
        details.masto-entry.open .sum-grid{
            display: grid;
            grid-template-columns: 50px auto;
        }
        .sum-grid .short-message{
            font-size: x-small;
        }
        details.masto-entry.open .sum-grid .detailed-status__display-avatar,
        details.masto-entry.open .sum-grid .short-message{
            display: none;
        }
        details.masto-entry .close-details{
            display: none;
        }
        details.masto-entry.open .close-details{
            display: block;
        }
        details.masto-entry.open .close-details .button{
            max-width: fit-content;
            text-align: center;
            margin: 5px;
        }
        .button-secondary{
            background-color: transparent;
            border: 2px solid transparent;
            color: #777777;
            padding: 5px;
        }
        .button-secondary:hover{
            background-color: #777777;
            color: white;
        }
        .masto-wrapper .footer{
            padding: 0 15px;
            margin: 0 0 20px;
            display: block;
            background-image: linear-gradient(to right, #5790ac,#91d6f8);
            font-size: small;
        }
        .masto-wrapper .footer a{
            color: white;
        }
    </style>
    <?php
}
function rpi_masto_tag_the_post(stdClass $post)
{

    ?>
    <div class="masto-wrapper">
        <details class="masto-entry">
            <summary class="sum-grid">

                <div class="detailed-status__display-avatar">
                    <img alt="" class="account__avatar u-photo"
                         src="<?php echo $post->account_avatar; ?>" style="max-width: 50px"></div>
                <div class="short-message"><?php echo $post->post_exerpt;?></div>
                <div class="close-details"><div class="button button-secondary"><span class="dashicons dashicons-arrow-up"></span></div></div>
                <div class="close-details">
                    <table>
                        <tr>
                            <td class="display-name">
                                <strong><?php echo $post->account_display_name; ?></strong>
                            </td>
                        </tr>
                    </table>
                </div>

            </summary>
            <header class="acc">
                <div class="p-author h-card">
                    <div class="detailed-status__display-name u-url">
                        <div >
                            <a target="_blank" rel="noopener"
                               href="<?php echo $post->account_url; ?>">
                                <div class="detailed-status__display-avatar">
                                    <img alt="" class="account__avatar u-photo"
                                         src="<?php echo $post->account_avatar; ?>" style="max-width: 90px"></div>
                            </a>
                        </div>
                    </div>
                    <div class="author-right-col">
                        <div  class="follow-button">
                            <a class="button" target="_new" href="<?php echo $post->account_url; ?>">Folgen</a>
                        </div>
                        <div class="ghost"> </div>
                        <div>
                            <a class="link" target="_blank" rel="noopener"
                               href="<?php echo $post->account_url; ?>"><span class="display-name__account">@<?php echo $post->account_username; ?></span></a>
                        </div>
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
        </details>
        <footer>
            <div class="footer">
                <a target="_blank" rel="noopener" href="<?php echo $post->url; ?>">@<?php echo $post->account_username; ?> am <?php echo $post->post_date?></a>
            </div>
        </footer>
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
