<?php
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

if (isset($_GET['lang']) && $_GET['lang'] !== '') {
    pp_set_lang($_GET['lang']);
    $redir = pp_checkout_address() . '?gateway=' . urlencode($_GET['gateway'] ?? '');
    echo '<script>location.href='.json_encode($redir).';</script>'; exit();
}
if (!isset($_GET['gateway'])) { http_response_code(403); exit('Direct access not allowed'); }

$gateway_info = pp_gateway_info($_GET['gateway'], $data);
if ($gateway_info['status'] == false) { http_response_code(403); exit('Gateway not found'); }
$gz_gw = $gateway_info['gateway'];

// Load gateway parameters
global $db_prefix;

// Cancel — earliest possible, before any output/redirect
if (isset($_GET['cancel']) && !empty($data['transaction']['ref'])) {
    $pdo = connectDatabase();
    $stmt = $pdo->prepare('UPDATE '.$db_prefix.'gateways_data SET unique_amount = NULL, status = :expired WHERE ref = :ref');
    $stmt->execute([':expired' => 'expired', ':ref' => $data['transaction']['ref']]);
    pp_set_transaction_status($data['transaction']['ref'], 'canceled');
    $dest = $data['transaction']['return_url'] ?? '';
    if (empty($dest) || $dest === '--') $dest = pp_checkout_address();
    echo '<script>window.location.replace('.json_encode($dest).');</script>'; exit();
}

$bnqr_opts = [];
$bnqr_params = json_decode(getData($db_prefix.'gateways_parameter', 'WHERE gateway_id = :gateway_id', '* FROM', [':gateway_id' => $gz_gw['gateway_id']]), true);
if (!empty($bnqr_params['response'])) {
    foreach ($bnqr_params['response'] as $field) {
        $val = $field['value'];
        if (!empty($field['multiple']) && !empty($val)) { $val = is_array($val) ? $val : json_decode($val, true); }
        $bnqr_opts[$field['option_name']] = $val;
    }
}

$bnqr_provider  = is_string($bnqr_opts['provider'] ?? '') ? $bnqr_opts['provider'] : 'bkash';
$bnqr_qr_code   = $bnqr_opts['qr_code'] ?? '';
// Force HTTPS to prevent mixed content blocking
if (!empty($bnqr_qr_code) && strpos($bnqr_qr_code, 'http://') === 0) {
    $bnqr_qr_code = 'https://' . substr($bnqr_qr_code, 7);
}
$bnqr_poll_int  = max(1, (int)($bnqr_opts['poll_interval'] ?? 4));
$bnqr_auto_cancel = max(1, (int)($bnqr_opts['auto_cancel_minutes'] ?? 8));
$bnqr_fallback_after = max(1, (int)($bnqr_opts['fallback_after_seconds'] ?? 30));
$bnqr_fallback_method = $bnqr_opts['fallback_verify_method'] ?? 'phone'; // phone | trxid

// Colors
$gz_primary  = $gz_gw['primary_color'] ?? '#128024';
$gz_text_col = $gz_gw['text_color']    ?? '#ffffff';
$hz_primary  = $data['options']['primary_color'] ?? '#128024';
$hz_text_col = $data['options']['text_color']    ?? '#FFFFFF';

// Transaction
$hz_amount   = $data['transaction']['amount'] ?? 0;
$hz_currency = !empty($data['transaction']['local_currency']) && $data['transaction']['local_currency'] !== '--' ? $data['transaction']['local_currency'] : 'BDT';
$hz_ref      = $data['transaction']['ref'] ?? '';
$hz_fee      = $data['transaction']['processing_fee'] ?? 0;
$hz_discount = $data['transaction']['discount_amount'] ?? 0;
$hz_payable  = number_format((float)$hz_amount - (float)$hz_discount + (float)$hz_fee, 2);

// Background
$bgStyle = 'background-color:#eef0f5;';
if (!empty($data['options']['enable_bg_image']) && $data['options']['enable_bg_image']==='enabled' && !empty($data['options']['background_image']))
    $bgStyle = "background-image:url('" . htmlspecialchars($data['options']['background_image'], ENT_QUOTES) . "');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;";

