<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();
ensure_app_builder_schema($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $msg = 'Saved.';

    if ($action === 'save') {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', trim($_POST['slug'] ?? '')));
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $currency = strtoupper(substr(trim($_POST['currency'] ?? 'USD'), 0, 3));
        $desc = trim($_POST['description'] ?? '');
        $modules = $_POST['included_modules'] ?? [];
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($slug === '' || $name === '') {
            header('Location: bundles.php?msg=' . urlencode('Slug and Name are required.'));
            exit;
        }

        if (empty($modules) || !is_array($modules)) {
            header('Location: bundles.php?msg=' . urlencode('Please select at least one module/product for this bundle.'));
            exit;
        }

        // Clean module slugs
        $cleanModules = array_values(array_unique(array_filter(array_map(fn($m) => strtolower(trim($m)), $modules))));

        $customFeaturesRaw = trim($_POST['custom_features'] ?? '');
        $cleanFeatures = [];
        if ($customFeaturesRaw !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $customFeaturesRaw);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $cleaned = preg_replace('/^[\x{2022}\x{25E6}\x{2043}\x{2219}\*\-]\s+/u', '', $trimmed);
                    $cleanFeatures[] = trim($cleaned !== null && $cleaned !== '' ? $cleaned : $trimmed);
                }
            }
        }

        $jsonFeatures = !empty($cleanFeatures)
            ? json_encode($cleanFeatures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            : null;

        try {
            // Ensure custom_features and app_builder_limit columns exist in bundles table
            static $bundleColReady = false;
            if (!$bundleColReady) {
                try {
                    $pdo->query('SELECT custom_features FROM bundles LIMIT 0');
                } catch (Throwable $e) {
                    try {
                        $pdo->exec('ALTER TABLE bundles ADD COLUMN custom_features JSON NULL AFTER included_modules');
                    } catch (Throwable $e2) {
                        $pdo->exec('ALTER TABLE bundles ADD COLUMN custom_features TEXT NULL');
                    }
                }
                try {
                    $pdo->query('SELECT app_builder_limit FROM bundles LIMIT 0');
                } catch (Throwable $e) {
                    try {
                        $pdo->exec('ALTER TABLE bundles ADD COLUMN app_builder_limit INT NOT NULL DEFAULT 20 AFTER price');
                    } catch (Throwable $e2) {}
                }
                $bundleColReady = true;
            }

            $appBuilderLimit = isset($_POST['app_builder_limit']) && $_POST['app_builder_limit'] !== ''
                ? (int)$_POST['app_builder_limit']
                : 20;

            $stmt = $pdo->prepare(
                'INSERT INTO bundles (slug, name, description, price, currency, app_builder_limit, included_modules, custom_features, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                   price = VALUES(price), currency = VALUES(currency),
                   app_builder_limit = VALUES(app_builder_limit),
                   included_modules = VALUES(included_modules), custom_features = VALUES(custom_features), is_active = VALUES(is_active)'
            );
            $stmt->execute([
                $slug,
                $name,
                $desc ?: null,
                $price,
                $currency,
                $appBuilderLimit,
                json_encode($cleanModules),
                $jsonFeatures,
                $isActive,
            ]);
            $msg = 'Bundle saved successfully.';
        } catch (Throwable $ex) {
            header('Location: bundles.php?msg=' . urlencode('Error saving bundle: ' . $ex->getMessage()));
            exit;
        }
    } elseif ($action === 'delete' && !empty($_POST['slug'])) {
        $delSlug = trim($_POST['slug']);
        $pdo->prepare('DELETE FROM bundles WHERE slug = ?')->execute([$delSlug]);
        $msg = 'Bundle deleted.';
    }
    clear_landing_cache();
    header('Location: bundles.php?msg=' . urlencode($msg));
    exit;
}

$bundles = $pdo->query('SELECT * FROM bundles ORDER BY id DESC')->fetchAll();
$products = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY name')->fetchAll();
$productsBySlug = [];
foreach ($products as $p) {
    $productsBySlug[$p['slug']] = $p;
}

