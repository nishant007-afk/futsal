<?php

define('DISPOSABLE_DOMAINS', array_map('strtolower', [
    '0-mail.com', '0wnd.net', '027168.com',
    '10minutemail.com', '10minutemail.net', '10minutemail.org', '10mail.org',
    '123mail.org', '1secmail.com', '1secmail.net', '1secmail.org',
    '2prong.com', '33mail.com',
    '4nrx.com', '5ymail.com',
    'a4trb.com', 'anonymbox.com', 'anonmails.de', 'antireg.com', 'antispam24.de',
    'armyspy.com', 'auti.st',
    'baxomale.ht.cx', 'binkmail.com', 'bobmail.info', 'bodhi.lawlita.com',
    'boun.cr', 'breakthru.com', 'brefmail.com', 'bumpymail.com',
    'casualdx.com', 'centermail.com', 'civxmail.com', 'click2mail.net',
    'courriel.fr.nf', 'courrieltemporaire.com', 'cox.net',
    'crankmails.com', 'cubiclink.com', 'curryworld.de', 'cust.in',
    'dandikmail.com', 'deadaddress.com', 'despam.it', 'dirtymail.co', 'dodgit.com',
    'dodgitt.com', 'doiea.com', 'dontreg.com', 'dontsendmespam.de',
    'dropmail.me', 'dump-email.com', 'dynu.net',
    'e4ward.com', 'email60.com', 'emaildienst.de', 'emailias.com', 'emailondeck.com',
    'emailtemporario.com.br', 'emailtempm.com', 'emz.net', 'envy17.com',
    'ezehe.com',
    'fakemail.net', 'fakeinbox.com', 'fammail.org', 'fawemail.com',
    'firstname@example.com',     'fizmail.com', 'fmail.com', 'fragolina2.tk',
    'garbagemail.org', 'grandmasmail.com', 'grr.la',
    'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org',
    'haltospam.com', 'harakirimail.com', 'hartbot.de', 'hidemail.pro',
    'hotlook.com', 'hstermail.com', 'hushmail.com',
    'icemails.net', 'ieatspam.eu', 'imails.info', 'inbax.tk', 'inbox.si',
    'inboxalias.com', 'incognitomail.com', 'ip6.li',
    'jetable.org', 'junk1e.com', 'junkmail.com',
    'kaspop.com', 'klassmaster.com', 'kobmail.com', 'kurzepost.de',
    'lags.us', 'lastmail.co', 'lazyinbox.com', 'letmeinon.se',
    'mail-temporaire.fr', 'mail.mezimages.net', 'mail4trash.com', 'mailcatch.com',
    'maileater.com', 'mailexpire.com', 'mailfa.tk', 'mailinator.com',
    'mailinator2.com', 'mailinatorz.com', 'mailin8r.com', 'mailmetrash.com',
    'mailnull.com', 'mailnxs.com', 'mailsac.com', 'mailshell.com',
    'mailsiphon.com', 'mailtemp.net', 'mamitainib.com', 'meinspamschutz.de',
    'meltmail.com', 'messagebeamer.de', 'mintemail.com', 'moburl.com',
    'moncourrier.fr.nf', 'monemail.fr.nf', 'mt2009.com', 'my-mail.net',
    'my10minutemail.com', 'mymailoasis.com', 'mytempemail.com',
    'nada.email', 'niceguy.com', 'nincsmail.com', 'nobulkmail.com',
    'nobuma.com', 'nomail.xl.cx', 'nospamfor.us', 'nowhere.org',
    'obobbo.com', 'oceanbase.online', 'offshore-proxies.net',
    'oneoffemail.com', 'one-time-mail.com', 'ovpn.to',
    'pancakemail.com', 'pastebitch.com', 'pizzajunkmail.com', 'pokemail.net',
    'poofy.org', 'privacy.net', 'proxymail.eu',
    'qipmail.net',
    'r8r4p0nb.com', 'rcpt.at', 'recode.me', 'rejectmail.com',
    'rhyta.com', 'safetymail.info', 'savetime.net', 'saynotospams.com',
    'sharklasers.com', 'sibmail.com', 'slaskpost.se', 'slipperybrick.com',
    'smakkeenip.com', 'sneakemail.com', 'sofort-mail.de', 'spam4.me',
    'spamfree24.info', 'spamgourmet.com', 'spamhole.com', 'spamthis.co.uk',
    'spambox.us', 'spamcero.com', 'spamday.com', 'spameater.com',
    'spamex.com', 'spamfree24.de', 'spamherelots.com', 'spamjet.org',
    'spam.la', 'spamserver.de', 'spamstack.net', 'spamthisplease.com',
    'speed.1s.fr', 'ssoia.com', 'startkeys.com', 'stuffmail.de',
    'superrito.com', 'suremail.info',
    'temporaryinbox.com', 'temp-mail.com', 'temp-mail.org', 'temp-mail.de',
    'tempinbox.co', 'tempmail.address', 'tempmailer.com', 'tempomail.fr',
    'thanksnospam.info', 'throwaway.email', 'throwawaymail.com', 'tmail.ws',
    'tmailinator.com', 'trash2009.com', 'trash-2009.com', 'trash-mail.com',
    'trashmail.com', 'trashmail.me', 'trashymail.com', 'trialmail.de',
    'tropicalbreeze.info', 'twinmail.de',
    'ureach.com',
    'veryrealemail.com', 'vgfhdh.com', 'vpn.st', 'vidchart.com',
    'webemail.me', 'wegwerfmail.de', 'wegwerfmail.net', 'wegwerfmail.org',
    'wh4f.org', 'whyspam.me', 'willselfdestruct.com', 'winemaven.info',
    'wuzup.net',
    'xemaps.com', 'xents.com', 'xmailer.be',
    'yopmail.com', 'yopmail.fr', 'yopmail.net', 'yopmail.org',
    'zep-hyr.com', 'zippymail.info', 'zoaxe.com', 'zoemail.org',
    'tempmail.ninja', 'guerrillamailblock.com', 'inboxkitten.com', 'dispostable.com',
    'burnermail.io', 'getairmail.com', 'mohmal.com', 'crazymailing.com',
    'nada.ltd', 'getnada.com', 'abyssmail.com', 'generator.email',
    'generator-mail.com', 'emailfake.com', 'fakemailgenerator.com', 'disposablemail.com',
    'temp-mail.io', 'inboxes.com', 'tmpmail.org', 'tmpmail.net',
    'internxt.com', 'luxusmail.org', 'mail.tm', 'mail.gw',
    'mohmal.im', 'mohmal.in', 'email-fake.com', 'trashmail.net',
    'mytemp.email', 'tempinbox.com', '10minemail.com', 'minutemailbox.com',
    'guerrillamail.biz', 'guerrillamail.info', 'bccto.me', 'chacuo.net',
    'disposable.com', 'maildrop.cc', 'harakirimail.com', 'tempr.email',
    'discard.email', 'discardmail.com', 'spambog.com', 'trashmail.de'
]));

