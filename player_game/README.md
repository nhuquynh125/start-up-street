# Player & Account System (PHP + MySQL)

## Requirements
- PHP 8.1+ with PDO MySQL enabled
- MySQL 8+ / MariaDB
- XAMPP, Laragon, or another local PHP server

## 1. Install
1. Copy this folder into your web root (for XAMPP: `C:\xampp\htdocs\player_account_system`).
2. Open phpMyAdmin and import `database/schema.sql`.
3. Edit `config/config.php` if your MySQL username/password differs.
4. Start Apache and MySQL.
5. Visit `http://localhost/player_account_system/public/`.

## 2. Included features
- Registration with `password_hash()`
- Login with `password_verify()`
- PHP session authentication
- Logout
- Authenticated profile API
- Profile update (display name and avatar)
- Initial player profile and starting money created in the same transaction as the user
- Frontend login/register/profile/HUD
- CSRF token for state-changing API requests
- Prepared SQL statements and server-side validation

## 3. API endpoints
All endpoints return JSON.
- `POST /api/register.php` JSON: `username`, `email`, `password`, `display_name`
- `POST /api/login.php` JSON: `username`, `password`
- `POST /api/logout.php`
- `GET /api/me.php`
- `POST /api/update_profile.php` JSON: `display_name`, `avatar`

The `public/index.php` page calls the API using relative URLs. If your web server uses a different base path, adjust the `API_BASE` constant in `public/app.js`.

## 4. Important
- This is a starter implementation for local development, not a production deployment.
- Set a strong database password before deployment, use HTTPS, and configure secure cookie settings.
- Never trust money, user IDs, roles, or profile values sent by the browser. The server decides the authenticated user from the session.
- Do not let the client directly set currency or admin status. Add separate server-side game APIs for money changes.
