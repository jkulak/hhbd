# Database Update Steps: Fix Old URLs

This document provides step-by-step instructions for updating old URL formats in the HHBD production database.

## Overview

**Problem**: Some text fields in the database contain old-format URLs like `https://hhbd.pl/n/waco` which need to be updated to proper SEO-friendly URLs like `https://hhbd.pl/waco-p100.html`.

**Solution**: Run the provided SQL migration script to replace all occurrences of old URL patterns.

## Affected Tables and Fields

The following tables and fields may contain old-format URLs:
- `albums`: `description`, `artistabout`, `notes`
- `artists`: `profile`, `concertinfo`, `trivia`
- `labels`: `profile`
- `songs`: `description`
- `news`: `news`
- `artists_photos`: `description`
- `hhb_comments`: `com_content`
- `cities`: `description`

## Prerequisites

- Access to production database server
- MySQL/MariaDB client (mysql command-line tool or equivalent)
- Sufficient disk space for database backup
- Database credentials with UPDATE privileges

## Step-by-Step Instructions

### 1. Create a Full Database Backup

**CRITICAL**: Always create a backup before running any UPDATE queries on production data.

```bash
# Using mysqldump
mysqldump -u [username] -p[password] -h [host] hhbd > hhbd_backup_$(date +%Y%m%d_%H%M%S).sql

# Or using a specific tool if available
# Example: Using gcloud for GCP-hosted database
gcloud sql backups create --instance=[INSTANCE_NAME]
```

**Verify the backup**:
```bash
# Check file size (should be non-zero and reasonable)
ls -lh hhbd_backup_*.sql

# Optionally, verify the backup can be parsed
head -n 50 hhbd_backup_*.sql
```

### 2. Check Scope of Changes

Before applying the migration, identify how many records will be affected:

```bash
mysql -u [username] -p[password] -h [host] hhbd < database/migrations/fix-old-urls.sql
```

The first section of the SQL script contains a query that shows counts of affected records by table and field.

Review the output to understand the scope:
```
+--------------+-------------+-------+
| table_name   | field_name  | count |
+--------------+-------------+-------+
| labels       | profile     |     5 |
| albums       | description |     12|
| ...          | ...         | ...   |
+--------------+-------------+-------+
```

### 3. Run the Migration Script

#### Option A: Interactive Mode (Recommended for First Time)

1. Open the SQL file in a text editor:
   ```bash
   nano database/migrations/fix-old-urls.sql
   ```

2. Locate **PART 2** in the file (around line 52)

3. Uncomment the UPDATE statements by removing the `/*` at the beginning and `*/` at the end

4. Run the script:
   ```bash
   mysql -u [username] -p[password] -h [host] hhbd < database/migrations/fix-old-urls.sql
   ```

#### Option B: Direct Execution (For Experienced DBAs)

If you're confident, you can run the UPDATE statements directly:

```bash
# Copy and uncomment the UPDATE section from fix-old-urls.sql
mysql -u [username] -p[password] -h [host] hhbd << 'EOF'
-- Paste uncommented UPDATE statements here
EOF
```

### 4. Verify the Changes

After running the migration, verify that:

1. **No old URLs remain**:
   Run the verification query from **PART 3** of the SQL script:
   ```bash
   mysql -u [username] -p[password] -h [host] hhbd -e "
   SELECT 'labels' as table_name, 'profile' as field_name, COUNT(*) as remaining
   FROM labels WHERE profile LIKE '%hhbd.pl/n/%';
   "
   ```
   
   This should return `0` for all tables.

2. **Sample records look correct**:
   ```sql
   -- Check a few updated records
   SELECT lab_id, name, profile 
   FROM labels 
   WHERE profile LIKE '%hhbd.pl%' 
   LIMIT 5;
   ```

3. **Test on the website**:
   - Visit a page that previously had old URLs (e.g., https://hhbd.pl/waco-records-l54.html)
   - Verify that links in descriptions now work correctly

### 5. Monitor for Issues

After deployment:

1. Check application logs for any errors related to URL routing
2. Monitor user reports for broken links
3. Use Google Search Console to identify any 404 errors

### 6. Rollback Procedure (If Needed)

If issues are discovered after the migration:

```bash
# Stop the application (if possible)
# Restore from backup
mysql -u [username] -p[password] -h [host] hhbd < hhbd_backup_YYYYMMDD_HHMMSS.sql

# Restart the application
```

## Technical Notes

### URL Pattern Transformation

The script performs a simple string replacement:

- **Before**: `https://hhbd.pl/n/{slug}`  or `http://hhbd.pl/n/{slug}`
- **After**: `https://hhbd.pl/{slug}`

This removes the `/n/` prefix, leaving just the slug. The application's routing should then handle these URLs properly through its SEO-friendly URL structure.

### Character Encoding

The script uses `REPLACE()` function which is character encoding-aware. Ensure your database connection uses `UTF-8` encoding:

```bash
mysql --default-character-set=utf8mb4 ...
```

### Performance Considerations

- The UPDATE statements use `LIKE` conditions which may be slow on large tables
- Consider running during off-peak hours
- The script updates records in-place (no additional storage needed)
- On a typical database with 10,000+ records, expect 1-5 minutes execution time

## Example: Complete Workflow

```bash
# 1. Backup
mysqldump -u hhbd_user -p -h localhost hhbd > backup_$(date +%Y%m%d).sql

# 2. Check scope
mysql -u hhbd_user -p -h localhost hhbd < database/migrations/fix-old-urls.sql | head -20

# 3. Edit script to uncomment UPDATE statements
nano database/migrations/fix-old-urls.sql

# 4. Run migration
mysql -u hhbd_user -p -h localhost hhbd < database/migrations/fix-old-urls.sql

# 5. Verify
mysql -u hhbd_user -p -h localhost hhbd -e "SELECT COUNT(*) FROM labels WHERE profile LIKE '%hhbd.pl/n/%'"
# Should return: 0

# 6. Test in browser
# Visit https://hhbd.pl/waco-records-l54.html and check links
```

## Questions or Issues?

If you encounter any problems:
1. **DO NOT PANIC** - you have a backup
2. Check the verification queries to understand what's wrong
3. Review application logs
4. If needed, restore from backup and investigate further before retrying

## Maintenance

After successful migration:
- Keep the backup for at least 30 days
- Document the migration date in your change log
- Update any related documentation about URL formats
