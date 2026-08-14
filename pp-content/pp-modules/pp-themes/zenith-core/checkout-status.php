<?php
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }
if (isset($_GET['receipt'])) {
    /* FPDF's Image() can't reliably fetch HTTPS URLs (allow_url_fopen, SSL issues).
       Resolve the brand logo to a local filesystem path if it lives in pp-media/storage. */
    $logoUrl = $data['brand']['logo'] ?? '';
    if (!empty($logoUrl)) {
        $parsedPath = parse_url($logoUrl, PHP_URL_PATH) ?? '';
        if (strpos($parsedPath, 'pp-media/storage/') !== false) {
            $filename  = basename($parsedPath);
            $localPath = __DIR__ . '/../../pp-media/storage/' . $filename;
            if (file_exists($localPath)) {
                $data['brand']['logo'] = $localPath;
            } else {
                $data['brand']['logo'] = ''; /* skip logo rather than crash */
            }
        }
    }
    pp_downloadReceiptPDF($data);
    exit;
}

$hz_primary  = $data['options']['primary_color'] ?? '#1a56db';
$hz_text_col = $data['options']['text_color']    ?? '#FFFFFF';
$anCode      = $data['options']['analytics_code'] ?? '';

/* Minimal lang setup needed for footer.php include */
$hz_avail_langs_raw = $data['options']['available_languages'] ?? 'en,bn,hi,ur,ar';
$hz_avail_langs     = array_filter(array_map('trim', explode(',', $hz_avail_langs_raw)));
require_once __DIR__ . '/inc/lang.php';

$bgStyle = 'background-color:#eef0f5;';
if (!empty($data['options']['enable_bg_image']) && $data['options']['enable_bg_image']==='enabled' && !empty($data['options']['background_image']))
    $bgStyle = "background-image:url('" . htmlspecialchars($data['options']['background_image'], ENT_QUOTES) . "');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;";

$status = strtolower($data['transaction']['status'] ?? 'pending');
$ret    = $data['transaction']['return_url'] ?? '';
$hasRet = ($ret && $ret !== '--');
$hz_auto_redir  = ($data['options']['redirect_after_payment'] ?? 'enabled') === 'enabled';
$hz_redir_delay = (int)($data['options']['redirect_delay'] ?? 5);
// 0 seconds = auto-redirect disabled (regardless of the toggle above)
if ($hz_redir_delay <= 0) { $hz_auto_redir = false; $hz_redir_delay = 0; }
$should_redir   = $hz_auto_redir && $hasRet && in_array($status, ['completed','canceled','failed','expired']);

$amt     = $data['transaction']['amount']          ?? 0;
$fee     = $data['transaction']['processing_fee']  ?? 0;
$disc    = $data['transaction']['discount_amount'] ?? 0;
$payable = number_format((float)$amt - (float)$disc + (float)$fee, 2);
$cur     = $data['transaction']['currency']        ?? '';
$lcur    = $data['transaction']['local_currency']  ?? $cur;
$lamt    = $data['transaction']['local_net_amount']?? 0;
$method  = $data['transaction']['payment_method']  ?? 'N/A';
$ref     = $data['transaction']['ref']             ?? '';

$statusMap = [
    'completed' => ['label'=>$data['lang']['payment_successful']??'Payment Successful','icon'=>'fa-circle-check',        'color'=>'#16a34a','bg'=>'#f0fdf4','border'=>'#bbf7d0'],
    'pending'   => ['label'=>$data['lang']['payment_pending']??'Payment Pending',      'icon'=>'fa-clock',               'color'=>'#d97706','bg'=>'#fffbeb','border'=>'#fde68a'],
    'initiated' => ['label'=>$data['lang']['payment_initiated']??'Payment Initiated',   'icon'=>'fa-circle-notch',        'color'=>'#2563eb','bg'=>'#eff6ff','border'=>'#bfdbfe'],
    'expired'   => ['label'=>$data['lang']['payment_expired']??'Payment Expired',       'icon'=>'fa-hourglass-end',       'color'=>'#d97706','bg'=>'#fffbeb','border'=>'#fde68a'],
    'refunded'  => ['label'=>$data['lang']['payment_refunded']??'Payment Refunded',    'icon'=>'fa-rotate-left',         'color'=>'#0369a1','bg'=>'#eff6ff','border'=>'#bfdbfe'],
    'canceled'  => ['label'=>$data['lang']['payment_canceled']??'Payment Cancelled',   'icon'=>'fa-circle-xmark',        'color'=>'#dc2626','bg'=>'#fff1f2','border'=>'#fecaca'],
    'failed'    => ['label'=>$data['lang']['payment_failed']??'Payment Failed',                                          'icon'=>'fa-triangle-exclamation','color'=>'#dc2626','bg'=>'#fff1f2','border'=>'#fecaca'],
];
$st = $statusMap[$status] ?? $statusMap['pending'];

