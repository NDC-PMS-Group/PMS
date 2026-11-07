# PMS
# PMS
# PMS

### Account manuals and video tutorials

Signed-in users can open **Manuals & Video Tutorials** from the sidebar at `/training`. The API selects the material using the user's role name, including when role IDs differ between databases. The 11 supported roles each have their own PDF and captioned MP4. Custom roles show an unavailable message until their materials are provided.

Install the contents of `output/tutorial/by-role/` under the backend's private `storage/app/private/training/` directory, preserving the role directories and filenames. For example, the Finance files are `training/finance/NDC_PMS_Finance_User_Manual.pdf` and `training/finance/NDC_PMS_Finance_Tutorial.mp4` within the local storage disk. Keep this directory outside the public web root. Media is deployed separately from Git and the frontend bundle.

The authenticated `/api/training` endpoint issues links valid for one hour. A link grants access to that file until it expires and should be treated as a temporary access credential. The file endpoint also checks that the issuing account remains active and retains its role. Native PDF viewing and MP4 byte ranges are supported. Users can refresh the links on the Training page.

Deploy the controller and API routes, copy the private media, refresh the existing Laravel route cache if enabled, and publish a coordinated frontend build. No database migration or seeding is required for this feature. Build with the production API origin, or an empty `VITE_APP_BASE_URL` for same-origin hosting. Verify a PDF, video playback and seeking using each account type after deployment. Focused API verification is `php artisan test --compact --filter=TrainingApiTest` from `pms-backend`.

### NDC code and location rollout (2026-10-16)

Project intake and legacy imports now share the `NDC-<year>-<number>` sequence. Before running the two `2026_10_16` migrations, back up the database and pause API writes. The code migration includes archived/deleted projects, preserves an available year/number, and assigns a new number when prefixes previously shared one. `project_code_renames` retains each project ID and old/new code for reconciliation and rollback; existing IDs, workflow links, historical audit messages and uploaded files are preserved. Rollback refuses to overwrite subsequently changed or reused codes. Do not reseed production.

Additional locations accept an optional latitude/longitude pair (latitude -90 to 90; longitude -180 to 180); sites without coordinates remain valid but produce no map pin. Record Classification accepts `other` with required `record_type_other` text, and retains classification locking after approval/release records exist.

`portfolio_summary.total_ndc_investment_by_currency` sums the form's `ndc_participation` across the dashboard's visible/filtered records. It excludes total project cost and other financing. `released_funds` continues to sum only actual `released` fund records. Existing investment-only API totals retain their previous meaning. Publish the rebuilt frontend after the migrations, refresh Laravel caches, restore API access, and verify unique NDC codes and the updated dashboard/form.

### SOI development alignment with 2026 Revision 05

Reference: `SOI - 1 Project Development - 2026 Rev. 05-final copy.docx` supplied in Downloads. The in-app SOI procedure guide maps sections 7 (traditional equity), 8 (NDC on its own), and 9 (JV). AO maps to Project Officer; AGM maps to Workgroup Head. JV retains stable workflow/gate keys and requires the applicable DEPDev guideline/version and compliance checklist; the reference does not itself specify a replacement JV procurement procedure.

The two `2026_11_03` migrations add planned Board presentation and deferral authorization fields, then align requirement metadata and routing labels without deleting project IDs, evidence or decision history. Take a private DB/source backup and pause writes before deployment. The policy data migration deliberately has no destructive reverse operation: restore its pre-deployment database backup to undo metadata alignment. Do not run a production seeder.

* Initial submission for non-SVF traditional proposals requires LOI and concept/pitch deck, with later documents requested after qualification. Additional source-specific requirements include the updated Project Information Memorandum and any absent financial-model/ownership evidence.
* Initial evaluation, qualification reply, completeness review and AGM-endorsed due diligence are linked to their review gates. ManCom may proceed with a documented AGM-authorized temporary deferral; Board action requires the full package. A deferral needs a reason, authorization, a planned Board presentation date and a deadline at least 14 days earlier. Future meeting dates cannot bypass the 14-day period at actual Board action.
* Conditional ManCom/Board decisions remain at the same step until a compliance/re-presentation note and a subsequent decision are recorded. Decisions append to history. Deferral, disapproval and return for revision are separate actions; disapproval retains all project records. Unresolved conditional approvals do not count as final Board approval or implementation readiness.
* Keep source and built assets coordinated. Verify migration status, record counts, NDC code uniqueness, new fields and the live SOI guide after deployment.

Focused proof: `php artisan test --compact --filter='ProjectApprovalWorkflowApiTest|ProjectCodeGenerationApiTest|DashboardDecisionSupportApiTest|LegacyProjectImportApiTest|QuarterlyMonitoringReportApiTest|ProjectReportFilterApiTest|test_rev05'` from `pms-backend`; `node --test e2e/map-locations.test.cjs` and `npm run build` from `pms-frontend`. Full backend verification after the role-identity and account-seeding repairs: `php artisan test --compact` passes 133 tests and 898 assertions. Authorization uses role identity/explicit permissions instead of treating numeric role IDs as administrator status. Deadline reminders exclude unrelated roles, and demo seeding preserves existing account IDs and refuses conflicting identities. Never run demo seeders against production.
