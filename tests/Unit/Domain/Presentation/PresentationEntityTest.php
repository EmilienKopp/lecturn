<?php

declare(strict_types=1);

use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\Exceptions\InvalidPresentationContent;
use App\Domain\Presentation\ValueObjects\PresentationContent;

function makePresentationEntity(): PresentationEntity
{
    return new PresentationEntity(
        team_id: 1,
        name: 'My deck',
        content: PresentationContent::empty(),
    );
}

it('renames the presentation', function () {
    $entity = makePresentationEntity();

    $entity->rename('New name');

    expect($entity->name)->toBe('New name');
});

it('rejects an empty name', function () {
    makePresentationEntity()->rename('   ');
})->throws(InvalidPresentationContent::class);

it('rejects a name longer than 255 characters', function () {
    makePresentationEntity()->rename(str_repeat('a', 256));
})->throws(InvalidPresentationContent::class);

it('replaces content', function () {
    $entity = makePresentationEntity();
    $replacement = PresentationContent::fromArray([
        'version' => '1.0',
        'slides' => [
            ['id' => 'slide-9', 'layout' => 'full', 'background' => null, 'slots' => []],
        ],
    ]);

    $entity->replaceContent($replacement);

    expect($entity->content->slides[0]->id)->toBe('slide-9');
});
