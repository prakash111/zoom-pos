<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

if (class_exists(\Modules\Chat\Http\Controllers\PromotionalBroadcastController::class)) {
    class PromotionalBroadcastController extends \Modules\Chat\Http\Controllers\PromotionalBroadcastController
    {
    }
} else {
    class PromotionalBroadcastController extends Controller
    {
        public function index(Request $request)
        {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'broadcasts' => [],
                    'items' => [],
                ]);
            }

            return redirect()->route('superadmin.modules.index')
                ->with('warning', __('The Chat & Promotional Broadcasts module is not installed.'));
        }

        public function store(Request $request)
        {
            return redirect()->route('superadmin.modules.index')
                ->with('warning', __('The Chat & Promotional Broadcasts module is not installed.'));
        }

        public function toggleActive($id)
        {
            return redirect()->route('superadmin.modules.index')
                ->with('warning', __('The Chat & Promotional Broadcasts module is not installed.'));
        }

        public function destroy($id)
        {
            return redirect()->route('superadmin.modules.index')
                ->with('warning', __('The Chat & Promotional Broadcasts module is not installed.'));
        }
    }
}
