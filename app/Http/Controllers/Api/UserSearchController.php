<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q      = trim($request->query('q', ''));
        $roles  = (array) $request->query('roles', ['abogado','gestor']);

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $users = User::query()
            ->whereIn('tipo_usuario', $roles)
            ->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                  ->orWhere('email', 'ilike', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get([
                'id',
                'name as nombre',   // <- el AsyncSelect usa "nombre"
                'email',
            ]);

        return response()->json($users);
    }
}
