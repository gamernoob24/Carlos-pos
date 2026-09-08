# Aronium POS Web

A **web-based point-of-sale system** built for the XAMPP stack — PHP 7.4+/8.x, MySQL/MariaDB, Apache, no frameworks and no Composer. It follows the classic Aronium-style workflow (catalog → cart → payment → receipt → history) but runs in any browser, on any machine on your network.

> Independent, open implementation inspired by the Aronium POS workflow. Not affiliated with or endorsed by Aronium Ltd.

---

## 1. What's inside

| Module | What it does |
|---|---|
| **Point of Sale** | Product grid with categories, live search, barcode/SKU lookup, cart with qty steppers, cart-level discount (% or amount), cash / card / e-wallet, cash tendered + change, keyboard shortcuts |
| **Products** | Add / edit / delete, SKU + barcode, category, cost & selling price, stock on hand, reorder level, unit, tax-exempt flag, low-stock badges, CSV export |
| **Stock control** | Stock is deducted on every sale, returned on void, plus manual adjustments with a full movement audit trail per product |
| **Categories** | Simple CRUD; products fall back to *Uncategorized* |
| **Sales history** | Filter by date range, sale no., customer, cashier, payment method or status; void a sale (admin) and return stock; CSV export |
| **Receipts** | Clean 58 mm / 80 mm thermal-style receipt, printable straight from the browser |
| **Reports** | Gross sales, transactions, average basket, discounts, tax collected, estimated gross profit, sales-over-time bars, payment mix, top sellers, category & cashier breakdown, inventory snapshot |
| **Settings** | Business name/address/phone/TIN, currency symbol & position, tax name/rate and inclusive-vs-exclusive handling, receipt footer & paper width, sale-number prefix, decimals, negative-stock toggle |
| **Users** | Admin + cashier roles, password hashing (`password_hash`), password reset, deactivate/delete (past sales keep the cashier name) |

**Security basics included:** PDO prepared statements everywhere, bcrypt password hashing, CSRF tokens on every POST/AJAX call, HTML escaping on output, HTTP-only session cookie, admin-only routes, throttled logins.

---

## 2. Install on XAMPP (Windows) — 5 minutes

### A. Copy the files
1. Download/extract this folder.
2. Copy `aronium-pos` into `C:\xampp\htdocs\` so you have  
   `C:\xampp\htdocs\aronium-pos\index.php`

### B. Start Apache and MySQL
Open **XAMPP Control Panel** → *Start* **Apache** and **MySQL**.

### C. Run the installer
Go to **http://localhost/aronium-pos/install.php**

Fill in:
- **Host:** `localhost`
- **Database name:** `aronium_pos`
- **Username:** `root`
- **Password:** *(leave empty — that's the XAMPP default)*
- **Administrator account:** pick your username and password

Click **Install**. It creates the database, imports the schema and demo catalogue, creates your admin user, and writes the credentials into `config/config.php`.

### D. Sign in
**http://localhost/aronium-pos/** → log in with the account you just created.

> **No installer?** (e.g. your Apache blocks it) Do it manually: open **http://localhost/phpmyadmin** → *Import* → choose `database/aronium_pos.sql` → *Go*. Then edit `config/config.php` if your MySQL user/password differ.  
> Default demo logins after a manual import: `admin` / `admin123` and `cashier` / `cashier123`.

### E. Optional clean-up
Delete `install.php` (it refuses to re-run anyway while `storage/installed.lock` exists).

---

## 3. Using it on the shop network

1. Find your PC's LAN IP: `ipconfig` → e.g. `192.168.1.25`.
2. From another device on the same Wi-Fi/LAN open **http://192.168.1.25/aronium-pos/**.
3. If Windows Firewall blocks it, allow **Apache HTTP Server** on private networks.

Any tablet, phone or second PC can then work as a cashier terminal — no install needed on those devices.

---

## 4. Keyboard shortcuts (POS screen)

| Key | Action |
|---|---|
| `F2` | Focus the search / scan box |
| `Enter` | Add the highlighted product, or look up a scanned barcode |
| `F8` | Complete the sale |
| `Esc` | Clear the current sale |

A USB barcode scanner works like a keyboard: click into the search box and scan.

---

## 5. Folder map

```
aronium-pos/
├─ index.php               front controller (all screens: index.php?page=…)
├─ api.php                 JSON API used by the POS (search + checkout)
├─ install.php             one-click database installer
├─ config/config.php       ← your DB credentials + app name/timezone
├─ app/
│  ├─ bootstrap.php        session, config, DB, settings cache
│  ├─ db.php               PDO connection + query helpers
│  ├─ helpers.php          escaping, money, CSRF, auth, settings, CSV
│  ├─ controllers/         auth, pos, products, sales, reports, settings, errors
│  └─ views/               one file per screen + layout/header|footer
├─ assets/                 css/app.css, js/app.js, js/pos.js
├─ database/aronium_pos.sql  schema + demo catalogue
└─ storage/                installer lock file (keep writable)
```

---

## 6. Tweaking it

**Change prices/stock quickly:** *Products* → *Edit* → adjust *Stock on hand* or use **Adjust stock** for deliveries and damages (every change is logged under *Recent movements*).

**Tax:** *Settings → Currency & tax*. Use **exclusive** if you add tax on top of the shelf price, **inclusive** if your prices already include it. Individual products can be flagged **Tax exempt**.

**Currency:** set any symbol (₱, $, €, £…) and whether it prints before or after the amount.

**Add a product:** *Products → Add product*. Barcode is optional but handy for scanning.

---

## 7. Backups

- **phpMyAdmin:** select `aronium_pos` → *Export* → *Go* (keeps a `.sql` snapshot).
- **Or** use the built-in *Export CSV* buttons on Products, Sales and Reports for spreadsheets.

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| *"The app could not reach the database"* | Make sure MySQL is started in XAMPP; check `config/config.php` (host `localhost`, user `root`, empty password). |
| Blank white page | Turn on `APP_DEBUG` in `config/config.php` and check `C:\xampp\php\logs\php_error_log`. |
| Installer says `config/config.php is not writable` | Edit that file by hand with the same host/db/user/password you typed. |
| Page loads but styles are missing | Make sure the whole `assets/` folder was copied. |
| `404` on every page but the first | You're probably opening files directly instead of through `http://localhost/…`. |
| Wrong time on receipts | Change `APP_TIMEZONE` in `config/config.php` (e.g. `Asia/Manila`). |
| Receipt prints on A4 with lots of white space | Use your browser's print dialog → set paper size/margins, or switch to 58 mm in *Settings*. |

---

## 9. Requirements

- XAMPP for Windows (or any Apache + PHP 7.4/8.x + MySQL 5.7+/MariaDB 10.2+ stack)
- PHP extensions: `pdo_mysql` (enabled by default in XAMPP). No Composer, no `mbstring` dependency.
- A modern browser (Chrome, Edge, Firefox, Safari)

Licensed freely for personal and small-business use.
