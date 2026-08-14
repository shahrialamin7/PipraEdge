<?php
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

if (isset($_GET['lang']) && $_GET['lang'] !== '') {
    pp_set_lang($_GET['lang']);
    $redir = pp_checkout_address() . '?gateway=' . urlencode($_GET['gateway'] ?? '');
    echo '<script>location.href='.json_encode($redir).';</script>'; exit();
}
if (!isset($_GET['gateway'])) { http_response_code(403); exit('Direct access not allowed'); }

/* ── Expired (verification window / session timed out) ── */
if (isset($_GET['expired']) && !empty($data['transaction']['ref'])) {
    pp_set_transaction_status($data['transaction']['ref'], 'expired');
    $rurl = $data['transaction']['return_url'] ?? '';
    $dest = ($rurl && $rurl !== '--') ? $rurl : pp_checkout_address();
    echo '<script>window.location.replace('.json_encode($dest).');</script>'; exit();
}

$gateway_info = pp_gateway_info($_GET['gateway'], $data);
if ($gateway_info['status'] == false) { http_response_code(403); exit('Direct access not allowed'); }

$gz_primary  = $gateway_info['gateway']['primary_color'] ?? '#1a56db';
$gz_text_col = $gateway_info['gateway']['text_color']    ?? '#ffffff';
$gz_gw_name  = $gateway_info['gateway']['display']       ?? '';
$gz_gw_logo  = $gateway_info['gateway']['logo']          ?? '';

$hz_primary  = $data['options']['primary_color'] ?? '#1a56db';
$hz_text_col = $data['options']['text_color']    ?? '#FFFFFF';
$hz_amount   = $data['transaction']['amount']    ?? 0;
$hz_currency = $data['transaction']['currency']  ?? '';
$hz_ref      = $data['transaction']['ref']       ?? '';
$hz_fee      = $data['transaction']['processing_fee']  ?? 0;
$hz_discount = $data['transaction']['discount_amount'] ?? 0;
$hz_payable  = number_format((float)$hz_amount - (float)$hz_discount + (float)$hz_fee, 2);

$bgStyle = 'background-color:#eef0f5;';
if (!empty($data['options']['enable_bg_image']) && $data['options']['enable_bg_image']==='enabled' && !empty($data['options']['background_image']))
    $bgStyle = "background-image:url('" . htmlspecialchars($data['options']['background_image'], ENT_QUOTES) . "');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;";

/* ── Session Timeout ── */
$hz_st_minutes   = max(0, (int)trim($data['options']['session_timeout_minutes'] ?? '15'));
$hz_st_enabled   = (!empty($data['options']['session_timeout']) && $data['options']['session_timeout'] === 'enabled' && $hz_st_minutes > 0);
$hz_st_remaining = 0;
if ($hz_st_enabled) {
    $hz_created      = strtotime($data['transaction']['created_date'] ?? 'now');
    $hz_elapsed      = time() - $hz_created;
    $hz_st_remaining = ($hz_st_minutes * 60) - $hz_elapsed;
    if ($hz_st_remaining <= 0) {
        pp_set_transaction_status($data['transaction']['ref'], 'expired');
        $rurl = $data['transaction']['return_url'] ?? '';
        $dest = ($rurl && $rurl !== '--') ? $rurl : pp_checkout_address();
        echo '<script>window.location.replace('.json_encode($dest).');</script>'; exit();
    }
}

$hz_avail_langs_raw = $data['options']['available_languages'] ?? 'en,bn,hi,ur,ar';
$hz_avail_langs     = array_filter(array_map('trim', explode(',', $hz_avail_langs_raw)));
$seoTitle           = $data['options']['seo_title'] ?? '';
$anCode             = $data['options']['analytics_code'] ?? '';

/* ── Auto language correction ────────────────────────────────────────────────
 * If the session language is missing or no longer in the admin-enabled list,
 * reset to the first enabled language and redirect. This forces index.php to
 * rebuild $data['lang'] via resolveModuleLanguage() with the correct session.
 * ─────────────────────────────────────────────────────────────────────────── */
