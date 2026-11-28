<?php

namespace ErnestDefoe\Fanfare;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * The admin's looks, as the page needs them: all of them in one cached list
 * (a handful of rows, forgotten on every save), and only today's moments'
 * looks go into the page.
 */
class Looks
{
    protected const CACHE = 'ernestdefoe-fanfare.looks';

    /** Where uploads go, on the assets disk. */
    public const DIR = 'extensions/ernestdefoe-fanfare/looks';

    protected Cloud $assets;

    public function __construct(protected Repository $cache, protected Config $config, Factory $filesystems)
    {
        $disk = $filesystems->disk('flarum-assets');
        // Flarum's assets disk always has URLs (local or S3).
        $this->assets = $disk instanceof Cloud ? $disk : throw new \RuntimeException('Fanfare needs an assets disk that serves URLs.');
    }

    /** The occasions a look can be for: the holidays, Festoon's days and the people's moments. */
    public function occasions(): array
    {
        return [...array_keys($this->config->occasions()), ...Look::PEOPLE];
    }

    /**
     * Today's looks, by occasion.
     *
     * @param list<string> $occasions
     * @return array<string, array<string, mixed>>
     */
    public function for(array $occasions): array
    {
        $all = $this->cache->rememberForever(self::CACHE, fn () => Look::query()->get()->mapWithKeys(fn (Look $l) => [$l->occasion => $this->present($l)])->all());

        return array_intersect_key($all, array_flip($occasions));
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE);
    }

    /** @return array<string, mixed> */
    public function present(Look $l): array
    {
        return array_filter([
            'title' => $l->title,
            'subtitle' => $l->subtitle,
            'art' => $l->art ?: null,
            'emoji' => $l->art === 'emoji' ? $l->emoji : null,
            'image' => $l->art === 'image' && $l->image_path ? $this->url($l->image_path) : null,
            'animation' => $l->animation !== 'rise' ? $l->animation : null,
            'effect' => $l->effect ?: null,
            'sound' => $l->sound ?: null,
            'soundUrl' => $l->sound === 'upload' && $l->sound_path ? $this->url($l->sound_path) : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function url(string $path): string
    {
        return $this->assets->url($path);
    }

    public function store(string $name, string $contents): string
    {
        $path = self::DIR.'/'.$name;
        $this->assets->put($path, $contents);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR.'/')) {
            $this->assets->delete($path);
        }
    }
}
