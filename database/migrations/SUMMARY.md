# URL Migration: Summary

## Deliverables

This PR provides a complete solution for updating old URL formats in the HHBD database.

### Files Created

1. **`database/migrations/fix-old-urls.sql`** (194 lines)
   - SQL migration script to update old URLs
   - Three-part structure: Identify, Update, Verify
   - All UPDATE statements commented out for safety
   - Compatible with MariaDB 10.11

2. **`database/migrations/db-update-steps.md`** (315 lines)
   - Complete step-by-step guide for production deployment
   - Includes backup procedures, verification steps, rollback instructions
   - Example workflow with actual commands

3. **`database/migrations/test-migration.sh`** (133 lines)
   - Bash test script to validate SQL syntax
   - Can be run against a test database
   - Automatically tests connection, inserts test data, runs updates, and verifies results

4. **`database/migrations/README.md`** (97 lines)
   - Overview of the migrations directory
   - Usage instructions
   - Best practices guide

## What the Migration Does

### Problem
Database text fields contain old-format URLs like:
- `https://hhbd.pl/n/waco`
- `http://hhbd.pl/n/artist-name`

These URLs are no longer supported by the application's routing system.

### Solution
The migration script replaces old URLs with a format the application can handle:
- **Before**: `https://hhbd.pl/n/waco` or `http://hhbd.pl/n/waco`
- **After**: `https://hhbd.pl/waco`

The application's routing system then handles these URLs properly, redirecting to the correct SEO-friendly URLs like `https://hhbd.pl/waco-p100.html`.

### Affected Tables
- albums (description, artistabout, notes)
- artists (profile, concertinfo, trivia)
- labels (profile)
- songs (description)
- news (news)
- artists_photos (description)
- hhb_comments (com_content)
- cities (description)

## How to Use

### Quick Start

1. **Backup the database**:
   ```bash
   mysqldump -u user -p hhbd > backup.sql
   ```

2. **Check scope** (run PART 1 of the SQL script):
   ```bash
   mysql -u user -p hhbd < database/migrations/fix-old-urls.sql
   ```
   This shows how many records will be affected.

3. **Review and uncomment** UPDATE statements in the SQL file

4. **Run the migration**:
   ```bash
   mysql -u user -p hhbd < database/migrations/fix-old-urls.sql
   ```

5. **Verify** using the queries in PART 3 of the SQL file

For detailed instructions, see `database/migrations/db-update-steps.md`.

### Testing (Optional)

Before running on production, you can test on a copy of the database:

```bash
export DB_HOST=localhost DB_USER=root DB_PASS=password DB_NAME=hhbd_test
bash database/migrations/test-migration.sh
```

## Safety Features

- All UPDATE statements are commented out by default
- Comprehensive documentation with backup procedures
- Verification queries included
- Test script for validation
- Simple REPLACE() function (no complex regex)
- WHERE clauses ensure only affected records are updated

## Technical Details

- **Database**: MariaDB 10.11
- **Method**: Simple string replacement using `REPLACE()` function
- **Performance**: Minimal impact, uses indexed WHERE clauses
- **Encoding**: UTF-8 compatible
- **Rollback**: Restore from backup if needed

## Example Record

**Before**:
```
profile: "Label founded in 2006 by <a href=\"https://hhbd.pl/n/waco\">Waco</a>"
```

**After**:
```
profile: "Label founded in 2006 by <a href=\"https://hhbd.pl/waco\">Waco</a>"
```

The application's routing will then handle `/waco` and redirect to `/waco-p100.html` (artist page).

## Next Steps

1. Review the migration files
2. Test on a copy of production database (optional but recommended)
3. Schedule maintenance window
4. Follow the steps in `db-update-steps.md`
5. Monitor application after deployment

## Support

For questions or issues:
- Review `database/migrations/db-update-steps.md` for detailed instructions
- Check the test script output for validation
- Refer to the HHBD project documentation
