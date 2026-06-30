export const SOI_SECTION_ORDER = [
  'intake',
  'requirements',
  'due_diligence',
  'management_review',
  'board_approval',
  'agreement_fund_release',
  'implementation_monitoring',
  'post_investment_strategy',
  'divestment',
  'completion',
];

export const SOI_SECTION_LABELS: Record<string, string> = {
  intake: 'Intake',
  requirements: 'Requirements',
  due_diligence: 'Due Diligence',
  management_review: 'Management Review',
  board_approval: 'Board Approval',
  agreement_fund_release: 'Agreement and Fund Release',
  implementation_monitoring: 'Implementation and Monitoring',
  post_investment_strategy: 'Post-Investment Strategy',
  divestment: 'Divestment',
  completion: 'Completion',
};

export type SoiPhaseDefinition = {
  key: string;
  label: string;
  stepOrders: number[];
  taskPrefixes: string[];
};

export type SoiTaskLike = {
  id: number;
  title?: string | null;
  description?: string | null;
  task_type?: string | null;
  soi_section?: string | null;
  status?: string | null;
  due_date?: string | null;
  priority?: string | null;
  progress_percentage?: number | null;
  subtasks?: SoiTaskLike[];
};

export type SoiTaskSection<T extends SoiTaskLike = SoiTaskLike> = {
  key: string;
  label: string;
  ordinal: string;
  tasks: T[];
  checklistItems: SoiTaskLike[];
  totalChecklist: number;
  completedChecklist: number;
  progress: number;
};

export const SOI_TRACK_PHASE_DEFINITIONS: Record<string, SoiPhaseDefinition[]> = {
  spg_jv: [
    { key: 'spg_jv_concept', label: 'JV Concept and ManCom Approval to Proceed', stepOrders: [1, 2], taskPrefixes: ['1.'] },
    { key: 'spg_jv_study', label: 'Consultancy Procurement and Study', stepOrders: [3], taskPrefixes: ['2.'] },
    { key: 'spg_jv_mancom_board', label: 'ManCom and Board Approval of JV Project', stepOrders: [4, 5], taskPrefixes: ['3.'] },
    { key: 'spg_jv_neda_jvsc', label: 'NEDA-ICC and JV-SC', stepOrders: [6, 7], taskPrefixes: ['4.'] },
    { key: 'spg_jv_selection_signing', label: 'JV Partner Selection, Award, and JVA Signing', stepOrders: [8, 9, 10], taskPrefixes: ['5.'] },
  ],
  spg_ndc_own: [
    { key: 'spg_own_concept', label: 'Project Concept and ManCom Approval to Proceed', stepOrders: [1, 2], taskPrefixes: ['1.'] },
    { key: 'spg_own_study', label: 'Consultancy Procurement and Study', stepOrders: [3], taskPrefixes: ['2.'] },
    { key: 'spg_own_mancom', label: 'ManCom Project Decision', stepOrders: [4], taskPrefixes: ['3.'] },
    { key: 'spg_own_board', label: 'Board Approval', stepOrders: [5], taskPrefixes: ['4.'] },
    { key: 'spg_own_ded', label: 'DED / Construction Procurement and Agreement', stepOrders: [6], taskPrefixes: ['5.'] },
    { key: 'spg_own_turnover', label: 'Construction Implementation and Turn-over', stepOrders: [7], taskPrefixes: ['6.'] },
  ],
};

