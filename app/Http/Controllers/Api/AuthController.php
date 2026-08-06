<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Subscription;
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
    /**
     * Création publique d'une organisation SDS-RH.
     *
     * Le prix et les dates d'abonnement sont calculés côté serveur.
     * Le frontend ne peut donc pas imposer un montant.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_type' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'in:XOF,EUR,USD'],
            'sector' => ['required', 'string', 'max:150'],
            'employee_count' => ['required', 'integer', 'min:1', 'max:200'],
            'plan' => ['required', 'string', 'in:free,starter,standard,business,enterprise'],
            'cycle' => ['required', 'string', 'in:monthly,annual'],
            'currency' => ['required', 'string', 'in:XOF,EUR,USD'],
            'payment' => ['nullable', 'string', 'in:fedapay,kkiapay,card,paypal,transfer'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:tenants,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cgu' => ['accepted'],
            'newsletter' => ['nullable', 'boolean'],
        ]);

        $plan = config("sds_rh.plans.{$data['plan']}");

        if (! $plan) {
            throw ValidationException::withMessages([
                'plan' => ['La formule sélectionnée est invalide.'],
            ]);
        }

        $paymentMethod = $data['plan'] === 'free' ? null : ($data['payment'] ?: 'fedapay');

        [$firstName, $lastName] = $this->splitFullName($data['full_name']);

        $price = $this->calculatePrice(
            $plan['price_xof_monthly'],
            $data['cycle'],
            $data['currency']
        );

        $isFree = $data['plan'] === 'free';
        $trialDays = (int) config('sds_rh.trial_days', 14);
        $startDate = now();
        $endDate = $isFree ? null : $startDate->copy()->addDays($trialDays);

        $result = DB::transaction(function () use (
            $data,
            $firstName,
            $lastName,
            $plan,
            $price,
            $paymentMethod,
            $startDate,
            $endDate,
            $isFree,
            $trialDays
        ) {
            $tenant = Tenant::create([
                'name' => $data['organization_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'subscription_plan' => $plan['db_name'],
                'subscription_expires_at' => $endDate,
                'is_active' => true,
                'settings' => [
                    'language' => 'fr',
                    'currency' => $data['currency'],
                    'timezone' => 'Africa/Porto-Novo',
                    'country_currency' => $data['country'],
                    'organization_type' => $data['organization_type'],
                    'sector' => $data['sector'],
                    'employee_count' => $data['employee_count'],
                    'marketing_newsletter' => (bool) ($data['newsletter'] ?? false),
                    'terms_accepted_at' => now()->toISOString(),
                    'registration' => [
                        'source' => 'web',
                        'trial_days' => $isFree ? 0 : $trialDays,
                    ],
                ],
            ]);

            app()->instance('tenant', $tenant);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'],
                'status' => 'active',
            ]);

            $user->assignRole('admin_org');

            $employee = Employee::create([
                'user_id' => $user->id,
                'employee_number' => 'EMP-' . str_pad((string) $tenant->id, 5, '0', STR_PAD_LEFT) . '-0001',
                'hire_date' => $startDate->toDateString(),
                'status' => 'active',
            ]);

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan' => $plan['db_name'],
                'price' => $price,
                'currency' => $data['currency'],
                'billing_cycle' => $data['cycle'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'active',
                'features' => [
                    'employee_limit_min' => $plan['min'],
                    'employee_limit_max' => $plan['max'] === PHP_INT_MAX ? null : $plan['max'],
                    'employee_count_at_signup' => $data['employee_count'],
                    'custom_quote' => $plan['custom_quote'],
                    'trial' => ! $isFree,
                    'trial_days' => $isFree ? 0 : $trialDays,
                ],
                'payment_reference' => null,
                'payment_method' => $paymentMethod,
            ]);

            return [
                'tenant' => $tenant,
                'user' => $user,
                'employee' => $employee,
            ];
        });

        $user = $result['user']->load('employee');
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Organisation créée avec succès',
            'tenant' => $result['tenant']->fresh(),
            'user' => $user,
            'subscription' => $result['tenant']->subscriptions()->latest()->first(),
            'access_token' => $token,
            'token_type' => 'Bearer',
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ], 201);
    }

    private function splitFullName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2, PREG_SPLIT_NO_EMPTY);

        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? $firstName;

        return [$firstName, $lastName];
    }

    private function calculatePrice(?int $monthlyPriceXof, string $cycle, string $currency): float
    {
        if ($monthlyPriceXof === null) {

            return 0;
        }

        $amountXof = $cycle === 'annual'
            ? $monthlyPriceXof * 10
            : $monthlyPriceXof;

        $rate = (float) config("sds_rh.currencies.{$currency}.rate_from_xof", 1);
        $decimals = (int) config("sds_rh.currencies.{$currency}.decimals", 2);

        $amount = $amountXof * $rate;

        if ($currency === 'XOF') {
            return (float) (round($amount / 100) * 100);
        }

        return round($amount, $decimals);
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

            return response()->json(['message' => 'Votre compte est désactivé'], 403);
        }

        $tenant = $user->tenant;

        if (! $tenant || ! $tenant->is_active) {
            Auth::logout();

            return response()->json(['message' => 'Votre organisation est inactive'], 403);
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
            return response()->json(['message' => 'Le mot de passe actuel est incorrect'], 422);
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        return response()->json(['message' => 'Mot de passe modifié avec succès']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink(['email' => $data['email']]);

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

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
