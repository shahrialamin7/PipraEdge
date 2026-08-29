<?php
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

// Allow receipt PDF download from within checkout (pending state)
if (isset($_GET['receipt'])) {
    $logoUrl = $data['brand']['logo'] ?? '';
    if (!empty($logoUrl)) {
        $parsedPath = parse_url($logoUrl, PHP_URL_PATH) ?? '';
        if (strpos($parsedPath, 'pp-media/storage/') !== false) {
            $filename  = basename($parsedPath);
            $localPath = __DIR__ . '/../../pp-media/storage/' . $filename;
            $data['brand']['logo'] = file_exists($localPath) ? $localPath : '';
        }
    }
    pp_downloadReceiptPDF($data);
    exit;
}

if (isset($_GET['cancel'])) {
    // Free Bangla QR unique-amount slot (if any) before canceling — manual cancel from gateway list
    if (!empty($data['transaction']['ref']) && file_exists(__DIR__ . '/../../pp-gateways/bangla-qr/bangla-qr.php')) {
        require_once __DIR__ . '/../../pp-gateways/bangla-qr/bangla-qr.php';
        global $db_prefix;
        bnqr_free_slot(connectDatabase(), $data['transaction']['ref']);
    }
    pp_set_transaction_status($data['transaction']['ref'], 'canceled');
    $rurl = $data['transaction']['return_url']??'';
    $dest = ($rurl && $rurl!=='--') ? $rurl : pp_checkout_address();
    echo '<script>window.location.replace('.json_encode($dest).');</script>'; exit();
}
if (isset($_GET['lang']) && $_GET['lang'] !== '') {
    pp_set_lang($_GET['lang']);
    echo '<script>window.location.replace('.json_encode(pp_checkout_address()).');</script>'; exit();
}

$pp_gw_mfs    = pp_gateways('mfs',    $data);
$pp_gw_bank   = pp_gateways('bank',   $data);
$pp_gw_global = pp_gateways('global', $data);

$hz_tabs = [];
if ($pp_gw_mfs['status']    && !empty($pp_gw_mfs['gateway']))    $hz_tabs[] = 'mfs';
if ($pp_gw_bank['status']   && !empty($pp_gw_bank['gateway']))   $hz_tabs[] = 'bank';
if ($pp_gw_global['status'] && !empty($pp_gw_global['gateway'])) $hz_tabs[] = 'global';

function hz_group($gateways) {
    // No stacking — each gateway gets its own card
    $out = [];
    foreach ($gateways as $gw) {
        $bk = $gw['gateway_id'];
        $name = $gw['display'] ?: ($gw['name'] ?? '');
        $out[] = ['bk'=>$bk,'name'=>$name,'logo'=>$gw['logo'],'items'=>[$gw]];
    }
    return $out;
}
function hz_sort($groups, $order_str) {
    $lines = array_filter(array_map('trim', explode("\n", $order_str ?? '')));
    if (empty($lines)) return $groups;
    $bkeys = [];
    foreach ($lines as $ln) { $bk = strtolower(trim($ln)); if($bk && !in_array($bk,$bkeys)) $bkeys[]=$bk; }
    $sorted=[]; $rest=[];
    foreach ($groups as $g) {
        // Match by full gateway_id or its prefix
        $bk = $g['bk'];
        $bkPrefix = strtolower(explode('-',$bk)[0]);
        $pos = array_search($bk,$bkeys);
        if ($pos === false) $pos = array_search($bkPrefix,$bkeys);
        if ($pos !== false) $sorted[$pos]=$g; else $rest[]=$g;
    }
    ksort($sorted);
    return array_merge(array_values($sorted),$rest);
}

$hz_primary   = $data['options']['primary_color']       ?? '#1a56db';
$hz_text_col  = $data['options']['text_color']          ?? '#FFFFFF';
$hz_show_name = ($data['options']['show_gateway_names'] ?? 'enabled') === 'enabled';
$hz_direct    = ($data['options']['direct_redirect']    ?? 'disabled') === 'enabled';
$hz_columns   = max(2, min(4, (int)($data['options']['gateway_columns'] ?? 3)));
$hz_nav_mode  = $data['options']['show_nav_buttons']    ?? 'all';
$hz_sp_cols   = max(1, min(2, (int)($data['options']['support_columns'] ?? 1)));
$hz_avail_langs_raw = $data['options']['available_languages'] ?? 'en,bn,hi,ur,ar';
$hz_avail_langs = array_filter(array_map('trim', explode(',', $hz_avail_langs_raw)));

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

/* ── Shared lang dictionary ── */
require_once __DIR__ . '/inc/lang.php';

$hz_show_faq     = in_array($hz_nav_mode, ['all','faq']);
$hz_show_support = in_array($hz_nav_mode, ['all','support']);

$hz_g_mfs    = hz_sort(hz_group($pp_gw_mfs['gateway']    ?? []), $data['options']['gateway_order_mfs']    ?? '');
$hz_g_bank   = hz_sort(hz_group($pp_gw_bank['gateway']   ?? []), $data['options']['gateway_order_bank']   ?? '');
$hz_g_global = hz_sort(hz_group($pp_gw_global['gateway'] ?? []), $data['options']['gateway_order_global'] ?? '');

$hz_amount   = $data['transaction']['amount']          ?? 0;
$hz_fee      = $data['transaction']['processing_fee']  ?? 0;
$hz_discount = $data['transaction']['discount_amount'] ?? 0;
$hz_payable  = number_format((float)$hz_amount - (float)$hz_discount + (float)$hz_fee, 2);
$hz_currency = $data['transaction']['currency']        ?? '';
$hz_ref      = $data['transaction']['ref']             ?? '';
$hz_cname    = $data['transaction']['customer']['name']  ?? '';
$hz_cemail   = $data['transaction']['customer']['email'] ?? '';