$token = csrf_token();
lm_header('bundles', 'Script & Module Bundles');
?>
<section class="card" id="bundle-form-card" style="margin-bottom:24px">
    <h3 class="card-title"><span>✨</span> <span id="form-heading">Create or Update Bundle</span></h3>
    <p class="muted" style="margin-top:-6px;margin-bottom:18px;font-size:13px">
        Package the Core SaaS Platform with add-on modules (e.g. Lead Manager, Pharmacy, Salon) at a custom bundle price. When a customer purchases a bundle, separate active license keys are automatically minted for each included product and emailed to them.
    </p>

    <form method="post" action="bundles.php" class="grid" id="bundle-form">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">

        <label>Bundle Slug (Identifier)
            <input name="slug" id="field-slug" placeholder="e.g. core-lead" required>
            <span class="muted" style="font-size:11px">Used in checkout URL: <code>?bundle=core-lead</code></span>
        </label>

        <label>Bundle Display Name
            <input name="name" id="field-name" placeholder="e.g. Main Script + Lead Manager Bundle" required>
        </label>

        <label>Bundle Price
            <input type="number" name="price" id="field-price" step="0.01" min="0" value="69.00" required>
        </label>

        <label>Currency
            <input name="currency" id="field-currency" value="<?= e(setting('currency', 'USD')) ?>" maxlength="3">
        </label>

        <label>App Builder Monthly Limit
            <input type="number" name="app_builder_limit" id="field-builder-limit" value="20" min="-1" step="1">
            <span class="muted" style="font-size:11px">Monthly builds granted to bundle buyers. Set <code>-1</code> for Unlimited builds.</span>
        </label>

        <label style="grid-column:1/-1">Description
            <input name="description" id="field-description" placeholder="Short marketing description of the bundle value">
        </label>

        <div style="grid-column:1/-1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
            <div style="font-weight:700;font-size:13px;color:#1e293b;margin-bottom:8px">
                📦 Included Products / Modules (Select at least one):
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:10px">
                <?php foreach ($products as $p): ?>
                    <label class="row" style="background:#ffffff;border:1px solid #cbd5e1;padding:8px 12px;border-radius:8px;cursor:pointer;margin-bottom:0">
                        <input type="checkbox" name="included_modules[]" value="<?= e($p['slug']) ?>" class="module-check" id="mod-<?= e($p['slug']) ?>">
                        <div>
                            <strong style="font-size:13px;color:#0f172a"><?= e($p['name']) ?></strong>
                            <div style="font-size:11px;color:#64748b">
                                <code><?= e($p['slug']) ?></code> &bull; Regular: <?= e(number_format((float)$p['price'], 2)) ?> <?= e($p['currency']) ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="grid-column:1/-1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <label style="font-weight:700;font-size:13px;color:#1e293b;margin-bottom:0">
                    ✨ Custom Features &amp; Perks (Shown on Landing Page Pricing Card)
                </label>
                <span class="muted" style="font-size:11px">One feature per line</span>
            </div>
            <textarea name="custom_features" id="field-custom-features" rows="5" placeholder="Everything in Core (Retail + Restaurant + Café)
Lead Management CRM Module Included
Visual Kanban Sales Pipeline &amp; Follow-ups
Quotation Generator &amp; Auto Customer Sync
Two Separate License Keys Emailed Instantly
Priority Updates &amp; Comprehensive Setup Guide
Zero Monthly or Annual Platform Fees" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:13px;line-height:1.6;box-sizing:border-box"></textarea>
            <p class="muted" style="margin-top:6px;margin-bottom:0;font-size:11px;line-height:1.5">
                💡 <b>Tip:</b> Enter the features/perks you want displayed with green checkmarks (✔) on the landing page pricing card for this plan. Enter one feature per line. If left blank, default features based on selected modules will be automatically generated.
            </p>
        </div>

        <label class="row" style="grid-column:1/-1;padding-top:4px">
            <input type="checkbox" name="is_active" id="field-is-active" checked>
            <strong>Active</strong> (Available for checkout and visible on marketing landing page)
        </label>

        <div style="grid-column:1/-1;display:flex;gap:10px;align-items:center;margin-top:8px">
            <button type="submit" id="submit-btn">💾 Save Bundle</button>
            <button type="button" class="outline-secondary" id="reset-btn" style="display:none" onclick="resetBundleForm()">Cancel Edit</button>
        </div>
    </form>
</section>

