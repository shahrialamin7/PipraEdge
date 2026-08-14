<?php
    class ZenithCoreTheme
    {
        public function info()
        {
            return ['title'=>'Zenith Core','logo'=>'assets/logo.jpg'];
        }

        public function fields()
        {
            $colorHint = function(string $id, string $placeholder) {
                return '<span id="'.$id.'"></span><script>(function(){function init(){var mk=document.getElementById("'.$id.'");if(!mk)return;var fg=mk.closest(".form-group");if(!fg)return;var wrap=fg.querySelector(".form-control-wrap");var ci=wrap&&wrap.querySelector("[type=color]");if(!ci)return;ci.style.cssText="width:38px;height:38px;padding:2px;border:1px solid #d0d5dd;border-radius:4px;cursor:pointer;flex-shrink:0;";ci.classList.remove("form-control");var hi=document.createElement("input");hi.type="text";hi.className="form-control";hi.placeholder="'.$placeholder.'";hi.maxLength=7;hi.value=ci.value;hi.style.fontFamily="monospace";var row=document.createElement("div");row.style.cssText="display:flex;align-items:center;gap:8px;";row.appendChild(hi);row.appendChild(ci);wrap.innerHTML="";wrap.appendChild(row);ci.addEventListener("input",function(){hi.value=this.value;});function apply(){var v=hi.value.trim();if(v.charAt(0)!="#")v="#"+v;if(/^#[0-9a-fA-F]{6}$/.test(v)){ci.value=v;hi.value=v;}}hi.addEventListener("keydown",function(e){if(e.key==="Enter"){e.preventDefault();apply();}});hi.addEventListener("change",apply);hi.addEventListener("input",function(){var v=this.value.trim();if(v.charAt(0)!="#")v="#"+v;if(/^#[0-9a-fA-F]{6}$/.test(v))ci.value=v;});}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}})()</script>';
            };

            return [

                /* ── 1. Appearance ─────────────────────────────────────────────
                   Brand colors and background — the first thing merchants configure.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'primary_color','label'=>'Primary Color','type'=>'color','value'=>'#1a56db','required'=>false,
                    'hint'=>$colorHint('_hm_primary_color','#1a56db').' Applied to buttons, active tabs, selected states, and accent highlights.',
                    'group'=>'Appearance'],

                ['name'=>'text_color','label'=>'Button Text Color','type'=>'color','value'=>'#FFFFFF','required'=>false,
                    'hint'=>$colorHint('_hm_text_color','#FFFFFF').' Text color rendered on top of your primary color buttons.',
                    'group'=>'Appearance'],

                ['name'=>'enable_bg_image','label'=>'Custom Background Image','type'=>'select',
                    'options'=>['disabled'=>'Disabled — use default background','enabled'=>'Enabled — use a custom background image'],
                    'value'=>'disabled','required'=>false,'multiple'=>false,
                    'hint'=>'When enabled, the image uploaded below replaces the default page background.',
                    'group'=>'Appearance'],

                ['name'=>'background_image','label'=>'Background Image','type'=>'image','required'=>false,
                    'hint'=>'Recommended: high-resolution JPEG or PNG. Displayed full-screen behind the checkout card.',
                    'group'=>'Appearance'],

                /* ── 2. Gateway Display ────────────────────────────────────────
                   Control how payment methods are presented on the checkout page.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'show_gateway_names','label'=>'Show Gateway Names','type'=>'select',
                    'options'=>['enabled'=>'Show Names — display label beneath each gateway logo','disabled'=>'Logo Only — hide names, show logos exclusively'],
                    'value'=>'enabled','required'=>false,'multiple'=>false,
                    'hint'=>'Displaying names improves recognisability, especially when logos are not widely known.',
                    'group'=>'Gateway Display'],

                ['name'=>'gateway_columns','label'=>'Gateway Grid Columns','type'=>'select',
                    'options'=>['2'=>'2 Columns','3'=>'3 Columns','4'=>'4 Columns'],
                    'value'=>'3','required'=>false,'multiple'=>false,
                    'hint'=>'Controls how many gateway cards appear per row on desktop. Mobile always shows 2 columns.',
                    'group'=>'Gateway Display'],

                ['name'=>'direct_redirect','label'=>'Direct Gateway Redirect','type'=>'select',
                    'options'=>['disabled'=>'Disabled — customer selects a gateway, then confirms with Pay Now','enabled'=>'Enabled — tapping a gateway card proceeds directly to the payment page'],
                    'value'=>'disabled','required'=>false,'multiple'=>false,
                    'hint'=>'Direct redirect reduces one tap in the checkout flow. Disable if customers need to review their selection before proceeding.',
                    'group'=>'Gateway Display'],

                ['name'=>'gateway_order_mfs','label'=>'Mobile Banking — Display Order','type'=>'textarea',
                    'value'=>'bkash-api-tokenized
bkash-merchant
bkash-personal
bkash-agent
nagad-merchant-api
nagad-merchant
nagad-personal
nagad-agent
rocket-merchant
rocket-personal
rocket-agent
upay-merchant
upay-personal
upay-agent
tap-merchant
tap-personal
tap-agent
cellfin-merchant
cellfin-personal
mcash-merchant
mcash-personal
mcash-agent
okwallet-merchant
okwallet-personal
okwallet-agent
ipay-merchant
ipay-personal
telecash-merchant
telecash-personal
telecash-agent
pathaopay-merchant-api
pathaopay-merchant
pathaopay-personal
paystation
aamarpay
shurjopay
sslcommerz
eps',
                    'required'=>false,
                    'placeholder'=>"Enter one gateway key per line:
bkash-api-tokenized
nagad-merchant-api
rocket-merchant",
                    'hint'=>'Gateways are displayed in this exact order. Unlisted gateways appear after configured ones. Use full gateway IDs for precise control.',
                    'group'=>'Gateway Display'],

                ['name'=>'gateway_order_bank','label'=>'Net Banking — Display Order','type'=>'textarea',
                    'value'=>'',
                    'required'=>false,
                    'placeholder'=>"Enter one gateway key per line:
sslcommerz
aamarpay
shurjopay",
                    'hint'=>'Gateways are displayed in this exact order. Unlisted gateways appear after configured ones. Use full gateway IDs for precise control.',
                    'group'=>'Gateway Display'],

                ['name'=>'gateway_order_global','label'=>'International — Display Order','type'=>'textarea',
                    'value'=>'stripe
paypal-manual
binance-personal
oxapay
payeer-manual
payoneer-manual
wise-manual
taptap-send-manual',
                    'required'=>false,
                    'placeholder'=>"Enter one gateway key per line:
stripe
paypal-manual
binance-personal",
                    'hint'=>'Gateways are displayed in this exact order. Unlisted gateways appear after configured ones. Use full gateway IDs for precise control.',
                    'group'=>'Gateway Display'],

                /* ── 3. Navigation & Support ───────────────────────────────────
                   Configure the utility navigation bar and customer support panel.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'show_nav_buttons','label'=>'Navigation Bar — Visible Sections','type'=>'select',
                    'options'=>[
                        'all'      => 'Show All — Transaction Details, FAQ, and Support',
                        'details'  => 'Transaction Details Only',
                        'faq'      => 'FAQ Only',
                        'support'  => 'Customer Support Only',
                        'disabled' => 'Hidden — do not display navigation sections',
                    ],
                    'value'=>'all','required'=>false,'multiple'=>false,
                    'hint'=>'Controls which informational sections are accessible from the checkout navigation bar.',
                    'group'=>'Navigation & Support'],

                ['name'=>'support_columns','label'=>'Support Panel — Items Per Row','type'=>'select',
                    'options'=>['1'=>'1 per row — full-width list layout','2'=>'2 per row — compact grid layout'],
                    'value'=>'1','required'=>false,'multiple'=>false,
                    'hint'=>'Determines the layout of support channel cards in the Help & Support panel.',
                    'group'=>'Navigation & Support'],

                /* ── 4. Payment Link ───────────────────────────────────────────
                   Customise the public-facing payment link page.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'pl_show_image','label'=>'Payment Link — Product Image','type'=>'select',
                    'options'=>['enabled'=>'Visible — display the product image at the top of the page','disabled'=>'Hidden — omit the product image'],
                    'value'=>'enabled','required'=>false,'multiple'=>false,
                    'hint'=>'When enabled, the product or service image is shown at the top of the payment link page.',
                    'group'=>'Payment Link'],

                ['name'=>'pl_show_description','label'=>'Payment Link — Product Description','type'=>'select',
                    'options'=>['enabled'=>'Visible — show the full product description','disabled'=>'Hidden — show title only'],
                    'value'=>'enabled','required'=>false,'multiple'=>false,
                    'hint'=>'When enabled, the full product description is displayed beneath the title on the payment link page.',
                    'group'=>'Payment Link'],

                ['name'=>'pl_button_text','label'=>'Payment Link — Pay Button Label','type'=>'text','value'=>'','required'=>false,
                    'placeholder'=>'Default: Pay Now — e.g. Buy Now, Donate, Subscribe, Get Access',
                    'hint'=>'Leave blank to use the default label derived from the active language.',
                    'group'=>'Payment Link'],

                ['name'=>'pl_notice_text','label'=>'Payment Link — Notice / Terms Text','type'=>'textarea','value'=>'','required'=>false,
                    'placeholder'=>'Optional notice displayed below the pay button, e.g. terms of service or refund policy.',
                    'hint'=>'Supports plain text. Displayed in a muted style beneath the pay button.',
                    'group'=>'Payment Link'],

                /* ── 5. Post-Payment Behaviour ─────────────────────────────────
                   Configure what happens immediately after a payment is completed.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'redirect_after_payment','label'=>'Auto-Redirect After Payment','type'=>'select',
                    'options'=>['enabled'=>'Enabled — automatically redirect the customer to the return URL','disabled'=>'Disabled — display the payment result page without redirecting'],
                    'value'=>'enabled','required'=>false,'multiple'=>false,
                    'hint'=>'Auto-redirect only applies when a return URL is configured on the transaction.',
                    'group'=>'Post-Payment Behaviour'],

                ['name'=>'redirect_delay','label'=>'Auto-Redirect Delay (seconds)','type'=>'text','value'=>'5','required'=>false,
                    'placeholder'=>'e.g. 3, 5, 10  |  Enter 0 to disable auto-redirect',
                    'hint'=>'Number of seconds to display the result page before redirecting. Set to 0 to disable automatic redirection entirely.',
                    'group'=>'Post-Payment Behaviour'],

                /* ── 6. Language & Localisation ────────────────────────────────
                   Control which languages customers can select at checkout.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'available_languages','label'=>'Available Languages','type'=>'text','value'=>'en,bn,hi,ur,ar','required'=>false,
                    'placeholder'=>'Comma-separated language codes: en,bn,hi,ur,ar',
                    'hint'=>'Only languages listed here appear in the language selector. Use ISO 639-1 codes supported by this theme.',
                    'group'=>'Language & Localisation'],

                /* ── 7. SEO & Analytics ────────────────────────────────────────
                   Search engine metadata and tracking script configuration.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'seo_title','label'=>'SEO Title','type'=>'text','required'=>false,
                    'placeholder'=>'Enter page title for search engines (recommended: up to 60 characters)',
                    'hint'=>'Overrides the default page title in search engine results and browser tabs.',
                    'group'=>'SEO & Analytics'],

                ['name'=>'seo_description','label'=>'SEO Meta Description','type'=>'textarea','required'=>false,
                    'placeholder'=>'Enter a concise page description for search engines (recommended: up to 160 characters)',
                    'hint'=>'Displayed as the snippet beneath your page title in search engine results.',
                    'group'=>'SEO & Analytics'],

                ['name'=>'seo_keywords','label'=>'SEO Keywords','type'=>'text','required'=>false,
                    'placeholder'=>'e.g. secure checkout, online payment, billing',
                    'hint'=>'Comma-separated keywords. Note: most modern search engines do not use this meta tag for ranking.',
                    'group'=>'SEO & Analytics'],

                ['name'=>'analytics_code','label'=>'Analytics & Tracking Code','type'=>'textarea','required'=>false,
                    'placeholder'=>'Paste your Google Analytics (gtag.js), Google Tag Manager, Meta Pixel, or other tracking script here.',
                    'hint'=>'Injected verbatim into the &lt;head&gt; of every checkout page. Ensure the script is trusted and well-formed.',
                    'group'=>'SEO & Analytics'],

                /* ── 8. Footer ─────────────────────────────────────────────────
                   Customise the footer displayed beneath the checkout card.
                ─────────────────────────────────────────────────────────────────── */
                [
                    'name'       => 'footer_text',
                    'label'      => 'Footer Text',
                    'type'       => 'textarea',
                    'value'      => '<div style="text-align:center">Your payment is secured with 256-bit encryption.<br>Powered by <a href="https://piprapay.com" target="_blank" style="color:#5b49fb;font-weight:600;">PIPRAPAY</a></div>',
                    'placeholder'=> "Supports HTML. Examples:\nPowered by <a href=\"https://piprapay.com\" target=\"_blank\" style=\"color:#5b49fb;font-weight:600;\">PIPRAPAY</a>\n&bull; <a href=\"https://yourbrand.com/privacy\" target=\"_blank\">Privacy Policy</a> &bull; <a href=\"https://yourbrand.com/terms\" target=\"_blank\">Terms of Service</a>",
                    'hint'       => 'Full HTML is supported. Use &lt;a&gt; tags for links, &lt;br&gt; or &lt;div&gt; for line breaks. All links should include target="_blank".',
                    'required'   => false,
                    'group'      => 'Footer',
                ],

                /* ── Session Timeout ─────────────────────────────────────────────
                   Auto-cancel the transaction if the customer does not complete
                   payment within the configured window.
                ─────────────────────────────────────────────────────────────────── */
                ['name'=>'session_timeout','label'=>'Session Timeout','type'=>'select',
                    'options'=>['disabled'=>'Disabled — no time limit on checkout','enabled'=>'Enabled — auto-cancel after the duration below'],
                    'value'=>'disabled','required'=>false,'multiple'=>false,
                    'hint'=>'When enabled, a countdown timer appears on the checkout and gateway pages. The transaction is automatically cancelled if the customer does not pay within the set duration. The timer is anchored to the transaction creation time — not the page load — so refreshing the page does not reset it.',
                    'group'=>'Session Timeout'],

                ['name'=>'session_timeout_minutes','label'=>'Timeout Duration (minutes)','type'=>'text',
                    'value'=>'15','required'=>false,
                    'placeholder'=>'e.g. 15',
                    'hint'=>'Number of minutes before the session expires. Set to 0 or leave blank to disable the countdown regardless of the toggle above.',
                    'group'=>'Session Timeout'],
            ];
        }

        public function supported_languages()
        {
            return ['en'=>'English','bn'=>'বাংলা','hi'=>'हिन्दी','ur'=>'اردو','ar'=>'العربية'];
        }

        public function lang_text()
        {
            return [
                'payment_link'          => ['en'=>'Payment Link','bn'=>'পেমেন্ট লিঙ্ক','hi'=>'भुगतान लिंक','ur'=>'ادائیگی کا لنک','ar'=>'رابط الدفع'],
                'checkout'              => ['en'=>'Checkout','bn'=>'চেকআউট','hi'=>'चेकआउट','ur'=>'چیک آؤٹ','ar'=>'الدفع'],
                'pay_now'               => ['en'=>'Pay Now','bn'=>'এখন পরিশোধ করুন','hi'=>'अभी भुगतान करें','ur'=>'ابھی ادا کریں','ar'=>'ادفع الآن'],
                'select_payment_method' => ['en'=>'Select Payment Method','bn'=>'পেমেন্ট পদ্ধতি নির্বাচন করুন','hi'=>'भुगतान विधि चुनें','ur'=>'ادائیگی کا طریقہ منتخب کریں','ar'=>'اختر طريقة الدفع'],
                'mobile_banking'        => ['en'=>'Mobile Banking','bn'=>'মোবাইল ব্যাংকিং','hi'=>'मोबाइल बैंकिंग','ur'=>'موبائل بینکنگ','ar'=>'الخدمات المصرفية عبر الهاتف'],
                'net_banking'           => ['en'=>'Net Banking','bn'=>'নেট ব্যাংকিং','hi'=>'नेट बैंकिंग','ur'=>'نیٹ بینکنگ','ar'=>'الخدمات المصرفية عبر الإنترنت'],
                'global'                => ['en'=>'International','bn'=>'আন্তর্জাতিক','hi'=>'अंतर्राष्ट्रीय','ur'=>'بین الاقوامی','ar'=>'دولي'],
                'amount'                => ['en'=>'Amount','bn'=>'পরিমাণ','hi'=>'राशि','ur'=>'رقم','ar'=>'المبلغ'],
                'currency'              => ['en'=>'Currency','bn'=>'মুদ্রা','hi'=>'मुद्रा','ur'=>'کرنسی','ar'=>'العملة'],
                'discount'              => ['en'=>'Discount','bn'=>'ছাড়','hi'=>'छूट','ur'=>'رعایت','ar'=>'خصم'],
                'processing_fee'        => ['en'=>'Processing Fee','bn'=>'প্রসেসিং ফি','hi'=>'प्रोसेसिंग शुल्क','ur'=>'پروسیسنگ فیس','ar'=>'رسوم المعالجة'],
                'full_name'             => ['en'=>'Full Name','bn'=>'পূর্ণ নাম','hi'=>'पूरा नाम','ur'=>'پورا نام','ar'=>'الاسم الكامل'],
                'email_address'         => ['en'=>'Email Address','bn'=>'ইমেইল','hi'=>'ईमेल','ur'=>'ای میل','ar'=>'بريد إلكتروني'],
                'mobile_number'         => ['en'=>'Mobile Number','bn'=>'মোবাইল নম্বর','hi'=>'मोबाइल नंबर','ur'=>'موبائل نمبر','ar'=>'رقم الهاتف'],
                'phone_number'          => ['en'=>'Phone Number','bn'=>'ফোন নম্বর','hi'=>'फोन नंबर','ur'=>'فون نمبر','ar'=>'رقم الهاتف'],
                'phone'                 => ['en'=>'Phone','bn'=>'ফোন','hi'=>'फोन','ur'=>'فون','ar'=>'هاتف'],
                'mobile'                => ['en'=>'Mobile','bn'=>'মোবাইল','hi'=>'मोबाइल','ur'=>'موبائل','ar'=>'موبايل'],
                'quantity'              => ['en'=>'Quantity','bn'=>'সংখ্যা','hi'=>'मात्रा','ur'=>'تعداد','ar'=>'الكمية'],
                'address'               => ['en'=>'Address','bn'=>'ঠিকানা','hi'=>'पता','ur'=>'پتہ','ar'=>'العنوان'],
                'note'                  => ['en'=>'Note','bn'=>'নোট','hi'=>'नोट','ur'=>'نوٹ','ar'=>'ملاحظة'],
                'message'               => ['en'=>'Message','bn'=>'বার্তা','hi'=>'संदेश','ur'=>'پیغام','ar'=>'رسالة'],
                'go_to_site'            => ['en'=>'Return to Merchant','bn'=>'মার্চেন্টে ফিরুন','hi'=>'मर्चेंट पर वापस जाएं','ur'=>'مرچنٹ پر واپس جائیں','ar'=>'العودة إلى التاجر'],
                'download_receipt'      => ['en'=>'Download Receipt','bn'=>'রসিদ ডাউনলোড করুন','hi'=>'रसीद डाउनलोड करें','ur'=>'رسید ڈاؤن لوڈ کریں','ar'=>'تنزيل الإيصال'],
                /* payment_successful covers all completed-status labels; receipt.php uses the same key */
                'payment_pending'       => ['en'=>'Payment Pending','bn'=>'পেমেন্ট বিচারাধীন','hi'=>'भुगतान लंबित','ur'=>'ادائیگی زیر التواء','ar'=>'الدفع معلق'],
                'payment_refunded'      => ['en'=>'Payment Refunded','bn'=>'পেমেন্ট ফেরত দেওয়া হয়েছে','hi'=>'भुगतान वापस किया गया','ur'=>'ادائیگی واپس کر دی گئی','ar'=>'تم استرداد الدفع'],
                'payment_canceled'      => ['en'=>'Payment Cancelled','bn'=>'পেমেন্ট বাতিল','hi'=>'भुगतान रद्द','ur'=>'ادائیگی منسوخ','ar'=>'تم إلغاء الدفع'],
                'select_language'       => ['en'=>'Select Language','bn'=>'ভাষা নির্বাচন করুন','hi'=>'भाषा चुनें','ur'=>'زبان منتخب کریں','ar'=>'اختر لغة'],
                'language'              => ['en'=>'Language','bn'=>'ভাষা','hi'=>'भाषा','ur'=>'زبان','ar'=>'لغة'],
                'select_a_language'     => ['en'=>'Select a language','bn'=>'একটি ভাষা নির্বাচন করুন','hi'=>'एक भाषा चुनें','ur'=>'ایک زبان منتخب کریں','ar'=>'اختر لغة'],
                'back'                  => ['en'=>'Back','bn'=>'ফিরে যান','hi'=>'वापस','ur'=>'واپس','ar'=>'رجوع'],
                'cancel'                => ['en'=>'Cancel','bn'=>'বাতিল করুন','hi'=>'रद्द करें','ur'=>'منسوخ کریں','ar'=>'إلغاء'],
                'customer'              => ['en'=>'Customer','bn'=>'গ্রাহক','hi'=>'ग्राहक','ur'=>'گاہک','ar'=>'العميل'],
                'receipt_id'            => ['en'=>'Receipt ID','bn'=>'রসিদ আইডি','hi'=>'रसीद आईडी','ur'=>'رسید آئی ڈی','ar'=>'معرف الإيصال'],
                'payment_receipt'       => ['en'=>'Payment Receipt','bn'=>'পেমেন্ট রসিদ','hi'=>'भुगतान रसीद','ur'=>'ادائیگی رسید','ar'=>'إيصال الدفع'],
                'receipt_date'          => ['en'=>'Receipt Date','bn'=>'রসিদের তারিখ','hi'=>'रसीद की तारीख','ur'=>'رسید کی تاریخ','ar'=>'تاريخ الإيصال'],
                'print'                 => ['en'=>'Print','bn'=>'প্রিন্ট করুন','hi'=>'प्रिंट करें','ur'=>'پرنٹ کریں','ar'=>'طباعة'],
                'print_receipt'         => ['en'=>'Print Receipt','bn'=>'রসিদ প্রিন্ট করুন','hi'=>'रसीद प्रिंट करें','ur'=>'رسید پرنٹ کریں','ar'=>'طباعة الإيصال'],
                'download_pdf'          => ['en'=>'Download PDF','bn'=>'PDF ডাউনলোড করুন','hi'=>'PDF डाउनलोड करें','ur'=>'PDF ڈاؤن لوڈ کریں','ar'=>'تنزيل PDF'],
                'transaction_details'   => ['en'=>'Transaction Details','bn'=>'লেনদেনের বিবরণ','hi'=>'लेनदेन विवरण','ur'=>'ٹرانزیکشن تفصیلات','ar'=>'تفاصيل المعاملة'],
                'payment_method'        => ['en'=>'Payment Method','bn'=>'পেমেন্ট পদ্ধতি','hi'=>'भुगतान विधि','ur'=>'ادائیگی طریقہ','ar'=>'طريقة الدفع'],
                'total_paid'            => ['en'=>'Total Paid','bn'=>'মোট পরিশোধ','hi'=>'कुल भुगतान','ur'=>'کل ادائیگی','ar'=>'إجمالي المدفوع'],
                'local_amount'          => ['en'=>'Local Amount','bn'=>'স্থানীয় পরিমাণ','hi'=>'स्थानीय राशि','ur'=>'مقامی رقم','ar'=>'المبلغ المحلي'],
                'payment_failed'        => ['en'=>'Payment Failed','bn'=>'পেমেন্ট ব্যর্থ','hi'=>'भुगतान विफल','ur'=>'ادائیگی ناکام','ar'=>'فشل الدفع'],
                'payment_successful'    => ['en'=>'Payment Successful','bn'=>'পেমেন্ট সফল','hi'=>'भुगतान सफल','ur'=>'ادائیگی کامیاب','ar'=>'تمت الدفع بنجاح'],
                'status_label'          => ['en'=>'Status','bn'=>'স্ট্যাটাস','hi'=>'स्थिति','ur'=>'حیثیت','ar'=>'الحالة'],
                'redirecting_in'        => ['en'=>'Redirecting to merchant in','bn'=>'মার্চেন্টে যাচ্ছেন','hi'=>'मर्चेंट पर जा रहे हैं','ur'=>'تاجر کی طرف جا رہے ہیں','ar'=>'التوجيه للتاجر في'],
                'seconds'               => ['en'=>'seconds','bn'=>'সেকেন্ড','hi'=>'सेकंड','ur'=>'سیکنڈ','ar'=>'ثوان'],
                'view_receipt'          => ['en'=>'View Full Receipt','bn'=>'রসিদ দেখুন','hi'=>'रसीद देखें','ur'=>'رسید دیکھیں','ar'=>'عرض الإيصال'],
                'subtotal'              => ['en'=>'Subtotal','bn'=>'সাবটোটাল','hi'=>'उप-योग','ur'=>'ذیلی کل','ar'=>'المجموع الفرعي'],
                /* ── Nav pill labels ── */
                'tab_details'           => ['en'=>'Transaction Details','bn'=>'লেনদেনের বিবরণ','hi'=>'लेनदेन विवरण','ur'=>'لین دین کی تفصیلات','ar'=>'تفاصيل المعاملة'],
                'tab_faq'               => ['en'=>'FAQ','bn'=>'সচরাচর জিজ্ঞাসা','hi'=>'सामान्य प्रश्न','ur'=>'عمومی سوالات','ar'=>'الأسئلة الشائعة'],
                'tab_support'           => ['en'=>'Help & Support','bn'=>'সাহায্য ও সহায়তা','hi'=>'सहायता और समर्थन','ur'=>'مدد اور سپورٹ','ar'=>'المساعدة والدعم'],
                /* ── Section headings ── */
                'section_details'       => ['en'=>'Transaction Details','bn'=>'লেনদেনের বিবরণ','hi'=>'लेनदेन विवरण','ur'=>'لین دین کی تفصیلات','ar'=>'تفاصيل المعاملة'],
                'section_faq'           => ['en'=>'Frequently Asked Questions','bn'=>'সচরাচর জিজ্ঞাসা','hi'=>'अक्सर पूछे जाने वाले प्रश्न','ur'=>'اکثر پوچھے جانے والے سوالات','ar'=>'الأسئلة المتداولة'],
                'section_support'       => ['en'=>'Help & Support','bn'=>'সাহায্য ও সহায়তা','hi'=>'सहायता और समर्थन','ur'=>'مدد اور سپورٹ','ar'=>'المساعدة والدعم'],
                /* ── Empty states ── */
                'no_faqs_title'         => ['en'=>'No FAQs Published','bn'=>'কোনো FAQ প্রকাশিত নেই','hi'=>'कोई FAQ प्रकाशित नहीं','ur'=>'کوئی FAQ شائع نہیں','ar'=>'لا توجد أسئلة شائعة'],
                'no_faqs_desc'          => ['en'=>'This merchant has not published any frequently asked questions.','bn'=>'এই মার্চেন্ট কোনো সচরাচর জিজ্ঞাসা প্রকাশ করেননি।','hi'=>'इस व्यापारी ने कोई अक्सर पूछे जाने वाले प्रश्न प्रकाशित नहीं किए।','ur'=>'اس مرچنٹ نے کوئی عمومی سوالات شائع نہیں کیے۔','ar'=>'لم ينشر هذا التاجر أي أسئلة شائعة.'],
                'no_support_title'      => ['en'=>'No Support Channels Configured','bn'=>'কোনো সাপোর্ট চ্যানেল কনফিগার করা হয়নি','hi'=>'कोई सहायता चैनल कॉन्फ़िगर नहीं','ur'=>'کوئی سپورٹ چینل ترتیب نہیں دیا گیا','ar'=>'لا توجد قنوات دعم مُهيأة'],
                'no_support_desc'       => ['en'=>'This merchant has not provided any contact information.','bn'=>'এই মার্চেন্ট কোনো যোগাযোগের তথ্য প্রদান করেননি।','hi'=>'इस व्यापारी ने कोई संपर्क जानकारी नहीं दी।','ur'=>'اس مرچنٹ نے کوئی رابطہ معلومات فراہم نہیں کی۔','ar'=>'لم يقدم هذا التاجر أي معلومات اتصال.'],
                'no_gateways_title'     => ['en'=>'No Payment Methods Available','bn'=>'কোনো পেমেন্ট পদ্ধতি উপলব্ধ নেই','hi'=>'कोई भुगतान विधि उपलब्ध नहीं','ur'=>'کوئی ادائیگی طریقہ دستیاب نہیں','ar'=>'لا توجد طرق دفع متاحة'],
                'no_gateways_desc'      => ['en'=>'No payment methods have been configured for this transaction.','bn'=>'এই লেনদেনের জন্য কোনো পেমেন্ট পদ্ধতি কনফিগার করা হয়নি।','hi'=>'इस लेनदेन के लिए कोई भुगतान विधि कॉन्फ़िगर नहीं की गई।','ur'=>'اس لین دین کے لیے کوئی ادائیگی طریقہ ترتیب نہیں دیا گیا۔','ar'=>'لم يتم تهيئة أي طرق دفع لهذه المعاملة.'],
                /* select_payment_method is used everywhere; select_gateway was an unused alias */
                'name_label'            => ['en'=>'Name','bn'=>'নাম','hi'=>'नाम','ur'=>'نام','ar'=>'الاسم'],
                'email_label'           => ['en'=>'Email','bn'=>'ইমেইল','hi'=>'ईमेल','ur'=>'ای میل','ar'=>'البريد الإلكتروني'],
                'mobile_label'          => ['en'=>'Mobile','bn'=>'মোবাইল','hi'=>'मोबाइल','ur'=>'موبائل','ar'=>'الهاتف'],
                'close'                 => ['en'=>'Close','bn'=>'বন্ধ করুন','hi'=>'बंद करें','ur'=>'بند کریں','ar'=>'إغلاق'],
                'payable'               => ['en'=>'Payable','bn'=>'প্রদেয়','hi'=>'देय','ur'=>'قابل ادائیگی','ar'=>'المستحق'],
                'change_status_completed'=> ['en'=>'Your payment has been received and confirmed successfully.','bn'=>'আপনার পেমেন্ট সফলভাবে গ্রহণ এবং নিশ্চিত করা হয়েছে।','hi'=>'आपका भुगतान सफलतापूर्वक प्राप्त और पुष्टि किया गया है।','ur'=>'آپ کی ادائیگی کامیابی سے موصول اور تصدیق ہو گئی ہے۔','ar'=>'تم استلام دفعتك وتأكيدها بنجاح.'],
                'change_status_pending' => ['en'=>'Your payment is currently being processed. Please allow a moment for confirmation.','bn'=>'আপনার পেমেন্ট প্রক্রিয়া করা হচ্ছে। নিশ্চিতকরণের জন্য একটু অপেক্ষা করুন।','hi'=>'आपका भुगतान संसाधित किया जा रहा है। पुष्टि के लिए कृपया प्रतीक्षा करें।','ur'=>'آپ کی ادائیگی پر کارروائی ہو رہی ہے۔ تصدیق کا انتظار کریں۔','ar'=>'يتم معالجة دفعتك حاليًا. يرجى الانتظار للحصول على التأكيد.'],
                'change_status_refunded'=> ['en'=>'Your payment has been successfully refunded to your original payment method.','bn'=>'আপনার পেমেন্ট আপনার মূল পেমেন্ট পদ্ধতিতে সফলভাবে ফেরত দেওয়া হয়েছে।','hi'=>'आपका भुगतान आपके मूल भुगतान विधि में वापस कर दिया गया है।','ur'=>'آپ کی ادائیگی آپ کے اصل ادائیگی طریقے پر کامیابی سے واپس کر دی گئی ہے۔','ar'=>'تم استرداد دفعتك بنجاح إلى طريقة الدفع الأصلية.'],
                'change_status_canceled' => ['en'=>'Your payment was cancelled. No amount has been charged to your account.','bn'=>'আপনার পেমেন্ট বাতিল করা হয়েছে। আপনার অ্যাকাউন্ট থেকে কোনো অর্থ কাটা হয়নি।','hi'=>'आपका भुगतान रद्द कर दिया गया। आपके खाते से कोई राशि नहीं काटी गई।','ur'=>'آپ کی ادائیگی منسوخ کر دی گئی ہے۔ آپ کے اکاؤنٹ سے کوئی رقم نہیں کاٹی گئی۔','ar'=>'تم إلغاء دفعتك. لم يتم خصم أي مبلغ من حسابك.'],
                'change_status_failed'   => ['en'=>'Your payment could not be completed. Please try again or contact the merchant for assistance.','bn'=>'আপনার পেমেন্ট সম্পন্ন হয়নি। পুনরায় চেষ্টা করুন অথবা মার্চেন্টের সাথে যোগাযোগ করুন।','hi'=>'आपका भुगतान पूर्ण नहीं हो सका। पुनः प्रयास करें या मर्चेंट से संपर्क करें।','ur'=>'آپ کی ادائیگی مکمل نہیں ہو سکی۔ دوبارہ کوشش کریں یا مرچنٹ سے رابطہ کریں۔','ar'=>'تعذّر إتمام دفعتك. يرجى المحاولة مرة أخرى أو التواصل مع التاجر للمساعدة.'],
            ];
        }

        public function renderCheckout($data)
        {
            // Only allow receipt/invoice view on non-initiated transactions
            if (isset($_GET['view_receipt']) && $data['transaction']['status'] !== 'initiated') {
                include __DIR__ . '/receipt.php'; return;
            }
            if ($data['transaction']['status'] === 'initiated') {
                if (isset($_GET['gateway'])) {
                    global $db_prefix;
                    $p = [':gateway_id' => $_GET['gateway'], ':brand_id' => $data['brand']['id']];
                    $r = json_decode(getData($db_prefix.'gateways', 'WHERE gateway_id = :gateway_id AND brand_id = :brand_id AND status = "active"', 'slug FROM', $p), true);
                    $slug = ($r['status'] && isset($r['response'][0]['slug'])) ? $r['response'][0]['slug'] : '';
                    if ($slug === 'bangla-qr') {
                        include __DIR__ . '/qr-gateway.php';
                    } else {
                        include __DIR__ . '/gateway.php';
                    }
                } else { include __DIR__ . '/checkout.php'; }
            } else {
                include __DIR__ . '/checkout-status.php';
            }
        }

        public function renderInvoice($data)
        {
            include __DIR__ . '/receipt.php';
        }

        public function renderPaymentLink($data) { include __DIR__ . '/payment-link.php'; }
        public function renderPaymentLinkDefault($data) { include __DIR__ . '/payment-link-default.php'; }
    }
