# PipraEdge — Extra Features & Addons Bundle for PipraPay

PipraEdge is a single consolidated drop-in that bundles the extra, must-have payment-automation features on top of PipraPay — the things competitors in the payment-automation space already ship out of the box. Universal QR checkout, automated SMS verification, phone-based fallback verification, unique-amount matching, and a branded, conversion-focused checkout theme. Instead of juggling three separate addon repos, PipraEdge keeps every modified file from the **Bangla QR** gateway, the **Phone Verify** addon, and the **Zenith Core** theme in one folder, already merged so they run together.

> **Important:** PipraEdge is built for **PipraPay 3.0.0+**. It layers on top of a clean PipraPay install — run the base `pp-install/db.sql` first, then `pipraedge.sql`.

## Meta Data
- *Name*: PipraEdge
- *Version*: 1.0.0
- *License*: [GNU Affero General Public License v3.0](LICENSE)
- *Platform*: PipraPay 3.0.0

## What It Adds (and why competitors already have it)

PipraPay covers the core gateway flow. PipraEdge fills the gaps that competing payment-automation tools treat as standard:

### Universal QR Checkout (Bangla QR)
- One QR code works across all providers — bKash, Nagad, Rocket, Upay, TallyPay, and more.
- Customers scan with any compatible app; the system auto-verifies via SMS matching.

### Automated SMS Verification
- Each transaction gets a **unique amount** (e.g., 100.01, 100.02, 100.03) baked into the QR.
- Incoming SMS is matched to the transaction automatically — no manual entry required.
- Atomic SMS claiming prevents double-verification under concurrency.

### Phone-Based Fallback Verification (Phone Verify)
- When SMS auto-match fails, the customer verifies by entering their **sender phone number**.
- 3-tier verification: unique amount → phone/bank match → manual TRX ID fallback.
- Configurable session timeout and polling keep the wizard responsive without hanging.

### Branded, Conversion-Focused Checkout (Zenith Core)
- Mobile-first checkout, status, receipt, and payment-link theme.
- Merchant-controlled branding, gateway ordering, language switcher, session timeout, and custom footer.

## Requirements
- PipraPay `3.0.0+`
- PHP `8.1+`
- MySQL/MariaDB with InnoDB support
- HTTPS-enabled environment
- The **Zenith Core** theme is included in this bundle

## Installation

### 1. Database Setup
Run the base PipraPay schema first, then import the PipraEdge extra schema:
```bash
mysql -u username -p database_name < piprapay/pp-content/pp-install/db.sql
mysql -u username -p database_name < PipraEdge/pipraedge.sql
```
`pipraedge.sql` is safe to re-run — the `CREATE TABLE` is `IF NOT EXISTS` and the `ALTER` is guarded by a column-existence check. It adds:
- `pp_brands.auto_verify_type` — brand-level verification-mode selector (trxid / phone)
- `pp_gateways_data` — per-gateway unique-amount slot table

### 2. File Installation
1. Extract / copy the `PipraEdge` folder.
2. Copy the `pp-content` folder into your PipraPay root directory (merge, do not overwrite unrelated files).
3. Some files are **new**, some are **merged cores** already combining all three addons:

| File | Action |
|------|--------|
| `pp-content/pp-modules/pp-gateways/bangla-qr/` | New folder — copy entirely |
| `pp-content/pp-modules/pp-themes/zenith-core/` | New folder — copy entirely (theme) |
| `pp-content/pp-include/pp-functions.php` | Merged core — replaces base |
| `pp-content/pp-include/pp-adapter.php` | Merged core — replaces base |
| `pp-content/pp-admin/pp-root/gateways/edit.php` | Merge changes |

4. In the PipraPay dashboard, activate the **Bangla QR** gateway and the **Zenith Core** theme.

## Configuration

### Brand Verification Mode (Dashboard → Brand Settings)
| Mode | Behaviour |
|------|-----------|
| `trxid` (default) | Unique-amount SMS auto-match, manual TRX ID fallback |
| `phone` | Phone-number verification wizard with SMS fallback |