if (!empty($hz_avail_langs)) {
    $hz_sess_lang = $_SESSION['ui_language'] ?? '';
    if (empty($hz_sess_lang) || !in_array($hz_sess_lang, $hz_avail_langs, true)) {
        pp_set_lang(reset($hz_avail_langs));
        $hz_qs = trim(preg_replace('/(^|&)lang=[^&]*/i', '', $_SERVER['QUERY_STRING'] ?? ''), '&');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($hz_qs !== '' ? '?' . $hz_qs : ''));
        exit();
    }
}

/* ── Shared lang dictionary (sets $hz_ui, $hz_ui_js, $hz_current_lang, etc.) ── */
require_once __DIR__ . '/inc/lang.php';

/* Alias for backward-compat with the JS block below */
$ui    = $hz_ui;
$ui_js = $hz_ui_js;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($hz_current_lang); ?>" dir="<?php echo in_array($hz_current_lang,['ar','ur']) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo htmlspecialchars($gz_gw_name); ?> — <?php echo htmlspecialchars($data['brand']['name']); ?></title>
<link rel="shortcut icon" href="<?php echo $data['brand']['favicon']; ?>">
<?php if($seoTitle&&$seoTitle!=='--') echo '<meta name="title" content="'.htmlspecialchars($seoTitle).'">'; ?>
<?php if($anCode&&$anCode!=='--') echo $anCode; ?>
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'" crossorigin="anonymous">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"></noscript>
<?php echo pp_assets('head'); ?>
<style>
:root{
    --p:    <?php echo $hz_primary; ?>;
    --p-lt: <?php echo pp_hexToRgba($hz_primary, 0.09); ?>;
    --p-tx: <?php echo $hz_text_col; ?>;
    --gp:   <?php echo $gz_primary; ?>;
    --gp-lt:<?php echo pp_hexToRgba($gz_primary, 0.10); ?>;
    --gp-dk:<?php echo pp_hexToRgba($gz_primary, 0.82); ?>;
    --gp-tx:<?php echo $gz_text_col; ?>;
    --text:#111827; --sub:#6b7280; --muted:#9ca3af; --line:#e5e7eb;
    --bg:#f5f6f9; --card:#ffffff; --r:6px; --r2:10px; --r3:13px;
    --sh:0 1px 3px rgba(0,0,0,.07),0 4px 14px rgba(0,0,0,.05);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
@keyframes hzFadeIn{from{opacity:0}to{opacity:1}}
body{animation:hzFadeIn .15s ease}
body{font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}button{font-family:inherit;cursor:pointer;border:none;outline:none;background:none}
.hz-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:26px 15px 52px}
.hz-wrap{width:100%;max-width:520px;display:flex;flex-direction:column;flex:1 0 auto}

