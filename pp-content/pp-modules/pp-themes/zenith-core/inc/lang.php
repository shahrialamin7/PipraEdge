<?php
/**
 * Zenith Core — Shared Language / Translation Dictionary
 * -------------------------------------------------------
 * Include this file AFTER $data is available and AFTER
 * $hz_avail_langs has been built.
 *
 * Sets:
 *   $hz_ui              – array of UI labels for current lang
 *   $hz_ui_js           – JSON-encoded $hz_ui for inline JS
 *   $hz_keywords_js     – JSON keyword list (current lang) for bold-highlight JS
 *   $hz_en_keywords_js  – JSON keyword list (English) for bold-highlight JS
 *   $hz_current_lang    – active language code string
 *   $hz_all_lang_names  – ['en'=>'English', 'bn'=>'বাংলা', …]
 */
if (!defined('PipraPay_INIT')) { http_response_code(403); exit('Direct access not allowed'); }

/* ── Language name map ──────────────────────────────────────── */
$hz_all_lang_names = [
    'en' => 'English',
    'bn' => 'বাংলা',
    'hi' => 'हिन्दी',
    'ur' => 'اردو',
    'ar' => 'العربية',
];

/* ── Active language ────────────────────────────────────────── */
/*
 * Priority:
 *   1. SESSION lang — if it exists AND is in the available-langs list
 *   2. First entry of $hz_avail_langs (set by the calling page before include)
 *   3. 'en' as absolute last-resort safety net
 *
 * Using the first available lang (not hardcoded 'en') means:
 *   - If admin sets only "bn" → site opens in Bengali on first load
 *   - If admin sets "bn,hi,ur" → site opens in Bengali (first) on first load
 */
$_hz_first_avail = (!empty($hz_avail_langs) && is_array($hz_avail_langs))
    ? reset($hz_avail_langs)
    : 'en';

$hz_current_lang = (
    !empty($_SESSION['ui_language']) &&
    isset($hz_all_lang_names[$_SESSION['ui_language']]) &&
    (!empty($hz_avail_langs) ? in_array($_SESSION['ui_language'], $hz_avail_langs, true) : true)
)
    ? $_SESSION['ui_language']
    : $_hz_first_avail;

/* ══════════════════════════════════════════════════════════════
   UI LABEL TRANSLATIONS
   Theme chrome: nav labels, button text, toast titles, etc.
   ══════════════════════════════════════════════════════════════ */
