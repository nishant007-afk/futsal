<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$logged_in = is_logged_in();
$user = $logged_in ? current_user() : null;
$isManager = $user !== null && $user['role'] === 'manager';

$page_title = 'Frequently Asked Questions';
$page_description = 'Find clear answers to common questions about booking futsal courts, digital QR payments, cancellations, and venue management on GoalSpace.';
require __DIR__ . '/../includes/header.php';
?>

<div class="content-hero">
    <div class="title-back-row">
        <a href="<?php echo base_url('index.php'); ?>" class="page-back-arrow" data-back aria-label="Go back"><i class="fa-solid fa-arrow-left"></i></a>
        <h1>Frequently Asked Questions</h1>
    </div>
    <p>Everything you need to know about booking, playing, payments, and managing futsal courts on GoalSpace.</p>
</div>

<div class="faq-search" role="search">
    <input type="search" id="faqSearch" placeholder="Search questions by keyword..." autocomplete="off">
</div>
<div class="faq-cats" aria-label="Jump to a topic">
    <a href="#cat-booking">Booking</a>
    <a href="#cat-payment">Payments</a>
    <a href="#cat-cancel">Cancellations</a>
    <a href="#cat-account">Account</a>
    <a href="#cat-owners">For Managers</a>
    <a href="#cat-tech">Technical Help</a>
</div>