$sp = $data['brand']['support'] ?? [];
$hz_sp_items = [];
if(!empty($sp['whatsapp'])&&$sp['whatsapp']!=='--') $hz_sp_items[]=['type'=>'whatsapp','label'=>'WhatsApp','href'=>'https://wa.me/'.preg_replace('/\D/','',$sp['whatsapp']),'color'=>'#25D366','bg'=>'#dcfce7'];
if(!empty($sp['telegram'])&&$sp['telegram']!=='--') $hz_sp_items[]=['type'=>'telegram','label'=>'Telegram','href'=>$sp['telegram'],'color'=>'#229ED9','bg'=>'#e0f2fe'];
if(!empty($sp['messenger'])&&$sp['messenger']!=='--') $hz_sp_items[]=['type'=>'messenger','label'=>'Messenger','href'=>$sp['messenger'],'color'=>'#0078FF','bg'=>'#dbeafe'];
if(!empty($sp['fb_page'])&&$sp['fb_page']!=='--') $hz_sp_items[]=['type'=>'facebook','label'=>'Facebook','href'=>$sp['fb_page'],'color'=>'#1877F2','bg'=>'#dbeafe'];
if(!empty($sp['website'])&&$sp['website']!=='--') $hz_sp_items[]=['type'=>'website','label'=>'Website','href'=>$sp['website'],'color'=>'#7c3aed','bg'=>'#ede9fe'];
if(!empty($sp['email'])&&$sp['email']!=='--') $hz_sp_items[]=['type'=>'email','label'=>'Email','href'=>'mailto:'.$sp['email'],'color'=>'#dc2626','bg'=>'#fee2e2'];
if(!empty($sp['phone'])&&$sp['phone']!=='--') $hz_sp_items[]=['type'=>'phone','label'=>'Phone','href'=>'tel:'.$sp['phone'],'color'=>'#16a34a','bg'=>'#dcfce7'];

$bgStyle = 'background-color:#eef0f5;';

