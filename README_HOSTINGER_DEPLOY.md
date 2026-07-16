# Hostinger Deployment Guide

## First-Time Deployment

1. Create a MySQL database in Hostinger hPanel.
2. Open phpMyAdmin for that database.
3. Import `database/hostinger_initial_import.sql`.
   - Import this only once for a new/empty database.
   - It creates the tables and default admin account.
   - It does not drop tables and does not import local test transactions.
4. Upload or pull the project files into Hostinger `public_html`.
5. Copy `db_config.example.php` to `db_config.php` on Hostinger.
6. Edit `db_config.php` with the Hostinger database name, user, and password.
7. Ensure these folders exist and are writable:
   - `uploads/payment_proofs`
   - `uploads/savings_proofs`
   - `uploads/withdrawal_proofs`
8. Login with:
   - Username: `admin`
   - Password: `admin123`

## Later File Updates

For later changes pushed from GitHub or uploaded by FTP:

- Upload/pull PHP, CSS, JS, and other source files only.
- Do not re-import `database/hostinger_initial_import.sql` into the live database.
- Do not overwrite `db_config.php` on Hostinger.
- Do not delete or overwrite the `uploads` folder on Hostinger.

This keeps the live Hostinger database values intact while applying file updates.

## GitHub Notes

The repository ignores:

- `db_config.php`
- deployment zip files
- uploaded proof images inside `uploads/*`

Keep `db_config.example.php` committed. Keep the real `db_config.php` only on the server.
