export const BASELINE_PROJECT_CATEGORIES = [
  { value: 'traditional_external', label: 'Traditional / External Investment', workflowKey: 'bdg_investment' },
  { value: 'startup_venture', label: 'Startup Venture', workflowKey: 'bdg_svf' },
  { value: 'joint_venture', label: 'Joint Venture', workflowKey: 'spg_jv' },
  { value: 'ndc_initiated', label: 'NDC-Initiated', workflowKey: 'spg_ndc_own' },
] as const;

export const projectCategoryKey = (track?: string | null, isStartup = false) => {
  if (isStartup || track === 'bdg_svf' || track === 'startup_venture') return 'startup_venture';
  if (['bdg_investment', 'spg_traditional', 'traditional_external'].includes(track || '')) return 'traditional_external';
  if (track === 'spg_jv' || track === 'joint_venture') return 'joint_venture';
  if (track === 'spg_ndc_own' || track === 'ndc_initiated') return 'ndc_initiated';
  return track || 'traditional_external';
};

export const projectCategoryLabel = (track?: string | null, isStartup = false) => {
  const key = projectCategoryKey(track, isStartup);
  return BASELINE_PROJECT_CATEGORIES.find(category => category.value === key)?.label
    || key.replaceAll('_', ' ').replace(/\b\w/g, letter => letter.toUpperCase());
};
