#!/bin/bash

# --- CONFIGURATION ---
TARGET="application/ library/"
JSON_TMP="stats.json"

# Ensure PHP settings allow short tags
export PHP_OPTIONS="-d short_open_tag=On"

echo "-------------------------------------------"
echo "   PHP LEGACY CODEBASE HEALTH REPORT"
echo "-------------------------------------------"

# 1. Structural Stats (PHPLOC)
if [ -f "./vendor/bin/phploc" ]; then
    # Capturing more specific lines from PHPLOC
    ./vendor/bin/phploc $TARGET | grep -E "Average Class Length|Average Complexity per LLOC|Logical Lines of Code \(LLOC\)|Global functions|Global constants|Cyclomatic Complexity per LLOC" | awk -F'  +' '{ printf "%-30s | %s\n", $2, $3 }'
fi

# 2. Advanced Metrics (PHPMetrics)
if [ -f "./vendor/bin/phpmetrics" ]; then
    php -d short_open_tag=On ./vendor/bin/phpmetrics --report-json=$JSON_TMP $TARGET > /dev/null 2>&1
    
    php -r '
        if(!file_exists("'$JSON_TMP'")) exit;
        $json = file_get_contents("'$JSON_TMP'");
        $decoded = json_decode($json, true);
        $avg = $decoded["avg"];
        
        $metrics = [
            "Intelligent Content"     => $avg["intelligentContent"] ?? 0,
            "Bugs (Estimated)"        => $avg["bugs"] ?? 0,
            "System Complexity"       => $avg["relativeSystemComplexity"] ?? 0,
            "Lack of Cohesion (LCOM)" => $avg["lcom"] ?? 0,
            "Unit Size (LLOC/Method)" => $avg["llocPerMethod"] ?? 0
        ];

        foreach ($metrics as $label => $val) {
            printf("%-30s | %s\n", $label, round($val, 2));
        }
    '
    rm $JSON_TMP 2>/dev/null
fi
echo "-------------------------------------------"
