#!/usr/bin/env php
<?php

/**
 * Generate Test Images for Smoke Tests
 *
 * This script generates small placeholder JPEG images for:
 * - Album covers (content/a/)
 * - Artist photos (content/p/)
 * - Label logos (content/l/)
 *
 * Usage:
 *   php app/tools/generate-test-images.php
 *
 * Or from Docker:
 *   docker compose exec app php app/tools/generate-test-images.php
 */

// Configuration
$baseDir = __DIR__ . '/../../content';
$imageTypes = [
    'a' => ['count' => 50, 'prefix' => 'test-cover', 'label' => 'ALBUM'],  // Album covers
    'p' => ['count' => 30, 'prefix' => 'test-artist', 'label' => 'ARTIST'], // Artist photos
    'l' => ['count' => 15, 'prefix' => 'test-label', 'label' => 'LABEL'],   // Label logos
];
$imageSize = 100; // 100x100 pixels

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
        $image = imagecreatetruecolor($imageSize, $imageSize);
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
        imagefilledrectangle($image, 0, 0, $imageSize - 1, $imageSize - 1, $bgColor);

        // Add text (number)
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $text = sprintf('%03d', $i);

        // Calculate text position (centered)
        $fontSize = 5; // Built-in font size (1-5)
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);
        $x = ($imageSize - $textWidth) / 2;
        $y = ($imageSize - $textHeight) / 2;

        imagestring($image, $fontSize, (int)$x, (int)$y, $text, $textColor);

        // Add type label at bottom
        $labelWidth = imagefontwidth(2) * strlen($label);
        $labelX = ($imageSize - $labelWidth) / 2;
        imagestring($image, 2, (int)$labelX, $imageSize - 15, $label, $textColor);

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
}

echo "\n=== Summary ===\n";
echo "Total generated: $totalGenerated images\n";
echo "Total skipped: $totalSkipped images\n";
echo "Output directories:\n";
foreach ($imageTypes as $type => $config) {
    echo "  - $baseDir/$type/ ({$config['count']} {$config['label']} images)\n";
}
