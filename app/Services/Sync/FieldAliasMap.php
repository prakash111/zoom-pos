<?php

namespace App\Services\Sync;

/**
 * Bilingual PT/EN field emission for the legacy sync-compat wire contract
 * (e.g. a product row round-trips with both "nome" and "name" populated
 * simultaneously). Empty per-table in Milestone 1 — populated as each
 * module's real Eloquent mapping lands in config/sync_tables.php, so the
 * KvStore-fallback path (raw passthrough of whatever the client sent) is
 * correct by construction until then.
 */
class FieldAliasMap
{
    /** @var array<string, array<string, string>> table => [english_field => legacy_field] */
    protected array $map = [
        'produtos' => [
            'name' => 'nome', 'code' => 'codigo', 'barcode' => 'codigo_barras',
            'cost_price' => 'preco_custo', 'sale_price' => 'preco_venda',
            'current_stock' => 'estoque_atual', 'minimum_stock' => 'estoque_minimo',
            'category_name' => 'categoria', 'brand_name' => 'marca', 'unit' => 'unidade',
            'active' => 'ativo',
        ],
        'clientes' => [
            'name' => 'nome', 'document' => 'documento', 'email' => 'email', 'phone' => 'telefone',
            'address' => 'endereco', 'city' => 'cidade', 'state' => 'estado', 'loyalty_points' => 'pontos_fidelidade',
        ],
        'fornecedores' => [
            'name' => 'nome', 'legal_name' => 'razao_social', 'tax_id' => 'documento',
            'email' => 'email', 'phone' => 'telefone', 'city' => 'cidade', 'state' => 'estado', 'active' => 'ativo',
        ],
        'categorias' => [
            'name' => 'nome', 'color' => 'cor', 'description' => 'descricao', 'active' => 'ativo',
        ],
        'vendas' => [
            'sale_number' => 'numero_venda', 'customer_name' => 'cliente_nome', 'total' => 'total',
            'discount' => 'desconto', 'payment_method' => 'forma_pagamento', 'status' => 'status', 'items' => 'itens',
        ],
    ];

    public function expandForRead(string $table, array $row): array
    {
        foreach ($this->map[$table] ?? [] as $english => $legacy) {
            if (array_key_exists($english, $row) && ! array_key_exists($legacy, $row)) {
                $row[$legacy] = $row[$english];
            }
        }

        return $row;
    }

    public function normalizeForWrite(string $table, array $row): array
    {
        foreach ($this->map[$table] ?? [] as $english => $legacy) {
            if (! array_key_exists($english, $row) && array_key_exists($legacy, $row)) {
                $row[$english] = $row[$legacy];
            }
        }

        return $row;
    }
}
