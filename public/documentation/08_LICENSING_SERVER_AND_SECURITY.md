# Licensing, Entitlements and Access Controls

## Backend purchase/license verification

The backend `CustomLicenseServerClient` posts to the fixed authority `https://license.zoomnearby.com/api/verify.php` with `X-Server-Secret` and JSON fields `license_key`, `product_slug`, `domain`. It reads `status`, `message`, `expires_at` and optional `plan`. Other operations call `api/catalog.php`, `api/download.php` and `api/issue.php`.

Configure `LICENSE_SERVER_SECRET` and optional `LICENSE_SERVER_TIMEOUT` in the server environment. `LICENSE_VERIFICATION_HOST` and `LICENSE_KEY` are not the configuration contract here. The authority URL is fixed in `config/services.php`. Enter the purchase key through installation or module activation. Keep the authority secret on the server.

A failed transport or non-success HTTP response is a failed verification in the client. Periodic license checking is a separate scheduled policy; review `CheckLicenseStatusCommand` for its failure-streak and deactivation behavior. An unreachable authority is not a successful activation.

## Flutter server-address handshake

The actual Flutter implementation reconstructs the endpoint from obfuscated byte arrays and sends:

```json
{
  "server_url": "https://saas.example.com",
  "platform": "android",
  "timestamp": 1790121600,
  "signature": "HMAC_SHA256_HEX_PLACEHOLDER"
}
```

The signature input is the normalized URL, Unix timestamp and platform joined with `|`. The request uses JSON headers and `X-Client-Platform`; the signature is in the body. `license_security_engine.dart` has a 15-second request timeout and handles redirects, network, TLS, server and invalid-format errors. It rejects an HTTPS-to-HTTP downgrade. Do not substitute the older documentation's nonce or `X-Signature` example for this actual contract.

The server-address screen accepts a result with `code=200` and `status=authorized`, stores the returned `license_token` in preferences and proceeds. That storage path does not establish encrypted token storage. XOR obfuscation does not make an embedded client signing key secret. Backend license verification and this client handshake are separate flows and must both match the deployed authority.

## Authorization and tenancy

Tenant sessions and API keys establish company context. Branch resolution checks ownership, active state and staff assignment. Role/permission checks, subscription gates, operating-mode/vertical gates and module entitlements constrain operations. Administrative routes use the platform guard. Public storefront, table QR, invoice/quote links and repair-tracking routes have separate access semantics; do not describe every public document link as a signed or expiring link unless its implementation does so.

The source includes OTP services, password recovery, invitation acceptance, social authentication, device revocation, demo-mode modification guards, maintenance checks and audit models. Configure the applicable feature, verify both allowed and denied operations, and review logs without distributing credentials.

## Entitlements and commercial promises

A license purchase, active module, plan feature, quota, user permission and client capability are distinct checks. A Regular/Extended label does not itself prove that turnkey rebranding, compiled binaries or hands-on support is included; those are contracted service deliverables. The proposed packaging appears in chapter 02. Avoid claims of regulatory certification, unbreakable client licensing or unlimited offline access that the code does not establish.
