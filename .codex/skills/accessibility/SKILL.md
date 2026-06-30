---
name: accessibility
description: Build accessible interfaces using WCAG guidance, keyboard navigation, semantic HTML, labels, focus states, contrast, and ARIA attributes.
---

# Accessibility Skill

## When To Use

Use this skill for forms, modals, navigation, tables, dashboards, custom controls, keyboard interactions, focus management, color contrast, semantic HTML, ARIA usage, and accessibility reviews.

## Best Practices

- Follow WCAG guidance for perceivable, operable, understandable, and robust interfaces.
- Use semantic HTML before ARIA.
- Provide labels for every form control.
- Make all interactive elements keyboard accessible.
- Keep visible focus states.
- Use sufficient color contrast.
- Do not rely on color alone for status.
- Use ARIA attributes only when native semantics are insufficient.
- Manage focus when opening and closing modals.
- Provide accessible names for icon-only buttons.

## Things To Avoid

- Do not use clickable `div` or `span` elements when a button or link is appropriate.
- Do not remove outlines without replacing them with visible focus styles.
- Do not add ARIA roles that conflict with native semantics.
- Do not trap keyboard focus accidentally.
- Do not hide validation errors from assistive technology.

## Common Mistakes

- Icon buttons without labels.
- Modal focus remaining behind the overlay.
- Table headers not associated with table data.
- Low-contrast badges and placeholder text.
- Form errors displayed visually but not connected to inputs.

## Practical Checklist

- Page uses semantic regions and headings.
- Controls have accessible names.
- Forms have labels and error associations.
- Keyboard navigation reaches all actions.
- Focus order is logical.
- Focus is visible.
- Contrast is sufficient.
- ARIA is minimal and correct.

## Production Recommendations

- Test with keyboard only.
- Test responsive zoom and long text.
- Use automated accessibility checks as a first pass.
- Manually inspect complex components such as modals, dropdowns, tabs, and tables.
- Include accessibility acceptance criteria for new UI workflows.

