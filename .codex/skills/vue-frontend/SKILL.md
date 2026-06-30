---
name: vue-frontend
description: Build Vue 3 frontend features with Composition API, script setup, Pinia, Vue Router, Axios, reusable components, and complete UI states.
---

# Vue Frontend Skill

## When To Use

Use this skill for Vue 3 pages, components, stores, routes, composables, Axios integrations, Vite configuration, lazy loading, responsive layouts, and frontend state management.

## Best Practices

- Use Composition API and `script setup`.
- Keep components focused and composable.
- Extract repeated stateful logic into composables.
- Use Pinia stores for shared domain state, authentication state, and cross-page data.
- Use Vue Router route-level lazy loading for large pages.
- Keep API calls in existing services or stores when that is the project pattern.
- Model loading, empty, error, success, disabled, and unauthorized states.
- Use optimistic UI only when rollback behavior is clear.
- Use typed props and emits where TypeScript is available.
- Keep responsive layouts stable with explicit constraints and sensible breakpoints.
- Split large components when readability or reuse improves.

## Things To Avoid

- Do not duplicate API calls across multiple components when a store/composable already exists.
- Do not mutate props directly.
- Do not hide backend validation errors behind generic messages.
- Do not create global state for local-only UI state.
- Do not use watchers for derived values that should be computed properties.
- Do not ship placeholder-only screens for requested workflows.

## Common Mistakes

- Forgetting loading and error states.
- Over-fetching on every render or route update.
- Creating components that know too much about unrelated domains.
- Breaking mobile layouts with fixed widths.
- Using raw HTML without sanitization.
- Not clearing stale store state between project/detail views.

## Practical Checklist

- Component follows existing style and naming.
- API integration uses existing Axios/store patterns.
- Loading, empty, error, success, and disabled states are present.
- Form fields show validation feedback.
- UI works on mobile and desktop.
- Route is lazy loaded if heavy.
- Repeated logic is extracted only when useful.
- Accessibility basics are present: labels, keyboard access, focus states.

## Production Recommendations

- Keep bundles small through route-level code splitting.
- Lazy load heavy charts, maps, editors, and file preview tools.
- Avoid unnecessary deep reactivity for large datasets.
- Use pagination or virtualization for large lists.
- Keep environment-specific API URLs in Vite environment variables.