// Session timeout
$hz_st_minutes   = max(0, (int)trim($data['options']['session_timeout_minutes'] ?? '15'));
$hz_st_enabled   = (!empty($data['options']['session_timeout']) && $data['options']['session_timeout'] === 'enabled' && $hz_st_minutes > 0);
$hz_st_remaining = 0;
if ($hz_st_enabled) {
    $hz_created      = strtotime($data['transaction']['created_date'] ?? 'now');
    $hz_elapsed      = time() - $hz_created;
    $hz_st_remaining = ($hz_st_minutes * 60) - $hz_elapsed;
    if ($hz_st_remaining <= 0) {
        // Free Bangla QR unique-amount slot (if any) before canceling — zenith-core global session timeout
        if (!empty($data['transaction']['ref']) && file_exists(__DIR__ . '/../../pp-gateways/bangla-qr/bangla-qr.php')) {
            require_once __DIR__ . '/../../pp-gateways/bangla-qr/bangla-qr.php';
            global $db_prefix;
            bnqr_free_slot(connectDatabase(), $data['transaction']['ref']);
        }
        pp_set_transaction_status($data['transaction']['ref'], 'canceled');
        $rurl = $data['transaction']['return_url'] ?? '';
        $dest = ($rurl && $rurl !== '--') ? $rurl : pp_checkout_address();
        echo '<script>window.location.replace('.json_encode($dest).');</script>'; exit();
    }
}

// Language
$hz_avail_langs_raw = $data['options']['available_languages'] ?? 'en,bn,hi,ur,ar';
$hz_avail_langs     = array_filter(array_map('trim', explode(',', $hz_avail_langs_raw)));
$seoTitle           = $data['options']['seo_title'] ?? '';
$anCode             = $data['options']['analytics_code'] ?? '';

if (!empty($hz_avail_langs)) {
    $hz_sess_lang = $_SESSION['ui_language'] ?? '';
    if (empty($hz_sess_lang) || !in_array($hz_sess_lang, $hz_avail_langs, true)) {
        $hz_default_lang = reset($hz_avail_langs);
        if (($_COOKIE['pp_lang'] ?? '') !== $hz_default_lang) {
            setcookie('pp_lang', $hz_default_lang, time() + 3600, '/');
            pp_set_lang($hz_default_lang);
            $hz_qs = trim(preg_replace('/(^|&)lang=[^&]*/i', '', $_SERVER['QUERY_STRING'] ?? ''), '&');
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . ($hz_qs !== '' ? '?' . $hz_qs : ''));
            exit();
        }
    }
}

require_once __DIR__ . '/inc/lang.php';
$ui    = $hz_ui;
$ui_js = $hz_ui_js;

$bnqr_lang = $data['lang'] ?? [];