export function formatSoiSectionLabel(key?: string | null): string {
  if (!key) return 'Unsectioned';
  return SOI_SECTION_LABELS[key] || String(key).split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

export function normalizeSoiSection(value?: string | null, fallback?: string | null): string {
  const normalized = String(value || '').toLowerCase();
  if (SOI_SECTION_ORDER.includes(normalized)) return normalized;

  const text = `${normalized} ${String(fallback || '').toLowerCase()}`;
  if (text.includes('divest')) return 'divestment';
  if (text.includes('post-investment') || text.includes('post investment')) return 'post_investment_strategy';
  if (text.includes('monitor') || text.includes('turn-over') || text.includes('turnover') || text.includes('construction implementation')) return 'implementation_monitoring';
  if (text.includes('board') || text.includes('neda') || text.includes('icc') || text.includes('selection and award') || text.includes('jv partner selection')) return 'board_approval';
  if (text.includes('fund') || text.includes('agreement') || text.includes('jva') || text.includes('construction')) return 'agreement_fund_release';
  if (text.includes('mancom') || text.includes('workgroup') || text.includes('agm') || normalized === 'approval') return 'management_review';
  if (text.includes('diligence') || text.includes('evaluation') || text.includes('validation') || text.includes('study')) return 'due_diligence';
  if (text.includes('requirement') || text.includes('completeness') || text.includes('checklist') || text.includes('response letter')) return 'requirements';
  if (text.includes('completion')) return 'completion';
  if (text.includes('submission') || text.includes('intake') || text.includes('concept') || text.includes('kyc') || text.includes('loi')) return 'intake';

  return 'intake';
}

export function resolveSoiTaskSection(task: SoiTaskLike): string {
  return normalizeSoiSection(task.soi_section || task.task_type, task.title);
}

export function resolveSoiTaskGroupKey(task: SoiTaskLike, processTrack?: string | null): string {
  const phaseDefinitions = SOI_TRACK_PHASE_DEFINITIONS[String(processTrack || '').toLowerCase()];
  if (phaseDefinitions) {
    const title = String(task.title || '').trim().toLowerCase();
    const phase = phaseDefinitions.find((item) =>
      item.taskPrefixes.some((prefix) => title.startsWith(prefix.toLowerCase()))
    );
    if (phase) return phase.key;
  }

  return resolveSoiTaskSection(task);
}

export function getTaskChecklistItems(task: SoiTaskLike): SoiTaskLike[] {
  return task.subtasks?.length ? task.subtasks : [task];
}

export function sortSoiTasks<T extends SoiTaskLike>(tasks: T[]): T[] {
  return [...tasks].sort((a, b) => {
    const aDue = a.due_date ? new Date(a.due_date).getTime() : Number.POSITIVE_INFINITY;
    const bDue = b.due_date ? new Date(b.due_date).getTime() : Number.POSITIVE_INFINITY;
    if (aDue !== bDue) return aDue - bDue;
    const aPriority = priorityRank(a.priority);
    const bPriority = priorityRank(b.priority);
    if (aPriority !== bPriority) return bPriority - aPriority;
    return Number(a.id || 0) - Number(b.id || 0);
  });
}

export function buildSoiTaskSections<T extends SoiTaskLike>(tasks: T[], processTrack?: string | null): SoiTaskSection<T>[] {
  const sortedTasks = sortSoiTasks(tasks);
  const phaseDefinitions = SOI_TRACK_PHASE_DEFINITIONS[String(processTrack || '').toLowerCase()];

  if (phaseDefinitions) {
    return phaseDefinitions
      .map((phase, index) => buildSection(
        phase.key,
        phase.label,
        `Phase ${index + 1}`,
        sortedTasks.filter((task) => resolveSoiTaskGroupKey(task, processTrack) === phase.key)
      ))
      .filter((section) => section.tasks.length);
  }

  const keys = new Set<string>();
  sortedTasks.forEach((task) => keys.add(resolveSoiTaskSection(task)));
  const orderedKeys = [
    ...SOI_SECTION_ORDER.filter((key) => keys.has(key)),
    ...Array.from(keys).filter((key) => !SOI_SECTION_ORDER.includes(key)),
  ];

  return orderedKeys.map((key, index) =>
    buildSection(key, formatSoiSectionLabel(key), `Section ${index + 1}`, sortedTasks.filter((task) => resolveSoiTaskSection(task) === key))
  );
}

function buildSection<T extends SoiTaskLike>(key: string, label: string, ordinal: string, tasks: T[]): SoiTaskSection<T> {
  const checklistItems = tasks.flatMap((task) => getTaskChecklistItems(task));
  const completedChecklist = checklistItems.filter((task) => task.status === 'completed').length;
  const progress = checklistItems.length ? Math.round((completedChecklist / checklistItems.length) * 100) : 0;

  return {
    key,
    label,
    ordinal,
    tasks,
    checklistItems,
    totalChecklist: checklistItems.length,
    completedChecklist,
    progress,
  };
}

function priorityRank(priority?: string | null): number {
  if (priority === 'critical') return 6;
  if (priority === 'urgent') return 5;
  if (priority === 'high') return 4;
  if (priority === 'medium' || priority === 'normal') return 3;
  if (priority === 'low') return 1;
  return 2;
}