/* Brand */
.hz-brand{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:0 2px;min-width:0;justify-content:space-between}
.hz-brand img{width:34px;height:34px;border-radius:7px;object-fit:cover;border:1px solid var(--line);flex-shrink:0}
.hz-brand-name{font-size:15px;font-weight:600;color:var(--text);letter-spacing:-.2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.hz-brand-timer{font-size:13px;font-weight:600;color:var(--sub);font-variant-numeric:tabular-nums;letter-spacing:.3px;flex-shrink:0}

/* Nav */
.hz-nav{display:flex;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:var(--r2);margin-bottom:11px;box-shadow:var(--sh);overflow:hidden}
.hz-nav-cancel{display:flex;align-items:center;gap:7px;padding:0 14px;height:44px;font-size:13px;font-weight:500;color:var(--sub);flex-shrink:0;transition:color 140ms,background 140ms;white-space:nowrap}
.hz-nav-cancel:hover{color:var(--p);background:var(--p-lt)}
.hz-nav-cancel svg{width:13px;height:13px;flex-shrink:0}
.hz-nav-pills{display:flex;align-items:center;flex:1;overflow-x:auto;scrollbar-width:none;padding:5px 6px;justify-content:flex-end;gap:2px;min-width:0}
.hz-nav-pills::-webkit-scrollbar{display:none}
.hz-nav-pill{display:flex;align-items:center;gap:6px;padding:0 11px;height:32px;border-radius:6px;font-size:13px;font-weight:500;color:var(--sub);white-space:nowrap;flex-shrink:0;transition:color 130ms,background 130ms}
.hz-nav-pill:hover{color:var(--text);background:var(--bg)}
.hz-nav-pill svg{width:14px;height:14px;flex-shrink:0}
.hz-nav-pill .pill-label{display:inline}
@media(max-width:400px){.hz-nav-pill .pill-label{display:none}.hz-nav-pill{padding:0 9px}}

/* Gateway logo — reduced spacing */
.hz-gw-logo-center{display:flex;align-items:center;justify-content:center;padding:10px 0 0;margin-bottom:8px}
.hz-gw-logo-box{display:inline-flex;align-items:center;justify-content:center;border-radius:16px;overflow:hidden;background:var(--card);border:1px solid var(--line);padding:10px 18px;box-shadow:var(--sh)}
.hz-gw-logo-center img{max-height:56px;max-width:160px;object-fit:contain;display:inline-block}

/* ===== RECEIPT / AMOUNT CARD ===== */
.hz-amount-bar{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--sh);margin-bottom:12px;display:flex;align-items:stretch;width:100%}
.hz-amount-half {
    flex: 1;
    padding: 10px 14px;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.hz-amount-half:first-child {
    align-items: flex-start;
    gap: 4px;
}
.hz-amount-half:last-child {
    text-align: right;
}
.hz-amount-half + .hz-amount-half::before {
    content: '';
    position: absolute;
    left: 0;
    top: 10px;
    bottom: 10px;
    width: 1px;
    background-color: var(--line);
}
.hz-inv-label{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.7px;
    color:var(--muted);
    font-weight:600;
    display:flex;
    align-items:center;
    gap:5px;
    margin-bottom:0;
}
.hz-inv-id{
    font-size:12px;
    color:var(--sub);
    font-family:'Courier New',monospace;
    letter-spacing:.3px;
}
.hz-amount-display {
    display: inline-block;
    max-width: 100%;
    text-align: right;
    word-break: break-word;
}
.hz-amount-num{
    font-size: clamp(16px, 5vw, 23px);
    font-weight:700;
    color:var(--text);
    letter-spacing:-.6px;
    line-height:1.2;
}
.hz-amount-cur{
    font-size: clamp(12px, 3vw, 14px);
    font-weight:500;
    color:var(--sub);
    margin-left:3px;
    white-space: nowrap;
}
/* ===== END RECEIPT CARD ===== */

/* ═══ INSTRUCTION CARD ═══
     Built entirely by JS so title + list are always in one container.
     These styles are applied once the card is injected into the DOM.
═══ */
.hz-inst-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--sh);margin-bottom:12px;overflow:hidden}
.hz-inst-title{padding:10px 16px 9px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);display:flex;align-items:center;gap:7px;border-bottom:1px solid var(--line)}
.hz-inst-title svg{width:13px;height:13px;color:var(--text);flex-shrink:0}

/* Payment instructions list — scoped + also global fallback */
.payment-instructions{list-style:none;padding:0;margin:0}
.payment-instructions li{display:flex;align-items:flex-start;gap:12px;padding:10px 16px;position:relative;width:100%}
.payment-instructions li::after{
    content:'';
    position:absolute;
    bottom:0;left:16px;right:16px;
    height:1px;
    background-color:var(--line);
}
.payment-instructions li:last-child::after{display:none}
.payment-instructions li .dot,
.payment-instructions li .hz-dot{
    width:9px;height:9px;border-radius:50%;min-width:9px;
    background:var(--gp);flex-shrink:0;
    margin-top:6px;
}
.payment-instructions li p{
    flex:1;
    font-size:13.5px;
    color:var(--sub);
    line-height:1.55;
    margin:0;
    word-break:break-word;
}
.payment-instructions li p br{display:none}
.dynamic-value,.dv,.hz-kw{font-weight:700;color:var(--text)}

/* ── Copy / QR button — global (not limited to .hz-inst-card) ── */
.button-icon{display:inline-flex;align-items:center;justify-content:center;gap:5px;
    padding:3px 10px 3px 8px;border-radius:var(--r);
    background:var(--gp);color:var(--gp-tx);
    font-size:12px;font-weight:700;cursor:pointer;
    border:none;font-family:inherit;transition:background 150ms;
    white-space:nowrap;vertical-align:middle;margin-left:4px;
    /* FIX: constrain the icon inside button to small size always */
    line-height:1;
}
.button-icon:hover{background:var(--gp-dk)}
/* FIX: force all SVGs inside button-icon to be small regardless of nesting */
.button-icon svg,
.button-icon > svg{width:13px !important;height:13px !important;flex-shrink:0}
.hz-btn-label{font-size:12px;font-weight:700}

