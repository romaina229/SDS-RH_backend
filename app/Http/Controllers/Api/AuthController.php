<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'tenant_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
        ]);

        $result = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'subscription_plan' => 'gratuit',
                'subscription_expires_at' => now()->addMonths(6),
                'settings' => [
                    'language' => 'fr',
                    'currency' => 'XOF',
                    'timezone' => 'Africa/Porto-Novo',
                    'country' => 'BJ',
                ],
            ]);

            app()->instance('tenant', $tenant);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);

            $user->assignRole('admin_org');

            Employee::create([
                'user_id' => $user->id,
                'employee_number' => 'EMP-' . str_pad($tenant->id, 5, '0', STR_PAD_LEFT) . '-0001',
                'hire_date' => now()->toDateString(),
                'status' => 'active',
            ]);

            return compact('tenant', 'user');
        });

        $user = $result['user'];
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Organisation créée avec succès',
            'tenant' => $result['tenant'],
            'user' => $user->load('employee'),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        if ($user->status !== 'active') {
            Auth::logout();

            return response()->json([
                'message' => 'Votre compte est désactivé',
            ], 403);
        }

        $tenant = $user->tenant;

        if (! $tenant || ! $tenant->is_active) {
            Auth::logout();

            return response()->json([
                'message' => 'Votre organisation est inactive',
            ], 403);
        }

        if (
            $tenant->subscription_expires_at
            && $tenant->subscription_expires_at->isPast()
            && $tenant->subscription_plan !== 'enterprise'
        ) {
            Auth::logout();

            return response()->json([
                'message' => 'L\'abonnement de votre organisation est expiré',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'user' => $user->load('employee'),
            'tenant' => $tenant,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load('tenant', 'employee.department', 'employee.position');

        return response()->json([
            'user' => $user,
            'tenant' => $user->tenant,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    public function changePassword(Request $request)
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

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.',
            ]);
        }

        // Avoid account enumeration.
        return response()->json([
            'message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => null,
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Le lien de réinitialisation est invalide ou expiré.',
            ], 422);
        }

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }
}