<div class="faq-list">
    <h2 class="faq-section-title" id="cat-booking">Booking a Court</h2>
    
    <details class="faq-item">
        <summary>How do I book a futsal court on GoalSpace?</summary>
        <p>Reserving a court takes less than two minutes:</p>
        <ul>
            <li><strong>Find a venue:</strong> Use the search bar or open the <a href="<?php echo base_url('pages/courts.php'); ?>">all courts directory</a> to compare nearby grounds, prices, and amenities.</li>
            <li><strong>Select date and slot:</strong> Open the court profile, choose your match day, and click any available green slot on the hourly calendar.</li>
            <li><strong>Confirm reservation:</strong> Review your match duration and pricing breakdown. Once you confirm, the slot is locked immediately in our database to prevent double-booking.</li>
            <li><strong>Complete payment:</strong> Choose between a 20% advance deposit or full payment via QR code, or pay at the court counter.</li>
        </ul>
        <p>You can review your confirmed game anytime under <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>.</p>
    </details>

    <details class="faq-item">
        <summary>Do I need an account to make a reservation?</summary>
        <p>Yes. Creating a free player account ensures that your booking is permanently recorded, your receipt is delivered to your email, and you can easily reschedule or cancel if your plans change.</p>
    </details>

    <details class="faq-item">
        <summary>Can I book recurring or weekly slots for my regular team?</summary>
        <p>Yes. When scheduling on court profiles that support recurring bookings, you can select weekly recurrence to reserve the same time slot across multiple weeks in advance.</p>
    </details>

    <details class="faq-item">
        <summary>Is my reservation confirmed immediately or does it require approval?</summary>
        <p>Every booking on GoalSpace is <strong>confirmed instantly</strong> the moment you submit it. There is no waiting for manual approval or phone confirmations. The court slot is held exclusively for you.</p>
    </details>


    <h2 class="faq-section-title" id="cat-payment">Payments and Pricing</h2>

    <details class="faq-item">
        <summary>What payment options are available?</summary>
        <p>GoalSpace offers three convenient payment arrangements depending on what suits you and your team:</p>
        <ul>
            <li><strong>20% Advance Online:</strong> Lock in your court with a small advance deposit via QR transfer, then pay the remaining 80% balance in cash or card when you arrive at the venue.</li>
            <li><strong>100% Full Payment Online:</strong> Pay the complete court fee upfront so your team can show up and play without handling money at the venue.</li>
            <li><strong>Pay at Venue:</strong> For grounds that allow it, reserve your time slot and settle the full payment directly at the venue reception prior to kickoff.</li>
        </ul>
    </details>

    <details class="faq-item">
        <summary>How does paying via QR code work?</summary>
        <p>During checkout, the court owner's verified payment QR code is displayed on your screen. Simply open your preferred digital wallet app (such as Khalti, eSewa, IME Pay, or mobile banking), scan the QR code, and approve the exact amount shown. Your booking status updates automatically.</p>
    </details>

    <details class="faq-item">
        <summary>Are digital payments secure?</summary>
        <p>Yes, completely. Because QR payments occur directly inside your bank or digital wallet app, GoalSpace never handles or stores your sensitive payment credentials, debit card numbers, or transaction PINs.</p>
    </details>

    <details class="faq-item">
        <summary>Where can I find my official booking receipt?</summary>
        <p>A digital receipt is automatically generated and emailed to your registered address upon booking confirmation. You can also view or download your receipt at any time by visiting <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a> and selecting the Receipt option on your reservation card.</p>
    </details>


    <h2 class="faq-section-title" id="cat-cancel">Cancellations and Refunds</h2>

    <details class="faq-item">
        <summary>What is the GoalSpace cancellation policy?</summary>
        <p>We maintain a balanced, transparent cancellation policy for both players and court operators:</p>
        <ul>
            <li><strong>24 Hours or More Prior to Kickoff:</strong> 100% full refund or platform credit, no penalty fees.</li>
            <li><strong>Within 24 Hours of Kickoff:</strong> The advance deposit is retained as credit for future bookings to protect the venue from empty pitch loss.</li>
            <li><strong>No-Shows:</strong> Failing to appear without notice forfeits the advance deposit. Continued unexcused no-shows may limit your booking privileges.</li>
        </ul>
    </details>

    <details class="faq-item">
        <summary>How do I cancel or reschedule a confirmed booking?</summary>
        <p>You can manage your booking directly from your dashboard without making phone calls:</p>
        <ul>
            <li>Go to <a href="<?php echo base_url('pages/my_bookings.php'); ?>">My Bookings</a>.</li>
            <li>Locate the upcoming match you want to modify.</li>
            <li>Click <strong>Cancel Booking</strong> or <strong>Reschedule</strong>. You will see the exact refund or adjustment breakdown before submitting.</li>
        </ul>
    </details>

    <details class="faq-item">
        <summary>What happens if bad weather makes the court unplayable?</summary>
        <p>In cases of extreme weather or unexpected ground maintenance where the venue cannot host your match, the manager can cancel the slot. You will immediately receive a 100% refund or credit to reschedule at your convenience.</p>
    </details>


    <h2 class="faq-section-title" id="cat-account">Player Accounts and Security</h2>

    <details class="faq-item">
        <summary>How do I register an account?</summary>
        <p>Click <a href="<?php echo base_url('pages/register.php'); ?>">Sign Up</a> at the top of any page, fill in your name, email, and password, and select the Player role. You can also sign in with one tap using Google.</p>
    </details>

    <details class="faq-item">
        <summary>What should I do if I forget my password?</summary>
        <p>Click <a href="<?php echo base_url('pages/forgot_password.php'); ?>">Forgot password?</a> on the login page, enter your registered email, and we will send you a secure 6-digit one-time code to reset your password.</p>
    </details>

    <details class="faq-item">
        <summary>Can I change my registered phone number or email?</summary>
        <p>Yes. Open your <a href="<?php echo base_url('pages/profile.php'); ?>">Profile</a> and click Settings. You can update your contact phone, display name, and avatar at any time.</p>
    </details>


    <h2 class="faq-section-title" id="cat-owners">For Court Owners and Managers</h2>

    <details class="faq-item">
        <summary>How do I list my futsal ground on GoalSpace?</summary>
        <p>Register an account with the <strong>Manager</strong> role, or click <a href="<?php echo base_url('pages/register.php?role=manager'); ?>">Become a Manager</a>. Once signed in, navigate to <strong>My Grounds</strong> in your dashboard and click <strong>Add Ground</strong> to set your court specifications, hourly rates, photos, and map location.</p>
    </details>

    <details class="faq-item">
        <summary>How do I receive digital payments from players?</summary>
        <p>Under <strong>My Grounds</strong>, click <strong>Edit</strong> on your court profile and open the Payment QR Code section. Upload a clear photo of your merchant QR code (eSewa, Khalti, IME Pay, or bank mobile banking). Players will scan your QR code directly during checkout.</p>
    </details>

    <details class="faq-item">
        <summary>How do I record payments made in cash at the counter?</summary>
        <p>In your manager Bookings screen, find the corresponding reservation and click <strong>Mark Paid</strong>. The booking status will instantly update to confirmed and paid on-site.</p>
    </details>

    <details class="faq-item">
        <summary>Can I block off slots for private tournaments or turf maintenance?</summary>
        <p>Yes. On the court edit page, access the <strong>Blocked Dates and Times</strong> tab. You can close off single hours, full days, or custom date ranges without taking your court offline.</p>
    </details>

    <details class="faq-item">
        <summary>How do promo discount codes work?</summary>
        <p>In your manager portal, open <strong>Promos</strong> to create custom discount coupons. You can configure percentage discounts or fixed deductions to attract teams and fill typically quiet daytime or weekday morning hours.</p>
    </details>


    <h2 class="faq-section-title" id="cat-tech">Technical Help and Support</h2>

    <details class="faq-item">
        <summary>How does the "Use my location" feature work?</summary>
        <p>When you enable location services, your browser calculates distances to nearby courts so they appear sorted closest to you first. Your location is processed only within your active browser session and is never stored on our servers or shared with any third party.</p>
    </details>

    <details class="faq-item">
        <summary>Can I install GoalSpace as an app on my phone?</summary>
        <p>Yes. GoalSpace is built as a progressive web application. On Android Chrome, tap the menu and select "Install app" or "Add to Home Screen". On iPhone Safari, tap the Share icon and select "Add to Home Screen".</p>
    </details>

    <details class="faq-item">
        <summary>Who do I contact if I need personalized assistance?</summary>
        <p>You can reach our dedicated support desk by emailing <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> or submitting an inquiry via our <a href="<?php echo base_url('pages/page.php?slug=contact'); ?>">Contact form</a>. We typically respond within 4 hours during normal business days.</p>
    </details>
