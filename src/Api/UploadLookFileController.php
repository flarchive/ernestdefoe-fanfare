<?php

namespace ErnestDefoe\Fanfare\Api;

use ErnestDefoe\Fanfare\Look;
use ErnestDefoe\Fanfare\Looks;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * POST /api/fanfare-looks/{id}/image and /sound, multipart `file`: a look's
 * picture or sound, admins only. Files are checked by their bytes, not their
 * names. Pictures (PNG, JPEG or WebP, never SVG) are re-encoded to WebP, which
 * also drops their metadata; sounds (MP3 or Ogg) are kept as sent. Both go to
 * the assets disk under a random name, replacing the look's last one.
 */
class UploadLookFileController implements RequestHandlerInterface
{
    public const IMAGE_BYTES = 2 * 1024 * 1024;
    public const SOUND_BYTES = 1024 * 1024;

    /** At most 25 megapixels: a small file can still decode to a huge image. */
    public const IMAGE_PIXELS = 25_000_000;

    public function __construct(protected Looks $looks, protected ImageManager $images, protected TranslatorInterface $translator)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $look = Look::query()->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $kind = Arr::get($request->getQueryParams(), 'kind');
        $file = Arr::get($request->getUploadedFiles(), 'file');

        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            throw $this->invalid('missing');
        }

        $bytes = (string) $file->getStream();

        if ($kind === 'image') {
            $old = $look->image_path;
            $path = $this->looks->store(Str::random(24).'.webp', $this->image($bytes));
            $look->image_path = $path;
            $look->art = 'image';
        } else {
            $old = $look->sound_path;
            $path = $this->looks->store(Str::random(24).'.'.$this->sound($bytes), $bytes);
            $look->sound_path = $path;
            $look->sound = 'upload';
        }

        // The old file goes only once the look points at the new one.
        $look->save();
        $this->looks->delete($old);
        $this->looks->forget();

        return new JsonResponse(['url' => $this->looks->url($path)], 201);
    }

    /** PNG, JPEG or WebP by their first bytes, re-encoded to WebP at most 1200 pixels across. */
    protected function image(string $bytes): string
    {
        $png = str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
        $jpeg = str_starts_with($bytes, "\xFF\xD8\xFF");
        $webp = str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP';

        // The size on disk, the kind by its bytes, and the pixels before anything is decoded.
        $size = strlen($bytes) <= self::IMAGE_BYTES && ($png || $jpeg || $webp) ? @getimagesizefromstring($bytes) : false;
        if (! $size || $size[0] * $size[1] > self::IMAGE_PIXELS) {
            throw $this->invalid('image');
        }

        return (string) $this->images->read($bytes)->scaleDown(1200, 1200)->toWebp(85);
    }

    /** MP3 (an ID3 tag or a frame sync) or Ogg, by their first bytes: the file's extension. */
    protected function sound(string $bytes): string
    {
        if (strlen($bytes) <= self::SOUND_BYTES) {
            if (str_starts_with($bytes, 'OggS')) {
                return 'ogg';
            }
            if (str_starts_with($bytes, 'ID3') || (strlen($bytes) > 1 && ord($bytes[0]) === 0xFF && (ord($bytes[1]) & 0xE0) === 0xE0)) {
                return 'mp3';
            }
        }

        throw $this->invalid('sound');
    }

    protected function invalid(string $what): ValidationException
    {
        return new ValidationException(['file' => $this->translator->trans('ernestdefoe-fanfare.api.upload_'.$what)]);
    }
}
