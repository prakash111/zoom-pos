# Omnichannel Notification & WhatsApp API Integration Guide

This guide provides end-to-end instructions for configuring and dispatching automated customer notifications across **WhatsApp, SMS, Email (SMTP), Telegram, and Custom Webhooks**.

All notification gateways are managed directly from your tenant web dashboard at:  
**Settings &gt; Integrations &amp; Notification Gateways** (`/tenant/settings/integrations`).

---

## 1. Architecture & Overview

The platform uses a unified, multi-channel dispatch architecture:

```
+-----------------------------------------------------------------------------------+
|                        Business Operational Triggers                              |
|  - POS Checkout & Digital Receipts           - Storefront Online Orders (OTP/Status) |
|  - Quotations & Estimates                    - Balance Due & Receivable Reminders    |
|  - Restaurant KOT Slips & Dine-in Tables     - Pharmacy Batch & Expiry Notices       |
|  - Salon Booking Confirmations               - Repair Shop Ticket Status Milestones  |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                     Tenant Notification Routing Engine                            |
|             (Encrypted AES-256 Credential Vault & Duplicate Shield)               |
+-----------------------------------------------------------------------------------+
         |                    |                  |                 |              |
         v                    v                  v                 v              v
+------------------+ +------------------+ +-------------+ +---------------+ +-----------+
|  WhatsApp API    | |   SMS Gateway    | | Custom SMTP | | Webhook Engine| | Telegram  |
| - Meta Cloud API | | - Twilio SMS     | | - TLS (587) | | - HMAC SHA256 | | - Bot API |
| - Twilio WA      | | - MSG91 (DLT)    | | - SSL (465) | | - JSON Stream | | - Admin   |
| - Unofficial QR  | | - Generic HTTP   | | - Domain ID | | - POS Events  | |   Alerts  |
+------------------+ +------------------+ +-------------+ +---------------+ +-----------+
```

---

## 2. Official Meta WhatsApp Cloud API (Recommended)

The **Meta WhatsApp Cloud API** is the direct, official cloud messaging interface hosted by Meta (Facebook). It requires no external hardware, supports high-volume throughput, and qualifies your brand for the official WhatsApp verified badge.

