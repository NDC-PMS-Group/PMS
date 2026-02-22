import { test, expect, request as playwrightRequest } from '@playwright/test';

type JsonRecord = Record<string, unknown>;

function normalizeBaseUrl(url: string): string {
  return url.endsWith('/') ? url.slice(0, -1) : url;
}

function extractList(payload: unknown): JsonRecord[] {
  if (Array.isArray(payload)) return payload as JsonRecord[];
  if (!payload || typeof payload !== 'object') return [];

  const data = (payload as JsonRecord).data;
  if (Array.isArray(data)) return data as JsonRecord[];
  if (data && typeof data === 'object' && Array.isArray((data as JsonRecord).data)) {
    return (data as JsonRecord).data as JsonRecord[];
  }

  return [];
}

test.describe('Staging E2E', () => {
  const base = process.env.STAGING_BASE_URL;
  const email = process.env.STAGING_USER_EMAIL;
  const password = process.env.STAGING_USER_PASSWORD;

  test.skip(!base || !email || !password, 'Missing STAGING_* environment variables.');

  test('login + create project + verify pending approvals', async ({ page }) => {
    const baseUrl = normalizeBaseUrl(base as string);

    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });

    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    const submitButton = page.locator('button[type="submit"]').first();

    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();

    await emailInput.fill(email as string);
    await passwordInput.fill(password as string);

    const loginResponsePromise = page.waitForResponse((res) => {
      return res.url().includes('/api/login') && res.request().method() === 'POST';
    });

    await submitButton.click();

    const loginResponse = await loginResponsePromise;
    expect(loginResponse.status()).toBe(200);

    const loginBody = (await loginResponse.json()) as JsonRecord;
    const token = loginBody.token as string | undefined;
    expect(token, 'Login token should be present').toBeTruthy();

    const api = await playwrightRequest.newContext({
      baseURL: baseUrl,
      extraHTTPHeaders: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    });

    const [stagesRes, statusesRes, typesRes, industriesRes, sectorsRes] = await Promise.all([
      api.get('/api/lookup/project-stages'),
      api.get('/api/lookup/project-statuses'),
      api.get('/api/lookup/project-types'),
      api.get('/api/lookup/industries'),
      api.get('/api/lookup/sectors'),
    ]);

    expect(stagesRes.status()).toBe(200);
    expect(statusesRes.status()).toBe(200);
    expect(typesRes.status()).toBe(200);
    expect(industriesRes.status()).toBe(200);
    expect(sectorsRes.status()).toBe(200);

    const stages = extractList(await stagesRes.json());
    const statuses = extractList(await statusesRes.json());
    const projectTypes = extractList(await typesRes.json());
    const industries = extractList(await industriesRes.json());
    const sectors = extractList(await sectorsRes.json());

    const proposalStage = stages.find((s) => s.name === 'Proposal') ?? stages[0];
    const defaultStatus = statuses.find((s) => s.name === 'Pending') ?? statuses[0];

    expect(proposalStage, 'Expected at least one project stage').toBeTruthy();
    expect(defaultStatus, 'Expected at least one project status').toBeTruthy();
    expect(projectTypes.length, 'Expected at least one project type').toBeGreaterThan(0);
    expect(industries.length, 'Expected at least one industry').toBeGreaterThan(0);
    expect(sectors.length, 'Expected at least one sector').toBeGreaterThan(0);

    const createPayload = {
      title: `E2E Project ${Date.now()}`,
      description: 'Created by Playwright staging test',
      project_type_id: projectTypes[0].id as number,
      industry_id: industries[0].id as number,
      sector_id: sectors[0].id as number,
      current_stage_id: proposalStage.id as number,
      status_id: defaultStatus.id as number,
      proposal_date: new Date().toISOString().slice(0, 10),
      is_svf: false,
    };

    const createProjectRes = await api.post('/api/projects', { data: createPayload });
    expect(createProjectRes.status()).toBe(200);

    const createdProject = (await createProjectRes.json()) as JsonRecord;
    const projectData = (createdProject.data ?? createdProject) as JsonRecord;
    const createdProjectId = projectData.id as number | undefined;
    expect(createdProjectId, 'Expected created project id').toBeTruthy();

    const pendingRes = await api.get('/api/approvals/pending');
    expect(pendingRes.status()).toBe(200);

    const pendingBody = (await pendingRes.json()) as JsonRecord;
    const pendingItems = extractList(pendingBody);
    const matching = pendingItems.find((item) => item.project_id === createdProjectId);
    expect(matching, 'Created project should appear in pending approvals').toBeTruthy();
  });
});

