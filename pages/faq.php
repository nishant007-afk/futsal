<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$logged_in = is_logged_in();
$user = $logged_in ? current_user() : null;
$isManager = $user !== null && $user['role'] === 'manager';

$page_title = 'Frequently Asked Questions';
$page_description = 'Answers to common questions about booking a futsal court, paying with a QR code, accounts and managing courts.';
require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="nav-back mob-title-back" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Frequently Asked Questions</h1>
    </div>
</div>

<form class="faq-search" role="search" aria-label="Search FAQs">
    <input type="search" id="faqSearch" placeholder="Search questions…" autocomplete="off">
    <button type="submit" class="btn btn-ghost btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
</form>

<div class="faq-list">
    <h2 class="faq-section-title"><i class="fa-solid fa-calendar-check"></i> Booking</h2>
    <details class="faq-item">
        <summary>How do I book a court?</summary>
        <p>In a few taps you can lock in a slot:</p>
        <ul>
            <li><strong>Find a court.</strong> Use the search bar or open the <a href="<?php echo base_url('pages/courts.php'); ?>">courts list</a> / <a href="<?php echo base_url('pages/map.php'); ?>">map</a>.</li>
            <li><strong>Pick a date &amp; slot.</strong> On the court page, tap a date and choose a free time on the calendar.</li>
            <li><strong>Confirm your booking.</strong> Check the summary (price, time, repeats) and confirm. The slot is held for you while you pay.</li>
            <li><strong>Pay.</strong> See <a href="#cat-payment">Paying</a> below, then you're done.</li>
        </ul>
        <p>You can review, reschedule or cancel the booking later from <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>.</p>
    </details>
    <details class="faq-item">
        <summary>Do I need an account to book?</summary>
        <p>Yes, you need a player account so your booking is linked to you and you get the confirmation and receipt. Creating an account is free.</p>
    </details>
    <details class="faq-item">
        <summary>Can I book a recurring or weekly slot?</summary>
        <p>Yes. When confirming your slot, choose "Repeat weekly" and set how many weeks you want the same slot repeated.</p>
    </details>
    <details class="faq-item">
        <summary>Can I change or reschedule my slot after booking?</summary>
        <p>Yes, as long as the court&rsquo;s cancellation window allows it. Open the booking in <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a> and choose Reschedule.</p>
    </details>

    <h2 class="faq-section-title"><i class="fa-solid fa-wallet"></i> Payments</h2>
    <details class="faq-item">
        <summary>How do I pay for a booking?</summary>
        <p>After you choose a slot, you pick how to pay, then scan to transfer the money:</p>
        <ul>
            <li><strong>Choose an option.</strong> Pay a 20% advance now (balance at the court) or pay the full amount.</li>
            <li><strong>Scan the QR.</strong> The court owner&rsquo;s payment QR is shown. Open your payment app (Khalti, eSewa, IME Pay, etc.) and scan it.</li>
            <li><strong>Confirm the transfer.</strong> Approve the amount in your app. Once done, your slot is confirmed and a receipt is emailed to you.</li>
            <li><strong>Check status.</strong> A "Paid via QR" badge appears on the booking once the payment is recorded.</li>
        </ul>
        <p>If the court has no QR code, you can pay at the venue instead. See <a href="#cat-cancel">Cancellations</a> for the refund rules.</p>
    </details>
    <details class="faq-item">
        <summary>What if the court has no QR code?</summary>
        <p>You can still pay at the venue with cash or card when you arrive. The booking is held as reserved, and the court collects payment on site.</p>
    </details>
    <details class="faq-item">
        <summary>Is my payment secure?</summary>
        <p>The transfer happens inside your own payment app, so none of your card or PIN details pass through GoalSpace. We never store card data.</p>
    </details>
    <details class="faq-item">
        <summary>I scanned the QR but nothing changed. Is my booking confirmed?</summary>
        <p>Your slot is reserved in the system once you choose to pay (full or advance). Scanning the QR completes the actual money transfer to the court. If the court collects payment at arrival instead, they will mark it paid on arrival.</p>
    </details>
    <details class="faq-item">
        <summary>When will I get a receipt?</summary>
        <p>A receipt is emailed as soon as the payment is recorded. You can also view and download it anytime from the booking details. Open the booking in <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a> and tap <strong>Receipt</strong>.</p>
    </details>

    <h2 class="faq-section-title"><i class="fa-solid fa-user"></i> Account</h2>
    <details class="faq-item">
        <summary>How do I create an account?</summary>
        <p>Tap <strong>Sign up</strong> in the top right, enter your name and email, set a password, and pick the <strong>Player</strong> role. You&rsquo;ll get a verification email to confirm your address.</p>
    </details>
    <details class="faq-item">
        <summary>I forgot my password</summary>
        <p>Use the "Forgot password?" link on the login page. We&rsquo;ll email you a one-time code to reset it.</p>
    </details>
    <details class="faq-item">
        <summary>Can I update my phone number or email?</summary>
        <p>Yes, in <a href="<?php echo base_url('pages/profile.php'); ?>">Profile</a> you can update your details. Changing email or phone may require re-verifying.</p>
    </details>

    <h2 class="faq-section-title"><i class="fa-solid fa-right-left"></i> Cancellations &amp; refunds</h2>
    <details class="faq-item">
        <summary>What is the cancellation policy?</summary>
        <ul>
            <li>Cancel <strong>24 hours or more</strong> before your slot &rarr; full refund.</li>
            <li>Cancel <strong>within 24 hours</strong> &rarr; we keep the advance as credit for a future booking.</li>
            <li><strong>No-shows</strong> &rarr; no refund, though the advance may be kept as credit if you contact us.</li>
        </ul>
        <p>See <a href="<?php echo base_url('pages/page.php?slug=terms'); ?>">Terms of service</a> for the full policy.</p>
    </details>
    <details class="faq-item">
        <summary>How do I cancel a booking?</summary>
        <p>Cancel directly from <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>:</p>
        <ul>
            <li>Open the booking you want to cancel.</li>
            <li>Tap <strong>Cancel booking</strong> (or <strong>Cancel series</strong> for a repeating slot).</li>
            <li>Confirm on the dialog. You&rsquo;ll see the refund amount before you submit.</li>
        </ul>
    </details>
    <details class="faq-item">
        <summary>Will I get a refund if I don&rsquo;t show up?</summary>
        <p>If you cancel in time you get a full refund. Late cancellations or no-shows are non-refundable, though the advance may be kept as credit for your next booking.</p>
    </details>

    <h2 class="faq-section-title"><i class="fa-solid fa-store"></i> For court owners</h2>
    <details class="faq-item">
        <summary>How do I add my court?</summary>
        <p>Sign up with the Manager role, then open <strong>My Grounds</strong> and click <strong>Add Ground</strong>. Fill in the details and the map pin, then Save.</p>
    </details>
    <details class="faq-item">
        <summary>How do I add my payment QR code?</summary>
        <ul>
            <li>Open <a href="<?php echo base_url('manager/grounds.php'); ?>">My Grounds</a> and click <strong>Edit</strong> on the court.</li>
            <li>Open the <strong>Payment QR code</strong> card, choose an image of your payment QR (Khalti/eSewa/IME Pay), and click <strong>Save QR</strong>.</li>
            <li>When a player pays, the QR is shown and the booking is recorded as paid via QR.</li>
        </ul>
        <p>To remove or replace it later, use the same card (upload a new image or click the remove icon).</p>
    </details>
    <details class="faq-item">
        <summary>How and when do I get paid?</summary>
        <ul>
            <li><strong>QR payments.</strong> Players scan your QR and transfer directly to your account. The booking is marked <em>Paid via QR</em> so you can match the incoming transfer.</li>
            <li><strong>At the court.</strong> If a player pays on arrival, open <a href="<?php echo base_url('manager/bookings.php'); ?>">Bookings</a>, find the booking, and click <strong>Mark paid</strong>.</li>
            <li>Payments show in your <a href="<?php echo base_url('manager/dashboard.php'); ?>">dashboard</a> alongside upcoming bookings.</li>
        </ul>
    </details>
    <details class="faq-item">
        <summary>How do I mark a booking as paid on arrival?</summary>
        <p>In <strong>Bookings</strong>, find the booking and click <strong>Mark paid</strong>. It&rsquo;s immediately recorded as paid at court.</p>
    </details>
    <details class="faq-item">
        <summary>Can I block dates I&rsquo;m not available?</summary>
        <p>Yes, on the ground edit page, open <strong>Blocked dates</strong>, pick a date and add an optional note.</p>
    </details>

    <h2 class="faq-section-title"><i class="fa-solid fa-circle-question"></i> Technical</h2>
    <details class="faq-item">
        <summary>The site isn&rsquo;t loading on mobile</summary>
        <p>Make sure you&rsquo;re using a modern browser (Chrome, Safari, Firefox, Edge) and that JavaScript is enabled. Clear your cache and try again.</p>
    </details>
    <details class="faq-item">
        <summary>I&rsquo;ve been charged twice</summary>
        <p>Contact us with your booking reference and a screenshot of both charges. We&rsquo;ll investigate within 48 hours.</p>
    </details>
    <details class="faq-item">
        <summary>Where can I get more help?</summary>
        <p>Visit the <a href="<?php echo base_url('pages/page.php?slug=help'); ?>">Help centre</a>, or <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">contact us</a>. We usually reply within a day.</p>
    </details>
