<?php

/**
 * Static fallback definitions for legal / static pages.
 * The live editor (admin/pages.php) writes into the `pages` table;
 * page_content() merges DB rows over these defaults, so the site
 * keeps working even if the table has not been created yet.
 */
function legal_pages_defaults(): array
{
    return [
        'about'   => [
            'title'   => 'About GoalSpace',
            'summary' => 'Connecting passionate futsal players with verified courts across Nepal.',
            'body'    => '
            <p><strong>GoalSpace</strong> is Nepal\'s dedicated futsal discovery and court reservation platform. We make booking a court as quick and effortless as scoring a tap-in, connecting players directly with venue managers in real time.</p>
            
            <p>Before GoalSpace, organizing a friendly match meant making multiple phone calls, checking availability through busy signals, and hoping your court slot was actually held when you arrived. We built GoalSpace to replace guesswork with clarity: see open slots live, lock in your game instantly, and hit the turf with confidence.</p>

            <h2>What We Believe In</h2>
            <ul>
                <li><strong>No more double bookings.</strong> Every confirmed reservation is locked in our database instantly. There are no verbal holds or lost time slots.</li>
                <li><strong>Full transparency.</strong> Clear pricing, court dimensions, surface details, parking info, changing rooms, and customer reviews are openly displayed for every ground.</li>
                <li><strong>Empowering venue managers.</strong> Court owners get dedicated dashboard tools to automate reservations, verify digital QR payments, manage custom rates, and run off-peak promotions.</li>
            </ul>

            <h2>Who Uses GoalSpace</h2>
            <ul>
                <li><strong>Players and Teams:</strong> Explore local courts by location or amenities, check live free slots, reserve in seconds, and track match histories.</li>
                <li><strong>Court Managers:</strong> Streamline front-desk operations, replace paper registers, accept cashless payments, and fill off-peak hours with automated promos.</li>
                <li><strong>Tournament Organizers:</strong> Discover verified venues with multi-court capacity, floodlights, and spectator seating.</li>
            </ul>

            <h2>Get in Touch</h2>
            <p>Have ideas to make GoalSpace better, or want to partner with us? Our Kathmandu-based team is always here to listen. Email us anytime at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> or visit our <a href="' . base_url('pages/page.php?slug=contact') . '">Contact page</a>.</p>
        ',
        ],
        'privacy' => [
            'title'   => 'Privacy Policy',
            'summary' => 'How we collect, protect, and handle your data with complete transparency.',
            'body'    => '
            <p class="updated-note">Last updated: September 2026</p>

            <p>At GoalSpace, your trust is fundamental to our service. This Privacy Policy explains what personal information we collect, why we collect it, how it is secured, and your control over your data.</p>

            <h2>1. What We Collect</h2>
            <ul>
                <li><strong>Account Information:</strong> When you register, we collect your full name, email address, contact phone number, and account password (stored securely as a one-way cryptographic hash).</li>
                <li><strong>Court Reservation Data:</strong> We store details of your futsal bookings, including chosen court, date, time slot, payment status (e.g. Paid, Advance, or Venue Pay), and cancellation history.</li>
                <li><strong>Technical and Device Logs:</strong> Basic server logs such as browser type, operating system, and IP address are maintained temporarily for security monitoring, DDoS prevention, and crash diagnostics.</li>
            </ul>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To create and authenticate your account across web and mobile devices.</li>
                <li>To instantly confirm, schedule, and maintain court reservations.</li>
                <li>To send crucial transactional notifications, such as login OTP codes, booking receipts, and schedule changes.</li>
                <li>To provide venue managers with necessary player contact details so they can welcome your team at the court.</li>
                <li>To maintain system integrity, detect fraudulent actions, and prevent unauthorized account access.</li>
            </ul>

            <h2>3. Information Sharing and Disclosure</h2>
            <p>GoalSpace does not sell, rent, or trade your personal information to third-party advertisers or data brokers. We disclose your data only in the following limited circumstances:</p>
            <ul>
                <li><strong>Court Managers:</strong> When you make a booking, the manager of that specific futsal venue receives your name and contact phone number to coordinate entry, pitch access, and ball allocation.</li>
                <li><strong>Infrastructure Service Providers:</strong> Trusted technical partners who assist with email delivery (such as Brevo/SMTP) and secure database hosting, governed by strict confidentiality terms.</li>
                <li><strong>Legal Requirements:</strong> If compelled by applicable law, court order, or governmental regulation in Nepal.</li>
            </ul>

            <h2>4. Data Security Standards</h2>
            <p>We implement comprehensive security measures to safeguard your personal data:</p>
            <ul>
                <li>Passwords are hashed with industry-standard bcrypt algorithms with salted rounds. Plaintext passwords are never accessible to any staff member.</li>
                <li>All network communications are transmitted over secure HTTPS with TLS encryption.</li>
                <li>Role-based access restrictions guarantee that players, venue managers, and platform administrators can only access authorized system records.</li>
            </ul>

            <h2>5. Your Privacy Rights</h2>
            <p>You have full autonomy over your personal information on GoalSpace:</p>
            <ul>
                <li><strong>Access and Correction:</strong> You can view and edit your profile name, contact phone, and avatar at any time in your account settings.</li>
                <li><strong>Data Portability and Deletion:</strong> You can request a copy of your booking history or permanently delete your account through your profile settings or by emailing <a href="mailto:hello@goalspace.com">hello@goalspace.com</a>.</li>
            </ul>

            <h2>6. Cookies and Session Storage</h2>
            <p>We use essential session cookies and local storage to keep you authenticated, remember your theme preference (dark or light mode), and retain active navigation state. We do not use third-party tracking cookies.</p>

            <h2>7. Contact Our Privacy Team</h2>
            <p>If you have questions or concerns regarding our privacy practices, please contact us at <a href="mailto:privacy@goalspace.com">privacy@goalspace.com</a>.</p>
        ',
        ],
        'terms'  => [
            'title'   => 'Terms of Service',
            'summary' => 'Clear terms governing the use of GoalSpace for players, managers, and visitors.',
            'body'    => '
            <p class="updated-note">Last updated: September 2026</p>

            <p>Welcome to GoalSpace. By accessing our platform, creating an account, or making a court booking, you agree to these Terms of Service. Please review them carefully.</p>

            <h2>1. User Accounts and Eligibility</h2>
            <ul>
                <li>You must be at least 16 years of age or have parent/guardian consent to create an account.</li>
                <li>You agree to provide accurate, up-to-date registration information and keep your credentials confidential.</li>
                <li>You are responsible for all activities and bookings made under your account credentials.</li>
                <li>GoalSpace reserves the right to suspend or terminate accounts that provide falsified details or misuse the reservation system.</li>
            </ul>

            <h2>2. Court Reservations and Instant Confirmation</h2>
            <ul>
                <li><strong>Instant Booking:</strong> When you select a time slot and complete checkout, your reservation is confirmed immediately. The court calendar updates in real time to prevent duplicate bookings.</li>
                <li><strong>Punctuality:</strong> Players are expected to arrive at the venue at least 10 minutes prior to their reserved kickoff time. Game time ends precisely when the booked slot concludes.</li>
                <li><strong>Venue Rules:</strong> Players agree to abide by the specific ground rules of the futsal facility, including proper turf footwear, equipment care, and courteous sportsmanship.</li>
            </ul>

            <h2>3. Pricing, Payments, and Advance Deposits</h2>
            <ul>
                <li><strong>Clear Rates:</strong> Court prices are established directly by venue managers and clearly displayed per 60-minute or 90-minute time slot.</li>
                <li><strong>Payment Methods:</strong> Depending on the court\'s configuration, players may pay the full amount online, pay a 20% advance online with the balance due upon arrival, or pay the entire fee at the venue.</li>
                <li><strong>Direct QR Transfers:</strong> Digital QR payments (e.g. Khalti, eSewa, IME Pay) are transferred directly to the venue manager\'s verified merchant account.</li>
            </ul>

            <h2>4. Cancellation and Refund Policy</h2>
            <ul>
                <li><strong>Standard Notice (24+ hours before kickoff):</strong> Cancellations submitted at least 24 hours prior to game start qualify for a 100% refund or platform credit.</li>
                <li><strong>Late Cancellation (within 24 hours):</strong> For cancellations made less than 24 hours before kickoff, the advance deposit is retained as credit for future bookings or paid to the court to cover idle turf loss.</li>
                <li><strong>No-Shows:</strong> Failing to attend a reserved slot without cancellation forfeits the advance deposit. Continued no-shows may lead to booking restrictions on your account.</li>
            </ul>

            <h2>5. Manager and Court Owner Responsibilities</h2>
            <p>Futsal court operators who register as Managers on GoalSpace agree to uphold the following standards:</p>
            <ul>
                <li><strong>Listing Accuracy:</strong> Court dimensions, amenities, grass type, rates, and working hours must remain truthful and up to date.</li>
                <li><strong>Guaranteed Availability:</strong> A slot confirmed on GoalSpace must be honored. Double-selling slots across phone or third-party platforms is strictly prohibited.</li>
                <li><strong>Player Privacy:</strong> Customer contact details may be used solely for reservation coordination and never for unsolicited commercial messaging.</li>
            </ul>

            <h2>6. Platform Availability and Liability</h2>
            <p>GoalSpace provides the digital booking infrastructure connecting players and courts. Physical venue conditions, weather disruptions, pitch maintenance, and player conduct remain the direct responsibility of the respective venue managers and participants. To the maximum extent permitted by law, GoalSpace is not liable for injuries or property loss occurring at partner futsal facilities.</p>

            <h2>7. Amendments to Terms</h2>
            <p>We may update these terms periodically to reflect new platform capabilities or legal guidelines. Continued use of GoalSpace following published updates constitutes acceptance of the modified terms.</p>
        ',
        ],
        'contact' => [
            'title'   => 'Contact Us',
            'summary' => 'Get in touch with the GoalSpace team for support, court onboarding, and inquiries.',
            'body'    => '
            <p>Whether you need assistance with a current booking, want to register your futsal court on our platform, or simply have feedback to share, we are here to help.</p>

            <h2>Support Channels</h2>
            <ul>
                <li><strong>Email Support:</strong> <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> (typically answered within 4 hours during business days)</li>
                <li><strong>Manager Venue Onboarding:</strong> <a href="mailto:partners@goalspace.com">partners@goalspace.com</a></li>
                <li><strong>Headquarters:</strong> GoalSpace Technologies, Kathmandu, Nepal</li>
                <li><strong>Operating Hours:</strong> Sunday through Friday, 8:00 AM to 8:00 PM NPT</li>
            </ul>

            <p>You can also send a direct inquiry using the contact form below, and our team will get back to you promptly.</p>
        ',
        ],
        'help'    => [
            'title'   => 'Help and Support',
            'summary' => 'Comprehensive answers and tutorials for players, court managers, and administrators.',
            'body'    => '
            <h2 id="for-players">Player Guide</h2>
            <h3>How do I find and book an open futsal court?</h3>
            <p>Browse courts on the <a href="' . base_url('pages/courts.php') . '">Courts page</a> or search by city and neighborhood. Tap any court to view available dates, then click on your preferred open time slot to begin checkout.</p>

            <h3>How does payment work?</h3>
            <p>GoalSpace supports flexible payment options. You can pay a 20% advance online and settle the balance when you arrive, pay 100% upfront via the court\'s official QR code (Khalti, eSewa, or IME Pay), or pay directly at the venue counter.</p>

            <h3>Can I reschedule or cancel my match?</h3>
            <p>Yes. Go to <a href="' . base_url('pages/my_bookings.php') . '">My Bookings</a>, choose your upcoming game, and tap Cancel or Reschedule. Cancellations made 24 hours or more before kickoff qualify for a full refund.</p>

            <h2 id="for-managers">Court Manager Guide</h2>
            <h3>How do I list my futsal ground on GoalSpace?</h3>
            <p>Sign up and select the <strong>Manager</strong> account role, or visit <a href="%MANAGER_URL%">Become a Manager</a>. From your manager dashboard, navigate to "My Grounds" and click "Add Ground" to set your rates, photos, and ground specifications.</p>

            <h3>How do I configure digital QR payments?</h3>
            <p>In "My Grounds", click "Edit" on your court and locate the Payment QR Code section. Upload a clear photo or screenshot of your Khalti, eSewa, or mobile banking QR code so players can scan and transfer fees directly to you.</p>

            <h3>How do I mark payments collected in cash?</h3>
            <p>In your manager Bookings tab, locate the player\'s reservation and click "Mark Paid". The booking status will immediately update to reflect full settlement.</p>

            <h3>Can I block courts for tournaments or private maintenance?</h3>
            <p>Yes. Under court settings, open "Blocked Dates and Times" to temporarily close specific slots from public availability without taking down your entire court profile.</p>

            <h2 id="general-questions">General Questions</h2>
            <h3>What should I do if a venue is closed upon arrival?</h3>
            <p>Please contact our support team immediately at <a href="mailto:hello@goalspace.com">hello@goalspace.com</a> with your booking reference code. We will verify the incident with the manager and promptly issue a full refund or credit.</p>
        ',
        ],
    ];
}

