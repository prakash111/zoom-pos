<?php

require __DIR__ . '/config.php';

$data = get_landing_page_data();
$siteName = $data['branding']['site_name'] ?? 'Zoom POS & Market';
$tagline = $data['branding']['site_tagline'] ?? 'Smarter Business. Greater Control.';
$supportEmail = $data['branding']['support_email'] ?? 'support@zoomnearby.com';
$urls = $data['urls'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Privacy Policy — <?= e_attr($siteName) ?></title>
  <meta name="description" content="Privacy Policy for <?= e_attr($siteName) ?>. Learn how we collect, protect, and handle your data and software license credentials.">
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
      <span class="policy-badge">Privacy &amp; Data Protection</span>
      <h1 class="policy-title">Privacy Policy</h1>
      <p class="policy-subtitle">
        We respect your privacy and are committed to safeguarding your personal data, transaction information, and licensing credentials.
      </p>
      <div class="policy-meta">
        <span>📅 Last Updated: September 2026</span>
        <span>🛡️ GDPR &amp; CCPA Compliant</span>
        <span>🔒 256-Bit SSL Encrypted</span>
      </div>
    </div>
  </section>

  <!-- Main Content Layout -->
  <main class="container">
    <div class="policy-layout">

      <!-- Sticky Table of Contents -->
      <aside class="policy-toc">
        <div class="toc-title">Privacy Table of Contents</div>
        <ul class="toc-list">
          <li><a href="#overview">1. Overview &amp; Controller</a></li>
          <li><a href="#data-we-collect">2. Information We Collect</a></li>
          <li><a href="#how-we-use-data">3. How We Use Your Data</a></li>
          <li><a href="#license-telemetry">4. Licensing &amp; Domain Verification</a></li>
          <li><a href="#payment-security">5. Payment Processors &amp; Security</a></li>
          <li><a href="#cookies-analytics">6. Cookies &amp; Tracking</a></li>
          <li><a href="#user-rights">7. Your Privacy Rights</a></li>
          <li><a href="#contact-privacy">8. Contact Data Officer</a></li>
        </ul>
      </aside>

      <!-- Document Content -->
      <article class="policy-content">

        <!-- Section 1 -->
        <section class="policy-section" id="overview">
          <h2><span class="sec-num">1</span> Overview &amp; Data Controller</h2>
          <p>
            This Privacy Policy describes how <strong><?= e_attr($siteName) ?></strong> collects, stores, uses, and protects information gathered from users who visit our marketing website, place software orders, verify license keys, or deploy our self-hosted platform.
          </p>
          <p>
            We take data confidentiality seriously. We do not sell, rent, monetize, or trade your personal information to third-party data brokers or marketing firms.
          </p>
        </section>

        <!-- Section 2 -->
        <section class="policy-section" id="data-we-collect">
          <h2><span class="sec-num">2</span> Information We Collect</h2>
          <p>We only collect information necessary to fulfill your orders, generate valid software licenses, and provide technical product updates:</p>
          <ul>
            <li><strong>Customer Identification:</strong> Registered email address, name, organization name, and billing country.</li>
            <li><strong>Order Details:</strong> Product identifiers, bundle choices, transaction references, invoice records, and purchase dates.</li>
            <li><strong>Installation Data:</strong> Primary domain/subdomain name where the software license is bound and activated.</li>
            <li><strong>Technical Diagnostics:</strong> IP address, browser user-agent, and server environment versions (e.g. PHP/MySQL versions) during verification requests.</li>
          </ul>
        </section>

        <!-- Section 3 -->
        <section class="policy-section" id="how-we-use-data">
          <h2><span class="sec-num">3</span> How We Use Your Information</h2>
          <p>Your information is used strictly for legitimate commercial and operational purposes:</p>
          <ul>
            <li><strong>Order Fulfillment:</strong> Generating cryptographic license keys and emailing access instructions immediately following payment.</li>
            <li><strong>Entitlement Management:</strong> Authorizing module downloads, package activations, and version update checks.</li>
            <li><strong>Customer Support:</strong> Verifying license ownership when addressing customer technical inquiries or bug reports.</li>
            <li><strong>Security &amp; Fraud Prevention:</strong> Preventing software piracy, unauthorized domain cloning, and fraudulent chargeback activities.</li>
          </ul>
        </section>

        <!-- Section 4 -->
        <section class="policy-section" id="license-telemetry">
          <h2><span class="sec-num">4</span> Central Licensing &amp; Telemetry</h2>
          <p>
            Our software includes a lightweight, secure licensing client that communicates with our Central License Server (<code>license.zoomnearby.com</code>):
          </p>
          <div class="policy-highlight-box">
            <p style="margin:0;font-size:13.5px;color:#334155;line-height:1.6;">
              <strong>What the License Client Transmits:</strong> Only your registered license key, installation domain, and installed module slugs are sent to verify your active entitlement. <strong>No sensitive business records, store revenues, customer lists, order items, or passwords</strong> are ever sent to our licensing server. Your business data remains 100% on your own self-hosted infrastructure.
            </p>
          </div>
        </section>

        <!-- Section 5 -->
        <section class="policy-section" id="payment-security">
          <h2><span class="sec-num">5</span> Payment Processing &amp; Security</h2>
          <p>
            All financial transactions are conducted through Tier-1 PCI-DSS compliant payment gateways (including Stripe, PayPal, and Razorpay):
          </p>
          <ul>
            <li><strong>No Credit Card Storage:</strong> We do not receive, store, or process raw credit card numbers or banking passwords on our servers. All sensitive card data is handled directly by the payment processors via encrypted tokens.</li>
            <li><strong>Data Encryption:</strong> All data transmissions between your browser and our servers are encrypted using modern Transport Layer Security (TLS 1.3 / 256-bit SSL).</li>
          </ul>
        </section>

        <!-- Section 6 -->
        <section class="policy-section" id="cookies-analytics">
          <h2><span class="sec-num">6</span> Cookies &amp; Local Storage</h2>
          <p>
            We use minimal cookies strictly required for the functioning of our marketing site:
          </p>
          <ul>
            <li><strong>Functional Session Cookies:</strong> Maintaining your selected plan in the checkout modal and caching license check statuses.</li>
            <li><strong>No Invasive Advertising Trackers:</strong> We do not deploy third-party advertising trackers or invasive cross-site profiling cookies.</li>
          </ul>
        </section>

        <!-- Section 7 -->
        <section class="policy-section" id="user-rights">
          <h2><span class="sec-num">7</span> Your Data Rights (GDPR &amp; CCPA)</h2>
          <p>
            Depending on your jurisdiction, you have specific rights regarding your personal data:
          </p>
          <ul>
            <li><strong>Right of Access:</strong> Request a copy of the personal information and order history we hold on your account.</li>
            <li><strong>Right to Rectification:</strong> Request correction of inaccurate contact email or registered domain names.</li>
            <li><strong>Right to Erasure:</strong> Request deletion of your personal records, subject to statutory tax and commercial record retention laws.</li>
          </ul>
        </section>

        <!-- Section 8 -->
        <section class="policy-section" id="contact-privacy">
          <h2><span class="sec-num">8</span> Contact Our Privacy Desk</h2>
          <p>
            If you have questions regarding this Privacy Policy or wish to exercise your data protection rights, please contact our data team:
          </p>

          <div class="contact-card">
            <h3>Privacy Questions or Data Requests?</h3>
            <p>
              Send an email to our support and privacy desk. We respond to all verified customer privacy requests within 48 business hours.
            </p>
            <a href="mailto:<?= e_attr($supportEmail) ?>?subject=Privacy%20Policy%20Inquiry" class="contact-btn">
              ✉️ Email: <?= e_attr($supportEmail) ?>
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
