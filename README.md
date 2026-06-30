# Trade-Zenfy — Phase 1 Setup Guide

## What's included in Phase 1

- Complete database schema (13 tables)
- `config.php` — DB connection, helpers
- `includes/middleware.php` — Auth guards (no geofencing)
- `login.php` — User login page
- `register.php` — User registration page
- `logout.php` — Shared logout
- `api/register-account.php` — Registration API
- `api/login.php` — User login API
- `api/admin-login.php` — Admin login API
- `api/admin-update-user.php` — Approve/reject user API
- `admin/login.php` — Admin login page
- `admin/index.php` — Admin dashboard
- `admin/manage-user.php` — User management page
- `user/dashboard.php` — User dashboard

---

## Step 1: Database setup

```bash
mysql -u root -p < database/tradezenfy_complete_schema.sql
```

---

## Step 2: Update config.php

Open `config.php` and set:

```php
define('DB_HOST', '103.159.65.195');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'tradezen_data');
define('SITE_URL', 'https://trade-zenfy.com');
```

---

## Step 3: Set file permissions

```bash
chmod 755 -R /home/tradeze2/public_html
chmod 777 -R /home/tradeze2/public_html/uploads
```

---

## Step 4: Update default admin password

The default admin seeded in the DB uses password `password` (bcrypt).
**Change it immediately after first login via:**

```sql
UPDATE admins
SET password = '$2y$10$YOUR_BCRYPT_HASH'
WHERE username = 'admin';
```

Generate a bcrypt hash with:
```php
echo password_hash('YourNewPassword', PASSWORD_BCRYPT);
```

---

## Step 5: Test the flows

| Flow | URL |
|------|-----|
| User Registration | `/register.php` |
| User Login | `/login.php` |
| User Dashboard | `/user/dashboard.php` |
| Admin Login | `/admin/login.php` |
| Admin Dashboard | `/admin/index.php` |
| Admin Manage Users | `/admin/manage-user.php` |

---

## Default Admin Credentials
- Username: `admin`
- Password: `password`
- ⚠️ Change immediately after first login!

---

## Phase 2 (next)
- Deposit form + proof upload
- Admin deposit verification
- Withdrawal request form
- Admin withdrawal approval
- Balance management
