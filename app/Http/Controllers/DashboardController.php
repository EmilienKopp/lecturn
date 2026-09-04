<?php

namespace App\Http\Controllers;

use App\Infrastructure\ReadModels\DashboardReadModel;
use App\Infrastructure\ReadModels\PresentationReadModel;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardReadModel $dashboard,
        private readonly PresentationReadModel $presentations,
    ) {}

    public function __invoke(Request $request, Team $current_team): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'engagement' => $this->dashboard->teamEngagementSummary($current_team->id),
            'recentSessions' => $this->dashboard->recentSessionsForTeam($current_team->id),
            'recentDecks' => array_slice($this->presentations->listForTeam($current_team->id), 0, 5),
        ]);
    }
}
