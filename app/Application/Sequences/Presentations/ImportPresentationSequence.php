<?php

declare(strict_types=1);

namespace App\Application\Sequences\Presentations;

use App\Application\Sequences\Presentations\Steps\CreatePresentationStep;
use App\Application\Sequences\Presentations\Steps\RehostImagesStep;
use Splitstack\Conveyor\Infrastructure\Transaction\Transactioner;
use Splitstack\Conveyor\Sequence;

/**
 * Imports a presentation from decoded JSON: persist the deck, then re-host its
 * remote images. Runs without a transaction so the slow image downloads don't
 * hold DB locks; on failure the create step reverses its committed row by hand.
 */
class ImportPresentationSequence extends Sequence
{
    public function __construct(
        Transactioner $transactioner,
        private readonly CreatePresentationStep $createPresentation,
        private readonly RehostImagesStep $rehostImages,
    ) {
        parent::__construct($transactioner);
    }

    public function import(ImportPresentationPayload $payload): ImportPresentationPayload
    {
        return $this
            ->dontTransact()
            ->steps([$this->createPresentation, $this->rehostImages])
            ->run($payload);
    }
}
