---
name: database
description: Design and maintain MySQL database changes with Laravel migrations, foreign keys, indexing, normalization, soft deletes, transactions, seeders, factories, and query optimization.
---

# Database Skill

## When To Use

Use this skill for migrations, schema changes, foreign keys, indexes, seeders, factories, transactions, soft deletes, data backfills, query optimization, and database review.

## Best Practices

- Use Laravel migrations for schema changes.
- Make migrations reversible when practical.
- Use foreign keys for relational integrity unless the project has a deliberate exception.
- Add indexes for foreign keys and frequent filters, joins, and sorts.
- Normalize data unless denormalization has a measured purpose.
- Use soft deletes only when restore/audit semantics are needed.
- Use transactions for multi-table writes.
- Keep seeders idempotent when they may run repeatedly.
- Use factories for test data.
- Plan data migrations carefully for production size and downtime.

## Things To Avoid

- Do not change or drop columns without considering existing data.
- Do not add non-null columns to populated tables without defaults or backfills.
- Do not store structured data as JSON when relational querying is required.
- Do not run destructive seeders in production.
- Do not add indexes for every column.

## Common Mistakes

- Missing indexes on `project_id`, `user_id`, status, and date fields used in lists.
- Foreign key type mismatches.
- Migrations that fail on existing production data.
- Seeders that create duplicates.
- Long transactions around slow external operations.

## Practical Checklist

- Migration has `up` and safe `down` behavior.
- Existing data is handled.
- Foreign keys and indexes are appropriate.
- Column names are clear.
- Query patterns are considered.
- Seeder is idempotent or clearly marked demo-only.
- Factories support tests.
- Production rollout and backup needs are documented.

## Production Recommendations

- Back up data before risky migrations.
- Run migrations during planned deployment windows for large tables.
- Avoid locking large tables when possible.
- Verify `migrate:status` after deploy.
- Monitor slow queries after schema changes.

