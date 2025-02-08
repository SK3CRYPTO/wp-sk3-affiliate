<?php
/**
 * Plugin Name: SK3 Affiliate
 * Plugin URI: https://github.com/SK3CRYPTO/wp-sk3-affiliate
 * Description: Plugin pour gérer et afficher des liens d’affiliation
 * Version: 0.1.0
 * Author: SK3
 * License: GPL2
 */

// Sécurité : Empêcher l'accès direct
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Inclusion du fichier updater dédié aux mises à jour via GitHub.
include_once plugin_dir_path( __FILE__ ) . 'sk3-affiliate-updater.php';

/* ===============================
   Création de la table à l'activation
   =============================== */
function sk3_affiliate_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sk3_affiliations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        category_id INT(11) NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        url VARCHAR(255) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'sk3_affiliate_install' );

/* ===========================================
   Ajout des menus d'administration et sous-menus
   =========================================== */
function sk3_affiliate_admin_menu() {
    // Menu principal "SK3 Affiliation"
    add_menu_page(
        'SK3 Affiliate',            // Titre de la page
        'SK3 Affiliation',          // Titre du menu
        'manage_options',           // Capacité requise
        'sk3-affiliate',            // Slug du menu
        'sk3_affiliate_admin_page', // Fonction d'affichage de la page principale
        'dashicons-admin-links',    // Icône
        6                           // Position dans le menu
    );
    // Sous-menu "Widget"
    add_submenu_page(
        'sk3-affiliate',            // Parent slug
        'Widget',                   // Titre de la page
        'Widget',                   // Titre du menu
        'manage_options',           // Capacité requise
        'sk3-affiliate-widget',     // Slug du sous-menu
        'sk3_affiliate_widget_page' // Fonction d'affichage
    );
    // Sous-menu "Shortcode"
    add_submenu_page(
        'sk3-affiliate',                      // Parent slug
        'Shortcode',                          // Titre de la page
        'Shortcode',                          // Titre du menu
        'manage_options',                     // Capacité requise
        'sk3-affiliate-shortcode-admin',      // Slug du sous-menu
        'sk3_affiliate_shortcode_admin_page'  // Fonction d'affichage
    );
}
add_action( 'admin_menu', 'sk3_affiliate_admin_menu' );

/* =====================================
   Page d'administration principale
   ===================================== */