// ── Unique Amount ──────────────────────────────────────────────
$bnqr_unique_amount = null;
$bnqr_slot_error = false;
if (file_exists(__DIR__ . '/../../pp-gateways/bangla-qr/bangla-qr.php')) {
    require_once __DIR__ . '/../../pp-gateways/bangla-qr/bangla-qr.php';

    // Use payable amount for unique amount calculation
    $bnqr_base_amount = (float)$hz_amount - (float)$hz_discount + (float)$hz_fee;
    $ua_result = bnqr_get_or_assign_unique_amount(
        $gz_gw['gateway_id'],
        $hz_ref,
        $bnqr_base_amount
    );
    if ($ua_result) {
        $bnqr_unique_amount = $ua_result['amount'];
        
        // Generate dynamic QR with unique amount baked in
        $bnqr_dynamic_qr = bnqr_generate_dynamic_qr(
            $gz_gw['gateway_id'],
            $bnqr_unique_amount,
            $hz_ref, // reference label (receipt ID)
            $data['brand']['name'] ?? '' // store label (brand name)
        );
    } else {
        $bnqr_slot_error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($hz_current_lang); ?>" dir="<?php echo in_array($hz_current_lang,['ar','ur']) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo htmlspecialchars($gz_gw['display'] ?? 'Bangla QR'); ?> — <?php echo htmlspecialchars($data['brand']['name']); ?></title>
<link rel="shortcut icon" href="<?php echo $data['brand']['favicon']; ?>">
<?php if($seoTitle&&$seoTitle!=='--') echo '<meta name="title" content="'.htmlspecialchars($seoTitle).'">'; ?>
<?php if($anCode&&$anCode!=='--') echo $anCode; ?>
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'" crossorigin="anonymous" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="></noscript>
<?php echo pp_assets('head'); ?>
<style>
:root{
    --p:<?php echo $hz_primary; ?>;
    --p-lt:<?php echo pp_hexToRgba($hz_primary, 0.09); ?>;
    --p-tx:<?php echo $hz_text_col; ?>;
    --gp:<?php echo $gz_primary; ?>;
    --gp-lt:<?php echo pp_hexToRgba($gz_primary, 0.10); ?>;
    --gp-dk:<?php echo pp_hexToRgba($gz_primary, 0.82); ?>;
    --gp-tx:<?php echo $gz_text_col; ?>;
    --text:#111827;--sub:#6b7280;--muted:#9ca3af;--line:#e5e7eb;
    --bg:#f5f6f9;--card:#ffffff;--r:6px;--r2:10px;--r3:13px;
    --sh:0 1px 3px rgba(0,0,0,.07),0 4px 14px rgba(0,0,0,.05);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
#toast-container{top:16px !important;right:16px !important}
.custom-toast{font-family:'DM Sans',sans-serif !important;border:1px solid var(--line) !important;border-radius:var(--r2) !important;background:var(--card) !important;box-shadow:0 4px 20px rgba(0,0,0,.12) !important;min-width:260px;max-width:320px}
.custom-toast [style*="padding: calc(.25rem * 4)"]{padding:12px 14px !important;gap:0 !important}
.custom-toast [style*="margin-left: 30px"]{margin-left:0 !important;font-size:12.5px;color:var(--sub);margin-top:4px;line-height:1.5}
.custom-toast [style*="font-weight: 500"]{font-size:13.5px;font-weight:600 !important;color:var(--text) !important}
.custom-toast .btn-close{width:20px;height:20px;padding:0;opacity:.4;flex-shrink:0}
.custom-toast .btn-close:hover{opacity:.8}
.custom-toast .toast-svg svg{width:16px;height:16px}
@keyframes hzFadeIn{from{opacity:0}to{opacity:1}}
body{animation:hzFadeIn .15s ease}
body{font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}button{font-family:inherit;cursor:pointer;border:none;outline:none;background:none}
.hz-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:26px 15px 52px}
.hz-wrap{width:100%;max-width:520px;display:flex;flex-direction:column;flex:1 0 auto}
.hz-brand{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:0 2px;min-width:0;justify-content:space-between}
.hz-brand img{width:34px;height:34px;border-radius:7px;object-fit:cover;border:1px solid var(--line);flex-shrink:0}
.hz-brand-name{font-size:15px;font-weight:600;color:var(--text);letter-spacing:-.2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.hz-brand-timer{font-size:13px;font-weight:600;color:var(--sub);font-variant-numeric:tabular-nums;letter-spacing:.3px;flex-shrink:0}
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
.hz-gw-logo-center{display:flex;align-items:center;justify-content:center;padding:10px 0 0;margin-bottom:8px}
.hz-gw-logo-box{display:inline-flex;align-items:center;justify-content:center;border-radius:16px;overflow:hidden;background:var(--card);border:1px solid var(--line);padding:10px 18px;box-shadow:var(--sh)}
.hz-gw-logo-center img{max-height:56px;max-width:160px;object-fit:contain;display:inline-block}
.hz-amount-bar{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--sh);margin-bottom:12px;display:flex;align-items:stretch;width:100%}
.hz-amount-half{flex:1;padding:10px 14px;position:relative;display:flex;flex-direction:column;justify-content:center}
.hz-amount-half:first-child{align-items:flex-start;gap:4px}
.hz-amount-half:last-child{text-align:right}
.hz-amount-half+.hz-amount-half::before{content:'';position:absolute;left:0;top:10px;bottom:10px;width:1px;background-color:var(--line)}
.hz-inv-label{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:5px;margin-bottom:0}
.hz-inv-id{font-size:12px;color:var(--sub);font-family:'Courier New',monospace;letter-spacing:.3px}
.hz-amount-display{display:inline-block;max-width:100%;text-align:right;word-break:break-word}
.hz-amount-num{font-size:clamp(16px,5vw,23px);font-weight:700;color:var(--text);letter-spacing:-.6px;line-height:1.2}
.hz-amount-cur{font-size:clamp(12px,3vw,14px);font-weight:500;color:var(--sub);margin-left:3px;white-space:nowrap}
.hz-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--sh);overflow:hidden;margin-bottom:12px}
.hz-card-title{padding:13px 18px 11px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);display:flex;align-items:center;gap:7px;border-bottom:1px solid var(--line)}
.hz-card-title svg{width:13px;height:13px;color:var(--text);flex-shrink:0}
.hz-card-body{padding:18px}
.bz-steps{text-align:center;font-size:13.5px;font-weight:600;color:var(--muted);padding:2px 0 8px;letter-spacing:.2px}
.bz-steps span{color:var(--muted);font-weight:700}
.bz-qr-wrap{display:flex;align-items:center;justify-content:center;margin-bottom:16px}
.bz-qr-img{width:220px;height:220px;border-radius:var(--r2);border:1px solid var(--line);overflow:hidden;background:#fff;display:flex;align-items:center;justify-content:center;padding:12px;box-sizing:border-box}
.bz-qr-img img{width:100%;height:100%;object-fit:contain}
.bz-waiting{text-align:center;padding:14px 0 0;display:none}
.bz-waiting-text{font-size:14px;font-weight:600;color:var(--sub);display:flex;align-items:center;justify-content:center;gap:8px}
.bz-timer{font-size:22px;font-weight:700;color:var(--gp);font-variant-numeric:tabular-nums;letter-spacing:.5px;margin-top:6px}
.bz-spinner{width:18px;height:18px;border:2px solid var(--line);border-top-color:var(--gp);border-radius:50%;animation:bzSpin .8s linear infinite}
@keyframes bzSpin{to{transform:rotate(360deg)}}
.bz-fallback{text-align:center;padding:12px 0 0;display:none}
.bz-fallback-text{font-size:12.5px;color:var(--muted)}
.bz-fallback-link{font-size:12.5px;color:var(--muted);cursor:pointer;transition:color 140ms;text-decoration:underline;text-underline-offset:2px}
.bz-fallback-link:hover{color:var(--gp)}
.bz-waiting-row{display:flex;align-items:center;justify-content:center;gap:10px}
.bz-waiting-label{font-size:14px;font-weight:600;color:var(--sub)}
.bz-timer{font-size:18px;font-weight:700;color:var(--gp);font-variant-numeric:tabular-nums;letter-spacing:.5px}
.bz-mid-title{text-align:center;font-size:15px;font-weight:700;color:var(--text);margin:18px 0 10px}
.bz-gw-inst{list-style:none;padding:0;margin:0}
.bz-gw-inst li{display:flex;align-items:flex-start;gap:12px;padding:10px 0;position:relative}
.bz-gw-inst li::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:var(--line)}
.bz-gw-inst li:last-child::after{display:none}
.bz-gw-inst li .hz-dot{width:9px;height:9px;border-radius:50%;min-width:9px;background:var(--gp);margin-top:6px;flex-shrink:0}
.bz-gw-inst li p{flex:1;font-size:13.5px;color:var(--sub);line-height:1.55;margin:0;word-break:break-word}
.bz-note{text-align:center;font-size:11px;color:var(--muted);line-height:1.5;margin-top:10px}
.hz-form-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);box-shadow:var(--sh);margin-bottom:12px;overflow:hidden}
.hz-form-body{padding:14px;display:flex;flex-direction:column}
.hz-form-body .form-group{margin-top:0!important}
.hz-form-body .form-group+.form-group{margin-top:12px!important}
.hz-form-body .form-control{border-radius:var(--r)!important;border-color:var(--line)!important;font-family:'DM Sans',sans-serif!important;font-size:14px!important;color:var(--text)!important;padding:6px 10px!important;transition:border-color 150ms!important}
.hz-form-body .form-control:focus{border-color:var(--gp)!important;box-shadow:0 0 0 3px var(--gp-lt)!important}
.hz-form-body .form-label{font-size:13.5px!important;font-weight:700!important;color:var(--text)!important;margin-bottom:3px!important}
.hz-form-body .btn-primary{background:var(--gp)!important;border-color:var(--gp)!important;color:var(--gp-tx)!important;border-radius:var(--r)!important;font-family:'DM Sans',sans-serif!important;font-weight:700!important;font-size:14.5px!important;padding:10px 20px!important;width:100%!important;display:flex!important;align-items:center!important;justify-content:center!important;gap:9px!important;transition:background 150ms!important;letter-spacing:.3px!important;margin-top:20px!important;text-transform:uppercase!important}
.hz-form-body .btn-primary:hover{background:var(--gp-dk)!important;border-color:var(--gp-dk)!important}
.hz-footer{text-align:center;margin-top:14px;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;width:100%}
.hz-footer-links{margin-top:32px;padding-bottom:8px;font-size:11.5px;color:var(--muted);text-align:center}
.hz-footer-links a{color:var(--muted);text-decoration:none;transition:color 130ms}
.hz-footer-links a:hover{color:var(--p)}
::selection{background:var(--p-lt);color:var(--text)}
*:focus-visible{outline:2px solid var(--p);outline-offset:2px}
a:hover{color:var(--p)}
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
        <span><?php echo htmlspecialchars($ui['back'] ?? 'Back'); ?></span>
    </button>
    <div class="hz-nav-pills">
        <?php if(count($hz_avail_langs)>1): ?>
        <button type="button" class="hz-nav-pill" onclick="hzOpenLangModal()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6.371c0 4.418 -2.239 6.629 -5 6.629"/><path d="M4 6.371h7"/><path d="M5 9c0 2.144 2.252 3.908 6 4"/><path d="M12 20l4 -9l4 9"/><path d="M19.1 18h-6.2"/></svg>
            <span class="pill-label"><?php echo htmlspecialchars($ui['language'] ?? 'Language'); ?></span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Gateway logo -->
