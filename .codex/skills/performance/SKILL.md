---
name: performance
description: Improve Laravel and Vue performance through eager loading, caching, indexing, query optimization, lazy loading, bundle optimization, image optimization, pagination, and memory control.
---

# Performance Skill

## When To Use

Use this skill when optimizing slow pages, APIs, database queries, large tables, frontend bundles, images, memory usage, repeated API calls, dashboards, maps, reports, imports, and exports.

## Best Practices

- Measure before optimizing when possible.
- Use eager loading to avoid N+1 queries.
- Paginate large result sets.
- Add indexes for frequent filters, joins, sort columns, and foreign keys.
- Select only needed columns for large queries.
- Cache stable, expensive data with a clear invalidation strategy.
- Move slow side effects to queues.
- Lazy load heavy Vue routes and components.
- Optimize images and avoid shipping oversized assets.
- Keep reactive state minimal for large data.
- Debounce search and filter inputs.

## Things To Avoid

- Do not load all records for lists that can grow.
- Do not cache permission-sensitive data without scoping by user or role.
- Do not add indexes blindly without considering write cost.
- Do not compute expensive derived values repeatedly in templates.
- Do not bundle rarely used admin tools into the initial page load.

## Common Mistakes

- API resources accessing unloaded relationships.
- Unpaginated export-like endpoints used by normal UI pages.
- Search queries without indexes or limits.
- Re-fetching data after every small UI interaction.
- Large watchers on deeply nested objects.

## Practical Checklist

- Query count is reasonable.
- Large lists are paginated.
- Relationships are eager loaded.
- Filters and sorts are indexed where needed.
- Response payloads are compact.
- Frontend route/component is lazy loaded when heavy.
- Search is debounced.
- Images and assets are appropriately sized.

## Production Recommendations

- Use Laravel config, route, and view caching.
- Monitor slow query logs.
- Use queue workers for email, notifications, reports, and imports.
- Set memory and timeout expectations for long-running jobs.
- Run production frontend builds and review bundle size for large changes.

