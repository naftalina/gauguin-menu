<?php
if (!defined('ABSPATH')) exit;

/**
 * Endpoint pubblico (sola lettura) con gli override di prezzo/disponibilità.
 * L'app del menù lo interroga e applica i valori sopra al menù di base.
 */
class GXM_Rest {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register']);
    }

    public function register() {
        register_rest_route('gauguin-menu/v1', '/overrides', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => [$this, 'get_overrides'],
        ]);
    }

    public function get_overrides() {
        $ov = get_option(GXM_Admin::OPTION, []);
        if (!is_array($ov) || empty($ov)) {
            // oggetto vuoto {}, non array [], così il client lo tratta come mappa
            return new WP_REST_Response((object) [], 200);
        }
        return new WP_REST_Response($ov, 200);
    }
}