</div>

<div class="faq-empty" id="faqEmpty" hidden>
    <p>No questions matched your search term "<span id="faqEmptyTerm"></span>". Try searching with different keywords.</p>
</div>

<?php if ($logged_in && $isManager): ?>
<div class="notice faq-help">
    <i class="fa-solid fa-circle-info"></i>
    <span>Managing a court? Check the <a href="<?php echo base_url('pages/page.php?slug=help#for-managers'); ?>">manager help section</a> for help with bookings, payments, and ground configuration.</span>
</div>
<?php endif; ?>

<script>
(function () {
    var input = document.getElementById('faqSearch');
    var empty = document.getElementById('faqEmpty');
    var termEl = document.getElementById('faqEmptyTerm');
    if (!input) return;
    var items = document.querySelectorAll('.faq-item');
    var filter = function () {
        var term = (input.value || '').trim().toLowerCase();
        var shown = 0;
        items.forEach(function (item) {
            var summary = item.querySelector('summary');
            var text = summary ? summary.textContent.toLowerCase() : '';
            var match = text.indexOf(term) !== -1;
            item.style.display = match ? '' : 'none';
            if (match) {
                shown++;
                if (term.length > 0) item.open = true;
            }
        });
        if (empty && termEl) {
            empty.hidden = shown !== 0;
            termEl.textContent = input.value.trim();
        }
    };
    input.addEventListener('input', filter);
    input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        filter();
        var first = Array.prototype.find.call(items, function (i) { return i.style.display !== 'none'; });
        if (first) {
            first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    items.forEach(function (item) {
        item.setAttribute('aria-expanded', item.open ? 'true' : 'false');
    });
})();
(function () {
    var items = document.querySelectorAll('.faq-item');
    var sync = function (item) {
        item.style.setProperty('--faq-chev', item.open ? '"\\f077"' : '"\\f078"');
        item.setAttribute('aria-expanded', item.open ? 'true' : 'false');
    };
    items.forEach(function (item) {
        sync(item);
        item.addEventListener('toggle', function () { sync(item); });
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
