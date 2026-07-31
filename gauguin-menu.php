<?php
/**
 * Plugin Name: Gauguin Menu
 * Plugin URI: https://gauguin.it
 * Description: Menu digitale di Gauguin Pizzeria Birreria (per QR code). Web-app del menù servita su una pagina del sito, con pannello prezzi/disponibilità.
 * Version: 1.1.4
 * Author: Gauguin
 * Text Domain: gauguin-menu
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('GXM_VERSION', '1.1.4');
define('GXM_FILE', __FILE__);
define('GXM_DIR', plugin_dir_path(__FILE__));
define('GXM_URL', plugin_dir_url(__FILE__));
define('GXM_TEMPLATE_SLUG', 'gauguin-menu'); // valore salvato in _wp_page_template
// Percorso base "cotto" negli asset compilati (vedi vite.config.ts -> base).
define('GXM_BUILD_BASE', '/wp-content/plugins/gauguin-menu/public/');

// Auto-update via GitHub (Plugin Update Checker), come gli altri plugin Gauguin.
// Repo tag-only: naftalina/gauguin-menu (se il repo non esiste ancora, il
// controllo semplicemente non trova aggiornamenti: nessun errore lato sito).
require_once GXM_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
$gxmUpdateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/naftalina/gauguin-menu/',
    __FILE__,
    'gauguin-menu'
);

require_once GXM_DIR . 'includes/class-admin.php';
require_once GXM_DIR . 'includes/class-rest.php';
require_once GXM_DIR . 'includes/class-template.php';

/**
 * Bootstrap.
 */
function gxm_boot() {
    GXM_Rest::instance();
    GXM_Template::instance();
    if (is_admin()) {
        GXM_Admin::instance();
    }
}
add_action('plugins_loaded', 'gxm_boot');

/**
 * Attivazione: crea (se manca) la pagina "Menu" con lo slug /menu e le assegna
 * il template del plugin, così il QR può puntare subito a dominio.it/menu.
 */
function gxm_activate() {
    $existing = get_page_by_path('menu');
    if ($existing instanceof WP_Post) {
        // Pagina /menu già presente: assicuriamoci che usi il nostro template.
        update_post_meta($existing->ID, '_wp_page_template', GXM_TEMPLATE_SLUG);
    } else {
        $page_id = wp_insert_post([
            'post_title'   => 'Menu',
            'post_name'    => 'menu',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
        ]);
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', GXM_TEMPLATE_SLUG);
        }
    }
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gxm_activate');
