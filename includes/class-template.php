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
        // Promo 30 anni: nastro in cima + bottone flottante che portano al
        // form dei ricordi sulla landing (home). Iniettati fuori da #root, così
        // non serve ricompilare l'app React. Vedi plugin gauguin-30anni.
        $inline .= $this->anniv_promo();
        $html = str_replace('<div id="root"></div>', $inline . '<div id="root"></div>', $html);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Promo "30 anni": nastro in cima al menù + bottone flottante, entrambi
     * verso il form dei ricordi sulla landing (home, #gx-form). Un piccolo
     * JS calcola i giorni mancanti al 2 novembre 2026 e nasconde tutto dopo
     * l'evento. Solo IT (pubblico locale), mobile-first, palette bordeaux.
     */
    private function anniv_promo() {
        $url = esc_url(home_url('/')) . '#gx-form';
        ob_start();
        ?>
<style id="gxm-anniv-css">
.gxm-anniv{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px 14px;padding:11px 16px;background:linear-gradient(135deg,#F4C862,#E0A128);color:#6E1120;font-family:'Inter',system-ui,-apple-system,sans-serif;font-size:15px;line-height:1.3;text-align:center;position:relative;z-index:6;box-shadow:0 3px 12px rgba(0,0,0,.25)}
.gxm-anniv strong{font-weight:800}
.gxm-anniv-cd{font-weight:700}
.gxm-anniv-cta{display:inline-block;background:#8C172A;color:#FBF4E6;font-weight:700;text-decoration:none;padding:7px 16px;border-radius:999px;white-space:nowrap;font-size:14px;box-shadow:0 2px 6px rgba(110,17,32,.35)}
.gxm-anniv-cta:active{transform:translateY(1px)}
.gxm-fab{position:fixed;right:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));z-index:9999;display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#F4C862,#E0A128);color:#6E1120;font-family:'Inter',system-ui,-apple-system,sans-serif;font-weight:800;font-size:15px;text-decoration:none;padding:13px 19px;border-radius:999px;box-shadow:0 6px 18px rgba(0,0,0,.3),0 0 0 0 rgba(224,161,40,.6);animation:gxm-fab-pulse 2.2s ease-out infinite}
.gxm-fab:active{transform:translateY(1px)}
@keyframes gxm-fab-pulse{0%{box-shadow:0 6px 18px rgba(0,0,0,.3),0 0 0 0 rgba(224,161,40,.6)}70%{box-shadow:0 6px 18px rgba(0,0,0,.3),0 0 0 14px rgba(224,161,40,0)}100%{box-shadow:0 6px 18px rgba(0,0,0,.3),0 0 0 0 rgba(224,161,40,0)}}
@media(max-width:600px){.gxm-fab{left:50%;right:auto;transform:translateX(-50%);bottom:calc(14px + env(safe-area-inset-bottom,0px))}.gxm-fab:active{transform:translateX(-50%) translateY(1px)}}
@media(prefers-reduced-motion:reduce){.gxm-fab{animation:none}}
</style>
<div class="gxm-anniv" id="gxm-anniv">
  <span>🎉 <strong>Gauguin compie 30 anni</strong> · festeggia con noi il 2 novembre<span class="gxm-anniv-cd" id="gxm-anniv-cd"></span></span>
  <a class="gxm-anniv-cta" href="<?php echo $url; ?>">Lascia il tuo ricordo</a>
</div>
<a class="gxm-fab" id="gxm-fab" href="<?php echo $url; ?>" aria-label="Lascia il tuo ricordo per i 30 anni del Gauguin">✍️ Lascia un ricordo</a>
<script>
(function(){
  var target=new Date(2026,10,2,19,0,0);      // 2 nov 2026, 19:00
  var hideAfter=new Date(2026,10,3,0,0,0);     // sparisce dal 3 novembre
  var now=new Date();
  var bar=document.getElementById('gxm-anniv');
  var fab=document.getElementById('gxm-fab');
  var cd=document.getElementById('gxm-anniv-cd');
  if(now>=hideAfter){ if(bar)bar.style.display='none'; if(fab)fab.style.display='none'; return; }
  if(cd){
    var days=Math.ceil((target-now)/86400000);
    if(days>1) cd.textContent=' · tra '+days+' giorni';
    else if(days===1) cd.textContent=' · è domani!';
    else cd.textContent=' · è oggi! 🎉';
  }
})();
</script>
<?php
        return ob_get_clean();
    }
}
