<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class SaleBuilder extends Builder
{
    /**
     * Add a basic where clause to the query, transparently mapping 'tenant_id' to 'company_id'.
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            if ($column === 'tenant_id') {
                $column = $this->getModel()->getTable() . '.company_id';
            } elseif (str_ends_with($column, '.tenant_id')) {
                $column = str_replace('.tenant_id', '.company_id', $column);
            } elseif ($column === 'balance_due') {
                $column = $this->getModel()->getTable() . '.due_amount';
            } elseif (str_ends_with($column, '.balance_due')) {
                $column = str_replace('.balance_due', '.due_amount', $column);
            }
        } elseif (is_array($column)) {
            $remapped = [];
            foreach ($column as $k => $v) {
                if ($k === 'tenant_id') {
                    $remapped[$this->getModel()->getTable() . '.company_id'] = $v;
                } elseif (is_string($k) && str_ends_with($k, '.tenant_id')) {
                    $remapped[str_replace('.tenant_id', '.company_id', $k)] = $v;
                } elseif ($k === 'balance_due') {
                    $remapped[$this->getModel()->getTable() . '.due_amount'] = $v;
                } elseif (is_string($k) && str_ends_with($k, '.balance_due')) {
                    $remapped[str_replace('.balance_due', '.due_amount', $k)] = $v;
                } else {
                    $remapped[$k] = $v;
                }
            }
            $column = $remapped;
        }

        return parent::where($column, $operator, $value, $boolean);
    }
}
