<?php

namespace App\Models;

/**
 * Domain-neutral name for the existing FEFO batch ledger. Keeping one table
 * avoids splitting pharmacy stock between product_batches/pharmacy_batches.
 */
class ProductBatch extends PharmacyBatch {}
