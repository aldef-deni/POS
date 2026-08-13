POS SaaS (Starter)
===================

Quick setup and notes for the POS features added:

1. Install composer dependencies:

   composer install

2. Install npm and build assets (if using Vite):

   npm install
   npm run build

3. Copy and configure environment:

   cp .env.example .env
   php artisan key:generate

4. Run migrations:

   php artisan migrate

5. Optional: seed an Owner user or create one via tinker.

Notes:
- POS routes added: `/pos/login`, `/pos`, `/pos/print`.
- Operator login for POS uses the regular users table but creates a separate POS session (no dashboard login).
- A `role` column was added to `users` via migration. Roles: `Owner`, `Supervisor`, `Kasir`.
- Products table includes `sku`, `barcode_value`, and `qr_value` fields; values auto-filled on create.
- Composer dependencies updated with barcode, qrcode, and dompdf packages — run `composer install` after pulling changes.

Next recommended tasks (I can implement on request):
- Integrate actual barcode and QR image generation using the added packages.
- Implement advanced reporting (date range, PDF export) using `barryvdh/laravel-dompdf`.
- Implement role-based dashboard guard (register middleware in Kernel).
- Create manager dashboard pages for Owner and Supervisor.
