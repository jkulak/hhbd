#!/usr/bin/env php
<?php

/**
 * Generate Test Images for Smoke Tests
 *
 * This script generates small placeholder JPEG images for:
 * - Album covers (content/a/)
 * - Artist photos (content/p/)
 * - Label logos (content/l/)
 * - News images (content/news/)
 *
 * Usage:
 *   php app/tools/generate-test-images.php
 *
 * Or from Docker, in the importer's image, the one with GD:
 *   make test-images
 */

// Configuration
$baseDir = __DIR__ . '/../../content';
$imageTypes = [
    'a' => ['count' => 50, 'prefix' => 'test-cover', 'label' => 'ALBUM'],  // Album covers
    'p' => ['count' => 30, 'prefix' => 'test-artist', 'label' => 'ARTIST'], // Artist photos
    'l' => ['count' => 15, 'prefix' => 'test-label', 'label' => 'LABEL'],   // Label logos
    'news' => ['count' => 3, 'prefix' => 'test-news', 'label' => 'NEWS'],  // News images (#133)
];
$imageSize = 100; // 100x100 pixels
// Files of another shape, as the fixtures describe them, so the photos backfill records what the
// pages expect: Eldo's main photo landscape, Stasiak's portrait (#166)
$shapes = [
    'test-artist-002.jpg' => [600, 378],
    'test-artist-003.jpg' => [225, 300],
];

// Check if GD is available
if (!extension_loaded('gd')) {
    die("Error: PHP GD extension is required but not loaded.\n");
}

// Color palette for variety
$colors = [
    [52, 152, 219],   // Blue
    [46, 204, 113],   // Green
    [155, 89, 182],   // Purple
    [241, 196, 15],   // Yellow
    [231, 76, 60],    // Red
    [26, 188, 156],   // Turquoise
    [230, 126, 34],   // Orange
    [149, 165, 166],  // Gray
    [52, 73, 94],     // Dark Blue
    [192, 57, 43],    // Dark Red
];

$totalGenerated = 0;
$totalSkipped = 0;

// Generate images for each type
foreach ($imageTypes as $type => $config) {
    $outputDir = $baseDir . '/' . $type;
    $imageCount = $config['count'];
    $prefix = $config['prefix'];
    $label = $config['label'];

    // Create output directory if it doesn't exist
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            echo "Error: Could not create directory: $outputDir\n";
            continue;
        }
        echo "Created directory: $outputDir\n";
    }

    echo "Generating $imageCount test {$label} images in content/$type/...\n";

    $generated = 0;
    $skipped = 0;

    for ($i = 1; $i <= $imageCount; $i++) {
        $filename = sprintf('%s-%03d.jpg', $prefix, $i);
        $filepath = $outputDir . '/' . $filename;

        // Skip if file already exists
        if (file_exists($filepath)) {
            $skipped++;
            continue;
        }

        // Create image
        list($width, $height) = isset($shapes[$filename]) ? $shapes[$filename] : [$imageSize, $imageSize];
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            echo "  Warning: Could not create image for $filename\n";
            continue;
        }

        // Select color based on index and type
        $colorIndex = ($i - 1 + ord($type)) % count($colors);
        $bgColor = imagecolorallocate(
            $image,
            $colors[$colorIndex][0],
            $colors[$colorIndex][1],
            $colors[$colorIndex][2]
        );

        // Fill background
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $bgColor);

        // Add text (number)
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $text = sprintf('%03d', $i);

        // Calculate text position (centered)
        $fontSize = 5; // Built-in font size (1-5)
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = ($width - $textWidth) / 2;
        $y = ($height - $textHeight) / 2;

        imagestring($image, $fontSize, (int)$x, (int)$y, $text, $textColor);

        // Add type label at bottom
        $labelWidth = imagefontwidth(2) * strlen($label);
        $labelX = ($width - $labelWidth) / 2;
        imagestring($image, 2, (int)$labelX, $height - 15, $label, $textColor);

        // Save as JPEG
        $result = imagejpeg($image, $filepath, 85);
        imagedestroy($image);

        if ($result) {
            $generated++;
        } else {
            echo "  Warning: Could not save $filename\n";
        }
    }

    echo "  Generated: $generated, Skipped: $skipped\n";
    $totalGenerated += $generated;
    $totalSkipped += $skipped;

    // Album lists show a 75 px thumbnail from content/a/th/, named after the cover with -th.jpg
    // for its extension; a cover without one is a missing file to make check-images (#47).
    if ($type === 'a') {
        $thumbDir = $outputDir . '/th';
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0755, true)) {
            echo "Error: Could not create directory: $thumbDir\n";
            continue;
        }
        $thumbs = 0;
        foreach (glob($outputDir . '/' . $prefix . '-*.jpg') as $cover) {
            $thumbPath = $thumbDir . '/' . substr(basename($cover), 0, -4) . '-th.jpg';
            if (file_exists($thumbPath)) {
                continue;
            }
            $source = imagecreatefromjpeg($cover);
            $thumb = imagecreatetruecolor(75, 75);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, 75, 75, imagesx($source), imagesy($source));
            if (imagejpeg($thumb, $thumbPath, 85)) {
                $thumbs++;
            }
            imagedestroy($source);
            imagedestroy($thumb);
        }
        echo "  Thumbnails generated: $thumbs\n";
        $totalGenerated += $thumbs;
    }
}

echo "\n=== Summary ===\n";
echo "Total generated: $totalGenerated images\n";
echo "Total skipped: $totalSkipped images\n";
echo "Output directories:\n";
foreach ($imageTypes as $type => $config) {
    echo "  - $baseDir/$type/ ({$config['count']} {$config['label']} images)\n";
}
