<?php

declare(strict_types=1);

use App\Application\Sequences\Presentations\ImportPresentationPayload;
use App\Application\Sequences\Presentations\Steps\CreatePresentationStep;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Models\PresentationModel;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Splitstack\Conveyor\Concerns\IsSteppable;
use Splitstack\Conveyor\Contracts\Steppable;
use Splitstack\Conveyor\Infrastructure\Transaction\Transactioner;
use Splitstack\Conveyor\Sequence;

/**
 * A 1x1 transparent PNG, so Media Library detects a real image mime type.
 */
function tinyPng(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
}

/**
 * @param  array<string, mixed>  $imageBlock
 * @return array<string, mixed>
 */
function presentationWithImage(array $imageBlock): array
{
    $payload = importablePresentation();
    $payload['content']['slides'][0]['slots']['main'][] = $imageBlock;

    return $payload;
}

/**
 * A trimmed version of the exported presentation JSON: content + talk
 * settings + a flow that references the two slides by id.
 *
 * @return array<string, mixed>
 */
function importablePresentation(): array
{
    return [
        'id' => '2',
        'name' => 'Tecturn Show and Tell',
        'embed_token' => 'KS3SCJUwSUbkZqykO4DjubOdcRBBcA',
        'content' => [
            'version' => '1.0',
            'slides' => [
                [
                    'id' => 'slide-a',
                    'layout' => 'free',
                    'background' => '#000000',
                    'slots' => ['main' => []],
                    'title' => 'Ception',
                ],
                [
                    'id' => 'slide-b',
                    'layout' => 'free',
                    'background' => '#000000',
                    'slots' => ['main' => []],
                    'title' => 'Slide',
                ],
            ],
        ],
        'talk_settings' => [
            'showReactions' => true,
            'showDock' => true,
            'timerMode' => 'elapsed',
            'autoSave' => true,
        ],
        'flow' => [
            'version' => '1.0',
            'nodes' => [
                ['id' => 'node-a', 'type' => 'slide', 'position' => ['x' => 0, 'y' => 0], 'data' => ['slideId' => 'slide-a']],
                ['id' => 'node-b', 'type' => 'slide', 'position' => ['x' => 0, 'y' => 100], 'data' => ['slideId' => 'slide-b']],
            ],
            'edges' => [],
        ],
    ];
}

function jsonUpload(array $payload): UploadedFile
{
    return UploadedFile::fake()->createWithContent('deck.json', json_encode($payload));
}

test('a presentation can be imported from a JSON file as a fresh clone', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $team->slug]), [
            'file' => jsonUpload(importablePresentation()),
        ]);

    $presentation = PresentationModel::query()->firstOrFail();

    $response->assertRedirect(route('presentations.edit', [
        'current_team' => $team->slug,
        'presentation' => $presentation->id,
    ]));

    expect($presentation->team_id)->toBe($team->id)
        ->and($presentation->name)->toBe('Tecturn Show and Tell')
        ->and($presentation->content['slides'])->toHaveCount(2)
        ->and($presentation->talk_settings['showReactions'])->toBeTrue()
        ->and($presentation->talk_settings['autoSave'])->toBeTrue()
        ->and($presentation->flow['nodes'])->toHaveCount(2)
        // Identity of the source is discarded: a fresh embed token is minted.
        ->and($presentation->embed_token)->not->toBe('KS3SCJUwSUbkZqykO4DjubOdcRBBcA');
});

test('a presentation can be imported from pasted JSON', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $team->slug]), [
            'json' => json_encode(importablePresentation()),
        ]);

    $presentation = PresentationModel::query()->firstOrFail();

    $response->assertRedirect(route('presentations.edit', [
        'current_team' => $team->slug,
        'presentation' => $presentation->id,
    ]));

    expect($presentation->name)->toBe('Tecturn Show and Tell')
        ->and($presentation->content['slides'])->toHaveCount(2);
});

test('pasted JSON errors land on the json field', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $user->currentTeam->slug]), [
            'json' => 'not json at all',
        ]);

    $response->assertSessionHasErrors('json');
    $this->assertDatabaseCount('presentations', 0);
});