$_hz_ui_all = [
    'en' => [
        'back'                  => 'Back',
        'language'              => 'Language',
        'select_language'       => 'Select Language',
        'select_a_language'     => 'Select a language',
        'payment_instructions'  => 'Payment Instructions',
        'receipt_id'            => 'Receipt ID',
        'payable'               => 'Payable',
        'view_qr'               => 'View QR',
        'copy'                  => 'Copy',
        'qr_code'               => 'QR Code',
        'qr_scan_hint'          => 'Scan this code with your app to pay',
        'validation_trx_title'  => 'Transaction ID Required',
        'validation_trx_msg'    => 'Please enter the Transaction ID before verifying.',
        'copied'                => 'Copied',
        'copied_msg'            => 'Copied to clipboard.',
        'copy_failed'           => 'Failed',
        'copy_failed_msg'       => 'Unable to copy. Please copy manually.',
        'error'                 => 'Error',
        'error_msg'             => 'Something went wrong. Please try again.',
        'nothing_to_copy'       => 'Nothing to copy.',
    ],
    'bn' => [
        'back'                  => 'ফিরে যান',
        'language'              => 'ভাষা',
        'select_language'       => 'ভাষা নির্বাচন করুন',
        'select_a_language'     => 'একটি ভাষা নির্বাচন করুন',
        'payment_instructions'  => 'পেমেন্ট নির্দেশনা',
        'receipt_id'            => 'রিসিপ্ট আইডি',
        'payable'               => 'Payable',
        'view_qr'               => 'QR দেখুন',
        'copy'                  => 'কপি করুন',
        'qr_code'               => 'কিউআর কোড',
        'qr_scan_hint'          => 'পেমেন্টের জন্য আপনার অ্যাপ দিয়ে স্ক্যান করুন',
        'validation_trx_title'  => 'ট্রানজ্যাকশন আইডি প্রয়োজন',
        'validation_trx_msg'    => 'verify করার আগে ট্রানজ্যাকশন আইডি লিখুন।',
        'copied'                => 'কপি হয়েছে',
        'copied_msg'            => 'ক্লিপবোর্ডে কপি হয়েছে।',
        'copy_failed'           => 'ব্যর্থ',
        'copy_failed_msg'       => 'কপি করা যায়নি। ম্যানুয়ালি কপি করুন।',
        'error'                 => 'ত্রুটি',
        'error_msg'             => 'কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।',
        'nothing_to_copy'       => 'কপি করার কিছু নেই।',
    ],
    'hi' => [
        'back'                  => 'वापस',
        'language'              => 'भाषा',
        'select_language'       => 'भाषा चुनें',
        'select_a_language'     => 'एक भाषा चुनें',
        'payment_instructions'  => 'भुगतान निर्देश',
        'receipt_id'            => 'रसीद आईडी',
        'payable'               => 'Payable',
        'view_qr'               => 'QR देखें',
        'copy'                  => 'कॉपी करें',
        'qr_code'               => 'क्यूआर कोड',
        'qr_scan_hint'          => 'भुगतान के लिए अपने ऐप से स्कैन करें',
        'validation_trx_title'  => 'लेन-देन आईडी आवश्यक है',
        'validation_trx_msg'    => 'सत्यापन से पहले लेन-देन आईडी दर्ज करें।',
        'copied'                => 'कॉपी हो गया',
        'copied_msg'            => 'क्लिपबोर्ड पर कॉपी हो गया।',
        'copy_failed'           => 'विफल',
        'copy_failed_msg'       => 'कॉपी नहीं हो सका। मैन्युअली कॉपी करें।',
        'error'                 => 'त्रुटि',
        'error_msg'             => 'कुछ गलत हो गया। पुनः प्रयास करें।',
        'nothing_to_copy'       => 'कॉपी करने के लिए कुछ नहीं।',
    ],
    'ur' => [
        'back'                  => 'واپس',
        'language'              => 'زبان',
        'select_language'       => 'زبان منتخب کریں',
        'select_a_language'     => 'ایک زبان منتخب کریں',
        'payment_instructions'  => 'ادائیگی ہدایات',
        'receipt_id'            => 'رسید آئی ڈی',
        'payable'               => 'Payable',
        'view_qr'               => 'QR دیکھیں',
        'copy'                  => 'کاپی کریں',
        'qr_code'               => 'کیو آر کوڈ',
        'qr_scan_hint'          => 'ادائیگی کے لیے اپنی ایپ سے اسکین کریں',
        'validation_trx_title'  => 'ٹرانزیکشن آئی ڈی ضروری ہے',
        'validation_trx_msg'    => 'تصدیق سے پہلے ٹرانزیکشن آئی ڈی درج کریں۔',
        'copied'                => 'کاپی ہو گیا',
        'copied_msg'            => 'کلپ بورڈ پر کاپی ہو گیا۔',
        'copy_failed'           => 'ناکام',
        'copy_failed_msg'       => 'کاپی نہیں ہو سکا۔ دستی طور پر کاپی کریں۔',
        'error'                 => 'خطا',
        'error_msg'             => 'کچھ غلط ہوا۔ دوبارہ کوشش کریں۔',
        'nothing_to_copy'       => 'کاپی کرنے کے لیے کچھ نہیں۔',
    ],
    'ar' => [
        'back'                  => 'رجوع',
        'language'              => 'اللغة',
        'select_language'       => 'اختر لغة',
        'select_a_language'     => 'اختر لغة',
        'payment_instructions'  => 'تعليمات الدفع',
        'receipt_id'            => 'معرف الإيصال',
        'payable'               => 'Payable',
        'view_qr'               => 'عرض QR',
        'copy'                  => 'نسخ',
        'qr_code'               => 'رمز QR',
        'qr_scan_hint'          => 'امسح بتطبيقك للدفع',
        'validation_trx_title'  => 'معرف المعاملة مطلوب',
        'validation_trx_msg'    => 'يرجى إدخال معرف المعاملة قبل التحقق.',
        'copied'                => 'تم النسخ',
        'copied_msg'            => 'تم النسخ إلى الحافظة.',
        'copy_failed'           => 'فشل',
        'copy_failed_msg'       => 'تعذر النسخ. يرجى النسخ يدوياً.',
        'error'                 => 'خطأ',
        'error_msg'             => 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
        'nothing_to_copy'       => 'لا شيء للنسخ.',
    ],
];