function sk3_affiliate_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sk3_affiliations';

    // Traitement du formulaire d'ajout/modification
    if ( isset( $_POST['sk3_affiliate_action'] ) && $_POST['sk3_affiliate_action'] === 'save_affiliation' ) {
        // Vérification du nonce
        if ( ! isset( $_POST['sk3_affiliate_nonce'] ) || ! wp_verify_nonce( $_POST['sk3_affiliate_nonce'], 'sk3_affiliate_nonce_action' ) ) {
            echo '<div class="error"><p>Nonce invalide !</p></div>';
            return;
        }

        // Récupération et assainissement des données
        $affiliation_id = isset( $_POST['affiliation_id'] ) ? intval( $_POST['affiliation_id'] ) : 0;
        $category_id    = isset( $_POST['category_id'] ) ? intval( $_POST['category_id'] ) : 0;
        $name           = sanitize_text_field( $_POST['name'] );
        $description    = sanitize_textarea_field( $_POST['description'] );
        $url            = esc_url_raw( $_POST['url'] );
        $active         = isset( $_POST['active'] ) ? 1 : 0;

        // Vérification que le lien commence par "http://" ou "https://"
        if ( ! preg_match( '/^https?:\/\//', $url ) ) {
            echo '<div class="error"><p>Le lien doit commencer par "http://" ou "https://".</p></div>';
            return;
        }

        if ( $affiliation_id > 0 ) {
            // Mise à jour d'une affiliation existante
            $wpdb->update(
                $table_name,
                array(
                    'category_id' => $category_id,
                    'name'        => $name,
                    'description' => $description,
                    'url'         => $url,
                    'active'      => $active,
                ),
                array( 'id' => $affiliation_id ),
                array( '%d', '%s', '%s', '%s', '%d' ),
                array( '%d' )
            );
            echo '<div class="updated"><p>Affiliation mise à jour.</p></div>';
        } else {
            // Insertion d'une nouvelle affiliation
            $wpdb->insert(
                $table_name,
                array(
                    'category_id' => $category_id,
                    'name'        => $name,
                    'description' => $description,
                    'url'         => $url,
                    'active'      => $active,
                ),
                array( '%d', '%s', '%s', '%s', '%d' )
            );
            echo '<div class="updated"><p>Nouvelle affiliation ajoutée.</p></div>';
        }
    }

    // Traitement de la suppression d'une affiliation
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['affiliation'] ) ) {
        $affiliation_id = intval( $_GET['affiliation'] );
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_affiliation_' . $affiliation_id ) ) {
            echo '<div class="error"><p>Nonce invalide pour la suppression !</p></div>';
        } else {
            $wpdb->delete( $table_name, array( 'id' => $affiliation_id ), array( '%d' ) );
            echo '<div class="updated"><p>Affiliation supprimée.</p></div>';
        }
    }

    // Affichage du formulaire en mode édition si demandé
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['affiliation'] ) ) {
        $affiliation_id = intval( $_GET['affiliation'] );
        $affiliation   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $affiliation_id ) );
        if ( $affiliation ) {
            sk3_affiliate_display_form( $affiliation );
        } else {
            echo '<div class="error"><p>Affiliation non trouvée.</p></div>';
        }
    } else {
        echo '<div class="wrap">';
        echo '<h1>SK3 Affiliation</h1>';
        echo '<a href="' . admin_url( 'admin.php?page=sk3-affiliate&action=new' ) . '" class="page-title-action">Ajouter une nouvelle affiliation</a>';

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'new' ) {
            sk3_affiliate_display_form();
        }

        // Affichage de la liste des affiliations
        $affiliations = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
        if ( $affiliations ) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>ID</th>';
            echo '<th>Catégorie</th>';
            echo '<th>Nom du lien</th>';
            echo '<th>URL</th>';
            echo '<th>Actif</th>';
            echo '<th>Actions</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            foreach ( $affiliations as $aff ) {
                $cat = get_category( $aff->category_id );
                $cat_name = ( $cat && ! is_wp_error( $cat ) ) ? $cat->name : 'N/A';
                echo '<tr>';
                echo '<td>' . esc_html( $aff->id ) . '</td>';
                echo '<td>' . esc_html( $cat_name ) . '</td>';
                echo '<td>' . esc_html( $aff->name ) . '</td>';
                echo '<td><a href="' . esc_url( $aff->url ) . '" target="_blank">' . esc_html( $aff->url ) . '</a></td>';
                echo '<td>' . ( $aff->active ? 'Activé' : 'Inactif' ) . '</td>';
                $edit_url   = admin_url( 'admin.php?page=sk3-affiliate&action=edit&affiliation=' . $aff->id );
                $delete_url = wp_nonce_url( admin_url( 'admin.php?page=sk3-affiliate&action=delete&affiliation=' . $aff->id ), 'delete_affiliation_' . $aff->id );
                echo '<td><a href="' . esc_url( $edit_url ) . '">Modifier</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'Êtes-vous sûr de vouloir supprimer cette affiliation ?\')">Supprimer</a></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>Aucune affiliation trouvée.</p>';
        }
        echo '</div>';
    }
}

/* =====================================
   Formulaire d'ajout/modification
   ===================================== */
