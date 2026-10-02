<?php

require __DIR__ . '/config.php';

$data = get_landing_page_data();
$siteName = $data['branding']['site_name'] ?? 'Zoom POS & Market';
$tagline = $data['branding']['site_tagline'] ?? 'Smarter Business. Greater Control.';
$supportEmail = $data['branding']['support_email'] ?? 'support@zoomnearby.com';
$urls = $data['urls'] ?? [];
$demoUrl = $urls['demo_admin'] ?? 'https://saas.zoomnearby.com/login';
$currencySym = $data['branding']['currency_symbol'] ?? '$';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Return &amp; Refund Policy — <?= e_attr($siteName) ?></title>
  <meta name="description" content="Return and Refund Policy for <?= e_attr($siteName) ?>. Understand our digital product nature, license terms, and strict no-return policy.">
  <link rel="stylesheet" href="<?= marketing_asset('css/marketing.css') ?>">
  <style>
    .policy-page {
      background: var(--slate-50, #f8fafc);
      color: var(--text-dark, #0f172a);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }
    .policy-hero {
      background: linear-gradient(135deg, #090e23 0%, #151d42 100%);
      color: #ffffff;
      padding: 60px 0 45px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .policy-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .08em;
      text-transform: uppercase;
      background: rgba(99, 102, 241, 0.2);
      border: 1px solid rgba(129, 140, 248, 0.35);
      color: #c7d2fe;
      padding: 4px 12px;
      border-radius: 9999px;
      margin-bottom: 16px;
    }
    .policy-title {
      font-size: 32px;
      font-weight: 900;
      line-height: 1.25;
      margin-bottom: 12px;
      letter-spacing: -0.02em;
    }
    .policy-subtitle {
      font-size: 15px;
      color: #94a3b8;
      max-width: 680px;
      line-height: 1.6;
    }
    .policy-meta {
      margin-top: 18px;
      font-size: 12px;
      color: #64748b;
      display: flex;
      gap: 18px;
      flex-wrap: wrap;
    }
    .policy-meta span {
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .policy-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 40px;
      padding: 50px 0 80px;
      align-items: start;
    }
    @media (max-width: 900px) {
      .policy-layout {
        grid-template-columns: 1fr;
        gap: 30px;
      }
      .policy-toc {
        position: static !important;
      }
    }
    .policy-toc {
      position: sticky;
      top: 100px;
      background: #ffffff;
      border: 1px solid var(--slate-200, #e2e8f0);
      border-radius: 16px;
      padding: 22px;
      box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
    }
    .toc-title {
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--slate-500, #64748b);
      margin-bottom: 14px;
    }
    .toc-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .toc-list li {
      margin-bottom: 10px;
    }
    .toc-list a {
      font-size: 13px;
      font-weight: 600;
      color: var(--slate-600, #475569);
      text-decoration: none;
      transition: color .15s ease;
      display: block;
      line-height: 1.4;
    }
    .toc-list a:hover {
      color: var(--primary-purple, #6366f1);
    }
    .policy-content {
      background: #ffffff;
      border: 1px solid var(--slate-200, #e2e8f0);
      border-radius: 20px;
      padding: 40px 44px;
      box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04);
    }
    @media (max-width: 600px) {
      .policy-content {
        padding: 26px 20px;
      }
    }
    .alert-box-warning {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-left: 5px solid #f59e0b;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 32px;
      color: #92400e;
    }
    .alert-box-warning h4 {
      font-size: 15px;
      font-weight: 800;
      margin-bottom: 6px;
      color: #78350f;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .alert-box-warning p {
      font-size: 13.5px;
      line-height: 1.55;
      margin: 0;
      color: #92400e;
    }
    .policy-section {
      margin-bottom: 40px;
      padding-bottom: 36px;
      border-bottom: 1px solid var(--slate-100, #f1f5f9);
    }
    .policy-section:last-child {
      margin-bottom: 0;
      padding-bottom: 0;
      border-bottom: none;
    }
    .policy-section h2 {
      font-size: 20px;
      font-weight: 800;
      color: var(--slate-900, #0f172a);
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .policy-section h2 .sec-num {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      border-radius: 8px;
      background: rgba(99, 102, 241, 0.1);
      color: var(--primary-purple, #6366f1);
      font-size: 13px;
      font-weight: 800;
      flex-shrink: 0;
    }
    .policy-section p {
      font-size: 14px;
      line-height: 1.7;
      color: var(--slate-600, #475569);
      margin-bottom: 14px;
    }
    .policy-section ul {
      margin: 0 0 16px 20px;
      padding: 0;
      color: var(--slate-600, #475569);
      font-size: 14px;
      line-height: 1.65;
    }
    .policy-section ul li {
      margin-bottom: 8px;
    }
    .policy-highlight-box {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 18px 22px;
      margin: 18px 0;
    }
    .policy-highlight-box strong {
      color: #0f172a;
    }
    .contact-card {
      background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
      color: #ffffff;
      border-radius: 14px;
      padding: 24px;
      margin-top: 30px;
    }
    .contact-card h3 {
      font-size: 16px;
      font-weight: 800;
      margin-bottom: 8px;
    }
    .contact-card p {
      color: #cbd5e1;
      font-size: 13.5px;
      line-height: 1.5;
      margin-bottom: 16px;
    }
    .contact-btn {
      display: inline-block;
      background: #ffffff;
      color: #312e81;
      font-weight: 700;
      font-size: 13px;
      padding: 9px 18px;
      border-radius: 8px;
      text-decoration: none;
      transition: opacity .15s ease;
    }
    .contact-btn:hover {
      opacity: 0.95;
    }
  </style>
</head>
<body class="policy-page">

  <!-- Header -->
  <header class="header">
    <div class="container header-container">
      <a href="index.php" class="brand">
        <span class="brand-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
          </svg>
        </span>
        <span class="brand-name">Zoom POS <span class="brand-sub">&amp; Market</span></span>
      </a>

      <div class="nav-actions">
        <a href="index.php" class="btn btn-nav-demo">Home</a>
        <a href="index.php#pricing" class="btn btn-nav-buy">Pricing Plans</a>
      </div>
    </div>
  </header>

  <!-- Hero Banner -->
  <section class="policy-hero">
    <div class="container">
      <span class="policy-badge">Legal &amp; Purchase Agreement</span>
      <h1 class="policy-title">Return &amp; Refund Policy</h1>
      <p class="policy-subtitle">
        Please read this policy carefully before completing your purchase. Because our software consists of digital source code and instant cryptographic license keys, special terms apply to all transactions.
      </p>
      <div class="policy-meta">
        <span>📅 Last Updated: September 2026</span>
        <span>🛡️ Applicable to: All Digital Products &amp; Bundles</span>
        <span>⚖️ Jurisdiction: Standard Commercial Software Terms</span>
      </div>
    </div>
  </section>

  <!-- Main Content Layout -->
  <main class="container">
    <div class="policy-layout">

      <!-- Sticky Table of Contents -->
      <aside class="policy-toc">
        <div class="toc-title">Policy Table of Contents</div>
        <ul class="toc-list">
          <li><a href="#digital-nature">1. Digital Product Nature</a></li>
          <li><a href="#no-return-policy">2. Strict No-Return / No-Refund Policy</a></li>
          <li><a href="#pre-purchase-testing">3. Try Before You Buy (Live Demos)</a></li>
          <li><a href="#delivery-scope">4. Scope of Delivery &amp; Self-Hosting</a></li>
          <li><a href="#exceptional-cases">5. Limited Exceptional Scenarios</a></li>
          <li><a href="#chargebacks">6. Chargebacks &amp; License Revocation</a></li>
          <li><a href="#support-assistance">7. Technical Support &amp; Contact</a></li>
        </ul>
      </aside>

      <!-- Document Content -->
      <article class="policy-content">

        <!-- Prominent Alert Box -->
        <div class="alert-box-warning">
          <h4>⚠️ Important Notice: Digital Software Products Are Non-Returnable</h4>
          <p>
            Due to the irrevocable digital nature of software, unencrypted full source code (Laravel PHP &amp; Flutter), database schemas, and immediately generated cryptographic license keys, <strong>all sales are strictly final and non-refundable once an order is placed and digital credentials have been dispatched</strong>.
          </p>
        </div>

        <!-- Section 1 -->
        <section class="policy-section" id="digital-nature">
          <h2><span class="sec-num">1</span> Digital Product Nature &amp; Scope</h2>
          <p>
            This Return and Refund Policy applies to all purchases of software products, core script licenses, vertical module add-ons, and bundles sold through <strong><?= e_attr($siteName) ?></strong> (hereinafter referred to as "the Platform", "we", "us", or "our").
          </p>
          <p>
            Our software catalog consists entirely of <strong>non-tangible, irrevocable digital goods</strong>. Upon successful payment verification, you receive:
          </p>
          <ul>
            <li><strong>Cryptographic License Keys:</strong> Unique, tamper-proof license keys registered and bound to your designated domain/subdomain on our Central License Server.</li>
            <li><strong>Full Unencrypted Source Code:</strong> Complete Laravel/PHP backend web source code and Flutter multi-platform application source code.</li>
            <li><strong>Database Assets:</strong> Full MySQL relational database schemas, migration files, and initial seeding datasets.</li>
            <li><strong>Developer Documentation:</strong> Comprehensive technical setup guides and compilation walkthroughs.</li>
          </ul>
        </section>

        <!-- Section 2 -->
        <section class="policy-section" id="no-return-policy">
          <h2><span class="sec-num">2</span> Strict No-Return &amp; No-Refund Policy</h2>
          <p>
            Because digital software files and license keys can be immediately copied, duplicated, stored locally, and deployed to private servers without possibility of physical recovery or proof of uninstallation, <strong>we enforce a strict NO-RETURN and NO-REFUND policy on all software purchases</strong>.
          </p>
          <div class="policy-highlight-box">
            <p style="margin:0;font-size:13.5px;color:#334155;line-height:1.6;">
              <strong>Once a purchase is finalized:</strong> Your license key is issued automatically and emailed to your registered address. Since digital source code and cryptographic keys cannot be physically "returned" or permanently erased from a customer's private possession, <strong>no refunds, cancellations, or exchanges will be granted</strong> under any circumstances once the digital credentials have been delivered.
            </p>
          </div>
          <p>
            By proceeding with checkout and completing payment, you explicitly acknowledge, agree, and consent that:
          </p>
          <ul>
            <li>You have reviewed the product specifications, requirements, and live interactive demonstrations.</li>
            <li>You waive any statutory cooling-off or withdrawal periods that might otherwise apply to physical retail merchandise.</li>
            <li>You agree that the software is delivered immediately upon payment completion and is therefore non-refundable.</li>
          </ul>
        </section>

        <!-- Section 3 -->
        <section class="policy-section" id="pre-purchase-testing">
          <h2><span class="sec-num">3</span> "Try Before You Buy" — Interactive Live Demos</h2>
          <p>
            To ensure complete confidence in your purchase decision, we provide open, fully functioning, public live demonstrations of our complete software suite prior to purchase:
          </p>
          <ul>
            <li><strong>SuperAdmin SaaS Portal Demo:</strong> Test the central SaaS administrative controls, subscription packages, and tenant management.</li>
            <li><strong>Store Cashier Backoffice Demo:</strong> Test order processing, table management, KOT, and cashier billing settlement.</li>
            <li><strong>Flutter Web POS Terminal:</strong> Experience the high-speed touch terminal interface directly in your web browser.</li>
            <li><strong>Native Windows Desktop App (.EXE):</strong> Download and test thermal receipt printing on 64-bit Windows hardware.</li>
            <li><strong>Native Android App (.APK):</strong> Download and test on Android handheld terminals and smartphones.</li>
            <li><strong>Online Documentation:</strong> Review system requirements, PHP/MySQL prerequisites, and installation manuals in advance.</li>
          </ul>
          <p>
            We strongly advise every prospective buyer to thoroughly test all features, workflows, and hardware compatibility via our live demos before committing to a purchase.
          </p>
        </section>

        <!-- Section 4 -->
        <section class="policy-section" id="delivery-scope">
          <h2><span class="sec-num">4</span> Scope of Delivery &amp; Self-Service Requirements</h2>
          <p>
            Please note that our products are self-hosted software licenses accompanied by full source code for self-service deployment:
          </p>
          <ul>
            <li><strong>Self-Service Deployment:</strong> Customers are required to possess or hire the necessary technical skills to configure their own web hosting/VPS, manage MySQL databases, and compile Flutter applications following our step-by-step documentation.</li>
            <li><strong>Exclusions from Base Plans:</strong> Unless explicitly purchased as a custom dedicated service or stated in an enterprise custom agreement, dedicated server installation, cPanel configuration, and pre-compiled customized APK/EXE app store builds are not included in digital source code licenses.</li>
            <li><strong>Hosting Incompatibility:</strong> Inability to setup hosting, lack of technical knowledge, refusal to read provided documentation, or changing one's mind after purchase does not constitute valid grounds for a refund.</li>
          </ul>
        </section>

        <!-- Section 5 -->
        <section class="policy-section" id="exceptional-cases">
          <h2><span class="sec-num">5</span> Limited Exceptional Scenarios</h2>
          <p>
            In rare and strictly defined situations, we may review requests for account adjustments on a case-by-case basis:
          </p>
          <div class="policy-highlight-box">
            <h4 style="margin:0 0 6px;font-size:14px;color:#0f172a;">A. Duplicate Billing / Accidental Double Charge</h4>
            <p style="margin:0;font-size:13px;color:#475569;line-height:1.5;">
              If your payment method was accidentally charged twice for the exact same license transaction due to a network glitch or multiple clicks, notify our support within <strong>24 hours</strong> with both transaction reference IDs. Once verified, the duplicate charge will be promptly reversed.
            </p>
          </div>
          <div class="policy-highlight-box">
            <h4 style="margin:0 0 6px;font-size:14px;color:#0f172a;">B. Verified Critical Core Defect</h4>
            <p style="margin:0;font-size:13px;color:#475569;line-height:1.5;">
              If the core unencrypted software contains a reproducible, critical technical flaw that completely prevents the software from functioning on standard supported environments (PHP 8.2+, MySQL 8+), and our engineering team is unable to provide a working patch or resolution within <strong>14 business days</strong> of a detailed technical ticket, a credit or refund may be considered at our sole discretion.
            </p>
          </div>
        </section>

        <!-- Section 6 -->
        <section class="policy-section" id="chargebacks">
          <h2><span class="sec-num">6</span> Payment Disputes &amp; License Revocation</h2>
          <p>
            Initiating an unauthorized chargeback, credit card dispute, or PayPal claim without contacting our support team first is a direct breach of this purchase agreement.
          </p>
          <ul>
            <li><strong>Immediate License Deactivation:</strong> In the event of a chargeback or payment reversal, all associated software licenses, module entitlements, and domain bindings will be immediately and permanently revoked on our Central License Server.</li>
            <li><strong>Central Network Blacklisting:</strong> The associated domain, customer email, and server IP will be blacklisted across our verification network, blocking future downloads, module downloads, and platform updates.</li>
            <li><strong>Legal Documentation:</strong> We submit detailed transaction logs, delivery proofs, IP logs, and binding records to the payment processor to contest fraudulent chargebacks.</li>
          </ul>
        </section>

        <!-- Section 7 -->
        <section class="policy-section" id="support-assistance">
          <h2><span class="sec-num">7</span> Technical Support &amp; Help Desk</h2>
          <p>
            We are dedicated to helping our customers succeed with their self-hosted deployments. If you experience difficulty installing, configuring, or activating your purchased licenses, our support team is available to assist you.
          </p>

          <div class="contact-card">
            <h3>Need Assistance with Your License or Order?</h3>
            <p>
              Please contact our official customer support desk. Provide your Order Reference ID, registered email address, and a detailed description of your question or issue.
            </p>
            <a href="mailto:<?= e_attr($supportEmail) ?>?subject=License%20Support%20Inquiry" class="contact-btn">
              ✉️ Email Support: <?= e_attr($supportEmail) ?>
            </a>
          </div>
        </section>

      </article>

    </div>
  </main>

  <!-- Site Footer -->
  <footer class="site-footer">
    <div class="container footer-container">
      <div class="footer-top">
        <div class="footer-brand-wrap">
          <div class="brand">
            <span class="brand-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
              </svg>
            </span>
            <span class="brand-name">Zoom POS &amp; Market</span>
          </div>
          <p class="footer-tagline"><?= e_attr($tagline) ?></p>
        </div>

        <nav class="footer-nav">
          <a href="index.php#home">Home</a>
          <a href="index.php#overview">Overview</a>
          <a href="index.php#business-types">Business Types</a>
          <a href="index.php#modules">Modules</a>
          <a href="index.php#pricing">Pricing</a>
          <a href="privacy-policy.php">Privacy Policy</a>
          <a href="return-policy.php">Return Policy</a>
          <a href="index.php#faq">FAQ</a>
        </nav>
      </div>

      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= e_attr($siteName) ?>. All rights reserved.</p>
        <div class="footer-meta-links">
          <a href="privacy-policy.php">Privacy Policy</a>
          <span>&bull;</span>
          <a href="return-policy.php">Return Policy</a>
          <span>&bull;</span>
          <a href="verify-license.php">Verify License Key</a>
          <?php if (!empty($urls['documentation'])): ?>
            <span>&bull;</span>
            <a href="<?= e_attr($urls['documentation']) ?>" target="_blank">Documentation</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </footer>

</body>
</html>
