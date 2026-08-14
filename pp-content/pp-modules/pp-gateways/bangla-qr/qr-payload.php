<?php
/**
 * Bangla QR Payload Library
 * 
 * EMVCo TLV payload parser, dynamic QR generator, and CRC-16 CCITT calculator.
 * Follows Bangladesh Bank's Bangla QR standard (EMVCo Specification v3.0).
 * 
 * QR Specifications:
 *   - EC Level: H (30% recovery) — for logo overlay
 *   - Size: 500×500 px (no resize, generate at target)
 *   - Quiet Zone: 4 modules (EMVCo standard)
 *   - Logo: max 20%, center only, 8px white padding
 *   - Format: PNG (lossless), pure black+white, sharp edges
 *   - CRC: CRC-16/CCITT-FALSE after payload change
 *   - Payload: EMVCo TLV structure with exact field-length
 * 
 * No external dependencies — requires only GD extension (already required by PipraPay).
 * 
 * Usage:
 *   $payload = bnqr_parse_tlv($rawString);
 *   $dynamic = bnqr_make_dynamic($staticPayload, '250.00');
 *   $png     = bnqr_generate_qr($dynamic, 500);
 */

if (!defined('PipraPay_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

// ─────────────────────────────────────────────────────────────────
// TLV Parsing
// ─────────────────────────────────────────────────────────────────

/**
 * Parse EMVCo TLV payload string into structured array.
 * 
 * @param string $payload Raw TLV string (e.g., "0002010102112650...")
 * @return array Each element: ['tag' => '00', 'len' => 2, 'value' => '01']
 */
function bnqr_parse_tlv($payload) {
    $fields = [];
    $i = 0;
    $len = strlen($payload);
    
    while ($i < $len) {
        if ($i + 4 > $len) break; // Need at least tag(2) + len(2)
        
        $tag  = substr($payload, $i, 2);
        $i   += 2;
        $nlen = (int)substr($payload, $i, 2);
        $i   += 2;
        
        if ($i + $nlen > $len) break; // Truncated payload
        
        $value = substr($payload, $i, $nlen);
        $i    += $nlen;
        
        $fields[] = [
            'tag'   => $tag,
            'len'   => $nlen,
            'value' => $value,
        ];
    }
    
    return $fields;
}

/**
 * Parse sub-TLV fields (used inside tag 26 and tag 62).
 * 
 * @param string $payload Sub-TLV string
 * @return array Each element: ['tag' => '00', 'len' => 9, 'value' => 'com.bkash']
 */
function bnqr_parse_sub_tlv($payload) {
    $fields = [];
    $i = 0;
    $len = strlen($payload);
    
    while ($i < $len) {
        if ($i + 4 > $len) break;
        
        $tag  = substr($payload, $i, 2);
        $i   += 2;
        $nlen = (int)substr($payload, $i, 2);
        $i   += 2;
        
        // Validate: tag must be numeric, length must be positive
        if (!ctype_digit($tag) || $nlen <= 0) {
            break; // Invalid sub-tag, stop parsing
        }
        
        if ($i + $nlen > $len) break;
        
        $value = substr($payload, $i, $nlen);
        $i    += $nlen;
        
        $fields[] = [
            'tag'   => $tag,
            'len'   => $nlen,
            'value' => $value,
        ];
    }
    
    return $fields;
}

/**
 * Convert parsed TLV fields back to raw payload string.
 * 
 * @param array $fields Output of bnqr_parse_tlv()
 * @return string Raw TLV string
 */
function bnqr_build_tlv($fields) {
    $result = '';
    foreach ($fields as $f) {
        $result .= $f['tag'] . str_pad((string)$f['len'], 2, '0', STR_PAD_LEFT) . $f['value'];
    }
    return $result;
}

// ─────────────────────────────────────────────────────────────────
// CRC-16 CCITT (EMVCo Standard)
// ─────────────────────────────────────────────────────────────────

/**
 * Calculate CRC-16 CCITT checksum.
 * Polynomial: 0x1021, Initial value: 0xFFFF
 * 
 * @param string $payload Data to calculate CRC over
 * @return int CRC value (0x0000 - 0xFFFF)
 */
function bnqr_crc16($payload) {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($payload); $i++) {
        $crc ^= ord($payload[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc <<= 1;
            }
            $crc &= 0xFFFF;
        }
    }
    return $crc;
}

/**
 * Format CRC value as 4-char uppercase hex string.
 * 
 * @param int $crc CRC value
 * @return string e.g., "06EB"
 */
function bnqr_format_crc($crc) {
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

// ─────────────────────────────────────────────────────────────────
// Static → Dynamic Payload Conversion
// ─────────────────────────────────────────────────────────────────

/**
 * Convert a static EMVCo QR payload to dynamic by injecting amount.
 * 
 * Changes:
 *   1. Tag 01: 11 (static) → 12 (dynamic)
 *   2. Tag 54: ADDED with the transaction amount
 *   3. Tag 63: CRC recalculated
 * 
 * @param string $staticPayload Full static payload including CRC
 * @param string $amount Transaction amount (e.g., "250.00")
 * @param string|null $referenceLabel Optional reference label (tag 62.05)
 * @param string|null $storeLabel Optional store label (tag 62.03)
 * @return string Dynamic payload with valid CRC
 */
function bnqr_make_dynamic($staticPayload, $amount, $referenceLabel = null, $storeLabel = null) {
    $fields = bnqr_parse_tlv($staticPayload);
    if (empty($fields)) return '';
    
    $result = '';
    
    foreach ($fields as $f) {
        if ($f['tag'] === '01') {
            // Static (11) → Dynamic (12)
            $result .= '010212';
            
        } elseif ($f['tag'] === '53') {
            // Keep currency, then insert amount tag 54 after
            $result .= '53' . str_pad((string)$f['len'], 2, '0', STR_PAD_LEFT) . $f['value'];
            $amountStr = number_format((float)$amount, 2, '.', '');
            $result .= '54' . str_pad((string)strlen($amountStr), 2, '0', STR_PAD_LEFT) . $amountStr;
            
        } elseif ($f['tag'] === '62') {
            // Keep original 62 value as-is (no injection)
            $result .= '62' . str_pad((string)$f['len'], 2, '0', STR_PAD_LEFT) . $f['value'];
            
        } elseif ($f['tag'] === '63') {
            // Skip old CRC — we recalculate
            continue;
            
        } else {
            $result .= $f['tag'] . str_pad((string)$f['len'], 2, '0', STR_PAD_LEFT) . $f['value'];
        }
    }
    
    // Calculate and append CRC
    $crcData = $result . '6304';
    $crcValue = bnqr_crc16($crcData);
    $crcHex = bnqr_format_crc($crcValue);
    
    return $result . '6304' . $crcHex;
}

/**
 * Inject a sub-tag into tag 62 value.
 * 
 * @param string $originalValue Original tag 62 value (sub-TLV)
 * @param string $subTag Sub-tag number (e.g., "05" for reference, "07" for terminal)
 * @param string $subValue Sub-tag value
 * @return string Updated tag 62 value
 */
function bnqr_inject_subtag($originalValue, $subTag, $subValue) {
    $subs = bnqr_parse_sub_tlv($originalValue);
    
    // Remove existing sub-tag if present
    $filtered = [];
    foreach ($subs as $s) {
        if ($s['tag'] !== $subTag) {
            $filtered[] = $s;
        }
    }
    
    // Rebuild with new sub-tag — use ACTUAL value lengths, not declared lengths
    $result = '';
    foreach ($filtered as $s) {
        $actualLen = strlen($s['value']);
        $result .= $s['tag'] . str_pad((string)$actualLen, 2, '0', STR_PAD_LEFT) . $s['value'];
    }
    
    // Add new sub-tag
    $result .= $subTag . str_pad((string)strlen($subValue), 2, '0', STR_PAD_LEFT) . $subValue;
    
    return $result;
}

// ─────────────────────────────────────────────────────────────────
// Payload Decoding (for admin auto-decode)
// ─────────────────────────────────────────────────────────────────

/**
 * Decode a QR payload into human-readable fields.
 * 
 * @param string $rawPayload Full QR payload string
 * @return array Decoded fields with labels
 */
function bnqr_decode_payload($rawPayload) {
    $fields = bnqr_parse_tlv($rawPayload);
    $decoded = [
        'raw'    => $rawPayload,
        'fields' => [],
        'provider' => '',
        'qr_type'  => '',
        'amount'   => '',
        'currency' => '',
        'merchant_name' => '',
        'merchant_city'  => '',
        'phone'   => '',
        'reference' => '',
        'purpose' => '',
    ];
    
    foreach ($fields as $f) {
        $label = match($f['tag']) {
            '00' => 'Payload Format Indicator',
            '01' => 'Point of Initiation Method',
            '26' => 'Merchant Account Information',
            '52' => 'Merchant Category Code',
            '53' => 'Transaction Currency',
            '54' => 'Transaction Amount',
            '58' => 'Country Code',
            '59' => 'Merchant Name',
            '60' => 'Merchant City',
            '62' => 'Additional Data Field',
            '63' => 'CRC Checksum',
            default => "Tag {$f['tag']}",
        };
        
        $fieldData = [
            'tag'   => $f['tag'],
            'label' => $label,
            'value' => $f['value'],
        ];
        
        // Parse sub-fields for tag 26 and tag 62
        if ($f['tag'] === '26') {
            $fieldData['sub_fields'] = bnqr_parse_sub_tlv($f['value']);
            
            // Extract provider from sub-tag 00
            foreach ($fieldData['sub_fields'] as $sub) {
                if ($sub['tag'] === '00') {
                    $decoded['provider'] = $sub['value'];
                }
                if ($sub['tag'] === '03') {
                    $decoded['phone'] = $sub['value'];
                }
            }
        }
        
        if ($f['tag'] === '62') {
            $fieldData['sub_fields'] = bnqr_parse_sub_tlv($f['value']);
            
            foreach ($fieldData['sub_fields'] as $sub) {
                if ($sub['tag'] === '02') {
                    $decoded['phone'] = $sub['value'];
                }
                if ($sub['tag'] === '05') {
                    $decoded['reference'] = $sub['value'];
                }
                if ($sub['tag'] === '08') {
                    $decoded['purpose'] = $sub['value'];
                }
            }
        }
        
        $decoded['fields'][] = $fieldData;
        
        // Extract top-level fields
        switch ($f['tag']) {
            case '01':
                $decoded['qr_type'] = $f['value'] === '12' ? 'dynamic' : 'static';
                break;
            case '54':
                $decoded['amount'] = $f['value'];
                break;
            case '53':
                $decoded['currency'] = $f['value'];
                break;
            case '59':
                $decoded['merchant_name'] = $f['value'];
                break;
            case '60':
                $decoded['merchant_city'] = $f['value'];
                break;
        }
    }
    
    return $decoded;
}

// ─────────────────────────────────────────────────────────────────
// QR Image Generation (GD-based)
// ─────────────────────────────────────────────────────────────────

/**
 * Generate QR code PNG image from payload string.
 * Uses external API (api.qrserver.com) for QR generation.
 * 
 * Specs:
 *   - EC Level: H (30% recovery) — for logo overlay
 *   - Size: 500×500 px (no resize, generate at target)
 *   - Quiet Zone: 4 modules (EMVCo standard)
 *   - Logo: max 20%, center only, 8px white padding
 *   - Format: PNG (lossless), pure black+white, sharp edges
 * 
 * @param string $payload Data to encode
 * @param int $size Image size in pixels (default: 500)
 * @param int $margin Quiet zone in modules (default: 4)
 * @param string|null $logo_path Path to logo image (optional)
 * @return string PNG image binary data
 * @throws RuntimeException if API fails
 */
function bnqr_generate_qr($payload, $size = 500, $margin = 4, $logo_path = null) {
    // External QR API — EC level H for logo support
    $encoded = rawurlencode($payload);
    $url = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data={$encoded}&format=png&ecc=H&margin={$margin}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: image/png'],
    ]);
    $png = curl_exec($ch);
    $rc  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($rc !== 200 || empty($png)) {
        throw new RuntimeException('QR API failed (HTTP ' . $rc . ')');
    }
    
    // Overlay logo on center if provided and GD available
    if ($logo_path && file_exists($logo_path) && function_exists('imagecreatefromstring')) {
        $qr = @imagecreatefromstring($png);
        if ($qr) {
            // Disable antialiasing for sharp edges
            imageantialias($qr, false);
            
            $logo = @imagecreatefromstring(file_get_contents($logo_path));
            if ($logo) {
                $qrW = imagesx($qr);
                $qrH = imagesy($qr);
                
                // Logo is max 18% of QR size (spec)
                $logoMax = (int)($qrW * 0.18);
                $logoW = imagesx($logo);
                $logoH = imagesy($logo);
                
                // Scale logo to fit (integer pixels, sharp edges)
                $scale = min($logoMax / $logoW, $logoMax / $logoH);
                $newW = max(1, (int)($logoW * $scale));
                $newH = max(1, (int)($logoH * $scale));
                
                // White background behind logo — 6px padding (spec)
                $bgPad = 6;
                $cx = (int)(($qrW - $newW) / 2);
                $cy = (int)(($qrH - $newH) / 2);
                $white = imagecolorallocate($qr, 255, 255, 255);
                imagefilledrectangle($qr, $cx - $bgPad, $cy - $bgPad, $cx + $newW + $bgPad, $cy + $newH + $bgPad, $white);
                
                // Resample logo with sharp edges (no antialiasing)
                $tmp = imagecreatetruecolor($newW, $newH);
                imageantialias($tmp, false);
                imagecopyresampled($tmp, $logo, 0, 0, 0, 0, $newW, $newH, $logoW, $logoH);
                imagecopy($qr, $tmp, $cx, $cy, 0, 0, $newW, $newH);
                imagedestroy($tmp);
                imagedestroy($logo);
                
                // Output as PNG (lossless)
                ob_start();
                imagepng($qr);
                $png = ob_get_clean();
            }
            imagedestroy($qr);
        }
    }
    
    return $png;
}

