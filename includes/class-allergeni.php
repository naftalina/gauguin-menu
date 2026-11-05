<?php
/**
 * Allergeni del menu (Reg. UE 1169/2011, all. II; D.Lgs. 231/2017).
 *
 * Dati in wp_option `gxm_allergeni`:
 *   voci        id => [numeri 1..14]
 *   verificati  id => true   (voce controllata dal locale)
 *   pubblicato  bool         (finche' e' false i clienti non vedono nulla)
 *
 * Al primo uso le voci partono dalla bozza `data/allergeni-bozza.json`,
 * preparata dagli ingredienti scritti nel menu: e' una proposta, non un dato
 * certo, e va verificata voce per voce prima di pubblicare.
 *
 * Nel menu gli allergeni si aggiungono sotto ogni piatto con uno script a
 * parte (gli elementi hanno data-testid="menu-item-<id>"), senza ricompilare
 * l'app React.
 */

if (!defined('ABSPATH')) exit;

class GXM_Allergeni {

    const OPTION = 'gxm_allergeni';

    const ELENCO = [
        1  => ['Glutine', 'Gluten'],
        2  => ['Crostacei', 'Crustaceans'],
        3  => ['Uova', 'Eggs'],
        4  => ['Pesce', 'Fish'],
        5  => ['Arachidi', 'Peanuts'],
        6  => ['Soia', 'Soy'],
        7  => ['Latte', 'Milk'],
        8  => ['Frutta a guscio', 'Tree nuts'],
        9  => ['Sedano', 'Celery'],
        10 => ['Senape', 'Mustard'],
        11 => ['Sesamo', 'Sesame'],
        12 => ['Solfiti', 'Sulphites'],
        13 => ['Lupini', 'Lupin'],
        14 => ['Molluschi', 'Molluscs'],
    ];

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'menu'], 20);
            add_action('admin_post_gxm_save_allergeni', [$this, 'save']);
        }
    }

    public function menu() {
        add_submenu_page('gxm-menu', 'Allergeni', 'Allergeni', 'manage_options', 'gxm-allergeni', [$this, 'render']);
    }

    private static function bozza() {
        $f = GXM_DIR . 'data/allergeni-bozza.json';
        $d = is_readable($f) ? json_decode((string) file_get_contents($f), true) : [];
        return is_array($d) ? $d : [];
    }

    public static function dati() {
        $d = get_option(self::OPTION, null);
        if (!is_array($d)) {
            $voci = [];
            foreach (self::bozza() as $id => $v) {
                if ($id === '_info' || !is_array($v)) continue;
                $voci[(string) $id] = array_values(array_map('intval', $v['a'] ?? []));
            }
            $d = ['voci' => $voci, 'verificati' => [], 'pubblicato' => false, 'aggiornato' => 0];
            update_option(self::OPTION, $d, false);
        }
        $d += ['voci' => [], 'verificati' => [], 'pubblicato' => false, 'aggiornato' => 0];
        return $d;
    }

    public function save() {
        if (!current_user_can('manage_options')) wp_die('Non autorizzato.');
        check_admin_referer('gxm_save_allergeni');

        $valid = [];
        foreach (GXM_Admin::items() as $cat) {
            foreach ($cat['items'] as $it) {
                if (empty($it['isHeader'])) $valid[(string) $it['id']] = true;
            }
        }
        $in = isset($_POST['all']) && is_array($_POST['all']) ? wp_unslash($_POST['all']) : [];
        $ver = isset($_POST['ver']) && is_array($_POST['ver']) ? wp_unslash($_POST['ver']) : [];

        $voci = [];
        $verificati = [];
        foreach (array_keys($valid) as $id) {
            $nums = isset($in[$id]) && is_array($in[$id]) ? array_keys($in[$id]) : [];
            $nums = array_values(array_filter(array_map('intval', $nums), function ($n) { return $n >= 1 && $n <= 14; }));
            sort($nums);
            $voci[$id] = $nums;
            if (!empty($ver[$id])) $verificati[$id] = true;
        }
        $d = self::dati();
        $d['voci'] = $voci;
        $d['verificati'] = $verificati;
        $d['pubblicato'] = !empty($_POST['pubblicato']);
        $d['aggiornato'] = time();
        update_option(self::OPTION, $d, false);

        wp_safe_redirect(admin_url('admin.php?page=gxm-allergeni&salvato=1'));
        exit;
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        $d = self::dati();
        $note = self::bozza();
        $tot = 0;
        $ok = 0;
        foreach (GXM_Admin::items() as $cat) {
            foreach ($cat['items'] as $it) {
                if (!empty($it['isHeader'])) continue;
                $tot++;
                if (!empty($d['verificati'][(string) $it['id']])) $ok++;
            }
        }
        $visti = [];
        ?>
        <div class="wrap">
            <h1>Allergeni del menu</h1>
            <?php if (!empty($_GET['salvato'])) : ?>
                <div class="notice notice-success"><p>Salvato.</p></div>
            <?php endif; ?>
            <div class="notice notice-warning inline"><p>
                <strong>Le caselle di partenza sono una bozza</strong> ricavata dagli ingredienti scritti nel menu.
                Controlla ogni voce con le ricette e le etichette dei prodotti (impasti, salumi, salse, fritti già pronti)
                e spunta «Verificato». Le note grigie dicono cosa controllare. I clienti vedono gli allergeni solo dopo
                «Mostra gli allergeni nel menu». Per vedere il risultato prima di pubblicare apri
                <a href="<?php echo esc_url(home_url('/menu/?anteprima_allergeni=1')); ?>" target="_blank">il menu in anteprima</a>
                (solo tu, da collegato, vedi i numeri).
            </p></div>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="gxm_save_allergeni">
                <?php wp_nonce_field('gxm_save_allergeni'); ?>
                <p style="font-size:14px">
                    Verificate <strong><?php echo (int) $ok; ?></strong> voci su <strong><?php echo (int) $tot; ?></strong>.
                    <label style="margin-left:16px;font-weight:600">
                        <input type="checkbox" name="pubblicato" value="1" <?php checked($d['pubblicato']); ?>>
                        Mostra gli allergeni nel menu
                    </label>
                    <?php submit_button('Salva', 'primary', 'submit', false, ['style' => 'margin-left:16px']); ?>
                </p>
                <p class="description">
                    <?php foreach (self::ELENCO as $n => $nome) echo '<strong>' . (int) $n . '</strong> ' . esc_html($nome[0]) . ' &nbsp; '; ?>
                </p>
                <style>
                    .gxm-all td, .gxm-all th { vertical-align: top; padding: 6px 4px; }
                    .gxm-all .n { text-align: center; width: 26px; font-size: 11px; }
                    .gxm-all .nota { color: #646970; font-size: 12px; }
                    .gxm-all tr.ok td:first-child { border-left: 4px solid #00a32a; }
                    .gxm-all tr.da td:first-child { border-left: 4px solid #dba617; }
                </style>
                <?php foreach (GXM_Admin::items() as $cat) : ?>
                    <h2 style="margin-top:24px"><?php echo esc_html($cat['name']); ?></h2>
                    <table class="widefat striped gxm-all">
                        <thead><tr>
                            <th>Voce</th>
                            <?php foreach (self::ELENCO as $n => $nome) : ?>
                                <th class="n" title="<?php echo esc_attr($nome[0]); ?>"><?php echo (int) $n; ?></th>
                            <?php endforeach; ?>
                            <th>Verificato</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($cat['items'] as $it) :
                            if (!empty($it['isHeader'])) continue;
                            $id = (string) $it['id'];
                            // Stesso prodotto in due categorie: i campi solo la prima volta.
                            $doppione = isset($visti[$id]);
                            $visti[$id] = true;
                            $sel = $d['voci'][$id] ?? [];
                            $ver = !empty($d['verificati'][$id]);
                            ?>
                            <tr class="<?php echo $ver ? 'ok' : 'da'; ?>">
                                <td>
                                    <strong><?php echo esc_html($it['name']); ?></strong>
                                    <?php if (!empty($note[$id]['n'])) : ?><div class="nota"><?php echo esc_html($note[$id]['n']); ?></div><?php endif; ?>
                                    <?php if ($doppione) : ?><div class="nota">Già in un'altra categoria: si modifica lì.</div><?php endif; ?>
                                </td>
                                <?php foreach (self::ELENCO as $n => $nome) : ?>
                                    <td class="n"><?php if (!$doppione) : ?>
                                        <input type="checkbox" name="all[<?php echo esc_attr($id); ?>][<?php echo (int) $n; ?>]" value="1"
                                            title="<?php echo esc_attr($nome[0]); ?>" <?php checked(in_array($n, $sel, true)); ?>>
                                    <?php endif; ?></td>
                                <?php endforeach; ?>
                                <td><?php if (!$doppione) : ?>
                                    <input type="checkbox" name="ver[<?php echo esc_attr($id); ?>]" value="1" <?php checked($ver); ?>>
                                <?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
                <?php submit_button('Salva'); ?>
            </form>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Menu pubblico                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Script e stile iniettati nella pagina del menu. Se gli allergeni non
     * sono pubblicati resta comunque l'avviso «chiedi al personale», che e'
     * la forma minima di informazione ammessa.
     */
    public static function public_html() {
        $d = self::dati();
        // Anteprima per l'amministratore: /menu/?anteprima_allergeni=1 mostra
        // gli allergeni anche se non sono ancora pubblicati.
        $vedi = $d['pubblicato']
            || (isset($_GET['anteprima_allergeni']) && function_exists('current_user_can') && current_user_can('manage_options'));
        $dati = [
            'pubblicato' => (bool) $vedi,
            'voci' => $vedi ? $d['voci'] : new stdClass(),
            'elenco' => self::ELENCO,
        ];
        ob_start();
        ?>
<style>
.gxm-all-riga{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;align-items:center;cursor:pointer}
.gxm-all-riga .et{border:0;padding:0 2px 0 0;min-width:0;font-weight:500;opacity:.6}
.gxm-all-riga span{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;border:1px solid currentColor;font:600 12px/1 system-ui,-apple-system,sans-serif;opacity:.75}
.gxm-all-btn{position:fixed;left:max(12px,env(safe-area-inset-left));bottom:max(12px,env(safe-area-inset-bottom));z-index:40;border:0;border-radius:999px;padding:10px 16px;font:600 15px/1 system-ui,-apple-system,sans-serif;background:#1f2937;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.25);cursor:pointer}
.gxm-all-velo{position:fixed;inset:0;z-index:60;background:rgba(0,0,0,.5);display:flex;align-items:flex-end;justify-content:center}
.gxm-all-foglio{background:#fff;color:#111;width:100%;max-width:520px;max-height:85dvh;overflow:auto;border-radius:16px 16px 0 0;padding:20px 20px calc(20px + env(safe-area-inset-bottom));font:15px/1.45 system-ui,-apple-system,sans-serif}
.gxm-all-foglio h2{font-size:20px;margin:0 0 8px}
.gxm-all-foglio ol{margin:12px 0;padding:0;list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:6px 12px}
.gxm-all-foglio li b{display:inline-block;min-width:24px}
.gxm-all-foglio p{margin:8px 0;color:#444}
.gxm-all-foglio button{margin-top:12px;width:100%;border:0;border-radius:10px;padding:12px;font:600 16px system-ui,sans-serif;background:#1f2937;color:#fff}
</style>
<script>
(function(){
  var D = <?php echo wp_json_encode($dati); ?>;
  function en(){
    var h = document.querySelector('#category-antipasti h2');
    return !!h && !/Antipasti/i.test(h.textContent || '');
  }
  function testi(){
    var e = en();
    return {
      btn: e ? 'Allergens' : 'Allergeni',
      titolo: e ? 'Allergens' : 'Allergeni',
      intro: D.pubblicato
        ? (e ? 'Numbers under each dish refer to the 14 allergens of EU Regulation 1169/2011:' : 'I numeri sotto ogni piatto indicano i 14 allergeni del Reg. UE 1169/2011:')
        : (e ? 'Information on the 14 allergens of EU Regulation 1169/2011 is available from our staff.' : 'Le informazioni sui 14 allergeni del Reg. UE 1169/2011 sono disponibili presso il personale.'),
      tracce: e ? 'Our kitchen handles all these ingredients: traces cannot be ruled out. If you have an allergy or intolerance, please tell our staff before ordering.' : 'In cucina si lavorano tutti questi ingredienti: non si possono escludere tracce. Se hai allergie o intolleranze dillo al personale prima di ordinare.',
      chiudi: e ? 'Close' : 'Chiudi'
    };
  }
  function nome(n){ return D.elenco[n][en() ? 1 : 0]; }
  function marca(){
    if (!D.pubblicato) return;
    document.querySelectorAll('[data-testid^="menu-item-"]').forEach(function(el){
      var gia = el.querySelector('.gxm-all-riga .et');
      if (gia) {
        // Cambio lingua: React riscrive i testi ma tiene i riquadri.
        var lingua = en() ? 'Allergens' : 'Allergeni';
        if (gia.textContent !== lingua) gia.textContent = lingua;
        return;
      }
      var id = el.getAttribute('data-testid').replace('menu-item-','');
      var a = D.voci[id];
      if (!a || !a.length) return;
      var box = el.firstElementChild || el;
      var r = document.createElement('div');
      r.className = 'gxm-all-riga';
      r.setAttribute('aria-label', (en() ? 'Allergens: ' : 'Allergeni: ') + a.map(nome).join(', '));
      var et = document.createElement('span'); et.className = 'et'; et.textContent = en() ? 'Allergens' : 'Allergeni'; r.appendChild(et);
      a.forEach(function(n){ var s = document.createElement('span'); s.textContent = n; s.title = nome(n); r.appendChild(s); });
      r.addEventListener('click', apri);
      box.appendChild(r);
    });
  }
  function apri(){
    var t = testi();
    var v = document.createElement('div'); v.className = 'gxm-all-velo';
    var html = '<div class="gxm-all-foglio" role="dialog" aria-modal="true"><h2>' + t.titolo + '</h2><p>' + t.intro + '</p><ol>';
    for (var n = 1; n <= 14; n++) html += '<li><b>' + n + '</b>' + nome(n) + '</li>';
    html += '</ol><p>' + t.tracce + '</p><button type="button">' + t.chiudi + '</button></div>';
    v.innerHTML = html;
    v.addEventListener('click', function(e){ if (e.target === v || e.target.tagName === 'BUTTON') v.remove(); });
    document.body.appendChild(v);
  }
  function bottone(){
    var b = document.querySelector('.gxm-all-btn');
    if (!b) { b = document.createElement('button'); b.type = 'button'; b.className = 'gxm-all-btn'; b.addEventListener('click', apri); document.body.appendChild(b); }
    // Solo se cambia: scrivere sempre farebbe ripartire l'observer all'infinito.
    var testo = testi().btn;
    if (b.textContent !== testo) b.textContent = testo;
  }
  var t0;
  new MutationObserver(function(){ clearTimeout(t0); t0 = setTimeout(function(){ marca(); bottone(); }, 60); })
    .observe(document.documentElement, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', function(){ marca(); bottone(); });
})();
</script>
        <?php
        return ob_get_clean();
    }
}