<div class="hz-gw-logo-center">
    <div class="hz-gw-logo-box">
        <img src="<?php echo htmlspecialchars($gz_gw['logo'] ?? ''); ?>" alt="<?php echo htmlspecialchars($gz_gw['display'] ?? ''); ?>">
    </div>
</div>

<!-- Amount bar -->
<div class="hz-amount-bar">
    <div class="hz-amount-half">
        <div class="hz-inv-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2"/></svg>
            <?php echo htmlspecialchars($ui['receipt_id'] ?? 'Receipt ID'); ?>
        </div>
        <div class="hz-inv-id"><?php echo htmlspecialchars($hz_ref); ?></div>
    </div>
    <div class="hz-amount-half">
        <div class="hz-amount-display">
            <?php if ($bnqr_unique_amount !== null): ?>
                <span class="hz-amount-num"><?php echo number_format($bnqr_unique_amount, 2); ?></span>
            <?php else: ?>
                <span class="hz-amount-num"><?php echo $hz_payable; ?></span>
            <?php endif; ?>
            <span class="hz-amount-cur"><?php echo $hz_currency; ?></span>
        </div>
    </div>
</div>

<!-- QR Card -->
<div class="hz-card" id="bz-qr-card">
    <div class="hz-card-body">
        <?php if ($bnqr_slot_error): ?>
            <!-- Slot exhausted error -->
            <div style="text-align:center;padding:30px 10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:10px"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>
                <div style="font-size:15px;font-weight:600;color:var(--text);margin-bottom:6px"><?php echo htmlspecialchars($bnqr_lang['max_concurrent_reached'] ?? 'Maximum pending transactions reached.'); ?></div>
                <div style="font-size:13px;color:var(--sub)"><?php echo htmlspecialchars($bnqr_lang['max_concurrent_reached'] ?? 'Please try again shortly.'); ?></div>
            </div>
        <?php else: ?>
            <!-- Pay with Bangla QR (top heading, no divider) -->
            <div class="bz-mid-title" style="margin-top:0"><?php echo htmlspecialchars($bnqr_lang['title'] ?? 'Pay with Bangla QR'); ?></div>

            <!-- Steps -->
            <div class="bz-steps"><?php echo htmlspecialchars($bnqr_lang['steps'] ?? 'Open App › Scan QR › Confirm'); ?></div>

            <!-- Unique amount instruction -->
            <?php if ($bnqr_unique_amount !== null): ?>
            <div style="text-align:center;margin-bottom:12px;padding:8px 12px;background:var(--gp-lt);border-radius:var(--r);font-size:13px;font-weight:600;color:var(--gp)">
                <?php
                // Dynamic QR (with unique amount baked in) — check if available
                $bnqr_show_dynamic = (!empty($bnqr_dynamic_qr) && !empty($bnqr_dynamic_qr['image']));
                ?>
                <?php if ($bnqr_show_dynamic): ?>
                    <?php echo htmlspecialchars($bnqr_lang['pay_exact_amount'] ?? 'Pay exactly'); ?>
                    <span style="font-size:16px;font-weight:700;margin-left:4px"><?php echo number_format($bnqr_unique_amount, 2); ?> <?php echo $hz_currency; ?></span>
                <?php else: ?>
                    <?php echo htmlspecialchars($bnqr_lang['pay_exact_amount'] ?? 'Pay exactly'); ?>
                    <span style="font-size:16px;font-weight:700;margin-left:4px"><?php echo number_format($bnqr_unique_amount, 2); ?> <?php echo $hz_currency; ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- QR Code -->
            <div class="bz-qr-wrap" id="bz-qr-wrap">
                <div class="bz-qr-img">
                    <?php
                    // Dynamic QR (with unique amount baked in) — preferred
                    if (empty($bnqr_show_dynamic)) {
                        $bnqr_show_dynamic = (!empty($bnqr_dynamic_qr) && !empty($bnqr_dynamic_qr['image']));
                    }
                    ?>
                    
                    <?php if ($bnqr_show_dynamic): ?>
                        <img src="<?php echo htmlspecialchars($bnqr_dynamic_qr['image']); ?>" alt="Scan to Pay">
                    <?php elseif (!empty($bnqr_qr_code)): ?>
                        <!-- Fallback: static QR from admin upload -->
                        <img src="<?php echo htmlspecialchars($bnqr_qr_code); ?>" alt="QR Code">
                    <?php else: ?>
                        <div style="text-align:center;color:var(--muted);font-size:13px;padding:20px">
                            <i class="fa-solid fa-qrcode" style="font-size:48px;margin-bottom:8px;display:block"></i>
                            QR not configured
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Waiting (label + countdown on one line) -->
            <div class="bz-waiting" id="bz-waiting">
                <div class="bz-waiting-row">
                    <div class="bz-spinner"></div>
                    <span class="bz-waiting-label"><?php echo htmlspecialchars($bnqr_lang['waiting_payment'] ?? 'Waiting for payment...'); ?></span>
                    <span class="bz-timer" id="bz-timer">00:00</span>
                </div>
            </div>

            <!-- Fallback link -->
            <div class="bz-fallback" id="bz-fallback">
                <span class="bz-fallback-text"><?php echo htmlspecialchars($bnqr_lang['fallback_text'] ?? 'Payment not detected?'); ?> </span>
                <span class="bz-fallback-link" onclick="BNQR.showFallback()"><?php
                    if ($bnqr_fallback_method === 'phone') {
                        echo htmlspecialchars($bnqr_lang['fallback_phone'] ?? 'Submit phone number or bank account');
                    } elseif ($bnqr_fallback_method === 'trxid') {
                        echo htmlspecialchars($bnqr_lang['fallback_trxid'] ?? 'Enter Transaction ID');
                    } else {
                        echo htmlspecialchars($bnqr_lang['fallback_both'] ?? 'Verify manually');
                    }
                ?>                </span>
            </div>

            <!-- Gateway instructions (3 steps) -->
            <?php $bnqr_gw_inst = class_exists('BanglaQrGateway') ? (new BanglaQrGateway())->instructions($data) : []; ?>
            <?php if (!empty($bnqr_gw_inst)): ?>
            <ul class="payment-instructions bz-gw-inst">
                <?php foreach ($bnqr_gw_inst as $bnqr_s): ?>
                <li>
                    <span class="hz-dot"></span>
                    <p><?php echo htmlspecialchars($bnqr_s['text'] ?? ''); ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <!-- Keep-window note (very small) -->
            <div class="bz-note"><?php echo htmlspecialchars($bnqr_lang['keep_window'] ?? 'Please do not close this window. We will automatically verify your payment once it’s complete.'); ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Fallback Form Card (separate) -->
