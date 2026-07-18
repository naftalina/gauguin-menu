<?php
if (!defined('ABSPATH')) exit;

/**
 * Pannello admin "leggero": permette di cambiare i PREZZI e segnare un piatto
 * come ESAURITO, senza toccare il menù di base (che resta cotto nel bundle).
 * Salva solo gli override in un'opzione WP; l'app li applica sopra al menù.
 */
class GXM_Admin {

    const OPTION = 'gxm_overrides';
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_gxm_save_overrides', [$this, 'save']);
    }

    public function menu() {
        add_menu_page(
            'Menu Gauguin', 'Menu Gauguin', 'manage_options',
            'gxm-menu', [$this, 'render'], 'dashicons-food', 26
        );
    }

    /** Legge la struttura del menù (id, nome, prezzi) dal file generato al build. */
    public static function items() {
        $file = GXM_DIR . 'menu-index.json';
        if (!is_readable($file)) return [];
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** Override correnti (array associativo id => [price, priceMaxi, soldOut]). */
    public static function overrides() {
        $ov = get_option(self::OPTION, []);
        return is_array($ov) ? $ov : [];
    }

    public function save() {
        if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
        check_admin_referer('gxm_save_overrides');

        $in_price = isset($_POST['price']) && is_array($_POST['price']) ? wp_unslash($_POST['price']) : [];
        $in_maxi  = isset($_POST['maxi'])  && is_array($_POST['maxi'])  ? wp_unslash($_POST['maxi'])  : [];
        $in_sold  = isset($_POST['soldout']) && is_array($_POST['soldout']) ? array_map('strval', array_keys($_POST['soldout'])) : [];
        $sold_set = array_flip($in_sold);

        // id validi = solo quelli presenti nel menù di base (niente iniezioni).
        $valid = [];
        foreach (self::items() as $cat) {
            foreach ($cat['items'] as $it) $valid[(string) $it['id']] = $it;
        }

        $clean = [];
        foreach ($valid as $id => $base) {
            $entry = [];
            $p = isset($in_price[$id]) ? $this->clean_price($in_price[$id]) : '';
            $m = isset($in_maxi[$id])  ? $this->clean_price($in_maxi[$id])  : '';
            if ($p !== '') $entry['price'] = $p;
            if ($m !== '') $entry['priceMaxi'] = $m;
            if (isset($sold_set[$id])) $entry['soldOut'] = true;
            if (!empty($entry)) $clean[$id] = $entry;
        }

        update_option(self::OPTION, $clean, false);
        wp_safe_redirect(add_query_arg(['page' => 'gxm-menu', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    /** Tiene solo cifre e separatore decimale; virgola -> punto (come il menù di base). */
    private function clean_price($v) {
        $v = preg_replace('/[^0-9.,]/', '', (string) $v);
        $v = str_replace(',', '.', $v);
        // eventuali punti multipli -> tieni il primo
        $parts = explode('.', $v);
        if (count($parts) > 2) $v = $parts[0] . '.' . implode('', array_slice($parts, 1));
        return trim($v);
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        $items = self::items();
        $ov = self::overrides();
        $rest = esc_url(rest_url('gauguin-menu/v1/overrides'));
        ?>
        <div class="wrap">
            <h1>Menu Gauguin — Prezzi &amp; Disponibilità</h1>
            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p>Menù aggiornato. Le modifiche sono già online su <code>/menu</code>.</p></div>
            <?php endif; ?>
            <p style="max-width:760px">
                Qui cambi <strong>solo i prezzi</strong> o segni un piatto <strong>esaurito</strong>.
                Lascia un prezzo <em>vuoto</em> per tenere quello originale. Per aggiungere piatti,
                cambiare descrizioni o tradurre, scrivi a chi gestisce il plugin.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="gxm_save_overrides">
                <?php wp_nonce_field('gxm_save_overrides'); ?>

                <?php foreach ($items as $cat): ?>
                    <h2 style="margin-top:28px;border-bottom:2px solid #8C172A;padding-bottom:6px;color:#8C172A"><?php echo esc_html($cat['name']); ?></h2>
                    <table class="widefat striped" style="max-width:820px">
                        <thead><tr>
                            <th style="width:42%">Piatto</th>
                            <th style="width:16%">Prezzo base</th>
                            <th style="width:16%">Nuovo prezzo</th>
                            <th style="width:14%">Maxi</th>
                            <th style="width:12%">Esaurito</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($cat['items'] as $it):
                            if (!empty($it['isHeader'])) continue;
                            $id = (string) $it['id'];
                            $o = isset($ov[$id]) ? $ov[$id] : [];
                            // Un piatto è modificabile se ha un prezzo unitario numerico,
                            // anche con un'etichetta (es. tagliate "all'etto", arrosticini "cad.",
                            // vini "bottiglia"). Non modificabili solo le birre, che hanno l'intero
                            // listino scritto nel testo (price vuoto, formato "0,25L · 3,50€ | ...").
                            $has_price = ($it['price'] !== '');
                            $unit_suffix = ($it['priceUnit'] !== '') ? ' ' . $it['priceUnit'] : '';
                            $base_price = $has_price
                                ? ($it['price'] . ' €' . $unit_suffix)
                                : ($it['priceUnit'] !== '' ? $it['priceUnit'] : '—');
                            $has_maxi = ($it['priceMaxi'] !== '');
                        ?>
                            <tr>
                                <td><?php echo esc_html($it['name']); ?></td>
                                <td style="color:#666"><?php echo esc_html($base_price); ?></td>
                                <td>
                                    <?php if ($has_price): ?>
                                        <input type="text" name="price[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr(isset($o['price']) ? $o['price'] : ''); ?>" placeholder="es. 9.50" style="width:90px" inputmode="decimal">
                                        <?php if ($unit_suffix !== ''): ?><span style="color:#999;font-size:12px"><?php echo esc_html($unit_suffix); ?></span><?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#999;font-size:12px">prezzi nel testo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($has_maxi): ?>
                                        <input type="text" name="maxi[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr(isset($o['priceMaxi']) ? $o['priceMaxi'] : ''); ?>" placeholder="<?php echo esc_attr($it['priceMaxi']); ?>" style="width:80px" inputmode="decimal">
                                    <?php else: ?>
                                        <span style="color:#ccc">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center">
                                    <input type="checkbox" name="soldout[<?php echo esc_attr($id); ?>]" value="1" <?php checked(!empty($o['soldOut'])); ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>

                <p style="margin-top:24px">
                    <?php submit_button('Salva modifiche', 'primary', 'submit', false); ?>
                    &nbsp; <a href="<?php echo esc_url(home_url('/menu/')); ?>" target="_blank" class="button">Apri il menù</a>
                </p>
            </form>
        </div>
        <?php
    }
}
