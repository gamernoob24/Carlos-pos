# Carlo's Burger — POS & Inventory System

A Point-of-Sale and inventory system built for a small burger restaurant, aligned to the
project SRS: **PHP 8 / MySQL (MariaDB) / HTML5 / CSS3 / vanilla JavaScript**, no framework,
no Composer, no CDN — it runs fully offline on XAMPP.

---

## 1. Requirements

| Need | Version |
|---|---|
| XAMPP (or any PHP + MySQL stack) | PHP 8.0+, MySQL 5.7+ / MariaDB 10.2+ |
| PHP extensions | `pdo_mysql` (enabled by default in XAMPP) |
| Browser | Any modern browser (Chrome, Edge, Firefox, Safari) |

No internet connection is required at runtime. Every style, script and icon ships with the app.

---

## 2. Installation

### Option A — Guided installer (easiest)

1. Copy the whole `Carlos-pos-main` folder into `C:\xampp\htdocs\` (or `/opt/lampp/htdocs/`).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/Carlos-pos-main/install.php`.
4. Enter your MySQL host, user, password and a database name (default `Carlos_pos`).
5. Click **Install**. The installer creates the database, imports all 13 tables and loads the
   demo data (15 menu items, 23 ingredients, 56 recipe lines, 10 modifiers, 2 users).
6. Delete `install.php` afterwards (the app will remind you).

### Option B — Manual import

1. Open `http://localhost/phpmyadmin` → **New** → create a database named `Carlos_pos`
   with collation `utf8mb4_general_ci`.
2. Select it → **Import** → choose `database/Carlos_pos.sql` → **Go**.
3. Edit `app/db.php` if your MySQL user/password differ from `root` / *(empty)*.

### Already running an older version?

Import `database/migrate_v2.sql` instead — it is idempotent and upgrades a v1 database
(7 tables) to v2 (13 tables) **without deleting any existing sales data**.

---

## 3. Demo accounts

| Username | Password | Role | Can do |
|---|---|---|---|
| `admin` | `admin123` | Manager | Everything — menu, recipes, ingredients, shifts, sales, reports, users |
| `cashier` | `cashier123` | Cashier | Take orders, view/advance the kitchen queue, open & close **own** shift, own profile |

Passwords are stored as **bcrypt** hashes (`password_hash` / `password_verify`).

---

## 4. How the SRS requirements are met

### Entities (5/5)
| SRS entity | Table(s) |
|---|---|
| Users | `users` (role, bcrypt hash, active flag) |
| Orders | `sales` (+ `shifts` for the drawer session) |
| Order Items | `sale_items`, `sale_item_modifiers`, `sale_item_ingredients` |
| Products / Menu Items | `products`, `categories` |
| Ingredients / Inventory | `ingredients`, `product_ingredients` (recipes), `stock_movements`, `modifiers` |

### Business rules
- **"A product is composed of one or many ingredients"** — `product_ingredients` is the recipe
  (bill of materials). Selling a burger deducts the bun, patty, cheese, lettuce, tomato and sauce
  from raw stock, not just a unit of "burger".
- **"A cashier opens one cash drawer session per shift"** — `shifts` enforces a single open
  shift per user; every order is stamped with `shift_id`; closing compares counted cash against
  `opening_cash + cash sales` and reports the variance.

### Functional requirements
- **Order modifiers** — pick "Extra cheese", "No onions", "Less ice", "Add fries" … at checkout.
  Each modifier carries a price delta, is saved per line (`sale_item_modifiers`) and prints on the
  kitchen ticket and the receipt.
- **Kitchen queue** — new orders land as `queued`, then move `queued → preparing → completed`.
  The kitchen board is live (polls the API, reloads only when something changed), shows waiting
  time, and flags orders older than 10 minutes.
- **Role separation** — cashiers are limited to checkout, the kitchen board and their own shift.
  Every manager route is guarded server-side by `require_admin()`; a cashier POSTing directly to
  a manager action is rejected and redirected, never silently allowed.
- **Reporting** — sales by day, payment mix, top sellers, category split, **peak ordering hours**
  (hourly bar chart with the busiest hour highlighted), cashier performance, inventory valuation,
  and low-stock / low-ingredient warnings.
- **Search** — instant client-side search across menu, products, ingredients, sales.

### Non-functional requirements
- **< 2 s response** — every page renders in ~2–4 ms locally (no framework, no external assets).
- **Password security** — bcrypt via `password_hash()`, session fixation protection.
- **Data integrity / ACID** — checkout runs inside a transaction and locks stock rows with
  `FOR UPDATE`, so two tills can never oversell the last patty (verified: 3 simultaneous orders
  for 1 remaining patty → 1 accepted, 2 rejected, stock 0).
- **Controlled access** — CSRF tokens on every form and every AJAX call; PDO prepared statements
  everywhere; output escaped through `e()`.
- **Ease of use** — one-screen checkout with keyboard shortcuts (`F2` search, `F8` pay,
  `Ctrl+Enter` charge), printable receipts.

---

## 5. Walkthrough (5 minutes)

1. Log in as **cashier** → **Cash Shifts → Open shift**, put ₱500 in the drawer.
2. Go to **Point of Sale** → tap **Carlo's Special Burger** → choose **Extra cheese** → **Pay** → **Cash ₱200**.
3. Open **Kitchen** — the ticket is there with the modifier chip. Press **Start preparing** → **Mark served**.
4. Check **Ingredients → Stock movements** — the patty, bun, cheese, lettuce, tomato and sauce
   have each been deducted.
5. Back in **Cash Shifts → Close shift**, count the drawer; the variance shows immediately.
6. Log in as **admin** → **Reports** → see peak hours, best sellers and stock valuation.
7. **Recipes → Edit** on any item to change how much of each ingredient one serving consumes —
   the next sale follows the new recipe.

---

## 6. Project structure

```
Carlos-pos-main/
├── index.php               front controller (route table + role gates)
├── install.php             guided installer (delete after setup)
├── app/
│   ├── bootstrap.php       session, autoload, DB bootstrap
│   ├── db.php              PDO connection + query helpers
│   ├── helpers.php         money, csrf, auth, stock, esc helpers
│   ├── controllers/        auth, pos, api, products, inventory (recipes + modifiers),
│   │                       kitchen, shifts, sales, reports, users, settings
│   └── views/              layout, login + one view per page (pos, kitchen, shifts,
│                           ingredients, recipes, modifiers, sales, reports, …)
├── assets/
│   ├── css/app.css         full theme (no CDN, no webfonts)
│   └── js/{app.js,pos.js}  checkout UI, modifier dialog, live kitchen board, charts
└── database/
    ├── Carlos_pos.sql      full schema + Carlo's Burger demo data (fresh install)
    └── migrate_v2.sql      idempotent v1 → v2 upgrade for existing installs
```

---

## 7. Notes

- The database connection defaults to `root` with an empty password on `127.0.0.1`;
  change it in `app/db.php` (or let `install.php` write it for you).
- Tax is **inclusive** by default at 12%; change it in **Settings**.
- `database/Carlos_pos.sql` is safe to re-run (uses `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE`).
- To reset the demo data: drop the database and re-import `Carlos_pos.sql`.
