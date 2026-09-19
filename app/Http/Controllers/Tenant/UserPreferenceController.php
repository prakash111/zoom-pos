<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    /**
     * Positions the dockable navigation menu understands (see
     * resources/js/dockable-nav.js). The task spec lists left/right/bottom;
     * the live component also supports top and floating, so all five are
     * accepted here — restricting them would silently drop a valid drop.
     */
    public const DOCK_POSITIONS = ['left', 'right', 'top', 'bottom', 'floating'];

    /**
     * POST /tenant/preferences/dock-position
     *
     * Persists the signed-in user's chosen dock position so a page reload on
     * any device restores it. Called asynchronously by the dock's drop
     * handler; the in-page state is still driven by localStorage for
     * flicker-free first paint.
     */
    public function updateDockPosition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dock_position' => ['required', 'string', Rule::in(self::DOCK_POSITIONS)],
        ]);

        $user = $request->user();
        $user->forceFill(['dock_position' => $data['dock_position']])->save();

        // Mirror into the session so a follow-up request in the same tab has
        // the value even before the model is re-read.
        $request->session()->put('dock_position', $data['dock_position']);

        return response()->json([
            'status' => 'success',
            'dock_position' => $user->dock_position,
        ]);
    }
}