$statusMsg = [
    'completed' => $data['lang']['change_status_completed'] ?? 'Your payment has been received and confirmed successfully.',
    'pending'   => $data['lang']['change_status_pending']   ?? 'Your payment is currently being processed. Please allow a moment for confirmation.',
    'initiated' => $data['lang']['change_status_initiated']  ?? 'Your payment has been initiated and is awaiting action.',
    'expired'   => $data['lang']['change_status_expired']    ?? 'This payment session has expired. Please start a new payment request.',
    'refunded'  => $data['lang']['change_status_refunded']  ?? 'Your payment has been successfully refunded to your original payment method.',
    'canceled'  => $data['lang']['change_status_canceled']   ?? 'Your payment was cancelled. No amount has been charged to your account.',
    'failed'    => $data['lang']['change_status_failed']  ?? 'Your payment could not be completed. Please try again or contact the merchant for assistance.',
];
$msg = $statusMsg[$status] ?? '';

// Determine if we should show transaction details
$showDetails = in_array($status, ['completed', 'pending', 'refunded', 'initiated', 'expired']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($hz_current_lang); ?>" dir="<?php echo in_array($hz_current_lang,['ar','ur']) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo htmlspecialchars($st['label']); ?> — <?php echo htmlspecialchars($data['brand']['name']); ?></title>
<link rel="shortcut icon" href="<?php echo $data['brand']['favicon']; ?>">
<?php if($anCode&&$anCode!=='--') echo $anCode; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
<?php echo pp_assets('head'); ?>
<style>
:root{--p:<?php echo $hz_primary; ?>;--p-lt:<?php echo pp_hexToRgba($hz_primary,0.09); ?>;--p-tx:<?php echo $hz_text_col; ?>;--text:#111827;--sub:#6b7280;--muted:#9ca3af;--line:#e5e7eb;--bg:#f5f6f9;--card:#ffffff;--r:6px;--r2:10px;--r3:13px;--sh:0 1px 3px rgba(0,0,0,.07),0 4px 14px rgba(0,0,0,.05)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:15px;color:var(--text);-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
.hz-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:26px 15px 52px}
.hz-wrap{width:100%;max-width:520px;display:flex;flex-direction:column;flex:1 0 auto}
.hz-brand{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:0 2px;min-width:0}
.hz-brand img{width:34px;height:34px;border-radius:7px;object-fit:cover;border:1px solid var(--line);flex-shrink:0}
.hz-brand-name{font-size:15px;font-weight:600;color:var(--text)}
.hz-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);overflow:hidden;box-shadow:var(--sh)}

/* Status hero */
.hz-status-hero{padding:32px 24px 24px;text-align:center;border-bottom:1px solid var(--line);display:flex;flex-direction:column;align-items:center}
.hz-status-icon{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 0 16px;border:2px solid;flex-shrink:0}
.hz-status-icon i{font-size:26px}
.hz-status-title{font-size:20px;font-weight:700;letter-spacing:-.3px;margin-bottom:6px}
.hz-status-msg{font-size:14px;color:var(--sub);line-height:1.6}

/* Auto-redirect banner - enhanced */
.hz-redir-banner{display:flex;align-items:center;justify-content:center;gap:12px;padding:16px 20px;font-size:14px;font-weight:500;border-bottom:1px solid var(--line);background:<?php echo $st['bg']; ?>;border-radius:0;text-align:center;flex-wrap:wrap}
.hz-redir-banner i{
    font-size:16px;
    color:<?php echo $st['color']; ?>;
    flex-shrink:0;
}
.hz-redir-banner span{
    color:<?php echo $st['color']; ?>;
    display:inline-flex;
    align-items:center;
    gap:5px;
}
.hz-redir-banner .hz-countdown-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #ffffff;
    color: <?php echo $st['color']; ?>;
    border: 2px solid <?php echo $st['color']; ?>;
    font-size: 15px;
    font-weight: 800;
    margin-left: 4px;
    flex-shrink: 0;
    letter-spacing: -.5px;
    box-shadow: 0 0 0 3px <?php echo $st['bg']; ?>;
}

