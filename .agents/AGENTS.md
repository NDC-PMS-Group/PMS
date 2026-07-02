# NDC PMS Workspace Rules

These project-scoped rules ensure design consistency, robust state management, and smooth user interactions across the NDC Project Management System.

## 1. SPA Route Reactivity & State Resets
* **Constraint**: When navigating from a sub-filtered view (e.g., project-specific task view `/projects/:id/tasks`) back to a global view (e.g., `/tasks`), always explicitly reset state/store filters (e.g., `resetFilters()`).
* **Implementation**: Listen to route transitions or use router hooks to clean up active filters to prevent stale data leaking across screens.

## 2. Theme-Aware Third-Party Components
* **Constraint**: All third-party libraries (e.g., Frappe Gantt, FullCalendar, Chart.js) must dynamically adapt to dark and light modes.
* **Implementation**:
  - Never hardcode color properties directly in JavaScript configuration objects.
  - Abstract theme colors into CSS variables (e.g., `--gantt-bg`, `--fc-border-color`) matched to the app's global styling classes (e.g., `:global(.dark)`).
  - Test deep style overrides against both light and dark mode classes to eliminate disruptive default backgrounds (e.g., white grids in dark mode).

## 3. DOM Auto-Scrolling Timing & Transition Delays
* **Constraint**: Bounding client rectangles (`getBoundingClientRect`) and scroll offsets will yield stale or zero values if computed while elements are animating (e.g., `fadeUp` tab transitions).
* **Implementation**: When auto-focusing or scrolling to elements (e.g., active timeline phase inside a tab), wait for both Vue's `nextTick` and an additional `300ms` delay (`setTimeout`) to let CSS transition animations fully settle before measuring positions.

## 4. Scrollability Affordance for Tall Dialogs
* **Constraint**: Users often miss form fields located below the fold in tall modals (e.g., Project Creation dialog).
* **Implementation**: Introduce clear scrollability hints (such as a subtle bouncing arrow/chevron overlay or a gradient fade at the bottom) on containers when the form height exceeds the viewport, providing a clear visual cue that additional input fields exist below the fold.

## 5. Scoping Dynamic Third-Party Style Rules
* **Constraint**: Do not import CSS files from external libraries (e.g. `frappe-gantt`, `leaflet`) inside Vue components' `<style scoped>` tags. Doing so causes the selectors to compile with scope-specific attributes (e.g., `data-v-xxxx`), which will fail to match dynamically generated nodes.
* **Implementation**:
  - Always import external third-party CSS globally (e.g. in `main.ts` or in `src/assets/scss/tailwind.scss`).
  - To override rules of dynamically generated elements inside a scoped component, wrap target selectors with Vue's `:deep()` combinator (e.g. `.gantt-container :deep(.gantt) .grid-row`).

## 6. Predefined vs. Dynamic Reporting Layouts
* **Constraint**: When designing data export or reporting pages, avoid implementing complex query builders or high-density custom filters by default.
* **Implementation**: Prefer presenting a clean list of predefined report types (e.g. register, financial, jobs) which auto-populate a curated set of default columns and expose only basic date-range and keyword search controls.

## 7. User Registration & Sign-Up Forms
* **Constraint**: Do not require duplicate fields (such as `Confirm Password` or `Confirm Email`) on registration forms. Instead, use inline visibility toggles (e.g., Show/Hide Password) and ensure full browser autofill integration with correct attributes.
* **Implementation**:
  - Always use `autocomplete="username"` for email inputs, and `autocomplete="new-password"` for password inputs in sign-up flows.
  - Use `type="email"`, `type="tel"` with `autocomplete="tel"` and `inputmode="tel"` for appropriate mobile keyboards.
  - Provide an eye-toggle button to show/hide typed passwords.
  - Populate confirmation fields programmatically before payload transmission if the API requires confirmation fields.
