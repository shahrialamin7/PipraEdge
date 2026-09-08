# 🟩 PipraEdge — Bangla QR Gateway Bundle for PipraPay

<p align="center">
  <strong>One QR for every wallet.</strong><br>
  Universal Bangla QR + SMS auto-verify + Phone Verify fallback + Zenith Core theme — one drop-in bundle for PipraPay.
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-AGPL--3.0-blue.svg?style=for-the-badge&logo=gnu&logoColor=white" alt="AGPL-3.0 License"></a>
  <img src="https://img.shields.io/badge/PipraPay-3.0.0%2B-5f38f9?style=for-the-badge" alt="PipraPay 3.0.0+">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.1+">
</p>

## 🇧🇩 What is Bangla QR?

**Bangla QR is Bangladesh's national interoperable QR payment standard**, introduced by **Bangladesh Bank** so customers can pay digitally through one common platform.

How it works:

- **Single Code** — merchants display only one unified Bangla QR code instead of separate codes for different apps.
- **Any App** — customers scan with any participating bank app or MFS provider like **bKash, Nagad, or Rocket**.
- **Instant Transfer** — funds move straight from the customer's account to the merchant's account via the **National Payment Switch (NPSB)**.

It is built on global **EMVCo specifications** for open-loop, interoperable, merchant-presented QR payments.

Key benefits:

- **No extra apps** — no need for a specific provider's app just to pay a merchant.
- **Less cash** — supports a cashless economy, lowering the cost and risk of carrying physical money.
- **Easy for small businesses** — micro and small vendors accept digital payments affordably.

> **Static vs Dynamic:** a *static* QR encodes a fixed merchant code (customer types the amount); a *dynamic* QR encodes the exact payable amount per transaction. This bundle converts your one static merchant QR into a **dynamic per-transaction QR**, which is what makes automatic verification possible.

## 📦 What is this repository?