### Step 1: Create a Meta Developer Account
1. Open [developers.facebook.com](https://developers.facebook.com) and log in with your primary Facebook credentials.
2. Click **Get Started** (or **My Apps**) in the top-right corner.
3. Accept the Meta Developer Terms and complete account verification.

### Step 2: Create a Meta Business App
1. Click **Create App**.
2. When prompted for use case, select **Other** > click **Next**.
3. For App Type, choose **Business** > click **Next**.
4. Enter your **App Display Name** (e.g., `Zoom Store Notifier`).
5. Select your verified **Meta Business Account** from the dropdown > click **Create App**.

### Step 3: Add the WhatsApp Product
1. On your App Dashboard, scroll down through the product catalog to **WhatsApp**.
2. Click **Set Up**. This opens the WhatsApp configuration interface.

### Step 4: Generate a Permanent System User Access Token
> [!IMPORTANT]
> The temporary token shown on the API Setup page expires after 24 hours. For automated production dispatch, you **must** generate a permanent System User token.

1. Navigate to **Meta Business Suite** at [business.facebook.com/settings](https://business.facebook.com/settings).
2. In the left navigation, go to **Users > System Users**.
3. Click **Add** to create a new System User:
   - **System User Name**: `zoom-whatsapp-bot`
   - **Role**: `Admin`
4. Click **Add Assets**:
   - Under **Asset Type**, select **Apps**.
   - Select your newly created WhatsApp App.
   - Toggle **Full Control (Manage App)** to `ON` > click **Save Changes**.
5. Click **Generate New Token**:
   - Select your WhatsApp App.
   - Select **Token Expiration**: `Never`.
   - Check the following two required permissions:
     - `whatsapp_business_messaging`
     - `whatsapp_business_management`
6. Click **Generate Token**. Copy and store the resulting token (starts with `EAAG...`).

### Step 5: Register and Verify Your Official Business Phone Number
1. In Meta Developer Dashboard, navigate to **WhatsApp > API Setup** (or in WhatsApp Manager under **Phone Numbers**).
2. Click **Add Phone Number**.
3. Enter your business profile details:
   - **Display Name**: Your registered business name.
   - **Category**: Retail, Food & Beverage, Pharmacy, etc.
   - **Business Description**: Brief description of your business.
4. Enter your business telephone number and select your verification method (**SMS** or **Voice Call**).
5. Enter the 6-digit verification code received.
6. Once verified, Meta displays your unique **Phone Number ID**.

### Step 6: Retrieve Your Connection Credentials
Collect the three values required for your SaaS dashboard:
- **Phone Number ID**: Numeric string (e.g., `104523456789012`).
- **WhatsApp Business Account ID (WABA ID)**: Numeric string (e.g., `108765432109876`).
- **Permanent Access Token**: The permanent token generated in Step 4.

### Step 7: Configure Webhook for Delivery Receipts (Optional)
To enable real-time delivery ticks (sent, delivered, read) in your POS receipts:
1. In Meta App Dashboard, go to **WhatsApp > Configuration > Webhook**.
2. Enter:
   - **Callback URL**: `https://your-domain.com/api/whatsapp/webhook`
   - **Verify Token**: Your configured secret verification string.
3. Click **Verify and Save**.
4. Under **Webhook Fields**, click **Manage** and subscribe to the `messages` event.

### Step 8: Create and Submit Message Templates
Meta requires approved templates for initiating notifications to customers:
1. Open **WhatsApp Manager > Account Tools > Message Templates**.
2. Click **Create Template**:
   - **Category**: `Utility` (for transactional invoices, receipts, and order updates) or `Authentication` (for OTP).
   - **Name**: e.g., `store_receipt_v1` (lowercase with underscores).
   - **Language**: English (or your store's primary language).
3. **Template Body Example**:
   ```
   Hello {{1}}, thank you for shopping at {{2}}!
   Your Order #{{3}} for {{4}} has been confirmed.
   View your digital receipt & invoice: {{5}}
   ```
4. **Interactive Action Buttons (Optional)**:
   - Add a Call to Action button: Type = `Visit Website`, Button Text = `View Invoice`, URL Type = `Dynamic`, URL = `{{5}}`.
5. Click **Submit**. Utility templates are typically approved within 5-15 minutes.

### Step 9: Save & Verify in Tenant Dashboard
1. Log in to your store dashboard > go to **Settings > Integrations > WhatsApp Business**.
2. Toggle **Enable WhatsApp** to `ON`.
3. Choose **Meta WhatsApp Cloud API (Official)**.
4. Input your **Phone Number ID**, **WABA ID**, **Permanent Access Token**, and **Template Namespace / Name**.
5. Click **Save WhatsApp Gateway**.
6. In the **Test WhatsApp Dispatch** panel:
   - Enter your mobile phone number including country code (e.g. `14155552671`).
   - Click **📲 Send Test Ping**.
   - Confirm receipt of the test verification message on your phone!

---

## 3. Alternative WhatsApp Connection Gateways

### Twilio WhatsApp API
For merchants using Twilio for unified SMS and WhatsApp messaging:
1. Obtain your **Twilio Account SID** and **Auth Token** from your Twilio Console.
2. In the dashboard, select **Twilio WhatsApp API**.
3. Enter your Twilio credentials and your approved **Sender WhatsApp Number** (e.g., `whatsapp:+14155238886`).
4. To test with the Twilio WhatsApp Sandbox, enter the sandbox join phrase and test sender number.

### Unofficial / Self-Hosted QR Gateway (Baileys / WPPConnect / Evolution API)
For businesses using an internal bridge or existing phone line without Meta registration:
1. Deploy a private WhatsApp bridge (such as Baileys, WPPConnect, or Evolution API).
2. Scan the provided QR code with any standard WhatsApp mobile app.
3. In your store dashboard, select **Unofficial / Self-hosted API**.
4. Enter your **Gateway URL Endpoint** (e.g. `https://wa-gw.yourstore.com/send`) and **Bearer Token**.
5. Test using the test ping panel.

---

## 4. SMS Gateway Integrations

Configure direct SMS delivery for receipts, OTP codes, and balance due notices:

| Gateway Provider | Required Fields | Highlights |
| :--- | :--- | :--- |
| **ZoomNearby SMS Gateway (Generic HTTP REST)** | Endpoint URL (`https://sms.zoomnearby.com/api/v1/messages/send`), Method (`POST`), Bearer Auth Token | Native ZoomNearby Android SMS Gateway. Routes messages through your connected Android phone SIM cards or Cloud sender IDs with high speed. |
| **Twilio SMS** | Account SID, Auth Token, Sender ID / From Number | Global carrier routing across 180+ countries. |
| **MSG91 (India DLT)** | Auth Key, 6-Char Sender ID (`ZOOMNB`), DLT Template ID | Telecom DLT compliant for Indian registered headers. |
| **Custom Generic HTTP REST** | Endpoint URL, Method (`POST`/`GET`), Bearer Token | Connects to any local telecom or custom SMS gateway using `{phone}` and `{message}` placeholders. |

---

### ZoomNearby SMS Gateway API Integration Guide

Reference: [https://sms.zoomnearby.com/docs/api#authenticating-requests](https://sms.zoomnearby.com/docs/api#authenticating-requests)

#### 1. Obtaining Your Auth Token
1. Log in to your SMS dashboard at [https://sms.zoomnearby.com](https://sms.zoomnearby.com).
2. Click **Generate API token** (or navigate to API Tokens).
3. Copy your secret bearer token.

#### 2. Authenticating Requests
To authenticate requests, include the `Authorization` header with the Bearer token:
```http
Authorization: Bearer {YOUR_AUTH_KEY}
Content-Type: application/json
Accept: application/json
```

#### 3. Send Message API Endpoint
- **Method**: `POST` (or `GET`)
- **URL**: `https://sms.zoomnearby.com/api/v1/messages/send`
- **Headers**:
  ```http
  Authorization: Bearer {YOUR_AUTH_KEY}
  Content-Type: application/json
  Accept: application/json
  ```
- **JSON Request Body**:
  ```json
  {
    "mobile_numbers": ["+12345678901"],
    "type": "SMS",
    "message": "Hello! Your Order #1024 has been confirmed.",
    "sims": ["*"]
  }
  ```
- **Parameters**:
  - `mobile_numbers` (array of string, required): Destination telephone numbers in international format with country code.
  - `message` (string, required): SMS text body (up to 1600 characters).
  - `type` (string, optional): `SMS` (default), `MMS`, or `WhatsApp`.
  - `sims` (array of integer/string, optional): Specific SIM card slot IDs, or `["*"]` to route through all available SIM cards.
  - `sender_ids` (array of integer/string, optional): Sending server IDs if using cloud sender IDs instead of SIM cards.

#### 4. cURL Request Example
```bash
curl --request POST \
  "https://sms.zoomnearby.com/api/v1/messages/send" \
  --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
  --header "Content-Type: application/json" \
  --header "Accept: application/json" \
  --data '{
    "mobile_numbers": [
      "+12345678901"
    ],
    "type": "SMS",
    "message": "Test SMS from ZoomNearby POS",
    "sims": [
      "*"
    ]
  }'
```

#### 5. Configuring in Tenant Settings
1. Navigate to **Settings > Integrations > SMS Gateways** (`/tenant/settings/integrations`).
2. Toggle **Enable SMS** to `ON`.
3. Select **Generic HTTP Gateway**.
4. Set **Gateway Endpoint URL**: `https://sms.zoomnearby.com/api/v1/messages/send`.
5. Set **HTTP Method**: `POST` (JSON - Recommended).
6. Set **API Auth Key**: Paste your Bearer token from `sms.zoomnearby.com`.
7. Click **Save SMS Gateway**.
8. In the **Test SMS Dispatch** box, enter your mobile phone number and click **📨 Send Test SMS** to verify live message delivery.

---

## 5. Custom SMTP Mail Server (Branded Invoices & Emails)

Ensure delivery of branded PDF invoices, quotations, and password resets from your own domain:

- **SMTP Host**: e.g., `smtp.gmail.com`, `smtp.mailgun.org`, `smtp.sendgrid.net`, `email-smtp.us-east-1.amazonaws.com`
- **Port**: `587` (TLS - Recommended), `465` (SSL), or `25` (Plain)
- **Encryption**: `TLS` or `SSL`
- **Username**: Your authenticated email address (e.g. `billing@yourstore.com`)
- **Password**: Your SMTP account password or secure App Password
- **From Address**: `billing@yourstore.com`
- **From Name**: `Your Store Name`

Click **Send Test Email** to send a live verification message to your inbox.

---

## 6. Custom Webhook Dispatcher & Third-Party Integrations

Stream store transactions and events to external systems (Zapier, Make, n8n, ERPs):

- **Destination URL**: `https://api.yourdomain.com/pos-events`
- **HTTP Method**: `POST` (JSON) or `PUT` (JSON)
- **HMAC SHA-256 Secret**: Enter a secret key. Every outbound dispatch will include an `X-Signature` header containing the computed HMAC hash of the raw JSON body for verification.
- **Event Subscriptions**:
  - `receipt_generated`: Finalized counter POS sales.
  - `invoice_created`: Storefront checkout and back-office invoices.
  - `quotation_sent`: Proforma invoices and estimates.
  - `due_reminder`: Automated account balance follow-ups.

---

## 7. Automated Operational Notification Triggers

Configure these triggers under **Settings > Integrations > Verification & Notifications**:

1. **Mandatory Customer Account Verification (OTP)**:
   - Require customers to verify a 6-digit OTP code before submitting online storefront orders.
   - Dispatch channels: SMS, WhatsApp, and/or Email.
2. **Order Lifecycle Status Updates**:
   - Order Placed / Confirmed (`placed`)
   - Order Completed / Delivered (`completed`)
   - Order Cancelled / Refunded (`cancelled`)
3. **POS Checkout Receipts**:
   - In the counter POS checkout sheet, checking the WhatsApp or SMS icon automatically dispatches the digital receipt upon finalizing payment.
4. **Quotations & Estimates**:
   - Send quotation links to customers with an expiry date and digital confirmation button.
5. **Account Receivable Due Reminders**:
   - Automated balance due reminders respect customer timezones and include built-in duplicate prevention.
6. **Industry Module Dispatches**:
   - **Restaurants**: Kitchen Order Ticket (KOT) dispatches and table status notifications.
   - **Pharmacies**: Prescription dispensing confirmation and batch expiry alerts.
   - **Salons**: Specialist appointment bookings, reminders, and schedule changes.
   - **Repair Shops**: Ticket milestone notifications (Received, Diagnosing, Parts Awaiting, Ready for Pickup).
