<?php

/**
 * An image a batch ships (#56, #96): checked against what the document says of it, then written
 * into content/ in the sizes the pages use, by GD in the importer's image.
 *
 * A batch carries one original per cover, photo or logo. Its header has to give the width,
 * height and MIME type the document declares, and its content the declared SHA-256, or the
 * document is refused with the reason. The written files are re-encoded, which drops EXIF and
 * every other piece of metadata, as JPEG of quality 90 (a logo keeps PNG, for its
 * transparency), and never larger than the original: a 600 px cover gives 600, 300 and 75, and
 * no orig that would only be an upscaled copy.
 */
class Model_Import_Image
{
    public const MIMES = array('image/jpeg', 'image/png', 'image/webp');

    /**
     * The sizes of a cover, by the longer side in pixels; orig is the original itself, capped.
     */
    public const COVER_VARIANTS = array('orig' => 1500, '600' => 600, '300' => 300, '75' => 75);

    /**
     * A photo is kept at one size, which the artist page shows at 300 px and the gallery at 150.
     */
    public const PHOTO_SIZE = 600;
    public const LOGO_SIZE = 300;
    public const QUALITY = 90;

    private $path;
    private $info;

    /**
     * @param string $path   the file in the batch
     * @param array  $declared width, height, mime and sha256, as the document gives them
     * @throws InvalidArgumentException when the file is missing, unreadable, or not what the
     *                                  document says it is
     */
    public function __construct($path, array $declared)
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('No file %s', basename($path)));
        }
        $info = @getimagesize($path);
        if (false === $info) {
            throw new InvalidArgumentException(sprintf('%s is not an image', basename($path)));
        }
        $actual = array('width' => $info[0], 'height' => $info[1], 'mime' => $info['mime'], 'sha256' => hash_file('sha256', $path));
        foreach (array('width', 'height', 'mime', 'sha256') as $field) {
            if (!isset($declared[$field]) || (string) $declared[$field] !== (string) $actual[$field]) {
                throw new InvalidArgumentException(sprintf(
                    '%s: the document says %s %s, the file has %s',
                    basename($path),
                    $field,
                    isset($declared[$field]) ? $declared[$field] : 'nothing',
                    $actual[$field]
                ));
            }
        }
        if (!in_array($info['mime'], self::MIMES, true)) {
            throw new InvalidArgumentException(sprintf('%s: %s is not an image type the importer takes', basename($path), $info['mime']));
        }
        $this->path = $path;
        $this->info = $actual;
    }

    public function getWidth()
    {
        return $this->info['width'];
    }

    public function getHeight()
    {
        return $this->info['height'];
    }

    public function getSha256()
    {
        return $this->info['sha256'];
    }

    /**
     * The cover variants this original gives: every size up to the original's longer side, orig
     * only when the original is larger than the biggest other size.
     *
     * @return string[] variant names, largest first
     */
    public static function coverVariantsFor($width, $height)
    {
        $longer = max($width, $height);
        $variants = array();
        foreach (self::COVER_VARIANTS as $variant => $size) {
            // PHP turns the keys '600', '300' and '75' into integers; the variants are names.
            $variant = (string) $variant;
            if ('orig' === $variant) {
                if ($longer > self::COVER_VARIANTS['600']) {
                    $variants[] = 'orig';
                }
            } elseif ($longer >= $size) {
                $variants[] = $variant;
            }
        }
        if (empty($variants)) {
            // Smaller than the smallest size: the one file there is.
            $variants[] = '75';
        }
        return $variants;
    }

    /**
     * The width and height of the original scaled so its longer side is at most $size.
     *
     * @return int[] array(width, height)
     */
    public static function fit($width, $height, $size)
    {
        $scale = min(1, $size / max($width, $height));
        return array(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
    }

    /**
     * Writes the image at most $size on its longer side to $target, re-encoded without
     * metadata, and says what was written.
     *
     * @param string $format jpeg or png
     * @return array width, height, sha256 and mime of the written file
     */
    public function write($target, $size, $format = 'jpeg')
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD is not available; images are written in the importer image');
        }
        $source = $this->load();
        list($width, $height) = self::fit($this->info['width'], $this->info['height'], $size);
        $image = imagecreatetruecolor($width, $height);
        if ('png' === $format) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        } else {
            // A transparent original goes onto white, as a page would show it.
            imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        }
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $this->info['width'], $this->info['height']);
        imagedestroy($source);

        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true)) {
            throw new RuntimeException(sprintf('Cannot create %s', dirname($target)));
        }
        $written = 'png' === $format ? imagepng($image, $target, 9) : imagejpeg($image, $target, self::QUALITY);
        imagedestroy($image);
        if (!$written) {
            throw new RuntimeException(sprintf('Cannot write %s', $target));
        }
        return array(
            'width'  => $width,
            'height' => $height,
            'sha256' => hash_file('sha256', $target),
            'mime'   => 'png' === $format ? 'image/png' : 'image/jpeg',
        );
    }

    private function load()
    {
        switch ($this->info['mime']) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($this->path);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($this->path);
                break;
            case 'image/webp':
                $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($this->path) : false;
                break;
            default:
                $image = false;
        }
        if (false === $image) {
            throw new InvalidArgumentException(sprintf('%s cannot be read as %s; is it truncated?', basename($this->path), $this->info['mime']));
        }
        return $image;
    }
}
