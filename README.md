# Futsal Booking System (GoalSpace)

A futsal court booking site with three kinds of users. Admins run everything, managers own the courts, and players book slots. It is built with plain PHP, MySQL, vanilla JavaScript, HTML and CSS. No frameworks, no build steps. It runs locally on XAMPP.

## The three roles

| Role | What they can do |
|------|------------------|
| **Admin** | Everything. Manage users (change roles, delete, export), manage all grounds and settlements, view and confirm or cancel any booking, watch revenue stats, handle contact messages. |
| **Manager** | Runs their own courts. They can only touch the grounds they own, so they add/edit/delete courts plus photos and a map location, confirm or cancel bookings, create promotions and track their income. |
| **Player** | Browse and search courts, pick a date and time, book a slot (including a weekly repeat), join a waitlist for sold out slots, pay, reschedule, review a visit and manage notifications. |

## What it can do

- **Role-based access.** Separate admin and manager panels. Every page checks the role on the server before it runs, and players get their own "My Bookings" home.
- **Grounds and ownership.** Each court belongs to a manager, and the server makes sure a manager can only touch their own courts.
- **Location picker.** A Leaflet map with a red pin. Search is proxied through the server so place names and coordinates keep working.
- **Photo gallery.** Several photos per court with arrows, thumbnails and a fullscreen lightbox.
- **Booking engine.** Hourly slots, protection against double booking, weekly repeat bookings and a waitlist for sold out times.
- **Payments and receipts.** A payment flow, printable and downloadable receipts, and the option to reschedule.
- **Promotions.** Managers can make promo codes and discounts for their own grounds.
- **Star reviews.** Players rate a visit and leave a comment.
- **Accounts and security.** Bcrypt passwords, email verification, an OTP check on sensitive actions, login throttling with a cooldown, Google sign in, CSRF tokens and security headers. No login state is kept in the browser; every check happens on the server.
- **Settings hub.** Role-aware Settings pages for profile details, notification preferences (per-type: bookings, promotions, slot expiry), appearance and security.
- **A fresh look.** The UI got a clean green restyle, with Manrope for text and Barlow Condensed for headings, plain copy with no em dashes, no glassy overlays, and images converted to WebP to keep pages fast.
- **Mobile friendly.** Font Awesome icons, a bottom bar on phones that tucks away while you scroll, scroll reveal animations, a skeleton loader while pages open, and tables that scroll when there is no room.

## Set it up (XAMPP)

1. Copy the `futsal` folder into `C:\xampp\htdocs\`.
2. Start Apache and MySQL from the XAMPP Control Panel. If you'd rather not run them by hand, install each as a Windows service once and set it to start automatically.
3. Open `http://localhost/phpmyadmin` and import `database.sql`. That creates the `futsal_booking` database, its tables and the starter data.
4. Copy `config/db.example.php` to `config/db.php` and `config/mail.example.php` to `config/mail.php`, then add your database and SMTP details. These files are gitignored, so keep your real secrets out of git. This machine's MySQL runs on port 3306, so leave `DB_PORT=3306`.
5. For Google sign in, put `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` and `GOOGLE_REDIRECT_URI` in `.env`. The Google exchange happens on the server in `auth/google_callback.php`.
6. Visit `http://localhost/futsal`. The map search needs outbound HTTPS to work, so give the server internet access.

## Project structure

```
futsal/
├── database.sql              # Schema + starter data (3 roles)
├── index.php                 # Homepage, knows who is logged in
├── config/                   # db/mail/Env helpers and .example files (gitignored)
├── includes/
│   ├── header.php            # Nav for each role, mobile drawer, fonts, CSS
│   ├── footer.php            # Scripts and the bottom bar hook
│   ├── functions.php         # Auth, ownership checks, payments, slots, reviews
│   └── views/                # Shared partials (landing, player home, picker)
├── assets/
│   ├── css/style.css         # The whole look, icons, responsive, lightbox
│   ├── css/leaflet/          # Map styling
│   ├── js/core.js            # Scroll behavior, reveal, plus module loader
│   ├── js/modules/           # auth.js, booking.js, manager.js
│   ├── js/leaflet/           # Leaflet core library
│   └── js/map-picker.js      # Map location picker with search
├── ajax/place_search.php     # Proxy for Photon + Nominatim search
├── auth/google_callback.php  # Server side Google OAuth
├── pages/                    # Player facing pages (login, booking, receipts, profile)
├── admin/                    # Admin only: users, grounds, bookings, fee, content
└── manager/                  # Manager only: courts, bookings, promos
```

## Signals, tips and tools

- Demo logins are left out of this README on purpose. Sign up from the sign-up page, or look in `database.sql` for the seeded accounts.
- Photos live under `uploads/grounds/` and `uploads/avatars/`. Both are gitignored, so keep the `.gitkeep` files.
- Promotions, notifications, OTP emails and Google sign in only matter when SMTP/Google are configured. Without them the site still works fine.
- `tools/system_check.php` runs a full audit from the command line: it hits the pages over HTTP, logs in as a manager and a player, and checks CSS, PHP, the database and the cache-busting versions. Nothing should fail.
- `tools/migrate_webp.php` converts legacy uploads to WebP and tidies up the records. Old files are stored in `uploads/_legacy_backup/`.