/* ══════════════════════════════════════════════════════════════
   BOLD KEYWORD LIST
   Used by gateway.php JS Step 2 to bold key terms in payment
   instructions. These are the FINAL words as they appear in the
   rendered HTML (after substitutions run first).

   Rules:
   - Brand names, gateway terms, transaction-specific nouns → bold
   - Generic action words (নম্বর, পরিমাণ, পিন) → NOT bold
   - Order matters: longer phrases before shorter ones
   ══════════════════════════════════════════════════════════════ */
$_hz_kw_all = [
    'en' => [
        'Send Money','Send to Binance user','Cash Out','Make Payment',
        'Transaction ID','Reference No','bKash PIN',
        'Verify','Account',
        'Send','Receiver','Payment','Ref','Reference',
        'ID',
    ],
    'bn' => [
        /* After substitutions run, these are the words that remain
           and should be bolded. Native words that get substituted
           (চাপুন→click, যাচাই→verify etc.) are NOT listed here —
           their English replacements are covered by the 'en' list
           which also runs on non-English pages. */
        'ট্রানজ্যাকশন আইডি','রেফারেন্স নং',
        'ক্যাশ আউট','পেমেন্ট করুন','ফোন নম্বর',
        'অ্যাকাউন্ট',
        'পাঠান','টাকা','প্রাপক','পেমেন্ট','রেফ','রেফারেন্স',
        'আইডি',
    ],
    'hi' => [
        'लेन-देन आईडी','संदर्भ संख्या',
        'कैश आउट','भुगतान करें','फ़ोन नंबर',
        'खाता',
        'भेजें','पैसे','प्राप्तकर्ता','भुगतान','रेफ','संदर्भ',
        'आईडी',
    ],
    'ur' => [
        'ٹرانزیکشن آئی ڈی','حوالہ نمبر',
        'کیش آؤٹ','ادائیگی کریں','فون نمبر',
        'اکاؤنٹ',
        'بھیجیں','پیسے','وصول کنندہ','ادائیگی','ریف','حوالہ',
        'آئی ڈی',
    ],
    'ar' => [
        'معرف المعاملة','الرقم المرجعي',
        'سحب نقدي','قم بالدفع','رقم الهاتف',
        'الحساب',
        'أرسل','مال','المستلم','دفع','مرجع',
        'معرف',
    ],
];

/* ══════════════════════════════════════════════════════════════
   WORD SUBSTITUTION MAP  ($_hz_subst_all)
   ──────────────────────────────────────────────────────────────
   Applied by gateway.php JS as "Step 0" — BEFORE bold keywords.

   Purpose: Replace native-language words that should appear as
   English (or English-pronunciation) in all non-English renders.

   Structure: per-language array of  [ 'native' => 'replacement' ]
   Replacements are plain strings (no HTML); the bold-keyword pass
   that follows will handle any bolding needed.

   Rules used here:
   • Brand names         → English brand spelling  (বিকাশ → bKash)
   • UX action words     → English word            (চাপুন → click)
   • Verification terms  → English word            (যাচাই → verify)
   • Receipt/Payable     → English pronunciation   (রিসিট → রিসিপ্ট)
   • "Send to X user"    → English                 (পাঠান to → Send to)
   • Submit button text  → সাবমিট (Bengali pronunciation)
   • Amount label        → পরিমাণ kept but NOT bolded (handled via kw list)

   Order matters: longer / more-specific strings must come FIRST.
   ══════════════════════════════════════════════════════════════ */
