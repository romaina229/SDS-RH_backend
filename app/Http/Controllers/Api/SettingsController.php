<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        $tenant = app('tenant');

        return response()->json([
            'tenant' => $tenant,
            'settings' => $tenant->settings ?? [],
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenants,email,' . $tenant->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'settings' => 'nullable|array',
            'settings.language' => 'nullable|in:fr,en',
            'settings.currency' => 'nullable|in:XOF,EUR,USD',
            'settings.timezone' => 'nullable|string',
            'settings.country' => 'nullable|string|size:2',
        ]);

        $tenant->update($data);

        return response()->json([
            'message' => 'Paramètres mis à jour avec succès',
            'tenant' => $tenant->fresh(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect',
            ], 422);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        return response()->json(['message' => 'Mot de passe modifié avec succès']);
    }
}
