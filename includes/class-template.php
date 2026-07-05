<?php
if (!defined('ABSPATH')) exit;

/**
 * Registra un template di pagina ("Gauguin Menu") selezionabile dall'editor
 * di WordPress e, per le pagine che lo usano, serve l'app del menù compilata
 * (client React statico) come documento standalone, senza header/footer del tema.
 */
class GXM_Template {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter('theme_page_templates', [$this, 'register_choice']);
        add_filter('template_include', [$this, 'maybe_render'], 99);
    }

    public function register_choice($templates) {
        $templates[GXM_TEMPLATE_SLUG] = 'Gauguin Menu';
        return $templates;
    }

    private function page_uses_template() {
        if (!is_page()) return false;
        $id = get_queried_object_id();
        return get_post_meta($id, '_wp_page_template', true) === GXM_TEMPLATE_SLUG;
    }

    public function maybe_render($template) {
        if (!$this->page_uses_template()) {
            return $template;
        }

        $html = @file_get_contents(GXM_DIR . 'public/index.html');
        if ($html === false) {
            // Fallback difensivo: se il build manca, non rompere la pagina.
            return $template;
        }

        // Resilienza: se il plugin non è nel percorso standard, riallineiamo il
        // base degli asset nell'HTML al percorso reale di questa installazione.
        $real_base = trailingslashit(plugins_url('public', GXM_FILE));
        if ($real_base !== GXM_BUILD_BASE) {
            $html = str_replace(GXM_BUILD_BASE, $real_base, $html);
        }

        // Inietta l'URL REST degli override (prezzi/esaurito) prima dell'app.
        $inline = '<script>window.GXM_OVERRIDES_URL=' .
            wp_json_encode(esc_url_raw(rest_url('gauguin-menu/v1/overrides'))) .
            ';</script>';
        $html = str_replace('<div id="root"></div>', $inline . '<div id="root"></div>', $html);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
