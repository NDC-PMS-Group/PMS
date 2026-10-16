# PMS
# PMS
# PMS

### NDC code and location rollout (2026-10-16)

Project intake and legacy imports now share the `NDC-<year>-<number>` sequence. Before running the two `2026_10_16` migrations, back up the database and pause API writes. The code migration includes archived/deleted projects, preserves an available year/number, and assigns a new number when prefixes previously shared one. `project_code_renames` retains each project ID and old/new code for reconciliation and rollback; existing IDs, workflow links, historical audit messages and uploaded files are preserved. Rollback refuses to overwrite subsequently changed or reused codes. Do not reseed production.

Additional locations accept an optional latitude/longitude pair (latitude -90 to 90; longitude -180 to 180); sites without coordinates remain valid but produce no map pin. Record Classification accepts `other` with required `record_type_other` text, and retains classification locking after approval/release records exist.

`portfolio_summary.total_ndc_investment_by_currency` sums the form's `ndc_participation` across the dashboard's visible/filtered records. It excludes total project cost and other financing. `released_funds` continues to sum only actual `released` fund records. Existing investment-only API totals retain their previous meaning. Publish the rebuilt frontend after the migrations, refresh Laravel caches, restore API access, and verify unique NDC codes and the updated dashboard/form.
