<?php
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

/* ── Mode detection ─────────────────────────────
   invoice mode → $data['invoice'] exists
   receipt mode → called from renderCheckout ?view_receipt
   ───────────────────────────────────────────── */
$hz_mode = isset($data['invoice']) ? 'invoice' : 'receipt';

if (isset($_GET['lang']) && $_GET['lang'] !== '') {
    pp_set_lang($_GET['lang']);
    /* Rebuild URL preserving all params except lang (will be set via session) */
    $p = array_filter($_GET, fn($k) => $k !== 'lang', ARRAY_FILTER_USE_KEY);
    $redir = pp_checkout_address() . ($p ? '?' . http_build_query($p) : '');
    echo '<script>location.href='.json_encode($redir).';</script>'; exit();
}
if ($hz_mode === 'receipt' && isset($_GET['receipt'])) { pp_downloadReceiptPDF($data); }

$hz_primary  = $data['options']['primary_color'] ?? '#1a56db';
$hz_text_col = $data['options']['text_color']    ?? '#FFFFFF';
$anCode      = $data['options']['analytics_code'] ?? '';

/* Minimal lang setup needed for footer.php include */
$hz_avail_langs_raw = $data['options']['available_languages'] ?? 'en,bn,hi,ur,ar';
$hz_avail_langs     = array_filter(array_map('trim', explode(',', $hz_avail_langs_raw)));

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
require_once __DIR__ . '/inc/lang.php';

$bgStyle = 'background-color:#eef0f5;';
if (!empty($data['options']['enable_bg_image']) && $data['options']['enable_bg_image']==='enabled' && !empty($data['options']['background_image']))
    $bgStyle = "background-image:url('".$data['options']['background_image']."');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;";

/* ── RECEIPT MODE ── */
if ($hz_mode === 'receipt') {
    $status  = strtolower($data['transaction']['status'] ?? 'pending');
    $ret     = $data['transaction']['return_url'] ?? '';
    $hasRet  = ($ret && $ret !== '--');
    $amt     = $data['transaction']['amount']           ?? 0;
    $fee     = $data['transaction']['processing_fee']   ?? 0;
    $disc    = $data['transaction']['discount_amount']  ?? 0;
    $payable = number_format((float)$amt - (float)$disc + (float)$fee, 2);
    $cur     = $data['transaction']['currency']         ?? '';
    $lcur    = $data['transaction']['local_currency']   ?? $cur;
    $lamt    = $data['transaction']['local_net_amount'] ?? 0;
    $method  = $data['transaction']['payment_method']   ?? 'N/A';
    $ref     = $data['transaction']['ref']              ?? '';
    $cname   = $data['transaction']['customer']['name']   ?? '';
    $cemail  = $data['transaction']['customer']['email']  ?? '';
    $cmobile = $data['transaction']['customer']['mobile'] ?? '';
    $statusMap = [
        'completed' => ['label'=>$data['lang']['payment_successful']??'Payment Successful','color'=>'#16a34a','bg'=>'#f0fdf4','border'=>'#bbf7d0','icon'=>'fa-circle-check'],
        'pending'   => ['label'=>$data['lang']['payment_pending']??'Payment Pending',   'color'=>'#d97706','bg'=>'#fffbeb','border'=>'#fde68a','icon'=>'fa-clock'],
        'refunded'  => ['label'=>$data['lang']['payment_refunded']??'Payment Refunded', 'color'=>'#0369a1','bg'=>'#eff6ff','border'=>'#bfdbfe','icon'=>'fa-rotate-left'],
        'canceled'  => ['label'=>$data['lang']['payment_canceled']??'Payment Cancelled','color'=>'#dc2626','bg'=>'#fff1f2','border'=>'#fecaca','icon'=>'fa-circle-xmark'],
        'failed'    => ['label'=>$data['lang']['payment_failed']??'Payment Failed',     'color'=>'#dc2626','bg'=>'#fff1f2','border'=>'#fecada','icon'=>'fa-triangle-exclamation'],
    ];
    $st = $statusMap[$status] ?? $statusMap['pending'];
}

