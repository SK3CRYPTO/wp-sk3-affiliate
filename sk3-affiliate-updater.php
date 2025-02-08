<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vérifie s'il y a une mise à jour disponible
 */
add_filter('pre_set_site_transient_update_plugins', 'sk3_affiliate_check_update');
function sk3_affiliate_check_update($transient) {
    if ( empty($transient->checked) ) {
        return $transient;
    }

    // Définir le slug du plugin (doit correspondre au nom du dossier et au slug utilisé dans le header)
    $plugin_slug = 'sk3-affiliate';
    // Vous pouvez définir ici le chemin vers le fichier principal du plugin (si besoin d'une référence)
    $plugin_file = plugin_basename( dirname(__FILE__) . '/sk3-affiliate.php' );

    // Version actuelle du plugin (doit correspondre à l'en-tête de sk3-affiliate.php)
    $current_version = '0.1.0';

    // Préparer la requête pour GitHub
    $request_args = array(
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        )
    );

    // Appel à l'API GitHub pour récupérer la dernière release
    $response = wp_remote_get('https://api.github.com/repos/SK3CRYPTO/wp-sk3-affiliate/releases/latest', $request_args);
    if ( is_wp_error($response) ) {
        return $transient;
    }
    $data = json_decode(wp_remote_retrieve_body($response));
    if ( empty($data) || empty($data->tag_name) ) {
        return $transient;
    }

    // Supposons que le tag est du format "v1.0.1"
    $remote_version = ltrim($data->tag_name, 'v');

    // Si une version supérieure est disponible, préparer les informations pour WordPress
    if ( version_compare($current_version, $remote_version, '<') ) {
        $update_data = new stdClass();
        $update_data->slug         = $plugin_slug;
        $update_data->plugin       = $plugin_file;
        $update_data->new_version  = $remote_version;
        $update_data->url          = 'https://github.com/SK3CRYPTO/wp-sk3-affiliate'; // Page GitHub du plugin
        $update_data->package      = $data->zipball_url; // URL de téléchargement du ZIP

        $transient->response[$plugin_file] = $update_data;
    }

    return $transient;
}

/**
 * Fournit les détails du plugin lors de l'affichage des informations de mise à jour.
 */
add_filter('plugins_api', 'sk3_affiliate_plugin_info', 10, 3);
function sk3_affiliate_plugin_info($res, $action, $args) {
    if ( 'plugin_information' !== $action || $args->slug !== 'sk3-affiliate' ) {
        return $res;
    }

    $request_args = array(
        'headers' => array(
            'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        )
    );

    $response = wp_remote_get('https://api.github.com/repos/SK3CRYPTO/wp-sk3-affiliate/releases/latest', $request_args);
    if ( is_wp_error($response) ) {
        return $res;
    }
    $data = json_decode(wp_remote_retrieve_body($response));
    if ( empty($data) ) {
        return $res;
    }

    $res = new stdClass();
    $res->name          = 'sk3-affiliate';
    $res->slug          = 'sk3-affiliate';
    $res->version       = ltrim($data->tag_name, 'v');
    $res->author        = 'Votre Nom ou Société';
    $res->homepage      = 'https://github.com/SK3CRYPTO/wp-sk3-affiliate';
    $res->download_link = $data->zipball_url;
    $res->last_updated  = $data->published_at;
    $res->sections      = array(
        'description' => $data->body, // Le corps de la release peut être utilisé comme changelog ou description
    );

    return $res;
}