$_hz_subst_all = [

    /* ════════════════════════════════════════════════════════════
       BENGALI (bn)
       • Compound phrases listed FIRST (longest match wins)
       • Then brand names, UX verbs, core labels, social media
    ════════════════════════════════════════════════════════════ */
    'bn' => [
        /* ── Compound phrases (must be first) ─────────────────── */
        'যাচাই করুন চাপুন'         => 'ভেরিফাই-এ ক্লিক করুন',
        'যাচাই করুন ক্লিক করুন'    => 'ভেরিফাই-এ ক্লিক করুন',

        /* ── Brand names ──────────────────────────────────────── */
        'বিকাশ'                    => 'bKash',
        'নগদ'                      => 'Nagad',
        'রকেট'                     => 'Rocket',

        /* ── Gateway "Send to X user" / "Send Money" ─────────── */
        'পাঠান to Binance user'    => 'Send to Binance user',
        'পাঠান to'                 => 'Send to',
        'টাকা পাঠান'               => 'Send Money',

        /* ── UX action verbs (Bengali pronunciation) ──────────── */
        'যাচাই করুন'               => 'ভেরিফাই',
        'যাচাই'                    => 'ভেরিফাই',
        'চাপুন'                    => 'ক্লিক',

        /* ── Submit (Bengali pronunciation) ───────────────────── */
        'জমা দিন'                  => 'সাবমিট',

        /* ── Core page labels (receipt / checkout / status) ───── */
        'গ্রাহক'                   => 'কাস্টমার',
        'রসিদ'                     => 'রিসিপ্ট',
        'রিসদ'                     => 'রিসিপ্ট',
        'রিসিট'                    => 'রিসিপ্ট',
        'প্রদেয়'                   => 'Payable',

        /* ── Social media names (Bengali pronunciation) ────────── */
        'ফেসবুক'                   => 'Facebook',
        'হোয়াটসঅ্যাপ'             => 'WhatsApp',
        'হোয়াটস অ্যাপ'           => 'WhatsApp',
        'ইনস্টাগ্রাম'             => 'Instagram',
        'টেলিগ্রাম'               => 'Telegram',
        'টুইটার'                   => 'Twitter',
        'এক্স (টুইটার)'            => 'X (Twitter)',
        'ইউটিউব'                   => 'YouTube',
        'লিংকডইন'                  => 'LinkedIn',
        'টিকটক'                    => 'TikTok',
        'স্ন্যাপচ্যাট'            => 'Snapchat',
        'পিন্টারেস্ট'             => 'Pinterest',
        'রেডিট'                    => 'Reddit',
        'ডিসকর্ড'                  => 'Discord',
        'টুইচ'                     => 'Twitch',
        'ভাইবার'                   => 'Viber',
        'স্কাইপ'                   => 'Skype',
        'জুম'                      => 'Zoom',
        'লাইন'                     => 'LINE',
    ],

    /* ════════════════════════════════════════════════════════════
       HINDI (hi)
    ════════════════════════════════════════════════════════════ */
    'hi' => [
        /* ── Compound phrases ─── */
        'सत्यापित करें दबाएं'       => 'वेरिफाई पर क्लिक करें',
        'सत्यापित करें दबाएँ'       => 'वेरिफाई पर क्लिक करें',

        /* ── Brand names ─── */
        'बिकाश'                    => 'bKash',
        'नगद'                      => 'Nagad',
        'रॉकेट'                    => 'Rocket',

        /* ── Gateway phrases ─── */
        'भेजें to Binance user'    => 'Send to Binance user',
        'पैसे भेजें'               => 'Send Money',

        /* ── UX verbs (Hindi pronunciation) ─── */
        'सत्यापित करें'            => 'वेरिफाई',
        'सत्यापित'                 => 'वेरिफाई',
        'दबाएं'                    => 'क्लिक',
        'दबाएँ'                    => 'क्लिक',
        'क्लिक करें'               => 'क्लिक',

        /* ── Submit ─── */
        'जमा करें'                 => 'सबमिट',

        /* ── Core page labels ─── */
        'ग्राहक'                   => 'कस्टमर',
        'रसीद'                     => 'रिसीट',
        'देय'                      => 'Payable',

        /* ── Social media ─── */
        'फेसबुक'                   => 'Facebook',
        'व्हाट्सएप'               => 'WhatsApp',
        'व्हाट्सऐप'               => 'WhatsApp',
        'इंस्टाग्राम'             => 'Instagram',
        'टेलीग्राम'               => 'Telegram',
        'ट्विटर'                   => 'Twitter',
        'यूट्यूब'                  => 'YouTube',
        'लिंक्डइन'                 => 'LinkedIn',
        'टिकटॉक'                   => 'TikTok',
        'स्नैपचैट'                 => 'Snapchat',
        'पिंटरेस्ट'               => 'Pinterest',
        'रेडिट'                    => 'Reddit',
        'डिस्कॉर्ड'               => 'Discord',
        'ट्विच'                    => 'Twitch',
        'वाइबर'                    => 'Viber',
        'स्काइप'                   => 'Skype',
        'ज़ूम'                     => 'Zoom',
    ],

    /* ════════════════════════════════════════════════════════════
       URDU (ur)
    ════════════════════════════════════════════════════════════ */
    'ur' => [
        /* ── Compound phrases ─── */
        'تصدیق کریں دبائیں'        => 'ویریفائی پر کلک کریں',
        'تصدیق کریں کلک کریں'      => 'ویریفائی پر کلک کریں',

        /* ── Brand names ─── */
        'بکاش'                     => 'bKash',
        'نقد'                      => 'Nagad',
        'راکٹ'                     => 'Rocket',

        /* ── Gateway phrases ─── */
        'بھیجیں to Binance user'   => 'Send to Binance user',
        'پیسے بھیجیں'              => 'Send Money',

        /* ── UX verbs (Urdu pronunciation) ─── */
        'تصدیق کریں'               => 'ویریفائی',
        'تصدیق'                    => 'ویریفائی',
        'دبائیں'                   => 'کلک',
        'کلک کریں'                 => 'کلک',

        /* ── Submit ─── */
        'جمع کریں'                 => 'سبمٹ',

        /* ── Core page labels ─── */
        'گاہک'                     => 'کسٹمر',
        'رسید'                     => 'رسیپٹ',
        'قابل ادائیگی'             => 'Payable',

        /* ── Social media ─── */
        'فیس بک'                   => 'Facebook',
        'فیسبک'                    => 'Facebook',
        'واٹس ایپ'                 => 'WhatsApp',
        'واٹس ایپ'                 => 'WhatsApp',
        'انسٹاگرام'               => 'Instagram',
        'ٹیلیگرام'                => 'Telegram',
        'ٹوئٹر'                   => 'Twitter',
        'یوٹیوب'                   => 'YouTube',
        'لنکڈ ان'                  => 'LinkedIn',
        'ٹک ٹاک'                   => 'TikTok',
        'سنیپ چیٹ'                => 'Snapchat',
        'پنٹرسٹ'                  => 'Pinterest',
        'ریڈٹ'                     => 'Reddit',
        'ڈسکارڈ'                  => 'Discord',
        'ٹوئچ'                     => 'Twitch',
        'وائبر'                    => 'Viber',
        'اسکائپ'                   => 'Skype',
        'زوم'                      => 'Zoom',
    ],

    /* ════════════════════════════════════════════════════════════
       ARABIC (ar)
    ════════════════════════════════════════════════════════════ */
    'ar' => [
        /* ── Compound phrases ─── */
        'تحقق اضغط'                => 'انقر فيريفاي',
        'تحقق انقر'                => 'انقر فيريفاي',

        /* ── Gateway phrases ─── */
        'أرسل to Binance user'     => 'Send to Binance user',
        'أرسل المال'               => 'Send Money',

        /* ── UX verbs (Arabic pronunciation) ─── */
        'تحقق'                     => 'فيريفاي',
        'اضغط'                     => 'كليك',
        'انقر'                     => 'كليك',

        /* ── Submit ─── */
        'إرسال'                    => 'سبميت',

        /* ── Core page labels ─── */
        'العميل'                   => 'كاستومر',
        'إيصال'                    => 'ريسيبت',
        'المستحق'                  => 'Payable',

        /* ── Social media ─── */
        'فيسبوك'                   => 'Facebook',
        'واتساب'                   => 'WhatsApp',
        'إنستغرام'                => 'Instagram',
        'إنستقرام'                => 'Instagram',
        'تيليغرام'                => 'Telegram',
        'تيلغرام'                 => 'Telegram',
        'تويتر'                    => 'Twitter',
        'يوتيوب'                   => 'YouTube',
        'لينكد إن'                 => 'LinkedIn',
        'تيك توك'                  => 'TikTok',
        'سناب شات'                => 'Snapchat',
        'بينتريست'                => 'Pinterest',
        'ريديت'                    => 'Reddit',
        'ديسكورد'                 => 'Discord',
        'تويتش'                    => 'Twitch',
        'فايبر'                    => 'Viber',
        'سكايب'                    => 'Skype',
        'زووم'                     => 'Zoom',
    ],
];



