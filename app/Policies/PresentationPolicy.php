<?php

namespace App\Policies;

use App\Models\PresentationModel;
use App\Models\Team;
use App\Models\User;

class PresentationPolicy
{
    /**
     * Determine whether the user can create presentations in the team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the presentation.
     */
    public function view(User $user, PresentationModel $presentation): bool
    {
        return $user->belongsToTeam($presentation->team);
    }

    /**
     * Determine whether the user can update the presentation.
     */
    public function update(User $user, PresentationModel $presentation): bool
    {
        return $user->belongsToTeam($presentation->team);
    }

    /**
     * Determine whether the user can delete the presentation.
     */
    public function delete(User $user, PresentationModel $presentation): bool
    {
        return $user->belongsToTeam($presentation->team);
    }
}
