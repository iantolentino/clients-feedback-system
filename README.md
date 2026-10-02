# Client Health Tracker

PHP 8+ and MySQL/MariaDB are required. Copy `config.example.php` to `config.php` and set the database credentials. `config.php` is ignored by Git and must stay private. For a new database, import `sscoadmin_clients_feedback.sql`; it creates tables and a view, but no accounts. Create the first admin account separately with a PHP `password_hash()` value. For an existing pre-update database, run `migrations/2026_10_02_feedback_questions.sql` once before deploying the updated PHP files. It adds columns and preserves older submissions. Use HTTPS. The public form is `feedback.php`; admins sign in at `login.php`.

Local review: `http://localhost/client-feedback/feedback.php` and `http://localhost/client-feedback/login.php`. Accounts are stored in the database and are not part of this repository.

After deployment, confirm that at least one trusted account has `role='admin'` and `is_active=1` in `sales_admins`. Only an admin can register accounts in Settings; sales accounts can change their own password.

The dashboard Excel export downloads every submission, not just the visible page or current filter. It requires PHP's `zip` and `xmlwriter` extensions. The submission page's Save a copy dialog offers PNG and direct PDF download; PDF generation paginates long answers. The bundled pdfmake browser files are distributed under the license in `assets/vendor/pdfmake/LICENSE`.
