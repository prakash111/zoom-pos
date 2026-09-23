<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $msg = 'Saved.';
    if ($action === 'save') {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', trim($_POST['slug'] ?? '')));
        if ($slug !== '') {
            $pdo->prepare(
                'INSERT INTO products (slug, name, description, price, currency, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                   price = VALUES(price), currency = VALUES(currency), is_active = VALUES(is_active)'
            )->execute([
                $slug,
                trim($_POST['name'] ?? $slug),
                trim($_POST['description'] ?? '') ?: null,
                (float) ($_POST['price'] ?? 0),
                strtoupper(substr(trim($_POST['currency'] ?? 'USD'), 0, 3)),
                isset($_POST['is_active']) ? 1 : 0,
            ]);

            // Module package upload — the ZIP the client server downloads once
            // its license key checks out. `core` needs no package.
            if ($slug !== 'core' && ! empty($_FILES['package']['tmp_name']) && is_uploaded_file($_FILES['package']['tmp_name'])) {
                if (strtolower(pathinfo($_FILES['package']['name'], PATHINFO_EXTENSION)) !== 'zip') {
                    $msg = 'Product saved, but the package must be a .zip.';
                } else {
                    @mkdir(package_dir(), 0770, true);
                    $old = package_path($slug);
                    $fname = package_slug($slug).'-'.bin2hex(random_bytes(12)).'.zip';
                    if (move_uploaded_file($_FILES['package']['tmp_name'], package_dir().'/'.$fname)) {
                        set_setting('pkg_file_'.package_slug($slug), $fname);
                        if ($old !== '' && basename($old) !== $fname) {
                            @unlink($old);
                        }
                        $pdo->prepare('UPDATE products SET package_uploaded_at = NOW() WHERE slug = ?')->execute([$slug]);
                        $msg = 'Product + package saved.';
                    } else {
                        $msg = 'Product saved, but the package upload failed (check storage/packages permissions).';
                    }
                }
            }
        }
    } elseif ($action === 'delete' && ! empty($_POST['slug'])) {
        $old = package_path($_POST['slug']);
        $pdo->prepare('DELETE FROM products WHERE slug = ?')->execute([$_POST['slug']]);
        if ($old !== '') {
            @unlink($old);
        }
        set_setting('pkg_file_'.package_slug($_POST['slug']), '');
    }
    header('Location: products.php?msg='.urlencode($msg));
    exit;
}

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll();
$token = csrf_token();
lm_header('products', 'Products');
?>
<section class="card" id="product-form-card" style="margin-bottom:24px">
    <h3 class="card-title"><span>✨</span> <span id="form-heading">Add or update product</span></h3>
    <form method="post" action="products.php" class="grid" enctype="multipart/form-data" id="product-form">
        <input type="hidden" name="csrf" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="save">
        <label>Slug (immutable ID)<input name="slug" id="field-slug" placeholder="e.g. pharmacy" required></label>
        <label>Product Name<input name="name" id="field-name" placeholder="e.g. Pharmacy POS Module" required></label>
        <label>Price<input type="number" name="price" id="field-price" step="0.01" min="0" value="0.00"></label>
        <label>Currency<input name="currency" id="field-currency" value="USD" maxlength="3"></label>
        <label style="grid-column:1/-1">Description<input name="description" id="field-description" placeholder="Brief summary of module features"></label>
        <label>Module Package (.zip)<input type="file" name="package" accept=".zip"></label>
        <label class="row" style="padding-bottom:10px"><input type="checkbox" name="is_active" id="field-is-active" checked> <strong>Active</strong> (available for checkout &amp; catalog)</label>
        <div style="display:flex;gap:8px;align-items:center">
            <button type="submit" id="submit-btn">💾 Save product</button>
            <button type="button" class="outline-secondary" id="reset-btn" style="display:none" onclick="resetForm()">Cancel</button>
        </div>
    </form>
    <p class="muted" style="margin-top:16px;font-size:12px;line-height:1.6">
        💡 <b>Note:</b> Use slug <code>core</code> for the primary SaaS script (core requires no package ZIP). Module slugs must match SaaS module keys (e.g. <code>pharmacy</code>, <code>salon</code>, <code>repairtechnician</code>). The package ZIP is streamed only after valid license check.
    </p>
</section>

<section class="card">
    <h3 class="card-title"><span>📦</span> All Products (<?= count($products) ?>)</h3>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Slug</th><th>Name</th><th>Price</th><th>Status</th><th>Package ZIP</th><th>Description</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><code><?= e($p['slug']) ?></code></td>
                <td><strong><?= e($p['name']) ?></strong></td>
                <td><span class="tag" style="font-weight:700;color:#0f172a"><?= e(number_format((float) $p['price'], 2)).' '.e($p['currency']) ?></span></td>
                <td>
                    <?php if ($p['is_active']): ?>
                        <span class="status-pill active">● Active</span>
                    <?php else: ?>
                        <span class="status-pill revoked">● Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($p['slug'] === 'core'): ?>
                        <span class="muted">Core (no ZIP)</span>
                    <?php elseif (is_file(package_path($p['slug']))): ?>
                        <span class="status-pill active">📦 Uploaded</span>
                        <span class="muted" style="font-size:11px"><?= e(number_format(filesize(package_path($p['slug'])) / 1024, 0)) ?> KB</span>
                    <?php else: ?>
                        <span class="status-pill revoked">⚠️ Missing ZIP</span>
                    <?php endif; ?>
                </td>
                <td style="max-width:320px;line-height:1.4"><?= e($p['description'] ?: '—') ?></td>
                <td class="acts">
                    <button type="button" class="outline-secondary" onclick='editProduct(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>
                    <form method="post" action="products.php" onsubmit="return confirm('Delete product <?= e($p['slug']) ?>?')">
                        <input type="hidden" name="csrf" value="<?= e($token) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slug" value="<?= e($p['slug']) ?>">
                        <button type="submit" class="danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $products): ?><tr><td colspan="7" class="muted" style="text-align:center;padding:24px">No products created yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>

<script>
function editProduct(p) {
    document.getElementById("form-heading").innerText = "Edit Product: " + p.slug;
    document.getElementById("field-slug").value = p.slug;
    document.getElementById("field-name").value = p.name || "";
    document.getElementById("field-price").value = parseFloat(p.price || 0).toFixed(2);
    document.getElementById("field-currency").value = p.currency || "USD";
    document.getElementById("field-description").value = p.description || "";
    document.getElementById("field-is-active").checked = (p.is_active == 1);
    document.getElementById("submit-btn").innerText = "💾 Update product";
    document.getElementById("reset-btn").style.display = "inline-flex";
    
    var card = document.getElementById("product-form-card");
    if (card) {
        card.scrollIntoView({ behavior: "smooth" });
    }
}

function resetForm() {
    document.getElementById("product-form").reset();
    document.getElementById("form-heading").innerText = "Add or update product";
    document.getElementById("submit-btn").innerText = "💾 Save product";
    document.getElementById("reset-btn").style.display = "none";
}
</script>
<?php lm_footer();