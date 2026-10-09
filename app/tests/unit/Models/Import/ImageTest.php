<?php

use PHPUnit\Framework\TestCase;

/**
 * Images a batch ships (#56, #96): checked against the document, written in the pages' sizes
 * without metadata, never upscaled.
 *
 * @requires extension gd
 */
class Model_Import_ImageTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hhbd-image-test-' . getmypid() . '-' . mt_rand();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
        rmdir($this->dir);
    }

    /** An image file of the given kind and size, and what a document would say of it. */
    private function original(string $kind, int $width, int $height): array
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
        $path = "{$this->dir}/original.$kind";
        if ('png' === $kind) {
            imagepng($image, $path);
        } elseif ('webp' === $kind) {
            imagewebp($image, $path);
        } else {
            imagejpeg($image, $path, 95);
        }
        imagedestroy($image);
        $info = getimagesize($path);
        return array($path, array('width' => $info[0], 'height' => $info[1], 'mime' => $info['mime'], 'sha256' => hash_file('sha256', $path)));
    }

    public function testALargeCoverGivesEveryVariantWithItsOriginalCapped(): void
    {
        $this->assertSame(array('orig', '600', '300', '75'), Model_Import_Image::coverVariantsFor(3000, 3000));
        $this->assertSame(array(1500, 1500), Model_Import_Image::fit(3000, 3000, Model_Import_Image::COVER_VARIANTS['orig']));
    }

    public function testA600PixelCoverGivesNoUpscaledOriginal(): void
    {
        $this->assertSame(array('600', '300', '75'), Model_Import_Image::coverVariantsFor(600, 600));
    }

    public function testASmallCoverGivesOnlyTheSizesItReaches(): void
    {
        $this->assertSame(array('300', '75'), Model_Import_Image::coverVariantsFor(500, 480));
        $this->assertSame(array('75'), Model_Import_Image::coverVariantsFor(60, 60));
    }

    public function testAnOblongImageKeepsItsProportions(): void
    {
        $this->assertSame(array(600, 400), Model_Import_Image::fit(1200, 800, 600));
        $this->assertSame(array(200, 300), Model_Import_Image::fit(200, 300, 600));
    }

    public function testAJpegIsWrittenAtTheSizeAskedWithWhatItIsRecorded(): void
    {
        list($path, $declared) = $this->original('jpeg', 800, 800);
        $written = (new Model_Import_Image($path, $declared))->write("{$this->dir}/a/300/x.jpg", 300);

        $this->assertSame(array(300, 300, 'image/jpeg'), array($written['width'], $written['height'], $written['mime']));
        $this->assertSame(hash_file('sha256', "{$this->dir}/a/300/x.jpg"), $written['sha256']);
        $this->assertSame(array(300, 300), array_slice(getimagesize("{$this->dir}/a/300/x.jpg"), 0, 2));
    }

    public function testAPngOriginalBecomesAJpegVariant(): void
    {
        list($path, $declared) = $this->original('png', 400, 400);
        $written = (new Model_Import_Image($path, $declared))->write("{$this->dir}/x.jpg", 75);

        $this->assertSame('image/jpeg', getimagesize("{$this->dir}/x.jpg")['mime']);
        $this->assertSame(75, $written['width']);
    }

    /**
     * @requires function imagewebp
     */
    public function testAWebpOriginalIsReadToo(): void
    {
        list($path, $declared) = $this->original('webp', 700, 700);
        $written = (new Model_Import_Image($path, $declared))->write("{$this->dir}/x.jpg", 600);

        $this->assertSame(array(600, 600), array($written['width'], $written['height']));
    }

    public function testTheWrittenFileCarriesNoMetadata(): void
    {
        list($path, $declared) = $this->original('jpeg', 400, 400);
        // An EXIF segment right after the start of image, as a camera writes it.
        $jpeg = file_get_contents($path);
        $exif = "Exif\0\0" . str_repeat('A', 32);
        file_put_contents($path, substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
        $declared['sha256'] = hash_file('sha256', $path);

        (new Model_Import_Image($path, $declared))->write("{$this->dir}/x.jpg", 300);

        $this->assertStringContainsString('Exif', file_get_contents($path));
        $this->assertStringNotContainsString('Exif', file_get_contents("{$this->dir}/x.jpg"));
    }

    /**
     * @dataProvider mismatchProvider
     */
    public function testAFileThatIsNotWhatTheDocumentSaysIsRefusedWithTheReason(string $field, $value, string $reason): void
    {
        list($path, $declared) = $this->original('jpeg', 400, 300);
        $declared[$field] = $value;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($reason);
        new Model_Import_Image($path, $declared);
    }

    public function mismatchProvider(): array
    {
        return array(
            'another width' => array('width', 401, 'the document says width 401, the file has 400'),
            'another type' => array('mime', 'image/png', 'the document says mime image/png, the file has image/jpeg'),
            'another hash' => array('sha256', str_repeat('0', 64), 'the document says sha256 ' . str_repeat('0', 64)),
        );
    }

    public function testATruncatedFileIsRefused(): void
    {
        list($path, $declared) = $this->original('jpeg', 400, 400);
        file_put_contents($path, substr(file_get_contents($path), 0, 300));
        $declared['sha256'] = hash_file('sha256', $path);

        $this->expectException(InvalidArgumentException::class);
        $image = new Model_Import_Image($path, $declared);
        $image->write("{$this->dir}/x.jpg", 75);
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No file nothing.jpg');
        new Model_Import_Image("{$this->dir}/nothing.jpg", array());
    }
}