**PipraEdge** is a drop-in addon bundle for [PipraPay](https://piprapay.com) (no core fork) containing three pieces that work together:

| # | Piece | Path | What it does |
|---|---|---|---|
| 1 | **Bangla QR gateway module** | `pp-content/pp-modules/pp-gateways/bangla-qr/` | PipraPay gateway driver (`class.php`) — takes your static merchant QR, bakes the exact payable amount into a dynamic QR per transaction (`qr-payload.php`), renders the pay screen (`bangla-qr.php`), and verifies payment automatically |
| 2 | **Phone Verify fallback** | `pp-content/pp-include/phone-verify.php` (via `pp-functions.php` / `pp-adapter.php` hooks) | If auto-match misses, the customer is verified by phone number or manual Transaction ID instead of a dead end |
| 3 | **Zenith Core theme** | `pp-content/pp-modules/pp-themes/zenith-core/` | Mobile-first checkout / QR gateway / status / receipt / payment-link screens with language switcher, session timeout, and gateway ordering |

### How verification works (3-tier fallback)

1. **Unique-amount auto-verify (exact)** — each transaction gets a unique amount (e.g. `100.07`) baked into the QR. When the SMS lands, `pp_gateways_data` (`UNIQUE(gateway_id, unique_amount)`) + `FOR UPDATE` locking + retry on `23000` prevents double-spend.
2. **Phone / tolerance match** — masked numbers (`017***1234`) matched via first-3 + last-4 with `verifyPaymentTolerance`.
3. **Manual Transaction ID** — customer submits the TRX ID as a last resort.

Built for **PipraPay 3.0.0+** (PHP 8.1+, MySQL InnoDB, HTTPS).

## ✨ Features

- **Universal QR** — single static QR converted to dynamic (EMVCo TLV `000201`, tag `01` 11→12, tag `54` amount, CRC-16 `0x1021` recalc), 800×800 ECC H + logo overlay
- **Unique-amount auto-verify** — per-transaction amount baked into QR, atomic slot claim, no double-spend
- **3-tier fallback** — unique-amount (exact) → phone/bank tolerance → manual TRX ID
- **Zenith Core theme** — mobile-first checkout/qr-gateway/status/receipt/payment-link, language switcher, session timeout, gateway ordering
- **Admin QR tools** — jsQR in-browser decode + hex picker on the gateway edit screen

## 🚀 Install

```bash
# 1. DB — run AFTER the PipraPay base db.sql
mysql -u user -p db < gateways_data.sql
# or: mysql < pp-content/pp-install/db.sql then gateways_data.sql

# 2. Files — merge pp-content into your PipraPay root
cp -r pp-content/* /path/to/piprapay/pp-content/

# 3. Dashboard → Gateways → Bangla QR → upload QR, select Provider, save
# 4. Dashboard → Themes → Zenith Core → Activate
```

## ⚙️ Gateway Settings

| Field | Default |
|---|---|
| Provider | — |
| Poll Interval | 4s |
| Auto Cancel | 8 min |
| Fallback After | 30s |
| Unique Amount Type | Decimal (0.01) |
| Unique Amount Slots | 50 |
| Fallback Method | Phone |

## 🗂️ File Map

```
gateways_data.sql
pp-content/
├── pp-admin/pp-root/gateways/edit.php          # jsQR admin decode + hex picker
├── pp-include/
│   ├── pp-adapter.php                          # bnqr-verify/poll/cancel/free-slot + refund $failed
│   └── pp-functions.php                        # db_port, pp_get_gateway_options, pp_bkash_tokenized_refund, sender_info
└── pp-modules/
    ├── pp-gateways/bangla-qr/
    │   ├── class.php, bangla-qr.php, qr-payload.php, qr-admin.php
    │   └── assets/{admin-qr-decode.js, jsQR.min.js, logo.jpg, qr-logo.jpg}
    └── pp-themes/zenith-core/
        ├── class.php, checkout.php, gateway.php, checkout-status.php, qr-gateway.php, receipt.php, payment-link*.php
        └── inc/{footer.php, lang.php, lang-modal.php}
```

## 🛠️ Fixes Included (vs PipraPay 3.0.0 base)

- `connectDatabase` → `port` support
- `pp_get_gateway_options` + `pp_bkash_tokenized_refund` (refund API)
- `tabler-social.min.css` → `tabler-socials.min.css`
- `pp-adapter` full refund block (`$failed`, `source_info`)
- `MFSMessageVerified` — all merchant `[\d*X]` patterns + uncommented, rocket `[*\d]`→`[\d*X]`

## 🙏 Gratitude & Credits

**PipraPay** — the entire foundation this bundle stands on — is the work of **[Mouzh Hossain Shad (Mouzhhs)](https://github.com/mouzhhs)**, Founder & CEO of [QubePlug](https://piprapay.com), who built and open-sourced Bangladesh's first self-hosted payment automation platform (AGPL-3.0) for the community. Genuine respect and thanks to him and every PipraPay contributor — without their core, there is no PipraEdge.

**PipraEdge bundle** (Bangla QR module + Phone Verify + Zenith Core integration) is maintained by [@shahrialamin7](https://github.com/shahrialamin7).

### ⚠️ Attribution — do NOT remove credits

1. The PipraPay credit above and the PipraEdge maintainer credit **must never be removed** from this README, the admin UI, or the checkout footer in any copy, fork, redistribution, or client deployment.
2. Do **not** rebrand this work as solely your own — PipraPay core belongs to its authors under AGPL-3.0.
3. If you distribute modified versions (including client projects), you **must keep them open-source under AGPL-3.0** with these credits intact — that is the license requirement, not a request.
4. Bug reports and improvements are welcome via Issues / Pull Requests. For PipraPay core issues, please report upstream at [PipraPay/PipraPay](https://github.com/PipraPay/PipraPay).

## 🛡️ License

AGPL-3.0 — as a PipraPay derivative. You can use, modify, and self-host it.
If you distribute modified versions, you must keep them open-source under the same license, with credits intact.