/* ── INVOICE MODE ── */
if ($hz_mode === 'invoice') {
    $inv_status   = $data['invoice']['status'] ?? 'unpaid';
    $inv_currency = $data['invoice']['currency'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($hz_current_lang); ?>" dir="<?php echo in_array($hz_current_lang,['ar','ur']) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo $hz_mode==='invoice' ? 'Invoice' : 'Receipt'; ?> — <?php echo htmlspecialchars($data['brand']['name']); ?></title>
<link rel="shortcut icon" href="<?php echo $data['brand']['favicon']; ?>">
<?php if($anCode&&$anCode!=='--') echo $anCode; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<?php echo pp_assets('head'); ?>
<style>
:root{
    --p:<?php echo $hz_primary; ?>;
    --p-lt:<?php echo pp_hexToRgba($hz_primary,0.09); ?>;
    --p-dk:<?php echo pp_hexToRgba($hz_primary,0.85); ?>;
    --p-tx:<?php echo $hz_text_col; ?>;
    --text:#111827;--sub:#6b7280;--muted:#9ca3af;--line:#e5e7eb;
    --bg:#f5f6f9;--card:#ffffff;--r:6px;--r2:10px;--r3:13px;
    --sh:0 1px 3px rgba(0,0,0,.07),0 4px 14px rgba(0,0,0,.05);
    --tblr-primary:<?php echo $hz_primary; ?>;
    --tblr-link-color:<?php echo $hz_primary; ?>;
    --bs-link-color:<?php echo $hz_primary; ?>;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
a:hover{color:var(--p)}
button{font-family:inherit;cursor:pointer;border:none;outline:none;background:none}
::selection{background:var(--p-lt);color:var(--text)}
*:focus-visible{outline:2px solid var(--p);outline-offset:2px}

.hz-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:26px 15px 52px}
.hz-wrap-narrow{width:100%;max-width:480px}
.hz-wrap-wide{width:100%;max-width:760px}

/* Brand */
.hz-brand{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:0 2px}
.hz-brand img{width:34px;height:34px;border-radius:7px;object-fit:cover;border:1px solid var(--line);flex-shrink:0}
.hz-brand-name{font-size:15px;font-weight:600;color:var(--text)}

/* Card */
.hz-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);overflow:hidden;box-shadow:var(--sh)}

/* ── BACK BUTTON — inside card, top-left ── */
.hz-rc-back-bar{display:flex;align-items:center;padding:10px 14px;border-bottom:1px solid var(--line);background:var(--bg)}
.hz-rc-back-btn{display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;color:var(--sub);background:none;border:none;cursor:pointer;padding:4px 6px;border-radius:5px;transition:color 130ms,background 130ms;font-family:inherit}
.hz-rc-back-btn:hover{color:var(--p);background:var(--p-lt)}
.hz-rc-back-btn svg{width:12px;height:12px;flex-shrink:0}

