<?php
/**
 * Bangla QR Gateway Controller
 * 
 * Standalone controller for Bangla QR payment verification.
 * All logic isolated in this file — zero modification to existing files.
 * 
 * Flow:
 * 1. User scans QR → pays via any app (bKash, Nagad, etc.)
 * 2. SMS reader captures transaction in sms_data table
 * 3. User enters phone number on checkout page
 * 4. System matches: provider + phone + amount → auto verify
 * 5. Fallback: manual trxid entry if no match
 */

// Direct access guard
if (!defined('PipraPay_INIT')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

/**
 * Ensure gateways_data table exists — create if missing
 */
function bnqr_ensure_table() {
    global $db_prefix;
    $pdo = connectDatabase();
    $table = $db_prefix.'gateways_data';
    
    // Check if table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) return; // Table exists
    } catch (Exception $e) {
        // Table doesn't exist, continue to create
    }
    
    // Create table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$table` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `gateway_id` VARCHAR(50) NOT NULL,
        `ref` VARCHAR(100) NOT NULL,
        `unique_amount` DECIMAL(10,2) DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'pending',
        `created_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_date` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_gateway_id` (`gateway_id`),
        KEY `idx_ref` (`ref`),
        KEY `idx_unique_amount` (`unique_amount`),
        KEY `idx_status` (`status`),
        UNIQUE KEY `uniq_gw_amount` (`gateway_id`, `unique_amount`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Check if two phone numbers match (handles masked SMS numbers)
 * SMS may show: 0177***4073, 017769****, etc.
 * Matches by first 3 + last 4 digits
 */
function bnqr_phone_match($user_phone, $sms_number) {
    // Strip all non-digits from user phone
    $clean_user = preg_replace('/\D/', '', $user_phone);
    
    // Handle masked numbers like 017***4073 or 410*****2456
    if (strpos($sms_number, '*') !== false) {
        // Extract first part (before *) and last part (after *)
        $parts = explode('*', $sms_number);
        $first = preg_replace('/\D/', '', $parts[0]); // digits before first *
        $last  = preg_replace('/\D/', '', end($parts)); // digits after last *
        
        // Need at least 3 digits from first + 4 from last
        if (strlen($first) < 3 || strlen($last) < 4) return false;
        
        // User must start with same first digits and end with same last digits
        $user_starts = substr($clean_user, 0, strlen($first));
        $user_ends   = substr($clean_user, -strlen($last));
        
        return ($user_starts === $first && $user_ends === $last);
    }
    
    // Unmasked: strip all non-digits
    $clean_sms = preg_replace('/\D/', '', $sms_number);
    
    // Need at least 7 digits to match (3 first + 4 last)
    if (strlen($clean_user) < 7 || strlen($clean_sms) < 7) return false;
    
    // Match first 3 + last 4
    $user_first3 = substr($clean_user, 0, 3);
    $sms_first3  = substr($clean_sms, 0, 3);
    $user_last4  = substr($clean_user, -4);
    $sms_last4   = substr($clean_sms, -4);
    
    return ($user_first3 === $sms_first3 && $user_last4 === $sms_last4);
}

/**
 * Wrap handler to ensure clean JSON output (no stray whitespace/HTML)
 */
function bnqr_safe_json($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE);
}

/**
 * Handle verification requests from frontend
 */
function bnqr_handle_verify($data = null) {
    global $db_prefix;

    $gateway_id   = escape_string($_POST['gateway_id'] ?? '');
    $transaction_id = escape_string($_POST['transaction_id'] ?? '');
    $provider     = escape_string($_POST['provider'] ?? '');
    $phone        = escape_string($_POST['mobile_number'] ?? '');
    $trxid        = escape_string($_POST['trxid'] ?? '');
    $verify_mode  = escape_string($_POST['verify_mode'] ?? 'auto'); // auto | phone | trxid

    // Validate phone number format (Bangladesh: 01XXXXXXXXX)
    if (($verify_mode === 'auto' || $verify_mode === 'phone') && !empty($phone)) {
        $phone = preg_replace('/[\s\-\+]/', '', $phone); // strip formatting
        if (strpos($phone, '880') === 0) $phone = '0' . substr($phone, 3); // 8801... → 01...
        if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
            return bnqr_safe_json([
                'status'  => 'false',
                'title'   => 'Invalid Number',
                'message' => 'Please enter a valid Bangladeshi mobile number (01XXXXXXXXX).'
            ]);
        }
    }

    if (empty($gateway_id) || empty($transaction_id)) {
        return bnqr_safe_json([
            'status'  => 'false',
            'title'   => 'Missing Information',
            'message' => 'Gateway ID and Transaction ID are required.'
        ]);
    }

    // Fetch transaction
    $params = [':ref' => $transaction_id, ':status' => 'initiated'];
    $response_txn = json_decode(getData($db_prefix.'transaction', 'WHERE ref = :ref AND status = :status', '* FROM', $params), true);

    if ($response_txn['status'] !== true) {
        return bnqr_safe_json([
            'status'  => 'false',
            'title'   => 'Transaction Not Found',
            'message' => 'This transaction has already been processed or does not exist.'
        ]);
    }

    $transaction = $response_txn['response'][0];

    // Fetch brand
    $params = [':brand_id' => $transaction['brand_id']];
    $response_brand = json_decode(getData($db_prefix.'brands', 'WHERE brand_id = :brand_id', '* FROM', $params), true);

    if ($response_brand['status'] !== true) {
        return bnqr_safe_json([
            'status'  => 'false',
            'title'   => 'Brand Not Found',
            'message' => 'Invalid brand configuration.'
        ]);
    }

    // Fetch gateway config
    $params = [':gateway_id' => $gateway_id, ':brand_id' => $transaction['brand_id']];
    $response_gw = json_decode(getData($db_prefix.'gateways', 'WHERE gateway_id = :gateway_id AND brand_id = :brand_id AND status = "active"', '* FROM', $params), true);

    if ($response_gw['status'] !== true) {
        return bnqr_safe_json([
            'status'  => 'false',
            'title'   => 'Gateway Not Found',
            'message' => 'This payment method is not available.'
        ]);
    }

    $gateway = $response_gw['response'][0];

    // Fetch gateway options
    $options = [];
    $params = [':gateway_id' => $gateway_id];
    $response_opts = json_decode(getData($db_prefix.'gateways_parameter', 'WHERE gateway_id = :gateway_id', '* FROM', $params), true);
    if ($response_opts['status'] === true) {
        foreach ($response_opts['response'] as $field) {
            $value = $field['value'];
            if (!empty($field['multiple']) && !empty($value)) {
                $value = is_array($value) ? $value : json_decode($value, true);
            }
            $options[$field['option_name']] = $value;
        }
    }

    // Calculate amount (same logic as pp-adapter)
    $brand = $response_brand['response'][0];
    $currencyRates = [];
    $currencyRes = json_decode(getData($db_prefix.'currency', 'WHERE brand_id = :brand_id', '* FROM', [':brand_id' => $brand['brand_id']]), true);
    if (!empty($currencyRes['response'])) {
        foreach ($currencyRes['response'] as $c) {
            $currencyRates[$c['code']] = $c['rate'];
        }
    }

    $txnAmount   = money_sanitize($transaction['amount']);
    $txnCurrency = $transaction['currency'];
    $gwCurrency  = $gateway['currency'];

    if ($txnCurrency === $gwCurrency) {
        $convertedAmount = $txnAmount;
    } else {
        $convertedAmount = isset($currencyRates[$gwCurrency])
            ? money_div($txnAmount, $currencyRates[$gwCurrency])
            : "0";
    }

    $fixed_discount    = money_sanitize($gateway['fixed_discount']);
    $percentage_discount = money_sanitize($gateway['percentage_discount']);
    $fixed_charge      = money_sanitize($gateway['fixed_charge']);
    $percentage_charge = money_sanitize($gateway['percentage_charge']);

    $pctDiscAmt = money_div(money_mul($convertedAmount, $percentage_discount, 8), "100", 8);
    $totalDiscount = money_add($fixed_discount, $pctDiscAmt, 8);

    $pctChgAmt = money_div(money_mul($convertedAmount, $percentage_charge, 8), "100", 8);
    $totalProcessingFee = money_add($fixed_charge, $pctChgAmt, 8);

    $convertedAmount = money_add(money_sub($convertedAmount, $totalDiscount, 8), $totalProcessingFee, 8);

    if ($txnCurrency !== $gwCurrency && isset($currencyRates[$gwCurrency])) {
        $totalDiscount      = money_mul($totalDiscount, $currencyRates[$gwCurrency]);
        $totalProcessingFee = money_mul($totalProcessingFee, $currencyRates[$gwCurrency]);
    }

    // ── Verification Logic ──────────────────────────────────────────
    // Provider ALWAYS from server config — never trust $_POST
    $provider_key = $options['provider'] ?? '';

    if (empty($provider_key)) {
        return bnqr_safe_json(['status' => 'false', 'title' => 'Configuration Error', 'message' => 'Provider not configured.']);
    }

    $verified = false;
    $matched_sms = null;
    $match_method = '';

    $pdo = connectDatabase();

    // ── MODE 0: Unique amount match (primary) ──────────────────────
    if (!$verified) {
        $uaStmt = $pdo->prepare('SELECT unique_amount FROM '.$db_prefix.'gateways_data WHERE ref = :ref AND unique_amount IS NOT NULL LIMIT 1');
        $uaStmt->execute([':ref' => $transaction['ref']]);
        $uaRow = $uaStmt->fetch(PDO::FETCH_ASSOC);

        if ($uaRow && !empty($uaRow['unique_amount'])) {
            $unique_amount = (float)$uaRow['unique_amount'];

            // Exact match only — tolerance here would let one customer's payment
            // complete another customer's transaction (slots differ by 0.01).
            // Over/under payments are handled by the phone-number fallback (MODE 1).
            $findSql = 'SELECT id, trx_id, number, amount, sender_key, sender, type FROM '.$db_prefix.'sms_data 
                        WHERE sender_key = :sender_key AND type = :type AND amount = :uamount AND status = :approved 
                        ORDER BY id DESC LIMIT 10';
            $findStmt = $pdo->prepare($findSql);
            $findStmt->execute([
                ':sender_key' => $provider_key, 
                ':type' => 'Merchant',
                ':uamount' => number_format($unique_amount, 2, '.', ''), 
                ':approved' => 'approved'
            ]);
            $candidates = $findStmt->fetchAll(PDO::FETCH_ASSOC);


            foreach ($candidates as $sms) {
                $claimStmt = $pdo->prepare('UPDATE '.$db_prefix.'sms_data SET status = :used WHERE id = :sms_id AND status = :approved');
                $claimStmt->execute([':used' => 'used', ':sms_id' => $sms['id'], ':approved' => 'approved']);

                if ($claimStmt->rowCount() > 0) {
                    $verified = true;
                    $matched_sms = $sms;
                    $match_method = 'unique-amount';
                    break;
                }
            }
        }
    }

    // MODE 1: Auto-verify by provider + phone + amount (fallback)
    if (!$verified && ($verify_mode === 'auto' || $verify_mode === 'phone')) {
        if (!empty($phone)) {
            // Get more candidates — phone matching is flexible now
            $findSql = 'SELECT id, trx_id, number, amount, sender_key, sender, type FROM '.$db_prefix.'sms_data 
                        WHERE sender_key = :sender_key AND type = :type AND status = :approved 
                        ORDER BY id DESC LIMIT 20';
            $findStmt = $pdo->prepare($findSql);
            $findStmt->execute([':sender_key' => $provider_key, ':type' => 'Merchant', ':approved' => 'approved']);
            $candidates = $findStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($candidates as $sms) {
                // Flexible phone match (handles masked numbers like 0177***4073)
                $phone_to_match = (!empty($sms['sender']) && $sms['sender'] !== '--') ? $sms['sender'] : $sms['number'];
                $phone_match = bnqr_phone_match($phone, $phone_to_match);
                $amount_match = verifyPaymentTolerance($convertedAmount, $sms['amount'], $brand['payment_tolerance']);

                if (!$phone_match) continue;
                if (!$amount_match) continue;

                $claimStmt = $pdo->prepare('UPDATE '.$db_prefix.'sms_data SET status = :used WHERE id = :sms_id AND status = :approved');
                $claimStmt->execute([':used' => 'used', ':sms_id' => $sms['id'], ':approved' => 'approved']);

                if ($claimStmt->rowCount() > 0) {
                    $verified = true;
                    $matched_sms = $sms;
                    $match_method = 'phone+amount';
                    break;
                }
            }
        }
    }

    // MODE 2: Manual trxid entry (fallback)
    if (!$verified && $verify_mode === 'trxid' && !empty($trxid)) {
        // Check duplicate
        $checkStmt = $pdo->prepare('SELECT id FROM '.$db_prefix.'transaction WHERE trx_id = :trx_id LIMIT 1');
        $checkStmt->execute([':trx_id' => $trxid]);
        if ($checkStmt->fetch()) {
            return bnqr_safe_json([
                'status'  => 'false',
                'title'   => 'Duplicate Transaction ID',
                'message' => 'This Transaction ID is already used. Please provide a different one.'
            ]);
        }

        // Atomic claim by trx_id
        $claimSql = 'UPDATE '.$db_prefix.'sms_data SET status = :used, updated_date = :now 
                     WHERE sender_key = :sender_key AND type = :type AND trx_id = :trx_id AND status = :approved LIMIT 1';
        $claimStmt = $pdo->prepare($claimSql);
        $claimStmt->execute([':used' => 'used', ':now' => getCurrentDatetime('Y-m-d H:i:s'), ':sender_key' => $provider_key, ':type' => 'Merchant', ':trx_id' => $trxid, ':approved' => 'approved']);

        if ($claimStmt->rowCount() > 0) {
            // Fetch the claimed SMS for amount verification
            $smsStmt = $pdo->prepare('SELECT id, trx_id, number, amount, sender_key, sender, type FROM '.$db_prefix.'sms_data WHERE trx_id = :trx_id AND status = :used LIMIT 1');
            $smsStmt->execute([':trx_id' => $trxid, ':used' => 'used']);
            $matched_sms = $smsStmt->fetch(PDO::FETCH_ASSOC);

            if ($matched_sms && verifyPaymentTolerance($convertedAmount, $matched_sms['amount'], $brand['payment_tolerance'])) {
                $verified = true;
                $match_method = 'trxid';
            } else {
                // Amount mismatch — revert claim
                $revertStmt = $pdo->prepare('UPDATE '.$db_prefix.'sms_data SET status = :approved WHERE id = :sms_id AND status = :used');
                $revertStmt->execute([':approved' => 'approved', ':sms_id' => $matched_sms['id']]);
            }
        }
    }

    // ── Process Verified Payment ─────────────────────────────────────

    if ($verified && $matched_sms) {

        // Free unique amount slot
        bnqr_free_slot($pdo, $transaction['ref']);

        // Complete transaction (setter also writes sender columns — single write)
        $moreinfo = [
            ['label' => 'Provider',       'value' => ucfirst($matched_sms['sender_key'])],
            ['label' => 'Mobile Number',  'value' => $matched_sms['number']],
            ['label' => 'Match Method',   'value' => $match_method],
        ];

        pp_set_transaction_status(
            $transaction['ref'],
            'completed',
            $gateway_id,
            $matched_sms['trx_id'],
            $moreinfo,
            [
                'sender'      => (!empty($matched_sms['sender']) && $matched_sms['sender'] !== '--') ? $matched_sms['sender'] : $matched_sms['number'],
                'sender_key'  => $matched_sms['sender_key'] ?? '',
                'sender_type' => $matched_sms['type'] ?? '',
            ]
        );

        // Webhook — use the already-loaded transaction row (no redundant re-fetch)
        if (!empty($transaction['webhook_url']) && $transaction['webhook_url'] !== '--') {
            $customer_info = json_decode($transaction['customer_info'], true) ?: [];
            $metadata = json_decode($transaction['metadata'], true) ?: [];

            $ipnData = [
                'pp_id'            => $transaction['ref'],
                'full_name'        => $customer_info['name'] ?? 'N/A',
                'email_address'    => $customer_info['email'] ?? 'N/A',
                'mobile_number'    => $customer_info['mobile'] ?? 'N/A',
                'gateway'          => 'Bangla QR',
                'amount'           => money_round($transaction['amount']),
                'fee'              => money_round($transaction['processing_fee']),
                'discount_amount'  => money_round($transaction['discount_amount']),
                'total'            => money_sub(
                    money_add($transaction['amount'], $transaction['processing_fee']),
                    $transaction['discount_amount']
                ),
                'local_net_amount' => money_round($transaction['local_net_amount']),
                'currency'         => $transaction['currency'],
                'local_currency'   => $transaction['local_currency'],
                'metadata'         => $metadata,
                'sender'           => (!empty($matched_sms['sender']) && $matched_sms['sender'] !== '--') ? $matched_sms['sender'] : $matched_sms['number'],
                'sender_key'       => $matched_sms['sender_key'] ?? '',
                'sender_type'      => $matched_sms['type'] ?? '',
                'transaction_id'   => $matched_sms['trx_id'],
                'status'           => 'completed',
                'date'             => convertUTCtoUserTZ(
                    $transaction['created_date'],
                    ($brand['timezone'] === '--' || $brand['timezone'] === '') ? 'Asia/Dhaka' : $brand['timezone'],
                    "M d, Y h:i A"
                ),
            ];

            $jobs = [[
                'id'      => rand(),
                'url'     => $transaction['webhook_url'],
                'payload' => $ipnData,
            ]];

            $results = sendIPNMulti($jobs);

            foreach ($jobs as $job) {
                $code = $results[$job['id']] ?? 0;
                if ($code !== 200) {
                    $columns = ['ref', 'brand_id', 'payload', 'url', 'created_date', 'updated_date'];
                    $values = [rand(), $brand['brand_id'], json_encode($ipnData, JSON_UNESCAPED_UNICODE), $transaction['webhook_url'], getCurrentDatetime('Y-m-d H:i:s'), getCurrentDatetime('Y-m-d H:i:s')];
                    insertData($db_prefix.'webhook_log', $columns, $values);
                }
            }
        }

        return bnqr_safe_json([
            'status'      => 'true',
            'title'       => 'Payment Verified',
            'message'     => 'Your payment has been verified successfully.',
            'trx_id'      => $matched_sms['trx_id'],
            'provider'    => ucfirst($matched_sms['sender_key']),
            'redirect'    => pp_checkout_address($transaction['ref']),
        ]);
    }

    // ── Not Verified ────────────────────────────────────────────────


    return bnqr_safe_json([
        'status'  => 'false',
        'title'   => 'Payment Not Found',
        'message' => 'No matching payment found. Please check your phone number and try again, or enter the Transaction ID manually.',
    ]);
}

/**
 * Handle transaction cancel when countdown expires
 * IDOR fix: validates transaction status + gateway_id ownership
 */
function bnqr_handle_cancel($data = null) {
    global $db_prefix;
    $transaction_id = escape_string($_POST['transaction_id'] ?? '');
    $gateway_id     = escape_string($_POST['gateway_id'] ?? '');

    if (empty($transaction_id) || empty($gateway_id)) {
        return bnqr_safe_json(['status' => 'false', 'message' => 'Missing parameters.']);
    }

    $pdo = connectDatabase();

    // Validate: transaction must exist with status=initiated and matching gateway_id
    $params = [':ref' => $transaction_id, ':status' => 'initiated', ':gw' => $gateway_id];
    $response = json_decode(getData($db_prefix.'transaction', 'WHERE ref = :ref AND status = :status AND gateway_id = :gw', '* FROM', $params), true);

    if ($response['status'] !== true || empty($response['response'])) {
        return bnqr_safe_json(['status' => 'false', 'message' => 'Invalid or already processed transaction.']);
    }

    // Free the unique amount slot if exists
    bnqr_free_slot($pdo, $transaction_id);

    pp_set_transaction_status($transaction_id, 'canceled');
    return bnqr_safe_json(['status' => 'true']);
}

/**
 * Get available unique amount slots for a gateway + base amount
 */
function bnqr_get_available_slots($pdo, $gateway_id, $base_amount, $total_slots) {
    global $db_prefix;

    // Count pending unique amounts assigned for this gateway
    $sql = 'SELECT COUNT(*) as cnt FROM '.$db_prefix.'gateways_data 
            WHERE gateway_id = :gw AND unique_amount IS NOT NULL';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':gw' => $gateway_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $pending = (int)($row['cnt'] ?? 0);
    return max(0, $total_slots - $pending);
}

/**
 * Assign unique amount to a transaction
 * Returns the unique amount or false if no slots available
 */
function bnqr_assign_unique_amount($pdo, $gateway_id, $transaction_ref, $base_amount, $amount_type, $total_slots) {
    global $db_prefix;

    $maxRetries = 3;
    $retryCount = 0;

    while ($retryCount < $maxRetries) {
        try {
            $pdo->beginTransaction();

            $available = bnqr_get_available_slots($pdo, $gateway_id, $base_amount, $total_slots);
            if ($available <= 0) {
                $pdo->rollBack();
                return false;
            }

            $base = money_sanitize($base_amount);

            // Lock rows for update
            $sql = 'SELECT id, unique_amount FROM '.$db_prefix.'gateways_data 
                    WHERE gateway_id = :gw AND unique_amount IS NOT NULL AND status != :cancelled
                    FOR UPDATE';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':gw' => $gateway_id, ':cancelled' => 'cancelled']);
            $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Find next available slot
            $used_slots = [];
            foreach ($existing as $row) {
                $ua = (float)$row['unique_amount'];
                if ($amount_type === 'integer') {
                    $slot = (int)($ua - $base);
                } else {
                    $slot = (int)round(($ua - $base) * 100);
                }
                if ($slot >= 0) $used_slots[] = $slot;
            }
            sort($used_slots);

            $next_slot = 0;
            foreach ($used_slots as $s) {
                if ($s === $next_slot) $next_slot++;
                elseif ($s > $next_slot) break;
            }

            if ($next_slot >= $total_slots) {
                $pdo->rollBack();
                return false;
            }

            // Calculate unique amount
            if ($amount_type === 'integer') {
                $unique_amount = $base + $next_slot;
            } else {
                $unique_amount = $base + ($next_slot * 0.01);
            }

            // Store in gateways_data
            $checkStmt = $pdo->prepare('SELECT id FROM '.$db_prefix.'gateways_data WHERE ref = :ref LIMIT 1 FOR UPDATE');
            $checkStmt->execute([':ref' => $transaction_ref]);
            if (!$checkStmt->fetch()) {
                $insStmt = $pdo->prepare('INSERT INTO '.$db_prefix.'gateways_data (gateway_id, ref, unique_amount, status, created_date) VALUES (:gw, :ref, :ua, :status, :now)');
                $insStmt->execute([':gw' => $gateway_id, ':ref' => $transaction_ref, ':ua' => number_format($unique_amount, 2, '.', ''), ':status' => 'pending', ':now' => getCurrentDatetime('Y-m-d H:i:s')]);
            } else {
                $sql = 'UPDATE '.$db_prefix.'gateways_data SET unique_amount = :ua WHERE ref = :ref';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':ua' => number_format($unique_amount, 2, '.', ''), ':ref' => $transaction_ref]);
            }

            $pdo->commit();
            return $unique_amount;

        } catch (Exception $e) {
            $pdo->rollBack();
            // 23000 = duplicate-entry from UNIQUE(gateway_id, unique_amount): a concurrent
            // checkout won the same slot. Retry so the loop claims the next free slot.
            $isDuplicate = ($e instanceof PDOException && $e->getCode() == 23000);
            if (!$isDuplicate) {
                error_log('[BNQR] assign_unique_amount: '.$e->getMessage());
                return false;
            }
            $retryCount++;
            if ($retryCount >= $maxRetries) {
                error_log('[BNQR] assign_unique_amount: duplicate slot after '.$maxRetries.' retries: '.$e->getMessage());
                return false;
            }
            usleep(100000); // 100ms delay before retry
        }
    }

    return false;
}

/**
 * Free slot — set unique_amount to NULL and status to expired after verification or timeout
 */
function bnqr_free_slot($pdo, $transaction_ref) {
    global $db_prefix;
    $sql = 'UPDATE '.$db_prefix.'gateways_data SET unique_amount = NULL, status = :expired WHERE ref = :ref';
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([':expired' => 'expired', ':ref' => $transaction_ref]);
    return $result;
}

/**
 * Get or assign unique amount for a transaction
 * Returns ['amount' => float, 'available' => int] or false if no slots
 */
function bnqr_get_or_assign_unique_amount($gateway_id, $transaction_ref, $base_amount) {
    global $db_prefix;

    bnqr_ensure_table(); // Ensure table exists
    $pdo = connectDatabase();

    // Load gateway options
    $opts = [];
    $optsRes = json_decode(getData($db_prefix.'gateways_parameter', 'WHERE gateway_id = :gw', '* FROM', [':gw' => $gateway_id]), true);
    if (!empty($optsRes['response'])) {
        foreach ($optsRes['response'] as $f) {
            $opts[$f['option_name']] = $f['value'];
        }
    }

    $amount_type = $opts['unique_amount_type'] ?? 'decimal';
    $total_slots = max(1, (int)($opts['unique_amount_slots'] ?? 50));

    // Check if already assigned (use ref, not id)
    $stmt = $pdo->prepare('SELECT unique_amount FROM '.$db_prefix.'gateways_data WHERE ref = :ref LIMIT 1');
    $stmt->execute([':ref' => $transaction_ref]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['unique_amount'])) {
        $available = bnqr_get_available_slots($pdo, $gateway_id, $base_amount, $total_slots);
        return ['amount' => (float)$row['unique_amount'], 'available' => $available];
    }

    // Assign new
    $assigned = bnqr_assign_unique_amount($pdo, $gateway_id, $transaction_ref, $base_amount, $amount_type, $total_slots);
    if ($assigned === false) return false;

    $available = bnqr_get_available_slots($pdo, $gateway_id, $base_amount, $total_slots);
    return ['amount' => (float)$assigned, 'available' => $available];
}

/**
 * Lazy-decode QR image on first checkout visit.
 * Decodes existing QR images that were uploaded before auto-decode was available.
 * Saves the decoded payload to gateway config for future use.
 * 
 * @param string $imageUrl QR code image URL
 * @param string $gateway_id Gateway identifier
 * @param string $brand_id Brand identifier
 * @return string|null Decoded payload or null on failure
 */
function bnqr_lazy_decode_qr($imageUrl, $gateway_id, $brand_id) {
    global $db_prefix;
    
    $tempFile = tempnam(sys_get_temp_dir(), 'bnqr_');
    $decoded = null;
    
    // Download image
    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $ch = curl_init($imageUrl);
        if ($ch) {
            $fp = fopen($tempFile, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        }
    }
    
    if (!file_exists($tempFile) || filesize($tempFile) < 100) {
        @unlink($tempFile);
        return null;
    }
    
    // Try zbarimg
    $output = [];
    $rc = 0;
    exec("zbarimg --raw " . escapeshellarg($tempFile) . " 2>/dev/null", $output, $rc);
    
    if ($rc === 0 && !empty($output)) {
        $payload = trim(implode("\n", $output));
        if (strpos($payload, '000201') === 0 || strpos($payload, '000202') === 0) {
            $decoded = $payload;
        }
    }
    
    // Fallback: GD + zbarimg
    if ($decoded === null && function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring(file_get_contents($tempFile));
        if ($img) {
            $tempPng = $tempFile . '.png';
            imagepng($img, $tempPng);
            imagedestroy($img);
            $output2 = [];
            $rc2 = 0;
            exec("zbarimg --raw " . escapeshellarg($tempPng) . " 2>/dev/null", $output2, $rc2);
            if ($rc2 === 0 && !empty($output2)) {
                $payload2 = trim(implode("\n", $output2));
                if (strpos($payload2, '000201') === 0 || strpos($payload2, '000202') === 0) {
                    $decoded = $payload2;
                }
            }
            @unlink($tempPng);
        }
    }
    
    @unlink($tempFile);
    
    // Save decoded payload to gateway config
    if ($decoded !== null && !empty($gateway_id)) {
        $check = json_decode(getData($db_prefix.'gateways_parameter', 
            'WHERE gateway_id = :gw AND option_name = :opt', '* FROM', 
            [':gw' => $gateway_id, ':opt' => 'raw_payload']), true);
        
        if (!empty($check['response'][0]['value']) && $check['response'][0]['value'] !== '--') {
            // Already exists — don't overwrite
        } else {
            // Insert or update
            if (!empty($check['response'][0]['id'])) {
                updateData($db_prefix.'gateways_parameter', ['value', 'updated_date'], 
                    [$decoded, getCurrentDatetime('Y-m-d H:i:s')], 
                    "gateway_id = '".$gateway_id."' AND option_name = 'raw_payload'");
            } else {
                insertData($db_prefix.'gateways_parameter', 
                    ['brand_id', 'gateway_id', 'option_name', 'value', 'created_date', 'updated_date'], 
                    [$brand_id, $gateway_id, 'raw_payload', $decoded, getCurrentDatetime('Y-m-d H:i:s'), getCurrentDatetime('Y-m-d H:i:s')]);
            }
        }
    }
    
    return $decoded;
}

/**
 * Generate dynamic QR code for checkout.
 * 
 * Loads raw payload from gateway config, injects unique amount,
 * and returns base64-encoded PNG image via external API.
 * 
 * @param string $gateway_id Gateway identifier
 * @param float $unique_amount Unique amount for this transaction
 * @param string|null $reference_label Optional reference (tag 62.05)
 * @return array|false ['payload' => string, 'image' => string, 'amount' => float] or false on error
 */
function bnqr_generate_dynamic_qr($gateway_id, $unique_amount, $reference_label = null, $store_label = null) {
    global $db_prefix;
    
    // Load raw payload from gateway config
    $opts = [];
    $optsRes = json_decode(getData($db_prefix.'gateways_parameter', 'WHERE gateway_id = :gw', '* FROM', [':gw' => $gateway_id]), true);
    if (!empty($optsRes['response'])) {
        foreach ($optsRes['response'] as $f) {
            $opts[$f['option_name']] = $f['value'];
        }
    }
    
    $rawPayload = $opts['raw_payload'] ?? '';
    $qrCodeUrl  = $opts['qr_code'] ?? '';
    
    // Lazy-decode: if raw_payload empty but qr_code exists, decode it now
    if (empty($rawPayload) && !empty($qrCodeUrl)) {
        $rawPayload = bnqr_lazy_decode_qr($qrCodeUrl, $gateway_id, $opts['brand_id'] ?? '');
    }
    
    if (empty($rawPayload)) {
        error_log('[BNQR] generate_dynamic_qr: raw_payload is empty for gateway ' . $gateway_id);
        return false; // No raw payload configured — fall back to static QR
    }
    
    // Load QR payload library
    $libPath = __DIR__ . '/qr-payload.php';
    if (!function_exists('bnqr_make_dynamic')) {
        if (file_exists($libPath)) {
            require_once $libPath;
        } else {
            return false; // Library not found
        }
    }
    
    // Generate dynamic payload with unique amount
    $amountStr = number_format((float)$unique_amount, 2, '.', '');
    $dynamicPayload = bnqr_make_dynamic($rawPayload, $amountStr, $reference_label, $store_label);
    
    if (empty($dynamicPayload)) {
        error_log('[BNQR] generate_dynamic_qr: bnqr_make_dynamic returned empty for amount ' . $amountStr);
        return false;
    }
    
    // Generate QR image — uses bnqr_generate_qr() from qr-payload.php (external API + logo overlay)
    $logoPath = __DIR__ . '/assets/qr-logo.jpg';
    if (!file_exists($logoPath)) $logoPath = null;
    
    try {
        $png = bnqr_generate_qr($dynamicPayload, 800, 4, $logoPath);
        $base64 = 'data:image/png;base64,' . base64_encode($png);
        
        error_log('[BNQR] generate_dynamic_qr: SUCCESS - dynamic QR generated for amount ' . $amountStr);
        return [
            'payload' => $dynamicPayload,
            'image'   => $base64,
            'amount'  => (float)$unique_amount,
        ];
    } catch (Exception $e) {
        error_log('[BNQR] generate_dynamic_qr: QR generation failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get static QR payload (raw TLV from config) for fallback display.
 * 
 * @param string $gateway_id Gateway identifier
 * @return string Raw payload or empty string
 */
function bnqr_get_raw_payload($gateway_id) {
    global $db_prefix;
    
    $optsRes = json_decode(getData($db_prefix.'gateways_parameter', 'WHERE gateway_id = :gw', '* FROM', [':gw' => $gateway_id]), true);
    if (!empty($optsRes['response'])) {
        foreach ($optsRes['response'] as $f) {
            if ($f['option_name'] === 'raw_payload') {
                return $f['value'] ?? '';
            }
        }
    }
    return '';
}

/**
 * Handle AJAX: free slot on cancel/timeout
 * IDOR fix: validates transaction status + gateway_id ownership
 */
function bnqr_handle_free_slot($data = null) {
    global $db_prefix;
    $transaction_id = escape_string($_POST['transaction_id'] ?? '');
    $gateway_id     = escape_string($_POST['gateway_id'] ?? '');

    if (empty($transaction_id) || empty($gateway_id)) {
        return bnqr_safe_json(['status' => 'false', 'message' => 'Missing parameters.']);
    }

    $pdo = connectDatabase();

    // Validate: transaction must exist with matching gateway_id
    $params = [':ref' => $transaction_id, ':gw' => $gateway_id];
    $response = json_decode(getData($db_prefix.'transaction', 'WHERE ref = :ref AND gateway_id = :gw', '* FROM', $params), true);

    if ($response['status'] !== true || empty($response['response'])) {
        return bnqr_safe_json(['status' => 'false', 'message' => 'Transaction not found.']);
    }

    // Free slot by transaction ref
    bnqr_free_slot($pdo, $transaction_id);

    return bnqr_safe_json(['status' => 'true']);
}

/**
 * Handle auto-check polling from frontend
 * Exact unique-amount matching only. Over/under payments are handled
 * by the phone-number manual verify (bnqr_handle_verify).
 */
function bnqr_handle_poll($data = null) {
    global $db_prefix;

    $gateway_id     = escape_string($_POST['gateway_id'] ?? '');
    $transaction_id = escape_string($_POST['transaction_id'] ?? '');
    $provider       = escape_string($_POST['provider'] ?? '');

    if (empty($gateway_id) || empty($transaction_id)) {
        return bnqr_safe_json(['status' => 'false', 'message' => 'Missing parameters.']);
    }

    // Fetch transaction
    $params = [':ref' => $transaction_id, ':status' => 'initiated'];
    $response_txn = json_decode(getData($db_prefix.'transaction', 'WHERE ref = :ref AND status = :status', '* FROM', $params), true);

    if ($response_txn['status'] !== true) {
        return bnqr_safe_json(['status' => 'true', 'message' => 'already_verified']);
    }

    $transaction = $response_txn['response'][0];

    // Fetch brand
    $params = [':brand_id' => $transaction['brand_id']];
    $response_brand = json_decode(getData($db_prefix.'brands', 'WHERE brand_id = :brand_id', '* FROM', $params), true);
    $brand = $response_brand['response'][0];

    // Fetch gateway config
    $params = [':gateway_id' => $gateway_id, ':brand_id' => $transaction['brand_id']];
    $response_gw = json_decode(getData($db_prefix.'gateways', 'WHERE gateway_id = :gateway_id AND brand_id = :brand_id AND status = "active"', '* FROM', $params), true);
    $gateway = $response_gw['response'][0];

    // Provider ALWAYS from server config — never trust $_POST
    $optsRes = json_decode(getData($db_prefix.'gateways_parameter', 'WHERE gateway_id = :gw', '* FROM', [':gw' => $gateway_id]), true);
    $server_provider = '';
    if (!empty($optsRes['response'])) {
        foreach ($optsRes['response'] as $f) {
            if ($f['option_name'] === 'provider') { $server_provider = $f['value']; break; }
        }
    }
    if (empty($server_provider)) {
        return bnqr_safe_json(['status' => 'false', 'message' => 'waiting']);
    }
    $provider = $server_provider;

    // Calculate base amount (without unique offset)
    $currencyRates = [];
    $currencyRes = json_decode(getData($db_prefix.'currency', 'WHERE brand_id = :brand_id', '* FROM', [':brand_id' => $brand['brand_id']]), true);
    if (!empty($currencyRes['response'])) {
        foreach ($currencyRes['response'] as $c) {
            $currencyRates[$c['code']] = $c['rate'];
        }
    }

    $txnAmount   = money_sanitize($transaction['amount']);
    $txnCurrency = $transaction['currency'];
    $gwCurrency  = $gateway['currency'];

    if ($txnCurrency === $gwCurrency) {
        $convertedAmount = $txnAmount;
    } else {
        $convertedAmount = isset($currencyRates[$gwCurrency])
            ? money_div($txnAmount, $currencyRates[$gwCurrency])
            : "0";
    }

    $fixed_discount    = money_sanitize($gateway['fixed_discount']);
    $percentage_discount = money_sanitize($gateway['percentage_discount']);
    $fixed_charge      = money_sanitize($gateway['fixed_charge']);
    $percentage_charge = money_sanitize($gateway['percentage_charge']);

    $pctDiscAmt = money_div(money_mul($convertedAmount, $percentage_discount, 8), "100", 8);
    $totalDiscount = money_add($fixed_discount, $pctDiscAmt, 8);

    $pctChgAmt = money_div(money_mul($convertedAmount, $percentage_charge, 8), "100", 8);
    $totalProcessingFee = money_add($fixed_charge, $pctChgAmt, 8);

    $convertedAmount = money_add(money_sub($convertedAmount, $totalDiscount, 8), $totalProcessingFee, 8);

    $pdo = connectDatabase();

    // ── UNIQUE AMOUNT MATCHING (primary) ──────────────────────────
    // Get unique amount for this transaction
    $uaStmt = $pdo->prepare('SELECT unique_amount FROM '.$db_prefix.'gateways_data WHERE ref = :ref AND unique_amount IS NOT NULL LIMIT 1');
    $uaStmt->execute([':ref' => $transaction['ref']]);
    $uaRow = $uaStmt->fetch(PDO::FETCH_ASSOC);

    if ($uaRow && !empty($uaRow['unique_amount'])) {
        $unique_amount = (float)$uaRow['unique_amount'];

        // Find SMS matching the unique amount EXACTLY (no tolerance — prevents cross-matching between users)
        $findSql = 'SELECT id, trx_id, number, amount, sender, type FROM '.$db_prefix.'sms_data 
                    WHERE sender_key = :provider AND type = :type
                    AND amount = :amount 
                    AND status = :status 
                    ORDER BY id ASC 
                    LIMIT 5';
        $findStmt = $pdo->prepare($findSql);
        $findStmt->execute([
            ':provider' => $provider,
            ':type'     => 'Merchant',
            ':amount'   => number_format($unique_amount, 2, '.', ''),
            ':status'   => 'approved',
        ]);
        $candidates = $findStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($candidates as $sms) {
            // Atomic claim
            $claimStmt = $pdo->prepare('UPDATE '.$db_prefix.'sms_data SET status = :used WHERE id = :sms_id AND status = :approved');
            $claimStmt->execute([':used' => 'used', ':sms_id' => $sms['id'], ':approved' => 'approved']);

            if ($claimStmt->rowCount() > 0) {
                bnqr_free_slot($pdo, $transaction['ref']);

                $moreinfo = [
                    ['label' => 'Provider',      'value' => ucfirst($provider)],
                    ['label' => 'Mobile Number',  'value' => $sms['number']],
                    ['label' => 'Match Method',   'value' => 'unique-amount'],
                    ['label' => 'Unique Amount',  'value' => number_format($unique_amount, 2)],
                ];

                pp_set_transaction_status($transaction['ref'], 'completed', $gateway_id, $sms['trx_id'], $moreinfo);

                // Update sender, sender_key, sender_type in transaction table
                $updateStmt = $pdo->prepare('UPDATE '.$db_prefix.'transaction SET sender = :sender, sender_key = :sender_key, sender_type = :sender_type WHERE ref = :ref');
                $updateStmt->execute([
                    ':sender'      => (!empty($sms['sender']) && $sms['sender'] !== '--') ? $sms['sender'] : $sms['number'],
                    ':sender_key'  => $sms['sender_key'] ?? $provider ?? '',
                    ':sender_type' => $sms['type'] ?? 'Merchant',
                    ':ref'         => $transaction['ref'],
                ]);

                return bnqr_safe_json([
                    'status'   => 'true',
                    'title'    => 'Payment Verified',
                    'message'  => 'Your payment has been verified automatically.',
                    'redirect' => pp_checkout_address($transaction['ref']),
                    'trx_id'   => $sms['trx_id'],
                ]);
            }
        }
    }

    // No tolerance fallback here by design — polling matches the unique amount
    // EXACTLY. Over/under payments must go through the phone-number manual
    // verify (bnqr_handle_verify MODE 1), which is tolerance-aware.

    return bnqr_safe_json([
        'status'  => 'false',
        'message' => 'waiting',
    ]);
}