<div class="hz-form-card" id="bz-fallback-form" style="display:none">
    <div class="hz-form-body">
        <?php if ($bnqr_fallback_method === 'phone'): ?>
        <div class="form-group" id="bz-phone-group">
            <label class="form-label"><?php echo htmlspecialchars($bnqr_lang['mobile_number'] ?? 'Mobile Number'); ?></label>
            <input type="tel" class="form-control" id="bz-phone" placeholder="Enter mobile number" maxlength="15">
        </div>
        <?php endif; ?>
        <?php if ($bnqr_fallback_method === 'trxid'): ?>
        <div class="form-group" id="bz-trxid-group">
            <label class="form-label"><?php echo htmlspecialchars($bnqr_lang['trxid_label'] ?? 'Transaction ID'); ?></label>
            <input type="text" class="form-control" id="bz-trxid" placeholder="Enter transaction ID" maxlength="50">
        </div>
        <?php endif; ?>
        <button type="button" class="btn-primary" id="bz-verify-btn" onclick="BNQR.verify()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span><?php echo htmlspecialchars($bnqr_lang['verify'] ?? 'Verify'); ?></span>
        </button>
    </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
<?php require __DIR__ . '/inc/lang-modal.php'; ?>

<script data-cfasync="false">
var ui = <?php echo $hz_ui_js; ?>;
var hzToastMap = <?php echo $hz_toast_map_js ?? '{}'; ?>;
function hzT(s){ return (hzToastMap && hzToastMap[s]) ? hzToastMap[s] : s; }