/* ── Session Timeout ── */
$hz_st_minutes   = max(0, (int)trim($data['options']['session_timeout_minutes'] ?? '15'));
$hz_st_enabled   = (!empty($data['options']['session_timeout']) && $data['options']['session_timeout'] === 'enabled' && $hz_st_minutes > 0);
$hz_st_remaining = 0;
if ($hz_st_enabled) {
    $hz_created      = strtotime($data['transaction']['created_date'] ?? 'now');
    $hz_elapsed      = time() - $hz_created;
    $hz_st_remaining = ($hz_st_minutes * 60) - $hz_elapsed;
    if ($hz_st_remaining <= 0) {
        // Free Bangla QR unique-amount slot (if any) before canceling on timeout
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
if (!empty($data['options']['enable_bg_image']) && $data['options']['enable_bg_image']==='enabled' && !empty($data['options']['background_image']))
    $bgStyle = "background-image:url('" . htmlspecialchars($data['options']['background_image'], ENT_QUOTES) . "');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;";

$seoTitle=$data['options']['seo_title']??''; $seoDesc=$data['options']['seo_description']??'';
$seoKey=$data['options']['seo_keywords']??''; $anCode=$data['options']['analytics_code']??'';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($hz_current_lang); ?>" dir="<?php echo in_array($hz_current_lang,['ar','ur']) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo htmlspecialchars($data['lang']['checkout']??'Checkout'); ?> — <?php echo htmlspecialchars($data['brand']['name']); ?></title>
<link rel="shortcut icon" href="<?php echo $data['brand']['favicon']; ?>">
<?php if($seoTitle&&$seoTitle!=='--') echo '<meta name="title" content="'.htmlspecialchars($seoTitle).'">'; ?>
<?php if($seoDesc&&$seoDesc!=='--')   echo '<meta name="description" content="'.htmlspecialchars($seoDesc).'">'; ?>
<?php if($seoKey&&$seoKey!=='--')     echo '<meta name="keywords" content="'.htmlspecialchars($seoKey).'">'; ?>
<?php if($anCode&&$anCode!=='--')     echo $anCode; ?>
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&display=swap&font-display=swap" rel="stylesheet">
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'" crossorigin="anonymous">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"></noscript>
<?php echo pp_assets('head'); ?>
<style>
:root{--p:<?php echo $hz_primary; ?>;--p-lt:<?php echo pp_hexToRgba($hz_primary,0.09); ?>;--p-bd:<?php echo pp_hexToRgba($hz_primary,0.30); ?>;--p-dk:<?php echo pp_hexToRgba($hz_primary,0.85); ?>;--p-tx:<?php echo $hz_text_col; ?>;--text:#111827;--sub:#6b7280;--muted:#9ca3af;--line:#e5e7eb;--bg:#f5f6f9;--card:#ffffff;--r:6px;--r2:10px;--r3:13px;--sh:0 1px 3px rgba(0,0,0,.07),0 4px 14px rgba(0,0,0,.05)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}button{font-family:inherit;cursor:pointer;border:none;outline:none;background:none}
.hz-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:26px 15px 52px}
.hz-wrap{width:100%;max-width:520px;display:flex;flex-direction:column;flex:1 0 auto}
.hz-brand{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:0 2px;min-width:0;justify-content:space-between}
.hz-brand img{width:34px;height:34px;border-radius:7px;object-fit:cover;border:1px solid var(--line);flex-shrink:0}
.hz-brand-name{font-size:15px;font-weight:600;color:var(--text);letter-spacing:-.2px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hz-brand-timer{font-size:13px;font-weight:600;color:var(--sub);font-variant-numeric:tabular-nums;letter-spacing:.3px;flex-shrink:0}

/* NAV — single bar, no internal dividers */
.hz-nav{display:flex;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:var(--r2);margin-bottom:11px;box-shadow:var(--sh);overflow:hidden}
.hz-nav-cancel{display:flex;align-items:center;gap:7px;padding:0 14px;height:44px;font-size:13px;font-weight:500;color:var(--sub);flex-shrink:0;transition:color 140ms,background 140ms;white-space:nowrap}
.hz-nav-cancel:hover{color:#ef4444;background:#fff7f7}
.hz-nav-cancel svg{width:13px;height:13px;flex-shrink:0}
.hz-nav-pills{display:flex;align-items:center;flex:1;overflow-x:auto;scrollbar-width:none;padding:5px 6px;justify-content:flex-end;gap:2px}
.hz-nav-pills::-webkit-scrollbar{display:none}
.hz-nav-pill{display:flex;align-items:center;gap:6px;padding:0 11px;height:32px;border-radius:6px;font-size:13px;font-weight:500;color:var(--sub);white-space:nowrap;flex-shrink:0;transition:color 130ms,background 130ms}
.hz-nav-pill:hover{color:var(--text);background:var(--bg)}
.hz-nav-pill.on{color:var(--p);background:var(--p-lt);font-weight:600}
.hz-nav-pill svg{width:14px;height:14px;flex-shrink:0}
.hz-nav-pill .pill-label{display:inline}
@media(max-width:400px){.hz-nav-pill .pill-label{display:none}.hz-nav-pill{padding:0 9px}}

.hz-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);overflow:hidden;box-shadow:var(--sh)}
.hz-card-foot{padding:10px 16px;border-top:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:8px}
.hz-dl-receipt-btn:hover{color:var(--p) !important;border-color:var(--p) !important;background:var(--p-lt) !important}

/* Toast: compact & theme-matching */
#toast-container{top:16px !important;right:16px !important;z-index:9999 !important}
.custom-toast{font-family:'DM Sans',sans-serif !important;border:1px solid var(--line) !important;border-radius:var(--r2) !important;background:var(--card) !important;box-shadow:0 8px 24px rgba(0,0,0,.13) !important;min-width:260px;max-width:320px;padding:0 !important;overflow:hidden !important}
.custom-toast>div{padding:12px 14px !important}
.custom-toast .t-head{display:flex !important;align-items:flex-start !important;gap:9px !important}
.custom-toast [style*="font-weight: 500"]{font-size:13.5px !important;font-weight:600 !important;color:var(--text) !important;line-height:1.3 !important}
.custom-toast [style*="margin-left"]{margin-left:0 !important;margin-top:3px !important;font-size:12px !important;color:var(--sub) !important;line-height:1.5 !important}
.custom-toast .btn-close{width:20px !important;height:20px !important;padding:0 !important;opacity:.35 !important;flex-shrink:0 !important;margin-left:auto !important}
.custom-toast .btn-close:hover{opacity:.75 !important}
.custom-toast svg{width:15px !important;height:15px !important;flex-shrink:0 !important}
.hz-amount-bar{border-bottom:1px solid var(--line);display:flex;align-items:stretch}

/* Amount bar – inline, wrap-friendly */
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
.hz-inv-label{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:5px;margin-bottom:0}
.hz-inv-id{font-size:12px;color:var(--sub);font-family:'Courier New',monospace;letter-spacing:.3px}
.hz-amount-display {
    display: inline-block;          /* keeps number & currency together */
    max-width: 100%;
    text-align: right;
    word-break: break-word;         /* allow long numbers to wrap */
}
.hz-amount-num{
    font-size: clamp(16px, 5vw, 23px);  /* responsive, up to 23px */
    font-weight:700;
    color:var(--text);
    letter-spacing:-.6px;
    line-height:1.2;
}
.hz-amount-cur{
    font-size: clamp(12px, 3vw, 14px);  /* slightly smaller, stays with number */
    font-weight:500;
    color:var(--sub);
    margin-left:3px;
    white-space: nowrap;            /* currency never wraps alone */
}

.hz-subpanel{display:none;flex-direction:column}.hz-subpanel.hz-vis{display:flex;flex-direction:column}
.hz-panel-body{padding:18px 18px 16px;display:flex;flex-direction:column;gap:0}
.hz-sh{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);padding-bottom:12px;margin-bottom:14px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:7px}
.hz-sh svg{width:14px;height:14px;color:var(--text);flex-shrink:0}

/* Details – full width, left-aligned list */
.hz-irows{
    list-style:none;
    width:100%;                     /* no centering, uses panel padding */
    margin:0;
}
.hz-irow{display:flex;justify-content:space-between;align-items:flex-start;padding:9px 0;border-bottom:1px solid #f3f4f6;font-size:14px;gap:12px}
.hz-irow:last-child{border-bottom:none;padding-bottom:0}
.hz-irow-l{color:var(--sub)}
.hz-irow-v{font-weight:600;color:var(--text);text-align:right;word-break:break-word}
.hz-irow.disc .hz-irow-v{color:#16a34a}
.hz-irow.total .hz-irow-l{font-weight:700;color:var(--text);font-size:14.5px}
.hz-irow.total .hz-irow-v{color:var(--p);font-size:17px;font-weight:700}
/* Bold black separator above payable */
.hz-isep{border:none;border-top:2px solid #111827;margin:6px 0 2px;display:block;width:100%;}

/* FAQ */
.hz-faq{border:1px solid var(--line);border-radius:var(--r);margin-bottom:8px;overflow:hidden}
.hz-faq:last-child{margin-bottom:0}
.hz-faq-q{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;cursor:pointer;width:100%;text-align:left;transition:background 140ms;background:none;border:none;font-family:inherit}
.hz-faq-q:hover{background:var(--bg)}
.hz-faq-n{min-width:22px;height:22px;border-radius:4px;background:var(--p-lt);color:var(--p);font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.hz-faq-text{flex:1;font-size:13.5px;font-weight:500;color:var(--text);line-height:1.45}
.hz-faq-arr{font-size:11px;color:var(--muted);flex-shrink:0;margin-top:5px;transition:transform .22s}
.hz-faq.open .hz-faq-arr{transform:rotate(180deg)}
.hz-faq-a{max-height:0;overflow:hidden;font-size:13.5px;color:var(--sub);line-height:1.65;transition:max-height .26s ease,padding .2s;padding:0 15px 0 48px}
.hz-faq.open .hz-faq-a{max-height:600px;padding-bottom:14px}

/* Support – hover matches gateway cards */
.hz-sp-grid{display:grid;grid-template-columns:repeat(<?php echo $hz_sp_cols; ?>,1fr);gap:8px}
.hz-sp-card{display:flex;flex-direction:column;align-items:center;text-align:center;padding:14px 10px 12px;border:1px solid var(--line);border-radius:var(--r2);transition:border-color 200ms,background 200ms;text-decoration:none;background:var(--card);cursor:pointer}
.hz-sp-card:hover{
    border-color:var(--p-bd);       /* matches gateway hover */
    background:var(--p-lt);
    /* no scale or shadow */
}
.hz-sp-ic-wrap{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:9px;flex-shrink:0}
.hz-sp-ic-wrap i{font-size:20px}
.hz-sp-card-name{font-size:12.5px;font-weight:700;color:var(--text);line-height:1.2}
<?php if($hz_sp_cols===1): ?>
.hz-sp-card{flex-direction:row;text-align:left;gap:13px;padding:11px 14px;align-items:center}
.hz-sp-ic-wrap{margin-bottom:0;width:40px;height:40px;border-radius:9px;flex-shrink:0}
.hz-sp-ic-wrap i{font-size:18px}
.hz-sp-card-name{flex:1;font-size:13px}
<?php endif; ?>

.hz-empty{display:flex;flex-direction:column;align-items:center;text-align:center;padding:30px 16px;gap:0}
.hz-empty-ic{width:48px;height:48px;border-radius:10px;margin:0 0 13px;background:var(--bg);border:1px solid var(--line);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.hz-empty-ic i{font-size:20px;color:var(--muted)}
.hz-empty-t{font-size:14.5px;font-weight:600;color:var(--text);margin-bottom:5px}
.hz-empty-s{font-size:13px;color:var(--muted);line-height:1.55}

/* Tabs – dynamic mask and centering */
.hz-tabs-wrap{
    overflow-x:auto;
    scrollbar-width:none;
    border-bottom:1px solid var(--line);
    -webkit-mask-image:linear-gradient(to right, black 90%, transparent 100%);
    mask-image:linear-gradient(to right, black 90%, transparent 100%);
    transition:mask-image 0.2s, -webkit-mask-image 0.2s;
}
.hz-tabs-wrap::-webkit-scrollbar{display:none}
.hz-tabs{
    display:flex;
    min-width:max-content;
    max-width:100%;
    transition:justify-content 0.2s;
}
.hz-tabs-center{
    justify-content: center !important;
}
.hz-tab{display:flex;align-items:center;gap:7px;padding:13px 18px 12px;font-size:13.5px;font-weight:500;color:var(--muted);border-bottom:2px solid transparent;transition:color 140ms,border-color 140ms;white-space:nowrap;cursor:pointer;flex-shrink:0;margin-bottom:-1px;background:none}
.hz-tab:hover{color:var(--sub)}.hz-tab.on{color:var(--p);border-bottom-color:var(--p);font-weight:600}
.hz-tab svg{width:15px;height:15px;flex-shrink:0}

/* Gateway grid */
.hz-gw-panel{display:none;padding:16px 15px 14px}.hz-gw-panel.on{display:block}
<?php $img_mh=$hz_show_name?'42px':'56px'; ?>
.hz-gw-grid{display:grid;grid-template-columns:repeat(<?php echo $hz_columns; ?>,1fr);gap:11px;margin-bottom:14px}
@media(max-width:640px){.hz-gw-grid{grid-template-columns:repeat(2,1fr);gap:9px}}
.hz-gw-card{position:relative;border:1.5px solid var(--line);border-radius:var(--r2);padding:<?php echo $hz_show_name?'8px 2px 6px':'10px 2px'; ?>;cursor:pointer;text-align:center;background:var(--card);transition:border-color 140ms,background 140ms;user-select:none}
.hz-gw-card:hover{border-color:var(--p-bd)}.hz-gw-card.selected{border-color:var(--p)}
.hz-gw-sel{position:absolute;top:5px;right:5px;width:17px;height:17px;border-radius:50%;background:var(--p);display:none;align-items:center;justify-content:center}
.hz-gw-sel i{font-size:8px;color:var(--p-tx)}.hz-gw-card.selected .hz-gw-sel{display:flex}
.hz-gw-img{display:flex;align-items:center;justify-content:center;<?php echo $hz_show_name?'margin-bottom:6px;':''; ?>}
.hz-gw-img img{width:100%;max-height:<?php echo $img_mh; ?>;object-fit:contain;display:block}
.hz-gw-lbl{font-size:11.5px;font-weight:500;color:var(--sub);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

.hz-pay-wrap{position:sticky;bottom:0;padding:0 0 2px}
.hz-pay{display:flex;align-items:center;justify-content:center;gap:9px;width:100%;padding:14px 20px;border-radius:var(--r);font-size:15px;font-weight:600;color:var(--p-tx);background:var(--p);transition:background 140ms,opacity 140ms;border:none;cursor:pointer;letter-spacing:-.1px}
.hz-pay:hover{background:var(--p-dk)}.hz-pay.idle{opacity:.62;cursor:default}.hz-pay.idle:hover{background:var(--p)}
.hz-pay-static{opacity:.72;cursor:default;pointer-events:none;user-select:none}.hz-pay-static:hover{background:var(--p)}
.hz-pay i{font-size:15px}
@keyframes hzPulse{0%,100%{}30%,70%{border-color:var(--p)}}
.hz-gw-card.hint{animation:hzPulse .45s ease}
/* Direct-redirect mode: card is a full clickable link — no selection state needed */
.hz-gw-card.hz-direct{cursor:pointer}
.hz-gw-card.hz-direct:hover{border-color:var(--p)}
.hz-gw-card.hz-direct .hz-gw-sel{display:none!important}
/* spinner injected by JS on click — no static arrow */
.hz-gw-spinner{position:absolute;bottom:5px;right:6px;width:12px;height:12px;border:1.5px solid var(--p-bd);border-top-color:var(--p);border-radius:50%;animation:hzSpin .55s linear infinite;display:none}
.hz-gw-card.hz-loading .hz-gw-spinner{display:block}
@keyframes hzSpin{to{transform:rotate(360deg)}}

.hz-footer{text-align:center;margin-top:14px;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;width:100%}
.hz-footer-links{margin-top:32px;padding-bottom:8px;font-size:11.5px;color:var(--muted);text-align:center}
.hz-footer-links a{color:var(--muted);text-decoration:none;transition:color 130ms}
.hz-footer-links a:hover{color:var(--p)}


/* Language dropdown styling */

/* Override violet hover/focus with theme color */
::selection{background:var(--p-lt);color:var(--text)}
*:focus-visible{outline:2px solid var(--p);outline-offset:2px}
a:hover{color:var(--p)}
:root{--tblr-primary:<?php echo $hz_primary; ?>;--tblr-link-color:<?php echo $hz_primary; ?>;--bs-link-color:<?php echo $hz_primary; ?>}
/* Toast override — defined above (lines 194-202); no duplicate needed */
/* intl-tel-input phone widget fix */
.iti{width:100%!important}
.iti input,.iti input[type=tel]{color:#111827!important;-webkit-text-fill-color:#111827!important}
.iti__selected-dial-code,.iti__arrow{color:#111827!important}
.iti__selected-flag{background:transparent!important}
.iti__country-list{background:#ffffff;color:#111827;border-color:#e5e7eb}
.iti__country-list .iti__country-name,.iti__dial-code{color:#111827!important}
.iti__country-list .iti__country.iti__highlight{background:var(--p-lt)}
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

<!-- NAV: one unified bar -->
<div class="hz-nav">
    <button type="button" class="hz-nav-cancel" id="hz-nav-left" onclick="hzNavLeft()">
        <svg id="hz-ico-back" style="display:none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M5 12l6 6"/><path d="M5 12l6 -6"/></svg>
        <svg id="hz-ico-x" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M18 6l-12 12"/><path d="M6 6l12 12"/></svg>
        <span id="hz-cancel-txt"><?php echo htmlspecialchars($data['lang']['cancel']??'Cancel'); ?></span>
    </button>
    <div class="hz-nav-pills">
        <button type="button" class="hz-nav-pill hz-util" data-sp="details">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 13l6 0"/><path d="M9 17l6 0"/></svg>
            <span class="pill-label"><?php echo $data['lang']['tab_details']??'Transaction Details'; ?></span>
        </button>
        <?php if($hz_show_faq): ?>
        <button type="button" class="hz-nav-pill hz-util" data-sp="faq">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/></svg>
            <span class="pill-label"><?php echo $data['lang']['tab_faq']??'FAQ'; ?></span>
        </button>
        <?php endif; ?>
        <?php if($hz_show_support): ?>
        <button type="button" class="hz-nav-pill hz-util" data-sp="support">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 15a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2l0 -3"/><path d="M15 15a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2l0 -3"/><path d="M4 15v-3a8 8 0 0 1 16 0v3"/></svg>
            <span class="pill-label"><?php echo $data['lang']['tab_support']??'Help &amp; Support'; ?></span>
        </button>
        <?php endif; ?>
        <?php if(count($hz_avail_langs) > 1): ?>
        <!-- 2-lang direct switch vs. modal handled by hzOpenLangModal() in inc/lang-modal.php -->
        <button type="button" class="hz-nav-pill" onclick="hzOpenLangModal()">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6.371c0 4.418 -2.239 6.629 -5 6.629"/><path d="M4 6.371h7"/><path d="M5 9c0 2.144 2.252 3.908 6 4"/><path d="M12 20l4 -9l4 9"/><path d="M19.1 18h-6.2"/></svg>
            <span class="pill-label"><?php echo $data['lang']['language']??'Language'; ?></span>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="hz-card">

    <!-- Amount bar with vertical divider -->
    <div class="hz-amount-bar">
        <div class="hz-amount-half">
            <div class="hz-inv-label">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2"/></svg>
                <?php echo $data['lang']['receipt_id']??'Receipt ID'; ?>
            </div>
            <div class="hz-inv-id"><?php echo htmlspecialchars($hz_ref); ?></div>
        </div>
        <div class="hz-amount-half">
            <div class="hz-amount-display">
                <span class="hz-amount-num"><?php echo money_round($hz_amount,2); ?></span>
                <span class="hz-amount-cur"><?php echo $hz_currency; ?></span>
            </div>
        </div>
    </div>

    <!-- Details panel -->
    <div class="hz-subpanel" id="hz-sp-details">
        <div class="hz-panel-body">
            <div class="hz-sh">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 13l6 0"/><path d="M9 17l6 0"/></svg>
                <?php echo $data['lang']['section_details']??'Transaction Details'; ?>
            </div>
            <ul class="hz-irows">
                <?php if($hz_cname): ?><li class="hz-irow"><span class="hz-irow-l"><?php echo $data['lang']['customer']??'Customer'; ?></span><span class="hz-irow-v"><?php echo htmlspecialchars($hz_cname); ?></span></li><?php endif; ?>
                <?php if($hz_cemail): ?><li class="hz-irow"><span class="hz-irow-l"><?php echo $data['lang']['email_label']??'Email'; ?></span><span class="hz-irow-v" style="font-size:13px"><?php echo htmlspecialchars($hz_cemail); ?></span></li><?php endif; ?>
                <li class="hz-irow"><span class="hz-irow-l"><?php echo $data['lang']['receipt_id']??'Receipt ID'; ?></span><span class="hz-irow-v" style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($hz_ref); ?></span></li>
                <li class="hz-irow"><span class="hz-irow-l"><?php echo $data['lang']['currency']??'Currency'; ?></span><span class="hz-irow-v"><?php echo $hz_currency; ?></span></li>
                <li class="hz-irow"><span class="hz-irow-l"><?php echo $data['lang']['amount']??'Amount'; ?></span><span class="hz-irow-v"><?php echo money_round($hz_amount,2).' '.$hz_currency; ?></span></li>
                <?php if((float)$hz_discount>0): ?><li class="hz-irow disc"><span class="hz-irow-l"><?php echo $data['lang']['discount']??'Discount'; ?></span><span class="hz-irow-v">− <?php echo money_round($hz_discount,2).' '.$hz_currency; ?></span></li><?php endif; ?>
                <li class="hz-irow"><span class="hz-irow-l"><?php echo $data['lang']['processing_fee']??'Processing Fee'; ?></span><span class="hz-irow-v"><?php echo money_round($hz_fee,2).' '.$hz_currency; ?></span></li>
            </ul>
            <hr class="hz-isep">
            <ul class="hz-irows">
                <li class="hz-irow total"><span class="hz-irow-l"><?php echo $data['lang']['payable']??'Total Payable'; ?></span><span class="hz-irow-v"><?php echo $hz_payable.' '.$hz_currency; ?></span></li>
            </ul>
            <div style="margin-top:14px">
                <a href="<?php echo pp_checkout_address(); ?>?receipt" target="_blank" class="hz-dl-receipt-btn" style="display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:10px 14px;border-radius:var(--r);background:var(--bg);border:1px solid var(--line);font-size:13px;font-weight:600;color:var(--sub);text-decoration:none;transition:color 130ms,background 130ms,border-color 130ms;letter-spacing:-.1px">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2"/><path d="M7 11l5 5l5 -5"/><path d="M12 4l0 12"/></svg>
                    <?php echo $data['lang']['download_receipt']??'Download Receipt'; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- FAQ panel -->
    <div class="hz-subpanel" id="hz-sp-faq">
        <div class="hz-panel-body">
            <div class="hz-sh">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12a9 9 0 1 0 18 0a9 9 0 0 0 -18 0"/><path d="M12 9h.01"/><path d="M11 12h1v4h1"/></svg>
                <?php echo $data['lang']['tab_faq']??'FAQ'; ?>
            </div>
            <?php if(empty($data['faqs'])): ?>
            <div class="hz-empty"><div class="hz-empty-ic"><i class="fa-regular fa-circle-question"></i></div><div class="hz-empty-t"><?php echo $data['lang']['no_faqs_title']??'No FAQs Published'; ?></div><div class="hz-empty-s"><?php echo $data['lang']['no_faqs_desc']??'This merchant has not published any frequently asked questions.'; ?></div></div>
            <?php else: $n=0; foreach($data['faqs'] as $faq): $n++; ?>
            <div class="hz-faq">
                <button type="button" class="hz-faq-q" onclick="this.closest('.hz-faq').classList.toggle('open')">
                    <span class="hz-faq-n"><?php echo $n; ?></span>
                    <span class="hz-faq-text"><?php echo htmlspecialchars($faq['title']); ?></span>
                    <i class="fa-solid fa-chevron-down hz-faq-arr"></i>
                </button>
                <div class="hz-faq-a"><?php echo $faq['description']; ?></div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Support panel -->
    <div class="hz-subpanel" id="hz-sp-support">
        <div class="hz-panel-body">
            <div class="hz-sh">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 15a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2l0 -3"/><path d="M15 15a2 2 0 0 1 2 -2h1a2 2 0 0 1 2 2v3a2 2 0 0 1 -2 2h-1a2 2 0 0 1 -2 -2l0 -3"/><path d="M4 15v-3a8 8 0 0 1 16 0v3"/></svg>
                <?php echo $data['lang']['section_support']??'Help &amp; Support'; ?>
            </div>
            <?php if(empty($hz_sp_items)): ?>
            <div class="hz-empty"><div class="hz-empty-ic"><i class="fa-solid fa-headset"></i></div><div class="hz-empty-t"><?php echo $data['lang']['no_support_title']??'No Support Channels Configured'; ?></div><div class="hz-empty-s"><?php echo $data['lang']['no_support_desc']??'This merchant has not provided any contact information.'; ?></div></div>
            <?php else:
            $sp_fa=['whatsapp'=>'fa-brands fa-whatsapp','telegram'=>'fa-brands fa-telegram',
                'messenger'=>'fa-brands fa-facebook-messenger','facebook'=>'fa-brands fa-square-facebook',
                'website'=>'fa-solid fa-earth-americas','email'=>'fa-solid fa-envelope','phone'=>'fa-solid fa-phone'];
            ?>
            <div class="hz-sp-grid">
                <?php foreach($hz_sp_items as $item): $ic=$sp_fa[$item['type']]??'fa-solid fa-link'; ?>
                <a href="<?php echo htmlspecialchars($item['href']); ?>" target="<?php echo in_array($item['type'],['email','phone'])?'_self':'_blank'; ?>" class="hz-sp-card">
                    <div class="hz-sp-ic-wrap" style="background:<?php echo $item['bg']; ?>">
                        <i class="<?php echo $ic; ?>" style="color:<?php echo $item['color']; ?>"></i>
                    </div>
                    <span class="hz-sp-card-name"><?php echo $item['label']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gateway section -->
    <div id="hz-gw-section">
        <?php if(count($hz_tabs)>1): ?>
        <div class="hz-tabs-wrap" id="hz-tabs-wrap"><div class="hz-tabs" id="hz-tabs">
            <?php if(in_array('mfs',$hz_tabs)): ?>
            <button type="button" class="hz-tab" data-target="hz-gw-mfs">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14"/><path d="M11 4h2"/><path d="M12 17v.01"/></svg>
                <?php echo $data['lang']['mobile_banking']??'Mobile Banking'; ?>
            </button>
            <?php endif; ?>
            <?php if(in_array('bank',$hz_tabs)): ?>
            <button type="button" class="hz-tab" data-target="hz-gw-bank">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0"/><path d="M3 10l18 0"/><path d="M5 6l7 -3l7 3"/><path d="M4 10l0 11"/><path d="M20 10l0 11"/><path d="M8 14l0 3"/><path d="M12 14l0 3"/><path d="M16 14l0 3"/></svg>
                <?php echo $data['lang']['net_banking']??'Net Banking'; ?>
            </button>
            <?php endif; ?>
            <?php if(in_array('global',$hz_tabs)): ?>
            <button type="button" class="hz-tab" data-target="hz-gw-global">
                <!-- Globe icon for International -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                <?php echo $data['lang']['global']??'International'; ?>
            </button>
            <?php endif; ?>
        </div></div>
        <?php endif; ?>

        <?php $render_panel=function($groups,$panel_id) use ($hz_show_name,$hz_amount,$hz_currency,$hz_direct,$data){ ?>
        <div class="hz-gw-panel" id="<?php echo $panel_id; ?>">
            <?php if(!empty($groups)): ?>
            <div class="hz-gw-grid">
                <?php foreach($groups as $g):
                    $gw_id   = $g['items'][0]['gateway_id'];
                    $gw_name = $g['items'][0]['display'] ?: $g['name']; ?>
                <div class="hz-gw-card<?php echo $hz_direct?' hz-direct':''; ?>"
                     data-gw-id="<?php echo htmlspecialchars($gw_id); ?>"
                     onclick="hzCardClick(this)">
                    <div class="hz-gw-sel"><i class="fa-solid fa-check"></i></div>
                    <?php if($hz_direct): ?><div class="hz-gw-spinner"></div><?php endif; ?>
                    <div class="hz-gw-img"><img src="<?php echo $g['logo']; ?>" alt="<?php echo htmlspecialchars($gw_name); ?>"></div>
                    <?php if($hz_show_name): ?><div class="hz-gw-lbl"><?php echo htmlspecialchars($gw_name); ?></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?><div class="hz-empty"><div class="hz-empty-ic"><i class="fa-solid fa-credit-card"></i></div><div class="hz-empty-t"><?php echo $data['lang']['no_gateways_title']??'No Payment Methods Available'; ?></div><div class="hz-empty-s"><?php echo $data['lang']['no_gateways_desc']??'No payment methods have been configured for this transaction.'; ?></div></div><?php endif; ?>
            <div class="hz-pay-wrap">
                <?php if($hz_direct): ?>
                <div class="hz-pay hz-pay-static" aria-disabled="true">
                    <i class="fa-solid fa-lock"></i>
                    <span><?php echo $data['lang']['payable']??'Payable'; ?> — <?php echo money_round($hz_amount,2).' '.$hz_currency; ?></span>
                </div>
                <?php else: ?>
                <button type="button" class="hz-pay idle" id="hz-pay-<?php echo $panel_id; ?>" onclick="hzPay(this)">
                    <i class="fa-solid fa-lock"></i>
                    <span><?php echo $data['lang']['pay_now']??'Pay Now'; ?> — <?php echo money_round($hz_amount,2).' '.$hz_currency; ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php };
        if(in_array('mfs',$hz_tabs))    $render_panel($hz_g_mfs,   'hz-gw-mfs');
        if(in_array('bank',$hz_tabs))   $render_panel($hz_g_bank,  'hz-gw-bank');
        if(in_array('global',$hz_tabs)) $render_panel($hz_g_global,'hz-gw-global');
        if(empty($hz_tabs)): ?>
        <div style="padding:16px"><div class="hz-empty"><div class="hz-empty-ic"><i class="fa-solid fa-credit-card"></i></div><div class="hz-empty-t"><?php echo $data['lang']['no_gateways_title']??'No Payment Methods Available'; ?></div></div></div>
        <?php endif; ?>
    </div>
</div><!-- /.hz-wrap -->

<?php require __DIR__ . '/inc/footer.php'; ?>

</div><!-- /.hz-page -->

<?php require __DIR__ . '/inc/lang-modal.php'; ?>

<?php echo pp_assets('footer'); ?>
<script data-cfasync="false">
var HZ_URL=<?php echo json_encode(pp_checkout_address()); ?>;
var HZ_CANCEL=HZ_URL+'?cancel';

var hzCurrentSp=null,hzSelectedGwId=null;

function updateTabsAlignment() {
    var wrap = document.getElementById('hz-tabs-wrap');
    var tabs = document.getElementById('hz-tabs');
    if (!wrap || !tabs) return;
    // If tabs content fits without scrolling, center them and remove mask
    if (tabs.scrollWidth <= wrap.clientWidth) {
        tabs.classList.add('hz-tabs-center');
        wrap.style.webkitMaskImage = 'none';
        wrap.style.maskImage = 'none';
    } else {
        tabs.classList.remove('hz-tabs-center');
        // Restore dynamic mask (will be updated by scroll handler)
        // We'll trigger the scroll handler to recalc mask
        if (wrap.scrollHandler) wrap.scrollHandler();
    }
}

document.addEventListener('DOMContentLoaded',function(){
    var tabs=document.querySelectorAll('.hz-tab');
    function activateTab(btn){
        tabs.forEach(function(t){t.classList.remove('on');});
        document.querySelectorAll('.hz-gw-panel').forEach(function(p){p.classList.remove('on');});
        btn.classList.add('on');
        var p=document.getElementById(btn.getAttribute('data-target'));if(p)p.classList.add('on');
        hzClearSelection();
    }
    tabs.forEach(function(t){t.addEventListener('click',function(){activateTab(this);});});
    if(tabs.length)activateTab(tabs[0]);else{var fp=document.querySelector('.hz-gw-panel');if(fp)fp.classList.add('on');}
    document.querySelectorAll('.hz-util').forEach(function(btn){
        btn.addEventListener('click',function(){
            var sp=this.getAttribute('data-sp'),same=(hzCurrentSp===sp);
            document.querySelectorAll('.hz-util').forEach(function(b){b.classList.remove('on');});
            if(same)hzShowGateways();else{this.classList.add('on');hzShowSp(sp);}
        });
    });

    // Dynamic mask for tab scroll
    var tabWrap = document.getElementById('hz-tabs-wrap');
    if(tabWrap){
        function updateTabMask(){
            var scrollLeft = tabWrap.scrollLeft;
            var maxScroll = tabWrap.scrollWidth - tabWrap.clientWidth;
            var maskImage;
            if(maxScroll <= 0){
                maskImage = 'none';
            } else if(scrollLeft <= 0){
                maskImage = 'linear-gradient(to right, black 90%, transparent 100%)';
            } else if(scrollLeft >= maxScroll){
                maskImage = 'linear-gradient(to left, black 90%, transparent 100%)';
            } else {
                maskImage = 'linear-gradient(to right, transparent, black 10%, black 90%, transparent)';
            }
            tabWrap.style.webkitMaskImage = maskImage;
            tabWrap.style.maskImage = maskImage;
        }
        tabWrap.scrollHandler = updateTabMask; // store for later use
        tabWrap.addEventListener('scroll', updateTabMask);
        updateTabMask();
    }

    // Initial tab alignment
    updateTabsAlignment();
    // Update on window resize
    window.addEventListener('resize', updateTabsAlignment);
});
function hzShowSp(sp){
    ['details','faq','support'].forEach(function(k){var el=document.getElementById('hz-sp-'+k);if(el)el.classList.toggle('hz-vis',k===sp);});
    document.getElementById('hz-gw-section').style.display='none';hzCurrentSp=sp;
    document.getElementById('hz-ico-x').style.display='none';document.getElementById('hz-ico-back').style.display='';
    document.getElementById('hz-cancel-txt').textContent='<?php echo addslashes($data['lang']['back']??'Back'); ?>';
}
function hzShowGateways(){
    ['details','faq','support'].forEach(function(k){var el=document.getElementById('hz-sp-'+k);if(el)el.classList.remove('hz-vis');});
    document.getElementById('hz-gw-section').style.display='';hzCurrentSp=null;
    document.querySelectorAll('.hz-util').forEach(function(b){b.classList.remove('on');});
    document.getElementById('hz-ico-x').style.display='';document.getElementById('hz-ico-back').style.display='none';
    document.getElementById('hz-cancel-txt').textContent='<?php echo addslashes($data['lang']['cancel']??'Cancel'); ?>';
}
function hzNavLeft(){if(hzCurrentSp)hzShowGateways();else window.location.href=HZ_CANCEL;}
var HZ_DIRECT = <?php echo $hz_direct ? 'true' : 'false'; ?>;
/* Prefetch gateway page on card hover to speed up navigation */
(function(){
    var prefetched={};
    document.addEventListener('mouseover',function(e){
        var card=e.target.closest('.hz-gw-card');
        if(!card) return;
        var gwId=card.getAttribute('data-gw-id');
        if(!gwId||prefetched[gwId]) return;
        prefetched[gwId]=true;
        var link=document.createElement('link');
        link.rel='prefetch';
        link.href=HZ_URL+'?gateway='+encodeURIComponent(gwId);
        document.head.appendChild(link);
    },true);
    /* Also prefetch on touch start for mobile */
    document.addEventListener('touchstart',function(e){
        var card=e.target.closest('.hz-gw-card');
        if(!card) return;
        var gwId=card.getAttribute('data-gw-id');
        if(!gwId||prefetched[gwId]) return;
        prefetched[gwId]=true;
        var link=document.createElement('link');
        link.rel='prefetch';
        link.href=HZ_URL+'?gateway='+encodeURIComponent(gwId);
        document.head.appendChild(link);
    },{passive:true});
})();

function hzClearSelection(){
    document.querySelectorAll('.hz-gw-card.selected').forEach(function(c){c.classList.remove('selected');});
    hzSelectedGwId=null;
    document.querySelectorAll('.hz-pay').forEach(function(b){b.classList.add('idle');});
}
function hzCardClick(card){
    var gwId=card.getAttribute('data-gw-id');
    if(HZ_DIRECT){
        /* Direct mode: show spinner, disable further clicks, navigate */
        if(card.classList.contains('hz-loading')) return;
        card.classList.add('hz-loading');
        /* Disable all other cards */
        document.querySelectorAll('.hz-gw-card').forEach(function(c){c.style.pointerEvents='none';});
        card.style.pointerEvents='auto';
        /* Instant visual feedback before navigation */
        document.body.style.opacity='0.7';
        document.body.style.transition='opacity 200ms';
        window.location.href=HZ_URL+'?gateway='+encodeURIComponent(gwId);
        return;
    }
    var wasSelected=card.classList.contains('selected');
    hzClearSelection();
    if(!wasSelected){
        card.classList.add('selected');hzSelectedGwId=gwId;
        var panel=card.closest('.hz-gw-panel'),payBtn=panel?panel.querySelector('.hz-pay'):null;
        if(payBtn)payBtn.classList.remove('idle');
    }
}
function hzPay(btn){
    var hasSel=hzSelectedGwId;
    if(btn.classList.contains('idle')||!hasSel){
        var panel=btn.closest('.hz-gw-panel');
        if(panel)panel.querySelectorAll('.hz-gw-card').forEach(function(c){c.classList.remove('hint');void c.offsetWidth;c.classList.add('hint');setTimeout(function(){c.classList.remove('hint');},800);});
        return;
    }
    window.location.href=HZ_URL+'?gateway='+encodeURIComponent(hzSelectedGwId);
}

<?php if ($hz_st_enabled): ?>
(function(){
    var rem = <?php echo (int)$hz_st_remaining; ?>;
    var el  = document.getElementById('hz-session-timer');
    if (!el) return;
    function fmt(s){ var m=Math.floor(s/60),sc=s%60; return (m<10?'0':'')+m+':'+(sc<10?'0':'')+sc; }
    el.textContent = fmt(rem);
    var iv = setInterval(function(){
        rem--;
        if (rem <= 0) { clearInterval(iv); window.location.replace(HZ_CANCEL); return; }
        el.textContent = fmt(rem);
    }, 1000);
})();
<?php endif; ?>
</script>
</body></html>