<section class="card">
    <h3 class="card-title"><span>🎁</span> Active Bundles (<?= count($bundles) ?>)</h3>
    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>Slug</th>
                <th>Bundle Name</th>
                <th>Included Products</th>
                <th>Bundle Price</th>
                <th>Builder Limit</th>
                <th>Regular Value</th>
                <th>Status</th>
                <th>Direct Buy Link</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bundles as $b): 
            $mods = json_decode($b['included_modules'] ?? '[]', true) ?: [];
            $regSum = 0;
            foreach ($mods as $mSlug) {
                if (isset($productsBySlug[$mSlug])) {
                    $regSum += (float) $productsBySlug[$mSlug]['price'];
                }
            }
            $savings = max(0, $regSum - (float) $b['price']);
            $buyUrl = '../buy.php?bundle=' . urlencode($b['slug']);
        ?>
            <tr>
                <td><code><?= e($b['slug']) ?></code></td>
                <td>
                    <strong><?= e($b['name']) ?></strong>
                    <?php if ($b['description']): ?>
                        <div class="muted" style="font-size:11px;margin-top:2px"><?= e($b['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;flex-wrap:wrap;gap:4px">
                        <?php foreach ($mods as $m): 
                            $mName = $productsBySlug[$m]['name'] ?? $m;
                            $isCore = ($m === 'core');
                        ?>
                            <span class="tag <?= $isCore ? 'blue' : '' ?>" style="font-size:11px">
                                <?= $isCore ? '★ ' : '＋ ' ?><?= e($mName) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td>
                    <span class="tag green" style="font-weight:700;font-size:13px">
                        <?= e(number_format((float) $b['price'], 2)) ?> <?= e($b['currency']) ?>
                    </span>
                </td>
                <td>
                    <?php 
                        $bl = isset($b['app_builder_limit']) ? (int)$b['app_builder_limit'] : 20;
                        if ($bl === -1): 
                    ?>
                        <span class="status-pill active" style="font-weight:700">♾️ Unlimited</span>
                    <?php elseif ($bl === 0): ?>
                        <span class="muted" style="font-size:11px">Disabled</span>
                    <?php else: ?>
                        <span class="tag" style="font-weight:700"><?= $bl ?> builds/mo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="font-size:12px;color:#64748b;text-decoration:line-through">
                        <?= e(number_format($regSum, 2)) ?> <?= e($b['currency']) ?>
                    </span>
                    <?php if ($savings > 0): ?>
                        <div style="font-size:11px;color:#16a34a;font-weight:600">Save <?= e(number_format($savings, 2)) ?> <?= e($b['currency']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($b['is_active']): ?>
                        <span class="status-pill active">● Active</span>
                    <?php else: ?>
                        <span class="status-pill revoked">● Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="key-badge">
                        <span style="font-size:11px"><?= e($buyUrl) ?></span>
                        <button type="button" class="copy-btn" onclick="copyBuyUrl('<?= e($buyUrl) ?>', this)" title="Copy checkout URL">🔗</button>
                    </div>
                </td>
                <td class="acts">
                    <button type="button" class="outline-secondary" onclick='editBundle(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>
                    <form method="post" action="bundles.php" onsubmit="return confirm('Delete bundle <?= e($b['slug']) ?>?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slug" value="<?= e($b['slug']) ?>">
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$bundles): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;padding:24px">No bundles created yet. Use the form above to bundle products.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<script>
function editBundle(b) {
    document.getElementById("form-heading").innerText = "Edit Bundle: " + b.slug;
    document.getElementById("field-slug").value = b.slug;
    document.getElementById("field-name").value = b.name || "";
    document.getElementById("field-price").value = parseFloat(b.price || 0).toFixed(2);
    document.getElementById("field-currency").value = b.currency || "USD";
    document.getElementById("field-builder-limit").value = (b.app_builder_limit !== undefined && b.app_builder_limit !== null) ? b.app_builder_limit : 20;
    document.getElementById("field-description").value = b.description || "";
    document.getElementById("field-is-active").checked = (b.is_active == 1);
    
    // Clear checks
    document.querySelectorAll(".module-check").forEach(function(chk) {
        chk.checked = false;
    });

    // Check included
    try {
        var mods = JSON.parse(b.included_modules || "[]");
        mods.forEach(function(m) {
            var el = document.getElementById("mod-" + m);
            if (el) el.checked = true;
        });
    } catch(e) {}

    // Set custom features
    var customFeaturesText = "";
    try {
        var featArr = Array.isArray(b.custom_features) ? b.custom_features : JSON.parse(b.custom_features || "[]");
        if (Array.isArray(featArr)) {
            customFeaturesText = featArr.join("\n");
        }
    } catch(e) {}
    document.getElementById("field-custom-features").value = customFeaturesText;

    document.getElementById("submit-btn").innerText = "💾 Update Bundle";
    document.getElementById("reset-btn").style.display = "inline-flex";

    var card = document.getElementById("bundle-form-card");
    if (card) card.scrollIntoView({ behavior: "smooth" });
}

function resetBundleForm() {
    document.getElementById("bundle-form").reset();
    document.getElementById("field-custom-features").value = "";
    document.getElementById("field-builder-limit").value = "20";
    document.getElementById("form-heading").innerText = "Create or Update Bundle";
    document.getElementById("submit-btn").innerText = "💾 Save Bundle";
    document.getElementById("reset-btn").style.display = "none";
}

function copyBuyUrl(relUrl, btn) {
    var full = window.location.origin + window.location.pathname.replace(/\/admin\/.*$/, "") + "/" + relUrl.replace(/^\.\.\//, "");
    navigator.clipboard.writeText(full).then(function() {
        var old = btn.innerText;
        btn.innerText = "✓";
        setTimeout(function() { btn.innerText = old; }, 1800);
    });
}
</script>

<?php lm_footer();