/* ═══ QR IMAGE MODAL (V1 — Zenith-owned, always reliable) ═══ */
.hz-img-modal{
    position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;
    align-items:center;justify-content:center;z-index:9999;padding:20px;
}
.hz-img-modal.on{display:flex}
.hz-img-modal-box{
    position:relative;background:var(--card);border-radius:var(--r3);
    overflow:hidden;max-width:320px;width:100%;border:1px solid var(--line);
    box-shadow:0 8px 28px rgba(0,0,0,.18);animation:popIn .16s ease;
}
@keyframes popIn{from{transform:translateY(6px);opacity:0}to{transform:none;opacity:1}}
.hz-img-modal-box img{display:block;width:100%}
.hz-img-modal-close{
    position:absolute;top:8px;right:8px;width:30px;height:30px;
    border-radius:50%;background:var(--gp);color:var(--gp-tx);
    display:flex;align-items:center;justify-content:center;
    cursor:pointer;font-size:16px;line-height:1;border:none;font-family:inherit;
    transition:background 150ms;
}
.hz-img-modal-close:hover{background:var(--gp-dk)}

/* ═══ FORM / VERIFY CARD ═══ */
.hz-form-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--sh);margin-bottom:12px;overflow:hidden}
.hz-form-card-title{padding:13px 18px 11px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);display:flex;align-items:center;gap:7px;border-bottom:1px solid var(--line);flex-wrap:nowrap}
.hz-form-card-title svg{width:13px;height:13px;color:var(--text);flex-shrink:0}
/* FIX: equal gap above Transaction ID title and below Verify button */
.hz-form-body{padding:14px;display:flex;flex-direction:column}
/* Remove Bootstrap mt-3 from the first visible form-group so only the card padding counts */
.hz-form-body .form-group{margin-top:0!important}
/* Restore spacing between groups when >1 group is visible (e.g. mobile field revealed) */
.hz-form-body .form-group+.form-group{margin-top:12px!important}
.hz-form-body .form-control,
.hz-form-body .form-select{
    border-radius:var(--r)!important;
    border-color:var(--line)!important;
    font-family:'DM Sans',sans-serif!important;
    font-size:14px!important;
    color:var(--text)!important;
    padding:6px 10px!important;
    transition:border-color 150ms!important
}
.hz-form-body .form-control:focus,
.hz-form-body .form-select:focus{
    border-color:var(--gp)!important;
    box-shadow:0 0 0 3px <?php echo pp_hexToRgba($gz_primary,0.12); ?>!important
}
.hz-form-body .form-label{
    font-size:13.5px!important;
    font-weight:700!important;
    color:var(--text)!important;
    margin-bottom:3px!important;
}
.hz-form-body .btn-primary,
.hz-form-body button[type="submit"],
.hz-form-body #payButton{
    background:var(--gp)!important;
    border-color:var(--gp)!important;
    color:var(--gp-tx)!important;
    border-radius:var(--r)!important;
    font-family:'DM Sans',sans-serif!important;
    font-weight:700!important;
    font-size:14.5px!important;
    padding:10px 20px!important;
    width:100%!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:9px!important;
    transition:background 150ms!important;
    letter-spacing:.3px!important;
    /* FIX: increased top gap between trxid field and verify button */
    margin-top:20px!important;
    text-transform:uppercase!important
}
.hz-form-body .btn-primary:hover,
.hz-form-body button[type="submit"]:hover,
.hz-form-body #payButton:hover{
    background:var(--gp-dk)!important;
    border-color:var(--gp-dk)!important
}

@keyframes popIn{from{transform:translateY(6px);opacity:0}to{transform:none;opacity:1}}
@keyframes hzShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}

.hz-footer{text-align:center;margin-top:14px;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;width:100%}
.hz-footer-links{margin-top:32px;padding-bottom:8px;font-size:11.5px;color:var(--muted);text-align:center}
.hz-footer-links a{color:var(--muted);text-decoration:none;transition:color 130ms}
.hz-footer-links a:hover{color:var(--p)}

/* Language switching is handled by the modal in inc/lang-modal.php (.hz-lm-* classes) */