var BNQR = {
    url:       <?php echo json_encode(pp_checkout_address()); ?>,
    ref:       <?php echo json_encode($hz_ref); ?>,
    gwId:      <?php echo json_encode($gz_gw['gateway_id'] ?? ''); ?>,
    provider:  <?php echo json_encode($bnqr_provider); ?>,
    amount:    <?php echo json_encode($bnqr_unique_amount !== null ? number_format($bnqr_unique_amount, 2, '.', '') : number_format((float)$hz_payable, 2, '.', '')); ?>,
    cur:       <?php echo json_encode($hz_currency); ?>,
    pollInt:   <?php echo $bnqr_poll_int * 1000; ?>,
    autoCancel:<?php echo $bnqr_auto_cancel * 60; ?>,
    fallbackAfter: <?php echo $bnqr_fallback_after; ?>,
    fallbackMethod: <?php echo json_encode($bnqr_fallback_method); ?>,
    slotError: <?php echo $bnqr_slot_error ? 'true' : 'false'; ?>,
    debug:     localStorage.getItem('bnqr_debug') === '1', // Toggle: localStorage.setItem('bnqr_debug','1')
    pollTimer: null,
    remainSec: 0,
    cdTimer:   null,
    _sessionIv: null,

    log: function(msg) {
        if (this.debug) {
            console.log('[BNQR]', msg);
            createToast({title: 'Debug', description: msg, svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>', timeout: 4000});
        }
    },

    logError: function(msg) {
        console.error('[BNQR]', msg);
        if (this.debug) {
            createToast({title: 'Debug Error', description: msg, svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>', timeout: 6000});
        }
    },

    init: function() {
        if (this.slotError) return;
        var key = 'bnqr_' + this.ref;
        var stored = sessionStorage.getItem(key);
        var now = Math.floor(Date.now() / 1000);
        if (stored) {
            var elapsed = now - parseInt(stored, 10);
            this.remainSec = Math.max(0, this.autoCancel - elapsed);
        } else {
            sessionStorage.setItem(key, now);
            this.remainSec = this.autoCancel;
        }
        var formOpen = sessionStorage.getItem('bnqr_form_' + this.ref);
        document.getElementById('bz-qr-wrap').style.display = 'flex';
        document.getElementById('bz-waiting').style.display = 'block';
        if (formOpen === '1') {
            document.getElementById('bz-fallback').style.display = 'none';
            document.getElementById('bz-fallback-form').style.display = 'block';
        } else {
            document.getElementById('bz-fallback').style.display = 'none';
            document.getElementById('bz-fallback-form').style.display = 'none';
        }
        if (this.remainSec <= 0) { this.stop(); return; }
        this.tick();
        this.poll();
    },

    tick: function() {
        var self = BNQR;
        self.remainSec--;
        if (self.remainSec <= 0) { self.stop(); return; }
        var m = Math.floor(self.remainSec / 60), s = self.remainSec % 60;
        document.getElementById('bz-timer').textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        if (self.remainSec <= (self.autoCancel - self.fallbackAfter)) {
            var formOpen = sessionStorage.getItem('bnqr_form_' + self.ref);
            if (formOpen !== '1') {
                document.getElementById('bz-fallback').style.display = 'block';
            }
        }
        self.cdTimer = setTimeout(function(){ self.tick(); }, 1000);
    },

    poll: function() {
        var self = BNQR;
        if (self.remainSec <= 0) return;
        
        self.log('Polling... (remaining: ' + self.remainSec + 's)');
        
        var fd = new FormData();
        fd.append('action', 'bnqr-poll');
        fd.append('gateway_id', self.gwId);
        fd.append('transaction_id', self.ref);
        fd.append('provider', self.provider);

        fetch(self.url, { method: 'POST', body: fd })
        .then(function(r){ 
            self.log('Poll response: HTTP ' + r.status);
            return r.json(); 
        })
        .then(function(d){
            self.log('Poll result: ' + JSON.stringify(d));
            if (d.status === 'true') { self.showSuccess(d.title, d.message, d.trx_id, d.redirect); }
            else if (d.message === 'already_verified') { self.showSuccess(hzT('Already Verified'), hzT('Transaction already completed.')); }
            else { self.pollTimer = setTimeout(function(){ self.poll(); }, self.pollInt); }
        })
        .catch(function(err){ 
            self.logError('Poll error: ' + err.message);
            self.pollTimer = setTimeout(function(){ self.poll(); }, self.pollInt); 
        });
    },

    stop: function() {
        if (this.pollTimer) clearTimeout(this.pollTimer);
        if (this.cdTimer) clearTimeout(this.cdTimer);
        if (BNQR._sessionIv) clearInterval(BNQR._sessionIv);
        this.pollTimer = null;
        this.cdTimer = null;
        sessionStorage.removeItem('bnqr_' + this.ref);
        sessionStorage.removeItem('bnqr_form_' + this.ref);
        var fd = new FormData();
        fd.append('action', 'bnqr-cancel');
        fd.append('gateway_id', this.gwId);
        fd.append('transaction_id', this.ref);
        fetch(this.url, { method: 'POST', body: fd });
        var fd2 = new FormData();
        fd2.append('action', 'bnqr-free-slot');
        fd2.append('transaction_id', this.ref);
        fetch(this.url, { method: 'POST', body: fd2 });
        window.location.href = '<?php echo pp_checkout_address(); ?>?cancel';
    },

    showFallback: function() {
        sessionStorage.setItem('bnqr_form_' + this.ref, '1');
        document.getElementById('bz-fallback').style.display = 'none';
        document.getElementById('bz-fallback-form').style.display = 'block';
    },

    verify: function() {
        var phone = '';
        var trxid = '';
        var verifyMode = 'auto';

        if (this.fallbackMethod === 'phone') {
            phone = (document.getElementById('bz-phone') || {}).value || '';
            phone = phone.replace(/[\s\-\+]/g, '').trim();
            if (phone.indexOf('880') === 0) phone = '0' + phone.substring(3);
        }
        if (this.fallbackMethod === 'trxid') {
            trxid = (document.getElementById('bz-trxid') || {}).value || '';
            trxid = trxid.trim();
        }

        if (this.fallbackMethod === 'phone' && !phone) {
            this.showError(hzT('Error'), hzT('Please enter your mobile number or bank account.')); return;
        }
        if (phone && !/^\d+$/.test(phone)) {
            this.showError(hzT('Invalid Input'), hzT('Only numbers are allowed.')); return;
        }
        if (this.fallbackMethod === 'trxid' && !trxid) {
            this.showError(hzT('Error'), hzT('Please enter your Transaction ID.')); return;
        }

        if (phone) verifyMode = 'phone';
        else if (trxid) verifyMode = 'trxid';

        this.log('Verifying: mode=' + verifyMode + ', phone=' + phone + ', trxid=' + trxid);

        var btn = document.getElementById('bz-verify-btn');
        btn.disabled = true;
        btn.innerHTML = '<div class="bz-spinner" style="width:16px;height:16px;border-width:1.5px"></div><span>' + hzT('Verifying...') + '</span>';

        var self = this;
        var fd = new FormData();
        fd.append('action', 'bnqr-verify');
        fd.append('gateway_id', self.gwId);
        fd.append('transaction_id', self.ref);
        fd.append('provider', self.provider);
        fd.append('mobile_number', phone);
        fd.append('trxid', trxid);
        fd.append('verify_mode', verifyMode);
        fd.append('debug', self.debug ? '1' : '0');

        fetch(self.url, { method: 'POST', body: fd })
        .then(function(r){ 
            self.log('Verify response: HTTP ' + r.status);
            return r.text(); 
        })
        .then(function(text){
            self.log('Verify raw: ' + text.substring(0, 500));
            try {
                var d = JSON.parse(text);
                if (d.debug) {
                    self.log('Server debug: ' + d.debug.join(' | '));
                }
                if (d.status === 'true') { self.showSuccess(d.title, d.message, d.trx_id, d.redirect); }
                else { self.showError(d.title || 'Error', d.message || 'No match'); }
            } catch(e) {
                self.logError('Parse error: ' + e.message);
                self.showError('Parse Error', hzT('Invalid response from server.') + (self.debug ? '<br><pre style="text-align:left;font-size:11px;margin-top:8px;max-height:150px;overflow:auto">' + text.substring(0, 1000) + '</pre>' : ''));
            }
        })
        .catch(function(err){ 
            self.logError('Network error: ' + err.message);
            self.showError('Network Error', hzT('Could not connect to server.'));
        });
    },

    showError: function(title, msg) {
        createToast({title: title, description: msg, svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 9v4"/><path d="M12 16v.01"/></svg>', timeout: 6000});
        var btn = document.getElementById('bz-verify-btn');
        btn.disabled = false;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg><span>' + hzT('Verify') + '</span>';
    },

    showSuccess: function(title, msg, trxid, redirect) {
        if (this.pollTimer) clearTimeout(this.pollTimer);
        if (this.cdTimer) clearTimeout(this.cdTimer);
        if (BNQR._sessionIv) clearInterval(BNQR._sessionIv);
        sessionStorage.removeItem('bnqr_' + this.ref);
        sessionStorage.removeItem('bnqr_form_' + this.ref);
        var desc = msg + (trxid ? '<br><strong>Transaction ID:</strong> ' + trxid : '');
        createToast({title: title, description: desc, svg: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 12l2 2l4 -4"/></svg>', timeout: 3000});
        document.getElementById('bz-fallback-form').style.display = 'none';
        document.getElementById('bz-fallback').style.display = 'none';
        if (redirect) { setTimeout(function(){ window.location.href = redirect; }, 2500); }
    }
};

BNQR.init();

<?php if ($hz_st_enabled): ?>
(function(){
    var totalSec = <?php echo (int)$hz_st_minutes * 60; ?>;
    var serverRem = <?php echo (int)$hz_st_remaining; ?>;
    var key = 'bnqr_timer_<?php echo htmlspecialchars($data["transaction"]["ref"] ?? "", ENT_QUOTES); ?>';
    var now = Math.floor(Date.now() / 1000);

    // If we have a saved start time, recalculate remaining from it
    var saved = localStorage.getItem(key);
    if (saved) {
        var savedObj = JSON.parse(saved);
        var elapsed = now - savedObj.start;
        var remaining = savedObj.total - elapsed;
        if (remaining > 0 && remaining < savedObj.total) {
            serverRem = remaining;
        } else if (remaining <= 0) {
            // Timer expired while away
            BNQR.stop();
            return;
        }
    } else {
        // First visit — save start time
        localStorage.setItem(key, JSON.stringify({ start: now, total: totalSec }));
    }

    var rem = serverRem;
    var el  = document.getElementById('hz-session-timer');
    if (!el) return;
    function fmt(s){ var m=Math.floor(s/60),sc=s%60; return (m<10?'0':'')+m+':'+(sc<10?'0':'')+sc; }
    el.textContent = fmt(rem);
    var iv = setInterval(function(){
        rem--;
        if (rem <= 0) { clearInterval(iv); localStorage.removeItem(key); BNQR.stop(); return; }
        el.textContent = fmt(rem);
    }, 1000);
    BNQR._sessionIv = iv;
})();
<?php endif; ?>
</script>
<?php echo pp_assets('footer'); ?>
</body>
</html>
