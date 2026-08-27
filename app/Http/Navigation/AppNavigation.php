<?php

namespace App\Http\Navigation;

use App\Models\User;
use Spatie\Navigation\Navigation;
use Spatie\Navigation\Section;

class AppNavigation
{
    /**
     * Build the sidebar navigation tree for the given user.
     *
     * @return array<int, array{url: string, title: string, active: bool, attributes: array<string, string>, children: array<int, mixed>, depth: int}>
     */
    public function tree(?User $user): array
    {
        $team = $user?->currentTeam;

        return Navigation::make()
            ->addIf($team !== null, 'Platform', '', function (Section $section) use ($team) {
                $section
                    ->add('Dashboard', route('dashboard', $team->slug), attributes: ['icon' => 'layout-grid'])
                    ->add('Presentations', route('presentations.index', $team->slug), attributes: ['icon' => 'presentation']);
            })
            ->addIf($user !== null, 'Settings', '', function (Section $section) {
                $section
                    ->add('Profile', route('profile.edit'), attributes: ['icon' => 'user'])
                    ->add('Teams', route('teams.index'), attributes: ['icon' => 'users']);
            })
            ->tree();
    }
}