function sk3_affiliate_display_form( $affiliation = null ) {
    $categories = get_categories( array( 'hide_empty' => false ) );
    $is_edit    = ( $affiliation !== null );
    ?>
    <div class="wrap">
        <h2><?php echo $is_edit ? 'Modifier l\'affiliation' : 'Ajouter une nouvelle affiliation'; ?></h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'sk3_affiliate_nonce_action', 'sk3_affiliate_nonce' ); ?>
            <input type="hidden" name="sk3_affiliate_action" value="save_affiliation" />
            <?php if ( $is_edit ) : ?>
                <input type="hidden" name="affiliation_id" value="<?php echo esc_attr( $affiliation->id ); ?>" />
            <?php endif; ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="category_id">Catégorie</label></th>
                    <td>
                        <select name="category_id" id="category_id" required>
                            <option value="">-- Choisissez une catégorie --</option>
                            <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $is_edit ? $affiliation->category_id : '', $cat->term_id ); ?>>
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="name">Nom du lien</label></th>
                    <td><input name="name" type="text" id="name" value="<?php echo $is_edit ? esc_attr( $affiliation->name ) : ''; ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="description">Description</label></th>
                    <td><textarea name="description" id="description" class="large-text"><?php echo $is_edit ? esc_textarea( $affiliation->description ) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="url">Lien</label></th>
                    <td><input name="url" type="url" id="url" value="<?php echo $is_edit ? esc_url( $affiliation->url ) : ''; ?>" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row">Activer l'affichage</th>
                    <td>
                        <input name="active" type="checkbox" id="active" value="1" <?php checked( $is_edit ? $affiliation->active : 1, 1 ); ?> />
                        <label for="active">Actif</label>
                    </td>
                </tr>
            </table>
            <?php submit_button( $is_edit ? 'Mettre à jour' : 'Ajouter' ); ?>
        </form>
    </div>
    <?php
}

/* =====================================
   Page du sous-menu "Widget"
   ===================================== */
function sk3_affiliate_widget_page() {
    ?>
    <div class="wrap">
        <h1>Widget SK3 Affiliate</h1>
        <p>Configurez ici le widget pour afficher vos liens d’affiliation dans les zones de widgets de votre thème.</p>
    </div>
    <?php
}

/* =====================================
   Page du sous-menu "Shortcode"
   ===================================== */
function sk3_affiliate_shortcode_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sk3_affiliations';

    $results = $wpdb->get_col( "SELECT DISTINCT category_id FROM $table_name WHERE active = 1" );

    echo '<div class="wrap">';
    echo '<h1>Shortcode Disponibles</h1>';
    if ( ! empty( $results ) ) {
        echo '<p>Voici la liste des shortcodes disponibles pour afficher les liens d’affiliation :</p>';
        echo '<ul>';
        foreach ( $results as $cat_id ) {
            $cat = get_category( $cat_id );
            $cat_name = ( $cat && ! is_wp_error( $cat ) ) ? $cat->name : 'Catégorie ' . $cat_id;
            echo '<li><strong>' . esc_html( $cat_name ) . ':</strong> <code>[sk3_affiliate cat="' . esc_attr( $cat_id ) . '"]</code></li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Aucun shortcode disponible. Aucune affiliation enregistrée.</p>';
    }
    echo '</div>';
}

/* =====================================
   Shortcode pour affichage en front-end
   ===================================== */
function sk3_affiliate_shortcode( $atts ) {
    global $wpdb;
    $atts = shortcode_atts( array(
        'cat' => '', // ID de la catégorie
    ), $atts, 'sk3_affiliate' );

    if ( empty( $atts['cat'] ) ) {
        return '<p>Aucune catégorie spécifiée pour l\'affiliation.</p>';
    }

    $table_name  = $wpdb->prefix . 'sk3_affiliations';
    $category_id = intval( $atts['cat'] );

    $affiliations = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE category_id = %d AND active = 1", $category_id ) );
    if ( ! $affiliations ) {
        return '<p>Aucune affiliation active trouvée pour cette catégorie.</p>';
    }

    $output = '<ul class="sk3-affiliate-list">';
    foreach ( $affiliations as $aff ) {
        $output .= '<li><a href="' . esc_url( $aff->url ) . '" target="_blank">' . esc_html( $aff->name ) . '</a></li>';
    }
    $output .= '</ul>';

    return $output;
}
add_shortcode( 'sk3_affiliate', 'sk3_affiliate_shortcode' );
