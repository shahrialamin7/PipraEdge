# Bangla QR — Universal QR Gateway for PipraPay

One QR for every wallet. Bangla QR lets customers scan a single QR with **bKash, Nagad, Rocket, Upay, TallyPay, DGePay** and more — auto-verified via SMS, no manual TRX ID.

Built for **PipraPay 3.0.0+** (PHP 8.1+, MySQL InnoDB, HTTPS). Drop-in addon — no core fork.

## Features
- **Universal QR** — single static QR converted to dynamic (EMVCo TLV `000201`, tag `01` 11→12, tag `54` amount, CRC-16 `0x1021` recalc), 800×800 ECC H + logo overlay
- **Unique-amount auto-verify** — per-transaction amount (`100.07`) baked into QR, `pp_gateways_data` (`UNIQUE(gateway_id,unique_amount)`) + `FOR UPDATE` + `23000` retry prevents double-spend
- **3-tier fallback:** unique-amount (exact) → phone/bank `verifyPaymentTolerance` → manual TRX ID. Masked numbers `017***1234` / `01XX` matched via `[\d*X]` + first3+last4
- **Zenith Core theme** — mobile-first checkout/qr-gateway/status/receipt/payment-link, language switcher, session timeout, gateway ordering

## Install
```bash
# 1. DB — run after PipraPay base db.sql
mysql -u user -p db < gateways_data.sql
# or: mysql < pp-content/pp-install/db.sql then gateways_data.sql

# 2. Files — merge pp-content into PipraPay root
cp -r pp-content/* /path/to/piprapay/pp-content/

# 3. Dashboard → Gateways → Bangla QR → upload QR, select Provider, save
# 4. Dashboard → Themes → Zenith Core → Activate
```

## Gateway Settings
| Field | Default |
|---|---|
| Provider | — |
| Poll Interval | 4s |
| Auto Cancel | 8 min |
| Fallback After | 30s |
| Unique Amount Type | Decimal (0.01) |
| Unique Amount Slots | 50 |
| Fallback Method | Phone |

## File Map
```
gateways_data.sql
pp-content/
├── pp-admin/pp-root/gateways/edit.php          # jsQR admin decode + hex+picker
├── pp-include/
│   ├── pp-adapter.php                          # bnqr-verify/poll/cancel/free-slot CSRF bypass + refund $failed
│   └── pp-functions.php                        # db_port, pp_get_gateway_options, pp_bkash_tokenized_refund, [\d*X], sender_info
└── pp-modules/
    ├── pp-gateways/bangla-qr/
    │   ├── class.php, bangla-qr.php, qr-payload.php, qr-admin.php
    │   └── assets/{admin-qr-decode.js, jsQR.min.js, logo.jpg, qr-logo.jpg}
    └── pp-themes/zenith-core/
        ├── class.php, checkout.php, gateway.php, checkout-status.php, qr-gateway.php, receipt.php, payment-link*.php
        └── inc/{footer.php, lang.php, lang-modal.php}
```

## Fixes Included (vs PipraPay 3.0.0 base)
- `connectDatabase` → `port` support
- `pp_get_gateway_options` + `pp_bkash_tokenized_refund` (refund API)
- `tabler-social.min.css` → `tabler-socials.min.css`
- `pp-adapter` full refund block (`$failed`, `source_info`)
- `MFSMessageVerified` — all Merchant `[\d*X]` + uncommented, rocket `[*\d]`→`[\d*X]`

## License
AGPL-3.0 — as PipraPay derivative.
