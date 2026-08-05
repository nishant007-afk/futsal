# Futsal Booking & Management System

A futsal court booking website with **three roles** — Admin, Futsal Manager (court owner) and Player — built with HTML, CSS, JavaScript, PHP and MySQL. No frameworks. Runs locally via XAMPP.

## Roles & duties

| Role    | Duties |
|---------|--------|
| **Admin** | Global control: manage all users (change roles, delete), manage all grounds (add/edit/delete, assign an owning manager), view/confirm/cancel all bookings, see revenue stats |
| **Manager** | Court owner: manage **only their own grounds** (add/edit/delete), view/confirm/cancel bookings on their grounds, track their revenue |
| **Player** | Browse grounds, pick a date + time slot, book, and cancel their own pending bookings |

## Features

- Role-based access control (separate `admin/` and `manager/` panels; players see only "My Bookings")
- Grounds belong to a manager via `grounds.manager_id`
- Booking with hourly slots (08:00–21:00); double-booking prevented server-side (unique slot key + check)
- bcrypt password hashing
- Modern UI: Font Awesome icons, scroll-reveal animations, animated hero, responsive cards/tables

## Setup (XAMPP)

1. Copy this `futsal` folder into `C:\xampp\htdocs\`
2. Start **Apache** and **MySQL** from the XAMPP Control Panel
3. Open http://localhost/phpmyadmin and import the `database.sql` file
   (creates the `futsal_booking` database, tables and seed data)
4. Copy `config/db.example.php` to `config/db.php` and `config/mail.example.php` to `config/mail.php`,
   then fill in your database and SMTP credentials (these files are gitignored, never commit real secrets).
   If your MySQL uses a non-default port, adjust `DB_PORT` in `config/db.php`
   (this machine's XAMPP runs MySQL on **3307**)
5. Visit http://localhost/futsal

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
├── index.php                 # Homepage
├── config/db.php             # DB connection (port 3307) + session
├── includes/
│   ├── header.php            # Role-aware nav
│   ├── footer.php
│   └── functions.php         # Auth helpers, ownership checks, flash, prices
├── assets/
│   ├── css/style.css         # Icons, animations, responsive
│   └── js/script.js          # Slot picker, scroll reveal, confirm dialogs
├── pages/
│   ├── login.php  register.php  logout.php
│   ├── ground.php            # Ground detail + slot picker
│   ├── book.php              # Booking handler (POST)
│   └── my_bookings.php       # Player bookings + cancel
├── admin/                    # Admin only (require_admin)
│   ├── dashboard.php         # Global stats
│   ├── users.php             # Change roles / delete users
│   ├── grounds.php           # All grounds + assign owner
│   └── bookings.php          # All bookings, confirm/cancel
└── manager/                  # Manager only (require_manager)
    ├── dashboard.php         # Stats for owned grounds
    ├── grounds.php           # CRUD on own grounds
    └── bookings.php          # Confirm/cancel own bookings
```

## Notes

- DB credentials in `config/db.php` default to XAMPP's `root` with no password.
- Time slots are fixed hourly slots from 08:00 to 21:00.
- Ground images are placeholders (animated icons). To use real images, put files
  in `assets/img/` and swap the `<div class="card-img">` markup.
- Placeholder content only — replace seed text with real ground info.
