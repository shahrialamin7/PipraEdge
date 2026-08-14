<?php
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

if (isset($_GET['lang']) && $_GET['lang'] !== '') {
    pp_set_lang($_GET['lang']);
    $params = array_filter($_GET, fn($k) => $k !== 'lang', ARRAY_FILTER_USE_KEY);
    $selfUrl = strtok($_SERVER['REQUEST_URI'], '?');
    if ($params) $selfUrl .= '?' . http_build_query($params);
    header('Location: ' . $selfUrl, true, 302); exit();
}

$hz_primary      = $data['options']['primary_color'] ?? '#1a56db';
$hz_text_col     = $data['options']['text_color']    ?? '#FFFFFF';
$anCode          = $data['options']['analytics_code'] ?? '';

$bgStyle = 'background-color:#eef0f5;';
if (!empty($data['options']['enable_bg_image']) && $data['options']['enable_bg_image']==='enabled' && !empty($data['options']['background_image']))
    $bgStyle = "background-image:url('" . htmlspecialchars($data['options']['background_image'], ENT_QUOTES) . "');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;";

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

/* ── Shared lang dictionary ── */
require_once __DIR__ . '/inc/lang.php';
$hz_cur_lang_label  = $hz_all_lang_names[$_GET['lang'] ?? ''] ?? ($data['lang']['select_a_language'] ?? 'Select a language');

$pl_active    = ($data['paymentLink']['status'] ?? '') === 'active';
$pl_title     = $data['paymentLink']['product']['title']       ?? '';
$pl_desc      = $data['paymentLink']['product']['description'] ?? '';
$pl_img       = $data['paymentLink']['product']['image']       ?? '';
$pl_show_img  = ($data['options']['pl_show_image']       ?? 'enabled') === 'enabled';
$pl_show_desc = ($data['options']['pl_show_description'] ?? 'enabled') === 'enabled';
$pl_btn_text  = trim($data['options']['pl_button_text'] ?? '') ?: ($data['lang']['pay_now'] ?? 'Pay Now');
$pl_notice    = trim($data['options']['pl_notice_text'] ?? '');

$SVG_LANG = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6.371c0 4.418 -2.239 6.629 -5 6.629"/><path d="M4 6.371h7"/><path d="M5 9c0 2.144 2.252 3.908 6 4"/><path d="M12 20l4 -9l4 9"/><path d="M19.1 18h-6.2"/><path d="M6.694 3l.793 .582"/></svg>';
$SVG_FOOTER = '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($hz_current_lang); ?>" dir="<?php echo in_array($hz_current_lang,['ar','ur']) ? 'rtl' : 'ltr'; ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title><?php echo htmlspecialchars($pl_title ?: ($data['lang']['payment_link']??'Payment Link')); ?> — <?php echo htmlspecialchars($data['brand']['name']); ?></title>
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

