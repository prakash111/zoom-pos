<?php

require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/_guard.php';
require __DIR__.'/_layout.php';
require_schema_web();

$rows = db()->query(
    'SELECT p.*, l.license_key, l.bound_domain
       FROM payments p LEFT JOIN licenses l ON l.id = p.license_id
      ORDER BY p.id DESC LIMIT 500'
)->fetchAll();

lm_header('payments', 'Payments');
?>
<section class="card">
    <h2><span>💳</span> Payment ledger (<?= count($rows) ?>)</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Reference</th><th>Gateway</th><th>Amount</th><th>Product</th><th>Domain</th><th>Email</th><th>Issued Key</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><code><?= e($r['reference']) ?></code></td>
                <td><span class="tag" style="text-transform:uppercase;font-size:10px"><?= e($r['gateway']) ?></span></td>
                <td><strong><?= e(number_format((float) $r['amount'], 2)).' '.e($r['currency']) ?></strong></td>
                <td><span class="tag blue"><?= e($r['product_slug']) ?></span></td>
                <td><?= $r['bound_domain'] ? '<strong>'.e($r['bound_domain']).'</strong>' : '<span class="muted">—</span>' ?></td>
                <td><?= $r['email'] ? e($r['email']) : '<span class="muted">—</span>' ?></td>
                <td>
                    <?php if (! empty($r['license_key'])): ?>
                        <div class="key-badge">
                            <span><?= e($r['license_key']) ?></span>
                            <button type="button" class="copy-btn" onclick="copyKey('<?= e($r['license_key']) ?>', this)" title="Copy key">📋</button>
                        </div>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $sc = ($r['status'] === 'paid' || $r['status'] === 'redeemed') ? 'green' : 'amber';
                    ?>
                    <span class="tag <?= $sc ?>">● <?= ucfirst(e($r['status'])) ?></span>
                </td>
                <td class="muted" style="font-size:12px"><?= e($r['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (! $rows): ?><tr><td colspan="9" class="muted" style="text-align:center;padding:24px">No payments recorded yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php lm_footer();