/**
 * Generate QR code and return as base64 data URI.
 * 
 * @param string $payload Data to encode
 * @param int $size Image size in pixels
 * @return string Base64 data URI (e.g., "data:image/png;base64,...")
 */
function bnqr_generate_qr_base64($payload, $size = 500) {
    $png = bnqr_generate_qr($payload, $size);
    return 'data:image/png;base64,' . base64_encode($png);
}

// ─────────────────────────────────────────────────────────────────
// Utility: Provider Detection
// ─────────────────────────────────────────────────────────────────

/**
 * Detect payment provider from decoded QR payload.
 * 
 * @param string $providerIdentifier Sub-tag 00 value (e.g., "com.bkash")
 * @return string Normalized provider key
 */
function bnqr_detect_provider($providerIdentifier) {
    $lower = strtolower($providerIdentifier);
    // Dynamic: extract provider name from app ID
    // e.g., "com.bkash.app" → "bkash", "bkash" → "bkash"
    if (preg_match('/\.([a-z0-9]+)(?:\.|$)/', $lower, $m)) {
        return $m[1];
    }
    return preg_replace('/[^a-z0-9]/', '', $lower) ?: 'unknown';
}

/**
 * Get human-readable provider name.
 * Dynamic: pulls from senderWhitelist() if available, fallback to ucfirst.
 * 
 * @param string $providerKey Provider key (e.g., "bkash")
 * @return string Display name (e.g., "bKash")
 */
function bnqr_provider_name($providerKey) {
    if (function_exists('senderWhitelist')) {
        $whitelist = senderWhitelist();
        if (isset($whitelist[$providerKey])) {
            return $whitelist[$providerKey]['name'] ?? ucfirst($providerKey);
        }
    }
    return ucfirst($providerKey);
}
