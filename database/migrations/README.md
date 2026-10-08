# Database Migrations

This directory contains database migration scripts for the HHBD application.

## Contents

- **`fix-old-urls.sql`** - Migration script to update old URL formats in database text fields
- **`db-update-steps.md`** - Step-by-step guide for applying the URL fix migration on production
- **`test-migration.sh`** - Test script to validate SQL migration syntax (optional)

## Usage

### For Production Database Updates

Follow the instructions in **`db-update-steps.md`**. This document provides:
- Prerequisites and requirements
- Step-by-step instructions with examples
- Backup and rollback procedures
- Verification queries
- Troubleshooting tips

### For Testing (Optional)

You can test the migration script on a test database first:

```bash
# Set your test database credentials
export DB_HOST=localhost
export DB_USER=root
export DB_PASS=your_password
export DB_NAME=hhbd_test

# Run the test script
bash database/migrations/test-migration.sh
```

## Migration: Fix Old URLs (2026-01-12)

**Problem**: Text fields in the database contain old-format URLs like `https://hhbd.pl/n/slug` which are no longer valid.

**Solution**: The migration script uses `REPLACE()` to update all occurrences of `hhbd.pl/n/` to `hhbd.pl/`, converting old short URLs to the correct format.

**Affected Tables**:
- albums (description, artistabout, notes)
- artists (profile, concertinfo, trivia)
- labels (profile)
- songs (description)
- news (news)
- artists_photos (description)
- hhb_comments (com_content)
- cities (description)

**Safety**: All UPDATE statements are commented out by default. You must uncomment them after reviewing the scope of changes.

## Creating New Migrations

When creating new database migrations:

1. Create a descriptive SQL file (e.g., `add-feature-xyz.sql`)
2. Include clear comments explaining the purpose
3. Add verification queries to test the migration
4. Create a corresponding markdown document with instructions
5. Comment out destructive operations by default
6. Always include rollback procedures

## Best Practices

- **Always backup** before running migrations on production
- **Test first** on a copy of the production database
- **Review changes** by running identification queries before updates
- **Verify results** after applying migrations
- **Document everything** for future reference
- **Monitor application** after migration for any issues

## Questions?

For questions about these migrations, refer to:
- The specific migration's documentation file
- HHBD project documentation
- Contact the development team