function is_disposable_email(string $email): bool
{
    $pos = strpos($email, '@');
    if ($pos === false) {
        return false;
    }
    $domain = strtolower(trim(substr($email, $pos + 1)));
    if (in_array($domain, DISPOSABLE_DOMAINS, true)) {
        return true;
    }
    $parts = explode('.', $domain);
    if (count($parts) > 2) {
        $base = implode('.', array_slice($parts, -2));
        if (in_array($base, DISPOSABLE_DOMAINS, true)) {
            return true;
        }
    }
    return false;
}

function email_has_plus_alias(string $email): bool
{
    $pos = strpos($email, '@');
    if ($pos === false) {
        return false;
    }
    $local = substr($email, 0, $pos);
    return strpos($local, '+') !== false;
}

function canonical_email(string $email): string
{
    $email = strtolower(trim($email));
    $pos = strpos($email, '@');
    if ($pos === false) {
        return $email;
    }
    $local = substr($email, 0, $pos);
    $domain = substr($email, $pos + 1);

    if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
        $domain = 'gmail.com';
        $local = str_replace('.', '', $local);
        $plusPos = strpos($local, '+');
        if ($plusPos !== false) {
            $local = substr($local, 0, $plusPos);
        }
    } else {
        $plusPos = strpos($local, '+');
        if ($plusPos !== false) {
            $local = substr($local, 0, $plusPos);
        }
    }
    return $local . '@' . $domain;
}

function email_has_mx(string $email): bool
{
    $pos = strpos($email, '@');
    if ($pos === false) {
        return false;
    }
    $domain = strtolower(trim(substr($email, $pos + 1)));
    $records = @dns_get_record($domain, DNS_MX);
    if (!$records) {
        return false;
    }
    foreach ($records as $r) {
        if (isset($r['target']) && $r['target'] !== '' && $r['target'] !== '.') {
            return true;
        }
    }
    return false;
}
