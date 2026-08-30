# Deploying GoalSpace to InfinityFree (free hosting)

Everything here is free: hosting, subdomain (`yourname.infinityfreeapp.com`),
MySQL database, and an SSL certificate. You only need an email address to
sign up.

---

## Step 1 - Create your InfinityFree account

1. Go to **https://www.infinityfree.com** and click **Create account**.
2. Enter your email, a username and a password (use a password manager).
3. Check your inbox, open the confirmation email, and log in to the
   **control panel** (vistapanel).

## Step 2 - Create the hosting account (get your free subdomain)

1. In the control panel, click **Create account** (top right).
2. Fill in:
   - **Account name:** something like `goalspace`
   - **Domain / subdomain:** pick a free subdomain, e.g. `goalspace-np`
     (your site will be at `https://goalspace-np.infinityfreeapp.com`)
   - **Password:** leave random/auto-generated (or set your own)
3. Click **Create**. Your free site is now created.

## Step 3 - Create the MySQL database

1. In the control panel, open the account you just made.
2. Go to **MySQL Databases** (in the left menu).
3. Click **Create database**, give it a name like `goalspace`.
4. The panel shows the real values you need. Copy them now:
   - **Hostname:** `sqlXXX.infinityfree.com`
   - **Username:** `if0_XXXXX`
   - **Password:** (shown once - copy it)
   - **Database name:** `if0_XXXXX_goalspace`
   - Port: `3306`
5. Open **phpMyAdmin** (button next to your database).
   - On the left, select your database.
   - Click the **Import** tab, choose the `database.sql` file from this
     package, and click **Go**.
   - You should see a success message with the tables listed.

## Step 4 - Upload the website files

Upload the **contents of this package** (not the folder itself) into the
`htdocs` folder of your account:

- **Option A - File Manager:** in the control panel, open your account >
  **File Manager** > `htdocs`. Delete the default `index.html` and upload the
  files with the **Upload** button (or drag & drop). Upload in batches if the
  browser uploader struggles with many files.
- **Option B - FTP:** use any FTP client (FileZilla) with the FTP details
  shown in **FTP Accounts** in the control panel. Drag the package contents
  into `htdocs`.

Make sure the upload finishes fully (all folders: `assets`, `config`,
`includes`, `pages`, `uploads`, etc.).

## Step 5 - Create the .env file with your database details

1. In the File Manager, find `.env.example` in `htdocs`.
2. Open it in the editor, and fill in your real database values from Step 3:
   ```
   DB_HOST=sqlXXX.infinityfree.com
   DB_USER=if0_XXXXX
   DB_PASS=your_real_db_password
   DB_NAME=if0_XXXXX_goalspace
   ```
3. Save the file, then **rename it to `.env`** (no leading dot is needed
   after all - the exact name is `.env`).

> If you downloaded this package, `.env.example` is also on your computer -
> you can fill it in there and upload it as `.env` directly.

## Step 6 - Turn on HTTPS (free SSL)

1. In the control panel, open **SSL Certificates** (or **Free SSL
   Certificates**).
2. Your subdomain should already have a certificate issued automatically.
   If not, click **Issue SSL Certificate** for it.
3. Once issued, the site is available at `https://yourname.infinityfreeapp.com`.
   Keep `APP_SCHEME=https` in `.env` (already set) so all traffic is forced
   to HTTPS and cookies are marked secure.

## Step 7 - You're live!

Open `https://yourname.infinityfreeapp.com`. If the homepage loads, done!

Test these so nothing surprises you:

- **Admin:** log in with `admin@futsal.com` / `password123`, then change the
  password right away (Security tab in your profile).
- **Register a player** - the verification code will be shown **on screen**
  because InfinityFree cannot send email (see notes below).
- **Create a ground** as a manager (manager@futsal.com / password123).
- **Install as app (PWA):** on your phone, the "Install GoalSpace" prompt
  appears after ~12 seconds, now that the site is on HTTPS.

---

## Notes & limitations of free hosting

| Feature | What to expect |
|---|---|
| **Email** | InfinityFree blocks outbound SMTP on free plans. Booking/OTP emails cannot be sent. The app already handles this: verification codes are shown **on screen**, and in-app notifications still work. If you later buy a domain + cheap SMTP (e.g. Brevo free tier), just fill `SMTP_*` in `.env`. |
| **Google sign-in** | Needs Google OAuth credentials with your domain added as a redirect URI. Optional - see `.env.example`. |
| **Uploads** | Photos/avatars save to `uploads/` and work normally. Keep `uploads/` writable (it is by default). |
| **Limits** | Free plan: ~5 GB disk, ~30-50k hits/day, 50 MB per database. Fine for a demo/small launch. |
| **Renewals** | Free hosting accounts need a login every ~30 days (InfinityFree emails a reminder) or they are suspended. Log in once a month to keep it alive. |
| **Database backups** | Export your database from phpMyAdmin before making big changes. |

## Updating the site later

Re-upload the changed files via File Manager/FTP. **Never overwrite `.env`**
with the old one if your database password changed, and never upload your
local `.env` (it has localhost credentials).

If you change any `.env` value, save and reload the page - no restart needed.
