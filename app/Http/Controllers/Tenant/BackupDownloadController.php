<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\OrderPayment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesTarget;
use App\Models\Supplier;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\Auth\PermissionChecker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDownloadController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $user = auth('web')->user();
        if (! $user || ! PermissionChecker::can($user, 'settings')) {
            abort(403, 'Unauthorized to export system backups.');
        }

        $companyId = $user->company_id;
        $company = Company::find($companyId);
        $companySlug = $company ? $company->slug : 'tenant';
        $filename = 'backup_'.$companySlug.'_'.now()->format('Y-m-d_His').'.json';

        AuditLog::record('tenant.backup_downloaded', $companyId, $user->id, [
            'filename' => $filename,
            'ip' => $request->ip(),
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($companyId, $company) {
            $data = [
                'metadata' => [
                    'version' => '2.0',
                    'exported_at' => now()->toIso8601String(),
                    'company' => $company ? $company->toArray() : null,
                ],
                'users' => User::where('company_id', $companyId)->get()->makeHidden(['password', 'remember_token'])->toArray(),
                'categories' => Category::where('company_id', $companyId)->get()->toArray(),
                'products' => Product::where('company_id', $companyId)->get()->toArray(),
                'customers' => Customer::where('company_id', $companyId)->get()->toArray(),
                'suppliers' => Supplier::where('company_id', $companyId)->get()->toArray(),
                'sales' => Sale::where('company_id', $companyId)->get()->toArray(),
                'order_payments' => OrderPayment::where('company_id', $companyId)->get()->toArray(),
                'sales_targets' => SalesTarget::where('company_id', $companyId)->get()->toArray(),
                'consignments' => Consignment::where('company_id', $companyId)->with('items')->get()->toArray(),
                'cash_registers' => CashRegister::where('company_id', $companyId)->get()->toArray(),
                'tax_rules' => TaxRule::where('company_id', $companyId)->get()->toArray(),
            ];

            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, 200, $headers);
    }
}
