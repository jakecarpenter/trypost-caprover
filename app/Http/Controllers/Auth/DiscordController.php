<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\SocialAccount\Platform as SocialPlatform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DiscordController extends SocialController
{
    protected string $driver = 'discord';

    protected SocialPlatform $platform = SocialPlatform::Discord;

    public function connect(Request $request): Response|RedirectResponse
    {
        $this->ensurePlatformEnabled();

        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('manageAccounts', $workspace);

        return $this->redirectToProvider($request, $this->driver, config('trypost.platforms.discord.scopes'));
    }

    public function callback(Request $request): View
    {
        return $this->handleCallback($request, $this->platform, $this->driver);
    }
}