test('importing with neither a file nor pasted JSON fails validation', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $user->currentTeam->slug]), []);

    $response->assertSessionHasErrors('json');
    $this->assertDatabaseCount('presentations', 0);
});

test('importing invalid JSON fails validation', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $user->currentTeam->slug]), [
            'file' => UploadedFile::fake()->createWithContent('deck.json', 'not json at all'),
        ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('presentations', 0);
});

test('importing content with an unknown layout fails validation', function () {
    $user = User::factory()->create();

    $payload = importablePresentation();
    $payload['content']['slides'][0]['layout'] = 'diagonal';

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $user->currentTeam->slug]), [
            'file' => jsonUpload($payload),
        ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('presentations', 0);
});

test('importing a flow that references an unknown slide fails validation', function () {
    $user = User::factory()->create();

    $payload = importablePresentation();
    $payload['flow']['nodes'][0]['data']['slideId'] = 'slide-does-not-exist';

    $response = $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $user->currentTeam->slug]), [
            'file' => jsonUpload($payload),
        ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('presentations', 0);
});

test('remote images are re-hosted and their URLs rebound', function () {
    Storage::fake('public');
    Http::fake([
        'cdn.example.com/*' => Http::response(tinyPng(), 200, ['Content-Type' => 'image/png']),
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $remoteUrl = 'https://cdn.example.com/deck/photo.png';

    $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $team->slug]), [
            'json' => json_encode(presentationWithImage([
                'id' => 'block-img',
                'type' => 'image',
                'content' => '',
                'style' => ['x' => '0', 'y' => '0', 'width' => '50'],
                'src' => $remoteUrl,
            ])),
        ])
        ->assertRedirect();

    $presentation = PresentationModel::query()->firstOrFail();
    $storedSrc = $presentation->content['slides'][0]['slots']['main'][0]['src'];

    expect($storedSrc)->not->toBe($remoteUrl)
        ->and($storedSrc)->toContain('/storage/')
        ->and($presentation->getMedia(PresentationModel::IMAGES_COLLECTION))->toHaveCount(1);

    Http::assertSent(fn (HttpRequest $request) => $request->url() === $remoteUrl);
});

test('an unreachable image keeps its original URL and the import still succeeds', function () {
    Storage::fake('public');
    Http::fake([
        'cdn.example.com/*' => Http::response('nope', 404),
    ]);

    $user = User::factory()->create();
    $team = $user->currentTeam;

    $remoteUrl = 'https://cdn.example.com/deck/missing.png';

    $this
        ->actingAs($user)
        ->post(route('presentations.importJson', ['current_team' => $team->slug]), [
            'json' => json_encode(presentationWithImage([
                'id' => 'block-img',
                'type' => 'image',
                'content' => '',
                'style' => ['x' => '0', 'y' => '0', 'width' => '50'],
                'src' => $remoteUrl,
            ])),
        ])
        ->assertRedirect();

    $presentation = PresentationModel::query()->firstOrFail();

    expect($presentation->content['slides'][0]['slots']['main'][0]['src'])->toBe($remoteUrl)
        ->and($presentation->getMedia(PresentationModel::IMAGES_COLLECTION))->toHaveCount(0);
});

test('a failure after creation compensates the committed presentation row', function () {
    $user = User::factory()->create();

    $repository = app(PresentationRepository::class);

    $explodingStep = new class implements Steppable
    {
        use IsSteppable;

        public function handle(ImportPresentationPayload $payload): void
        {
            throw new RuntimeException('boom');
        }
    };

    $sequence = (new Sequence(new Transactioner))
        ->dontTransact()
        ->steps([new CreatePresentationStep($repository), $explodingStep]);

    try {
        $sequence->run(new ImportPresentationPayload(
            team_id: $user->currentTeam->id,
            name: 'Doomed import',
            content: importablePresentation()['content'],
        ));
        $this->fail('expected the sequence to throw');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('boom');
    }

    // compensateData() removed the row that was committed before the failure.
    $this->assertDatabaseCount('presentations', 0);
});

test('guests cannot import presentations', function () {
    $user = User::factory()->create();

    $response = $this->post(route('presentations.importJson', ['current_team' => $user->currentTeam->slug]), [
        'file' => jsonUpload(importablePresentation()),
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('presentations', 0);
});
