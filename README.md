# Carlo's Burger — POS & Inventory System

A point-of-sale and inventory system for a small burger restaurant, built with:

- PHP 8
- MySQL / MariaDB
- HTML
- CSS
- JavaScript

It runs offline on XAMPP.

## Group Members

- Gonzales, Jessie James M.
- Alavar, Christian F.
- Lao, Wenzes Luke R.
- Samiddin, Al - Khaidar J.
- Lee, Rovin Karl C.

## Tools and Software Used

- XAMPP
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.2+
- phpMyAdmin
- Web browser (Chrome, Edge, Firefox, Safari)

## System Requirements

- XAMPP or any PHP + MySQL stack
- PHP extension: `pdo_mysql`
- Modern web browser

## How to Set Up and Run the System

### Option 1: Using the Installer

1. Copy the project folder to `C:\xampp\htdocs\` or `/opt/lampp/htdocs/`.
2. Open XAMPP and start **Apache** and **MySQL**.
3. Open this link in your browser:

   `http://localhost/Carlos-pos-main/install.php`

4. Enter the MySQL host, username, password, and database name.
5. Click **Install**.
6. Wait for the database and demo data to be created.
7. Delete `install.php` after installation.

### Option 2: Manual Setup

1. Open `http://localhost/phpmyadmin`.
2. Create a new database named `Carlos_pos`.
3. Set the collation to `utf8mb4_general_ci`.
4. Import the file:

   `database/Carlos_pos.sql`

5. Update `app/db.php` if your MySQL username or password is different.

## Test Accounts

| Role | Username | Password |
|---|---|---|
| Manager / Admin | `admin` | `admin123` |
| Cashier | `cashier` | `cashier123` |

## Notes

- The system uses bcrypt password hashing.
- The cashier can only access POS, kitchen queue, and shift functions.
- The admin can manage menu items, ingredients, users, reports, and settings.
- The system works fully offline after setup.