::selection{background:var(--p-lt);color:var(--text)}
*:focus-visible{outline:2px solid var(--p);outline-offset:2px}
a:hover{color:var(--p)}
:root{--tblr-primary:<?php echo $hz_primary; ?>;--tblr-link-color:<?php echo $hz_primary; ?>;--bs-link-color:<?php echo $hz_primary; ?>}
#toast-container{top:16px !important;right:16px !important}
.custom-toast{font-family:'DM Sans',sans-serif !important;border:1px solid var(--line) !important;border-radius:var(--r2) !important;background:var(--card) !important;box-shadow:0 4px 20px rgba(0,0,0,.12) !important;min-width:260px;max-width:320px}
.custom-toast [style*="padding: calc(.25rem * 4)"]{padding:12px 14px !important;gap:0 !important}
.custom-toast [style*="margin-left: 30px"]{margin-left:0 !important;font-size:12.5px;color:var(--sub);margin-top:4px;line-height:1.5}
.custom-toast [style*="font-weight: 500"]{font-size:13.5px;font-weight:600 !important;color:var(--text) !important}
.custom-toast .btn-close{width:20px;height:20px;padding:0;opacity:.4;flex-shrink:0}
.custom-toast .btn-close:hover{opacity:.8}
.custom-toast .toast-svg svg{width:16px;height:16px}
.iti{width:100%!important}
.iti input,.iti input[type=tel]{color:#111827!important;-webkit-text-fill-color:#111827!important}
.iti__selected-dial-code,.iti__arrow{color:#111827!important}
.iti__selected-flag{background:transparent!important}
.iti__country-list{background:#ffffff;color:#111827;border-color:#e5e7eb}
.iti__country-list .iti__country-name,.iti__dial-code{color:#111827!important}
.iti__country-list .iti__country.iti__highlight{background:var(--p-lt)}

/* Validation highlight — red border only, NO background X icon */
.hz-form-body .form-control.is-invalid{
    border-color:#e53e3e!important;
    box-shadow:0 0 0 3px rgba(229,62,62,.15)!important;
    /* FIX: remove the red cross (×) background icon Bootstrap adds */
    background-image:none!important;
    padding-right:10px!important;
    animation:hzShake .35s ease;
}
/* Also suppress Bootstrap's .invalid-feedback text block if present */
.hz-form-body .invalid-feedback{display:none!important}
</style>
</head>
<body style="<?php echo $bgStyle; ?>">
<div class="hz-page"><div class="hz-wrap">

<!-- Brand -->
<div class="hz-brand">
    <img src="<?php echo $data['brand']['favicon']; ?>" alt="">
    <span class="hz-brand-name"><?php echo htmlspecialchars($data['brand']['name']); ?></span>
    <?php if ($hz_st_enabled): ?><span class="hz-brand-timer" id="hz-session-timer"></span><?php endif; ?>
</div>

<!-- Nav -->
<div class="hz-nav">
    <button type="button" class="hz-nav-cancel" onclick="location.href='<?php echo pp_checkout_address(); ?>'">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M5 12l6 6"/><path d="M5 12l6 -6"/></svg>
        <span><?php echo htmlspecialchars($ui['back']); ?></span>
    </button>
    <div class="hz-nav-pills">
        <?php if(count($hz_avail_langs)>1): ?>
        <!-- 2-lang direct switch vs. modal handled by hzOpenLangModal() in inc/lang-modal.php -->
        <button type="button" class="hz-nav-pill" onclick="hzOpenLangModal()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6.371c0 4.418 -2.239 6.629 -5 6.629"/><path d="M4 6.371h7"/><path d="M5 9c0 2.144 2.252 3.908 6 4"/><path d="M12 20l4 -9l4 9"/><path d="M19.1 18h-6.2"/></svg>
            <span class="pill-label"><?php echo htmlspecialchars($ui['language']); ?></span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Gateway logo -->
<div class="hz-gw-logo-center">
    <div class="hz-gw-logo-box">
        <img src="<?php echo $gz_gw_logo; ?>" alt="<?php echo htmlspecialchars($gz_gw_name); ?>">
    </div>
</div>

<!-- Receipt ID | Payable amount card -->
<div class="hz-amount-bar">
    <div class="hz-amount-half">
        <div class="hz-inv-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2"/></svg>
            <?php echo htmlspecialchars($ui['receipt_id']); ?>
        </div>
        <div class="hz-inv-id"><?php echo htmlspecialchars($hz_ref); ?></div>
    </div>
    <div class="hz-amount-half">
        <div class="hz-inv-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M14.8 9a2 2 0 0 0 -1.8 -1h-2a2 2 0 1 0 0 4h2a2 2 0 1 0 0 4h-2a2 2 0 0 1 -1.8 -1"/><path d="M12 7v10"/></svg>
            <?php echo htmlspecialchars($ui['payable'] ?? 'Payable'); ?>
        </div>
        <div class="hz-amount-display">
            <span class="hz-amount-num"><?php echo $hz_payable; ?></span>
            <span class="hz-amount-cur"><?php echo $hz_currency; ?></span>
        </div>
    </div>
</div>

<!--
    FIX: The instruction card (hz-inst-card) is NO LONGER pre-rendered here.
    It is built entirely by JS below, so the title and the payment-instructions
    list are always constructed together inside one container — eliminating the
    "two separate boxes" render issue.
-->

<!-- Verify / Transaction form card -->
<div class="hz-form-card" id="hz-form-section">
    <div class="hz-form-body" id="hz-gateway-form">
        <?php pp_gateway_render($_GET['gateway']??'', $data); ?>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>

<?php require __DIR__ . '/inc/lang-modal.php'; ?>


<!-- QR Image Modal (Zenith V1 — always in DOM) -->
<div class="hz-img-modal" id="bp-modal" onclick="if(event.target===this)hzCloseImg()">
    <div class="hz-img-modal-box">
        <button class="hz-img-modal-close" onclick="hzCloseImg()">&#x00D7;</button>
        <div style="padding:14px"><img src="" id="bp-modal-image" alt="QR"></div>
    </div>
</div>
<?php echo pp_assets('footer'); ?>
<script data-cfasync="false">
var ui = <?php echo $hz_ui_js; ?>;

/* Toast translation map — English key → current language string.
   Empty object when current lang is 'en' (no mapping needed). */
var hzToastMap = <?php echo $hz_toast_map_js; ?>;

/* Word substitution map — native → preferred (e.g. বিকাশ→bKash).
   Empty object when current lang is 'en' (nothing to substitute). */
var hzSubstMap = <?php echo $hz_subst_map_js; ?>;

/**
 * Translate a toast string using hzToastMap.
 * Falls back to the original string when no mapping found.
 */
function hzT(str){
    if(!str) return str;
    return (hzToastMap && hzToastMap[str]) ? hzToastMap[str] : str;
}

/* ===== V1 QR MODAL — Zenith-owned, bypass core modal entirely ===== */
function show_image(src){
    var m = document.getElementById('bp-modal');
    var img = document.getElementById('bp-modal-image');
    if(img) img.src = src;
    if(m){ m.classList.add('on'); document.body.style.overflow='hidden'; }
}
function hzCloseImg(){
    var m = document.getElementById('bp-modal');
    if(m){ m.classList.remove('on'); document.body.style.overflow=''; }
}
/* Lock pp_show_image/pp_close_image — core or addons cannot override */
Object.defineProperty(window,'pp_show_image',{value:show_image,writable:false,configurable:false});
Object.defineProperty(window,'pp_close_image',{value:hzCloseImg,writable:false,configurable:false});
/* =================================================================== */

function copy_value(content){
    if(!content){createToast({title:ui.error,description:ui.nothing_to_copy,svg:'',timeout:4000});return;}
    navigator.clipboard.writeText(content).then(function(){
        createToast({title:ui.copied,description:ui.copied_msg,svg:'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>',timeout:3000});
    }).catch(function(){createToast({title:ui.copy_failed,description:ui.copy_failed_msg,svg:'',timeout:4000});});
}
/* ── failed(): translates title + message via hzToastMap before showing ── */
function failed(title,msg){
    createToast({
        title:hzT(title),
        description:hzT(msg),
        svg:'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>',
        timeout:6000
    });
}
function success(){location.href=<?php echo json_encode(pp_checkout_address()); ?>;}

$(document).ready(function(){

    /* ════════════════════════════════════════════════════════════
       INSTRUCTION CARD — build, process, render
       ════════════════════════════════════════════════════════════ */
    var instrEl = $('#hz-gateway-form .payment-instructions');

    if(instrEl.length){
        /* ── 1. Build the instruction card shell ── */
        var instCardHtml =
            '<div class="hz-inst-card" id="hz-inst-section">' +
                '<div class="hz-inst-title">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>' +
                        '<path d="M8 9h8"/><path d="M8 13h6"/>' +
                        '<path d="M18 4a3 3 0 0 1 3 3v8a3 3 0 0 1 -3 3h-5l-5 3v-3h-2a3 3 0 0 1 -3 -3v-8a3 3 0 0 1 3 -3h12z"/>' +
                    '</svg>' +
                    '<span>' + (ui.payment_instructions || 'Payment Instructions') + '</span>' +
                '</div>' +
                '<div id="hz-instructions-content"></div>' +
            '</div>';

        $('#hz-form-section').before(instCardHtml);
        $('#hz-instructions-content').append(instrEl);

        /* ── PHP-supplied data ── */
        var currentLang  = <?php echo json_encode($hz_current_lang); ?>;
        var keywords     = <?php echo $hz_keywords_js; ?>;        /* current-lang bold list */
        var enKeywords   = <?php echo $hz_en_keywords_js; ?>;     /* English bold list */
        var substMap     = <?php echo $hz_subst_map_js; ?>;       /* native→preferred substitution */

        /* ── Helper: escape a string for use in RegExp ── */
        function reEsc(s){ return s.replace(/[-\/\\^$*+?.()|[\]{}]/g,'\\$&'); }

        instrEl.find('li p').each(function(){
            var inner = $(this).html();

            /* ── STEP 0: Word substitutions (native → preferred) ──────────
               Runs BEFORE bolding. Replaces language-specific words with
               their English (or English-pronunciation) equivalents.
               Examples: বিকাশ→bKash, চাপুন→click, যাচাই→verify, etc.
               Longer phrases are sorted first to avoid partial matches.
            ─────────────────────────────────────────────────────────────── */
            if(currentLang !== 'en'){
                var substPairs = Object.keys(substMap).map(function(native){
                    return { from: native, to: substMap[native] };
                });
                /* Sort longest first — prevents "যাচাই" matching inside "যাচাই করুন" */
                substPairs.sort(function(a,b){ return b.from.length - a.from.length; });

                substPairs.forEach(function(pair){
                    /* Word-boundary aware: don't match inside HTML tags */
                    var re = new RegExp('(?<![>\\w])(' + reEsc(pair.from) + ')(?![\\w<])', 'g');
                    inner = inner.replace(re, pair.to);
                });
            }

            /* ── STEP 1 (REMOVED) ─────────────────────────────────────────
               The old Step 1 replaced English keywords with native-language
               equivalents. This caused conflicts because gateway classes
               already provide correct per-language texts with deliberate
               English terms embedded (e.g. "Send Money", "Send to Binance
               user"). Step 0 substitutions handle the reverse direction
               (native→English) where needed.
            ─────────────────────────────────────────────────────────────── */

            /* ── STEP 2: Bold keywords ────────────────────────────────────
               Bold the current-language keyword list.
               For non-English pages, also bold English brand/action terms
               that survived or were introduced by Step 0.
            ─────────────────────────────────────────────────────────────── */
            var boldList = (currentLang !== 'en')
                ? keywords.concat(enKeywords)   /* both lists: native + English terms */
                : enKeywords;

            /* De-duplicate and sort longest first */
            var seen = {};
            boldList = boldList.filter(function(k){
                if(seen[k]) return false;
                seen[k] = true;
                return true;
            });
            boldList.sort(function(a,b){ return b.length - a.length; });

            boldList.forEach(function(kw){
                var re = new RegExp('(?<![\\w>])(' + reEsc(kw) + ')(?![\\w<])','gi');
                inner = inner.replace(re,'<strong class="hz-kw">$1</strong>');
            });

            $(this).html(inner);
        });

        /* ── STEP 3: Re-style .button-icon elements ───────────────────── */
        instrEl.find('.button-icon').each(function(){
            var oc = $(this).attr('onclick') || '';
            if($(this).find('.hz-btn-label').length) return; /* already processed */

            if(oc.indexOf('pp_show_image')!==-1 || oc.indexOf('show_image')!==-1){
                $(this).html(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/>' +
                        '<rect x="4" y="14" width="6" height="6"/><path d="M14 14h2v2h-2z"/>' +
                        '<path d="M18 14h2v2h-2z"/><path d="M14 18h2v2h-2z"/><path d="M18 18h2v2h-2z"/>' +
                    '</svg>' +
                    '<span class="hz-btn-label">'+ui.view_qr+'</span>'
                );
            } else {
                $(this).html(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path stroke="none" d="M0 0h24v24H0z" fill="none"/>' +
                        '<path d="M7 9.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667l0 -8.666"/>' +
                        '<path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1"/>' +
                    '</svg>' +
                    '<span class="hz-btn-label">'+ui.copy+'</span>'
                );
            }
        });

    }
    /* If no instructions found, the form card already sits at the top */

    /* ── Re-run page-wide substitution after instruction card is built ────
       footer.php registered hzRunPageSubst on DOMContentLoaded which already
       ran on the original DOM. Now that we've moved/built the instruction card
       and bolded keywords, run it once more so any text introduced or moved
       during the card-build phase also gets substituted.
       Both runs are idempotent — no double-substitution occurs.
    ──────────────────────────────────────────────────────────────────── */
    if(typeof window.hzRunPageSubst === 'function'){
        window.hzRunPageSubst();
    }

    /* ── Add lock icon to verify/submit button ── */
    var pb = $('#hz-gateway-form #payButton, #hz-gateway-form button[type="submit"]').not('#pv-form-set button').first();
    if(pb.length && !pb.find('.hz-verify-ic').length){
        pb.prepend('<svg class="hz-verify-ic" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>');
    }

    /* ── Client-side validation for empty Transaction ID ──────────────────
       FIX: We add 'is-invalid' for the red border shake effect ONLY.
       The X background icon is suppressed via CSS (background-image:none).
    ──────────────────────────────────────────────────────────────────── */
    var $txForm = $('#hz-gateway-form form.payment-form-submit');
    if($txForm.length){
        $txForm.attr('novalidate', true);
    }

    $(document).on('click', '#hz-gateway-form .payment-form-btn, #hz-gateway-form button[type="submit"]', function(e){
        var $form    = $(this).closest('form');
        var trxInput = $form.find('input[name="trxid"]');
        if(trxInput.length && trxInput.val().trim() === ''){
            e.preventDefault();
            e.stopImmediatePropagation();
            /* Add is-invalid only for the shake + red border — X icon removed via CSS */
            trxInput.addClass('is-invalid');
            createToast({
                title: ui.validation_trx_title,
                description: ui.validation_trx_msg,
                svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>',
                timeout: 5000
            });
            /* Remove red border once user starts typing */
            trxInput.one('input', function(){ $(this).removeClass('is-invalid'); });
            return false;
        }
    });

    /* Legacy #form handler (kept for fallback, no-op if id absent) */
    $('#form').on('submit', function(e){
        e.preventDefault();
        var fd = $(this).serialize(), pb = document.getElementById('payButton');
        if(pb) pb.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div>';
        $.ajax({
            url:  <?php echo json_encode(pp_site_address()); ?>,
            type: 'POST', dataType: 'json', data: fd,
            success: function(data){
                if(pb) pb.innerHTML = '<i class="fa-solid fa-circle-check"></i> Verify';
                if(data.status === "true") location.href = data.redirect;
                else createToast({title:hzT(data.title),description:hzT(data.message),svg:'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>',timeout:6000});
            },
            error: function(){
                if(pb) pb.innerHTML = '<i class="fa-solid fa-circle-check"></i> Verify';
                createToast({title:ui.error,description:ui.error_msg,svg:'',timeout:6000});
            }
        });
    });

    var pb = $('#payButton');
    if(pb.length && !pb.hasClass('btn-primary')) pb.addClass('btn-primary');
});
<?php if ($hz_st_enabled): ?>
(function(){
    var rem    = <?php echo (int)$hz_st_remaining; ?>;
    var el     = document.getElementById('hz-session-timer');
    var cancel = <?php echo json_encode(pp_checkout_address().'?expired'); ?>;
    if (!el) return;
    function fmt(s){ var m=Math.floor(s/60),sc=s%60; return (m<10?'0':'')+m+':'+(sc<10?'0':'')+sc; }
    el.textContent = fmt(rem);
    var iv = setInterval(function(){
        rem--;
        if (rem <= 0) { clearInterval(iv); window.location.replace(cancel); return; }
        el.textContent = fmt(rem);
    }, 1000);
})();
<?php endif; ?>
</script>

</body>
</html>