/**
 * Resolve a legal/static page.
 * Falls back to the static defaults in legal_pages_defaults()
 * when the `pages` table is missing or the slug is not present.
 */
function page_content(string $slug): ?array
{
    global $conn;
    $defaults = legal_pages_defaults();
    $base = isset($defaults[$slug]) ? $defaults[$slug] : null;
    if ($base === null) {
        return null;
    }
    // Try the live DB first (table may not exist on a fresh install).
    try {
        $stmt = $conn->prepare('SELECT title, summary, body, updated_at FROM pages WHERE slug = ?');
        if ($stmt) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $stmt->bind_result($title, $summary, $body, $updated_at);
            if ($stmt->fetch()) {
                $stmt->close();
                return [
                    'title'       => (string)$title,
                    'summary'     => (string)$summary,
                    'body'        => (string)$body,
                    'updated_at'  => $updated_at ? (string)$updated_at : '',
                ];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // table missing or other DB issue: keep the static fallback
    }
    return $base;
}

/**
 * Persist an edited legal/static page. Inserts or updates the `pages` row.
 * Body HTML is sanitized via allowlist (vanilla, no framework).
 * @return bool true on success
 */
function sanitize_page_body(string $html): string
{
    // Allow only safe formatting tags; strip scripts, iframes, objects, forms, event handlers.
    $allowed = '<p><br><h2><h3><h4><ul><ol><li><strong><em><b><i><u><a><blockquote><code><pre><hr>';
    $html = strip_tags($html, $allowed);
    // Remove event-handler attributes (onclick= etc.)
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // Remove style attributes that could hide content or exfiltrate.
    $html = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    // For <a> tags: validate href is a safe protocol (http, https, mailto, #)
    // This defeats encoded javascript: URIs (unicode, percent-encoding, newlines, etc.)
    $html = preg_replace_callback('/<a\s[^>]*href\s*=\s*(["\'])(.*?)\1/i', function ($m) {
        $val = rawurldecode(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
        // Strip whitespace/control chars that could bypass protocol check
        $clean = preg_replace('/[\s\x00-\x1f\x7f]+/', '', $val);
        if (!preg_match('~^(https?://|mailto:|#)~i', $clean)) {
            return str_replace($m[0], '<a href="#">', $m[0]);
        }
        return $m[0];
    }, $html);
    return trim($html);
}

function save_page(string $slug, string $title, string $summary, string $body): bool
{
    global $conn;
    $defaults = legal_pages_defaults();
    if (!isset($defaults[$slug])) {
        return false;
    }
    $title = mb_substr(trim($title), 0, 150);
    $summary = mb_substr(trim($summary), 0, 255);
    $body = sanitize_page_body($body);
    $stmt = $conn->prepare(
        'INSERT INTO pages (slug, title, summary, body) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), body = VALUES(body), updated_at = CURRENT_TIMESTAMP'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssss', $slug, $title, $summary, $body);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function render_json_ld(array $data): string
{
    return '<script type="application/ld+json">' . "\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . "\n" . '</script>';
}

function ground_seo_meta(array $ground): void
{
    global $page_title, $page_description, $page_image, $page_url, $og_type;
    $title = $ground['name'];
    $loc  = isset($ground['address']) && $ground['address'] !== ''
        ? $ground['address'] : (isset($ground['city']) && $ground['city'] !== '' ? $ground['city'] : 'GoalSpace');
    $page_title       = $title . ' - Book futsal court';
    $page_description = 'Book the ' . $ground['name'] . ' futsal court'
        . ($loc !== 'GoalSpace' ? ' in ' . $loc : '')
        . '. Check real-time availability, prices, and pay securely with GoalSpace.';
    $img = ground_cover($ground['id']);
    $page_image = $img ? absolute_url($img) : absolute_url('assets/img/icon-512.png');
    $page_url     = absolute_url('pages/ground.php?id=' . (int) $ground['id'] . (isset($ground['slug']) && $ground['slug'] !== '' ? '&slug=' . $ground['slug'] : ''));
    $og_type      = 'article';
}

function ground_detail_url(int $ground_id): string
{
    $slug = ground_slug($ground_id);
    if ($slug !== '') {
        return base_url('pages/ground.php?id=' . $ground_id . '&slug=' . $slug);
    }
    return base_url('pages/ground.php?id=' . $ground_id);
}

function ground_slug(int $ground_id): string
{
    global $conn;
    $slug = '';
    $stmt = $conn->prepare('SELECT slug FROM grounds WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $ground_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $slug = (string) ($row['slug'] ?? '');
        }
        $stmt->close();
    }
    return $slug;
}

function ground_json_ld(array $ground): string
{
    $rating = ground_rating($ground['id']);
    $img    = ground_cover($ground['id']);
    $price  = isset($ground['price']) ? (float) $ground['price'] : 0.0;
    $data   = [
        '@context' => 'https://schema.org',
        '@type'    => 'SportsActivityLocation',
        'name'     => $ground['name'],
        'image'    => $img ? [absolute_url($img)] : [absolute_url('assets/img/icon-512.png')],
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $ground['address'] ?? '',
            'addressLocality' => $ground['city'] ?? '',
            'addressRegion'   => $ground['state'] ?? '',
            'postalCode'      => $ground['zip'] ?? '',
        ],
        'geo' => [
            '@type'      => 'GeoCoordinates',
            'latitude'   => $ground['lat'] ?? null,
            'longitude'  => $ground['lng'] ?? null,
        ],
        'priceRange'  => $price > 0 ? '$' . number_format($price, 2) : 'Ask',
        'sport'       => 'Futsal',
        'aggregateRating' => [
            '@type'         => 'AggregateRating',
            'ratingValue'   => $rating['avg'] ?? null,
            'reviewCount'   => $rating['count'] ?? 0,
            'bestRating'    => 5,
            'worstRating'   => 1,
        ],
    ];
    return render_json_ld($data);
}

/**
 * True when the court is the dedicated demo/practice court.
 */
function is_demo_ground(array $ground): bool
{
    return isset($ground['slug']) && $ground['slug'] === 'demo-court';
}

/**
 * Human-friendly owner label. The demo court is presented as GoalSpace itself.
 */
function ground_owner_label(array $ground): string
{
    if (is_demo_ground($ground)) {
        return 'GoalSpace';
    }
    return (string) ($ground['owner_name'] ?? '');
}