</div>

<?php if ($logged_in && $isManager): ?>
<div class="notice faq-help">
    <i class="fa-solid fa-circle-info"></i>
    <span>Managing a court? <a href="<?php echo base_url('pages/how_to_use.php#managers'); ?>">Read the manager guide</a> for step-by-step help with payments, bookings and blocked dates.</span>
</div>
<?php endif; ?>

<script>
(function () {
    var form = document.querySelector('.faq-search');
    if (!form) return;
    var input = form.querySelector('input[type="search"]');
    var items = document.querySelectorAll('.faq-item');
    var filter = function () {
        var term = (input.value || '').toLowerCase();
        items.forEach(function (item) {
            var summary = item.querySelector('summary');
            var text = summary ? summary.textContent.toLowerCase() : '';
            var shown = text.indexOf(term) !== -1;
            item.style.display = shown ? '' : 'none';
            if (shown && term.length > 0) {
                item.open = true;
            }
        });
    };
    input.addEventListener('input', filter);
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        filter();
        var visible = Array.prototype.filter.call(items, function (i) { return i.style.display !== 'none'; });
        if (visible.length && visible[0].parentNode) {
            visible[0].parentNode.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
(function () {
    var items = document.querySelectorAll('.faq-item');
    var sync = function (item) {
        item.style.setProperty('--faq-chev', item.open ? '"\\f077"' : '"\\f078"');
    };
    items.forEach(function (item) {
        sync(item);
        item.addEventListener('toggle', function () { sync(item); });
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
