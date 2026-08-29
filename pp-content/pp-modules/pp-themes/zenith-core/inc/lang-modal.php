<?php
/**
 * Zenith Core — Shared Language-Switch Modal
 * -------------------------------------------
 * Requires (from calling scope):
 *   $hz_avail_langs   – array of enabled lang codes
 *   $hz_all_lang_names – ['en'=>'English', …]
 *   $hz_ui             – current-lang UI label array (from inc/lang.php)
 *   $hz_current_lang   – active lang code
 *   $data['lang']      – PipraPay lang array
 */
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }
?>
<style>
/* ── Language modal — standalone (no Bootstrap dropdown) ── */
#hz-lang-modal-overlay{
    display:none;position:fixed;inset:0;z-index:10000;
    background:rgba(15,23,42,.45);backdrop-filter:blur(2px);
    align-items:flex-start;justify-content:center;padding:56px 16px 32px;
}
#hz-lang-modal-overlay.hz-lm-on{display:flex}
.hz-lm-box{
    background:var(--card);border:1px solid var(--line);
    border-radius:var(--r2);width:100%;max-width:400px;
    box-shadow:0 8px 32px rgba(0,0,0,.18);overflow:hidden;
    display:flex;flex-direction:column;max-height:80vh;
}
.hz-lm-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 18px;border-bottom:1px solid var(--line);flex-shrink:0;
}
.hz-lm-title{font-size:15px;font-weight:600;color:var(--text)}
.hz-lm-close{
    width:28px;height:28px;border-radius:6px;border:none;background:var(--bg);
    color:var(--sub);cursor:pointer;display:flex;align-items:center;justify-content:center;
    font-size:14px;transition:background 130ms,color 130ms;flex-shrink:0;font-family:inherit;
}
.hz-lm-close:hover{background:var(--line);color:var(--text)}
.hz-lm-body{padding:14px 16px;overflow-y:auto;flex:1}
.hz-lm-label{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:10px}
.hz-lm-list{display:flex;flex-direction:column;gap:6px}
.hz-lm-item{
    display:flex;align-items:center;justify-content:space-between;
    padding:11px 14px;border-radius:var(--r);border:1.5px solid var(--line);
    cursor:pointer;transition:border-color 130ms,background 130ms;
    background:var(--card);text-decoration:none;
}
.hz-lm-item:hover{border-color:var(--p-bd);background:var(--p-lt)}
.hz-lm-item.hz-lm-active{border-color:var(--p);background:var(--p-lt)}
.hz-lm-item-text{font-size:14px;font-weight:500;color:var(--text)}
.hz-lm-item.hz-lm-active .hz-lm-item-text{font-weight:700;color:var(--p)}
.hz-lm-check{width:18px;height:18px;border-radius:50%;background:var(--p);
    display:none;align-items:center;justify-content:center;flex-shrink:0}
.hz-lm-check svg{width:10px;height:10px;stroke:#fff;stroke-width:2.5}
.hz-lm-item.hz-lm-active .hz-lm-check{display:flex}
</style>

<div id="hz-lang-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="hz-lm-title-text">
    <div class="hz-lm-box">
        <div class="hz-lm-head">
            <span class="hz-lm-title" id="hz-lm-title-text">
                <?php echo htmlspecialchars($hz_ui['select_language'] ?? ($data['lang']['select_language'] ?? 'Select Language')); ?>
            </span>
            <button class="hz-lm-close" onclick="hzCloseLangModal()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="hz-lm-body">
            <div class="hz-lm-label">
                <?php echo htmlspecialchars($hz_ui['language'] ?? ($data['lang']['language'] ?? 'Language')); ?>
            </div>
            <div class="hz-lm-list">
                <?php foreach ($hz_avail_langs as $lc): if (isset($hz_all_lang_names[$lc])): ?>
                <a class="hz-lm-item<?php echo ($lc === $hz_current_lang) ? ' hz-lm-active' : ''; ?>"
                   href="#"
                   data-lang="<?php echo htmlspecialchars($lc); ?>"
                   onclick="hzSwitchLang(event,this)">
                    <span class="hz-lm-item-text"><?php echo htmlspecialchars($hz_all_lang_names[$lc]); ?></span>
                    <span class="hz-lm-check" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                </a>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    /* ── PHP-supplied data ── */
    var hzAvailLangs  = <?php echo json_encode(array_values($hz_avail_langs)); ?>;
    var hzCurrentLang = <?php echo json_encode($hz_current_lang); ?>;

    /* ── Core modal helpers ── */
    function openLangModal(){
        var o=document.getElementById('hz-lang-modal-overlay');
        if(o){o.classList.add('hz-lm-on');document.body.style.overflow='hidden';}
    }
    function closeLangModal(){
        var o=document.getElementById('hz-lang-modal-overlay');
        if(o){o.classList.remove('hz-lm-on');document.body.style.overflow='';}
    }
    window.hzCloseLangModal = closeLangModal;

    /**
     * hzOpenLangModal — smart entry point called by ALL pages.
     *
     *  • Exactly 2 available langs → switch to the other one directly (no modal).
     *  • 3+ langs                  → open the picker modal as usual.
     *
     * Pages no longer need their own inline if/else PHP logic for this.
     */
    window.hzOpenLangModal = function(){
        if(hzAvailLangs.length === 2){
            var other = hzAvailLangs.filter(function(l){ return l !== hzCurrentLang; })[0];
            if(!other) other = hzAvailLangs[0];
            var url = new URL(window.location.href);
            url.searchParams.set('lang', other);
            window.location.href = url.toString();
        } else {
            openLangModal();
        }
    };

    /* Close on overlay click */
    document.addEventListener('click',function(e){
        var o=document.getElementById('hz-lang-modal-overlay');
        if(o && e.target===o) closeLangModal();
    });
    /* Close on Escape */
    document.addEventListener('keydown',function(e){
        if(e.key==='Escape') closeLangModal();
    });

    /* Individual item click inside the modal */
    window.hzSwitchLang=function(e,el){
        e.preventDefault();
        var lang=el.getAttribute('data-lang');
        if(!lang) return;
        var url=new URL(window.location.href);
        url.searchParams.set('lang',lang);
        window.location.href=url.toString();
    };

    /* Shim: Bootstrap-style triggers still work */
    document.addEventListener('DOMContentLoaded',function(){
        document.querySelectorAll('[data-bs-target="#hz-lang-modal"]').forEach(function(btn){
            btn.removeAttribute('data-bs-toggle');
            btn.removeAttribute('data-bs-target');
            btn.addEventListener('click',function(e){e.preventDefault();window.hzOpenLangModal();});
        });
    });
})();
</script>
