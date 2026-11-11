<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
ini_set('display_errors', '0');

const CONTACT_RECIPIENT = 'neemachildrencentermombasa@gmail.com';
const SAFEGUARDING_RECIPIENT = 'neemachildrencentermombasa@gmail.com';
// Create this sender mailbox on the website domain in HostAfrica for reliable delivery.
const SENDER_ADDRESS = 'website@neemachildrenscentre.org';

function isAjaxRequest(): bool
{
    return isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}

function safeField(string $key, int $maxLength): ?string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return null;
    }

    $value = str_replace("\0", '', $value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = trim(strip_tags($value));
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

    if ($length > $maxLength) {
        return null;
    }

    return $value;
}

function sendResult(string $formType, bool $success, string $message, int $statusCode = 400): void
{
    $isAjax = isAjaxRequest();

    if ($success && !$isAjax) {
        $page = $formType === 'report' ? 'report.html?submission=sent#report-form' : 'contact.html?submission=sent#contact-form';
        header('Location: ' . $page, true, 303);
        exit;
    }

    http_response_code($success ? 200 : $statusCode);

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $title = $success ? 'Thank you' : 'Message not sent';
    $page = $formType === 'report' ? 'report.html#report-form' : 'contact.html#contact-form';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $urgentNote = $formType === 'report'
        ? '<p class="result-urgent">If a child is in immediate danger, contact emergency services in Kenya on 999 or 112.</p><p>You can also contact <a href="mailto:neemachildrencentermombasa@gmail.com">neemachildrencentermombasa@gmail.com</a>.</p>'
        : '<p>You can also email <a href="mailto:neemachildrencentermombasa@gmail.com">neemachildrencentermombasa@gmail.com</a>.</p>';

    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $title . ' | Neema Children\'s Centre</title><link rel="stylesheet" href="style.css"><style>.form-result{max-width:760px;margin:72px auto;padding:48px 28px;background:#fff;border-top:4px solid #ED1E79;box-shadow:0 10px 32px rgba(27,42,74,.08)}.form-result h1{margin:0 0 16px;color:#1B2A4A}.form-result p{line-height:1.7}.form-result .result-urgent{margin-top:20px;padding:14px 18px;border-left:4px solid #ED1E79;background:#FFF5F8}.form-result .btn{margin-top:18px}@media(max-width:600px){.form-result{margin:32px 16px;padding:32px 20px}}</style></head><body><header class="site-header"><div class="header-inner"><a href="index.html" class="brand"><img src="images/logo.png" alt="" class="brand-logo"><span class="brand-name">Neema Children\'s Centre</span></a></div></header><main class="form-result"><h1>' . $title . '</h1><p>' . $safeMessage . '</p>' . $urgentNote . '<a class="btn btn-primary" href="' . $page . '">Return to the form</a></main><footer class="site-footer"><div class="footer-bottom"><p>Neema Children\'s Centre · Mombasa, Kenya</p></div></footer></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$formType = isset($_POST['form_type']) && is_string($_POST['form_type']) ? $_POST['form_type'] : '';
if (!in_array($formType, ['contact', 'report'], true)) {
    sendResult('contact', false, 'This form could not be identified. Please return to the form and try again.');
}

// Quietly accept automated submissions caught by the hidden honeypot.
$website = safeField('website', 300);
if ($website === null || $website !== '') {
    sendResult($formType, true, 'Thank you. Your message has been received.');
}

if ($formType === 'contact') {
    $name = safeField('name', 120);
    $email = safeField('email', 254);
    $phone = safeField('phone', 80);
    $subject = safeField('subject', 40);
    $message = safeField('message', 6000);

    $subjects = [
        'general' => 'General enquiry',
        'donation' => 'Donation enquiry',
        'partnership' => 'Partnership',
        'volunteer' => 'Volunteering',
        'in-kind' => 'In-kind donation',
        'legacy' => 'Legacy or planned giving',
        'fundraise' => 'Fundraising',
        'visit' => 'Visit request',
        'media' => 'Media or press',
        'education' => 'Education programme',
        'health' => 'Health programme',
        'counselling' => 'Counselling programme',
        'mentorship' => 'Mentorship programme',
        'skills' => 'Skills development programme',
        'family' => 'Family strengthening programme',
        'reintegration' => 'Reintegration and aftercare programme',
        'safeguarding' => 'Safeguarding concern',
        'other' => 'Other'
    ];

    if ($name === null || $email === null || $phone === null || $subject === null || $message === null) {
        sendResult($formType, false, 'One or more fields are too long. Please shorten your message and try again.');
    }
    if ($name === '' || $email === '' || $message === '' || !isset($subjects[$subject]) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($_POST['consent'])) {
        sendResult($formType, false, 'Please complete the required fields, provide a valid email address, and agree to the privacy notice.');
    }

    $recipient = $subject === 'safeguarding' ? SAFEGUARDING_RECIPIENT : CONTACT_RECIPIENT;
    $emailSubject = $subject === 'safeguarding'
        ? '[Neema website] Safeguarding concern via contact form'
        : '[Neema website] Contact enquiry: ' . $subjects[$subject];
    $body = "A new website contact form message was submitted.\n\n"
        . 'Name: ' . $name . "\n"
        . 'Email: ' . $email . "\n"
        . 'Phone: ' . ($phone !== '' ? $phone : 'Not provided') . "\n"
        . 'Subject: ' . $subjects[$subject] . "\n\n"
        . "Message:\n" . $message . "\n";
    $replyTo = $email;
} else {
    $reporterName = safeField('reporter-name', 120);
    $reporterEmail = safeField('reporter-email', 254);
    $reporterPhone = safeField('reporter-phone', 80);
    $relationship = safeField('relationship', 40);
    $concernType = safeField('concern-type', 50);
    $childSafe = safeField('child-safe', 20);
    $incidentDate = safeField('incident-date', 200);
    $incidentLocation = safeField('incident-location', 300);
    $description = safeField('description', 12000);
    $otherInfo = safeField('other-info', 6000);
    $followUp = isset($_POST['concern-contact-consent']);

    $concernTypes = [
        'physical-abuse' => 'Physical abuse',
        'emotional-abuse' => 'Emotional abuse',
        'sexual-abuse' => 'Sexual abuse',
        'neglect' => 'Neglect',
        'inappropriate-conduct' => 'Inappropriate conduct by an adult',
        'policy-violation' => 'Safeguarding policy violation',
        'mental-health' => 'Mental health or wellbeing',
        'online-safety' => 'Online or digital safety',
        'other' => 'Other'
    ];
    $safeOptions = ['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Not sure'];
    $relationshipOptions = [
        '' => 'Not provided', 'community' => 'Community member', 'visitor' => 'Visitor',
        'volunteer' => 'Volunteer', 'staff' => 'Staff', 'partner' => 'Partner',
        'donor' => 'Donor', 'family' => 'Family member of a child', 'other' => 'Other',
        'prefer-not' => 'Prefer not to say'
    ];

    $fields = [$reporterName, $reporterEmail, $reporterPhone, $relationship, $concernType, $childSafe, $incidentDate, $incidentLocation, $description, $otherInfo];
    if (in_array(null, $fields, true)) {
        sendResult($formType, false, 'One or more fields are too long. Please shorten the details and try again.');
    }
    if ($concernType === '' || !isset($concernTypes[$concernType]) || !isset($safeOptions[$childSafe]) || $description === '' || !isset($_POST['concern-consent'])) {
        sendResult($formType, false, 'Please complete the required report details and confirm the confidentiality notice.');
    }
    if ($reporterEmail !== '' && !filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
        sendResult($formType, false, 'Please check the reporter email address or leave it blank to submit anonymously.');
    }
    if ($followUp && $reporterEmail === '' && $reporterPhone === '') {
        sendResult($formType, false, 'Please provide an email address or phone number if you would like the safeguarding lead to contact you.');
    }

    $emailSubject = '[NEEMA SAFEGUARDING] New concern report';
    $body = "A new confidential safeguarding report was submitted through the Neema website.\n\n"
        . 'Reporter name: ' . ($reporterName !== '' ? $reporterName : 'Anonymous') . "\n"
        . 'Reporter email: ' . ($reporterEmail !== '' ? $reporterEmail : 'Not provided') . "\n"
        . 'Reporter phone: ' . ($reporterPhone !== '' ? $reporterPhone : 'Not provided') . "\n"
        . 'Relationship to Neema: ' . ($relationshipOptions[$relationship] ?? 'Not provided') . "\n"
        . 'Concern type: ' . $concernTypes[$concernType] . "\n"
        . 'Child currently safe: ' . $safeOptions[$childSafe] . "\n"
        . 'When: ' . ($incidentDate !== '' ? $incidentDate : 'Not provided') . "\n"
        . 'Where: ' . ($incidentLocation !== '' ? $incidentLocation : 'Not provided') . "\n"
        . 'Follow-up consent: ' . ($followUp ? 'Yes' : 'No') . "\n\n"
        . "What happened or raised concern:\n" . $description . "\n\n"
        . "Additional information:\n" . ($otherInfo !== '' ? $otherInfo : 'None provided') . "\n";
    $replyTo = $followUp && $reporterEmail !== '' ? $reporterEmail : '';
    $recipient = SAFEGUARDING_RECIPIENT;
}

if (!function_exists('mail')) {
    sendResult($formType, false, 'The website email service is unavailable. Please use the contact details shown on this page. Contact the safeguarding lead directly for a child safety concern.', 503);
}

$headers = [
    'From: Neema Children\'s Centre <' . SENDER_ADDRESS . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8'
];
if ($replyTo !== '') {
    $headers[] = 'Reply-To: ' . $replyTo;
}
$encodedSubject = '=?UTF-8?B?' . base64_encode($emailSubject) . '?=';

if (!mail($recipient, $encodedSubject, $body, implode("\r\n", $headers))) {
    sendResult($formType, false, 'We could not send your message right now. Please try again later or use the contact details shown on this page.', 503);
}

sendResult($formType, true, 'Thank you. Your message has been sent.');