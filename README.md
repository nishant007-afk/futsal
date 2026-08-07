# Futsal Booking & Management System (GoalSpace)

A full-featured futsal court booking website with **three roles** — Admin, Futsal Manager (court owner) and Player — built with vanilla HTML, CSS, JavaScript, PHP and MySQL (no frameworks). Runs locally via XAMPP.

## Roles & duties

| Role    | Duties |
|---------|--------|
| **Admin** | Global control: manage all users (change roles, delete, export), manage all grounds & settlements, view/confirm/cancel all bookings, revenue stats, resolve contact messages |
| **Manager** | Court owner: manage **only their own grounds** (add/edit/delete + photos + location map picker), view/confirm/cancel bookings on their grounds, run promotions, track revenue |
| **Player** | Browse & search grounds, pick a date + time slot, book (incl. weekly repeat), waitlist sold-out slots, pay, reschedule, add reviews, manage notifications |

## Features

- **Role-based access control** — separate `admin/` and `manager/` panels; server-side guards (`require_login`, `require_admin`, `require_manager`, `require_player`); players see "My Bookings".
- **Grounds & ownership** — grounds belong to a manager via `grounds.manager_id`; ownership enforced server-side.
- **Location picker** — map-based picker (Leaflet + CARTO tiles) with a Google-style red pin; search/reverse-geocode proxied through `ajax/place_search.php` (Photon + Nominatim, Nepal-only).
- **Photo gallery** — multiple images per ground with prev/next arrows, smaller thumbnails, and a fullscreen zoom lightbox.
- **Booking engine** — hourly (or configurable) slots, server-side double-booking prevention, repeat-weekly booking, sold-out waitlist.
- **Payments & receipts** — payment flow, receipt generation (printable HTML + downloadable PDF), reschedule.
- **Promotions** — managers can create promo codes / discounts on their grounds.
- **Reviews & ratings** — star ratings + comments from players.
- **Auth & security** — bcrypt password hashing, email verification, OTP flows (login step-up, two-step password reset), login throttling/lockout with countdown, Google OAuth sign-in, CSRF tokens, CSP headers. No session tokens stored client-side; all auth checks happen server-side.
- **Mobile-first responsive UI** — Font Awesome icons, scroll-reveal animations, animated hero, skeleton page loaders, off-canvas mobile nav (collapses at 820px), scrollable tables.

## Setup (XAMPP)

1. Copy this `futsal` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin and import `database.sql` (creates the `futsal_booking` database, tables and seed data).
4. Copy `config/db.example.php` → `config/db.php` and `config/mail.example.php` → `config/mail.php`, then fill in your DB and SMTP credentials. These are gitignored — never commit real secrets. If MySQL is on a non-default port, adjust `DB_PORT` (this machine's XAMPP runs MySQL on **3307**).
5. (Optional Google sign-in) put `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` in `.env`. OAuth exchange with Google is done server-side in `auth/google_callback.php`.
6. Visit http://localhost/futsal. For the map search proxy to return results, the server needs outbound HTTPS access.

## Demo accounts (from seed data)

| Role    | Email               | Password    |
|---------|---------------------|-------------|
| Admin   | admin@futsal.com    | password123 |
| Manager | manager@futsal.com  | password123 |
| Player  | john@example.com    | password123 |

You can also register a new account from the sign-up page.

## Project structure

```
futsal/
├── database.sql              # DB schema + seed data (3 roles)
├── index.php                 # Homepage (role-aware landing)
├── config/                   # db/mail/env helpers + .example files (gitignored)
├── includes/
│   ├── header.php            # Role-aware nav + mobile drawer + skeleton loader
│   ├── footer.php            # Scripts, closing tags
│   ├── functions.php         # Auth helpers, ownership checks, flash, prices, slots, reviews
│   └── views/                # Shared partials (landing, player home, location picker)
├── assets/
│   ├── css/style.css         # Icons, animations, responsive, map pin, gallery, lightbox
│   ├── css/leaflet/          # Leaflet map styling
│   ├── js/script.js          # Slot picker, gallery, autogate, notifications, menu, reveal
│   ├── js/leaflet/           # Leaflet core
│   └── js/map-picker.js      # Ground location picker (search + pin)
├── ajax/
│   └── place_search.php      # Proxy: Photon + Nominatim place search / reverse geocode
├── auth/
│   └── google_callback.php   # Server-side Google OAuth exchange
├── pages/                    # Player-facing pages
│   ├── login register otp_verify verify forgot_password reset_password
│   │   logout login_google change_password change_password_otp
│   ├── ground.php courts.php book.php            # booking flow
│   ├── my_bookings.php booking_details.php reschedule.php
│   ├── payment.php receipt.php receipt_pdf.php confirmation.php
│   ├── profile.php notifications.php notification_details.php
│   ├── page.php contact_submit.php ics.php
├── admin/                    # Admin only (require_admin)
│   ├── dashboard.php         # Global stats + charts
│   ├── users.php             # Manage roles, delete, export
│   ├── grounds.php           # All grounds + photos + location
│   ├── bookings.php          # All bookings
│   ├── settlements.php       # Manager settlements/fees
│   └── contact_messages.php  # Resolve/delete contact submissions
└── manager/                  # Manager only (require_manager)
    ├── dashboard.php  bookings.php  grounds.php  promos.php
```

## Notes

- DB credentials in `config/db.php` default to XAMPP's `root` with no password.
- Fix photos: upload under `uploads/grounds/`, `uploads/avatars/` (gitignored — keep the `.gitkeep` files).
- Promotions, notifications, OTP email, and Google sign-in rely on the fields/steps above being configured; the app degrades gracefully if SMTP/Google aren't set.