/* ══════════════════════════════════════════════════════════════
   TOAST MESSAGE TRANSLATIONS
   Maps known English server/client toast strings → current lang.
   Used by gateway.php JS to translate failed() / success toasts
   that originate from PipraPay AJAX responses or gateway scripts.
   ══════════════════════════════════════════════════════════════ */
$_hz_toast_map_all = [
    'bn' => [
        /* ── Titles ── */
        'Duplicate Transaction'     => 'ডুপ্লিকেট ট্রানজ্যাকশন',
        'Duplicate Transaction ID'  => 'ডুপ্লিকেট ট্রানজ্যাকশন আইডি',
        'Missing Transaction ID'    => 'ট্রানজ্যাকশন আইডি দেওয়া হয়নি',
        'Transaction Not Found'     => 'ট্রানজ্যাকশন পাওয়া যায়নি',
        'Transaction Not Matched'   => 'ট্রানজ্যাকশন মেলেনি',
        'Transaction Submitted'     => 'ট্রানজ্যাকশন জমা হয়েছে',
        'Transaction Verified'      => 'ট্রানজ্যাকশন যাচাই হয়েছে',
        'Unexpected Response'       => 'অপ্রত্যাশিত সাড়া',
        'Request Error'             => 'অনুরোধ ত্রুটি',
        /* ── Messages ── */
        'The provided Transaction ID already exists in our system.'
            => 'এই ট্রানজ্যাকশন আইডি ইতিমধ্যে আমাদের সিস্টেমে রয়েছে।',
        'The Transaction ID field cannot be empty. Please provide a valid Transaction ID.'
            => 'ট্রানজ্যাকশন আইডি ফিল্ড খালি রাখা যাবে না। সঠিক আইডি দিন।',
        'This Transaction ID is already exits. Please provide a different one.'
            => 'এই ট্রানজ্যাকশন আইডি ইতিমধ্যে বিদ্যমান। অন্য একটি দিন।',
        'The Transaction ID you entered could not be verified. Please check the ID and try again after some time.'
            => 'আপনার দেওয়া ট্রানজ্যাকশন আইডি যাচাই করা যায়নি। আইডি পরীক্ষা করে কিছুক্ষণ পর আবার চেষ্টা করুন।',
        'The Transaction ID you entered could not be verified. Please try again after some time, or enter your phone number and submit for manual approval.'
            => 'ট্রানজ্যাকশন আইডি যাচাই করা যায়নি। কিছুক্ষণ পর চেষ্টা করুন অথবা ফোন নম্বর দিয়ে ম্যানুয়াল অনুমোদনের জন্য জমা দিন।',
        'The Transaction ID has been successfully verified.'
            => 'ট্রানজ্যাকশন আইডি সফলভাবে যাচাই হয়েছে।',
        'Your Transaction ID has been successfully submitted'
            => 'আপনার ট্রানজ্যাকশন আইডি সফলভাবে জমা হয়েছে।',
        'Please try again later.'
            => 'অনুগ্রহ করে পরে আবার চেষ্টা করুন।',
        'Something went wrong. Please try again.'
            => 'কিছু একটা ভুল হয়েছে। আবার চেষ্টা করুন।',
    ],
    'hi' => [
        /* ── Titles ── */
        'Duplicate Transaction'     => 'डुप्लीकेट लेनदेन',
        'Duplicate Transaction ID'  => 'डुप्लीकेट लेनदेन आईडी',
        'Missing Transaction ID'    => 'लेनदेन आईडी नहीं दी गई',
        'Transaction Not Found'     => 'लेनदेन नहीं मिला',
        'Transaction Not Matched'   => 'लेनदेन मेल नहीं खाया',
        'Transaction Submitted'     => 'लेनदेन सबमिट हो गया',
        'Transaction Verified'      => 'लेनदेन सत्यापित हो गया',
        'Unexpected Response'       => 'अप्रत्याशित प्रतिक्रिया',
        'Request Error'             => 'अनुरोध त्रुटि',
        /* ── Messages ── */
        'The provided Transaction ID already exists in our system.'
            => 'यह लेनदेन आईडी हमारे सिस्टम में पहले से मौजूद है।',
        'The Transaction ID field cannot be empty. Please provide a valid Transaction ID.'
            => 'लेनदेन आईडी फ़ील्ड खाली नहीं हो सकता। कृपया एक वैध आईडी दर्ज करें।',
        'This Transaction ID is already exits. Please provide a different one.'
            => 'यह लेनदेन आईडी पहले से मौजूद है। कोई अन्य आईडी दर्ज करें।',
        'The Transaction ID you entered could not be verified. Please check the ID and try again after some time.'
            => 'आपकी दर्ज की गई लेनदेन आईडी सत्यापित नहीं हो सकी। आईडी जांचें और थोड़ी देर बाद पुनः प्रयास करें।',
        'The Transaction ID you entered could not be verified. Please try again after some time, or enter your phone number and submit for manual approval.'
            => 'लेनदेन आईडी सत्यापित नहीं हो सकी। कुछ समय बाद पुनः प्रयास करें या फ़ोन नंबर दर्ज करके मैन्युअल अनुमोदन हेतु सबमिट करें।',
        'The Transaction ID has been successfully verified.'
            => 'लेनदेन आईडी सफलतापूर्वक सत्यापित हो गई।',
        'Your Transaction ID has been successfully submitted'
            => 'आपकी लेनदेन आईडी सफलतापूर्वक सबमिट हो गई।',
        'Please try again later.'
            => 'कृपया बाद में पुनः प्रयास करें।',
        'Something went wrong. Please try again.'
            => 'कुछ गलत हो गया। कृपया पुनः प्रयास करें।',
    ],
    'ur' => [
        /* ── Titles ── */
        'Duplicate Transaction'     => 'ڈپلیکیٹ ٹرانزیکشن',
        'Duplicate Transaction ID'  => 'ڈپلیکیٹ ٹرانزیکشن آئی ڈی',
        'Missing Transaction ID'    => 'ٹرانزیکشن آئی ڈی درج نہیں کی گئی',
        'Transaction Not Found'     => 'ٹرانزیکشن نہیں ملا',
        'Transaction Not Matched'   => 'ٹرانزیکشن مطابقت نہیں کرتا',
        'Transaction Submitted'     => 'ٹرانزیکشن جمع ہو گیا',
        'Transaction Verified'      => 'ٹرانزیکشن تصدیق ہو گیا',
        'Unexpected Response'       => 'غیر متوقع جواب',
        'Request Error'             => 'درخواست کی غلطی',
        /* ── Messages ── */
        'The provided Transaction ID already exists in our system.'
            => 'یہ ٹرانزیکشن آئی ڈی ہمارے سسٹم میں پہلے سے موجود ہے۔',
        'The Transaction ID field cannot be empty. Please provide a valid Transaction ID.'
            => 'ٹرانزیکشن آئی ڈی فیلڈ خالی نہیں ہو سکتا۔ درست آئی ڈی درج کریں۔',
        'This Transaction ID is already exits. Please provide a different one.'
            => 'یہ ٹرانزیکشن آئی ڈی پہلے سے موجود ہے۔ کوئی دوسری آئی ڈی درج کریں۔',
        'The Transaction ID you entered could not be verified. Please check the ID and try again after some time.'
            => 'آپ کی درج کردہ ٹرانزیکشن آئی ڈی تصدیق نہیں ہو سکی۔ آئی ڈی چیک کریں اور تھوڑی دیر بعد دوبارہ کوشش کریں۔',
        'The Transaction ID you entered could not be verified. Please try again after some time, or enter your phone number and submit for manual approval.'
            => 'ٹرانزیکشن آئی ڈی تصدیق نہیں ہو سکی۔ کچھ دیر بعد کوشش کریں یا فون نمبر درج کر کے دستی منظوری کے لیے جمع کریں۔',
        'The Transaction ID has been successfully verified.'
            => 'ٹرانزیکشن آئی ڈی کامیابی سے تصدیق ہو گئی۔',
        'Your Transaction ID has been successfully submitted'
            => 'آپ کی ٹرانزیکشن آئی ڈی کامیابی سے جمع ہو گئی۔',
        'Please try again later.'
            => 'براہ کرم بعد میں دوبارہ کوشش کریں۔',
        'Something went wrong. Please try again.'
            => 'کچھ غلط ہو گیا۔ دوبارہ کوشش کریں۔',
    ],
    'ar' => [
        /* ── Titles ── */
        'Duplicate Transaction'     => 'معاملة مكررة',
        'Duplicate Transaction ID'  => 'معرف معاملة مكرر',
        'Missing Transaction ID'    => 'معرف المعاملة مفقود',
        'Transaction Not Found'     => 'لم يتم العثور على المعاملة',
        'Transaction Not Matched'   => 'المعاملة غير متطابقة',
        'Transaction Submitted'     => 'تم إرسال المعاملة',
        'Transaction Verified'      => 'تم التحقق من المعاملة',
        'Unexpected Response'       => 'استجابة غير متوقعة',
        'Request Error'             => 'خطأ في الطلب',
        /* ── Messages ── */
        'The provided Transaction ID already exists in our system.'
            => 'معرف المعاملة المقدم موجود بالفعل في نظامنا.',
        'The Transaction ID field cannot be empty. Please provide a valid Transaction ID.'
            => 'لا يمكن أن يكون حقل معرف المعاملة فارغاً. يرجى إدخال معرف صحيح.',
        'This Transaction ID is already exits. Please provide a different one.'
            => 'معرف المعاملة هذا موجود مسبقاً. يرجى إدخال معرف آخر.',
        'The Transaction ID you entered could not be verified. Please check the ID and try again after some time.'
            => 'تعذر التحقق من معرف المعاملة الذي أدخلته. تحقق من المعرف وحاول مرة أخرى بعد قليل.',
        'The Transaction ID you entered could not be verified. Please try again after some time, or enter your phone number and submit for manual approval.'
            => 'تعذر التحقق من معرف المعاملة. حاول مرة أخرى لاحقاً أو أدخل رقم هاتفك وأرسله للموافقة اليدوية.',
        'The Transaction ID has been successfully verified.'
            => 'تم التحقق من معرف المعاملة بنجاح.',
        'Your Transaction ID has been successfully submitted'
            => 'تم إرسال معرف المعاملة الخاص بك بنجاح.',
        'Please try again later.'
            => 'يرجى المحاولة مرة أخرى لاحقاً.',
        'Something went wrong. Please try again.'
            => 'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
    ],
];

/* ── Export resolved values ─────────────────────────────────── */
$hz_ui              = $_hz_ui_all[$hz_current_lang]  ?? $_hz_ui_all['en'];
$hz_ui_js           = json_encode($hz_ui);
$hz_keywords_js     = json_encode($_hz_kw_all[$hz_current_lang] ?? $_hz_kw_all['en']);
$hz_en_keywords_js  = json_encode($_hz_kw_all['en']);
/* Toast map: English → current language (empty for 'en' — no mapping needed) */
$hz_toast_map_js    = json_encode($_hz_toast_map_all[$hz_current_lang] ?? (object)[]);
/* Substitution map: native → preferred (empty for 'en' — nothing to substitute) */
$hz_subst_map_js    = json_encode($_hz_subst_all[$hz_current_lang]    ?? (object)[]);
