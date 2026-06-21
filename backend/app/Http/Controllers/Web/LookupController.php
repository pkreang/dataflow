<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\LookupRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userPerms = session('user_permissions', []);
        $isSuperAdmin = (bool) session('user.is_super_admin', false);
        $accessibleKeys = array_keys(LookupRegistry::accessibleSources($userPerms, $isSuperAdmin));

        $validated = $request->validate([
            'source' => 'required|string|in:'.implode(',', $accessibleKeys),
            'filters' => 'nullable|array',
            'filters.*' => 'nullable|string|max:100',
        ]);

        $items = LookupRegistry::getItems(
            $validated['source'],
            $validated['filters'] ?? null
        );

        return response()->json(['data' => $items->values()]);
    }
}