/* ── Force body text / Tabler variables ── */
body{color:#111827}
:root{--tblr-body-color:#111827;--tblr-body-bg:#ffffff;--tblr-font-size:15px}

/* ── Form: force all inputs visible ── */
.form-group{margin-bottom:14px}
.form-label,label,
.form-label *,label *{
    color:#111827 !important;
    font-size:13.5px !important;
    font-weight:600 !important;
    margin-bottom:5px !important;
    display:inline-block;
    font-family:'DM Sans',sans-serif !important;
}
.text-danger,.required-star{color:#dc2626 !important}
.form-control,.form-select,
input[type="text"],input[type="email"],input[type="tel"],input[type="number"],
input[type="password"],input[type="url"],select,textarea,
.iti input,.iti input[type=tel],.iti input[type=text]{
    color:#111827 !important;
    background-color:#ffffff !important;
    border-color:#e5e7eb !important;
    font-family:'DM Sans',sans-serif !important;
    font-size:14px !important;
    -webkit-text-fill-color:#111827 !important;
}
.form-control:focus,.form-select:focus,
input[type="text"]:focus,input[type="email"]:focus,input[type="tel"]:focus,
input[type="number"]:focus,select:focus,textarea:focus{
    border-color:var(--p) !important;
    box-shadow:0 0 0 3px var(--p-lt) !important;
    color:#111827 !important;
    -webkit-text-fill-color:#111827 !important;
    outline:none !important;
}
::placeholder{color:#9ca3af !important;opacity:1 !important;-webkit-text-fill-color:#9ca3af !important}
.mb-3{margin-bottom:14px !important}
/* intl-tel-input phone widget — full fix */
.iti{width:100%!important}
.iti__flag-container{z-index:2}
.iti__selected-dial-code,.iti__arrow{color:#111827 !important}
.iti__selected-flag{background:transparent!important}
.iti__country-list{background:#ffffff;color:#111827;border-color:#e5e7eb;box-shadow:var(--sh)}
.iti__country-list .iti__country-name,.iti__dial-code{color:#111827!important}
.iti__country-list .iti__country.iti__highlight{background:var(--p-lt)}

.hz-page{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:26px 15px 52px}
.hz-wrap{width:100%;max-width:480px;display:flex;flex-direction:column;flex:1 0 auto}
.hz-brand{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:0 2px;min-width:0}
.hz-brand img{width:34px;height:34px;border-radius:7px;object-fit:cover;border:1px solid var(--line);flex-shrink:0}
.hz-brand-name{font-size:15px;font-weight:600;color:var(--text)}

.hz-nav{display:flex;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:var(--r2);margin-bottom:11px;box-shadow:var(--sh);overflow:hidden;justify-content:flex-end;width:100%}
.hz-nav-pills{display:flex;align-items:center;padding:5px 6px;gap:2px}
.hz-nav-pill{display:flex;align-items:center;gap:6px;padding:0 11px;height:32px;border-radius:6px;font-size:13px;font-weight:500;color:var(--sub);white-space:nowrap;transition:color 130ms,background 130ms;cursor:pointer;background:none;border:none;font-family:inherit;flex-shrink:0}
.hz-nav-pill:hover{color:var(--text);background:var(--bg)}
.hz-nav-pill svg{width:14px;height:14px;flex-shrink:0}

.hz-card{background:var(--card);border:1px solid var(--line);border-radius:var(--r3);overflow:hidden;box-shadow:var(--sh)}

/* Product image */
.hz-pl-image-wrap{width:100%;overflow:hidden;border-bottom:1px solid var(--line);background:var(--bg)}
.hz-pl-image{width:100%;height:200px;object-fit:cover;display:block}
.hz-pl-vector{width:100%;display:block}

/* Product info */
.hz-pl-info{padding:18px 20px 14px;border-bottom:1px solid var(--line)}
.hz-pl-title{font-size:18px;font-weight:700;color:var(--text);letter-spacing:-.3px;line-height:1.3;margin-bottom:5px}
.hz-pl-desc{font-size:13.5px;color:var(--sub);line-height:1.6}

/* Inactive */
.hz-pl-inactive{padding:48px 28px;text-align:center;display:flex;flex-direction:column;align-items:center}
.hz-pl-inactive-icon{width:64px;height:64px;border-radius:50%;background:#fff1f2;border:2px solid #fecaca;display:flex;align-items:center;justify-content:center;margin:0 0 16px;flex-shrink:0}
.hz-pl-inactive-icon i{font-size:26px;color:#dc2626}
.hz-pl-inactive-title{font-size:18px;font-weight:700;color:var(--text);margin-bottom:8px}
.hz-pl-inactive-sub{font-size:14px;color:var(--sub);line-height:1.6}

/* Form */
.hz-pl-form{padding:18px 20px 20px;display:flex;flex-direction:column;gap:4px}
.hz-pay-btn{width:100%;padding:12px 20px;border-radius:var(--r);background:var(--p);color:var(--p-tx);font-size:15px;font-weight:700;border:none;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity 150ms;margin-top:6px;letter-spacing:-.1px}
.hz-pay-btn:hover{opacity:.88}
.hz-pay-btn i{font-size:14px}
.hz-pl-notice{margin-top:12px;padding:10px 14px;background:var(--bg);border-radius:var(--r);border:1px solid var(--line);font-size:12.5px;color:var(--sub);line-height:1.6;text-align:center}

/* Footer */
.hz-footer{text-align:center;margin-top:14px;font-size:12px;color:var(--muted);display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px;width:100%}
.hz-footer-links{margin-top:32px;padding-bottom:8px;font-size:11.5px;color:var(--muted);text-align:center}
.hz-footer-links a,.hz-footer a{color:var(--muted);text-decoration:none;transition:color 130ms}
.hz-footer-links a:hover,.hz-footer a:hover{color:var(--p)}

/* Language modal dropdown */


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
</style>
</head>
<body style="<?php echo $bgStyle; ?>">
<div class="hz-page"><div class="hz-wrap">

<div class="hz-brand">
    <img src="<?php echo $data['brand']['favicon']; ?>" alt="">
    <span class="hz-brand-name"><?php echo htmlspecialchars($data['brand']['name']); ?></span>
</div>

<?php if(count($hz_avail_langs)>1): ?>
<div class="hz-nav">
    <div class="hz-nav-pills">
        <button type="button" class="hz-nav-pill" onclick="hzOpenLangModal()">
            <?php echo $SVG_LANG; ?> <span>Select Language</span>
        </button>
    </div>
</div>
<?php endif; ?>

<div class="hz-card">

<?php if(!$pl_active): ?>

    <div class="hz-pl-inactive">
        <div class="hz-pl-inactive-icon"><i class="fa-solid fa-link-slash"></i></div>
        <div class="hz-pl-inactive-title">Payment Link Not Active</div>
        <div class="hz-pl-inactive-sub">This payment link is currently unavailable.<br>Please contact the merchant for assistance.</div>
    </div>

<?php else: ?>

    <?php if($pl_show_img): ?>
    <div class="hz-pl-image-wrap">
        <?php if($pl_img): ?>
        <img class="hz-pl-image" src="<?php echo htmlspecialchars($pl_img); ?>" alt="<?php echo htmlspecialchars($pl_title); ?>">
        <?php else: ?>
        <!-- Default product vector illustration -->
        <svg class="hz-pl-vector" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 170" fill="none">
            <rect width="480" height="170" fill="#f5f6f9"/>
            <!-- Background soft circles -->
            <circle cx="400" cy="85" r="70" fill="<?php echo $hz_primary; ?>" opacity=".05"/>
            <circle cx="80" cy="140" r="40" fill="<?php echo $hz_primary; ?>" opacity=".05"/>
            <!-- Card shape -->
            <rect x="110" y="30" width="260" height="110" rx="12" fill="white" stroke="#e5e7eb" stroke-width="1.5"/>
            <!-- Card header band -->
            <rect x="110" y="30" width="260" height="38" rx="12" fill="<?php echo $hz_primary; ?>" opacity=".14"/>
            <rect x="110" y="52" width="260" height="16" fill="<?php echo $hz_primary; ?>" opacity=".08"/>
            <!-- Card chip -->
            <rect x="128" y="40" width="22" height="17" rx="3" fill="<?php echo $hz_primary; ?>" opacity=".35"/>
            <!-- Card label -->
            <rect x="158" y="44" width="60" height="6" rx="3" fill="<?php echo $hz_primary; ?>" opacity=".4"/>
            <!-- Card content rows -->
            <rect x="128" y="80" width="200" height="7" rx="3.5" fill="#e5e7eb"/>
            <rect x="128" y="94" width="140" height="7" rx="3.5" fill="#e5e7eb"/>
            <!-- Pay button -->
            <rect x="128" y="116" width="200" height="16" rx="6" fill="<?php echo $hz_primary; ?>" opacity=".2"/>
            <rect x="174" y="120" width="108" height="8" rx="4" fill="<?php echo $hz_primary; ?>" opacity=".4"/>
            <!-- Lock icon -->
            <circle cx="390" cy="85" r="28" fill="<?php echo $hz_primary; ?>" opacity=".1"/>
            <rect x="380" y="82" width="20" height="16" rx="3" fill="none" stroke="<?php echo $hz_primary; ?>" stroke-width="2" opacity=".5"/>
            <path d="M384 82v-4a6 6 0 0 1 12 0v4" stroke="<?php echo $hz_primary; ?>" stroke-width="2" stroke-linecap="round" opacity=".5"/>
            <circle cx="390" cy="90" r="2.5" fill="<?php echo $hz_primary; ?>" opacity=".6"/>
        </svg>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($pl_title || ($pl_show_desc && $pl_desc)): ?>
    <div class="hz-pl-info">
        <?php if($pl_title): ?><div class="hz-pl-title"><?php echo htmlspecialchars($pl_title); ?></div><?php endif; ?>
        <?php if($pl_show_desc && $pl_desc): ?><div class="hz-pl-desc"><?php echo nl2br(htmlspecialchars($pl_desc)); ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="hz-pl-form">
        <form action="" method="POST" id="form" enctype="multipart/form-data">
            <?php pp_renderFormFields('payment-link', $data); ?>
            <button type="submit" id="payButton" class="hz-pay-btn">
                <i class="fa-solid fa-lock"></i>
                <?php echo htmlspecialchars($pl_btn_text); ?>
            </button>
        </form>
        <?php if($pl_notice): ?>
        <div class="hz-pl-notice"><?php echo nl2br(htmlspecialchars($pl_notice)); ?></div>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div><!-- /.hz-wrap -->

<?php require __DIR__ . '/inc/footer.php'; ?>

<?php require __DIR__ . '/inc/lang-modal.php'; ?>

<?php echo pp_assets('footer'); ?>
<script>
/* ── Label repair: pp_renderFormFields sometimes emits empty or missing labels ── */
document.addEventListener('DOMContentLoaded', function() {
    var KNOWN = {
        'mobile-number': 'Mobile Number',
        'phone-number':  'Phone Number',
        'mobile_number': 'Mobile Number',
        'phone_number':  'Phone Number',
        'mobile':        'Mobile Number',
        'phone':         'Phone Number',
        'full-name':     'Full Name',
        'full_name':     'Full Name',
        'email-address': 'Email Address',
        'email_address': 'Email Address',
        'email':         'Email',
        'address':       'Address',
        'zip':           'ZIP / Postal Code',
        'country':       'Country',
        'message':       'Message',
        'note':          'Note',
        'uid':           'User ID',
        'user-id':       'User ID',
        'user_id':       'User ID',
        'player-id':     'Player ID',
        'player_id':     'Player ID',
        'game-id':       'Game ID',
        'game_id':       'Game ID',
    };
    function toTitle(str) {
        if (!str) return '';
        // Short codes (≤4 chars) go uppercase, e.g. "uid" → "UID"
        var parts = str.replace(/[-_]/g, ' ').trim().split(/\s+/);
        return parts.map(function(w) {
            return w.length <= 3 ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
        }).join(' ');
    }

    /* 1. Fix labels that exist but have no visible text (handles space-only text nodes) */
    document.querySelectorAll('label.form-label, label').forEach(function(lbl) {
        var text = '';
        lbl.childNodes.forEach(function(n) { if (n.nodeType === 3) text += n.textContent; });
        if (text.trim() === '') {
            var forAttr = lbl.getAttribute('for') || '';
            // Try the for attr, then the associated input's name attr
            var inp2 = forAttr ? document.getElementById(forAttr) : null;
            var nameAttr = inp2 ? (inp2.getAttribute('name') || '') : '';
            var label = KNOWN[forAttr] || KNOWN[nameAttr] || toTitle(forAttr) || toTitle(nameAttr);
            if (label) lbl.insertBefore(document.createTextNode(label + ' '), lbl.firstChild);
        }
    });

    /* 2. Fix inputs/selects/textareas that have NO label at all */
    var form = document.getElementById('form');
    if (!form) return;
    form.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(function(inp) {
        if (!inp.id) return;
        if (document.querySelector('label[for="' + inp.id + '"]')) return; // already has label
        var fieldName = KNOWN[inp.name] || KNOWN[inp.id] || toTitle(inp.name || inp.id);
        if (!fieldName) return;
        var lbl = document.createElement('label');
        lbl.className = 'form-label';
        lbl.setAttribute('for', inp.id);
        lbl.textContent = fieldName;
        if (inp.required) {
            var star = document.createElement('span');
            star.className = 'text-danger';
            star.textContent = ' *';
            lbl.appendChild(star);
        }
        // Insert before the input (or its wrapping .iti div)
        var target = inp.closest('.iti') || inp;
        target.parentNode.insertBefore(lbl, target);
    });
});

$(document).ready(function(){
    $('#form').on('submit',function(e){
        e.preventDefault();
        var pb=document.getElementById('payButton');
        if(pb) pb.innerHTML='<div class="spinner-border spinner-border-sm" role="status"></div>';
        $.ajax({
            url:'<?php echo pp_site_address(); ?>',type:'POST',dataType:'json',data:$(this).serialize(),
            success:function(r){
                if(pb) pb.innerHTML='<i class="fa-solid fa-lock"></i> <?php echo addslashes(htmlspecialchars($pl_btn_text)); ?>';
                if(r.status==="true") location.href=r.redirect;
                else createToast({title:r.title||'Error',description:r.message||'Something went wrong.',svg:'',timeout:6000});
            },
            error:function(){
                if(pb) pb.innerHTML='<i class="fa-solid fa-lock"></i> <?php echo addslashes(htmlspecialchars($pl_btn_text)); ?>';
                createToast({title:'Something went wrong',description:'Please try again or contact support.',svg:'',timeout:6000});
            }
        });
    });
});
</script>
</body>
</html>
