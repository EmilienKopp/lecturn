<?php

namespace App\Presentation\Contracts;

interface Presenter
{
    public function present(mixed $data): mixed;
}