/* Transaction details */
.hz-detail-section{padding:18px 20px 4px}
.hz-detail-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);margin-bottom:14px;display:flex;align-items:center;gap:6px;flex-wrap:nowrap}
.hz-detail-title svg{width:13px;height:13px;color:var(--text)}
.hz-drow{display:flex;justify-content:space-between;align-items:flex-start;padding:9px 0;border-bottom:1px solid #f3f4f6;font-size:13.5px;gap:12px}
.hz-drow:last-child{border-bottom:none}
.hz-drow-l{color:var(--sub);flex-shrink:0}.hz-drow-v{font-weight:600;color:var(--text);text-align:right;word-break:break-word}
.hz-dsep{border:none;border-top:2px solid #111827;margin:4px 0}
.hz-drow.total{padding-top:12px;padding-bottom:4px}
.hz-drow.total .hz-drow-l{font-weight:700;color:var(--text);font-size:14px}
.hz-drow.total .hz-drow-v{font-size:17px;font-weight:700}

/* Status badge */
.hz-status-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:700}

/* Action buttons */
.hz-actions{padding:16px 20px 4px;display:flex;gap:10px;flex-wrap:wrap;align-items:stretch}
.hz-btn{display:flex;align-items:center;justify-content:center;gap:8px;padding:12px 18px;border-radius:var(--r);font-size:14px;font-weight:600;cursor:pointer;flex:1;min-width:140px;text-decoration:none;border:none;font-family:inherit;transition:opacity 140ms;letter-spacing:-.1px}
.hz-btn:hover{opacity:.88}
.hz-btn-primary{background:var(--p);color:var(--p-tx)}
.hz-btn-secondary{background:var(--bg);color:var(--text);border:1px solid var(--line)}
.hz-btn i{font-size:14px}
.hz-card-foot{padding:10px 20px 16px;display:flex;align-items:center;justify-content:flex-start;gap:8px}
.hz-view-receipt-btn{display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:600;color:var(--sub);text-decoration:none;transition:color 130ms}
.hz-view-receipt-btn:hover{color:var(--p)}
.hz-view-receipt-btn svg{flex-shrink:0}

a{text-decoration:none;color:inherit}
.hz-footer{text-align:center;margin-top:14px;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;width:100%}
.hz-footer-links{margin-top:32px;padding-bottom:8px;font-size:11.5px;color:var(--muted);text-align:center}
.hz-footer-links a{color:var(--muted);text-decoration:none;transition:color 130ms}
.hz-footer-links a:hover{color:var(--p)}
/* Override Tabler/Bootstrap violet link/focus colors with theme primary */
::selection{background:var(--p-lt);color:var(--text)}
*:focus-visible{outline:2px solid var(--p);outline-offset:2px}
a:hover{color:var(--p)}
:root{--tblr-primary:<?php echo $hz_primary; ?>;--tblr-link-color:<?php echo $hz_primary; ?>;--bs-link-color:<?php echo $hz_primary; ?>}
/* Toast override — compact & theme-matching */
#toast-container{top:16px !important;right:16px !important}
.custom-toast{font-family:'DM Sans',sans-serif !important;border:1px solid var(--line) !important;border-radius:var(--r2) !important;background:var(--card) !important;box-shadow:0 4px 20px rgba(0,0,0,.12) !important;min-width:260px;max-width:320px}
.custom-toast [style*="padding: calc(.25rem * 4)"]{padding:12px 14px !important;gap:0 !important}
.custom-toast [style*="margin-left: 30px"]{margin-left:0 !important;font-size:12.5px;color:var(--sub);margin-top:4px;line-height:1.5}
.custom-toast [style*="font-weight: 500"]{font-size:13.5px;font-weight:600 !important;color:var(--text) !important}
.custom-toast .btn-close{width:20px;height:20px;padding:0;opacity:.4;flex-shrink:0}
.custom-toast .btn-close:hover{opacity:.8}
.custom-toast .toast-svg svg{width:16px;height:16px}
</style>
</head>
<body style="<?php echo $bgStyle; ?>">
<div class="hz-page"><div class="hz-wrap">

<div class="hz-brand">
    <img src="<?php echo $data['brand']['favicon']; ?>" alt="">
    <span class="hz-brand-name"><?php echo htmlspecialchars($data['brand']['name']); ?></span>
</div>

<div class="hz-card">

    <!-- Status hero -->
    <div class="hz-status-hero">
        <div class="hz-status-icon" style="background:<?php echo $st['bg']; ?>;border-color:<?php echo $st['border']; ?>">
            <i class="fa-solid <?php echo $st['icon']; ?>" style="color:<?php echo $st['color']; ?>"></i>
        </div>
        <div class="hz-status-title" style="color:<?php echo $st['color']; ?>"><?php echo $st['label']; ?></div>
        <div class="hz-status-msg"><?php echo htmlspecialchars($msg); ?></div>
    </div>

    <?php if($should_redir): ?>
    <!-- Enhanced auto-redirect countdown banner -->
    <div class="hz-redir-banner">
        <i class="fa-solid fa-rotate-right fa-spin"></i>
        <span>
            <?php echo $data['lang']['redirecting_in']??'Redirecting to merchant in'; ?>
            <span class="hz-countdown-badge" id="hz-countdown"><?php echo $hz_redir_delay; ?></span>
            <?php echo $data['lang']['seconds']??'seconds'; ?>
        </span>
    </div>
    <?php endif; ?>

    <?php if($showDetails): ?>
    <!-- Transaction details (only for pending/refunded) -->
    <div class="hz-detail-section">
        <div class="hz-detail-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/><path d="M9 13l6 0"/><path d="M9 17l6 0"/></svg>
            <?php echo $data['lang']['transaction_details']??'Transaction Details'; ?>
        </div>

        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['receipt_id']??'Receipt ID'; ?></span><span class="hz-drow-v" style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($ref); ?></span></div>
        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['payment_method']??'Payment Method'; ?></span><span class="hz-drow-v"><?php echo htmlspecialchars($method); ?></span></div>
        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['amount']??'Amount'; ?></span><span class="hz-drow-v"><?php echo money_round($amt,2).' '.$cur; ?></span></div>
        <?php if((float)$disc>0): ?><div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['discount']??'Discount'; ?></span><span class="hz-drow-v" style="color:#16a34a">−<?php echo money_round($disc,2).' '.$cur; ?></span></div><?php endif; ?>
        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['processing_fee']??'Processing Fee'; ?></span><span class="hz-drow-v"><?php echo money_round($fee,2).' '.$cur; ?></span></div>
        <?php if($lcur && $lcur!==$cur): ?>
        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['local_amount']??'Local Amount'; ?></span><span class="hz-drow-v"><?php echo money_round($lamt,2).' '.$lcur; ?></span></div>
        <?php endif; ?>
        <hr class="hz-dsep">
        <div class="hz-drow total"><span class="hz-drow-l"><?php echo $data['lang']['total_paid']??'Total Paid'; ?></span><span class="hz-drow-v" style="color:<?php echo $status==='completed'?'#16a34a':'var(--p)'; ?>"><?php echo $payable.' '.$cur; ?></span></div>
    </div>
    <?php endif; ?>

    <?php if(in_array($status, ['canceled','failed'])): ?>
    <!-- For cancelled/failed, show minimal details (already handled above, but we need to show the list) -->
    <div class="hz-detail-section">
        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['receipt_id']??'Receipt ID'; ?></span><span class="hz-drow-v" style="font-family:monospace;font-size:12px"><?php echo htmlspecialchars($ref); ?></span></div>
        <div class="hz-drow"><span class="hz-drow-l"><?php echo $data['lang']['amount']??'Amount'; ?></span><span class="hz-drow-v"><?php echo $payable.' '.$cur; ?></span></div>
        <div class="hz-drow">
            <span class="hz-drow-l"><?php echo $data['lang']['status_label']??'Status'; ?></span>
            <span class="hz-drow-v">
                <span class="hz-status-badge" style="background:<?php echo $st['bg']; ?>;color:<?php echo $st['color']; ?>;border:1px solid <?php echo $st['border']; ?>">
                    <i class="fa-solid <?php echo $st['icon']; ?>"></i>
                    <?php echo ucfirst($status); ?>
                </span>
            </span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action buttons -->
    <div class="hz-actions">
        <?php if(!in_array($status, ['canceled','failed','expired','initiated'])): ?>
        <a href="<?php echo pp_checkout_address(); ?>?receipt" class="hz-btn hz-btn-secondary">
            <i class="fa-solid fa-download"></i> <?php echo $data['lang']['download_receipt']??'Download Receipt'; ?>
        </a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($hasRet ? $ret : pp_checkout_address()); ?>" class="hz-btn hz-btn-primary">
            <i class="fa-solid fa-arrow-up-right-from-square"></i>
            <?php echo $data['lang']['go_to_site']??'Return to Merchant'; ?>
        </a>
    </div>

    <!-- View Full Receipt text link — bottom-left -->
    <div class="hz-card-foot">
        <a href="<?php echo pp_checkout_address(); ?>?view_receipt" class="hz-view-receipt-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 5h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-3a2 2 0 0 0 0 -4v-3a2 2 0 0 1 2 -2"/></svg>
            View Full Receipt
        </a>
    </div>

</div>

<?php require __DIR__ . '/inc/footer.php'; ?>

<?php echo pp_assets('footer'); ?>
<?php if($should_redir): ?>
<script data-cfasync="false">
(function(){
    var url=<?php echo json_encode($ret); ?>,n=<?php echo $hz_redir_delay; ?>,el=document.getElementById('hz-countdown');
    var t=setInterval(function(){n--;if(el)el.textContent=n;if(n<=0){clearInterval(t);window.location.replace(url);}},1000);
})();
</script>
<?php endif; ?>
</body></html>