/* ── RECEIPT MODE ── */
.hz-rc-hero{padding:28px 24px 22px;text-align:center;border-bottom:1px solid var(--line)}
.hz-rc-icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;border:2px solid}
.hz-rc-icon i{font-size:24px}
.hz-rc-title{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.9px;color:var(--muted);margin-bottom:6px}
.hz-rc-amount{font-size:34px;font-weight:700;letter-spacing:-1px;color:var(--text);line-height:1}
.hz-rc-cur{font-size:16px;font-weight:500;color:var(--sub);margin-left:3px}
.hz-rc-status-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-top:10px;border:1px solid}
.hz-rc-ref-bar{padding:12px 20px;background:var(--bg);border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;gap:12px}
.hz-rc-ref-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted)}
.hz-rc-ref-val{font-family:'Courier New',monospace;font-size:12px;color:var(--sub);font-weight:600}
.hz-rc-section{padding:16px 20px 4px}
.hz-rc-sec-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.hz-rc-sec-title svg{width:12px;height:12px;flex-shrink:0}
.hz-rc-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:13.5px}
.hz-rc-row:last-child{border-bottom:none;padding-bottom:2px}
.hz-rc-l{color:var(--sub)}.hz-rc-v{font-weight:600;color:var(--text);text-align:right}
.hz-rc-disc{color:#16a34a}
.hz-rc-sep{border:none;border-top:2px solid #111827;margin:6px 20px 0}
.hz-rc-total-row{display:flex;justify-content:space-between;align-items:center;padding:12px 20px 16px;font-size:15px;font-weight:700}
.hz-rc-total-lbl{color:var(--text)}
.hz-rc-total-val{font-size:19px;color:var(--p)}

/* ── INVOICE MODE ── */
.hz-inv-header{padding:24px 24px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px}
.hz-inv-brand-logo{height:40px;max-width:160px;object-fit:contain}
.hz-inv-meta{text-align:right}
.hz-inv-num{font-size:20px;font-weight:700;color:var(--text);letter-spacing:-.3px}
.hz-inv-date-row{display:flex;gap:24px;margin-top:10px;flex-wrap:wrap;justify-content:flex-end}
.hz-inv-date-lbl{font-size:10.5px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);font-weight:600;margin-bottom:2px}
.hz-inv-date-val{font-size:13.5px;font-weight:600;color:var(--text)}
.hz-inv-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.3px;margin-top:10px;border:1px solid}
.hz-from-to{display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:20px 24px;border-bottom:1px solid var(--line)}
@media(max-width:480px){.hz-from-to{grid-template-columns:1fr}}
.hz-addr-box{background:var(--bg);border-radius:var(--r2);padding:16px}
.hz-addr-lbl{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.hz-addr-lbl i{font-size:11px;color:var(--p)}
.hz-addr-name{font-size:14.5px;font-weight:700;color:var(--text);margin-bottom:4px}
.hz-addr-line{font-size:13px;color:var(--sub);line-height:1.6}
.hz-table-wrap{padding:20px 24px;border-bottom:1px solid var(--line)}
.hz-table{width:100%;border-collapse:collapse}
.hz-table thead th{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);padding:8px 10px;border-bottom:2px solid var(--line);background:var(--bg)}
.hz-table tbody td{padding:13px 10px;border-bottom:1px solid #f3f4f6;font-size:13.5px;vertical-align:middle}
.hz-table tbody tr:last-child td{border-bottom:none}
.hz-table tbody tr:hover td{background:#fafafa}
.hz-table .tc{text-align:center}.hz-table .tr{text-align:right}
.hz-summary-area{display:grid;grid-template-columns:1fr auto;gap:24px;padding:20px 24px;border-bottom:1px solid var(--line)}
@media(max-width:520px){.hz-summary-area{grid-template-columns:1fr}}
.hz-note-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);margin-bottom:8px}
.hz-note-text{font-size:13.5px;color:var(--sub);line-height:1.6}
.hz-totals{min-width:200px}
.hz-tot-row{display:flex;justify-content:space-between;gap:20px;padding:7px 0;font-size:13.5px;border-bottom:1px solid #f3f4f6}
.hz-tot-row:last-child{border-bottom:none}
.hz-tot-row .lbl{color:var(--sub)}.hz-tot-row .val{font-weight:600;color:var(--text)}
.hz-tot-sep{border:none;border-top:2px solid #111827;margin:6px 0 4px}
.hz-tot-total .lbl{font-weight:700;color:var(--text)}
.hz-tot-total .val{font-weight:700;font-size:15px}

/* Shared action buttons */
.hz-actions{padding:14px 20px 16px;display:flex;gap:9px;flex-wrap:wrap;border-top:1px solid var(--line)}
.hz-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:10px 16px;border-radius:var(--r);font-size:13.5px;font-weight:600;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:opacity 140ms;flex:1;min-width:130px}
.hz-btn:hover{opacity:.88;color:inherit}
.hz-btn-primary{background:var(--p);color:var(--p-tx)}
.hz-btn-secondary{background:var(--bg);color:var(--text);border:1px solid var(--line)}
.hz-btn i{font-size:12.5px}

.hz-inv-footer-addr{padding:14px 24px 16px;border-top:1px solid var(--line);text-align:center;font-size:12px;color:var(--muted)}

/* Page footer */
.hz-footer{text-align:center;margin-top:14px;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px}
.hz-footer-links{margin-top:32px;padding-bottom:8px;font-size:11.5px;color:var(--muted);text-align:center}
.hz-footer-links a,.hz-footer a{color:var(--muted);text-decoration:none;transition:color 130ms}
.hz-footer-links a:hover,.hz-footer a:hover{color:var(--p)}

/* Invoice form inputs */
.form-control,.form-select{color:var(--text) !important;background-color:var(--card) !important;border-color:var(--line) !important;font-family:'DM Sans',sans-serif !important}
.form-control:focus,.form-select:focus{border-color:var(--p) !important;box-shadow:0 0 0 3px var(--p-lt) !important}
.form-label{color:var(--text) !important;font-size:13.5px !important;font-weight:600 !important;margin-bottom:5px !important}
.text-danger{color:#dc2626 !important}

/* Toast override */
#toast-container{top:16px !important;right:16px !important}
.custom-toast{font-family:'DM Sans',sans-serif !important;border:1px solid var(--line) !important;border-radius:var(--r2) !important;background:var(--card) !important;box-shadow:0 4px 20px rgba(0,0,0,.12) !important;min-width:260px;max-width:320px}
.custom-toast [style*="padding: calc(.25rem * 4)"]{padding:12px 14px !important;gap:0 !important}
.custom-toast [style*="margin-left: 30px"]{margin-left:0 !important;font-size:12.5px;color:var(--sub);margin-top:4px;line-height:1.5}
.custom-toast [style*="font-weight: 500"]{font-size:13.5px;font-weight:600 !important;color:var(--text) !important}
.custom-toast .btn-close{width:20px;height:20px;padding:0;opacity:.4;flex-shrink:0}
.custom-toast .btn-close:hover{opacity:.8}
.custom-toast .toast-svg svg{width:16px;height:16px}

@media print{.hz-actions,.hz-footer,.hz-footer-links,.hz-rc-back-bar,.no-print{display:none!important} .hz-page{padding:0} .hz-card{box-shadow:none;border:none}}
</style>
</head>
<body style="<?php echo $bgStyle; ?>">
<div class="hz-page"><div class="<?php echo $hz_mode==='invoice' ? 'hz-wrap-wide' : 'hz-wrap-narrow'; ?>">

<div class="hz-brand">
    <img src="<?php echo $data['brand']['favicon']; ?>" alt="">
    <span class="hz-brand-name"><?php echo htmlspecialchars($data['brand']['name']); ?></span>
</div>

<div class="hz-card">

<!-- Back button — inside card, top-left -->
<div class="hz-rc-back-bar no-print">
    <button type="button" class="hz-rc-back-btn" onclick="history.back()">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l14 0"/><path d="M5 12l6 6"/><path d="M5 12l6 -6"/></svg>
        <?php echo $data['lang']['back']??'Back'; ?>
    </button>
    <?php if($hz_mode==='invoice'): ?>
    <button type="button" style="margin-left:auto;display:flex;align-items:center;gap:5px;font-size:12.5px;font-weight:500;color:var(--sub);background:none;border:none;cursor:pointer;padding:4px 6px;border-radius:5px;font-family:inherit;transition:color 130ms" onclick="window.print()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2"/><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4"/><path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z"/></svg>
        <?php echo $data['lang']['print']??'Print'; ?>
    </button>
    <?php endif; ?>
</div>

<?php if($hz_mode === 'receipt'): ?>

<!-- ═══════════════════════════════
     RECEIPT MODE
═══════════════════════════════ -->
<div class="hz-rc-hero">
    <div class="hz-rc-icon" style="background:<?php echo $st['bg']; ?>;border-color:<?php echo $st['border']; ?>">
        <i class="fa-solid <?php echo $st['icon']; ?>" style="color:<?php echo $st['color']; ?>"></i>
    </div>
    <div class="hz-rc-title"><?php echo $data['lang']['payment_receipt']??'Payment Receipt'; ?></div>
    <div>
        <span class="hz-rc-amount"><?php echo $payable; ?></span>
        <span class="hz-rc-cur"><?php echo $cur; ?></span>
    </div>
    <div>
        <span class="hz-rc-status-badge" style="background:<?php echo $st['bg']; ?>;color:<?php echo $st['color']; ?>;border-color:<?php echo $st['border']; ?>">
            <i class="fa-solid <?php echo $st['icon']; ?>"></i>
            <?php echo $st['label']; ?>
        </span>
    </div>
</div>

<div class="hz-rc-ref-bar">
    <span class="hz-rc-ref-lbl"><?php echo $data['lang']['receipt_id']??'Receipt ID'; ?></span>
    <span class="hz-rc-ref-val"><?php echo htmlspecialchars($ref); ?></span>
</div>

<div class="hz-rc-section">
    <div class="hz-rc-sec-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2"/></svg>
        <?php echo $data['lang']['transaction_details']??'Transaction Details'; ?>
    </div>
    <div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['payment_method']??'Payment Method'; ?></span><span class="hz-rc-v"><?php echo htmlspecialchars($method); ?></span></div>
    <div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['amount']??'Amount'; ?></span><span class="hz-rc-v"><?php echo money_round($amt,2).' '.$cur; ?></span></div>
    <?php if((float)$disc>0): ?>
    <div class="hz-rc-row"><span class="hz-rc-l">Discount</span><span class="hz-rc-v hz-rc-disc">−<?php echo money_round($disc,2).' '.$cur; ?></span></div>
    <?php endif; ?>
    <div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['processing_fee']??'Processing Fee'; ?></span><span class="hz-rc-v"><?php echo money_round($fee,2).' '.$cur; ?></span></div>
    <?php if($lcur && $lcur !== $cur): ?>
    <div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['local_amount']??'Local Amount'; ?></span><span class="hz-rc-v"><?php echo money_round($lamt,2).' '.$lcur; ?></span></div>
    <?php endif; ?>
</div>

<hr class="hz-rc-sep">
<div class="hz-rc-total-row">
    <span class="hz-rc-total-lbl"><?php echo $data['lang']['total_paid']??'Total Paid'; ?></span>
    <span class="hz-rc-total-val"><?php echo $payable.' '.$cur; ?></span>
</div>

<?php if($cname || $cemail || $cmobile): ?>
<div class="hz-rc-section" style="border-top:1px solid var(--line);padding-top:16px">
    <div class="hz-rc-sec-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>
        <?php echo $data['lang']['customer']??'Customer'; ?>
    </div>
    <?php if($cname): ?><div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['full_name']??'Name'; ?></span><span class="hz-rc-v"><?php echo htmlspecialchars($cname); ?></span></div><?php endif; ?>
    <?php if($cemail): ?><div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['email_address']??'Email'; ?></span><span class="hz-rc-v" style="font-size:13px"><?php echo htmlspecialchars($cemail); ?></span></div><?php endif; ?>
    <?php if($cmobile): ?><div class="hz-rc-row"><span class="hz-rc-l"><?php echo $data['lang']['mobile_number']??'Mobile'; ?></span><span class="hz-rc-v"><?php echo htmlspecialchars($cmobile); ?></span></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="hz-actions">
    <a href="<?php echo pp_checkout_address(); ?>?receipt" class="hz-btn hz-btn-secondary">
        <i class="fa-solid fa-download"></i> <?php echo $data['lang']['download_pdf']??'Download PDF'; ?>
    </a>
    <?php if($hasRet): ?>
    <a href="<?php echo htmlspecialchars($ret); ?>" class="hz-btn hz-btn-primary">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> <?php echo $data['lang']['go_to_site']??'Return to Merchant'; ?>
    </a>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- ═══════════════════════════════
     INVOICE MODE
═══════════════════════════════ -->
<div class="hz-inv-header">
    <div><img class="hz-inv-brand-logo" src="<?php echo $data['brand']['logo']; ?>" alt="<?php echo htmlspecialchars($data['brand']['name']); ?>"></div>
    <div class="hz-inv-meta">
        <div class="hz-inv-num"># <?php echo htmlspecialchars($data['invoice']['invoice_id']??$data['invoice']['ref']??''); ?></div>
        <div class="hz-inv-date-row">
            <div>
                <div class="hz-inv-date-lbl"><?php echo $data['lang']['receipt_date']??'Receipt Date'; ?></div>
                <div class="hz-inv-date-val"><?php echo $data['invoice']['created_date']; ?></div>
            </div>
            <div>
                <div class="hz-inv-date-lbl">Due Date</div>
                <div class="hz-inv-date-val"><?php echo $data['invoice']['due_date']; ?></div>
            </div>
            <?php if($inv_status==='paid' && !empty($data['invoice']['gateway'])): ?>
            <div>
                <div class="hz-inv-date-lbl">Method</div>
                <div class="hz-inv-date-val"><?php echo htmlspecialchars($data['invoice']['gateway']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php if($inv_status==='paid'): ?>
        <div class="hz-inv-badge" style="background:#f0fdf4;color:#16a34a;border-color:#bbf7d0"><i class="fa-solid fa-circle-check"></i> Paid</div>
        <?php else: ?>
        <div class="hz-inv-badge" style="background:#fff1f2;color:#dc2626;border-color:#fecaca"><i class="fa-solid fa-clock"></i> <?php echo ucfirst($inv_status); ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="hz-from-to">
    <div class="hz-addr-box">
        <div class="hz-addr-lbl"><i class="fa-solid fa-building"></i> From</div>
        <div class="hz-addr-name"><?php echo htmlspecialchars($data['brand']['name']); ?></div>
        <div class="hz-addr-line">
            <?php if(!empty($data['brand']['support']['email'])&&$data['brand']['support']['email']!=='--'): ?><i class="fa-solid fa-envelope" style="font-size:11px;margin-right:4px"></i><?php echo htmlspecialchars($data['brand']['support']['email']); ?><br><?php endif; ?>
            <?php if(!empty($data['brand']['support']['phone'])&&$data['brand']['support']['phone']!=='--'): ?><i class="fa-solid fa-phone" style="font-size:11px;margin-right:4px"></i><?php echo htmlspecialchars($data['brand']['support']['phone']); ?><?php endif; ?>
        </div>
    </div>
    <div class="hz-addr-box">
        <div class="hz-addr-lbl"><i class="fa-solid fa-user"></i> To</div>
        <div class="hz-addr-name"><?php echo htmlspecialchars($data['invoice']['customer']['name']??''); ?></div>
        <div class="hz-addr-line">
            <?php if(!empty($data['invoice']['customer']['email'])): ?><i class="fa-solid fa-envelope" style="font-size:11px;margin-right:4px"></i><?php echo htmlspecialchars($data['invoice']['customer']['email']); ?><br><?php endif; ?>
            <?php if(!empty($data['invoice']['customer']['mobile'])): ?><i class="fa-solid fa-phone" style="font-size:11px;margin-right:4px"></i><?php echo htmlspecialchars($data['invoice']['customer']['mobile']); ?><?php endif; ?>
        </div>
    </div>
</div>

<div class="hz-table-wrap">
    <table class="hz-table">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th>Description</th>
                <th class="tc" style="width:10%">Qty</th>
                <th class="tr" style="width:18%">Unit Price</th>
                <th class="tr" style="width:18%">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $subtotal=0;$totalDiscount=0;$totalVAT=0;$grandTotal=0;$counter=1;
        if(!empty($data['items'])):
            foreach($data['items'] as &$item):
                $rowBefore=$item['unitPrice']*$item['quantity'];
                $discAmt=$item['discount']??0;
                $afterDisc=$rowBefore-$discAmt;
                $vatAmt=$afterDisc*($item['vat']/100);
                $item['total']=$afterDisc+$vatAmt;
                $subtotal+=$rowBefore;$totalDiscount+=$discAmt;$totalVAT+=$vatAmt;$grandTotal+=$item['total'];
        ?>
            <tr>
                <td class="tc" style="color:var(--muted)"><?php echo $counter++; ?></td>
                <td><div style="font-weight:500"><?php echo htmlspecialchars($item['description']); ?></div></td>
                <td class="tc"><?php echo htmlspecialchars($item['quantity']); ?></td>
                <td class="tr"><?php echo money_round($item['unitPrice'],2).$inv_currency; ?></td>
                <td class="tr" style="font-weight:600"><?php echo money_round($item['total'],2).$inv_currency; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<div class="hz-summary-area">
    <div>
        <div class="hz-note-label">Note</div>
        <div class="hz-note-text"><?php echo nl2br(htmlspecialchars($data['invoice']['note']??'')); ?></div>
    </div>
    <div class="hz-totals">
        <div class="hz-tot-row"><span class="lbl">Subtotal</span><span class="val"><?php echo money_round($subtotal,2).$inv_currency; ?></span></div>
        <?php if((float)$totalDiscount>0): ?>
        <div class="hz-tot-row"><span class="lbl">Discount</span><span class="val" style="color:#16a34a">−<?php echo money_round($totalDiscount,2).$inv_currency; ?></span></div>
        <?php endif; ?>
        <?php if((float)$totalVAT>0): ?>
        <div class="hz-tot-row"><span class="lbl">Tax / VAT</span><span class="val"><?php echo money_round($totalVAT,2).$inv_currency; ?></span></div>
        <?php endif; ?>
        <?php if(!empty($data['invoice']['shippingFee'])&&(float)$data['invoice']['shippingFee']>0): ?>
        <div class="hz-tot-row"><span class="lbl">Shipping</span><span class="val"><?php echo money_round($data['invoice']['shippingFee'],2).$inv_currency; ?></span></div>
        <?php endif; ?>
        <hr class="hz-tot-sep">
        <div class="hz-tot-row hz-tot-total">
            <?php $finalTotal=$grandTotal+($data['invoice']['shippingFee']??0); ?>
            <?php if($inv_status==='paid'): ?>
            <span class="lbl">Total</span><span class="val" style="color:#16a34a"><?php echo money_round($finalTotal,2).$inv_currency; ?></span>
            <?php else: ?>
            <span class="lbl">Amount Due</span><span class="val" style="color:#dc2626"><?php echo money_round($finalTotal,2).$inv_currency; ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="hz-actions no-print">
    <button type="button" class="hz-btn hz-btn-secondary" onclick="window.print()">
        <i class="fa-solid fa-print"></i> <?php echo $data['lang']['print_receipt']??'Print Receipt'; ?>
    </button>
    <?php if(($inv_status??'')!=='paid'): ?>
    <div style="flex:1;min-width:140px">
        <form action="" method="POST" id="form" enctype="multipart/form-data">
            <?php pp_renderFormFields('invoice', $data); ?>
            <button id="payButton" class="hz-btn hz-btn-primary" style="width:100%">
                <i class="fa-solid fa-lock"></i> <?php echo $data['lang']['pay_now']??'Pay Now'; ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="hz-inv-footer-addr">
    <?php echo htmlspecialchars($data['brand']['name']); ?>
    <?php $a=$data['brand']['address']??[]; if(!empty($a['street'])&&$a['street']!=='--') echo ' &bull; '.htmlspecialchars($a['street'].', '.$a['city'].' '.$a['postal'].', '.$a['country']); ?>
</div>

<?php endif; ?>
</div><!-- .hz-card -->
</div><!-- /.hz-wrap -->

<?php require __DIR__ . '/inc/footer.php'; ?>

</div><!-- /.hz-page -->

<?php echo pp_assets('footer'); ?>
<script>
<?php if($hz_mode==='invoice' && ($inv_status??'')!=='paid'): ?>
$(document).ready(function(){
    $('#form').on('submit',function(e){
        e.preventDefault();
        var pb=document.getElementById('payButton');
        if(pb) pb.innerHTML='<div class="spinner-border spinner-border-sm" role="status"></div>';
        $.ajax({url:'<?php echo pp_site_address(); ?>',type:'POST',dataType:'json',data:$(this).serialize(),
            success:function(r){
                if(pb) pb.innerHTML='<i class="fa-solid fa-lock"></i> <?php echo $data['lang']['pay_now']??'Pay Now'; ?>';
                if(r.status==="true") location.href=r.redirect;
                else createToast({title:r.title,description:r.message,svg:'',timeout:6000});
            },
            error:function(){if(pb) pb.innerHTML='<i class="fa-solid fa-lock"></i> <?php echo $data['lang']['pay_now']??'Pay Now'; ?>';createToast({title:'Error',description:'Something went wrong.',svg:'',timeout:6000});}
        });
    });
});
<?php endif; ?>
</script>
</body>
</html>
