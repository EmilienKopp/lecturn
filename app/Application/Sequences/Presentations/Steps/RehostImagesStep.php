<?php

declare(strict_types=1);

namespace App\Application\Sequences\Presentations\Steps;

use App\Application\Sequences\Presentations\ImportPresentationPayload;
use App\Domain\Presentation\Contracts\PresentationRepository;
use App\Domain\Presentation\Entities\PresentationEntity;
use App\Domain\Presentation\ValueObjects\PresentationContent;
use Illuminate\Support\Facades\Log;
use Splitstack\Conveyor\Concerns\IsSteppable;
use Splitstack\Conveyor\Contracts\Steppable;
use Throwable;

/**
 * Downloads the imported deck's remote images into its own media library and
 * rebinds the URLs, so the presentation no longer depends on the source's
 * storage. Best-effort: an image that can't be fetched keeps its original
 * URL and is reported back through the payload for the user to see.
 */
class RehostImagesStep implements Steppable
{
    use IsSteppable;

    public function __construct(private readonly PresentationRepository $presentations) {}

    public function handle(ImportPresentationPayload $payload): void
    {
        /** @var PresentationEntity $presentation */
        $presentation = $payload->get('presentation');

        $urls = $this->collectRemoteImageUrls($payload->content);
        $rehosted = [];
        $unresolved = [];

        foreach ($urls as $url) {
            try {
                $rehosted[$url] = $this->presentations->storeImageFromUrl($presentation->id, $url);
            } catch (Throwable $exception) {
                $unresolved[] = $url;
                Log::warning('Import could not re-host an image.', [
                    'presentation_id' => $presentation->id,
                    'url' => $url,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($rehosted !== []) {
            $content = $this->rewriteImageUrls($payload->content, $rehosted);
            $presentation->replaceContent(PresentationContent::fromArray($content));
            $this->presentations->save($presentation);
        }

        $payload->set('unresolvedImages', $unresolved);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<string>
     */
    private function collectRemoteImageUrls(array $content): array
    {
        $urls = [];

        array_walk_recursive($content, static function (mixed $value, mixed $key) use (&$urls): void {
            if (($key === 'src' || $key === 'backgroundImage')
                && is_string($value)
                && preg_match('#^https?://#i', $value) === 1) {
                $urls[$value] = true;
            }
        });

        return array_keys($urls);
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, string>  $map
     * @return array<string, mixed>
     */
    private function rewriteImageUrls(array $content, array $map): array
    {
        array_walk_recursive($content, static function (mixed &$value) use ($map): void {
            if (is_string($value) && isset($map[$value])) {
                $value = $map[$value];
            }
        });

        return $content;
    }
}
