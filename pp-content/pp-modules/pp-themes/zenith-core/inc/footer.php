<?php
/**
 * Zenith Core — Shared Footer Component
 * ---------------------------------------
 * Renders the configurable footer text block.
 * Requires (from calling scope):
 *   $data['options']['footer_text']
 *   $hz_current_lang   – active lang code  (set by inc/lang.php)
 *   $hz_subst_map_js   – JSON substitution map (set by inc/lang.php)
 */
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

$_hz_footer_html = trim($data['options']['footer_text'] ?? '');
if ($_hz_footer_html && $_hz_footer_html !== '--'):
?>
<div class="hz-footer-links"><?php echo $_hz_footer_html; ?></div>
<?php endif; ?>
<?php if (isset($hz_current_lang) && $hz_current_lang !== 'en' && isset($hz_subst_map_js)): ?>
<script>
/* ── Zenith page-wide word substitution ───────────────────────────────
   Runs on every page (receipt, checkout, checkout-status, gateway, etc.)
   Walks all visible text nodes in <body> and applies the substitution
   map from lang.php — same map used by gateway.php's Step 0.

   Why text nodes (not innerHTML replace)?
     • Safe: never corrupts HTML tags or attributes
     • Idempotent: already-substituted words won't match again
     • Works on core-rendered labels that theme JS can't control via PHP

   Timing: DOMContentLoaded — fires once after all HTML is parsed.
   On gateway.php this runs before $(document).ready builds the
   instruction card; that's fine because Step 0 runs again on the
   instruction HTML and both passes are idempotent.
──────────────────────────────────────────────────────────────────── */
(function(){
    var substMap = <?php echo $hz_subst_map_js; ?>;
    var keys = Object.keys(substMap);
    if (!keys.length) return;

    /* Sort longest-first so "যাচাই করুন চাপুন" matches before "যাচাই করুন" */
    keys.sort(function(a, b){ return b.length - a.length; });

    /* Tags whose text content must not be touched */
    var SKIP = { SCRIPT:1, STYLE:1, NOSCRIPT:1, META:1, LINK:1, TEXTAREA:1, INPUT:1 };

    function applySubst(textNode) {
        var v = textNode.nodeValue;
        var changed = false;
        for (var i = 0; i < keys.length; i++) {
            var from = keys[i];
            if (v.indexOf(from) !== -1) {
                /* Simple split-join — safe, no regex, handles all occurrences */
                v = v.split(from).join(substMap[from]);
                changed = true;
            }
        }
        if (changed) textNode.nodeValue = v;
    }

    function walkBody(root) {
        var walker = document.createTreeWalker(
            root,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    var p = node.parentNode;
                    if (!p) return NodeFilter.FILTER_REJECT;
                    if (SKIP[p.nodeName]) return NodeFilter.FILTER_REJECT;
                    /* Skip empty/whitespace-only nodes */
                    if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_SKIP;
                    return NodeFilter.FILTER_ACCEPT;
                }
            },
            false
        );
        /* Collect first, then mutate — safe against live NodeList issues */
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        for (var j = 0; j < nodes.length; j++) applySubst(nodes[j]);
    }

    /* Export so gateway.php can call it again after building the instruction card */
    window.hzRunPageSubst = function() {
        if (document.body) walkBody(document.body);
    };

    document.addEventListener('DOMContentLoaded', window.hzRunPageSubst);
})();
</script>
<?php endif; ?>
