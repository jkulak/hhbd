# Database Performance Measurement Tools

This directory contains scripts to measure and optimize MariaDB buffer pool performance before and after configuration changes.

## Overview

The optimization increases the InnoDB buffer pool from **128MB → 256MB** and enables persistent caching so data stays in memory across restarts.

## Scripts

### 1. `benchmark-db.sh` - Capture Metrics

Measures buffer pool statistics, cache hit ratio, and database size.

```bash
# Before optimization
bash tools/benchmark-db.sh before

# After optimization  
bash tools/benchmark-db.sh after
```

**Output:** `database-metrics-before.txt`, `database-metrics-after.txt`

**Metrics captured:**

- Buffer pool configuration (size, old blocks time)
- Cache hit ratio (target: >95%)
- Disk reads count
- Buffer pool utilization
- Database size
- Largest tables

### 2. `load-test.sh` - Stress Test

Uses `mysqlslap` to simulate realistic concurrent load and measure query performance.

```bash
# Before optimization
bash tools/load-test.sh before

# After optimization
bash tools/load-test.sh after
```

**Output:** `load-test-before.txt`, `load-test-after.txt`

**Test parameters:**

- 10 concurrent connections
- 100 iterations each
- 300 total queries
- Realistic queries (SELECT from albums, artists, songs)

**Expected improvement:** 20-50% faster queries if buffer pool is effective

### 3. `response-time-test.sh` - End-to-End Response Times

Measures actual HTTP page load times (5 different pages, 20 runs each).

```bash
# Before optimization (dev environment)
bash tools/response-time-test.sh before http://localhost:8080

# After optimization (production)
bash tools/response-time-test.sh after https://hhbd.pl
```

**Output:** `response-time-before.txt`, `response-time-after.txt`

**Pages tested:**

- `/` (homepage)
- `/artists.html`
- `/albums.html`
- `/top10.html`
- `/search.html`

**Expected improvement:** 10-40% faster page loads

### 4. `compare-metrics.sh` - Side-by-Side Comparison

Automatically compares all before/after metrics and calculates improvements.

```bash
bash tools/compare-metrics.sh
```

**Output example:**

```
--- 1. Cache Hit Ratio ---
  BEFORE: 87.50%
  AFTER:  98.75%
  CHANGE: +11.25% (higher is better) ✅

--- 2. Buffer Pool Disk Reads (lower is better) ---
  BEFORE: 45234 reads
  AFTER:  3421 reads
  REDUCTION: 92.4% ✅

--- 3. Query Execution Time (mysqlslap) ---
  BEFORE: 0.523s (average query time)
  AFTER:  0.198s (average query time)
  SPEEDUP: 62.1% faster ✅
```

### 5. `warm-buffer-pool.sh` - Pre-warm Cache on Startup

Loads all database tables into memory after MariaDB starts.

```bash
bash tools/warm-buffer-pool.sh
```

**What it does:**

- Waits for MariaDB to be ready
- Runs `SELECT COUNT(*)` on every table
- Pre-loads all data into buffer pool
- Keeps data in memory indefinitely

**When to run:**

- After deploying with new buffer pool settings
- After server restarts
- Optionally: add to Docker startup hooks

## Complete Before/After Workflow

### Step 1: Baseline Measurement (BEFORE)

```bash
# Run all baseline tests
bash tools/benchmark-db.sh before
bash tools/load-test.sh before http://localhost:8080
bash tools/response-time-test.sh before http://localhost:8080

# Save results
mkdir -p results/before
cp database-metrics-before.txt load-test-before.txt response-time-before.txt results/before/
```

### Step 2: Deploy New Configuration

```bash
# This updates compose.gcp.yaml with:
# - innodb-buffer-pool-size=256M (was 128M)
# - innodb-old-blocks-time=1000
# - innodb-buffer-pool-dump-at-shutdown=1
# - innodb-buffer-pool-load-at-startup=1
# - memory limit: 512M (was 384M)

docker compose -f deploy/compose.gcp.yaml up -d db

# Warm up the buffer pool
bash tools/warm-buffer-pool.sh

# Wait 10-15 minutes for cache to stabilize
sleep 600
```

### Step 3: After-Deploy Measurement

```bash
# Run all tests again
bash tools/benchmark-db.sh after
bash tools/load-test.sh after http://localhost:8080
bash tools/response-time-test.sh after http://localhost:8080

# Save results
mkdir -p results/after
cp database-metrics-after.txt load-test-after.txt response-time-after.txt results/after/
```

### Step 4: Compare Results

```bash
# Generate comparison report
bash tools/compare-metrics.sh > comparison-report.txt

# View results
cat comparison-report.txt
```

## Expected Results

If the optimization is successful, you should see:

| Metric | Expected Improvement |
|--------|---------------------|
| Cache Hit Ratio | +5-15% (targeting >95%) |
| Disk Reads | -70% to -95% |
| Query Time | -20% to -50% |
| Page Load Time | -10% to -40% |
| Memory Used | ~250-300MB (fits in buffer pool) |

## Troubleshooting

### "Container not running"

```bash
docker compose -f deploy/compose.gcp.yaml up -d
```

### Missing `bc` command

```bash
# On Alpine/minimal images
apk add bc

# On Debian/Ubuntu
apt-get install bc
```

### Scripts timeout

Increase timeout in scripts if running on slow connections:

```bash
# Modify curl timeout in response-time-test.sh
curl -s --max-time 30 ...  # Change from 10 to 30
```

### No improvement seen

This happens if:

1. Database already fits in original buffer pool
2. Not enough traffic to generate realistic load
3. Buffer pool not fully warmed up yet

Run warm-buffer-pool.sh again and wait 15 minutes.

## Database Information

After optimization:

- **Buffer Pool Size:** 256MB (up from 128M)
- **Container Memory Limit:** 512MB (up from 384M)
- **Database Size:** ~200-300MB (typical for HHBD)
- **Expected Fit:** ✅ Entire database in buffer pool
- **Data Persistence:** Data stays in memory until restart
- **Auto-restore:** Buffer pool state saved/loaded on restart

## Notes

- Scripts require `docker`, `mysql-client`, `bc`, and `curl`
- Container must have sufficient memory (512MB recommended)
- GCP e2-micro has 1GB total + 4GB swap, so 512MB for DB is safe
- Buffer pool dump/load feature ensures fast startup after restarts
- All data without access TTL will stay in memory indefinitely

## Files Generated

After running benchmarks:

```
database-metrics-before.txt   # Buffer pool stats (before)
database-metrics-after.txt    # Buffer pool stats (after)
load-test-before.txt          # mysqlslap results (before)
load-test-after.txt           # mysqlslap results (after)
response-time-before.txt      # Page load times (before)
response-time-after.txt       # Page load times (after)
```

Run `bash tools/compare-metrics.sh` to analyze all at once.
