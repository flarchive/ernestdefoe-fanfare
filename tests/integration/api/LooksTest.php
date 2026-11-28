<?php

namespace ErnestDefoe\Fanfare\Tests\integration\api;

use ErnestDefoe\Fanfare\Config;
use ErnestDefoe\Fanfare\Occasions;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

/**
 * An admin's own looks: who may make them, the uploads (checked by their
 * bytes, pictures re-encoded, never SVG), and the page carrying today's only.
 */
class LooksTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ernestdefoe-fanfare');
        $this->prepareDatabase([User::class => [$this->normalUser()]]);
        $this->setting(Config::OCCASIONS, json_encode(array_map(fn () => ['on' => false], Occasions::CATALOGUE)));
    }

    protected function create(int $as, array $attributes): \Psr\Http\Message\ResponseInterface
    {
        return $this->send($this->request('POST', '/api/fanfare-looks', ['authenticatedAs' => $as, 'json' => ['data' => ['type' => 'fanfare-looks', 'attributes' => $attributes]]]));
    }

    protected function upload(int $id, string $kind, string $bytes, string $name): \Psr\Http\Message\ResponseInterface
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ff');
        file_put_contents($tmp, $bytes);
        $request = $this->request('POST', "/api/fanfare-looks/$id/$kind", ['authenticatedAs' => 1])
            ->withUploadedFiles(['file' => new UploadedFile($tmp, strlen($bytes), UPLOAD_ERR_OK, $name)]);

        return $this->send($request);
    }

    protected function png(): string
    {
        $img = imagecreatetruecolor(40, 30);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 60));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    #[Test]
    public function only_admins_make_looks_and_only_for_known_occasions()
    {
        $this->assertSame(403, $this->create(2, ['occasion' => 'christmas', 'title' => 'Hi'])->getStatusCode());
        $this->assertSame(422, $this->create(1, ['occasion' => 'nonsense'])->getStatusCode());
        $this->assertSame(422, $this->create(1, ['occasion' => 'christmas', 'art' => 'scene:nonsense'])->getStatusCode());
        $this->assertSame(201, $this->create(1, ['occasion' => 'christmas', 'title' => 'Happy holidays, {name}!', 'effect' => 'snow', 'art' => 'scene:hanukkah'])->getStatusCode());
        $this->assertSame(422, $this->create(1, ['occasion' => 'christmas'])->getStatusCode(), 'one look per occasion');
    }

    #[Test]
    public function uploads_are_checked_by_their_bytes()
    {
        $id = (int) json_decode($this->create(1, ['occasion' => 'birthday'])->getBody()->getContents(), true)['data']['id'];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>';
        $this->assertSame(422, $this->upload($id, 'image', $svg, 'cake.png')->getStatusCode(), 'an SVG named .png');
        $this->assertSame(422, $this->upload($id, 'sound', 'just text', 'tune.mp3')->getStatusCode(), 'text named .mp3');

        $r = $this->upload($id, 'image', $this->png(), 'cake.png');
        $this->assertSame(201, $r->getStatusCode());
        $url = json_decode($r->getBody()->getContents(), true)['url'];
        $this->assertStringEndsWith('.webp', $url, 're-encoded');

        $this->assertSame(201, $this->upload($id, 'sound', "ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\x00", 64), 'tune.mp3')->getStatusCode());

        $look = $this->database()->table('fanfare_looks')->find($id);
        $this->assertSame('image', $look->art);
        $this->assertSame('upload', $look->sound);

        // Gone with the look.
        $this->send($this->request('DELETE', "/api/fanfare-looks/$id", ['authenticatedAs' => 1]));
        $this->assertNull($this->database()->table('fanfare_looks')->find($id));
    }

    #[Test]
    public function the_page_carries_only_todays_looks()
    {
        $this->create(1, ['occasion' => 'christmas', 'title' => 'Happy holidays!']);
        $this->create(1, ['occasion' => 'halloween', 'title' => 'Boo!']);

        $html = $this->send($this->request('GET', '/', ['authenticatedAs' => 1])->withQueryParams(['fanfare' => 'christmas']))->getBody()->getContents();
        $this->assertStringContainsString('"looks":{"christmas":{"title":"Happy holidays!"', $html);
        $this->assertStringNotContainsString('Boo!', $html);

        $this->assertStringNotContainsString('"looks"', $this->send($this->request('GET', '/', ['authenticatedAs' => 2]))->getBody()->getContents(), 'a normal day carries none');
    }
}
