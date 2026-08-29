<?php
class BanglaQrGateway
{
    public function info()
    {
        return [
            'title'       => 'Bangla QR',
            'logo'        => 'assets/logo.jpg',
            'currency'    => 'BDT',
            'tab'         => 'mfs',
            'gateway_type' => 'bangla-qr',
        ];
    }

    public function color()
    {
        return [
            'primary_color'  => '#128024',
            'text_color'     => '#FFFFFF',
            'btn_color'      => '#128024',
            'btn_text_color' => '#FFFFFF',
        ];
    }

    public function fields()
    {
        return [
            [
                'name'  => 'qr_code',
                'label' => 'QR Code Image',
                'type'  => 'image',
            ],
            [
                'name'  => 'raw_payload',
                'label' => 'Decoded QR Payload',
                'type'  => 'textarea',
                'readonly' => true,
                'hint'  => 'Auto-populated when QR image is uploaded. Read-only for reference.',
            ],
            [
                'name'     => 'provider',
                'label'    => 'Provider',
                'type'     => 'select',
                'options'  => array_map(fn($p) => $p['name'], senderWhitelist()),
                'value'    => '',
                'required' => true,
                'multiple' => false,
                'hint'     => 'Select your payment provider. This is required.',
            ],
            [
                'name'  => 'poll_interval',
                'label' => 'Verification Interval (sec)',
                'type'  => 'text',
                'value' => '4',
            ],
            [
                'name'  => 'auto_cancel_minutes',
                'label' => 'Session Timeout (min)',
                'type'  => 'text',
                'value' => '8',
            ],
            [
                'name'  => 'fallback_after_seconds',
                'label' => 'Manual Verify After (sec)',
                'type'  => 'text',
                'value' => '30',
            ],
            [
                'name'     => 'unique_amount_type',
                'label'    => 'Unique Amount Type',
                'type'     => 'select',
                'options'  => [
                    'decimal' => 'Decimal (e.g. 100.04)',
                    'integer' => 'Integer (e.g. 101)',
                ],
                'value'    => 'decimal',
                'required' => true,
                'multiple' => false,
            ],
            [
                'name'  => 'unique_amount_slots',
                'label' => 'Unique Amount Slots',
                'type'  => 'text',
                'value' => '50',
            ],
            [
                'name'     => 'fallback_verify_method',
                'label'    => 'Fallback Verification',
                'type'     => 'select',
                'options'  => [
                    'phone' => 'Phone Number Only',
                    'trxid' => 'Transaction ID Only',
                ],
                'value'    => 'phone',
                'required' => true,
                'multiple' => false,
            ],
        ];
    }

    public function supported_languages()
    {
        return [
            'en' => 'English',
            'bn' => 'বাংলা',
        ];
    }

    public function lang_text()
    {
        return [
            'title' => [
                'en' => 'Pay with Bangla QR',
                'bn' => 'Bangla QR দিয়ে দিন',
            ],
            'steps' => [
                'en' => 'Open App › Scan QR › Confirm',
                'bn' => 'অ্যাপ খুলুন › QR স্ক্যান করুন › নিশ্চিত করুন',
            ],
            'pay_exact' => [
                'en' => 'Scan the QR code and pay exactly {amount} {currency} to complete your payment.',
                'bn' => 'QR কোড স্ক্যান করুন এবং আপনার পেমেন্ট সম্পন্ন করতে সঠিক {amount} {currency} পেমেন্ট করুন।',
            ],
            'waiting_payment' => [
                'en' => 'Waiting for payment...',
                'bn' => 'পেমেন্টের জন্য অপেক্ষা...',
            ],
            'taking_too_long' => [
                'en' => 'Payment not detected?',
                'bn' => 'পেমেন্ট সনাক্ত হয়নি?',
            ],
            'submit_manually' => [
                'en' => 'Verify manually',
                'bn' => 'ম্যানুয়ালি যাচাই করুন',
            ],
            'enter_phone' => [
                'en' => 'Submit phone number or bank account',
                'bn' => 'মোবাইল নম্বর বা ব্যাংক অ্যাকাউন্ট দিন',
            ],
            'enter_trxid' => [
                'en' => 'Enter Transaction ID',
                'bn' => 'ট্রানজ্যাকশন আইডি লিখুন',
            ],
            'pay_now' => [
                'en' => 'Pay Now',
                'bn' => 'এখনই পেমেন্ট করুন',
            ],
            'verifying' => [
                'en' => 'Verifying...',
                'bn' => 'যাচাই করা হচ্ছে...',
            ],
            'verify' => [
                'en' => 'Verify',
                'bn' => 'যাচাই করুন',
            ],
            'error' => [
                'en' => 'Error',
                'bn' => 'ত্রুটি',
            ],
            'something_wrong' => [
                'en' => 'Something went wrong. Please try again.',
                'bn' => 'কিছু ভুল হয়েছে। আবার চেষ্টা করুন।',
            ],
            'step_1' => [
                'en' => 'Open your preferred Bank or MFS App.',
                'bn' => 'আপনার পছন্দের ব্যাংক বা এমএফএস অ্যাপ খুলুন।',
            ],
            'step_2' => [
                'en' => 'Scan the QR Code. Amount {amount} {currency} will be auto-filled.',
                'bn' => 'QR কোড স্ক্যান করুন। পরিমাণ {amount} {currency} অটো-ফিল হবে।',
            ],
            'step_3' => [
                'en' => 'Confirm payment. We will auto-verify instantly.',
                'bn' => 'পেমেন্ট নিশ্চিত করুন। আমরা তাৎক্ষণিক অটো-যাচাই করব।',
            ],
            'keep_window' => [
                'en' => 'Please do not close this window. We will automatically verify your payment once it’s complete.',
                'bn' => 'অনুগ্রহ করে এই উইন্ডো বন্ধ করবেন না। পেমেন্ট সম্পন্ন হলে আমরা স্বয়ংক্রিয়ভাবে যাচাই করব।',
            ],
        ];
    }

    public function instructions($data)
    {
        $lang = $data['lang'] ?? [];
        $amount = number_format((float)($data['transaction']['local_net_amount'] ?? 0), 2);
        $currency = $data['transaction']['local_currency'] ?? 'BDT';

        return [
            // Step 1
            [
                'text' => $lang['step_1'] ?? 'Open your preferred Bank or MFS App.',
                'icon' => '',
                'copy' => false,
            ],
            // Step 2
            [
                'text' => str_replace(['{amount}','{currency}'], [$amount, $currency], $lang['step_2'] ?? 'Scan the QR Code. Amount {amount} {currency} will be auto-filled.'),
                'icon' => '',
                'copy' => false,
            ],
            // Step 3
            [
                'text' => $lang['step_3'] ?? 'Confirm payment. We will auto-verify instantly.',
                'icon' => '',
                'copy' => false,
            ],
        ];
    }


}