### Gateway Settings (Dashboard → Payment Gateways → Bangla QR)
| Field | Description | Default |
|-------|-------------|---------|
| QR Code | Upload your Bangla QR image | — |
| Provider | Select your provider | — |
| Poll Interval | Auto-check interval (seconds) | 4 |
| Auto Cancel | Transaction timeout (minutes) | 8 |
| Fallback After | Show manual verify link (seconds) | 30 |
| Unique Amount Type | Decimal (0.01) or Integer (1) | Decimal |
| Unique Amount Slots | Max concurrent transactions | 50 |
| Fallback Method | Phone or TRX ID | Phone |

### Phone Verify Constants (in `pp-functions.php`)
| Constant | Purpose | Default |
|----------|---------|---------|
| `PHONE_VERIFY_SESSION_MINUTES` | Wizard session lifetime | 10 |
| `PHONE_VERIFY_POLL_INTERVAL` | Front-end poll interval (seconds) | 4 |

## File Structure
```
PipraEdge/
├── pipraedge.sql                          # Extra schema (run after base db.sql)
├── README.md                              # This file
└── pp-content/
    ├── pp-admin/pp-root/gateways/
    │   └── edit.php                       # jsQR admin decode hook
    ├── pp-include/
    │   ├── pp-functions.php               # Merged core: phone handlers + sender_info write
    │   └── pp-adapter.php                 # Merged core: bnqr + phone routing
    └── pp-modules/
        ├── pp-gateways/bangla-qr/
        │   ├── class.php                  # Gateway metadata, fields, lang
        │   ├── bangla-qr.php              # Core engine (verify, poll, QR gen)
        │   ├── qr-payload.php             # TLV parser, CRC, dynamic QR
        │   ├── qr-admin.php               # Admin QR extract handlers
        │   └── assets/
        │       ├── admin-qr-decode.js
        │       ├── jsQR.min.js
        │       ├── logo.jpg
        │       └── qr-logo.jpg
        └── pp-themes/zenith-core/
            ├── class.php                  # Theme metadata, settings, lang_text
            ├── checkout.php               # Method-selection checkout
            ├── gateway.php                # Per-gateway payment page (phone wizard)
            ├── checkout-status.php        # Pending / completed / failed / refunded / canceled
            ├── qr-gateway.php             # QR checkout page
            ├── receipt.php                # Invoice / receipt view
            ├── payment-link.php           # Hosted payment link
            ├── payment-link-default.php   # Default payment link fallback
            └── inc/
                ├── footer.php             # Shared footer
                ├── lang.php               # Language resolution helpers
                └── lang-modal.php         # Language picker modal
```

## Bundled Components
PipraEdge is the merged result of three addons, with conflicting files resolved to a single winning version:

| Component | Source | Role |
|-----------|--------|------|
| Bangla QR gateway | `bangla-qr` | Universal QR + SMS auto-verification |
| Phone Verify | `phone-verify` | Phone-number fallback wizard + TRX ID fallback |
| Zenith Core theme | `theme` | Branded checkout / status / receipt / payment-link |

Core files (`pp-functions.php`, `pp-adapter.php`) carry the phone-verify base plus bangla-qr's `sender_info` write and `bnqr_*` handlers; `gateway.php` carries the phone wizard mods; `checkout-status.php` carries the status maps.

## Adding Future Addons
PipraEdge stays a **single folder** — new addons drop in here, not in separate repos. To keep merges cheap and the bundle maintainable, follow one rule:

1. **Module-first.** Build each addon as a self-contained folder (`pp-modules/pp-gateways/<name>/` like Bangla QR) with its own action name and handlers. Touch the shared cores as little as possible.
2. **Minimal core glue.** `pp-functions.php` / `pp-adapter.php` are only the glue — a route line per addon, not duplicated logic. The merged core should stay the *smallest* shared surface.
3. **Record the merge order.** When a new addon is merged, note which file wins and why (see `bangla-qr_phone-verify_piprapay_analysis.md`). That turns the next merge into a mechanical step instead of a re-derivation.

If two addons need to edit the *same* core function in conflicting ways, resolve it once in the merged core and keep the per-addon logic isolated in its module. Never fork the folder per addon.

## License
PipraEdge is released under the [GNU Affero General Public License v3.0](LICENSE). As a derivative work of PipraPay (AGPL-3.0), it must remain AGPL compatible — any distributed or network-served modified version stays open under the same license.
