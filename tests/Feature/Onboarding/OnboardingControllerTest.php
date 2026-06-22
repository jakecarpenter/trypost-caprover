<?php

declare(strict_types=1);

use App\Actions\Billing\StartSubscriptionCheckout;
use App\Enums\Plan\Slug;
use App\Enums\User\Persona;
use App\Jobs\PostHog\SendEvent;
use App\Models\Account;
use App\Models\Plan;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    config(['trypost.self_hosted' => false]);
    $this->user = User::factory()->create();
});

/**
 * Give the acting user a current workspace under their account.
 */
function onboardingWorkspace(User $user): Workspace
{
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);

    $user->update(['current_workspace_id' => $workspace->id]);

    return $workspace;
}

function subscribeOnboardingAccount(Account $account): void
{
    $account->subscriptions()->create([
        'type' => Account::SUBSCRIPTION_NAME,
        'stripe_id' => 'sub_'.fake()->uuid(),
        'stripe_status' => 'active',
        'stripe_price' => 'price_123',
    ]);
}

test('onboarding renders the persona selection for an unsubscribed account', function () {
    $response = $this->actingAs($this->user)->get(route('app.onboarding'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Index')
        ->has('personas', count(Persona::cases()))
    );
});

test('onboarding redirects to calendar in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding'));

    $response->assertRedirect(route('app.calendar'));
});

test('onboarding redirects to calendar when already subscribed', function () {
    subscribeOnboardingAccount($this->user->account);

    $response = $this->actingAs($this->user->fresh())->get(route('app.onboarding'));

    $response->assertRedirect(route('app.calendar'));
});

test('onboarding store rejects an invalid persona', function () {
    $response = $this->actingAs($this->user)->post(route('app.onboarding.store'), [
        'persona' => 'not-a-persona',
    ]);

    $response->assertSessionHasErrors('persona');
    expect($this->user->fresh()->persona)->toBeNull();
});

test('onboarding store requires a persona', function () {
    $response = $this->actingAs($this->user)->post(route('app.onboarding.store'), []);

    $response->assertSessionHasErrors('persona');
});

test('onboarding store does nothing in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->post(route('app.onboarding.store'), [
        'persona' => Persona::Agency->value,
    ]);

    $response->assertRedirect(route('app.calendar'));
    expect($this->user->fresh()->persona)->toBeNull();
});

test('onboarding store saves the persona, mirrors to PostHog and advances to the connect step', function () {
    config(['services.posthog.enabled' => true, 'services.posthog.api_key' => 'phc_test']);
    Bus::fake();

    $response = $this->actingAs($this->user)->post(route('app.onboarding.store'), [
        'persona' => Persona::Agency->value,
    ]);

    $response->assertRedirect(route('app.onboarding.connect'));
    expect($this->user->fresh()->persona)->toBe(Persona::Agency);

    Bus::assertDispatched(SendEvent::class);
});

test('onboarding store redirects an already-subscribed account to the calendar', function () {
    subscribeOnboardingAccount($this->user->account);

    $response = $this->actingAs($this->user->fresh())->post(route('app.onboarding.store'), [
        'persona' => Persona::Agency->value,
    ]);

    $response->assertRedirect(route('app.calendar'));
    expect($this->user->fresh()->persona)->toBeNull();
});

test('connect renders the network grid for an unsubscribed account that picked a persona', function () {
    $this->user->update(['persona' => Persona::Agency->value]);
    onboardingWorkspace($this->user);

    $response = $this->actingAs($this->user->fresh())->get(route('app.onboarding.connect'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Connect')
        ->has('platforms')
        ->has('platforms.0.network')
        ->has('accounts')
    );
});

test('connect lists the workspace social accounts already connected', function () {
    $this->user->update(['persona' => Persona::Agency->value]);
    $workspace = onboardingWorkspace($this->user);
    SocialAccount::factory()->create(['workspace_id' => $workspace->id]);

    $response = $this->actingAs($this->user->fresh())->get(route('app.onboarding.connect'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('onboarding/Connect')
        ->has('accounts', 1)
    );
});

test('connect redirects back to the persona step when no persona was chosen', function () {
    onboardingWorkspace($this->user);

    $response = $this->actingAs($this->user->fresh())->get(route('app.onboarding.connect'));

    $response->assertRedirect(route('app.onboarding'));
});

test('connect redirects to calendar in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $response = $this->actingAs($this->user)->get(route('app.onboarding.connect'));

    $response->assertRedirect(route('app.calendar'));
});

test('connect redirects to calendar when already subscribed', function () {
    subscribeOnboardingAccount($this->user->account);

    $response = $this->actingAs($this->user->fresh())->get(route('app.onboarding.connect'));

    $response->assertRedirect(route('app.calendar'));
});

test('checkout blocks and redirects back when no network is connected', function () {
    $this->user->update(['persona' => Persona::Agency->value]);
    onboardingWorkspace($this->user);

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->never();

    $response = $this->actingAs($this->user->fresh())->post(route('app.onboarding.checkout'));

    $response->assertRedirect(route('app.onboarding.connect'));
});

test('checkout starts monthly checkout once at least one network is connected', function () {
    $this->user->update(['persona' => Persona::Agency->value]);
    $workspace = onboardingWorkspace($this->user);
    SocialAccount::factory()->create(['workspace_id' => $workspace->id]);

    Plan::where('slug', Slug::Workspace)->firstOrFail()->update([
        'stripe_monthly_price_id' => 'price_monthly_test',
        'stripe_yearly_price_id' => 'price_yearly_test',
    ]);

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->once()
        ->withArgs(fn (Account $account, string $priceId, string $cancelUrl): bool => $priceId === 'price_monthly_test')
        ->andReturn(redirect()->route('app.calendar'));

    $response = $this->actingAs($this->user->fresh())->post(route('app.onboarding.checkout'));

    $response->assertRedirect(route('app.calendar'));
});

test('checkout redirects an already-subscribed account to the calendar', function () {
    subscribeOnboardingAccount($this->user->account);

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->never();

    $response = $this->actingAs($this->user->fresh())->post(route('app.onboarding.checkout'));

    $response->assertRedirect(route('app.calendar'));
});

test('checkout does nothing in self-hosted mode', function () {
    config(['trypost.self_hosted' => true]);

    $this->mock(StartSubscriptionCheckout::class)
        ->shouldReceive('redirect')
        ->never();

    $response = $this->actingAs($this->user)->post(route('app.onboarding.checkout'));

    $response->assertRedirect(route('app.calendar'));
});
