<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\Plan\Slug;
use App\Enums\SocialAccount\Platform as SocialPlatform;
use App\Enums\User\Persona;
use App\Http\Requests\App\Onboarding\StoreOnboardingRequest;
use App\Http\Resources\App\SocialAccountResource;
use App\Models\Account;
use App\Models\Plan;
use App\Services\PostHogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class OnboardingController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $user = $request->user();

        if ($user->account?->subscribed(Account::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        return Inertia::render('onboarding/Index', [
            'personas' => array_map(fn (Persona $persona): string => $persona->value, Persona::cases()),
            'selected' => $user->persona?->value,
        ]);
    }

    public function store(StoreOnboardingRequest $request, PostHogService $postHog): RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $user = $request->user();

        if ($user->account?->subscribed(Account::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        $persona = (string) $request->validated('persona');

        $user->update(['persona' => $persona]);

        $postHog->identify($user->id, [
            'persona' => $persona,
        ]);

        return redirect()->route('app.onboarding.connect');
    }

    public function connect(Request $request): Response|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $user = $request->user();

        if ($user->account?->subscribed(Account::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        if (! $user->persona) {
            return redirect()->route('app.onboarding');
        }

        $workspace = $user->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $accounts = $workspace->socialAccounts()->orderBy('id')->get();

        $platforms = collect(SocialPlatform::enabled())->map(fn (SocialPlatform $platform): array => [
            'value' => $platform->value,
            'label' => $platform->label(),
            'color' => $platform->color(),
            'network' => $platform->network(),
        ])->values();

        return Inertia::render('onboarding/Connect', [
            'platforms' => $platforms,
            'accounts' => SocialAccountResource::collection($accounts)->resolve(),
        ]);
    }

    public function checkout(Request $request, StartSubscriptionCheckout $checkout): SymfonyResponse|RedirectResponse
    {
        if (config('trypost.self_hosted')) {
            return redirect()->route('app.calendar');
        }

        $user = $request->user();
        $account = $user->account;

        if ($account?->subscribed(Account::SUBSCRIPTION_NAME)) {
            return redirect()->route('app.calendar');
        }

        $workspace = $user->currentWorkspace;

        if (! $workspace || ! $workspace->socialAccounts()->exists()) {
            return redirect()->route('app.onboarding.connect')
                ->with('flash.banner', __('onboarding.connect.must_connect'))
                ->with('flash.bannerStyle', 'danger');
        }

        $plan = Plan::where('slug', Slug::Workspace)->firstOrFail();

        return $checkout->redirect(
            $account,
            (string) $plan->stripe_monthly_price_id,
            route('app.onboarding.connect'),
        );
    }
}
