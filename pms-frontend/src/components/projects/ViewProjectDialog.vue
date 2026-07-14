<!-- src/components/projects/ViewProjectDialog.vue -->
<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="modal-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="handleClose">
        <div class="modal-panel">
          <!-- Loading -->
          <div v-if="loading" class="loading-state">
            <div class="spinner-lg"></div><span>Loading project...</span>
          </div>
          <div v-else-if="loadError" class="loading-state">
            <AlertCircleIcon class="ep-icon" />
            <span>{{ loadError }}</span>
            <div class="load-actions">
              <button class="retry-btn" @click="loadDialogData">Retry</button>
              <button class="close-btn" @click="handleClose">Close</button>
            </div>
          </div>

          <template v-else-if="project">
            <!-- Hero -->
            <div class="hero" :style="{ background: heroGradient }">
              <div class="hero-top">
                <div class="hero-badges">
                  <span class="h-code">{{ project.project_code }}</span>
                  <span v-if="project.is_svf" class="h-badge svf">SVF</span>
                  <span v-if="project.is_overdue" class="h-badge overdue">Overdue</span>
                  <span v-if="project.is_archived" class="h-badge archived">Archived</span>
                </div>
                <div class="hero-actions">
                  <button
                    v-if="canSubmitProposalAction"
                    class="h-submit"
                    :disabled="proposalSubmitting || documentSubmitting"
                    @click="submitRequirementPackage"
                    :title="submitPackageHelpText"
                  >
                    <CheckCircleIcon class="icon" />
                    {{ proposalSubmitting || documentSubmitting ? 'Submitting...' : 'Submit Proposal' }}
                  </button>
                  <button v-if="canEditProjectAction" class="h-btn" @click="emit('edit', project)" title="Edit">
                    <EditIcon class="icon" />
                  </button>
                  <button class="h-close" @click="handleClose"><XIcon class="icon" /></button>
                </div>
              </div>
              <h1 class="hero-title">{{ project.title }}</h1>
              <div class="hero-meta">
                <span class="h-pill" v-if="project.current_stage"><LayersIcon class="pi" />{{ project.current_stage.name }}</span>
                <span class="h-pill status-pill" :style="heroStatusStyle" v-if="project.status"><span class="sdot"></span>{{ project.status.name }}</span>
                <span class="h-pill" v-if="project.project_type"><BriefcaseIcon class="pi" />{{ project.project_type.name }}</span>
              </div>
              <div v-if="project.progress_percentage !== undefined" class="hero-prog">
                <div class="hp-track"><div class="hp-fill" :style="{ width: `${project.progress_percentage}%` }"></div></div>
                <span class="hp-label">{{ project.progress_percentage }}% complete</span>
              </div>
            </div>

            <!-- Dashboard Split Layout Container -->
            <div class="project-dashboard-layout">
              <!-- Left Column: Tabs Navigation and Body Content -->
              <div class="main-content-column">
                <div class="tab-scroll-shell">
                  <button class="tab-scroll-btn" type="button" title="Previous tabs" @click="scrollTabs('left')">
                    <ChevronLeftIcon class="icon" />
                  </button>
                  <div ref="tabNavRef" class="tab-nav" aria-label="Project sections">
                    <button v-for="tab in tabs" :key="tab.id" class="tab-btn" :class="{ active: activeTab === tab.id }" @click="activeTab = tab.id">
                      <component :is="tab.icon" class="ti" />{{ tab.label }}
                      <span v-if="tab.count !== undefined" class="tc">{{ tab.count }}</span>
                    </button>
                  </div>
                  <button class="tab-scroll-btn" type="button" title="More tabs" @click="scrollTabs('right')">
                    <ChevronRightIcon class="icon" />
                  </button>
                </div>

                <!-- Content -->
                <div class="tab-body" ref="tabBodyRef">
              <div v-if="canSubmitProposalAction" class="submission-callout">
                <div>
                  <strong>Draft proposal</strong>
                  <span>{{ submitPackageHelpText }}</span>
                  <small v-if="initialPackageMissing.length" class="submission-missing">
                    Missing: {{ initialPackageMissing.join(', ') }}
                  </small>
                </div>
                <button
                  class="submit-callout-btn"
                  :disabled="proposalSubmitting || documentSubmitting"
                  @click="submitRequirementPackage"
                >
                  <CheckCircleIcon class="icon" />
                  {{ proposalSubmitting || documentSubmitting ? 'Submitting...' : 'Submit for Review' }}
                </button>
              </div>

              <!-- Overview -->
              <!-- Overview -->
              <div v-show="activeTab === 'overview'" class="tab-pane">
                <div v-if="!isExternalProponentUser || monitoringIsActive" class="monitoring-gate" :class="project.monitoring_status || 'closed'">
                  <div class="monitoring-gate-copy">
                    <span class="gate-kicker">Implementation lifecycle</span>
                    <strong>{{ monitoringGateTitle }}</strong>
                    <p>{{ monitoringGateDescription }}</p>
                    <div v-if="monitoringIsActive" class="gate-meta">
                      <span v-if="project.monitoring_due_date">Due {{ fmtDate(project.monitoring_due_date) }}</span>
                      <span v-if="project.monitoring_activated_by">Opened by {{ project.monitoring_activated_by.full_name || project.monitoring_activated_by.name }}</span>
                    </div>
                  </div>
                  <div v-if="canManagePostMonitoringAction" class="gate-actions" style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem;">
                    <button 
                      v-if="project.monitoring_status !== 'active'" 
                      class="add-btn" 
                      :disabled="!isProjectMonitoringEligible"
                      :class="{ 'opacity-50 cursor-not-allowed': !isProjectMonitoringEligible }"
                      @click="isProjectMonitoringEligible && (monitoringActivationOpen = !monitoringActivationOpen)"
                    >
                      <ActivityIcon class="icon" /> Open Monitoring
                    </button>
                    <button v-else-if="project.monitoring_submission_status === 'accepted'" class="ghost-action danger-text" :disabled="monitoringSaving" @click="closeMonitoring">
                      Close Period
                    </button>
                    <p v-if="project.monitoring_status !== 'active' && !isProjectMonitoringEligible" class="gate-error-note">
                      ⚠️ Monitoring can only be opened after project approval.
                    </p>
                  </div>
                  <div v-if="monitoringActivationOpen && canManagePostMonitoringAction" class="monitoring-activation-form">
                    <label>
                      <span>Compliance due date</span>
                      <input v-model="monitoringActivationForm.due_date" type="date" class="member-input" />
                    </label>
                    <label class="span-2">
                      <span>Instructions to the proponent</span>
                      <textarea v-model="monitoringActivationForm.instructions" rows="3" class="member-input monitor-textarea" placeholder="Specify the monitoring period, indicators, evidence, and reporting instructions."></textarea>
                    </label>
                    <label class="monitor-check compact-check">
                      <input v-model="monitoringActivationForm.proponent_access" type="checkbox" />
                      <span><strong>Request proponent compliance</strong><small>Opens Post-Monitoring for the proponent and sends email.</small></span>
                    </label>
                    <div class="activation-actions">
                      <button class="ghost-action" @click="monitoringActivationOpen = false">Cancel</button>
                      <button class="add-btn" :disabled="monitoringSaving" @click="activateMonitoring">
                        {{ monitoringSaving ? 'Opening...' : 'Open & Notify' }}
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Premium Dossier Sheet Layout -->
                <div class="project-dossier-sheet">
                  <!-- Dossier Header Section -->
                  <div class="dossier-header-block">
                    <div class="dossier-badge-row">
                      <span class="dossier-confidential">OFFICIAL USE ONLY</span>
                      <span class="dossier-date">Applied: {{ fmtDate(project.date_of_application) || 'N/A' }}</span>
                    </div>
                    <h2 class="dossier-title">PROJECT DOSSIER</h2>
                    <p class="dossier-subtitle">Detailed Proposal Summary Sheet & Strategic Indicators</p>
                  </div>

                  <!-- Dossier Info Grid -->
                  <div class="dossier-grid-row">
                    <div class="dossier-grid-item">
                      <span>PROJECT NAME</span>
                      <strong>{{ project.title }}</strong>
                    </div>
                    <div class="dossier-grid-item">
                      <span>PROJECT CODE</span>
                      <strong class="font-mono text-primary">{{ project.project_code }}</strong>
                    </div>
                    <div class="dossier-grid-item">
                      <span>PROPONENT</span>
                      <strong>{{ project.proponent_name || 'N/A' }}</strong>
                    </div>
                    <div class="dossier-grid-item">
                      <span>CURRENT STAGE</span>
                      <strong>{{ project.current_stage?.name || 'N/A' }}</strong>
                    </div>
                  </div>

                  <!-- Segment 1: Rationale & Project Description -->
                  <div class="dossier-section">
                    <h3 class="dossier-section-title"><FileTextIcon class="di" /> 1. Project Concept & Rationale</h3>
                    <div class="dossier-content-card">
                      <p class="dossier-text">{{ project.description || 'No description provided.' }}</p>
                      <div v-if="project.project_rationale" class="dossier-sub-field mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <strong>Project Rationale:</strong>
                        <p class="dossier-text mt-1 text-gray-700 dark:text-gray-300">{{ project.project_rationale }}</p>
                      </div>
                    </div>
                  </div>

                  <!-- Segment 2: Location & Spatial Profile -->
                  <div v-if="project.location_address || locationDetailRows.length || hasCoordinates(project)" class="dossier-section">
                    <h3 class="dossier-section-title"><MapPinIcon class="di" /> 2. Location & Spatial Profile</h3>
                    <div class="dossier-content-card">
                      <p v-if="project.location_address" class="dossier-text"><strong>Address:</strong> {{ project.location_address }}</p>
                      <div v-if="locationDetailRows.length" class="location-grid mt-3">
                        <div v-for="row in locationDetailRows" :key="row.label" class="location-field">
                          <span>{{ row.label }}</span>
                          <strong>{{ row.value }}</strong>
                        </div>
                      </div>
                      <div v-if="hasCoordinates(project)" class="coord-row mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <div class="coord-chip"><strong>Lat</strong><span>{{ fmtCoord(project.location_lat) }}</span></div>
                        <div class="coord-chip"><strong>Lng</strong><span>{{ fmtCoord(project.location_lng) }}</span></div>
                      </div>
                    </div>
                  </div>

                  <!-- Segment 3: Strategic Alignment Indicators (SOI) -->
                  <div v-if="hasSoiDetails" class="dossier-section">
                    <h3 class="dossier-section-title"><ListChecksIcon class="di" /> 3. Strategic Alignment Indicators</h3>
                    <div class="dossier-content-card">
                      <div v-if="project.ndc_investment_criteria?.length" class="criteria-chips mb-4">
                        <span v-for="criterion in project.ndc_investment_criteria" :key="criterion">{{ formatRequirementStatus(criterion) }}</span>
                      </div>
                      <div class="dossier-q-and-a">
                        <div v-if="project.target_beneficiaries" class="dossier-sub-field">
                          <strong>Target Beneficiaries:</strong>
                          <p class="dossier-text mt-1 text-gray-700 dark:text-gray-300">{{ project.target_beneficiaries }}</p>
                        </div>
                        <div v-if="project.expected_benefits" class="dossier-sub-field mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                          <strong>Expected Benefits & Outcomes:</strong>
                          <p class="dossier-text mt-1 text-gray-700 dark:text-gray-300">{{ project.expected_benefits }}</p>
                        </div>
                        <div v-if="project.risk_analysis" class="dossier-sub-field mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                          <strong>Risk Analysis & Mitigation:</strong>
                          <p class="dossier-text mt-1 text-gray-700 dark:text-gray-300">{{ project.risk_analysis }}</p>
                        </div>
                        <div v-if="project.next_steps" class="dossier-sub-field mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                          <strong>Next Immediate Steps:</strong>
                          <p class="dossier-text mt-1 text-gray-700 dark:text-gray-300">{{ project.next_steps }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Segment 4: Proponent Background & Declared Track Record -->
                  <div v-if="hasDeclaredProponentProfile || canViewProponentHistory" class="dossier-section">
                    <h3 class="dossier-section-title"><UserIcon class="di" /> 4. Proponent Track Record</h3>
                    <div class="dossier-content-card">
                      <div v-if="hasDeclaredProponentProfile" class="declared-profile-box">
                        <div class="profile-field" v-for="row in proponentProfileRows" :key="row.label">
                          <span>{{ row.label }}</span>
                          <p>{{ row.value }}</p>
                        </div>
                      </div>
                      <div v-if="canViewProponentHistory" class="ph-history-block mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                        <div class="ph-head flex justify-between items-center mb-3">
                          <strong>Verified PMS Projects Tracker</strong>
                          <button class="ph-refresh" :disabled="proponentHistoryLoading" @click="loadProponentHistory(true)">
                            <HistoryIcon class="icon" />
                            {{ proponentHistoryLoading ? 'Loading' : 'Refresh' }}
                          </button>
                        </div>
                        <div v-if="proponentHistoryLoading" class="ph-empty text-center py-4 text-gray-400">Checking proponent history...</div>
                        <div v-else-if="proponentHistoryChecked && !proponentHistory.length" class="ph-empty text-center py-4 text-gray-400">No previous project records found.</div>
                        <div v-else-if="proponentHistory.length" class="ph-list space-y-2">
                          <div v-for="item in proponentHistory" :key="item.id" class="ph-item flex justify-between items-center p-3 border border-gray-100 dark:border-gray-800 rounded-lg bg-gray-50 dark:bg-slate-800/40">
                            <div>
                              <strong class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ item.project_code }}</strong>
                              <span class="text-xs text-gray-500 block">{{ item.title }}</span>
                            </div>
                            <div class="ph-meta flex gap-2 text-[10px]">
                              <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400">{{ item.current_stage?.name || 'No stage' }}</span>
                              <span class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">{{ item.status?.name || 'No status' }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Segment 5: SOI Lifecycle Tracker -->
                  <div v-if="soiTrackerMilestones.length" class="dossier-section">
                    <h3 class="dossier-section-title"><LayersIcon class="di" /> 5. SOI Lifecycle Tracker</h3>
                    <div class="dossier-content-card">
                      <p class="tracker-desc">Milestones for {{ formatProcessTrack(project.process_track) }} projects. Status is derived from the project's stage history and approval records.</p>
                      <div class="soi-tracker-timeline">
                        <div v-for="(milestone, idx) in soiTrackerMilestones" :key="idx" class="tracker-node" :class="milestone.status">
                          <div class="tracker-dot-col">
                            <div class="tracker-dot">
                              <CheckCircleIcon v-if="milestone.status === 'completed'" class="td-icon" />
                              <ActivityIcon v-else-if="milestone.status === 'current'" class="td-icon" />
                              <span v-else class="td-num">{{ idx + 1 }}</span>
                            </div>
                            <div v-if="idx < soiTrackerMilestones.length - 1" class="tracker-line" :class="milestone.status"></div>
                          </div>
                          <div class="tracker-content">
                            <strong>{{ milestone.label }}</strong>
                            <span v-if="milestone.date" class="tracker-date">{{ fmtDate(milestone.date) }}</span>
                            <span v-else-if="milestone.status === 'current'" class="tracker-current-tag">In Progress</span>
                            <p v-if="milestone.party" class="tracker-party">{{ milestone.party }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Post-Monitoring -->
              <div v-show="activeTab === 'monitoring'" class="tab-pane">
                <div class="monitoring-request-banner">
                  <div>
                    <strong>Monitoring compliance is active</strong>
                    <p>{{ project.monitoring_instructions || 'Provide the requested implementation and portfolio monitoring updates.' }}</p>
                  </div>
                  <span v-if="project.monitoring_due_date">Due {{ fmtDate(project.monitoring_due_date) }}</span>
                </div>
                <div class="pane-head">
                  <div>
                    <h3>Implementation Monitoring</h3>
                    <p class="pane-sub">SOI-02 updates for implementation milestones, portfolio reporting, GCG/COA indicators, and post-investment monitoring</p>
                  </div>
                  <div v-if="canEditPostMonitoringAction" class="pane-actions">
                    <button v-if="!monitoringEditing" class="add-btn" @click="startMonitoringEdit">
                      <EditIcon class="icon" /> {{ isExternalProponentUser ? 'Prepare Report' : 'Edit Monitoring' }}
                    </button>
                    <template v-else>
                      <button class="ghost-action" :disabled="monitoringSaving" @click="cancelMonitoringEdit">Cancel</button>
                      <button class="ghost-action" :disabled="monitoringSaving" @click="savePostMonitoring">
                        {{ monitoringSaving ? 'Saving...' : (isExternalProponentUser ? 'Save Draft' : 'Save Monitoring') }}
                      </button>
                      <button v-if="canSubmitMonitoringAction" class="add-btn" :disabled="monitoringSaving" @click="submitPostMonitoring">
                        {{ monitoringSaving ? 'Submitting...' : 'Submit Report' }}
                      </button>
                    </template>
                  </div>
                </div>

                <div class="monitoring-submission-state" :class="monitoringSubmissionStatus">
                  <div>
                    <span class="gate-kicker">Report status</span>
                    <strong>{{ monitoringSubmissionLabel }}</strong>
                    <p>{{ monitoringSubmissionDescription }}</p>
                  </div>
                  <span v-if="project.monitoring_submitted_at">Submitted {{ fmtDate(project.monitoring_submitted_at) }}</span>
                </div>

                <div v-if="project.monitoring_submission_status === 'returned' && project.monitoring_review_notes" class="monitoring-review-note returned">
                  <strong>Correction requested by NDC</strong>
                  <p>{{ project.monitoring_review_notes }}</p>
                </div>

                <div v-if="canReviewMonitoringAction" class="monitoring-review-panel">
                  <div>
                    <strong>Review submitted report</strong>
                    <p>Accept the report for the proponent's performance record, or return it with clear correction notes.</p>
                  </div>
                  <textarea v-model="monitoringReviewRemarks" class="member-input monitor-textarea" rows="3" placeholder="Required when returning the report"></textarea>
                  <div class="activation-actions">
                    <button class="ghost-action danger-text" :disabled="monitoringSaving" @click="reviewPostMonitoring('returned')">Return for Correction</button>
                    <button class="add-btn" :disabled="monitoringSaving" @click="reviewPostMonitoring('accepted')">Accept Report</button>
                  </div>
                </div>

                <div v-if="monitoringEditing" class="info-card">
                  <div class="ic-head"><ActivityIcon class="ci" /><span>Implementation Monitoring Inputs</span></div>
                  <div class="post-monitoring-form">
                    <!-- Jobs Grid (GAD breakdown) -->
                    <div class="col-span-2 border border-gray-200 dark:border-gray-700/80 rounded-xl p-4 bg-gray-50/50 dark:bg-slate-800/20 space-y-4">
                      <h4 class="text-xs font-bold text-blue-500 uppercase tracking-wider">Jobs Generated & Retained (GAD Breakdown)</h4>
                      
                      <div class="grid grid-cols-3 gap-4">
                        <!-- Direct Jobs -->
                        <div class="space-y-2">
                          <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Direct Jobs (Total)</label>
                          <input v-model.number="monitoringForm.jobs_generated_direct" type="number" min="0" class="member-input" placeholder="0" />
                          <div class="grid grid-cols-2 gap-2 mt-1">
                            <div>
                              <span class="text-[10px] text-gray-500 block">Male</span>
                              <input v-model.number="monitoringForm.jobs_direct_male" type="number" min="0" class="member-input text-xs py-1" placeholder="0" />
                            </div>
                            <div>
                              <span class="text-[10px] text-gray-500 block">Female</span>
                              <input v-model.number="monitoringForm.jobs_direct_female" type="number" min="0" class="member-input text-xs py-1" placeholder="0" />
                            </div>
                          </div>
                        </div>

                        <!-- Indirect Jobs -->
                        <div class="space-y-2">
                          <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Indirect Jobs (Total)</label>
                          <input v-model.number="monitoringForm.jobs_generated_indirect" type="number" min="0" class="member-input" placeholder="0" />
                          <div class="grid grid-cols-2 gap-2 mt-1">
                            <div>
                              <span class="text-[10px] text-gray-500 block">Male</span>
                              <input v-model.number="monitoringForm.jobs_indirect_male" type="number" min="0" class="member-input text-xs py-1" placeholder="0" />
                            </div>
                            <div>
                              <span class="text-[10px] text-gray-500 block">Female</span>
                              <input v-model.number="monitoringForm.jobs_indirect_female" type="number" min="0" class="member-input text-xs py-1" placeholder="0" />
                            </div>
                          </div>
                        </div>

                        <!-- Retained Jobs -->
                        <div class="space-y-2">
                          <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300">Retained Jobs (Total)</label>
                          <input v-model.number="monitoringForm.retained_jobs" type="number" min="0" class="member-input" placeholder="0" />
                          <div class="grid grid-cols-2 gap-2 mt-1">
                            <div>
                              <span class="text-[10px] text-gray-500 block">Male</span>
                              <input v-model.number="monitoringForm.jobs_retained_male" type="number" min="0" class="member-input text-xs py-1" placeholder="0" />
                            </div>
                            <div>
                              <span class="text-[10px] text-gray-500 block">Female</span>
                              <input v-model.number="monitoringForm.jobs_retained_female" type="number" min="0" class="member-input text-xs py-1" placeholder="0" />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="monitor-field">
                      <label>Projected Revenue</label>
                      <input v-model.number="monitoringForm.projected_revenue" type="number" min="0" step="0.01" class="member-input" placeholder="0.00" />
                    </div>
                    <div class="monitor-field">
                      <label>Actual Revenue</label>
                      <input v-model.number="monitoringForm.actual_revenue" type="number" min="0" step="0.01" class="member-input" placeholder="0.00" />
                    </div>
                    <div class="monitor-field">
                      <label>Dividend / Remittance</label>
                      <input v-model.number="monitoringForm.dividend_remittance" type="number" min="0" step="0.01" class="member-input" placeholder="0.00" />
                    </div>
                    <label v-if="!isExternalProponentUser" class="monitor-check">
                      <input v-model="monitoringForm.reportable_to_gcg" type="checkbox" />
                      <span>
                        <strong>Reportable to GCG</strong>
                        <small>Include in formal portfolio reporting.</small>
                      </span>
                    </label>
                    <label v-if="!isExternalProponentUser" class="monitor-check">
                      <input v-model="monitoringForm.gcg_relevance" type="checkbox" />
                      <span>
                        <strong>GCG Relevant</strong>
                        <small>Track a GCG-related contribution or metric.</small>
                      </span>
                    </label>
                    <div v-if="!isExternalProponentUser" class="monitor-field">
                      <label>GCG Score / Rating</label>
                      <input v-model.number="monitoringForm.gcg_score" type="number" min="0" step="0.01" class="member-input" placeholder="Optional" />
                    </div>
                    <div class="monitor-field">
                      <label>Monitoring Frequency</label>
                      <select v-model="monitoringForm.monitoring_frequency" class="member-input">
                        <option value="">Not set</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Semi-Annual">Semi-Annual</option>
                        <option value="Annual">Annual</option>
                        <option value="As Needed">As Needed</option>
                      </select>
                    </div>
                    <div class="monitor-field span-2">
                      <label>Reporting Period</label>
                      <input v-model="monitoringForm.reporting_period" type="text" class="member-input" placeholder="e.g. Q2 2026, FY 2026" />
                    </div>
                    <div class="monitor-field span-2">
                      <label>Monitoring Indicators</label>
                      <textarea v-model="monitoringForm.monitoring_indicators" class="member-input monitor-textarea" rows="3" placeholder="Jobs, revenue, milestones, issues, covenants, safeguards, or management reporting notes"></textarea>
                    </div>
                    <div v-if="!isExternalProponentUser" class="monitor-field">
                      <label>GCG Metrics</label>
                      <textarea v-model="monitoringForm.gcg_metrics" class="member-input monitor-textarea" rows="3" placeholder="GCG scorecard item, target, evidence, or remarks"></textarea>
                    </div>
                    <div class="monitor-field" :class="{ 'span-2': isExternalProponentUser }">
                      <label>Social Impact Notes</label>
                      <textarea v-model="monitoringForm.social_impact_notes" class="member-input monitor-textarea" rows="3" placeholder="Employment, beneficiaries, regional development, inclusion impact"></textarea>
                    </div>
                  </div>
                </div>

                <div v-else-if="hasReportingMetrics" class="info-card">
                  <div class="ic-head"><ActivityIcon class="ci" /><span>Implementation Monitoring Indicators</span></div>
                  <div class="fin-grid reporting-grid">
                    <div class="fin-item"><span class="fl">Direct Jobs</span><span class="fa sm">{{ metricNumber(financialMetrics.jobs_generated_direct) }}</span></div>
                    <div class="fin-item"><span class="fl">Indirect Jobs</span><span class="fa sm">{{ metricNumber(financialMetrics.jobs_generated_indirect) }}</span></div>
                    <div class="fin-item"><span class="fl">Retained Jobs</span><span class="fa sm">{{ metricNumber(financialMetrics.retained_jobs) }}</span></div>
                    <div class="fin-item"><span class="fl">Projected Revenue</span><span class="fa sm">{{ metricMoney(financialMetrics.projected_revenue) }}</span></div>
                    <div class="fin-item"><span class="fl">Actual Revenue</span><span class="fa sm">{{ metricMoney(financialMetrics.actual_revenue) }}</span></div>
                    <div class="fin-item"><span class="fl">Dividend / Remittance</span><span class="fa sm">{{ metricMoney(financialMetrics.dividend_remittance) }}</span></div>
                    <div v-if="!isExternalProponentUser" class="fin-item"><span class="fl">GCG Relevant</span><span class="fa sm">{{ yesNo(financialMetrics.gcg_relevance) }}</span></div>
                    <div v-if="!isExternalProponentUser" class="fin-item"><span class="fl">GCG Score</span><span class="fa sm">{{ metricNumber(financialMetrics.gcg_score) }}</span></div>
                    <div v-if="!isExternalProponentUser" class="fin-item"><span class="fl">Reportable to GCG</span><span class="fa sm">{{ yesNo(financialMetrics.reportable_to_gcg || financialMetrics.is_reportable) }}</span></div>
                    <div class="fin-item"><span class="fl">Monitoring</span><span class="fa sm">{{ financialMetrics.monitoring_frequency || 'Not set' }}</span></div>
                    <div v-if="financialMetrics.reporting_period" class="fin-item"><span class="fl">Reporting Period</span><span class="fa sm">{{ financialMetrics.reporting_period }}</span></div>
                  </div>
                  <!-- GAD Employment Indicators (Male vs. Female Breakdown) -->
                  <div class="mt-4 border border-gray-200 dark:border-gray-700/80 rounded-xl p-4 bg-gray-50/50 dark:bg-slate-800/20 max-w-2xl">
                    <h4 class="text-xs font-bold text-blue-500 uppercase tracking-wider mb-3">GAD Employment Indicators (Male vs. Female Breakdown)</h4>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                      <thead>
                        <tr class="text-left text-xs font-semibold text-gray-500 uppercase">
                          <th class="pb-2">Employment Type</th>
                          <th class="pb-2 text-right">Male</th>
                          <th class="pb-2 text-right">Female</th>
                          <th class="pb-2 text-right">Total</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-150 dark:divide-gray-850 text-gray-700 dark:text-gray-300">
                        <tr>
                          <td class="py-2.5 font-medium">Direct Jobs</td>
                          <td class="py-2.5 text-right text-gray-900 dark:text-gray-100">{{ metricNumber(financialMetrics.jobs_direct_male) }}</td>
                          <td class="py-2.5 text-right text-gray-900 dark:text-gray-100">{{ metricNumber(financialMetrics.jobs_direct_female) }}</td>
                          <td class="py-2.5 text-right font-semibold text-blue-600 dark:text-blue-400">{{ metricNumber(financialMetrics.jobs_generated_direct) }}</td>
                        </tr>
                        <tr>
                          <td class="py-2.5 font-medium">Indirect Jobs</td>
                          <td class="py-2.5 text-right text-gray-900 dark:text-gray-100">{{ metricNumber(financialMetrics.jobs_indirect_male) }}</td>
                          <td class="py-2.5 text-right text-gray-900 dark:text-gray-100">{{ metricNumber(financialMetrics.jobs_indirect_female) }}</td>
                          <td class="py-2.5 text-right font-semibold text-blue-600 dark:text-blue-400">{{ metricNumber(financialMetrics.jobs_generated_indirect) }}</td>
                        </tr>
                        <tr>
                          <td class="py-2.5 font-medium">Retained Jobs</td>
                          <td class="py-2.5 text-right text-gray-900 dark:text-gray-100">{{ metricNumber(financialMetrics.jobs_retained_male) }}</td>
                          <td class="py-2.5 text-right text-gray-900 dark:text-gray-100">{{ metricNumber(financialMetrics.jobs_retained_female) }}</td>
                          <td class="py-2.5 text-right font-semibold text-blue-600 dark:text-blue-400">{{ metricNumber(financialMetrics.retained_jobs) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <p v-if="financialMetrics.monitoring_indicators" class="desc metric-note mt-3"><strong>Indicators:</strong> {{ financialMetrics.monitoring_indicators }}</p>
                  <p v-if="!isExternalProponentUser && financialMetrics.gcg_metrics" class="desc metric-note"><strong>GCG metrics:</strong> {{ financialMetrics.gcg_metrics }}</p>
                  <p v-if="financialMetrics.social_impact_notes" class="desc metric-note"><strong>Impact:</strong> {{ financialMetrics.social_impact_notes }}</p>
                </div>
                <div v-else class="empty-pane">
                  <ActivityIcon class="ep-icon" />
                  <p>No post-monitoring data recorded yet</p>
                </div>
              </div>

              <!-- Team -->
              <div v-show="activeTab === 'team'" class="tab-pane">
                <div class="pane-head">
                  <h3>Team Members</h3>
                  <button v-if="canManageMembersAction" class="add-btn" @click="openAddMember"><UserPlusIcon class="icon" /> Add Member</button>
                </div>
                <div v-if="activeMembers.length > 0" class="members-list">
                  <div v-for="m in activeMembers" :key="m.id" class="member-card">
                    <div class="m-avatar">
                      <img v-if="m.user?.avatar" :src="m.user.avatar" :alt="m.user?.name || m.user?.full_name" />
                      <span v-else>{{ initials(m.user?.name || m.user?.full_name || '') }}</span>
                    </div>
                    <div class="m-info">
                      <p class="m-name">{{ m.user?.name || m.user?.full_name }}</p>
                      <p class="m-role">{{ m.role?.name || 'Team Member' }}</p>
                      <div class="m-perms">
                        <span class="m-perm" :class="{ on: memberFlag(m, 'can_edit') }">Edit Project + Create/Update Tasks</span>
                        <span class="m-perm" :class="{ on: memberFlag(m, 'can_delete') }">Delete Project/Tasks</span>
                        <span class="m-perm" :class="{ on: memberFlag(m, 'can_manage_members') }">Manage Members</span>
                      </div>
                    </div>
                    <div class="m-actions">
                      <button v-if="m.user?.id" class="remove-btn" @click="openUserProfile(m.user.id)">Profile</button>
                      <button class="remove-btn" @click="openEditMember(m)">Edit</button>
                      <button class="remove-btn danger" @click="handleRemoveMember(m.id)">Remove</button>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-pane"><UsersIcon class="ep-icon" /><p>No team members added yet</p></div>

                <!-- Sent Invitations -->
                <div v-if="project.invitations && project.invitations.length > 0" class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                  <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-1.5">
                    <UsersIcon class="w-4 h-4 text-blue-500" /> Pending & Sent Invitations
                  </h4>
                  <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="invite in project.invitations" :key="invite.id" class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-slate-800/40">
                      <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate max-w-[200px]" :title="invite.email">
                          {{ invite.email }}
                        </p>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                          Role: {{ invite.role?.name || 'Member' }} · Invited by {{ invite.invited_by?.full_name || 'System' }}
                        </p>
                      </div>
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize shrink-0" :class="inviteStatusBadgeClass(invite.status)">
                        {{ invite.status }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Tasks -->
              <div v-show="activeTab === 'tasks'" class="tab-pane">
                <div class="pane-head">
                  <div>
                    <h3>Project Work Plan</h3>
                    <p class="pane-sub">{{ workPlanDescription }}</p>
                  </div>
                  <button
                    v-if="project?.id && !isExternalProponentUser"
                    class="btn-secondary"
                    type="button"
                    @click="openFullWorkboard"
                  >
                    <ArrowRightIcon class="w-4 h-4" /> Open Project Tasks
                  </button>
                </div>

                <div v-if="!implementationStarted" class="rounded-lg border border-slate-200 bg-slate-50 p-5 dark:border-slate-700 dark:bg-slate-900/50">
                  <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                      <p class="text-xs font-bold uppercase text-blue-600">Implementation readiness</p>
                      <h4 class="mt-1 text-base font-bold text-slate-900 dark:text-white">SOI work continues while implementation readiness is reviewed</h4>
                      <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-300">The work plan below includes tasks from every SOI phase. Starting implementation adds the Implementation & Monitoring phase tasks configured in SOI Workflow Settings.</p>
                    </div>
                    <button v-if="!isExternalProponentUser && canUpdateTasksAction" type="button" class="add-btn shrink-0" :disabled="implementationLoading || !implementationReadiness?.ready" @click="startImplementation">
                      <ActivityIcon class="icon" /> {{ implementationLoading ? 'Starting...' : 'Start Implementation' }}
                    </button>
                  </div>
                  <div v-if="implementationLoading && !implementationReadiness" class="mt-4 text-sm text-slate-500">Checking readiness...</div>
                  <ul v-else-if="implementationReadiness?.blockers?.length" class="mt-4 grid gap-2">
                    <li v-for="blocker in implementationReadiness.blockers" :key="blocker.code" class="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/30">
                      <AlertCircleIcon class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                      <span><strong class="block text-slate-900 dark:text-white">{{ blocker.label }}</strong><span class="text-slate-600 dark:text-slate-300">{{ blocker.detail }}</span></span>
                    </li>
                  </ul>
                  <div v-else-if="implementationReadiness?.ready" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">Readiness checks passed. Starting implementation will create the {{ implementationReadiness.template }} delivery template.</div>
                </div>

                <div class="task-summary-grid">
                  <div class="task-stat">
                    <span>Checklist Items</span>
                    <strong>{{ taskStats.total }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>Completed</span>
                    <strong>{{ taskStats.completed }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>In Progress</span>
                    <strong>{{ taskStats.inProgress }}</strong>
                  </div>
                  <div class="task-stat warn">
                    <span>Overdue</span>
                    <strong>{{ taskStats.overdue }}</strong>
                  </div>
                </div>

                <div class="info-card">
                  <div class="ic-head"><ListChecksIcon class="ci" /><span>Overall Execution</span></div>
                  <div class="execution-row">
                    <div class="execution-track">
                      <div class="execution-fill" :style="{ width: `${taskStats.averageProgress}%` }"></div>
                    </div>
                    <strong>{{ taskStats.averageProgress }}%</strong>
                  </div>
                </div>

                <div class="workplan-guide">
                  <strong>{{ isExternalProponentUser ? 'Who defines this work plan?' : 'How to progress this project' }}</strong>
                  <span>{{ workPlanGuideText }}</span>
                </div>
                <div v-if="!isExternalProponentUser && !canUpdateTasksAction" class="workplan-guide muted">
                  <strong>Progress controls are locked for your account</strong>
                  <span>You can review the work plan here. Project admins, assigned editors, and Super Admin can start phases, complete subtasks, and update progress.</span>
                </div>

                <!-- Sub-tabs Selector -->
                <div class="flex border-b border-gray-200 dark:border-gray-700/80 mb-4 gap-4">
                  <button 
                    class="py-2.5 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-all duration-150"
                    :class="taskViewMode === 'list' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    @click="taskViewMode = 'list'"
                  >
                    <ListIcon class="w-4 h-4" /> List Checklist
                  </button>
                  <button 
                    class="py-2.5 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-all duration-150"
                    :class="taskViewMode === 'gantt' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    @click="taskViewMode = 'gantt'"
                  >
                    <ActivityIcon class="w-4 h-4" /> Gantt Chart
                  </button>
                  <button 
                    class="py-2.5 px-1 border-b-2 font-medium text-sm flex items-center gap-2 transition-all duration-150"
                    :class="taskViewMode === 'calendar' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    @click="taskViewMode = 'calendar'"
                  >
                    <CalendarDaysIcon class="w-4 h-4" /> Calendar View
                  </button>
                </div>

                <template v-if="taskViewMode === 'list'">
                  <div v-if="workPlanSections.length" class="task-list">
                    <section v-for="section in workPlanSections" :key="section.key" class="workplan-section-card">
                      <div class="workplan-section-head">
                        <div>
                          <p>{{ section.ordinal }}</p>
                          <h4>{{ section.label }}</h4>
                        </div>
                        <div class="workplan-section-count">
                          <strong>{{ section.completedChecklist }}/{{ section.totalChecklist }}</strong>
                          <span>checklist</span>
                        </div>
                      </div>
                      <div class="execution-row compact">
                        <div class="execution-track">
                          <div class="execution-fill" :style="{ width: `${section.progress}%` }"></div>
                        </div>
                        <strong>{{ section.progress }}%</strong>
                      </div>

                      <div class="task-section-list">
                        <div v-for="task in section.tasks" :key="task.id" class="task-card">
                          <div class="task-main">
                            <div class="task-title-row">
                              <strong :style="task.status === 'completed' ? 'text-decoration: line-through; opacity: 0.6;' : ''">{{ task.title }}</strong>
                              <span class="task-status" :class="task.status">{{ formatTaskStatus(task.status) }}</span>
                            </div>
                            <p v-if="task.description">{{ task.description }}</p>
                            <div class="task-meta">
                              <span class="type-chip">{{ task.workstream || 'General delivery' }}</span>
                              <span>{{ task.assigned_to?.full_name || task.assigned_to?.name || 'Unassigned' }}</span>
                              <span v-if="task.due_date" :class="{ danger: task.is_overdue }">Due {{ fmtDate(task.due_date) }}</span>
                              <span v-if="task.priority">{{ task.priority }}</span>
                            </div>
                            <div v-if="task.subtasks?.length" class="subtask-mini-list">
                              <div v-for="subtask in task.subtasks" :key="subtask.id" class="subtask-mini">
                                <div class="subtask-copy">
                                  <span :style="subtask.status === 'completed' ? 'text-decoration: line-through; opacity: 0.6;' : ''">{{ subtask.title }}</span>
                                  <small>{{ formatTaskStatus(subtask.status) }}</small>
                                </div>
                                <div class="subtask-actions">
                                  <input
                                    type="checkbox"
                                    :checked="subtask.status === 'completed'"
                                    :disabled="!canUpdateTasksAction || isTaskUpdating(subtask.id)"
                                    @change="setTaskStatus(subtask, subtask.status === 'completed' ? 'in_progress' : 'completed', task)"
                                    class="subtask-checkbox"
                                    :class="{ 'cursor-pointer': canUpdateTasksAction }"
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                          <div class="task-progress">
                            <span>{{ task.progress_percentage || 0 }}%</span>
                            <div class="mini-track"><div class="mini-fill" :style="{ width: `${task.progress_percentage || 0}%` }"></div></div>
                            <div v-if="!task.subtasks?.length" class="task-checkbox-wrap">
                              <input
                                type="checkbox"
                                :checked="task.status === 'completed'"
                                :disabled="!canUpdateTasksAction || isTaskUpdating(task.id)"
                                @change="setTaskStatus(task, task.status === 'completed' ? 'in_progress' : 'completed')"
                                class="task-checkbox"
                                :class="{ 'cursor-pointer': canUpdateTasksAction }"
                              />
                            </div>
                          </div>
                        </div>
                      </div>
                    </section>
                  </div>
                  <div v-else class="empty-pane"><ListChecksIcon class="ep-icon" /><p>{{ isExternalProponentUser ? 'No action items assigned to you right now' : 'No tasks linked to this project yet' }}</p></div>
                </template>

                <template v-else-if="taskViewMode === 'gantt'">
                  <div class="gantt-chart-container p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-gray-700/80 rounded-xl">
                    <div class="flex items-center justify-between gap-4 mb-4">
                      <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Project Timeline</span>
                      <div class="flex gap-1 bg-gray-100 dark:bg-slate-800 p-0.5 rounded-lg">
                        <button 
                          v-for="mode in (['Day', 'Week', 'Month'] as const)" 
                          :key="mode" 
                          class="px-2.5 py-1 text-xs font-bold rounded-md transition-all duration-150"
                          :class="ganttViewMode === mode ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                          @click="ganttViewMode = mode"
                        >
                          {{ mode }}
                        </button>
                      </div>
                    </div>
                    <div v-if="!ganttTasks.length" class="empty-pane py-8">
                      <ActivityIcon class="ep-icon" />
                      <p>No tasks with schedules to plot on the Gantt chart yet.</p>
                    </div>
                    <div v-else ref="ganttContainer" class="gantt-target overflow-x-auto"></div>
                  </div>
                </template>

                <template v-else-if="taskViewMode === 'calendar'">
                  <div class="calendar-view-container p-4 bg-white dark:bg-slate-900 border border-gray-200 dark:border-gray-700/80 rounded-xl text-gray-900 dark:text-gray-100 overflow-x-auto">
                    <div v-if="!calendarEvents.length" class="empty-pane py-8">
                      <CalendarDaysIcon class="ep-icon" />
                      <p>No task dates set to show on the calendar yet.</p>
                    </div>
                    <div v-else class="calendar-layout-split flex flex-col md:flex-row gap-4">
                      <div class="calendar-main flex-1 overflow-x-auto">
                        <div class="calendar-wrapper min-w-[550px]">
                          <FullCalendar :options="calendarOptions" />
                        </div>
                      </div>
                      <!-- Task Detail Side Panel -->
                      <div class="calendar-side-panel w-full md:w-64 shrink-0 border border-gray-200 dark:border-slate-700/80 rounded-xl bg-slate-50 dark:bg-slate-800/40 p-3 text-xs space-y-3">
                        <div v-if="selectedCalendarTask" class="flex flex-col h-full justify-between gap-3">
                          <div class="space-y-2.5">
                            <div class="border-b border-gray-200 dark:border-slate-700 pb-2">
                              <span 
                                class="px-1.5 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                :class="selectedCalendarTask.status === 'completed' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-300' : (selectedCalendarTask.status === 'in_progress' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300' : 'bg-slate-100 dark:bg-slate-800 text-gray-700 dark:text-gray-300')"
                              >
                                {{ selectedCalendarTask.status === 'completed' ? 'Completed' : (selectedCalendarTask.status === 'in_progress' ? 'In Progress' : 'Pending') }}
                              </span>
                              <h4 class="font-bold text-gray-800 dark:text-gray-100 mt-1.5 leading-tight">{{ selectedCalendarTask.title }}</h4>
                            </div>
                            <div class="space-y-1.5 text-[11px]">
                              <div class="flex justify-between">
                                <span class="text-gray-400">Start Date:</span>
                                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ selectedCalendarTask.start_date ? fmtDate(selectedCalendarTask.start_date) : 'N/A' }}</span>
                              </div>
                              <div class="flex justify-between">
                                <span class="text-gray-400">Due Date:</span>
                                <span class="text-gray-700 dark:text-gray-300 font-medium">{{ selectedCalendarTask.due_date ? fmtDate(selectedCalendarTask.due_date) : 'N/A' }}</span>
                              </div>
                            </div>
                            <div v-if="selectedCalendarTask.description" class="pt-1.5 border-t border-gray-100 dark:border-slate-700/60">
                              <span class="text-gray-400 block mb-0.5 font-semibold">Description:</span>
                              <p class="text-gray-600 dark:text-gray-300 leading-normal text-[11px]">{{ selectedCalendarTask.description }}</p>
                            </div>
                            <div class="progress-box space-y-1 pt-1.5 border-t border-gray-100 dark:border-slate-700/60">
                              <div class="flex justify-between text-[11px]">
                                <span class="text-gray-400">Task Progress:</span>
                                <span class="font-bold text-gray-700 dark:text-gray-300">{{ selectedCalendarTask.progress_percentage || 0 }}%</span>
                              </div>
                              <div class="w-full h-1.5 bg-gray-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-600" :style="{ width: `${selectedCalendarTask.progress_percentage || 0}%` }"></div>
                              </div>
                            </div>
                          </div>
                          <!-- Quick Action to update status if allowed -->
                          <div v-if="canUpdateTasksAction" class="pt-2 border-t border-gray-100 dark:border-slate-700/60">
                            <button 
                              class="w-full py-1.5 px-3 font-bold rounded-lg transition-all text-xs"
                              :class="selectedCalendarTask.status === 'completed' ? 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-300' : 'bg-emerald-600 hover:bg-emerald-700 text-white'"
                              @click="setTaskStatus(selectedCalendarTask, selectedCalendarTask.status === 'completed' ? 'in_progress' : 'completed')"
                            >
                              {{ selectedCalendarTask.status === 'completed' ? 'Mark In Progress' : 'Mark Completed' }}
                            </button>
                          </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center h-full text-center py-6 text-gray-400 dark:text-gray-500">
                          <CalendarDaysIcon class="w-6 h-6 mb-1.5 opacity-50" />
                          <p class="text-[11px]">Click a task bar in the calendar to inspect or update it here.</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>
              </div>

              <!-- Attachments -->
              <div v-show="activeTab === 'requirements'" class="tab-pane">
                <div class="pane-head">
                  <div>
                    <h3>SOI Requirements</h3>
                    <p class="pane-sub">NDC requests only the documents needed for the current SOI step. Later-stage items remain inactive until requested.</p>
                  </div>
                  <div class="pane-actions">
                    <button
                      v-if="canSubmitDocumentsAction && (draftDocuments.length || canSubmitProposalAction)"
                      class="add-btn submit-all"
                      :disabled="documentSubmitting || proposalSubmitting || !canSubmitCurrentPackage"
                      @click="submitRequirementPackage"
                    >
                      <CheckCircleIcon class="icon" />
                      {{ submitPackageLabel }}
                      <span v-if="draftDocuments.length" class="mini-count">{{ draftDocuments.length }}</span>
                    </button>
                  </div>
                </div>

                <div class="requirement-command-center">
                  <div class="requirement-summary">
                    <span><strong>{{ requirementQueueFilters.find((f) => f.id === 'action_needed')?.count || 0 }}</strong> needs action</span>
                    <span><strong>{{ requiredRequirementCount }}</strong> required</span>
                    <span><strong>{{ uploadedRequirementCount }}</strong> uploaded</span>
                    <span><strong>{{ completedRequirementCount }}</strong> confirmed</span>
                    <span v-if="!isExternalProponentUser"><strong>{{ pendingSelectionCount }}</strong> not requested</span>
                  </div>

                  <div class="requirement-toolbar">
                    <label class="requirement-search">
                      <SearchIcon class="toolbar-icon" />
                      <input v-model="requirementSearch" type="search" placeholder="Search requirement, group, SOI gate, remarks" />
                    </label>
                    <label class="requirement-select">
                      <SlidersHorizontalIcon class="toolbar-icon" />
                      <select v-model="requirementOwnerFilter">
                        <option value="all">All owners</option>
                        <option value="proponent">Proponent</option>
                        <option value="internal">Internal NDC</option>
                      </select>
                    </label>
                    <label class="requirement-select">
                      <select v-model="requirementSectionFilter">
                        <option value="all">All SOI phases</option>
                        <option v-for="section in requirementSectionOptions" :key="section" :value="section">
                          {{ formatSoiSectionLabel(section) }}
                        </option>
                      </select>
                    </label>
                    <button class="ghost-action" type="button" @click="resetRequirementFilters">Reset</button>
                  </div>

                  <div class="requirement-queue">
                    <button
                      v-for="filter in requirementQueueFilters"
                      :key="filter.id"
                      class="queue-filter-btn"
                      :class="{ active: requirementQueueFilter === filter.id }"
                      @click="requirementQueueFilter = filter.id"
                    >
                      {{ filter.label }}
                      <span>{{ filter.count }}</span>
                    </button>
                  </div>
                </div>

                <div v-if="canSubmitProposalAction && initialPackageMissing.length" class="submission-readiness">
                  <strong>Required files still need drafts</strong>
                  <span>Attach these before submitting: {{ initialPackageMissing.join(', ') }}</span>
                </div>
                <div v-else-if="canSubmitProposalAction" class="submission-readiness ready">
                  <strong>Initial proposal package is ready</strong>
                  <span>Submit here when the required drafts are ready. Files remain replaceable until NDC confirms the requirement.</span>
                </div>

                <div v-if="hasSoiApprovalStarted && missingRequirementsForCurrentPhase.length" class="submission-readiness">
                  <strong>⚠️ Missing Required Documents for {{ currentPhaseLabel }}:</strong>
                  <span>The following required files are still missing or not yet approved for this phase:</span>
                  <ul style="margin: 0.25rem 0 0; padding-left: 1.25rem; font-size: 0.76rem; list-style-type: disc;">
                    <li v-for="req in missingRequirementsForCurrentPhase" :key="req.id" style="margin-bottom: 0.15rem;">
                      <strong>{{ req.item_name }}</strong> 
                      <span style="opacity: 0.8;"> - Assigned to: {{ req.owner_type === 'internal' ? 'Internal NDC' : 'Proponent' }}</span>
                      <span style="font-weight: 600; text-transform: uppercase; margin-left: 0.5rem;">
                        ({{ formatRequirementStatus(req.status) }})
                      </span>
                    </li>
                  </ul>
                </div>

                <div v-if="displayRequirementSections.length" class="requirement-sections space-y-4">
                  <section 
                    v-for="section in displayRequirementSections" 
                    :key="section.id" 
                    class="requirement-section border border-gray-200 dark:border-gray-700/80 rounded-xl bg-white dark:bg-slate-900/60 overflow-hidden shadow-sm transition-all duration-200"
                    :class="{ 'border-blue-300 dark:border-blue-800 ring-1 ring-blue-500/10 dark:ring-blue-500/5': !collapsedSections[section.id] }"
                  >
                    <!-- Folder Header -->
                    <div 
                      class="requirement-section-head flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800/40 transition-colors"
                      @click="collapsedSections[section.id] = !collapsedSections[section.id]"
                    >
                      <div class="flex items-center gap-3">
                        <FolderIcon class="w-5 h-5 text-blue-500 shrink-0" />
                        <div>
                          <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            {{ section.title }}
                          </h4>
                          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ section.description }}</p>
                        </div>
                      </div>
                      <div class="flex items-center gap-4 shrink-0">
                        <div class="flex flex-col items-end gap-1">
                          <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            {{ section.completed }}/{{ section.requested }} Completed
                          </span>
                          <!-- Progress Bar -->
                          <div class="w-24 h-1.5 bg-gray-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div 
                              class="h-full bg-blue-500 rounded-full transition-all duration-300"
                              :style="{ width: `${section.requested > 0 ? (section.completed / section.requested) * 100 : 0}%` }"
                            ></div>
                          </div>
                        </div>
                        <component 
                          :is="collapsedSections[section.id] ? ChevronRightIcon : ChevronDownIcon" 
                          class="w-4 h-4 text-gray-400" 
                        />
                      </div>
                    </div>

                    <!-- Collapsible Contents -->
                    <div v-show="!collapsedSections[section.id]" class="requirement-groups border-t border-gray-100 dark:border-gray-800 p-4 space-y-4 bg-gray-50/50 dark:bg-slate-900/40">
                      <div v-for="group in section.groups" :key="`${section.id}-${group.name}`" class="requirement-group">
                        <div class="requirement-group-head">
                          <h4>{{ group.name }}</h4>
                          <span>{{ group.completed }}/{{ group.requested }} requested items completed</span>
                        </div>
                        <div class="requirement-list">
                          <div
                            v-for="req in group.items"
                            :key="req.id"
                            class="requirement-card"
                            :class="{ 
                              spotlight: highlightedRequirementId === req.id, 
                              internal: isInternalRequirement(req),
                              'is-missing': req.is_required && !completedRequirementStatuses.includes(req.status || '')
                            }"
                            :data-requirement-id="req.id"
                          >
                            <div class="requirement-main">
                              <strong>{{ req.item_name }}</strong>
                              <p>{{ req.source_document || 'NDC SOI requirement' }}</p>
                              <div class="doc-meta">
                                <span class="requirement-status" :class="req.status">{{ formatRequirementStatus(req.status) }}</span>
                                <span v-if="req.document" class="document-status" :class="documentStatusClass(req.document)">
                                  {{ documentStatusLabel(req.document) }}
                                </span>
                                <span v-if="isInternalRequirement(req)" class="req-kind internal">Internal NDC</span>
                                <span v-if="req.gate_step" class="req-kind gate">{{ formatGateStep(req.gate_step) }}</span>
                                <span v-if="req.svf_only">SVF only</span>
                                <span v-if="req.status === 'pending'">Not requested yet</span>
                                <span :class="req.is_required ? 'req-kind required' : 'req-kind optional'">{{ req.is_required ? 'Required' : 'Optional' }}</span>
                                <span v-if="req.is_required && !completedRequirementStatuses.includes(req.status || '')" class="req-kind missing-flag">⚠️ Missing</span>
                                <span v-if="req.is_required && !isInternalRequirement(req)">Initial package</span>
                                <span v-if="req.due_date">Due {{ fmtDate(req.due_date) }}</span>
                                <span v-if="req.received_at">Received {{ fmtDate(req.received_at) }}</span>
                              </div>
                              <p v-if="req.remarks" class="requirement-remarks">{{ req.remarks }}</p>
                            </div>
                            <div class="requirement-actions">
                              <button v-if="req.template_file_path" class="doc-action-btn template-btn" title="Download document template" @click="downloadTemplate(req.template_file_path)">
                                Template
                              </button>
                              <button v-if="req.document && canPreviewDocument(req.document)" class="doc-action-btn" @click="viewDocument(req.document)">
                                View
                              </button>
                              <button v-if="req.document" class="icon-action" title="Download attachment" @click="downloadDocument(req.document)">
                                <DownloadIcon class="icon" />
                              </button>
                              <button
                                v-if="req.document && canSubmitDocumentsAction && isDraftDocument(req.document)"
                                class="doc-action-btn"
                                :disabled="isDocumentActing(req.document.id)"
                                @click="submitDocument(req.document)"
                              >Submit</button>
                              <button
                                v-if="canManageRequirementsAction"
                                class="doc-action-btn"
                                @click="openRequirementReview(req)"
                              >Review</button>
                              <button v-if="canAttachRequirement(req)" class="remove-btn" @click="openRequirementUpload(req)">
                                {{ requirementAttachLabel(req) }}
                              </button>
                              <button
                                v-if="req.document && canDeleteRequirementDocument(req)"
                                class="icon-action danger"
                                title="Delete attachment"
                                :disabled="isDocumentActing(req.document.id)"
                                @click="deleteDocument(req.document.id)"
                              >
                                <TrashIcon class="icon" />
                              </button>
                            </div>
                            <div v-if="reviewingRequirementId === req.id" class="requirement-review-panel">
                          <div class="review-panel-head">
                            <div>
                              <strong>NDC requirement review</strong>
                              <span>{{ isInternalRequirement(req) ? 'Record the internal endorsement artifact decision. Waivers require remarks.' : 'Record the decision here. Request updates instead of replacing proponent files.' }}</span>
                            </div>
                            <button class="icon-action" title="Close review" @click="closeRequirementReview">
                              <XIcon class="icon" />
                            </button>
                          </div>
                          <div class="review-grid">
                            <label>
                              Decision
                              <select v-model="requirementReviewForm.status" class="member-input">
                                <option value="requested">{{ isInternalRequirement(req) ? 'Open Internal Artifact' : 'Request from Proponent' }}</option>
                                <option value="received">Mark as Received</option>
                                <option value="approved">Approve File</option>
                                <option value="approved_with_conditions">Approve with Conditions</option>
                                <option value="deferred">Request Update</option>
                                <option value="for_further_evaluation">For Further Evaluation</option>
                                <option value="waived">Waive Requirement</option>
                                <option value="disapproved">Disapprove File</option>
                              </select>
                            </label>
                            <label v-if="requirementReviewNeedsDueDate">
                              Due date
                              <input v-model="requirementReviewForm.due_date" type="date" class="member-input" />
                            </label>
                            <label class="review-notes">
                              Remarks / instructions
                              <textarea
                                v-model="requirementReviewForm.remarks"
                                class="member-input upload-textarea"
                                :placeholder="requirementReviewRequiresRemarks ? 'Required. Tell the proponent exactly what must be corrected or provided.' : 'Optional reviewer note'"
                              ></textarea>
                            </label>
                          </div>
                          <p class="upload-note">If a file needs correction, choose Request Update and specify the exact document issue. The proponent can replace it until NDC confirms it.</p>
                          <div class="upload-actions">
                            <button class="remove-btn" @click="closeRequirementReview">Cancel</button>
                            <button class="add-btn" :disabled="requirementReviewSaving" @click="saveRequirementReview">
                              <span v-if="requirementReviewSaving" class="spinner-sm"></span>
                              Save Review
                            </button>
                          </div>
                        </div>
                            <div v-if="activeRequirementId === req.id && selectedDocumentFile" class="upload-card requirement-upload">
                          <div class="upload-copy">
                            <strong>{{ selectedDocumentFile.name }}</strong>
                            <span>{{ fmtFileSize(selectedDocumentFile.size) }} · linked to {{ req.item_name }}</span>
                          </div>
                          <input v-model="documentForm.title" class="member-input" placeholder="Document title" />
                          <textarea v-model="documentForm.description" class="member-input upload-textarea" placeholder="Optional note for the NDC reviewer"></textarea>
                          <p class="upload-note">This remains a draft until you submit the requirement package. You will stay on this tab.</p>
                          <div class="upload-actions">
                            <button class="remove-btn" @click="clearSelectedDocument">Cancel</button>
                            <button class="add-btn" :disabled="documentUploading" @click="uploadDocument(false)">
                              <span v-if="documentUploading" class="spinner-sm"></span>
                              Save Requirement Draft
                            </button>
                          </div>
                        </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </section>
                </div>
                <div v-else class="empty-pane"><ListChecksIcon class="ep-icon" /><p>{{ emptyRequirementQueueMessage }}</p></div>
              </div>

              <!-- Attachments -->
              <div v-show="activeTab === 'attachments'" class="tab-pane">
                <div class="pane-head">
                  <div>
                    <h3>Attachments</h3>
                    <p class="pane-sub">{{ attachmentSubmissionHelp }}</p>
                  </div>
                  <div class="pane-actions">
                    <button
                      v-if="canSubmitDocumentsAction && draftDocuments.length"
                      class="add-btn submit-all"
                      :disabled="documentSubmitting || !canSubmitCurrentPackage"
                      @click="submitDraftDocuments"
                    >
                      <CheckCircleIcon class="icon" />
                      {{ submitPackageLabel }}
                      <span class="mini-count">{{ draftDocuments.length }}</span>
                    </button>
                    <button v-if="canUploadDocumentsAction" class="add-btn" @click="openDocumentPicker">
                      <UploadIcon class="icon" /> Upload Draft
                    </button>
                  </div>
                </div>

                <!-- Search bar -->
                <div class="mb-4 relative">
                  <input 
                    v-model="fileSearchQuery" 
                    type="text" 
                    class="member-input pl-9" 
                    placeholder="Search files by title, description, category..." 
                  />
                  <PaperclipIcon class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                </div>

                <div class="media-section">
                  <div class="section-head compact">
                    <div>
                      <h4>Map Photos</h4>
                      <p>Upload project photos here. The selected thumbnail appears on the project map slider.</p>
                    </div>
                    <button v-if="canUploadDocumentsAction" class="add-btn" :disabled="imageUploading" @click="openImagePicker">
                      <ImageIcon class="icon" /> Add Photos
                    </button>
                  </div>

                  <input
                    ref="imageFileInput"
                    type="file"
                    class="hidden-file"
                    multiple
                    accept="image/png,image/jpeg,image/jpg,image/webp"
                    @change="handleImageFileSelect"
                  />

                  <div v-if="selectedImageFiles.length" class="upload-card photo-upload">
                    <div class="upload-copy">
                      <strong>{{ selectedImageFiles.length }} photo{{ selectedImageFiles.length > 1 ? 's' : '' }} selected</strong>
                      <span>{{ selectedImageFiles.map((file) => file.name).join(', ') }}</span>
                    </div>
                    <input v-model="imageUploadTitle" class="member-input" placeholder="Optional shared caption" />
                    <p class="upload-note">The first uploaded photo becomes the map thumbnail when no thumbnail is set.</p>
                    <div class="upload-actions">
                      <button class="remove-btn" @click="clearSelectedImages">Cancel</button>
                      <button class="add-btn" :disabled="imageUploading" @click="uploadProjectImages">
                        <span v-if="imageUploading" class="spinner-sm"></span>
                        Save Photos
                      </button>
                    </div>
                  </div>

                  <div v-if="projectImages.length" class="image-gallery">
                    <div v-for="image in projectImages" :key="image.id" class="image-card">
                      <img :src="projectImageUrl(image)" :alt="image.title || image.file_name" />
                      <div class="image-card-meta">
                        <div>
                          <strong>{{ image.title || image.file_name }}</strong>
                          <span>{{ fmtFileSize(image.file_size || 0) }}</span>
                        </div>
                        <span v-if="image.is_thumbnail" class="thumb-badge">Map thumbnail</span>
                      </div>
                      <div v-if="canUploadDocumentsAction" class="image-card-actions">
                        <button
                          class="doc-action-btn"
                          :disabled="image.is_thumbnail || imageActionIds.has(image.id)"
                          @click="setMapThumbnail(image)"
                        >
                          Use on Map
                        </button>
                        <button
                          class="icon-action danger"
                          title="Delete photo"
                          :disabled="imageActionIds.has(image.id)"
                          @click="deleteProjectImage(image)"
                        >
                          <TrashIcon class="icon" />
                        </button>
                      </div>
                    </div>
                  </div>
                  <div v-else class="empty-pane compact"><ImageIcon class="ep-icon" /><p>No map photos uploaded yet</p></div>
                </div>

                <div v-if="initialPackageMissing.length" class="submission-readiness">
                  <strong>Required files still need drafts</strong>
                  <span>Attach these before submitting: {{ initialPackageMissing.join(', ') }}</span>
                </div>

                <input ref="documentFileInput" type="file" class="hidden-file" :accept="allowedDocumentAccept" @change="handleDocumentFileSelect" />

                <div v-if="selectedDocumentFile" class="upload-card">
                  <div class="upload-copy">
                    <strong>{{ selectedDocumentFile.name }}</strong>
                    <span>{{ fmtFileSize(selectedDocumentFile.size) }}</span>
                  </div>
                  <input v-model="documentForm.title" class="member-input" placeholder="Document title" />
                  <input v-model="documentForm.category" class="member-input" placeholder="Category (TOR, SOI, Condition Evidence...)" />
                  <textarea v-model="documentForm.description" class="member-input upload-textarea" placeholder="Short description or condition being satisfied"></textarea>
                  <p class="upload-note">This saves the file as a draft. Drafts are editable/removable; submitted files are locked unless an update is requested.</p>
                  <div class="upload-actions">
                    <button class="remove-btn" @click="clearSelectedDocument">Cancel</button>
                    <button class="add-btn" :disabled="documentUploading" @click="uploadDocument(false)">
                      <span v-if="documentUploading" class="spinner-sm"></span>
                      Save Draft
                    </button>
                  </div>
                </div>

                <!-- Folders and File Lists -->
                <div v-if="projectDocuments.length" class="space-y-4">
                  <div v-for="(docs, folderName) in groupedDocuments" :key="folderName" class="border border-gray-200 dark:border-gray-700/80 rounded-xl overflow-hidden bg-white dark:bg-slate-900/60 shadow-sm">
                    <!-- Folder Header -->
                    <div 
                      class="flex items-center justify-between p-3.5 cursor-pointer bg-gray-50 dark:bg-slate-800/40 hover:bg-gray-100 dark:hover:bg-slate-800/80 transition-colors"
                      @click="collapsedFolders[folderName] = !collapsedFolders[folderName]"
                    >
                      <div class="flex items-center gap-3">
                        <FolderIcon class="w-5 h-5 text-amber-500 dark:text-amber-400 shrink-0" />
                        <div>
                          <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ folderName }}</span>
                          <span class="text-xs text-gray-400 dark:text-gray-500 ml-2">({{ docs.length }} files)</span>
                        </div>
                      </div>
                      <component 
                        :is="collapsedFolders[folderName] ? ChevronRightIcon : ChevronDownIcon" 
                        class="w-4 h-4 text-gray-400" 
                      />
                    </div>

                    <!-- Collapsed Items List -->
                    <div v-show="!collapsedFolders[folderName]" class="p-3 divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-slate-900">
                      <div v-if="!docs.length" class="text-center py-6 text-xs text-gray-400 dark:text-gray-500">
                        No files in this category.
                      </div>
                      <div v-else v-for="doc in docs" :key="doc.id" class="document-card py-3 first:pt-0 last:pb-0 flex items-center justify-between gap-4">
                        <div class="flex gap-3 items-start min-w-0">
                          <div class="doc-icon mt-0.5 shrink-0"><PaperclipIcon class="w-4 h-4 text-gray-400" /></div>
                          <div class="min-w-0">
                            <strong class="text-sm font-semibold text-gray-800 dark:text-gray-200 block truncate" :title="doc.title">{{ doc.title }}</strong>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate" :title="doc.description || doc.file_name">{{ doc.description || doc.file_name || 'Project attachment' }}</p>
                            <div class="doc-meta flex items-center gap-2 text-[10px] text-gray-400 mt-1 flex-wrap">
                              <span class="document-status px-1.5 py-0.5 rounded text-[9px] font-semibold uppercase" :class="documentStatusClass(doc)">{{ documentStatusLabel(doc) }}</span>
                              <span>{{ doc.category || 'General' }}</span>
                              <span>{{ fmtFileSize(doc.file_size || 0) }}</span>
                              <span v-if="doc.uploaded_at">{{ fmtDate(doc.uploaded_at) }}</span>
                              <span v-if="doc.uploaded_by">by {{ doc.uploaded_by.full_name || doc.uploaded_by.name }}</span>
                            </div>
                            <p v-if="doc.update_request_reason" class="document-revision-note text-xs text-amber-600 dark:text-amber-400 mt-1.5 p-1.5 bg-amber-50 dark:bg-amber-900/10 rounded">
                              Update requested: {{ doc.update_request_reason }}
                            </p>
                          </div>
                        </div>
                        <div class="doc-actions flex items-center gap-2 shrink-0">
                          <button v-if="canPreviewDocument(doc)" class="doc-action-btn" @click="viewDocument(doc)">View</button>
                          <button class="icon-action" title="Download" @click="downloadDocument(doc)"><DownloadIcon class="icon" /></button>
                          <button
                            v-if="canSubmitDocumentsAction && isDraftDocument(doc)"
                            class="doc-action-btn"
                            :disabled="isDocumentActing(doc.id)"
                            @click="submitDocument(doc)"
                          >Submit</button>
                          <button
                            v-if="canRequestDocumentUpdateAction && isSubmittedDocument(doc)"
                            class="doc-action-btn warn"
                            :disabled="isDocumentActing(doc.id)"
                            @click="requestDocumentUpdate(doc)"
                          >Request Update</button>
                          <button v-if="canDeleteDocument(doc)" class="icon-action danger" title="Delete" @click="deleteDocument(doc.id)"><TrashIcon class="icon" /></button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-pane"><PaperclipIcon class="ep-icon" /><p>No attachments uploaded yet</p></div>
              </div>

              <!-- Timeline -->
              <div v-show="activeTab === 'timeline'" class="tab-pane">
                <div v-if="timelineLoading" class="tl-loading"><div class="spinner-sm"></div> Loading history...</div>
                <div v-else-if="timelineData">
                  <div v-if="timelineData.stage_history.length > 0" class="tl-section">
                    <h4 class="tl-title">Stage Changes</h4>
                    <div class="tl-items">
                      <div v-for="h in timelineData.stage_history" :key="h.id" class="tl-item">
                        <div class="tl-dot s-dot"><ArrowRightIcon class="ti-" /></div>
                        <div class="tl-content">
                          <p class="tl-text">Stage changed<span v-if="h.from_stage" class="from"> from {{ h.from_stage.name }}</span> to <strong>{{ h.to_stage?.name }}</strong></p>
                          <p v-if="h.change_reason" class="tl-reason">{{ h.change_reason }}</p>
                          <p class="tl-meta">{{ fmtDate(h.changed_at) }} · {{ h.changed_by_user?.name }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="timelineData.status_history.length > 0" class="tl-section">
                    <h4 class="tl-title">Status Changes</h4>
                    <div class="tl-items">
                      <div v-for="h in timelineData.status_history" :key="h.id" class="tl-item">
                        <div class="tl-dot st-dot"><CheckCircleIcon class="ti-" /></div>
                        <div class="tl-content">
                          <p class="tl-text">Status changed<span v-if="h.from_status" class="from"> from {{ h.from_status.name }}</span> to <strong>{{ h.to_status?.name }}</strong></p>
                          <p v-if="h.change_reason" class="tl-reason">{{ h.change_reason }}</p>
                          <p class="tl-meta">{{ fmtDate(h.changed_at) }} · {{ h.changed_by_user?.name }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-if="!timelineData.stage_history.length && !timelineData.status_history.length" class="empty-pane"><ClockIcon class="ep-icon" /><p>No history available</p></div>
                </div>
              </div>

              <!-- Approval Flow -->
              <div v-show="activeTab === 'approval'" class="tab-pane">
                 <ProjectApprovalTimeline 
                   :current-approval="timelineData?.current_approval || null"
                   :approval-history="timelineData?.approval_history || []"
                   :work-plan-tasks="soiTimelineTasks"
                   :requirements="projectRequirements"
                   :can-update-tasks="canUpdateTasksAction"
                   :milestone-only="isExternalProponentUser"
                   :empty-checklist-message="soiChecklistEmptyMessage"
                   :dark-mode="isDarkMode"
                   :updating-task-ids="updatingTaskIds"
                   :loading="timelineLoading"
                   :project-creator-id="projectCreatorId"
                   :process-track="project?.process_track"
                   :active-tab="activeTab"
                   @open-action="showApprovalModal = true"
                   @set-task-status="setTaskStatus"
                   @view-document="viewDocument"
                 />
              </div>

              <!-- Fund Releases -->
              <div v-show="activeTab === 'funds'" class="tab-pane">
                <div class="pane-head">
                  <div>
                    <h3>Fund Releases</h3>
                    <p class="pane-sub">Actual release ledger linked to the configured SOI fund-release requirement or task.</p>
                  </div>
                </div>

                <div class="task-summary-grid">
                  <div class="task-stat">
                    <span>Approved / Target</span>
                    <strong>{{ fmtPeso(fundReleaseSummary.target_amount) }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>Released</span>
                    <strong>{{ fmtPeso(fundReleaseSummary.released_amount) }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>Remaining</span>
                    <strong>{{ fmtPeso(fundReleaseSummary.remaining_amount) }}</strong>
                  </div>
                  <div class="task-stat">
                    <span>Entries</span>
                    <strong>{{ fundReleaseSummary.released_count }}/{{ fundReleaseSummary.release_count }}</strong>
                  </div>
                </div>

                <div class="info-card">
                  <div class="ic-head"><CoinsIcon class="ci" /><span>Release Progress</span></div>
                  <div class="execution-row">
                    <div class="execution-track">
                      <div class="execution-fill" :style="{ width: `${fundReleaseSummary.progress}%` }"></div>
                    </div>
                    <strong>{{ fundReleaseSummary.progress }}%</strong>
                  </div>
                </div>

                <div v-if="canManageFundReleasesAction" class="fund-release-form">
                  <div class="form-section-head">
                    <strong>Record Release</strong>
                    <span>Choose the SOI-linked requirement/task so the finance record updates the correct flow item.</span>
                  </div>
                  <div class="fund-release-grid">
                    <label class="span-2">
                      <span>SOI anchor</span>
                      <select v-model="fundReleaseForm.anchor_key" class="member-input">
                        <option value="">Auto-detect fund-release gate</option>
                        <option v-for="anchor in effectiveFundReleaseAnchors" :key="fundReleaseAnchorKey(anchor)" :value="fundReleaseAnchorKey(anchor)">
                          {{ fundReleaseAnchorLabel(anchor) }}
                        </option>
                      </select>
                    </label>
                    <label>
                      <span>Amount released</span>
                      <input v-model="fundReleaseForm.amount" type="number" min="0" step="0.01" class="member-input" placeholder="0.00" />
                    </label>
                    <label>
                      <span>Approved amount</span>
                      <input v-model="fundReleaseForm.approved_amount" type="number" min="0" step="0.01" class="member-input" placeholder="0.00" />
                    </label>
                    <label>
                      <span>Release date</span>
                      <input v-model="fundReleaseForm.release_date" type="date" class="member-input" />
                    </label>
                    <label>
                      <span>Status</span>
                      <select v-model="fundReleaseForm.status" class="member-input">
                        <option value="draft">Draft</option>
                        <option value="for_review">For Finance Review</option>
                        <option value="approved">Approved</option>
                        <option value="released">Released</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </label>
                    <label>
                      <span>Reference no.</span>
                      <input v-model="fundReleaseForm.reference_no" class="member-input" placeholder="DV / OR / transfer ref." />
                    </label>
                    <label>
                      <span>Payee / investee</span>
                      <input v-model="fundReleaseForm.payee" class="member-input" placeholder="Recipient name" />
                    </label>
                    <label class="span-2">
                      <span>Evidence document</span>
                      <select v-model="fundReleaseForm.document_id" class="member-input">
                        <option value="">No linked document</option>
                        <option v-for="doc in projectDocuments" :key="doc.id" :value="String(doc.id)">
                          {{ doc.title || doc.file_name }}
                        </option>
                      </select>
                    </label>
                    <label class="span-2">
                      <span>Remarks</span>
                      <textarea v-model="fundReleaseForm.remarks" rows="3" class="member-input monitor-textarea" placeholder="Notes on tranche, milestone, voucher, or release condition"></textarea>
                    </label>
                  </div>
                  <div class="activation-actions">
                    <button class="ghost-action" type="button" @click="resetFundReleaseForm">Reset</button>
                    <button class="add-btn" type="button" :disabled="fundReleaseSaving" @click="submitFundRelease">
                      {{ fundReleaseSaving ? 'Saving...' : 'Record Release' }}
                    </button>
                  </div>
                </div>

                <div v-if="projectFundReleases.length" class="task-list">
                  <div v-for="release in projectFundReleases" :key="release.id" class="fund-release-card">
                    <div class="task-main">
                      <div class="task-title-row">
                        <strong>{{ fmtPeso(Number(release.amount || 0)) }}</strong>
                        <span class="task-status" :class="release.status">{{ formatTaskStatus(release.status) }}</span>
                      </div>
                      <p>{{ release.reference_no || 'No reference number' }}<span v-if="release.payee"> · {{ release.payee }}</span></p>
                      <div class="task-meta">
                        <span class="type-chip">{{ formatSoiSectionLabel(release.soi_section || 'agreement_fund_release') }}</span>
                        <span v-if="release.gate_step">{{ formatGateStep(release.gate_step) }}</span>
                        <span v-if="release.release_date">Released {{ fmtDate(release.release_date) }}</span>
                        <span v-if="release.requirement">Requirement: {{ release.requirement.item_name }}</span>
                        <span v-else-if="release.task">Task: {{ release.task.title }}</span>
                      </div>
                      <p v-if="release.remarks" class="upload-note">{{ release.remarks }}</p>
                    </div>
                    <div v-if="release.document" class="doc-actions">
                      <button class="doc-action-btn" type="button" @click="viewDocument(release.document)">View Evidence</button>
                    </div>
                  </div>
                </div>
                <div v-else class="empty-pane"><CoinsIcon class="ep-icon" /><p>No fund release entries recorded yet</p></div>
              </div>
                </div>
              </div>

              <!-- Right: Persistent Metadata Sidebar -->
              <aside class="project-sidebar">
                <!-- Project Info Widget -->
                <div class="sidebar-widget">
                  <h4 class="widget-title"><InfoIcon class="widget-icon" /> Project Details</h4>
                  <div class="sidebar-details">
                    <div class="sd-row"><span class="sdl">Code</span><strong class="sdv font-mono">{{ project.project_code }}</strong></div>
                    <div v-if="project.process_track" class="sd-row"><span class="sdl">SOI Track</span><strong class="sdv">{{ formatProcessTrack(project.process_track) }}</strong></div>
                    <div v-if="project.project_type" class="sd-row"><span class="sdl">Type</span><strong class="sdv">{{ project.project_type.name }}</strong></div>
                    <div v-if="project.industry" class="sd-row"><span class="sdl">Industry</span><strong class="sdv">{{ project.industry.name }}</strong></div>
                    <div v-if="project.sector" class="sd-row"><span class="sdl">Sector</span><strong class="sdv">{{ project.sector.name }}</strong></div>
                    <div v-if="project.investment_type" class="sd-row"><span class="sdl">Investment</span><strong class="sdv">{{ project.investment_type.name }}</strong></div>
                  </div>
                </div>

                <!-- Financial Summary Widget -->
                <div v-if="project.estimated_cost || project.actual_cost" class="sidebar-widget">
                  <h4 class="widget-title"><CoinsIcon class="widget-icon" /> Financial Summary</h4>
                  <div class="sidebar-details">
                    <div v-if="project.estimated_cost" class="sd-row"><span class="sdl">Estimated Cost</span><strong class="sdv text-primary">{{ fmtPeso(project.estimated_cost) }}</strong></div>
                    <div v-if="project.target_amount_to_raise" class="sd-row"><span class="sdl">Target Raise</span><strong class="sdv text-warning">{{ fmtPeso(project.target_amount_to_raise) }}</strong></div>
                    <div v-if="project.ndc_participation" class="sd-row"><span class="sdl">NDC Participation</span><strong class="sdv text-success">{{ fmtPeso(project.ndc_participation) }}</strong></div>
                    <div v-if="project.actual_cost" class="sd-row"><span class="sdl">Actual Cost</span><strong class="sdv text-info">{{ fmtPeso(project.actual_cost) }}</strong></div>
                    <div v-if="project.funding_source" class="sd-row"><span class="sdl">Funding Source</span><strong class="sdv">{{ project.funding_source.name }}</strong></div>
                  </div>
                </div>

                <!-- Project Timeline Widget -->
                <div class="sidebar-widget">
                  <h4 class="widget-title"><CalendarIcon class="widget-icon" /> Project Dates</h4>
                  <div class="sidebar-details">
                    <div v-if="project.proposal_date" class="sd-row"><span class="sdl">Proposed Date</span><strong class="sdv">{{ fmtDate(project.proposal_date) }}</strong></div>
                    <div v-if="project.start_date" class="sd-row"><span class="sdl">Start Date</span><strong class="sdv">{{ fmtDate(project.start_date) }}</strong></div>
                    <div v-if="project.target_completion_date" class="sd-row">
                      <span class="sdl">Target Completion</span>
                      <strong class="sdv" :class="{ 'text-danger font-bold': project.is_overdue }">
                        {{ fmtDate(project.target_completion_date) }}
                      </strong>
                    </div>
                    <div v-if="project.actual_completion_date" class="sd-row"><span class="sdl">Actual Completion</span><strong class="sdv text-success">{{ fmtDate(project.actual_completion_date) }}</strong></div>
                  </div>
                </div>

                <!-- GCG Performance KPIs Widget -->
                <div v-if="hasReportingMetrics" class="sidebar-widget">
                  <h4 class="widget-title"><ActivityIcon class="widget-icon" /> GCG KPIs & Jobs</h4>
                  <div class="sidebar-details">
                    <div class="sd-row"><span class="sdl">Direct Jobs</span><strong class="sdv">{{ metricNumber(financialMetrics.jobs_generated_direct) }}</strong></div>
                    <div class="sd-row"><span class="sdl">Indirect Jobs</span><strong class="sdv">{{ metricNumber(financialMetrics.jobs_generated_indirect) }}</strong></div>
                    <div class="sd-row"><span class="sdl">Retained Jobs</span><strong class="sdv">{{ metricNumber(financialMetrics.retained_jobs) }}</strong></div>
                    <div v-if="!isExternalProponentUser" class="sd-row"><span class="sdl">GCG Score</span><strong class="sdv text-accent">{{ metricNumber(financialMetrics.gcg_score) }}</strong></div>
                    <div v-if="!isExternalProponentUser" class="sd-row"><span class="sdl">Reportable to GCG</span><strong class="sdv">{{ yesNo(financialMetrics.reportable_to_gcg || financialMetrics.is_reportable) }}</strong></div>
                  </div>
                </div>

                <!-- Proponent Details Widget -->
                <div v-if="project.proponent_name" class="sidebar-widget">
                  <h4 class="widget-title"><UserIcon class="widget-icon" /> Proponent Profile</h4>
                  <div class="sidebar-details">
                    <div class="sd-row"><span class="sdl">Name</span><strong class="sdv">{{ project.proponent_name }}</strong></div>
                    <div v-if="project.proponent_contact" class="sd-row"><span class="sdl">Contact</span><strong class="sdv">{{ project.proponent_contact }}</strong></div>
                    <div v-if="project.proponent_email" class="sd-row"><span class="sdl">Email</span><a :href="`mailto:${project.proponent_email}`" class="sdv text-link">{{ project.proponent_email }}</a></div>

                    <button v-if="linkedProponentUser?.id" class="profile-link-btn" @click="openUserProfile(linkedProponentUser.id)" style="margin-top: 0.75rem; width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem; border: 1px solid var(--v-border); background: var(--v-bg); border-radius: 0.5rem; padding: 0.5rem; color: var(--v-text); font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.15s;">
                      <UserIcon class="icon" style="width: 0.875rem; height: 0.875rem;" />
                      View Proponent Profile
                    </button>
                  </div>
                </div>
              </aside>
            </div>
          </template>
        </div>
      </div>
    </Transition>

    <ApprovalActionModal
       v-model="showApprovalModal"
       :approval-id="timelineData?.current_approval?.id || null"
       :current-step="timelineData?.current_approval?.current_step"
       :resubmission="isReturnedProponentResubmission"
       :missing-requirements="missingRequirementsForCurrentStep"
       @submit="handleApprovalSubmit"
    />

    <Transition name="modal">
      <div v-if="showMemberModal" class="modal-overlay member-overlay" :class="{ 'is-dark': isDarkMode }" @mousedown.self="closeMemberModal">
        <div class="member-modal">
          <div class="member-head">
            <h3>{{ editingMemberId ? 'Edit Project Member' : 'Add Project Member' }}</h3>
            <button class="h-close" @click="closeMemberModal"><XIcon class="icon" /></button>
          </div>

          <div class="member-body">
            <template v-if="!editingMemberId">
              <label class="member-label">Email Address *</label>
              <input type="email" v-model="memberForm.email" class="member-input" placeholder="e.g. member@company.com" />

              <label class="member-label">Role *</label>
              <select v-model.number="memberForm.role_id" class="member-input">
                <option :value="0">Select role</option>
                <option v-for="r in roles" :key="r.id" :value="r.id">
                  {{ r.name }}
                </option>
              </select>
            </template>
            <template v-else>
              <label class="member-label">User</label>
              <input class="member-input" :value="selectedUserFullName" readonly disabled />

              <label class="member-label">Role</label>
              <input class="member-input" :value="selectedUserRoleName" readonly disabled />
            </template>

            <div class="member-perm-grid">
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_view" />
                <span>Can view project and tasks</span>
              </label>
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_edit" />
                <span>Can edit project and create/update tasks</span>
              </label>
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_delete" />
                <span>Can delete tasks and project</span>
              </label>
              <label class="member-check">
                <input type="checkbox" v-model="memberForm.can_manage_members" />
                <span>Can manage project members</span>
              </label>
            </div>
          </div>

          <div class="member-foot">
            <button class="remove-btn" @click="closeMemberModal">Cancel</button>
            <button class="add-btn" @click="saveMember">{{ editingMemberId ? 'Save Changes' : 'Add Member' }}</button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, markRaw, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { useProjectStore } from '@/store/projects';
import { useUserStore } from '@/store/user';
import { useAuthStore } from '@/store/auth';
import { useLayoutStore } from '@/store/layout';
import { SITE_MODE } from '@/app/const';
import axiosInstance from '@/utils/axiosInstance';
import { resolveImageUrl } from '@/utils/resolveImage';
import { buildSoiTaskSections, formatSoiSectionLabel } from '@/utils/soiWorkflow';
import type { Project, ProjectFinancialMetrics, ProjectMember, ProjectStageHistory, ProjectStatusHistory, ProjectApproval, ApprovalStepRecord, Document as ProjectDocument, ProjectRequirement, ProjectFundRelease, ProjectFundReleaseSummary, Task as ProjectTask, ProjectImage } from '@/types/project';
import type { ProponentProfile, User as AppUser } from '@/types/user';
import { toast } from 'vue3-toastify';
import { X as XIcon, Edit as EditIcon, Layers as LayersIcon, Briefcase as BriefcaseIcon, FileText as FileTextIcon, Info as InfoIcon, Calendar as CalendarIcon, Coins as CoinsIcon, MapPin as MapPinIcon, User as UserIcon, Users as UsersIcon, UserPlus as UserPlusIcon, Clock as ClockIcon, CheckCircle as CheckCircleIcon, ArrowRight as ArrowRightIcon, AlertCircle as AlertCircleIcon, ListChecks as ListChecksIcon, Paperclip as PaperclipIcon, Upload as UploadIcon, Download as DownloadIcon, Trash as TrashIcon, Image as ImageIcon, History as HistoryIcon, Activity as ActivityIcon, Folder as FolderIcon, ChevronDown as ChevronDownIcon, ChevronLeft as ChevronLeftIcon, ChevronRight as ChevronRightIcon, List as ListIcon, CalendarDays as CalendarDaysIcon, Search as SearchIcon, SlidersHorizontal as SlidersHorizontalIcon } from 'lucide-vue-next';
import ProjectApprovalTimeline from './ProjectApprovalTimeline.vue';
import ApprovalActionModal from './ApprovalActionModal.vue';
import Gantt from 'frappe-gantt';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';

interface Props { modelValue: boolean; projectId: number | null; initialTab?: string; initialRequirementId?: number | null }
const props = defineProps<Props>();
const emit = defineEmits<{ 'update:modelValue': [v: boolean]; edit: [p: Project] }>();

const router = useRouter();
const projectStore = useProjectStore();
const userStore = useUserStore();
const authStore = useAuthStore();
const layoutStore = useLayoutStore();
const isDarkMode = computed(() => {
  const htmlDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  return layoutStore.mode === SITE_MODE.DARK || htmlDark;
});
const loading = ref(false);
const timelineLoading = ref(false);
const loadError = ref('');
let loadRequestId = 0;
const project = ref<Project | null>(null);
const activeTab = ref('overview');
type ImplementationBlocker = { code: string; label: string; detail: string; count?: number };
type ImplementationReadiness = { ready: boolean; already_started: boolean; lifecycle_phase: string; template: string; blockers: ImplementationBlocker[] };
const implementationReadiness = ref<ImplementationReadiness | null>(null);
const implementationLoading = ref(false);
const collapsedSections = ref<Record<string, boolean>>({});
const tabNavRef = ref<HTMLElement | null>(null);
type FundReleaseAnchor = {
  kind: 'requirement' | 'task';
  id: number;
  label: string;
  group?: string | null;
  soi_section?: string | null;
  gate_step?: string | null;
  status?: string | null;
  document_id?: number | null;
};

const fundReleaseSaving = ref(false);
const fundReleaseAnchors = ref<FundReleaseAnchor[]>([]);
const fundReleaseForm = ref({
  anchor_key: '',
  amount: '',
  approved_amount: '',
  release_date: new Date().toISOString().slice(0, 10),
  status: 'released',
  release_type: 'fund_release',
  reference_no: '',
  payee: '',
  document_id: '',
  remarks: '',
});

const currentUserId = computed(() => authStore.user?.id || 0);

const isSuperAdmin = computed(() => {
  const roleName = authStore.user?.role?.name?.toLowerCase();
  const roleId = Number(authStore.user?.role?.id);
  return roleName === 'superadmin' || roleId === 1;
});

const isExternalProponentUser = computed(() => {
  const roleName = authStore.user?.role?.name?.toLowerCase();
  const roleId = Number(authStore.user?.role?.id);
  return roleName === 'proponent' || roleId === 7;
});

const hasAnyPermission = (permissionNames: string[]) =>
  isSuperAdmin.value || permissionNames.some((permission) => authStore.permissions.includes(permission));

const memberFlag = (member: ProjectMember | null | undefined, key: 'can_view' | 'can_edit' | 'can_delete' | 'can_approve' | 'can_manage_members') => {
  if (!member) return false;
  if (member.is_owner || String(member.assignment_type).toLowerCase() === 'owner') return true;
  if (typeof (member as any)[key] === 'boolean') return (member as any)[key] as boolean;
  return Boolean(member.permissions?.[key]);
};

const activeMembers = computed(() => (project.value?.members || []).filter(m => !m.removed_at));

const currentMember = computed(() =>
  activeMembers.value.find((m) => m.user_id === currentUserId.value)
);

const projectCreatorId = computed(() => {
  if (project.value?.created_by_id) return project.value.created_by_id;
  const createdBy = project.value?.created_by;
  if (typeof createdBy === 'number') return createdBy;
  if (createdBy && typeof createdBy === 'object') return createdBy.id;
  return project.value?.creator?.id || undefined;
});

const projectRequirements = computed(() => project.value?.requirements || []);
const projectFundReleases = computed(() => project.value?.fund_releases || []);
const releasedFundReleases = computed(() =>
  projectFundReleases.value.filter((release) => release.status === 'released')
);
const fundReleaseSummary = computed<ProjectFundReleaseSummary>(() => {
  const apiSummary = project.value?.fund_release_summary;
  const target = Number(
    apiSummary?.target_amount ??
    project.value?.ndc_participation ??
    project.value?.target_amount_to_raise ??
    project.value?.estimated_cost ??
    0
  );
  const released = Number(
    apiSummary?.released_amount ??
    releasedFundReleases.value.reduce((sum, release) => sum + Number(release.amount || 0), 0)
  );

  return {
    target_amount: target,
    released_amount: released,
    remaining_amount: Math.max(target - released, 0),
    release_count: apiSummary?.release_count ?? projectFundReleases.value.length,
    released_count: apiSummary?.released_count ?? releasedFundReleases.value.length,
    progress: target > 0 ? Math.min(100, Math.round((released / target) * 100)) : 0,
  };
});
const fundReleaseRequirementOptions = computed(() =>
  projectRequirements.value.filter((req) => isFundReleaseAnchor(req.soi_section, req.gate_step, req.item_name, req.group_name))
);
const fundReleaseTaskOptions = computed(() =>
  topLevelTasks.value
    .flatMap((task) => [task, ...(task.subtasks || [])])
    .filter((task) => isFundReleaseAnchor(task.soi_section, null, task.title, task.task_type))
);
const localFundReleaseAnchors = computed<FundReleaseAnchor[]>(() => {
  const requirementAnchors = fundReleaseRequirementOptions.value.map((req) => ({
    kind: 'requirement' as const,
    id: req.id,
    label: req.item_name,
    group: req.group_name,
    soi_section: req.soi_section,
    gate_step: req.gate_step,
    status: req.status,
    document_id: req.document_id,
  }));
  const taskAnchors = fundReleaseTaskOptions.value.map((task) => ({
    kind: 'task' as const,
    id: task.id,
    label: task.title,
    group: task.task_type,
    soi_section: task.soi_section,
    gate_step: null,
    status: task.status,
    document_id: null,
  }));

  return [...requirementAnchors, ...taskAnchors];
});
const effectiveFundReleaseAnchors = computed(() =>
  fundReleaseAnchors.value.length ? fundReleaseAnchors.value : localFundReleaseAnchors.value
);
const selectedFundReleaseAnchor = computed(() =>
  effectiveFundReleaseAnchors.value.find((anchor) => fundReleaseAnchorKey(anchor) === fundReleaseForm.value.anchor_key) || null
);

const visibleProjectRequirements = computed(() =>
  isExternalProponentUser.value
    ? projectRequirements.value.filter((req) => req.status !== 'pending')
    : projectRequirements.value
);

const isDraftDocument = (doc: ProjectDocument) => (doc.submission_status || 'draft') === 'draft';

const priorityRank = (priority?: string | null) => {
  const map: Record<string, number> = { critical: 6, urgent: 5, high: 4, medium: 3, normal: 3, low: 1 };
  return priority ? map[priority] || 2 : 2;
};

const isInternalRequirement = (requirement: ProjectRequirement) =>
  requirement.owner_type === 'internal' || requirement.visibility === 'internal_only';

const completedRequirementStatuses = ['received', 'approved', 'approved_with_conditions', 'waived'];
const activeRequirementStatuses = ['requested', 'received', 'approved', 'approved_with_conditions', 'deferred', 'for_further_evaluation', 'disapproved'];

const isRequirementActionNeeded = (requirement: ProjectRequirement) => {
  if (isExternalProponentUser.value) {
    if (requirement.document && isDraftDocument(requirement.document)) return true;
    return ['requested', 'deferred', 'for_further_evaluation', 'disapproved'].includes(requirement.status);
  }

  if (requirement.status === 'pending') return true;
  if (requirement.document && (requirement.document.submission_status || 'draft') === 'submitted') return true;
  return ['requested', 'received', 'deferred', 'for_further_evaluation'].includes(requirement.status)
    && !completedRequirementStatuses.includes(requirement.status);
};

const filteredProjectRequirements = computed(() => {
  const items = visibleProjectRequirements.value;

  return items.filter((requirement) => {
    const search = requirementSearch.value.trim().toLowerCase();
    if (search) {
      const haystack = [
        requirement.item_name,
        requirement.group_name,
        requirement.source_document,
        requirement.soi_section ? formatSoiSectionLabel(requirement.soi_section) : '',
        requirement.gate_step ? formatGateStep(requirement.gate_step) : '',
        requirement.status,
        requirement.remarks,
      ].filter(Boolean).join(' ').toLowerCase();
      if (!haystack.includes(search)) return false;
    }

    if (requirementOwnerFilter.value === 'internal' && !isInternalRequirement(requirement)) return false;
    if (requirementOwnerFilter.value === 'proponent' && isInternalRequirement(requirement)) return false;
    if (requirementSectionFilter.value !== 'all' && requirement.soi_section !== requirementSectionFilter.value) return false;

    if (requirementQueueFilter.value === 'all') return true;
    if (requirementQueueFilter.value === 'action_needed') return isRequirementActionNeeded(requirement);
    if (requirementQueueFilter.value === 'drafts') return Boolean(requirement.document && isDraftDocument(requirement.document));
    if (requirementQueueFilter.value === 'requested') return ['requested', 'deferred', 'for_further_evaluation', 'disapproved'].includes(requirement.status);
    if (requirementQueueFilter.value === 'completed') return completedRequirementStatuses.includes(requirement.status);
    if (requirementQueueFilter.value === 'internal') return isInternalRequirement(requirement);
    return true;
  });
});

const proponentVisibleRequirements = computed(() =>
  filteredProjectRequirements.value.filter((req) => !isInternalRequirement(req))
);

const internalProjectRequirements = computed(() =>
  isExternalProponentUser.value
    ? []
    : filteredProjectRequirements.value.filter((req) => isInternalRequirement(req))
);

const buildRequirementGroups = (items: ProjectRequirement[]) => {
  const map = new Map<string, ProjectRequirement[]>();
  items.forEach((req) => {
    const key = req.group_name || 'General';
    map.set(key, [...(map.get(key) || []), req]);
  });

  return Array.from(map.entries()).map(([name, items]) => {
    const requested = items.filter((req) => activeRequirementStatuses.includes(req.status)).length;

    return {
      name,
      items,
      requested,
      completed: items.filter((req) => completedRequirementStatuses.includes(req.status)).length,
    };
  });
};

const requirementGroups = computed(() => buildRequirementGroups(visibleProjectRequirements.value));

const requirementSections = computed(() => {
  const sections = [];
  const proponentGroups = buildRequirementGroups(proponentVisibleRequirements.value);
  if (proponentGroups.length) {
    sections.push({
      id: 'proponent',
      title: 'Proponent Requests',
      description: 'Documents requested from or supplied by the proponent.',
      groups: proponentGroups,
      requested: proponentVisibleRequirements.value.filter((req) => activeRequirementStatuses.includes(req.status)).length,
      completed: proponentVisibleRequirements.value.filter((req) => completedRequirementStatuses.includes(req.status)).length,
    });
  }

  const internalGroups = buildRequirementGroups(internalProjectRequirements.value);
  if (internalGroups.length) {
    sections.push({
      id: 'internal',
      title: 'Internal NDC Artifacts',
      description: 'Endorsement papers, decisions, resolutions, fund release, monitoring, and SOI evidence prepared by NDC.',
      groups: internalGroups,
      requested: internalProjectRequirements.value.filter((req) => activeRequirementStatuses.includes(req.status)).length,
      completed: internalProjectRequirements.value.filter((req) => completedRequirementStatuses.includes(req.status)).length,
    });
  }

  return sections;
});

const displayRequirementSections = computed(() => requirementSections.value);

const projectTasks = computed(() => project.value?.tasks || []);
const isTaskAssignedToCurrentUser = (task: ProjectTask) =>
  Number(task.assigned_to?.id || 0) === Number(currentUserId.value || 0);
const proponentFacingTaskTypes = new Set([
  'compliance',
  'proponent_action',
  'follow_up',
  'document_submission',
  'requirement_submission',
]);
const isProponentFacingTask = (task: ProjectTask) =>
  proponentFacingTaskTypes.has(String(task.task_type || '').toLowerCase());

function sortProjectTasks(items: Project['tasks']) {
  return [...(items || [])].sort((a, b) => {
    const priorityDiff = priorityRank(b.priority) - priorityRank(a.priority);
    if (priorityDiff !== 0) return priorityDiff;

    const aDue = a.due_date ? new Date(a.due_date).getTime() : Number.POSITIVE_INFINITY;
    const bDue = b.due_date ? new Date(b.due_date).getTime() : Number.POSITIVE_INFINITY;
    if (aDue !== bDue) return aDue - bDue;

    return a.id - b.id;
  });
}

const topLevelTasks = computed(() => {
  const items = sortProjectTasks(projectTasks.value.filter((task) => !task.parent_task_id));

  if (!isExternalProponentUser.value) {
    return items;
  }

  return items
    .map((task) => {
      const visibleSubtasks = (task.subtasks || []).filter((subtask) =>
        isTaskAssignedToCurrentUser(subtask) && isProponentFacingTask(subtask)
      );
      if (isTaskAssignedToCurrentUser(task) && isProponentFacingTask(task)) {
        return { ...task, subtasks: visibleSubtasks };
      }

      return visibleSubtasks.length ? { ...task, subtasks: visibleSubtasks } : null;
    })
    .filter((task): task is ProjectTask => Boolean(task));
});

const workPlanSections = computed(() => {
  return buildSoiTaskSections(topLevelTasks.value, project.value?.origin_track || project.value?.process_track);
});

const workPlanChecklistItems = computed(() =>
  workPlanSections.value.flatMap((section) => section.checklistItems)
);

const shouldShowWorkPlanTab = computed(() =>
  !isExternalProponentUser.value || workPlanChecklistItems.value.length > 0
);

const monitoringIsActive = computed(() => project.value?.monitoring_status === 'active');
const projectDocuments = computed(() => project.value?.documents || []);
const projectImages = computed(() => project.value?.images || []);
const financialMetrics = computed<ProjectFinancialMetrics>(() => project.value?.financial_metrics || {});
const hasReportingMetrics = computed(() => {
  const metrics = financialMetrics.value;
  return Object.entries(metrics).some(([key, value]) => {
    if (key === 'monitoring_frequency' && value === 'Quarterly') return false;
    return value !== null && value !== undefined && value !== '' && value !== false;
  });
});
const activeVisibleRequirements = computed(() =>
  visibleProjectRequirements.value.filter((req) => activeRequirementStatuses.includes(req.status))
);
const requiredRequirementCount = computed(() =>
  activeVisibleRequirements.value.filter((req) => req.is_required).length
);
const optionalRequirementCount = computed(() =>
  activeVisibleRequirements.value.filter((req) => !req.is_required).length
);
const uploadedRequirementCount = computed(() =>
  activeVisibleRequirements.value.filter((req) => req.document && !req.document.is_deleted).length
);
const completedRequirementCount = computed(() =>
  visibleProjectRequirements.value.filter((req) => completedRequirementStatuses.includes(req.status)).length
);
const pendingSelectionCount = computed(() =>
  isExternalProponentUser.value ? 0 : projectRequirements.value.filter((req) => req.status === 'pending').length
);

const pendingRequirements = computed(() =>
  visibleProjectRequirements.value.filter((req) => activeRequirementStatuses.includes(req.status) && !completedRequirementStatuses.includes(req.status)).length
);

const tabs = computed(() => {
  const items = [
    { id: 'overview', label: 'Summary', icon: markRaw(InfoIcon) },
    { id: 'approval', label: 'SOI Flow', icon: markRaw(CheckCircleIcon) },
    { id: 'requirements', label: 'Requirements', icon: markRaw(ListChecksIcon), count: pendingRequirements.value },
    { id: 'attachments', label: 'Files', icon: markRaw(PaperclipIcon), count: projectDocuments.value.length + projectImages.value.length },
    { id: 'team', label: 'Team', icon: markRaw(UsersIcon), count: activeMembers.value.length },
    { id: 'timeline', label: 'History', icon: markRaw(ClockIcon) },
  ];

  if (shouldShowWorkPlanTab.value) {
    items.splice(3, 0, {
      id: 'tasks',
      label: 'Work Plan',
      icon: markRaw(ListChecksIcon),
      count: workPlanChecklistItems.value.length,
    });
  }

  if (!isExternalProponentUser.value || projectFundReleases.value.length) {
    items.splice(4, 0, {
      id: 'funds',
      label: 'Fund Releases',
      icon: markRaw(CoinsIcon),
      count: fundReleaseSummary.value.released_count,
    });
  }

  if (monitoringIsActive.value || hasReportingMetrics.value) {
    items.splice(5, 0, { id: 'monitoring', label: 'Implementation Monitoring', icon: markRaw(ActivityIcon) });
  }

  return items;
});

const scrollTabs = (direction: 'left' | 'right') => {
  const el = tabNavRef.value;
  if (!el) return;
  const amount = Math.max(220, Math.round(el.clientWidth * 0.65));
  el.scrollBy({ left: direction === 'right' ? amount : -amount, behavior: 'smooth' });
};

const canEditProjectAction = computed(() => {
  if (project.value?.approval_lock?.is_locked && !project.value.approval_lock.can_override) {
    return false;
  }

  if (
    hasAnyPermission([
      'projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const canManageMembersAction = computed(() => {
  if (
    hasAnyPermission([
      'projects.members.manage', 'project_members.manage', 'project_member.manage', 'manage_members'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_manage_members');
});

const canUploadDocumentsAction = computed(() => {
  if (isSuperAdmin.value || project.value?.approval_lock?.can_override) return true;
  if (projectCreatorId.value === currentUserId.value) return true;
  if (
    hasAnyPermission([
      'documents.create', 'documents.upload', 'projects.update', 'project.update', 'project.edit', 'edit_project'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const canSupplyProjectDocuments = computed(() =>
  !isExternalProponentUser.value || projectCreatorId.value === currentUserId.value
);

const canSubmitDocumentsAction = computed(() => canUploadDocumentsAction.value && canSupplyProjectDocuments.value);

const canRequestDocumentUpdateAction = computed(() => {
  if (isSuperAdmin.value) return true;
  if (
    hasAnyPermission([
      'documents.review', 'documents.update', 'projects.update', 'project.update', 'project.edit', 'edit_project'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_approve') || memberFlag(currentMember.value, 'can_edit');
});

const canManageRequirementsAction = computed(() => {
  if (isExternalProponentUser.value) return false;
  if (isSuperAdmin.value) return true;
  if (
    hasAnyPermission([
      'documents.review', 'documents.update',
      'projects.update', 'project.update', 'project.edit', 'edit_project',
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_approve');
});

const canUpdateTasksAction = computed(() => {
  if (isExternalProponentUser.value) return false;

  if (
    hasAnyPermission([
      'tasks.update', 'task.update', 'edit_task',
      'projects.update', 'project.update', 'project.edit', 'edit_project'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const canManageFundReleasesAction = computed(() => {
  if (isExternalProponentUser.value) return false;
  if (isSuperAdmin.value) return true;
  if (
    hasAnyPermission([
      'projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project',
      'finance.update', 'finance.manage',
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const canManagePostMonitoringAction = computed(() => {
  if (isExternalProponentUser.value) return false;
  if (isSuperAdmin.value) return true;
  if (
    hasAnyPermission([
      'projects.update', 'projects.edit', 'project.update', 'project.edit', 'edit_project',
      'reports.create', 'reports.view'
    ])
  ) return true;
  return memberFlag(currentMember.value, 'can_edit');
});

const isProjectMonitoringEligible = computed(() => {
  return ['implementation_monitoring', 'post_investment', 'divestment', 'completed'].includes(project.value?.lifecycle_phase || '');
});

const monitoringSubmissionStatus = computed(() => project.value?.monitoring_submission_status || 'not_requested');
const canEditPostMonitoringAction = computed(() =>
  canManagePostMonitoringAction.value
  || Boolean(
    isExternalProponentUser.value
    && monitoringIsActive.value
    && project.value?.monitoring_proponent_access
    && ['draft', 'returned'].includes(monitoringSubmissionStatus.value)
  )
);

const taskViewMode = ref('list');
const ganttViewMode = ref<'Day' | 'Week' | 'Month'>('Month');
const selectedCalendarTask = ref<any | null>(null);
const ganttContainer = ref<HTMLElement | null>(null);
let ganttInstance: any = null;

const ganttTasks = computed(() => {
  const list: any[] = [];
  const validIds = new Set((project.value?.tasks || []).map((t: any) => String(t.id)));
  (project.value?.tasks || []).forEach((t: any) => {
    const rawStart = t.start_date || project.value?.start_date || new Date().toISOString();
    const rawEnd = t.due_date || project.value?.target_completion_date || rawStart;
    const start = String(rawStart).split('T')[0];
    const end = String(rawEnd).split('T')[0];
    let deps = '';
    if (t.parent_id && validIds.has(String(t.parent_id))) {
      deps = String(t.parent_id);
    }
    list.push({
      id: String(t.id),
      name: t.title,
      start: start,
      end: end,
      progress: t.progress_percentage || 0,
      dependencies: deps
    });
  });
  return list;
});

function initGantt() {
  nextTick(() => {
    if (!ganttContainer.value || !ganttTasks.value.length) return;
    try {
      ganttContainer.value.innerHTML = '';
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.id = 'gantt-svg';
      ganttContainer.value.appendChild(svg);

      ganttInstance = new Gantt(svg, ganttTasks.value, {
        header_height: 50,
        column_width: 30,
        step: 24,
        view_modes: ['Quarter Day', 'Half Day', 'Day', 'Week', 'Month'],
        bar_height: 20,
        bar_corner_radius: 3,
        arrow_curve: 5,
        padding: 18,
        view_mode: ganttViewMode.value,
        date_format: 'YYYY-MM-DD',
        language: 'en'
      });
    } catch (err) {
      console.error('Failed to initialize Gantt chart:', err);
    }
  });
}

const calendarEvents = computed(() => {
  return (project.value?.tasks || []).map((t: any) => {
    const start = t.start_date || t.due_date || new Date().toISOString().split('T')[0];
    const end = t.due_date || start;
    return {
      id: String(t.id),
      title: t.title,
      start: start,
      end: end,
      color: t.status === 'completed' ? '#10b981' : (t.status === 'in_progress' ? '#2563eb' : '#64748b'),
      allDay: true,
      extendedProps: { status: t.status, progress: t.progress_percentage || 0 }
    };
  });
});

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  events: calendarEvents.value,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,dayGridWeek,dayGridDay'
  },
  themeSystem: 'standard',
  height: 'auto',
  eventContent: (arg: any) => {
    const title = arg.event.title;
    const extend = arg.event.extendedProps;
    const status = extend.status || 'pending';
    const progress = extend.progress !== undefined ? `${extend.progress}%` : '';
    const icon = status === 'completed' ? '✓' : (status === 'in_progress' ? '●' : '○');
    return {
      html: `
        <div class="fc-event-custom-content flex items-center justify-between gap-1 w-full overflow-hidden px-1.5 py-0.5">
          <span class="truncate font-semibold text-[11px]">${icon} ${title}</span>
          ${progress ? `<span class="text-[9px] bg-black/25 dark:bg-white/25 px-1 rounded-sm font-bold shrink-0 text-white">${progress}</span>` : ''}
        </div>
      `
    };
  },
  eventClick: (info: any) => {
    const taskId = Number(info.event.id);
    const task = (project.value?.tasks || []).find((t: any) => t.id === taskId);
    if (task) {
      selectedCalendarTask.value = task;
    }
  }
}));

watch([taskViewMode, activeTab, ganttViewMode, () => project.value], () => {
  if (activeTab.value === 'tasks' && taskViewMode.value === 'gantt') {
    initGantt();
  }
});

const fileSearchQuery = ref('');
const collapsedFolders = ref<Record<string, boolean>>({
  'Intake': false,
  'Due Diligence': false,
  'Agreements': false,
  'Monitoring': false,
  'General': false
});

const groupedDocuments = computed(() => {
  const folders = {
    'Intake': [] as any[],
    'Due Diligence': [] as any[],
    'Agreements': [] as any[],
    'Monitoring': [] as any[],
    'General': [] as any[]
  };

  const query = fileSearchQuery.value.trim().toLowerCase();

  const filteredDocs = (project.value?.documents || []).filter((doc: any) => {
    if (!query) return true;
    const title = (doc.title || '').toLowerCase();
    const desc = (doc.description || '').toLowerCase();
    const cat = (doc.category || '').toLowerCase();
    const name = (doc.file_name || '').toLowerCase();
    return title.includes(query) || desc.includes(query) || cat.includes(query) || name.includes(query);
  });

  filteredDocs.forEach((doc: any) => {
    const cat = (doc.category || '').toLowerCase();
    if (cat.includes('intake') || cat.includes('loi') || cat.includes('concept') || cat.includes('pitch') || cat.includes('proposal') || cat.includes('profile') || cat.includes('letter') || cat.includes('register')) {
      folders['Intake'].push(doc);
    } else if (cat.includes('diligence') || cat.includes('evaluation') || cat.includes('dd') || cat.includes('screening') || cat.includes('review') || cat.includes('checklist')) {
      folders['Due Diligence'].push(doc);
    } else if (cat.includes('agreement') || cat.includes('nda') || cat.includes('resolution') || cat.includes('board') || cat.includes('moa') || cat.includes('jv') || cat.includes('release') || cat.includes('contract')) {
      folders['Agreements'].push(doc);
    } else if (cat.includes('monitoring') || cat.includes('report') || cat.includes('compliance') || cat.includes('evidence') || cat.includes('progress') || cat.includes('gad')) {
      folders['Monitoring'].push(doc);
    } else {
      folders['General'].push(doc);
    }
  });

  return folders;
});

const tabBodyRef = ref<HTMLElement | null>(null);
const highlightedRequirementId = ref<number | null>(null);
const timelineData = ref<{ stage_history: ProjectStageHistory[]; status_history: ProjectStatusHistory[]; current_approval: ProjectApproval | null; approval_history: ApprovalStepRecord[] } | null>(null);

const currentApprovalStepIsProponent = computed(() => {
  const step = timelineData.value?.current_approval?.current_step;
  if (!step) return false;

  const roleName = step.role?.name?.toLowerCase() || '';
  const stepName = step.step_name?.toLowerCase() || '';

  return roleName === 'proponent' || stepName.includes('proponent submission');
});

const isReturnedProponentResubmission = computed(() =>
  timelineData.value?.current_approval?.overall_status === 'returned'
  && currentApprovalStepIsProponent.value
);

const canAttachRequirement = (requirement: ProjectRequirement) => {
  if (!canSupplyProjectDocuments.value || !canUploadDocumentsAction.value) return false;
  if (isExternalProponentUser.value && isInternalRequirement(requirement)) return false;
  if (['approved', 'approved_with_conditions', 'waived'].includes(requirement.status)) return false;
  if (!isExternalProponentUser.value && requirement.document) return false;
  if (
    isExternalProponentUser.value &&
    requirement.document &&
    !isDraftDocument(requirement.document) &&
    !['deferred', 'for_further_evaluation', 'disapproved'].includes(requirement.status)
  ) return false;
  const allowedStatuses = isExternalProponentUser.value
    ? ['requested', 'received', 'deferred', 'for_further_evaluation']
    : ['pending', 'requested', 'received', 'deferred', 'for_further_evaluation'];
  return allowedStatuses.includes(requirement.status);
};

const requirementAttachLabel = (requirement: ProjectRequirement) => {
  if (!requirement.document) {
    return isExternalProponentUser.value ? 'Attach File' : 'Attach';
  }

  if (isExternalProponentUser.value && requirement.document && isDraftDocument(requirement.document)) {
    return 'Replace Draft';
  }

  if (isExternalProponentUser.value && requirement.document) {
    return 'Replace File';
  }

  return 'Attach';
};

const users = ref<AppUser[]>([]);
const proponentHistory = ref<Project[]>([]);
const proponentHistoryLoading = ref(false);
const proponentHistoryChecked = ref(false);
const showMemberModal = ref(false);
const showApprovalModal = ref(false);
const editingMemberId = ref<number | null>(null);
const documentFileInput = ref<HTMLInputElement | null>(null);
const imageFileInput = ref<HTMLInputElement | null>(null);
const selectedDocumentFile = ref<File | null>(null);
const selectedImageFiles = ref<File[]>([]);
const documentUploading = ref(false);
const imageUploading = ref(false);
const documentSubmitting = ref(false);
const proposalSubmitting = ref(false);
const documentActionIds = ref<Set<number>>(new Set());
const imageActionIds = ref<Set<number>>(new Set());
const activeRequirementId = ref<number | null>(null);
const updatingTaskIds = ref<Set<number>>(new Set());
const imageUploadTitle = ref('');
const monitoringEditing = ref(false);
const monitoringSaving = ref(false);
const monitoringActivationOpen = ref(false);
const monitoringReviewRemarks = ref('');
const monitoringActivationForm = ref({
  due_date: '',
  instructions: '',
  proponent_access: true,
});
const documentForm = ref({
  title: '',
  category: 'Project File',
  description: '',
});
const allowedDocumentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg', 'webp'];
const allowedDocumentAccept = allowedDocumentExtensions.map((extension) => `.${extension}`).join(',');
const previewableDocumentTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
const reviewingRequirementId = ref<number | null>(null);
const requirementReviewSaving = ref(false);
const requirementReviewForm = ref({
  status: 'received',
  remarks: '',
  due_date: '',
});
const monitoringForm = ref<ProjectFinancialMetrics>({
  jobs_generated_direct: null,
  jobs_generated_indirect: null,
  retained_jobs: null,
  projected_revenue: null,
  actual_revenue: null,
  dividend_remittance: null,
  gcg_relevance: false,
  gcg_score: null,
  reportable_to_gcg: false,
  is_reportable: false,
  monitoring_frequency: '',
  reporting_period: '',
  monitoring_indicators: '',
  gcg_metrics: '',
  social_impact_notes: '',
});
const memberForm = ref({
  email: '',
  user_id: 0,
  role_id: 0,
  assignment_type: 'member' as 'member' | 'owner' | 'collaborator' | 'observer',
  can_view: true,
  can_edit: false,
  can_delete: false,
  can_approve: false,
  can_manage_members: false,
});

const soiTimelineTasks = computed(() => {
  const items = sortProjectTasks(projectTasks.value.filter((task) => !task.parent_task_id));

  if (!isExternalProponentUser.value) {
    return items;
  }

  return items.map((task) => ({
    ...task,
    description: null,
    assigned_to: null,
    assigned_by: null,
    due_date: null,
    priority: null,
    estimated_hours: null,
    actual_hours: null,
    is_overdue: false,
    is_milestone: true,
    subtasks: [],
  }));
});

const soiChecklistEmptyMessage = computed(() =>
  isExternalProponentUser.value
    ? 'No visible milestones for this section yet.'
    : 'No automated checklist items for this section yet.'
);

const draftDocuments = computed(() =>
  projectDocuments.value.filter((doc) => (doc.submission_status || 'draft') === 'draft')
);
const requirementQueueFilter = ref('action_needed');
const requirementSearch = ref('');
const requirementOwnerFilter = ref<'all' | 'proponent' | 'internal'>('all');
const requirementSectionFilter = ref('all');
const hasSoiDetails = computed(() => Boolean(
  project.value?.ndc_investment_criteria?.length ||
  project.value?.project_rationale ||
  project.value?.target_beneficiaries ||
  project.value?.expected_benefits ||
  project.value?.risk_analysis ||
  project.value?.next_steps
));

const soiTrackDefinitions: Record<string, { label: string; party: string }[]> = {
  spg_jv: [
    { label: 'LOI / Proposal Received', party: 'Proponent' },
    { label: 'NDA Signed', party: 'Legal' },
    { label: 'Due Diligence & Evaluation', party: 'Project Officer' },
    { label: 'Workgroup Endorsement', party: 'Workgroup Head' },
    { label: 'ManCom Approval', party: 'Management Committee' },
    { label: 'Board Approval', party: 'Board of Directors' },
    { label: 'JV Agreement Execution', party: 'Legal / Proponent' },
    { label: 'Implementation & Monitoring', party: 'Project Officer' },
    { label: 'Post-Investment Review', party: 'SPG Head' },
    { label: 'Exit / Divestment', party: 'Management Committee' },
  ],
  spg_ndc_own: [
    { label: 'Concept Paper Submitted', party: 'Project Officer' },
    { label: 'Feasibility Study', party: 'Project Officer' },
    { label: 'Due Diligence & Evaluation', party: 'Project Officer' },
    { label: 'Workgroup Endorsement', party: 'Workgroup Head' },
    { label: 'ManCom Approval', party: 'Management Committee' },
    { label: 'Board Approval', party: 'Board of Directors' },
    { label: 'Capital Deployment', party: 'Finance' },
    { label: 'Implementation & Monitoring', party: 'Project Officer' },
    { label: 'Performance Review', party: 'SPG Head' },
    { label: 'Completion / Divestment', party: 'Management Committee' },
  ],
  spg_traditional: [
    { label: 'Proposal Received', party: 'Proponent' },
    { label: 'Screening & Evaluation', party: 'Project Officer' },
    { label: 'Workgroup Endorsement', party: 'Workgroup Head' },
    { label: 'ManCom Approval', party: 'Management Committee' },
    { label: 'Board Approval', party: 'Board of Directors' },
    { label: 'Agreement & Release', party: 'Legal / Finance' },
    { label: 'Implementation & Monitoring', party: 'Project Officer' },
    { label: 'Post-Investment Strategy', party: 'SPG Head' },
  ],
  bdg_investment: [
    { label: 'LOI / Proposal Intake', party: 'Proponent' },
    { label: 'Screening & Due Diligence', party: 'Project Officer' },
    { label: 'SDD Endorsement', party: 'SDD Head' },
    { label: 'ManCom Approval', party: 'Management Committee' },
    { label: 'Board Approval', party: 'Board of Directors' },
    { label: 'Agreement & Release', party: 'Legal / Finance' },
    { label: 'Implementation & Monitoring', party: 'Project Officer' },
    { label: 'GCG Reporting', party: 'Compliance' },
  ],
};

const soiStageOrder = [
  'Intake', 'Requirements', 'Screening', 'Evaluation', 'Due Diligence',
  'Workgroup', 'ManCom', 'Management Approval', 'Board', 'Agreement',
  'Implementation', 'Monitoring', 'Post-Investment', 'Divestment', 'Completion',
];

const soiTrackerMilestones = computed(() => {
  const track = project.value?.process_track || '';
  const milestones = soiTrackDefinitions[track];
  if (!milestones) return [];

  const currentStage = project.value?.current_stage?.name || '';
  const stageHistory = (project.value as any)?.stage_history || [];
  const completedStages = new Set(stageHistory.map((h: any) => h.stage?.name || h.name || ''));
  
  // Determine the current position using stage order
  const currentIdx = soiStageOrder.findIndex(s => currentStage.toLowerCase().includes(s.toLowerCase()));

  return milestones.map((m, idx) => {
    // Simple heuristic: milestones before the current position are completed
    const totalMilestones = milestones.length;
    const progressRatio = currentIdx >= 0 ? (currentIdx + 1) / soiStageOrder.length : 0;
    const milestoneThreshold = Math.floor(progressRatio * totalMilestones);

    let status: 'completed' | 'current' | 'pending' = 'pending';
    if (idx < milestoneThreshold) {
      status = 'completed';
    } else if (idx === milestoneThreshold) {
      status = 'current';
    }

    // Try to find a date from stage history
    const historyMatch = stageHistory.find((h: any) => {
      const name = (h.stage?.name || h.name || '').toLowerCase();
      return m.label.toLowerCase().includes(name) || name.includes(m.label.toLowerCase().split(' ')[0]);
    });

    return {
      label: m.label,
      party: m.party,
      status,
      date: historyMatch?.created_at || historyMatch?.transitioned_at || null,
    };
  });
});


const requirementQueueFilters = computed(() => {
  const items = visibleProjectRequirements.value;
  const filters = [
    { id: 'action_needed', label: isExternalProponentUser.value ? 'To Submit' : 'Needs Review', count: items.filter(isRequirementActionNeeded).length },
    { id: 'drafts', label: 'Drafts', count: items.filter((req) => req.document && isDraftDocument(req.document)).length },
    { id: 'requested', label: 'Requested', count: items.filter((req) => ['requested', 'deferred', 'for_further_evaluation', 'disapproved'].includes(req.status)).length },
    { id: 'completed', label: 'Confirmed', count: items.filter((req) => completedRequirementStatuses.includes(req.status)).length },
    { id: 'all', label: 'All', count: items.length },
  ];

  if (!isExternalProponentUser.value) {
    filters.splice(4, 0, { id: 'internal', label: 'Internal', count: items.filter(isInternalRequirement).length });
  }

  return filters;
});

const requirementSectionOptions = computed(() => {
  const sections = new Set<string>();
  visibleProjectRequirements.value.forEach((req) => {
    if (req.soi_section) sections.add(req.soi_section);
  });
  return Array.from(sections).sort((a, b) => formatSoiSectionLabel(a).localeCompare(formatSoiSectionLabel(b)));
});

const resetRequirementFilters = () => {
  requirementSearch.value = '';
  requirementOwnerFilter.value = 'all';
  requirementSectionFilter.value = 'all';
  requirementQueueFilter.value = 'action_needed';
};

const emptyRequirementQueueMessage = computed(() => {
  if (requirementQueueFilter.value === 'action_needed') {
    return isExternalProponentUser.value
      ? 'No files need action from you right now.'
      : 'No requirements need review or request action right now.';
  }

  return 'No requirements match this view.';
});



watch(() => displayRequirementSections.value, (sections) => {
  if (sections && sections.length) {
    sections.forEach(sec => {
      if (collapsedSections.value[sec.id] === undefined) {
        collapsedSections.value[sec.id] = false;
      }
    });
  }
}, { immediate: true });

const missingRequirementsForCurrentStep = computed(() => {
  const step = timelineData.value?.current_approval?.current_step;
  if (!step || !project.value) return [];

  const stepName = (step.step_name || '').toLowerCase();
  const roleName = (step.role?.name || '').toLowerCase();
  const track = (project.value.process_track || '').toLowerCase();
  const text = `${stepName} ${roleName}`;

  let gates: string[] = [];

  if (track === 'spg_jv') {
    if (stepName.includes('mancom approval to proceed') || stepName.includes('jv project conceptualization') || stepName.includes('procurement of consultancy')) {
      gates = [];
    } else if (stepName.includes('mancom jv project decision')) {
      gates = ['spg_jv_mancom_project_decision'];
    } else if (stepName.includes('board approval of jv project')) {
      gates = ['spg_jv_board_project_approval'];
    } else if (stepName.includes('neda-icc') || stepName.includes('neda icc')) {
      gates = ['spg_jv_neda_icc'];
    } else if (stepName.includes('jva terms') || stepName.includes('jv-sc') || stepName.includes('jv sc')) {
      gates = ['spg_jv_jva_terms_jvsc'];
    } else if (stepName.includes('jv partner selection')) {
      gates = ['spg_jv_selection_award'];
    } else if (stepName.includes('final board approval')) {
      gates = ['spg_jv_final_award'];
    } else if (stepName.includes('signing of jva')) {
      gates = ['spg_jv_jva_signing'];
    }
  } else if (track === 'spg_ndc_own') {
    if (stepName.includes('mancom approval to proceed') || stepName.includes('project conceptualization') || stepName.includes('procurement of consultancy')) {
      gates = [];
    } else if (stepName.includes('mancom project decision')) {
      gates = ['spg_ndc_own_mancom_project_decision'];
    } else if (stepName.trim() === 'board approval') {
      gates = ['spg_ndc_own_board_approval'];
    } else if (stepName.includes('ded') || stepName.includes('construction procurement') || stepName.includes('construction agreement')) {
      gates = ['spg_ndc_own_ded_construction'];
    } else if (stepName.includes('construction implementation') || stepName.includes('turn-over') || stepName.includes('turnover')) {
      gates = ['spg_ndc_own_turnover'];
    }
  } else {
    if (text.includes('mancom') || text.includes('management committee')) {
      gates.push('mancom');
    }
    if (text.includes('board')) {
      gates.push('board');
    }
    if (text.includes('legal') || text.includes('finance') || text.includes('agreement') || text.includes('fund release') || text.includes('signing')) {
      gates.push('fund_release');
    }
    if (text.includes('neda') || text.includes('icc') || text.includes('selection') || text.includes('award') || text.includes('partner selection')) {
      gates.push('jv');
    }
    if (text.includes('monitor') || text.includes('milestone') || text.includes('adjustment')) {
      gates.push('monitoring');
    }
    if (text.includes('divest')) {
      gates.push('divestment');
    }
  }

  if (gates.length === 0) return [];

  const completedStatuses = ['received', 'approved', 'approved_with_conditions', 'waived'];

  return projectRequirements.value
    .filter((req) => {
      // Must be internal, required, and match the gate step
      if (req.owner_type !== 'internal' || !req.is_required || !req.gate_step) return false;
      if (!gates.includes(req.gate_step)) return false;
      
      // Filter out completed ones
      if (completedStatuses.includes(req.status || '')) {
        // If waived, must have remarks
        if (req.status === 'waived' && req.remarks && req.remarks.trim() !== '') {
          return false;
        }
        return req.status !== 'waived';
      }
      return true;
    })
    .map((req) => req.item_name);
});

const trackPhaseDefinitions = {
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

const sectionLabels: Record<string, string> = {
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

function normalizeSection(value?: string | null, fallback?: string | null): string {
  const normalized = String(value || '').toLowerCase();
  const sectionOrder = [
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
  if (sectionOrder.includes(normalized)) return normalized;

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

function deriveStepSection(step: any): string {
  return normalizeSection(step.soi_section, step.step_name);
}

function dialogDeriveRequirementGroupKey(req: any): string {
  const track = String(project.value?.process_track || '').toLowerCase();
  const section = String(req.soi_section || '').toLowerCase();
  const gate = String(req.gate_step || '').toLowerCase();

  if (track === 'spg_jv') {
    if (section === 'intake') return 'spg_jv_concept';
    if (section === 'due_diligence') return 'spg_jv_study';
    if (gate === 'spg_jv_mancom_project_decision' || gate === 'spg_jv_board_project_approval') return 'spg_jv_mancom_board';
    if (gate === 'spg_jv_neda_icc' || gate === 'spg_jv_jva_terms_jvsc') return 'spg_jv_neda_jvsc';
    if (gate === 'spg_jv_selection_award' || gate === 'spg_jv_final_award' || gate === 'spg_jv_jva_signing' || section === 'agreement_fund_release') return 'spg_jv_selection_signing';
    return section;
  }

  if (track === 'spg_ndc_own') {
    if (section === 'intake') return 'spg_own_concept';
    if (section === 'due_diligence') return 'spg_own_study';
    if (section === 'management_review') return 'spg_own_mancom';
    if (section === 'board_approval') return 'spg_own_board';
    if (gate === 'spg_ndc_own_ded_construction' || section === 'agreement_fund_release') return 'spg_own_ded';
    if (gate === 'spg_ndc_own_turnover' || section === 'implementation_monitoring') return 'spg_own_turnover';
    return section;
  }

  return section;
}

const currentStepGroupKey = computed(() => {
  const step = timelineData.value?.current_approval?.current_step;
  if (!step) return null;
  
  const track = String(project.value?.process_track || '').toLowerCase();
  
  if (track === 'spg_jv') {
    const phase = trackPhaseDefinitions.spg_jv.find((item) => item.stepOrders.includes(step.step_order));
    return phase?.key || deriveStepSection(step);
  }
  
  if (track === 'spg_ndc_own') {
    const phase = trackPhaseDefinitions.spg_ndc_own.find((item) => item.stepOrders.includes(step.step_order));
    return phase?.key || deriveStepSection(step);
  }
  
  return deriveStepSection(step);
});

const currentPhaseLabel = computed(() => {
  const key = currentStepGroupKey.value;
  if (!key) return '';
  const track = String(project.value?.process_track || '').toLowerCase();
  
  if (track === 'spg_jv') {
    const phase = trackPhaseDefinitions.spg_jv.find(p => p.key === key);
    return phase ? phase.label : key;
  }
  if (track === 'spg_ndc_own') {
    const phase = trackPhaseDefinitions.spg_ndc_own.find(p => p.key === key);
    return phase ? phase.label : key;
  }
  
  return sectionLabels[key] || key;
});

const missingRequirementsForCurrentPhase = computed(() => {
  const step = timelineData.value?.current_approval?.current_step;
  if (!step || !project.value) return [];
  
  const phaseKey = currentStepGroupKey.value;
  if (!phaseKey) return [];
  
  const completedStatuses = ['received', 'approved', 'approved_with_conditions', 'waived'];
  
  return projectRequirements.value.filter((req) => {
    if (dialogDeriveRequirementGroupKey(req) !== phaseKey) return false;
    if (!req.is_required) return false;
    return !completedStatuses.includes(req.status || '');
  });
});


const isProposalPackageTrack = computed(() => {
  const track = project.value?.process_track || 'bdg_investment';
  return Boolean(project.value?.is_svf) || ['bdg_investment', 'spg_traditional', 'spg_jv'].includes(track);
});

const hasSoiApprovalStarted = computed(() =>
  Boolean(timelineData.value?.current_approval || timelineData.value?.approval_history?.length)
);

const isDraftProposal = computed(() =>
  (project.value?.status?.name || '').toLowerCase() === 'draft'
);

const canSubmitProposalAction = computed(() =>
  Boolean(project.value?.id)
  && isDraftProposal.value
  && !hasSoiApprovalStarted.value
  && canEditProjectAction.value
);

const initialSubmissionRequirements = computed(() =>
  projectRequirements.value.filter((req) =>
    req.group_name === '1. Intake Pack' &&
    req.is_required &&
    req.status === 'requested'
  )
);

const requirementHasSubmittableDocument = (req: ProjectRequirement) => {
  const document = req.document;
  if (!document || document.is_deleted) return false;

  return ['draft', 'submitted'].includes(document.submission_status || 'draft');
};

const initialPackageMissing = computed(() =>
  initialSubmissionRequirements.value
    .filter((req) => !requirementHasSubmittableDocument(req))
    .map((req) => req.item_name)
);

const canSubmitCurrentPackage = computed(() => {
  if (!isProposalPackageTrack.value || hasSoiApprovalStarted.value) return true;

  return initialPackageMissing.value.length === 0;
});

const submitPackageHelpText = computed(() => {
  if (!isProposalPackageTrack.value || hasSoiApprovalStarted.value) {
    return 'Submit all draft files when the package is ready for SOI review.';
  }

  if (initialPackageMissing.value.length) {
    return 'Complete the required SOI intake files before submitting this proposal.';
  }

  return 'Submit this when the SOI intake files are final. NDC admins and the next SOI approver will be notified.';
});

const submitPackageLabel = computed(() =>
  isProposalPackageTrack.value && !hasSoiApprovalStarted.value
    ? 'Submit Initial Package'
    : 'Submit Draft Files'
);

const attachmentSubmissionHelp = computed(() =>
  isProposalPackageTrack.value && !hasSoiApprovalStarted.value
    ? 'Attach the requested intake files first, then submit the initial package for SOI screening.'
    : 'Upload requested files as drafts first, then submit them when the package is ready for SOI review.'
);

const linkedProponentUser = computed(() => {
  if (project.value?.proponent_user) return project.value.proponent_user;
  if (project.value?.creator && project.value.creator.email === project.value?.proponent_email) return project.value.creator;
  const createdBy = project.value?.created_by;
  if (createdBy && typeof createdBy === 'object' && createdBy.email === project.value?.proponent_email) return createdBy;
  return null;
});

const proponentProfile = computed<ProponentProfile>(() => linkedProponentUser.value?.proponent_profile || {});
const locationDetailRows = computed(() => [
  { label: 'Region', value: project.value?.location_region_name },
  { label: 'Province', value: project.value?.location_province_name },
  { label: 'City / Municipality', value: project.value?.location_city_name },
  { label: 'Barangay', value: project.value?.location_barangay_name },
].filter((row) => String(row.value || '').trim().length > 0));
const proponentProfileRows = computed(() => [
  { label: 'Business Summary', value: proponentProfile.value.business_summary },
  { label: 'Project Experience', value: proponentProfile.value.project_experience },
  { label: 'Company Project Track Record', value: proponentProfile.value.previous_projects },
  { label: 'Major Clients / Partners', value: proponentProfile.value.major_clients },
  { label: 'Certifications / Registrations', value: proponentProfile.value.certifications },
].filter((row) => String(row.value || '').trim().length > 0));
const hasDeclaredProponentProfile = computed(() => proponentProfileRows.value.length > 0);

const canViewProponentHistory = computed(() =>
  !isExternalProponentUser.value &&
  Boolean(project.value?.proponent_name || project.value?.proponent_email) &&
  hasAnyPermission(['projects.view', 'project.view', 'view_project'])
);

const canSubmitMonitoringAction = computed(() =>
  isExternalProponentUser.value
  && monitoringIsActive.value
  && Boolean(project.value?.monitoring_proponent_access)
  && ['draft', 'returned'].includes(monitoringSubmissionStatus.value)
);
const canReviewMonitoringAction = computed(() =>
  canManagePostMonitoringAction.value
  && monitoringIsActive.value
  && monitoringSubmissionStatus.value === 'submitted'
);
const monitoringSubmissionLabel = computed(() => ({
  not_requested: 'Not requested',
  draft: 'Draft in progress',
  submitted: 'Submitted for NDC review',
  returned: 'Returned for correction',
  accepted: 'Accepted by NDC',
}[monitoringSubmissionStatus.value] || 'Not requested'));
const monitoringSubmissionDescription = computed(() => ({
  not_requested: 'NDC has not requested a monitoring report for this project.',
  draft: isExternalProponentUser.value
    ? 'Complete the requested indicators, save your work, then submit the report to NDC.'
    : 'The proponent is preparing the requested monitoring report.',
  submitted: 'The report is locked while NDC reviews the submitted results.',
  returned: 'The report is open for correction and resubmission.',
  accepted: 'The accepted results are included in the proponent performance profile.',
}[monitoringSubmissionStatus.value] || 'No monitoring submission is active.'));
const monitoringGateTitle = computed(() => {
  if (project.value?.monitoring_status === 'active') return 'SOI implementation monitoring is open';
  if (project.value?.monitoring_status === 'completed') return 'SOI implementation monitoring completed';
  return 'SOI implementation monitoring opens after approval';
});
const monitoringGateDescription = computed(() => {
  if (project.value?.monitoring_status === 'active') {
    return project.value.monitoring_instructions || 'NDC opened implementation and portfolio monitoring for this project.';
  }
  if (project.value?.monitoring_status === 'completed') {
    return 'The current monitoring cycle has been closed. NDC may open another compliance period when needed.';
  }
  return 'This follows SOI-02: development and approval first, then implementation milestones, compliance monitoring, and post-investment updates.';
});

const workPlanDescription = computed(() =>
  'Project tasks grouped by the SOI phase configured in Workflow Settings'
);

const workPlanGuideText = computed(() =>
  'Project officers manage phase tasks here from intake through implementation and closeout. Requirements, approval decisions, release evidence, and monitoring submissions remain in their dedicated tabs.'
);

const implementationStarted = computed(() =>
  project.value?.lifecycle_phase === 'implementation_monitoring' || Boolean(implementationReadiness.value?.already_started)
);

const loadImplementationReadiness = async () => {
  if (!project.value?.id || isExternalProponentUser.value) return;
  implementationLoading.value = true;
  try {
    const response = await axiosInstance.get(`/api/projects/${project.value.id}/implementation/readiness`);
    implementationReadiness.value = response.data?.data || response.data;
  } catch (error: any) {
    implementationReadiness.value = null;
    if (error?.response?.status !== 403) toast.error(error?.response?.data?.message || 'Unable to check implementation readiness.');
  } finally {
    implementationLoading.value = false;
  }
};

const startImplementation = async () => {
  if (!project.value?.id || implementationLoading.value) return;
  implementationLoading.value = true;
  try {
    await axiosInstance.post(`/api/projects/${project.value.id}/implementation/start`);
    toast.success('Implementation started and the delivery plan was created.');
    await Promise.all([loadProject(), loadTimeline()]);
    await loadImplementationReadiness();
  } catch (error: any) {
    const blockers = error?.response?.data?.blockers as ImplementationBlocker[] | undefined;
    if (blockers) implementationReadiness.value = { ready: false, already_started: false, lifecycle_phase: project.value.lifecycle_phase || 'development', template: '', blockers };
    toast.error(error?.response?.data?.message || 'Implementation could not be started.');
  } finally {
    implementationLoading.value = false;
  }
};

const taskStats = computed(() => {
  const tasks = workPlanChecklistItems.value;
  const total = tasks.length;
  const completed = tasks.filter((task) => task.status === 'completed').length;
  const inProgress = tasks.filter((task) => task.status === 'in_progress').length;
  const overdue = tasks.filter((task) => task.is_overdue).length;
  const averageProgress = total ? Math.round((completed / total) * 100) : 0;

  return { total, completed, inProgress, overdue, averageProgress };
});

const isFundReleaseAnchor = (...parts: Array<string | null | undefined>) => {
  const text = parts.filter(Boolean).join(' ').toLowerCase();
  return text.includes('fund_release')
    || text.includes('fund release')
    || text.includes('fund deployment')
    || text.includes('release evidence')
    || text.includes('receipt issued')
    || text.includes('drawdown')
    || text.includes('disbursement')
    || text.includes('agreement_fund_release');
};

const fundReleaseAnchorKey = (anchor: FundReleaseAnchor) => `${anchor.kind}:${anchor.id}`;

const fundReleaseAnchorLabel = (anchor: FundReleaseAnchor) => {
  const phase = formatSoiSectionLabel(anchor.soi_section || 'agreement_fund_release');
  return `${anchor.kind === 'requirement' ? 'Requirement' : 'Task'} · ${phase} · ${anchor.label}`;
};

const resetFundReleaseForm = () => {
  const primary = effectiveFundReleaseAnchors.value[0];
  fundReleaseForm.value = {
    anchor_key: primary ? fundReleaseAnchorKey(primary) : '',
    amount: '',
    approved_amount: String(fundReleaseSummary.value.target_amount || ''),
    release_date: new Date().toISOString().slice(0, 10),
    status: 'released',
    release_type: 'fund_release',
    reference_no: '',
    payee: project.value?.proponent_name || '',
    document_id: primary?.document_id ? String(primary.document_id) : '',
    remarks: '',
  };
};

const patchFundRelease = (release: ProjectFundRelease) => {
  if (!project.value) return;
  const releases = project.value.fund_releases || [];
  const exists = releases.some((item) => item.id === release.id);
  const nextReleases = exists
    ? releases.map((item) => (item.id === release.id ? release : item))
    : [release, ...releases];
  project.value = {
    ...project.value,
    fund_releases: nextReleases,
  };
};

const loadFundReleaseAnchors = async () => {
  if (!props.projectId || isExternalProponentUser.value) return;
  try {
    const response = await axiosInstance.get(`/api/projects/${props.projectId}/fund-releases/anchors`);
    const anchors = response.data?.anchors?.items;
    fundReleaseAnchors.value = Array.isArray(anchors) ? anchors : [];
    resetFundReleaseForm();
  } catch (error) {
    fundReleaseAnchors.value = [];
    resetFundReleaseForm();
  }
};

const submitFundRelease = async () => {
  if (!props.projectId || !canManageFundReleasesAction.value) return;
  const amount = Number(fundReleaseForm.value.amount);
  if (!Number.isFinite(amount) || amount <= 0) {
    toast.error('Enter a valid release amount.');
    return;
  }

  const anchor = selectedFundReleaseAnchor.value;
  const payload: Record<string, any> = {
    amount,
    approved_amount: fundReleaseForm.value.approved_amount ? Number(fundReleaseForm.value.approved_amount) : null,
    release_date: fundReleaseForm.value.release_date || null,
    status: fundReleaseForm.value.status,
    release_type: fundReleaseForm.value.release_type || 'fund_release',
    reference_no: fundReleaseForm.value.reference_no || null,
    payee: fundReleaseForm.value.payee || null,
    document_id: fundReleaseForm.value.document_id ? Number(fundReleaseForm.value.document_id) : null,
    remarks: fundReleaseForm.value.remarks || null,
  };

  if (anchor?.kind === 'requirement') payload.requirement_id = anchor.id;
  if (anchor?.kind === 'task') payload.task_id = anchor.id;

  fundReleaseSaving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${props.projectId}/fund-releases`, payload);
    patchFundRelease(response.data?.data || response.data);
    toast.success('Fund release recorded.');
    resetFundReleaseForm();
    await loadProject();
    await loadTimeline();
    activeTab.value = 'funds';
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to record fund release.');
  } finally {
    fundReleaseSaving.value = false;
  }
};

const openFullWorkboard = () => {
  if (!project.value?.id) return;
  emit('update:modelValue', false);
  router.push({
    path: `/projects/${project.value.id}/tasks`,
    query: { view: 'list', project_id: String(project.value.id) },
  });
};

const availableUsers = computed(() => {
  if (editingMemberId.value) {
    return users.value;
  }

  const memberUserIds = new Set(activeMembers.value.map((m) => m.user_id));
  return users.value.filter((u) => !memberUserIds.has(u.id));
});

const selectedUserRoleName = computed(() => {
  const selected = users.value.find((u) => u.id === memberForm.value.user_id);
  return selected?.role?.name || 'No default role';
});

const selectedUserFullName = computed(() => {
  const selected = users.value.find((u) => u.id === memberForm.value.user_id);
  return selected ? (selected.full_name || `${selected.first_name} ${selected.last_name}`) : 'Unknown User';
});

const inviteStatusBadgeClass = (status: string) => {
  if (status === 'accepted') return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
  if (status === 'declined') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
  return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
};


const heroGradient = computed(() => {
  if (project.value?.is_archived) return 'linear-gradient(135deg,#334155 0%,#0f172a 100%)';
  const m: Record<string,string> = { Active:'linear-gradient(135deg,#0f4c81 0%,#0f172a 100%)', 'On Hold':'linear-gradient(135deg,#78350f 0%,#0f172a 100%)', Completed:'linear-gradient(135deg,#1e3a5f 0%,#0f172a 100%)', Cancelled:'linear-gradient(135deg,#7f1d1d 0%,#0f172a 100%)' };
  return m[project.value?.status?.name || ''] || 'linear-gradient(135deg,#312e81 0%,#0f172a 100%)';
});

const heroStatusStyle = computed(() => {
  const m: Record<string,{bg:string;color:string}> = { Active:{bg:'rgba(34,197,94,0.2)',color:'#86efac'}, 'On Hold':{bg:'rgba(245,158,11,0.2)',color:'#fcd34d'}, Completed:{bg:'rgba(59,130,246,0.2)',color:'#93c5fd'}, Cancelled:{bg:'rgba(239,68,68,0.2)',color:'#fca5a5'} };
  const s = m[project.value?.status?.name || ''] || {bg:'rgba(255,255,255,0.1)',color:'rgba(255,255,255,0.8)'};
  return { background: s.bg, color: s.color };
});

watch(
  [() => props.modelValue, () => props.projectId],
  async ([isOpen, projectId]) => {
    if (isOpen && projectId) {
      await loadDialogData();
    }
  },
  { immediate: true }
);

watch(activeTab, async (tab) => {
  if (tab === 'team' && users.value.length === 0) {
    await loadUsers();
  }
});

watch(tabs, (availableTabs) => {
  if (!availableTabs.some((tab) => tab.id === activeTab.value)) {
    activeTab.value = 'overview';
  }
});

const loadProject = async () => {
  if (!props.projectId) return;
  loading.value = true;
  loadError.value = '';
  try {
    const result = await projectStore.fetchProject(props.projectId);
    if (!result) throw new Error('Project details were not found.');
    project.value = result;
    void loadFundReleaseAnchors();
    void loadProponentHistory(false);
    void loadImplementationReadiness();
  } catch (error: any) {
    loadError.value = projectErrorMessage(error, 'Failed to load project details.');
    toast.error(loadError.value);
    throw error;
  } finally { loading.value = false; }
};
const loadTimeline = async () => {
  if (!props.projectId) return;
  timelineLoading.value = true;
  try { timelineData.value = await projectStore.fetchTimeline(props.projectId); }
  catch (error: any) {
    toast.error(projectErrorMessage(error, 'Failed to load project history.'));
    throw error;
  } finally { timelineLoading.value = false; }
};

async function withTimeout<T>(promise: Promise<T>, ms: number, message: string): Promise<T> {
  let timeoutId: ReturnType<typeof setTimeout> | undefined;
  const timeout = new Promise<never>((_, reject) => {
    timeoutId = setTimeout(() => reject(new Error(message)), ms);
  });

  try {
    return await Promise.race([promise, timeout]);
  } finally {
    if (timeoutId) clearTimeout(timeoutId);
  }
}

function projectErrorMessage(error: any, fallback: string) {
  return error?.response?.data?.message || error?.message || fallback;
}

function resetProponentHistory() {
  proponentHistory.value = [];
  proponentHistoryLoading.value = false;
  proponentHistoryChecked.value = false;
}

async function loadProponentHistory(manual = false) {
  if (!canViewProponentHistory.value || !project.value) {
    resetProponentHistory();
    return;
  }

  proponentHistoryLoading.value = true;

  try {
    proponentHistory.value = await projectStore.fetchProponentHistory({
      proponent_name: project.value.proponent_name || undefined,
      proponent_email: project.value.proponent_email || undefined,
      exclude_project_id: project.value.id,
    });
    proponentHistoryChecked.value = true;
    if (manual) {
      toast.success(proponentHistory.value.length
        ? 'Previous proponent projects loaded'
        : 'No company project track record found');
    }
  } catch (error: any) {
    proponentHistoryChecked.value = true;
    if (manual) {
      toast.error(projectErrorMessage(error, 'Failed to check company project track record.'));
    }
  } finally {
    proponentHistoryLoading.value = false;
  }
}

const defaultMonitoringMetrics = (): ProjectFinancialMetrics => ({
  jobs_generated_direct: null,
  jobs_generated_indirect: null,
  retained_jobs: null,
  jobs_direct_male: null,
  jobs_direct_female: null,
  jobs_indirect_male: null,
  jobs_indirect_female: null,
  jobs_retained_male: null,
  jobs_retained_female: null,
  projected_revenue: null,
  actual_revenue: null,
  dividend_remittance: null,
  gcg_relevance: false,
  gcg_score: null,
  reportable_to_gcg: false,
  is_reportable: false,
  monitoring_frequency: '',
  reporting_period: '',
  monitoring_indicators: '',
  gcg_metrics: '',
  social_impact_notes: '',
});

const toNullableNumber = (value: unknown) => {
  if (value === null || value === undefined || value === '') return null;
  const num = Number(value);
  return Number.isFinite(num) ? num : null;
};

const normalizeMonitoringMetrics = (value?: ProjectFinancialMetrics | null): ProjectFinancialMetrics => ({
  ...defaultMonitoringMetrics(),
  ...(value || {}),
  jobs_generated_direct: toNullableNumber(value?.jobs_generated_direct),
  jobs_generated_indirect: toNullableNumber(value?.jobs_generated_indirect),
  retained_jobs: toNullableNumber(value?.retained_jobs),
  jobs_direct_male: toNullableNumber(value?.jobs_direct_male),
  jobs_direct_female: toNullableNumber(value?.jobs_direct_female),
  jobs_indirect_male: toNullableNumber(value?.jobs_indirect_male),
  jobs_indirect_female: toNullableNumber(value?.jobs_indirect_female),
  jobs_retained_male: toNullableNumber(value?.jobs_retained_male),
  jobs_retained_female: toNullableNumber(value?.jobs_retained_female),
  projected_revenue: toNullableNumber(value?.projected_revenue),
  actual_revenue: toNullableNumber(value?.actual_revenue),
  dividend_remittance: toNullableNumber(value?.dividend_remittance),
  gcg_score: toNullableNumber(value?.gcg_score),
  gcg_relevance: Boolean(value?.gcg_relevance),
  reportable_to_gcg: Boolean(value?.reportable_to_gcg || value?.is_reportable),
  is_reportable: Boolean(value?.is_reportable || value?.reportable_to_gcg),
  monitoring_frequency: value?.monitoring_frequency || '',
  reporting_period: value?.reporting_period || '',
  monitoring_indicators: value?.monitoring_indicators || '',
  gcg_metrics: value?.gcg_metrics || '',
  social_impact_notes: value?.social_impact_notes || '',
});

const resetMonitoringForm = () => {
  monitoringForm.value = normalizeMonitoringMetrics(project.value?.financial_metrics);
};

const startMonitoringEdit = () => {
  resetMonitoringForm();
  monitoringEditing.value = true;
};

const cancelMonitoringEdit = () => {
  resetMonitoringForm();
  monitoringEditing.value = false;
};

const persistPostMonitoring = async (silent = false): Promise<boolean> => {
  if (!project.value) return false;
  monitoringSaving.value = true;

  try {
    let payload = normalizeMonitoringMetrics(monitoringForm.value);
    if (isExternalProponentUser.value) {
      const {
        gcg_relevance,
        gcg_score,
        reportable_to_gcg,
        is_reportable,
        gcg_metrics,
        ...proponentMetrics
      } = payload;
      payload = proponentMetrics;
    }
    const response = await axiosInstance.put(`/api/projects/${project.value.id}/monitoring`, {
      financial_metrics: payload,
    });
    const updated = response.data?.project?.data || response.data?.project;
    if (updated) {
      project.value = updated;
    } else {
      await loadProject();
    }
    monitoringEditing.value = false;
    if (!silent) {
      toast.success(isExternalProponentUser.value ? 'Monitoring draft saved' : 'Post-monitoring data saved');
    }
    return true;
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to save post-monitoring data');
    return false;
  } finally {
    monitoringSaving.value = false;
  }
};

const savePostMonitoring = () => persistPostMonitoring(false);

const submitPostMonitoring = async () => {
  if (!project.value) return;
  const saved = await persistPostMonitoring(true);
  if (!saved) return;

  monitoringSaving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${project.value.id}/monitoring/submit`);
    const updated = response.data?.project?.data || response.data?.project;
    if (updated) project.value = updated;
    else await loadProject();
    monitoringEditing.value = false;
    toast.success('Monitoring report submitted to NDC.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to submit monitoring report.');
  } finally {
    monitoringSaving.value = false;
  }
};

const reviewPostMonitoring = async (action: 'accepted' | 'returned') => {
  if (!project.value) return;
  if (action === 'returned' && !monitoringReviewRemarks.value.trim()) {
    toast.error('Add correction remarks before returning the report.');
    return;
  }

  monitoringSaving.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${project.value.id}/monitoring/review`, {
      action,
      remarks: monitoringReviewRemarks.value.trim() || null,
    });
    const updated = response.data?.project?.data || response.data?.project;
    if (updated) project.value = updated;
    else await loadProject();
    monitoringReviewRemarks.value = '';
    toast.success(action === 'accepted' ? 'Monitoring report accepted.' : 'Report returned to the proponent.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to review monitoring report.');
  } finally {
    monitoringSaving.value = false;
  }
};

const activateMonitoring = async () => {
  if (!project.value) return;
  if (!monitoringActivationForm.value.due_date || !monitoringActivationForm.value.instructions.trim()) {
    toast.error('Add a due date and clear monitoring instructions.');
    return;
  }

  monitoringSaving.value = true;
  try {
    await axiosInstance.post(`/api/projects/${project.value.id}/monitoring/activate`, {
      due_date: monitoringActivationForm.value.due_date,
      instructions: monitoringActivationForm.value.instructions.trim(),
      proponent_access: monitoringActivationForm.value.proponent_access,
    });
    monitoringActivationOpen.value = false;
    await Promise.all([loadProject(), loadTimeline()]);
    activeTab.value = 'monitoring';
    toast.success('Monitoring opened and notifications sent.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to open monitoring.');
  } finally {
    monitoringSaving.value = false;
  }
};

const closeMonitoring = async () => {
  if (!project.value || !window.confirm('Close this monitoring period? The proponent will no longer be able to update it.')) return;
  monitoringSaving.value = true;
  try {
    await axiosInstance.post(`/api/projects/${project.value.id}/monitoring/close`);
    await Promise.all([loadProject(), loadTimeline()]);
    activeTab.value = 'overview';
    toast.success('Monitoring period closed.');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to close monitoring.');
  } finally {
    monitoringSaving.value = false;
  }
};

watch(project, () => {
  resetMonitoringForm();
  monitoringEditing.value = false;
  monitoringActivationOpen.value = false;
  monitoringReviewRemarks.value = '';
  monitoringActivationForm.value = {
    due_date: '',
    instructions: '',
    proponent_access: true,
  };
});

async function loadDialogData() {
  if (!props.projectId) return;

  const requestId = ++loadRequestId;
  loading.value = true;
  timelineLoading.value = true;
  loadError.value = '';
  activeTab.value = props.initialTab || 'overview';
  project.value = null;
  timelineData.value = null;
  resetProponentHistory();

  try {
    const [projectResult, timelineResult] = await Promise.all([
      withTimeout(projectStore.fetchProject(props.projectId), 15000, 'Project details took too long to load.'),
      withTimeout(projectStore.fetchTimeline(props.projectId), 15000, 'Project history took too long to load.'),
    ]);

    if (requestId !== loadRequestId) return;
    if (!projectResult) throw new Error('Project details were not found.');
    project.value = projectResult;
    timelineData.value = timelineResult;
    void loadFundReleaseAnchors();
    void loadProponentHistory(false);
    void loadImplementationReadiness();
    await scrollToRequirement(props.initialRequirementId);
  } catch (error: any) {
    if (requestId !== loadRequestId) return;
    loadError.value = projectErrorMessage(error, 'Failed to load project.');
    toast.error(loadError.value);
  } finally {
    if (requestId === loadRequestId) {
      loading.value = false;
      timelineLoading.value = false;
    }
  }
}

const loadUsers = async () => {
  try {
    await userStore.fetchUsers({ per_page: 200, page: 1, is_active: true });
    users.value = [...userStore.users];
  } catch (error) {
    toast.error('Failed to load users');
  }
};

const roles = ref<Array<{ id: number, name: string }>>([]);
const loadRoles = async () => {
  try {
    const res = await axiosInstance.get('/api/lookup/roles');
    roles.value = res.data;
  } catch (error) {
    toast.error('Failed to load roles lookup');
  }
};

const openAddMember = async () => {
  if (roles.value.length === 0) {
    await loadRoles();
  }
  editingMemberId.value = null;
  memberForm.value = {
    email: '',
    user_id: 0,
    role_id: 0,
    assignment_type: 'member',
    can_view: true,
    can_edit: false,
    can_delete: false,
    can_approve: false,
    can_manage_members: false,
  };
  showMemberModal.value = true;
};

const openEditMember = async (member: ProjectMember) => {
  if (users.value.length === 0) {
    await loadUsers();
  }
  editingMemberId.value = member.id;
  memberForm.value = {
    user_id: member.user_id,
    role_id: member.role_id,
    assignment_type: (member.assignment_type as 'member' | 'owner' | 'collaborator' | 'observer') || 'member',
    can_view: memberFlag(member, 'can_view'),
    can_edit: memberFlag(member, 'can_edit'),
    can_delete: memberFlag(member, 'can_delete'),
    can_approve: memberFlag(member, 'can_approve'),
    can_manage_members: memberFlag(member, 'can_manage_members'),
  };
  showMemberModal.value = true;
};

const closeMemberModal = () => {
  showMemberModal.value = false;
  editingMemberId.value = null;
};

watch(() => memberForm.value.user_id, (userId) => {
  const selected = users.value.find((u) => u.id === userId);
  memberForm.value.role_id = selected?.role?.id || 0;
});

const saveMember = async () => {
  if (!props.projectId) return;

  if (editingMemberId.value) {
    if (!memberForm.value.user_id || !memberForm.value.role_id) {
      toast.error('User with valid default role is required');
      return;
    }
  } else {
    if (!memberForm.value.email || !memberForm.value.role_id) {
      toast.error('Email and role are required');
      return;
    }
  }

  try {
    if (editingMemberId.value) {
      await projectStore.addMember(props.projectId, {
        user_id: memberForm.value.user_id,
        role_id: memberForm.value.role_id,
        assignment_type: memberForm.value.assignment_type,
        can_view: memberForm.value.can_view,
        can_edit: memberForm.value.can_edit,
        can_delete: memberForm.value.can_delete,
        can_approve: memberForm.value.can_approve,
        can_manage_members: memberForm.value.can_manage_members,
      });
      toast.success('Member updated');
    } else {
      await axiosInstance.post(`/api/projects/${props.projectId}/invitations`, {
        email: memberForm.value.email,
        role_id: memberForm.value.role_id,
        assignment_type: memberForm.value.assignment_type,
        can_view: memberForm.value.can_view,
        can_edit: memberForm.value.can_edit,
        can_delete: memberForm.value.can_delete,
        can_approve: memberForm.value.can_approve,
        can_manage_members: memberForm.value.can_manage_members,
      });
      toast.success('Invitation sent successfully');
    }
    closeMemberModal();
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to save member');
  }
};

const handleRemoveMember = async (memberId: number) => {
  if (!props.projectId) return;
  const confirmed = window.confirm('Remove this member from the project?');
  if (!confirmed) return;
  try {
    await projectStore.removeMember(props.projectId, memberId);
    toast.success('Member removed');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to remove member');
  }
};

const isTaskUpdating = (taskId: number) => updatingTaskIds.value.has(taskId);

const setTaskUpdating = (taskId: number, value: boolean) => {
  const next = new Set(updatingTaskIds.value);
  if (value) next.add(taskId);
  else next.delete(taskId);
  updatingTaskIds.value = next;
};

const captureTabScroll = () => {
  const el = tabBodyRef.value;
  return {
    top: el?.scrollTop || 0,
    left: el?.scrollLeft || 0,
  };
};

const restoreTabScroll = async (scrollPosition: { top: number; left: number }) => {
  await nextTick();
  const applyScroll = () => {
    const el = tabBodyRef.value;
    if (!el) return;
    el.scrollTo({ top: scrollPosition.top, left: scrollPosition.left, behavior: 'auto' });
  };

  window.setTimeout(applyScroll, 180);
  window.setTimeout(applyScroll, 850);
  window.setTimeout(applyScroll, 1800);
};

const unwrapApiResource = <T>(value: any): T | null => {
  if (!value) return null;
  return (value.data || value) as T;
};

const unwrapApiCollection = <T>(value: any): T[] => {
  if (!value) return [];
  return (Array.isArray(value) ? value : value.data || []) as T[];
};

const patchProjectDocument = (document?: ProjectDocument | null) => {
  if (!project.value || !document?.id) return;
  const documents = project.value.documents || [];
  const exists = documents.some((item) => item.id === document.id);
  project.value = {
    ...project.value,
    documents: exists
      ? documents.map((item) => (item.id === document.id ? { ...item, ...document } : item))
      : [...documents, document],
  };
};

const patchProjectRequirement = (requirement?: ProjectRequirement | null) => {
  if (!project.value || !requirement?.id) return;
  const requirements = project.value.requirements || [];
  const exists = requirements.some((item) => item.id === requirement.id);
  project.value = {
    ...project.value,
    requirements: exists
      ? requirements.map((item) => (item.id === requirement.id ? { ...item, ...requirement } : item))
      : [...requirements, requirement],
  };
  if (requirement.document) patchProjectDocument(requirement.document);
};

const patchSubmittedDocumentsPayload = (payload: any) => {
  patchProjectDocument(unwrapApiResource<ProjectDocument>(payload?.document));
  patchProjectRequirement(unwrapApiResource<ProjectRequirement>(payload?.requirement));
  unwrapApiCollection<ProjectDocument>(payload?.documents).forEach(patchProjectDocument);
  unwrapApiCollection<ProjectRequirement>(payload?.requirements).forEach(patchProjectRequirement);
};

const scrollToRequirement = async (requirementId?: number | null) => {
  if (!requirementId) return;
  activeTab.value = 'requirements';
  highlightedRequirementId.value = Number(requirementId);
  await nextTick();

  const applyScroll = () => {
    const container = tabBodyRef.value;
    const target = container?.querySelector(`[data-requirement-id="${requirementId}"]`) as HTMLElement | null;
    if (!container || !target) return;
    container.scrollTo({ top: Math.max(target.offsetTop - 24, 0), left: 0, behavior: 'auto' });
  };

  window.setTimeout(applyScroll, 120);
  window.setTimeout(applyScroll, 650);
  window.setTimeout(() => {
    if (highlightedRequirementId.value === Number(requirementId)) highlightedRequirementId.value = null;
  }, 3000);
};

const isDocumentActing = (documentId: number) => documentActionIds.value.has(documentId);

const setDocumentActing = (documentId: number, value: boolean) => {
  const next = new Set(documentActionIds.value);
  if (value) next.add(documentId);
  else next.delete(documentId);
  documentActionIds.value = next;
};

const updateTask = async (taskId: number, payload: Record<string, unknown>): Promise<ProjectTask> => {
  const response = await axiosInstance.put(`/api/tasks/${taskId}`, payload);
  return response.data?.data || response.data;
};

const mergeTask = (current: ProjectTask, updated: ProjectTask): ProjectTask => ({
  ...current,
  ...updated,
  subtasks: updated.subtasks?.length ? updated.subtasks : current.subtasks,
});

const applyTaskUpdates = (updatedTask: ProjectTask, updatedParent?: ProjectTask) => {
  if (!project.value?.tasks) return;

  const tasks = project.value.tasks.map((task) => {
    if (updatedParent && task.id === updatedParent.id) {
      const currentSubtasks = task.subtasks || [];
      const nextSubtasks = currentSubtasks.map((subtask) =>
        subtask.id === updatedTask.id ? mergeTask(subtask, updatedTask) : subtask
      );

      return {
        ...mergeTask(task, updatedParent),
        subtasks: nextSubtasks,
      };
    }

    if (task.id === updatedTask.id) {
      return mergeTask(task, updatedTask);
    }

    if (task.subtasks?.length) {
      return {
        ...task,
        subtasks: task.subtasks.map((subtask) =>
          subtask.id === updatedTask.id ? mergeTask(subtask, updatedTask) : subtask
        ),
      };
    }

    return task;
  });

  project.value = {
    ...project.value,
    tasks,
  };
};

const setTaskStatus = async (
  task: ProjectTask,
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled',
  parentTask?: ProjectTask
) => {
  (document.activeElement as HTMLElement | null)?.blur?.();
  const scrollPosition = captureTabScroll();
  setTaskUpdating(task.id, true);
  if (parentTask) setTaskUpdating(parentTask.id, true);

  try {
    const completed = status === 'completed';
    const response = await axiosInstance.patch(`/api/tasks/${task.id}/completion`, { completed });
    const updatedTask = response.data?.data || response.data;
    applyTaskUpdates(updatedTask);
    if (parentTask) await loadProject();
    await restoreTabScroll(scrollPosition);
    toast.success(status === 'completed' ? 'Task completed' : 'Task updated');
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to update task');
  } finally {
    setTaskUpdating(task.id, false);
    if (parentTask) setTaskUpdating(parentTask.id, false);
  }
};

const syncParentTaskProgress = async (
  parentTask: ProjectTask,
  updatedSubtask: ProjectTask
): Promise<ProjectTask | undefined> => {
  const subtasks = parentTask.subtasks || [];
  if (!subtasks.length) return undefined;

  const simulated = subtasks.map((subtask) =>
    subtask.id === updatedSubtask.id ? mergeTask(subtask, updatedSubtask) : subtask
  );
  const completedCount = simulated.filter((subtask) => subtask.status === 'completed').length;
  const activeCount = simulated.filter((subtask) => ['in_progress', 'completed'].includes(subtask.status)).length;
  const progress = Math.round((completedCount / simulated.length) * 100);
  const parentStatus = completedCount === simulated.length
    ? 'completed'
    : activeCount > 0
      ? 'in_progress'
      : 'pending';

  const updatedParent = await updateTask(parentTask.id, {
    status: parentStatus,
    progress_percentage: progress,
  });

  return {
    ...parentTask,
    ...updatedParent,
    subtasks: simulated,
  };
};

const handleClose = () => {
  loadRequestId++;
  emit('update:modelValue', false);
  project.value = null;
  timelineData.value = null;
  resetProponentHistory();
  loadError.value = '';
  loading.value = false;
  timelineLoading.value = false;
  closeMemberModal();
};

const openUserProfile = async (userId?: number | null) => {
  if (!userId) return;
  handleClose();
  await router.push(`/account/profile/${userId}`);
};

const handleApprovalSubmit = async (data: { status: string; comments?: string; conditions?: string }) => {
  if (!timelineData.value?.current_approval?.id) return;
  const aid = timelineData.value.current_approval.id;
  const scrollPosition = captureTabScroll();
  try {
    if (data.status === 'returned') {
      await projectStore.rejectProject(aid, { comments: data.comments || '' });
    } else {
      await projectStore.approveProject(aid, data);
    }
    toast.success(data.status === 'returned' ? 'Project returned for revision' : 'Approval action submitted');
    showApprovalModal.value = false;
    await loadTimeline();
    await loadProject();
    await restoreTabScroll(scrollPosition);
  } catch (err: any) {
    toast.error(err?.response?.data?.message || projectStore.error || 'Failed to submit approval action.');
  }
};

const openImagePicker = () => {
  imageFileInput.value?.click();
};

const handleImageFileSelect = (event: Event) => {
  const input = event.target as HTMLInputElement;
  selectedImageFiles.value = Array.from(input.files || []);
};

const clearSelectedImages = () => {
  selectedImageFiles.value = [];
  imageUploadTitle.value = '';
  if (imageFileInput.value) {
    imageFileInput.value.value = '';
  }
};

const projectImageUrl = (image: ProjectImage) =>
  resolveImageUrl(image.url || image.file_path) || '';

const setImageActing = (imageId: number, acting: boolean) => {
  const next = new Set(imageActionIds.value);
  if (acting) next.add(imageId);
  else next.delete(imageId);
  imageActionIds.value = next;
};

const uploadProjectImages = async () => {
  if (!props.projectId || !selectedImageFiles.value.length) return;

  const payload = new FormData();
  selectedImageFiles.value.forEach((file) => payload.append('images[]', file));
  if (imageUploadTitle.value.trim()) {
    payload.append('title', imageUploadTitle.value.trim());
  }

  imageUploading.value = true;
  try {
    await axiosInstance.post(`/api/projects/${props.projectId}/images`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    toast.success('Map photos uploaded');
    clearSelectedImages();
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to upload map photos');
  } finally {
    imageUploading.value = false;
  }
};

const setMapThumbnail = async (image: ProjectImage) => {
  if (!props.projectId) return;
  setImageActing(image.id, true);
  try {
    await axiosInstance.patch(`/api/projects/${props.projectId}/images/${image.id}/thumbnail`);
    toast.success('Map thumbnail updated');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to update map thumbnail');
  } finally {
    setImageActing(image.id, false);
  }
};

const deleteProjectImage = async (image: ProjectImage) => {
  if (!props.projectId) return;
  const confirmed = window.confirm('Delete this project photo?');
  if (!confirmed) return;

  setImageActing(image.id, true);
  try {
    await axiosInstance.delete(`/api/projects/${props.projectId}/images/${image.id}`);
    toast.success('Map photo deleted');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to delete map photo');
  } finally {
    setImageActing(image.id, false);
  }
};

const openDocumentPicker = (keepRequirement = false) => {
  if (!keepRequirement) {
    activeRequirementId.value = null;
  }
  documentFileInput.value?.click();
};

const handleDocumentFileSelect = (event: Event) => {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0] || null;
  if (file && !isAllowedDocumentFile(file)) {
    toast.error('Unsupported file type. Upload PDF, Word, Excel, CSV, or image files only.');
    input.value = '';
    return;
  }
  selectedDocumentFile.value = file;
  if (file) {
    documentForm.value.title = file.name.replace(/\.[^/.]+$/, '');
    documentForm.value.category = documentForm.value.category || 'Project File';
  }
};

const clearSelectedDocument = () => {
  selectedDocumentFile.value = null;
  activeRequirementId.value = null;
  documentForm.value = {
    title: '',
    category: 'Project File',
    description: '',
  };
  if (documentFileInput.value) {
    documentFileInput.value.value = '';
  }
};

const uploadDocument = async (_submitAfter = false) => {
  if (!props.projectId || !selectedDocumentFile.value) return;
  if (!documentForm.value.title.trim()) {
    toast.error('Document title is required');
    return;
  }

  const scrollPosition = captureTabScroll();
  const shouldStayOnRequirements = activeTab.value === 'requirements';
  const payload = new FormData();
  payload.append('project_id', String(props.projectId));
  payload.append('title', documentForm.value.title.trim());
  payload.append('category', documentForm.value.category.trim() || 'Project File');
  payload.append('description', documentForm.value.description.trim());
  if (activeRequirementId.value) {
    payload.append('requirement_id', String(activeRequirementId.value));
  }
  payload.append('file', selectedDocumentFile.value);

  documentUploading.value = true;
  try {
    const response = await axiosInstance.post('/api/documents', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    patchSubmittedDocumentsPayload(response.data);
    toast.success(response.data?.message || 'Draft file uploaded. Submit it when the package is ready.');
    clearSelectedDocument();
    if (shouldStayOnRequirements) activeTab.value = 'requirements';
    await restoreTabScroll(scrollPosition);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to upload attachment');
  } finally {
    documentUploading.value = false;
  }
};

const isSubmittedDocument = (doc: ProjectDocument) => (doc.submission_status || 'draft') === 'submitted';
const isConfirmedRequirement = (requirement: ProjectRequirement) =>
  ['approved', 'approved_with_conditions', 'waived'].includes(requirement.status);
const canDeleteDocument = (doc: ProjectDocument) =>
  canUploadDocumentsAction.value && (!isSubmittedDocument(doc) || isSuperAdmin.value);
const canDeleteRequirementDocument = (requirement: ProjectRequirement) => {
  if (!requirement.document || !canSupplyProjectDocuments.value || !canUploadDocumentsAction.value) return false;
  if (isConfirmedRequirement(requirement)) return false;
  return !isSubmittedDocument(requirement.document);
};

const isAllowedDocumentFile = (file: File) => {
  const extension = file.name.split('.').pop()?.toLowerCase() || '';
  return allowedDocumentExtensions.includes(extension);
};

const canPreviewDocument = (doc: ProjectDocument) => {
  const type = String(doc.file_type || '').toLowerCase();
  return previewableDocumentTypes.includes(type) || type.startsWith('image/');
};

const documentStatusLabel = (doc: ProjectDocument) => {
  const status = doc.submission_status || 'draft';
  const map: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    update_requested: 'Update Requested',
  };
  return map[status] || formatRequirementStatus(status);
};

const documentStatusClass = (doc: ProjectDocument) => `doc-${doc.submission_status || 'draft'}`;

const submitDocument = async (doc: ProjectDocument) => {
  const scrollPosition = captureTabScroll();
  const shouldStayOnRequirements = activeTab.value === 'requirements';
  setDocumentActing(doc.id, true);
  try {
    const response = await axiosInstance.post(`/api/documents/${doc.id}/submit`);
    patchSubmittedDocumentsPayload(response.data);
    toast.success(response.data?.message || 'File submitted for SOI review');
    if (response.data?.approval_started) {
      await Promise.all([loadProject(), loadTimeline()]);
    }
    if (shouldStayOnRequirements) activeTab.value = 'requirements';
    await restoreTabScroll(scrollPosition);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to submit file');
  } finally {
    setDocumentActing(doc.id, false);
  }
};

const submitDraftDocuments = async () => {
  if (!props.projectId) return;
  const scrollPosition = captureTabScroll();
  const shouldStayOnRequirements = activeTab.value === 'requirements';
  documentSubmitting.value = true;
  try {
    const response = await axiosInstance.post(`/api/projects/${props.projectId}/documents/submit-drafts`);
    patchSubmittedDocumentsPayload(response.data);
    toast.success(response.data?.message || 'Draft files submitted');
    if (response.data?.approval_started) {
      await Promise.all([loadProject(), loadTimeline()]);
    }
    if (shouldStayOnRequirements) activeTab.value = 'requirements';
    await restoreTabScroll(scrollPosition);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to submit draft files');
  } finally {
    documentSubmitting.value = false;
  }
};

const submitRequirementPackage = async () => {
  if (!canSubmitCurrentPackage.value) {
    activeTab.value = 'requirements';
    toast.warning(`Attach required file drafts before submitting: ${initialPackageMissing.value.join(', ')}`);
    return;
  }

  if (draftDocuments.value.length) {
    await submitDraftDocuments();
    return;
  }

  await submitProposal();
};

const submitProposal = async () => {
  if (!project.value?.id || proposalSubmitting.value) return;

  proposalSubmitting.value = true;
  try {
    const response = await projectStore.submitProposal(project.value.id);
    toast.success(response.message || 'Proposal submitted for SOI review');
    await Promise.all([loadProject(), loadTimeline()]);
    activeTab.value = 'approval';
  } catch (error: any) {
    toast.error(error?.response?.data?.message || projectStore.error || 'Failed to submit proposal');
  } finally {
    proposalSubmitting.value = false;
  }
};

const requestDocumentUpdate = async (doc: ProjectDocument) => {
  const reason = window.prompt('What needs to be corrected or provided for this file?');
  if (!reason?.trim()) return;

  setDocumentActing(doc.id, true);
  try {
    await axiosInstance.post(`/api/documents/${doc.id}/request-update`, { reason: reason.trim() });
    toast.success('Update request sent to the file owner');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to request file update');
  } finally {
    setDocumentActing(doc.id, false);
  }
};

const openRequirementUpload = (requirement: ProjectRequirement) => {
  activeRequirementId.value = requirement.id;
  documentForm.value = {
    title: requirement.item_name,
    category: requirement.group_name,
    description: requirement.source_document || '',
  };
  openDocumentPicker(true);
};

const requirementReviewRequiresRemarks = computed(() =>
  ['deferred', 'for_further_evaluation', 'approved_with_conditions', 'disapproved'].includes(requirementReviewForm.value.status)
);

const requirementReviewNeedsDueDate = computed(() =>
  ['requested', 'deferred', 'for_further_evaluation'].includes(requirementReviewForm.value.status)
);

const openRequirementReview = (requirement: ProjectRequirement) => {
  reviewingRequirementId.value = requirement.id;
  const defaultStatus = requirement.status === 'pending'
    ? 'requested'
    : requirement.status;
  requirementReviewForm.value = {
    status: defaultStatus,
    remarks: requirement.remarks || '',
    due_date: requirement.due_date ? String(requirement.due_date).slice(0, 10) : '',
  };
};

const closeRequirementReview = () => {
  reviewingRequirementId.value = null;
  requirementReviewForm.value = {
    status: 'received',
    remarks: '',
    due_date: '',
  };
};

const saveRequirementReview = async () => {
  if (!props.projectId) return;
  const requirementId = reviewingRequirementId.value;
  if (!requirementId) return;

  const remarks = requirementReviewForm.value.remarks.trim();
  if (requirementReviewRequiresRemarks.value && !remarks) {
    toast.error('Remarks are required for this decision.');
    return;
  }

  const payload: Record<string, any> = {
    status: requirementReviewForm.value.status,
    remarks,
    due_date: requirementReviewNeedsDueDate.value
      ? (requirementReviewForm.value.due_date || null)
      : null,
  };

  const scrollPosition = captureTabScroll();
  requirementReviewSaving.value = true;
  try {
    const response = await axiosInstance.patch(`/api/projects/${props.projectId}/requirements/${requirementId}`, payload);
    const updatedRequirement = response.data?.requirement;
    if (updatedRequirement && project.value?.requirements) {
      project.value = {
        ...project.value,
        requirements: project.value.requirements.map((req) =>
          req.id === updatedRequirement.id ? { ...req, ...updatedRequirement } : req
        ),
      };
    }
    toast.success('Requirement review saved');
    reviewingRequirementId.value = null;
    await restoreTabScroll(scrollPosition);
  } catch (error: any) {
    const validationMessage = error?.response?.data?.errors
      ? Object.values(error.response.data.errors).flat().join(' ')
      : null;
    toast.error(validationMessage || error?.response?.data?.message || 'Failed to save requirement review');
  } finally {
    requirementReviewSaving.value = false;
  }
};

const formatRequirementStatus = (status: string) =>
  status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');

const formatGateStep = (gate: string) => {
  const map: Record<string, string> = {
    mancom: 'ManCom Gate',
    board: 'Board Gate',
    fund_release: 'Fund Release Gate',
    jv: 'JV / NEDA Gate',
    monitoring: 'Monitoring Gate',
    divestment: 'Divestment Gate',
    spg_jv_mancom_project_decision: 'SPG JV ManCom Decision Gate',
    spg_jv_board_project_approval: 'SPG JV Board Approval Gate',
    spg_jv_neda_icc: 'SPG JV NEDA-ICC Gate',
    spg_jv_jva_terms_jvsc: 'SPG JV JVA Terms / JV-SC Gate',
    spg_jv_selection_award: 'SPG JV Selection Gate',
    spg_jv_final_award: 'SPG JV Final Award Gate',
    spg_jv_jva_signing: 'SPG JV Signing Gate',
    spg_ndc_own_mancom_project_decision: 'SPG NDC-Owned ManCom Decision Gate',
    spg_ndc_own_board_approval: 'SPG NDC-Owned Board Gate',
    spg_ndc_own_ded_construction: 'SPG DED / Construction Gate',
    spg_ndc_own_turnover: 'SPG Turn-over Gate',
  };
  return map[gate] || formatRequirementStatus(gate);
};

const formatProcessTrack = (track: string) => {
  const map: Record<string, string> = {
    bdg_investment: 'External Investment Proposal (BDG)',
    spg_traditional: 'Traditional Equity Funding (SPG)',
    spg_ndc_own: 'SPG NDC-Owned Project',
    spg_jv: 'Joint Venture Proposal (SPG)',
    implementation_monitoring: 'Approved Project for Monitoring',
    divestment: 'Post-Investment / Divestment',
  };
  return map[track] || formatRequirementStatus(track);
};

const viewDocument = async (doc: ProjectDocument) => {
  if (!canPreviewDocument(doc)) {
    toast.info('Preview is available for PDF and image files. Please download Word or Excel files.');
    return;
  }

  const previewWindow = window.open('', '_blank');

  try {
    const response = await axiosInstance.get(`/api/documents/${doc.id}/view`, {
      responseType: 'blob',
    });
    const contentType = response.headers?.['content-type'] || doc.file_type || 'application/octet-stream';
    const blob = new Blob([response.data], { type: contentType });
    const url = URL.createObjectURL(blob);

    if (previewWindow) {
      previewWindow.location.href = url;
      previewWindow.addEventListener('beforeunload', () => URL.revokeObjectURL(url), { once: true });
    } else {
      window.open(url, '_blank');
      window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    }
  } catch (error: any) {
    previewWindow?.close();
    toast.error(error?.response?.data?.message || 'Failed to open attachment preview');
  }
};

const downloadDocument = async (doc: ProjectDocument) => {
  try {
    const response = await axiosInstance.get(`/api/documents/${doc.id}/download`, {
      responseType: 'blob',
    });
    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = doc.file_name || doc.title;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to download attachment');
  }
};

const downloadTemplate = (filePath: string) => {
  const token = localStorage.getItem('token') || sessionStorage.getItem('token');
  const tokenQuery = token ? `&token=${token}` : '';
  window.open(`/api/lookup/templates/download?file=${encodeURIComponent(filePath)}${tokenQuery}`, '_blank');
};

const deleteDocument = async (documentId: number) => {
  const confirmed = window.confirm('Delete this attachment?');
  if (!confirmed) return;

  try {
    await axiosInstance.delete(`/api/documents/${documentId}`);
    toast.success('Attachment deleted');
    await loadProject();
  } catch (error: any) {
    toast.error(error?.response?.data?.message || 'Failed to delete attachment');
  }
};

const formatTaskStatus = (status: string) =>
  status.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
const formatTaskType = (type: string) =>
  type.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');


const fmtFileSize = (bytes: number) => {
  if (!bytes) return 'Unknown size';
  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let unitIndex = 0;
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024;
    unitIndex++;
  }
  return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
};

const initials = (n: string) => n.split(' ').map(x => x[0]).slice(0,2).join('').toUpperCase() || '?';
const fmtPeso = (a: number) => `₱${new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(a)}`;
const metricNumber = (value: number | string | null | undefined) => {
  if (value === null || value === undefined || value === '') return 'Not set';
  const num = Number(value);
  return Number.isFinite(num) ? new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(num) : String(value);
};
const metricMoney = (value: number | string | null | undefined) => {
  if (value === null || value === undefined || value === '') return 'Not set';
  const num = Number(value);
  return Number.isFinite(num) ? fmtPeso(num) : String(value);
};
const yesNo = (value: unknown) => value ? 'Yes' : 'No';
const fmtDate = (d: string) => new Date(d).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
const fmtCoord = (value: number | string | null | undefined) => {
  const num = Number(value);
  return Number.isFinite(num) ? num.toFixed(4) : '';
};
const hasCoordinates = (p: Project) =>
  Number.isFinite(Number(p.location_lat)) && Number.isFinite(Number(p.location_lng));
</script>

<style scoped>

.modal-overlay {
  --v-bg: #ffffff;
  --v-border: #e2e8f0;
  --v-sub: #f8fafc;
  --v-muted: #f1f5f9;
  --v-text: #0f172a;
  --v-text-2: #475569;
  --v-text-3: #94a3b8;
  --v-accent: #2563eb;
  --v-accent-bg: #eff6ff;
  --v-card: #fafafa;
  --v-avatar-bg: #e0e7ff;
  --v-avatar-c: #4338ca;
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(15,23,42,0.65);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: 1rem; overflow-y: auto;
}
:global(.dark) .modal-overlay,
.modal-overlay.is-dark {
  --v-bg: #0b1220;
  --v-border: #29384e;
  --v-sub: #101a2b;
  --v-muted: #172238;
  --v-text: #f8fafc;
  --v-text-2: #cbd5e1;
  --v-text-3: #94a3b8;
  --v-accent: #60a5fa;
  --v-accent-bg: #172b4a;
  --v-card: #111c2e;
  --v-avatar-bg: #1d4ed8;
  --v-avatar-c: #dbeafe;
  background: rgba(0,0,0,0.75);
}

.modal-panel {
  background: var(--v-bg);
  color: var(--v-text);
  border-radius: 1.125rem;
  box-shadow: 0 24px 64px rgba(0,0,0,0.2);
  width: 95vw; max-width: 1240px; height: 90vh; max-height: 90vh;
  display: flex; flex-direction: column; overflow: hidden;
}
:global(.dark) .modal-panel,
.modal-overlay.is-dark .modal-panel {
  border: 1px solid #314259;
  box-shadow: 0 24px 64px rgba(0,0,0,0.58);
}

.project-dashboard-layout {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  flex-direction: row;
}

.main-content-column {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.project-sidebar {
  width: 330px;
  min-width: 330px;
  background: var(--v-sub);
  border-left: 1px solid var(--v-border);
  overflow-y: auto;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* Sidebar Widgets */
.sidebar-widget {
  background: var(--v-bg);
  border: 1px solid var(--v-border);
  border-radius: 0.75rem;
  padding: 1rem;
  box-shadow: 0 2px 8px rgba(0,0,0,0.02);
}

:global(.dark) .sidebar-widget,
.modal-overlay.is-dark .sidebar-widget {
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.widget-title {
  font-size: 0.75rem;
  font-weight: 800;
  color: var(--v-text-3);
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin: 0 0 0.85rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border-bottom: 1px solid var(--v-border);
  padding-bottom: 0.5rem;
}

.widget-icon {
  width: 0.9rem;
  height: 0.9rem;
  color: var(--v-accent);
}

.sidebar-details {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
}

.sd-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  font-size: 0.8rem;
  line-height: 1.45;
}

.sdl {
  color: var(--v-text-2);
  font-weight: 500;
}

.sdv {
  color: var(--v-text);
  text-align: right;
  word-break: break-word;
  max-width: 65%;
}

.sdv.font-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.75rem;
}

.text-primary { color: var(--v-accent) !important; }
.text-warning { color: #f59e0b !important; }
.text-success { color: #10b981 !important; }
.text-info { color: #06b6d4 !important; }
.text-danger { color: #ef4444 !important; }
.text-accent { color: #8b5cf6 !important; }
.text-link {
  color: var(--v-accent) !important;
  text-decoration: none;
  font-weight: 600;
  transition: opacity 0.15s;
}
.text-link:hover {
  opacity: 0.8;
  text-decoration: underline;
}
.font-bold {
  font-weight: 700;
}

/* Loading */
.loading-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 5rem; gap: 1rem; color: var(--v-text-3); font-size: 0.9rem; }
.load-actions { display: flex; gap: 0.75rem; }
.retry-btn, .close-btn { border: 1px solid var(--v-border); border-radius: 0.5rem; padding: 0.55rem 1rem; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.retry-btn { background: var(--v-accent); border-color: var(--v-accent); color: white; }
.retry-btn:hover { filter: brightness(1.05); }
.close-btn { background: var(--v-muted); color: var(--v-text-2); }
.close-btn:hover { color: var(--v-text); }
.spinner-lg { width: 2.75rem; height: 2.75rem; border: 3px solid var(--v-muted); border-top-color: var(--v-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
.spinner-sm { display: inline-block; width: 1rem; height: 1rem; border: 2px solid var(--v-muted); border-top-color: var(--v-accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Hero */
.hero { padding: 1.375rem; position: relative; overflow: hidden; flex-shrink: 0; }
.hero::before { content: ''; position: absolute; inset: 0; background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E"); }
.hero-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; position: relative; z-index: 1; }
.hero-badges { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.h-code { font-size: 0.68rem; font-weight: 700; color: rgba(255,255,255,0.45); letter-spacing: 0.1em; text-transform: uppercase; }
.h-badge { font-size: 0.62rem; font-weight: 700; padding: 0.12rem 0.45rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; }
.h-badge.svf { background: rgba(245,158,11,0.25); color: #fcd34d; }
.h-badge.overdue { background: rgba(239,68,68,0.25); color: #fca5a5; }
.h-badge.archived { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.45); }
.hero-actions { display: flex; align-items: center; gap: 0.375rem; position: relative; z-index: 1; }
.h-btn, .h-close { width: 2.125rem; height: 2.125rem; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.08); border-radius: 0.5rem; cursor: pointer; color: rgba(255,255,255,0.7); transition: all 0.15s; }
.h-submit { min-height: 2.125rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; border: 1px solid rgba(255,255,255,0.35); background: #ffffff; border-radius: 0.5rem; padding: 0 0.75rem; color: #1d4ed8; font-size: 0.72rem; font-weight: 800; cursor: pointer; }
.h-submit:hover:not(:disabled) { background: #eff6ff; }
.h-submit:disabled { opacity: 0.65; cursor: not-allowed; }
.h-btn:hover { background: rgba(255,255,255,0.16); color: white; }
.h-close:hover { background: rgba(239,68,68,0.3); border-color: rgba(239,68,68,0.4); color: white; }
.icon { width: 1rem; height: 1rem; }
.hero-title { font-size: 1.5rem; font-weight: 800; color: white; margin: 0 0 0.75rem; line-height: 1.25; letter-spacing: -0.02em; position: relative; z-index: 1; }
.hero-meta { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.875rem; position: relative; z-index: 1; }
.h-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.28rem 0.7rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); border-radius: 999px; font-size: 0.78rem; color: rgba(255,255,255,0.75); font-weight: 500; }
.pi { width: 0.78rem; height: 0.78rem; }
.sdot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: currentColor; flex-shrink: 0; }
.hero-prog { position: relative; z-index: 1; }
.hp-track { height: 0.35rem; background: rgba(255,255,255,0.1); border-radius: 999px; overflow: hidden; }
.hp-fill { height: 100%; background: linear-gradient(90deg,#60a5fa,#34d399); border-radius: 999px; transition: width 0.5s ease; }
.hp-label { font-size: 0.72rem; color: rgba(255,255,255,0.45); margin-top: 0.3rem; display: block; text-align: right; }

/* Tabs */
.tab-scroll-shell { position: relative; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: stretch; border-bottom: 1px solid var(--v-border); background: var(--v-card); flex-shrink: 0; }
.tab-scroll-shell::before,
.tab-scroll-shell::after { content: ''; position: absolute; top: 0; bottom: 0; z-index: 1; width: 1.2rem; pointer-events: none; }
.tab-scroll-shell::before { left: 2.25rem; background: linear-gradient(90deg, var(--v-card), transparent); }
.tab-scroll-shell::after { right: 2.25rem; background: linear-gradient(270deg, var(--v-card), transparent); }
.tab-scroll-btn { position: relative; z-index: 2; width: 2.25rem; border: 0; border-inline: 1px solid var(--v-border); background: var(--v-sub); color: var(--v-text-2); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s; }
.tab-scroll-btn:hover { color: var(--v-accent); background: var(--v-accent-bg); }
.tab-nav { display: flex; padding: 0 0.65rem; overflow-x: auto; scrollbar-width: thin; scrollbar-color: var(--v-border) transparent; scroll-behavior: smooth; flex-shrink: 0; }
.tab-nav::-webkit-scrollbar { height: 0.35rem; }
.tab-nav::-webkit-scrollbar-thumb { background: var(--v-border); border-radius: 999px; }
.tab-nav::-webkit-scrollbar-track { background: transparent; }
.tab-btn { display: flex; align-items: center; gap: 0.4rem; padding: 0.75rem 0.875rem; background: none; border: none; border-bottom: 2.5px solid transparent; margin-bottom: -1px; font-size: 0.8rem; font-weight: 500; color: var(--v-text-3); cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.tab-btn:hover { color: var(--v-text-2); }
.tab-btn.active { color: var(--v-accent); border-bottom-color: var(--v-accent); }
.ti { width: 0.875rem; height: 0.875rem; }
.tc { background: var(--v-muted); color: var(--v-text-3); font-size: 0.68rem; font-weight: 700; padding: 0.08rem 0.38rem; border-radius: 999px; }
.tab-btn.active .tc { background: var(--v-accent-bg); color: var(--v-accent); }

/* Tab body */
.tab-body { flex: 1; overflow-y: auto; padding: 1.25rem; overscroll-behavior: contain; overflow-anchor: none; }
.tab-pane { animation: fadeUp 0.18s ease; }
.submission-callout { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: 0.85rem 0.95rem; border: 1px solid #bfdbfe; border-radius: 0.7rem; background: #eff6ff; }
.submission-callout strong { display: block; color: #1e3a8a; font-size: 0.82rem; }
.submission-callout span { display: block; color: #475569; font-size: 0.74rem; line-height: 1.45; margin-top: 0.15rem; }
.submission-callout small { display: block; margin-top: 0.35rem; color: #b45309; font-size: 0.72rem; font-weight: 800; line-height: 1.4; }
.submit-callout-btn { flex-shrink: 0; min-height: 2.25rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; border: 1px solid #2563eb; border-radius: 0.5rem; background: #2563eb; color: #fff; padding: 0 0.8rem; font-size: 0.74rem; font-weight: 800; cursor: pointer; }
.submit-callout-btn:hover:not(:disabled) { background: #1d4ed8; }
.submit-callout-btn:disabled { opacity: 0.6; cursor: not-allowed; }
:global(.dark) .submission-callout { border-color: #1d4ed8; background: #172554; }
.modal-overlay.is-dark .submission-callout { border-color: #1d4ed8; background: #101f3f; }
:global(.dark) .submission-callout strong { color: #bfdbfe; }
.modal-overlay.is-dark .submission-callout strong { color: #dbeafe; }
:global(.dark) .submission-callout span { color: #cbd5e1; }
.modal-overlay.is-dark .submission-callout span { color: #cbd5e1; }
:global(.dark) .submission-callout small { color: #fcd34d; }
.modal-overlay.is-dark .submission-callout small { color: #fcd34d; }
@keyframes fadeUp { from{opacity:0;transform:translateY(5px)} to{opacity:1;transform:translateY(0)} }

/* Info cards */
.info-card { background: var(--v-card); border: 1px solid var(--v-border); border-radius: 0.75rem; padding: 1rem; margin-bottom: 0.875rem; }
.ic-head { display: flex; align-items: center; gap: 0.45rem; margin-bottom: 0.7rem; font-size: 0.72rem; font-weight: 700; color: var(--v-text-3); text-transform: uppercase; letter-spacing: 0.06em; }
.ci { width: 0.825rem; height: 0.825rem; color: var(--v-accent); }
.desc { font-size: 0.875rem; color: var(--v-text-2); line-height: 1.65; margin: 0; }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem; margin-bottom: 0.875rem; }
.two-col .info-card { margin-bottom: 0; }
.balanced-profile-grid { align-items: start; }
.balanced-profile-grid .info-card { height: fit-content; }
.proponent-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 0.875rem;
  margin-bottom: 0.875rem;
}
.proponent-detail-grid .info-card { margin-bottom: 0; }
.location-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.55rem;
  margin-top: 0.85rem;
}
.location-field {
  display: grid;
  gap: 0.15rem;
  min-width: 0;
  padding: 0.6rem 0.7rem;
  border: 1px solid var(--v-border);
  border-radius: 0.6rem;
  background: var(--v-sub);
}
.location-field span {
  color: var(--v-text-3);
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.location-field strong {
  color: var(--v-text);
  font-size: 0.8rem;
  line-height: 1.35;
}
.coord-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.85rem;
}
.coord-chip {
  display: inline-flex;
  align-items: baseline;
  gap: 0.35rem;
  padding: 0.5rem 0.7rem;
  border-radius: 0.55rem;
  background: var(--v-accent-bg);
  color: var(--v-accent);
  font-size: 0.76rem;
  font-weight: 800;
}
.coord-chip strong {
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.coord-chip span {
  color: var(--v-text);
  font-family: monospace;
  font-size: 0.8rem;
  font-weight: 700;
}
.d-list { display: flex; flex-direction: column; gap: 0.45rem; }
.d-item { display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem; }
.dl { font-size: 0.73rem; color: var(--v-text-3); font-weight: 500; white-space: nowrap; flex-shrink: 0; }
.dv { font-size: 0.8rem; font-weight: 600; color: var(--v-text); text-align: right; }
.dv.link { color: var(--v-accent); text-decoration: none; }
.dv.link:hover { text-decoration: underline; }
.ov-text { color: #dc2626; }
:global(.dark) .ov-text { color: #f87171; }
.ok-text { color: #16a34a; }
:global(.dark) .ok-text { color: #4ade80; }
.fin-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.reporting-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.fin-item { display: flex; flex-direction: column; gap: 0.2rem; }
.fl { font-size: 0.73rem; color: var(--v-text-3); font-weight: 500; }
.fa { font-size: 1.0625rem; font-weight: 700; color: var(--v-text); }
.fa.sm { font-size: 0.875rem; color: var(--v-text-2); }
.fa.pos { color: #16a34a; } .fa.neg { color: #dc2626; }
:global(.dark) .fa.pos { color: #4ade80; }
:global(.dark) .fa.neg { color: #f87171; }
.metric-note { margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--v-border); }
.profile-link-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  width: fit-content;
  margin-top: 0.7rem;
  min-height: 2rem;
  padding: 0 0.65rem;
  border: 1px solid rgba(37,99,235,0.24);
  border-radius: 0.5rem;
  background: var(--v-accent-bg);
  color: var(--v-accent);
  font-size: 0.73rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.15s;
}
.profile-link-btn:hover {
  border-color: rgba(37,99,235,0.42);
  filter: brightness(0.98);
}
.declared-profile-box,
.proponent-history-box {
  display: grid;
  gap: 0.65rem;
  margin-top: 0.85rem;
  padding-top: 0.85rem;
  border-top: 1px solid var(--v-border);
}
.ph-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}
.ph-head div {
  min-width: 0;
  display: grid;
  gap: 0.12rem;
}
.ph-head strong {
  color: var(--v-text);
  font-size: 0.82rem;
}
.ph-head span,
.profile-field span,
.ph-meta span {
  color: var(--v-text-3);
  font-size: 0.7rem;
  font-weight: 700;
}
.profile-field {
  display: grid;
  gap: 0.18rem;
  padding: 0.55rem 0.65rem;
  border: 1px solid var(--v-border);
  border-radius: 0.55rem;
  background: var(--v-sub);
}
.profile-field p {
  margin: 0;
  color: var(--v-text-2);
  font-size: 0.78rem;
  line-height: 1.5;
  white-space: pre-line;
}
.ph-refresh {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 1.9rem;
  padding: 0 0.55rem;
  border: 1px solid rgba(37,99,235,0.24);
  border-radius: 0.45rem;
  background: var(--v-accent-bg);
  color: var(--v-accent);
  font-size: 0.7rem;
  font-weight: 800;
  cursor: pointer;
  white-space: nowrap;
}
.ph-refresh:disabled {
  cursor: not-allowed;
  opacity: 0.65;
}
.ph-empty {
  padding: 0.65rem;
  border: 1px dashed var(--v-border);
  border-radius: 0.55rem;
  color: var(--v-text-3);
  font-size: 0.76rem;
}
.ph-list {
  display: grid;
  gap: 0.45rem;
}
.ph-item {
  display: grid;
  gap: 0.35rem;
  padding: 0.6rem 0.65rem;
  border: 1px solid var(--v-border);
  border-radius: 0.55rem;
  background: var(--v-sub);
}
.ph-item div:first-child {
  display: grid;
  gap: 0.12rem;
  min-width: 0;
}
.ph-item strong {
  color: var(--v-accent);
  font-size: 0.72rem;
  letter-spacing: 0.04em;
}
.ph-item span {
  color: var(--v-text-2);
  font-size: 0.78rem;
  line-height: 1.35;
}
.ph-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.ph-meta span {
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background: var(--v-muted);
}
.coord-chip { display: inline-block; background: var(--v-muted); padding: 0.2rem 0.55rem; border-radius: 0.375rem; font-size: 0.73rem; font-family: monospace; color: var(--v-text-2); margin-top: 0.5rem; }
.criteria-chips { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.65rem; }
.criteria-chips span { padding: 0.22rem 0.55rem; border-radius: 999px; color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; font-size: 0.7rem; font-weight: 800; }
:global(.dark) .criteria-chips span { color: #86efac; background: #14532d; border-color: #166534; }

/* Team */
.pane-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
.pane-head h3 { font-size: 1rem; font-weight: 700; color: var(--v-text); margin: 0; }
.pane-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; flex-wrap: wrap; }
.add-btn { display: flex; align-items: center; gap: 0.35rem; padding: 0.45rem 0.875rem; background: var(--v-accent-bg); border: 1px solid rgba(37,99,235,0.25); border-radius: 0.5rem; font-size: 0.78rem; font-weight: 600; color: var(--v-accent); cursor: pointer; transition: all 0.15s; }
.ghost-action { display: inline-flex; align-items: center; justify-content: center; min-height: 2.05rem; padding: 0 0.85rem; border-radius: 0.5rem; border: 1px solid var(--v-border); background: var(--v-sub); color: var(--v-text-2); font-size: 0.78rem; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.ghost-action:hover:not(:disabled) { color: var(--v-accent); border-color: rgba(37,99,235,0.35); background: var(--v-accent-bg); }
.ghost-action:disabled { cursor: not-allowed; opacity: 0.65; }
.add-btn.submit-all { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.mini-count { display: inline-grid; place-items: center; min-width: 1.15rem; height: 1.15rem; padding: 0 0.25rem; border-radius: 999px; background: rgba(255,255,255,0.65); font-size: 0.65rem; font-weight: 900; }
.add-btn:hover { background: #dbeafe; }
:global(.dark) .add-btn:hover { background: #1e3a5f; }
:global(.dark) .add-btn.submit-all { background: #14532d; color: #86efac; border-color: #166534; }
.modal-overlay.is-dark .add-btn.submit-all { background: #172b4a; color: #bfdbfe; border-color: #2563eb; }
.post-monitoring-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.8rem; }
.monitoring-gate { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:1rem; margin-bottom:0.9rem; padding:1rem; border:1px solid var(--v-border); border-left:4px solid #94a3b8; border-radius:0.75rem; background:var(--v-card); }
.monitoring-gate.active { border-left-color:#16a34a; }
.monitoring-gate.completed { border-left-color:#2563eb; }
.monitoring-gate-copy { min-width:0; }
.gate-kicker { display:block; margin-bottom:0.25rem; color:var(--v-text-3); font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.06em; }
.monitoring-gate-copy > strong { display:block; color:var(--v-text); font-size:0.96rem; }
.monitoring-gate-copy > p { margin:0.3rem 0 0; color:var(--v-text-2); font-size:0.82rem; line-height:1.45; }
.gate-meta { display:flex; flex-wrap:wrap; gap:0.45rem; margin-top:0.55rem; }
.gate-meta span { padding:0.25rem 0.45rem; border-radius:0.35rem; background:var(--v-muted); color:var(--v-text-2); font-size:0.7rem; font-weight:700; }
.gate-actions { display:flex; align-items:flex-start; }
.monitoring-activation-form { grid-column:1/-1; display:grid; grid-template-columns:minmax(11rem,.45fr) minmax(0,1fr); gap:0.75rem; padding-top:0.9rem; border-top:1px solid var(--v-border); }
.monitoring-activation-form label > span:first-child { display:block; margin-bottom:0.35rem; color:var(--v-text-2); font-size:0.72rem; font-weight:800; }
.monitoring-activation-form .span-2 { grid-column:2; grid-row:1 / span 2; }
.compact-check { align-self:start; }
.activation-actions { grid-column:1/-1; display:flex; justify-content:flex-end; gap:0.55rem; }
.monitoring-request-banner { display:flex; justify-content:space-between; gap:1rem; margin-bottom:0.9rem; padding:0.9rem; border:1px solid rgba(37,99,235,0.28); border-radius:0.7rem; background:var(--v-accent-bg); }
.monitoring-request-banner strong { color:var(--v-text); }
.monitoring-request-banner p { margin:0.25rem 0 0; color:var(--v-text-2); font-size:0.8rem; line-height:1.45; }
.monitoring-request-banner > span { flex:none; color:var(--v-accent); font-size:0.75rem; font-weight:800; }
.monitoring-submission-state { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:0.8rem; padding:0.85rem 0.95rem; border:1px solid var(--v-border); border-left:4px solid #94a3b8; border-radius:0.7rem; background:var(--v-card); }
.monitoring-submission-state strong { display:block; color:var(--v-text); font-size:0.88rem; }
.monitoring-submission-state p { margin:0.2rem 0 0; color:var(--v-text-2); font-size:0.77rem; line-height:1.45; }
.monitoring-submission-state > span { flex:none; color:var(--v-text-3); font-size:0.72rem; font-weight:700; }
.monitoring-submission-state.draft { border-left-color:#f59e0b; }
.monitoring-submission-state.submitted { border-left-color:#2563eb; }
.monitoring-submission-state.returned { border-left-color:#dc2626; }
.monitoring-submission-state.accepted { border-left-color:#16a34a; }
.monitoring-review-note, .monitoring-review-panel { display:grid; gap:0.65rem; margin-bottom:0.8rem; padding:0.9rem; border:1px solid var(--v-border); border-radius:0.7rem; background:var(--v-card); }
.monitoring-review-note.returned { border-color:#fecaca; background:#fef2f2; }
.monitoring-review-note strong, .monitoring-review-panel strong { color:var(--v-text); font-size:0.85rem; }
.monitoring-review-note p, .monitoring-review-panel p { margin:0.2rem 0 0; color:var(--v-text-2); font-size:0.77rem; line-height:1.5; }
:global(.dark) .monitoring-review-note.returned { border-color:#7f1d1d; background:#450a0a; }
.danger-text { color:#dc2626; }
.monitor-field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; }
.monitor-field.span-2 { grid-column: span 2; }
.monitor-field label { color: var(--v-text-2); font-size: 0.75rem; font-weight: 800; }
.monitor-textarea { min-height: 5.2rem; resize: vertical; }
.monitor-check { min-height: 4.2rem; display: flex; align-items: flex-start; gap: 0.6rem; padding: 0.7rem; border: 1px solid var(--v-border); border-radius: 0.65rem; background: var(--v-sub); color: var(--v-text); cursor: pointer; }
.monitor-check input { margin-top: 0.18rem; accent-color: var(--v-accent); }
.monitor-check span { display: grid; gap: 0.15rem; }
.monitor-check strong { font-size: 0.82rem; line-height: 1.3; }
.monitor-check small { color: var(--v-text-3); font-size: 0.72rem; line-height: 1.35; }
.members-list { display: flex; flex-direction: column; gap: 0.5rem; }
.member-card { display: flex; align-items: center; gap: 0.875rem; padding: 0.75rem 1rem; background: var(--v-card); border: 1px solid var(--v-border); border-radius: 0.75rem; }
.m-avatar { width: 2.5rem; height: 2.5rem; border-radius: 50%; background: var(--v-avatar-bg); color: var(--v-avatar-c); font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.m-avatar img { width: 100%; height: 100%; object-fit: cover; }
.m-info { flex: 1; }
.m-name { font-size: 0.875rem; font-weight: 600; color: var(--v-text); margin: 0 0 0.1rem; }
.m-role { font-size: 0.78rem; color: var(--v-text-3); margin: 0; }
.m-perms { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.45rem; }
.m-perm { font-size: 0.64rem; padding: 0.15rem 0.4rem; border-radius: 999px; border: 1px solid var(--v-border); color: var(--v-text-3); background: transparent; }
.m-perm.on { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
:global(.dark) .m-perm.on { color: #86efac; background: #14532d; border-color: #166534; }
.m-actions { display: flex; align-items: center; gap: 0.4rem; }
.remove-btn { padding: 0.35rem 0.7rem; background: transparent; border: 1px solid var(--v-border); border-radius: 0.375rem; font-size: 0.73rem; font-weight: 500; color: var(--v-text-2); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
.remove-btn:hover { background: #fee2e2; border-color: #fecaca; color: #dc2626; }
:global(.dark) .remove-btn:hover { background: #450a0a; border-color: #7f1d1d; color: #f87171; }
.remove-btn.danger:hover { background: #fee2e2; border-color: #fecaca; color: #dc2626; }
:global(.dark) .remove-btn.danger:hover { background: #450a0a; border-color: #7f1d1d; color: #f87171; }
.empty-pane { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3.5rem 2rem; text-align: center; color: var(--v-text-3); }
.ep-icon { width: 2.75rem; height: 2.75rem; margin-bottom: 0.875rem; }
.empty-pane p { font-size: 0.875rem; margin: 0; }

/* Tasks and attachments */
.pane-sub { margin: 0.18rem 0 0; font-size: 0.78rem; color: var(--v-text-3); }
.task-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.7rem; margin-bottom: 0.875rem; }
.task-stat { min-width: 0; padding: 0.85rem; border: 1px solid var(--v-border); border-radius: 0.7rem; background: var(--v-card); display: flex; flex-direction: column; gap: 0.25rem; }
.task-stat span { font-size: 0.68rem; font-weight: 700; color: var(--v-text-3); text-transform: uppercase; letter-spacing: 0.05em; }
.task-stat strong { font-size: 1.25rem; line-height: 1; color: var(--v-text); }
.task-stat.warn strong { color: #dc2626; }
:global(.dark) .task-stat.warn strong { color: #f87171; }
.execution-row { display: grid; grid-template-columns: 1fr auto; gap: 0.85rem; align-items: center; }
.execution-row.compact { margin-bottom: 0.7rem; gap: 0.6rem; }
.execution-row.compact .execution-track { height: 0.45rem; }
.execution-track, .mini-track { overflow: hidden; background: var(--v-muted); border-radius: 999px; }
.execution-track { height: 0.7rem; }
.execution-fill, .mini-fill { height: 100%; background: linear-gradient(90deg,#2563eb,#14b8a6); border-radius: inherit; transition: width 0.3s ease; }
.execution-row strong { font-size: 0.9rem; color: var(--v-text); min-width: 3.25rem; text-align: right; }
.workplan-guide { display: grid; gap: 0.25rem; margin-bottom: 0.875rem; padding: 0.85rem 0.95rem; border: 1px solid rgba(37,99,235,0.24); border-radius: 0.75rem; background: var(--v-accent-bg); color: var(--v-text-2); }
.workplan-guide strong { color: var(--v-text); font-size: 0.86rem; }
.workplan-guide span { font-size: 0.78rem; line-height: 1.5; }
.workplan-guide.muted { border-color: var(--v-border); background: var(--v-card); }
.requirements-guide {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.7rem;
  margin-bottom: 0.8rem;
}
.requirements-guide div {
  min-width: 0;
  display: grid;
  gap: 0.24rem;
  padding: 0.82rem 0.9rem;
  border: 1px solid var(--v-border);
  border-radius: 0.75rem;
  background: var(--v-card);
}
.requirements-guide strong { color: var(--v-text); font-size: 0.8rem; }
.requirements-guide span { color: var(--v-text-2); font-size: 0.74rem; line-height: 1.45; }
.requirement-command-center {
  display: grid;
  gap: 0.75rem;
  margin-bottom: 1rem;
  padding: 0.85rem;
  border: 1px solid var(--v-border);
  border-radius: 0.85rem;
  background: var(--v-sub);
}
.requirement-summary {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.55rem;
}
.requirement-summary span {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 2rem;
  padding: 0 0.7rem;
  border: 1px solid var(--v-border);
  border-radius: 999px;
  background: var(--v-card);
  color: var(--v-text-2);
  font-size: 0.75rem;
  font-weight: 800;
}
.requirement-summary strong { color: var(--v-text); font-size: 0.9rem; }
.requirement-toolbar {
  display: grid;
  grid-template-columns: minmax(14rem, 1fr) minmax(9rem, auto) minmax(11rem, auto) auto;
  gap: 0.55rem;
  align-items: center;
}
.requirement-search,
.requirement-select {
  min-width: 0;
  min-height: 2.35rem;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid var(--v-border);
  border-radius: 0.65rem;
  background: var(--v-card);
  color: var(--v-text-2);
  padding: 0 0.65rem;
}
.requirement-search input,
.requirement-select select {
  min-width: 0;
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--v-text);
  font-size: 0.78rem;
  font-weight: 700;
}
.requirement-select select { cursor: pointer; }
.toolbar-icon {
  width: 0.9rem;
  height: 0.9rem;
  flex-shrink: 0;
  color: var(--v-accent);
}
.requirement-queue {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin: 0;
  padding: 0.35rem;
  border: 1px solid var(--v-border);
  border-radius: 0.75rem;
  background: var(--v-card);
}
.queue-filter-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 2rem;
  border: 1px solid transparent;
  border-radius: 0.55rem;
  background: transparent;
  color: var(--v-text-2);
  cursor: pointer;
  font-size: 0.74rem;
  font-weight: 800;
  padding: 0 0.6rem;
  transition: all 0.15s;
}
.queue-filter-btn span {
  min-width: 1.25rem;
  border-radius: 999px;
  background: var(--v-sub);
  color: var(--v-text-3);
  font-size: 0.68rem;
  line-height: 1.25rem;
  text-align: center;
}
.queue-filter-btn:hover,
.queue-filter-btn.active {
  border-color: rgba(37,99,235,0.25);
  background: var(--v-accent-bg);
  color: var(--v-accent);
}
.queue-filter-btn.active span {
  background: var(--v-accent);
  color: #fff;
}
.submission-readiness {
  display: grid;
  gap: 0.25rem;
  margin-bottom: 0.9rem;
  padding: 0.85rem 0.95rem;
  border: 1px solid #fde68a;
  border-radius: 0.75rem;
  background: #fffbeb;
  color: #92400e;
}
.submission-readiness strong { font-size: 0.83rem; }
.submission-readiness span { font-size: 0.76rem; line-height: 1.45; }
.submission-readiness.ready {
  border-color: #bbf7d0;
  background: #f0fdf4;
  color: #166534;
}
:global(.dark) .submission-readiness {
  border-color: #78350f;
  background: #451a03;
  color: #fcd34d;
}
:global(.dark) .submission-readiness.ready {
  border-color: #166534;
  background: #052e16;
  color: #86efac;
}
.task-list, .document-list, .requirement-list, .requirement-groups, .requirement-sections { display: flex; flex-direction: column; gap: 0.65rem; }
.workplan-section-card { display: grid; gap: 0.65rem; padding: 0.85rem; border: 1px solid var(--v-border); border-radius: 0.85rem; background: var(--v-sub); }
.workplan-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.workplan-section-head p { margin: 0 0 0.18rem; color: var(--v-text-3); font-size: 0.68rem; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; }
.workplan-section-head h4 { margin: 0; color: var(--v-text); font-size: 0.95rem; }
.workplan-section-count { flex-shrink: 0; display: grid; justify-items: end; color: var(--v-text-3); font-size: 0.68rem; font-weight: 800; text-transform: uppercase; }
.workplan-section-count strong { color: var(--v-text); font-size: 0.95rem; line-height: 1.1; }
.task-section-list { display: grid; gap: 0.55rem; }
.fund-release-form { display: grid; gap: 1rem; margin: 1rem 0; padding: 1rem; border: 1px solid var(--v-border); border-radius: 0.85rem; background: var(--v-card); }
.form-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.form-section-head strong { color: var(--v-text); font-size: 0.9rem; }
.form-section-head span { max-width: 34rem; color: var(--v-text-3); font-size: 0.74rem; line-height: 1.45; }
.fund-release-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.85rem; }
.fund-release-grid label { display: grid; gap: 0.35rem; }
.fund-release-grid label > span { color: var(--v-text-2); font-size: 0.72rem; font-weight: 800; }
.fund-release-grid .span-2 { grid-column: 1 / -1; }
.fund-release-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.85rem; padding: 0.95rem; border: 1px solid var(--v-border); border-radius: 0.8rem; background: var(--v-card); }
.task-status.for_review, .task-status.approved, .task-status.released { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
:global(.dark) .task-status.for_review, :global(.dark) .task-status.approved, :global(.dark) .task-status.released { color: #86efac; background: #14532d; border-color: #166534; }
.requirement-section { display: grid; gap: 0.75rem; padding: 0.85rem; border: 1px solid var(--v-border); border-radius: 0.85rem; background: var(--v-sub); }
.requirement-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.requirement-section-head h4 { margin: 0; color: var(--v-text); font-size: 0.94rem; }
.requirement-section-head p { margin: 0.16rem 0 0; color: var(--v-text-3); font-size: 0.75rem; line-height: 1.45; }
.requirement-section-head > span { flex-shrink: 0; color: var(--v-text-3); font-size: 0.72rem; font-weight: 800; }
.task-card, .document-card, .requirement-card { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 0.9rem; align-items: stretch; padding: 0.9rem; border: 1px solid var(--v-border); border-radius: 0.75rem; background: var(--v-card); }
.requirement-card.internal { border-style: dashed; background: color-mix(in srgb, var(--v-card) 86%, var(--v-accent-bg)); }
.requirement-card.is-missing {
  border-color: rgba(239, 68, 68, 0.4);
  background: rgba(254, 242, 242, 0.2);
}
:global(.dark) .requirement-card.is-missing {
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(69, 10, 10, 0.1);
}
.req-kind.missing-flag {
  background: #fee2e2;
  color: #b91c1c;
  font-weight: 700;
  border: 1px solid #fca3a3;
  animation: pulse-border-dialog 2s infinite;
}
:global(.dark) .req-kind.missing-flag {
  background: #450a0a;
  color: #fca5a5;
  border-color: #7f1d1d;
}
@keyframes pulse-border-dialog {
  0% { border-color: rgba(252, 163, 163, 0.4); }
  50% { border-color: rgba(239, 68, 68, 0.6); }
  100% { border-color: rgba(252, 163, 163, 0.4); }
}
.requirement-card.spotlight {
  border-color: #60a5fa;
  box-shadow: 0 0 0 3px rgba(37,99,235,0.18);
}
.modal-overlay.is-dark .requirement-card.spotlight {
  border-color: #93c5fd;
  box-shadow: 0 0 0 3px rgba(96,165,250,0.24);
}
.requirement-upload { grid-column:1/-1; margin:0; }
.requirement-review-panel {
  grid-column: 1 / -1;
  display: grid;
  gap: 0.75rem;
  padding: 0.9rem;
  border: 1px solid rgba(37,99,235,0.26);
  border-radius: 0.8rem;
  background: linear-gradient(180deg, rgba(37,99,235,0.08), rgba(20,184,166,0.05));
}
.review-panel-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}
.review-panel-head div {
  display: grid;
  gap: 0.16rem;
}
.review-panel-head strong {
  color: var(--v-text);
  font-size: 0.86rem;
}
.review-panel-head span {
  color: var(--v-text-3);
  font-size: 0.74rem;
  line-height: 1.45;
}
.review-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 12rem;
  gap: 0.65rem;
}
.review-grid label {
  display: grid;
  gap: 0.3rem;
  min-width: 0;
  color: var(--v-text-2);
  font-size: 0.74rem;
  font-weight: 800;
}
.review-grid .review-notes {
  grid-column: 1 / -1;
}
:global(.dark) .requirement-review-panel {
  border-color: rgba(96,165,250,0.26);
  background: linear-gradient(180deg, rgba(37,99,235,0.16), rgba(20,184,166,0.08));
}
.modal-overlay.is-dark .requirement-review-panel {
  border-color: rgba(96,165,250,0.32);
  background: #101a2b;
}
.requirement-group { display: grid; gap: 0.65rem; }
.requirement-group-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.45rem 0.1rem 0; }
.requirement-group-head h4 { margin: 0; color: var(--v-text); font-size: 0.9rem; }
.requirement-group-head span { color: var(--v-text-3); font-size: 0.74rem; font-weight: 700; }
.requirement-main { min-width: 0; }
.requirement-main strong { color: var(--v-text); font-size: 0.88rem; }
.requirement-main p { margin: 0.2rem 0 0.45rem; color: var(--v-text-2); font-size: 0.78rem; line-height: 1.45; }
.requirement-remarks { font-style: italic; }
.requirement-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.45rem; flex-wrap: wrap; }
.requirement-status { border-radius: 999px; padding: 0.14rem 0.48rem; border: 1px solid var(--v-border); font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.document-status { border-radius: 999px; padding: 0.14rem 0.48rem; border: 1px solid var(--v-border); font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
.req-kind { border-radius: 999px; padding: 0.14rem 0.48rem; border: 1px solid var(--v-border); font-weight: 800; }
.req-kind.required { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
.req-kind.optional { color: #475569; background: #f1f5f9; border-color: #e2e8f0; }
.req-kind.internal { color: #6d28d9; background: #ede9fe; border-color: #ddd6fe; }
.req-kind.gate { color: #0f766e; background: #ccfbf1; border-color: #99f6e4; }
.requirement-status.received, .requirement-status.approved { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
.requirement-status.pending, .requirement-status.requested { color: #92400e; background: #fef3c7; border-color: #fde68a; }
.requirement-status.deferred, .requirement-status.for_further_evaluation { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
.requirement-status.disapproved { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
.document-status.doc-draft { color: #92400e; background: #fef3c7; border-color: #fde68a; }
.document-status.doc-submitted { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
.document-status.doc-update_requested { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
.req-status-select { min-height: 2rem; border: 1px solid var(--v-border); border-radius: 0.45rem; background: var(--v-sub); color: var(--v-text); font-size: 0.72rem; padding: 0 0.5rem; }
:global(.dark) .requirement-status.received, :global(.dark) .requirement-status.approved { color: #86efac; background: #14532d; border-color: #166534; }
:global(.dark) .requirement-status.pending, :global(.dark) .requirement-status.requested { color: #fcd34d; background: #451a03; border-color: #78350f; }
:global(.dark) .requirement-status.deferred, :global(.dark) .requirement-status.for_further_evaluation { color: #93c5fd; background: #172554; border-color: #1d4ed8; }
:global(.dark) .requirement-status.disapproved { color: #fca5a5; background: #450a0a; border-color: #7f1d1d; }
:global(.dark) .req-kind.required { color: #93c5fd; background: #172554; border-color: #1d4ed8; }
:global(.dark) .req-kind.optional { color: #cbd5e1; background: #1e293b; border-color: #334155; }
:global(.dark) .req-kind.internal { color: #c4b5fd; background: #2e1065; border-color: #6d28d9; }
:global(.dark) .req-kind.gate { color: #99f6e4; background: #134e4a; border-color: #0f766e; }
:global(.dark) .document-status.doc-draft { color: #fcd34d; background: #451a03; border-color: #78350f; }
:global(.dark) .document-status.doc-submitted { color: #86efac; background: #14532d; border-color: #166534; }
:global(.dark) .document-status.doc-update_requested { color: #93c5fd; background: #172554; border-color: #1d4ed8; }
.task-main { min-width: 0; }
.task-title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.65rem; margin-bottom: 0.35rem; }
.task-title-row strong, .doc-main strong { color: var(--v-text); font-size: 0.9rem; line-height: 1.35; }
.task-main p, .doc-main p { color: var(--v-text-2); font-size: 0.8rem; line-height: 1.55; margin: 0.1rem 0 0.55rem; }
.task-status { flex-shrink: 0; border-radius: 999px; padding: 0.18rem 0.5rem; font-size: 0.64rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; border: 1px solid var(--v-border); color: var(--v-text-3); background: var(--v-muted); }
.task-status.pending { color: #92400e; background: #fef3c7; border-color: #fde68a; }
.task-status.in_progress { color: #1d4ed8; background: #dbeafe; border-color: #bfdbfe; }
.task-status.completed { color: #166534; background: #dcfce7; border-color: #bbf7d0; }
.task-status.cancelled { color: #991b1b; background: #fee2e2; border-color: #fecaca; }
:global(.dark) .task-status.pending { color: #fcd34d; background: #451a03; border-color: #78350f; }
:global(.dark) .task-status.in_progress { color: #93c5fd; background: #172554; border-color: #1d4ed8; }
:global(.dark) .task-status.completed { color: #86efac; background: #14532d; border-color: #166534; }
:global(.dark) .task-status.cancelled { color: #fca5a5; background: #450a0a; border-color: #7f1d1d; }
.modal-overlay.is-dark .task-status.pending { color: #cbd5e1; background: #253244; border-color: #3a4b63; }
.modal-overlay.is-dark .task-status.in_progress { color: #bfdbfe; background: #172b4a; border-color: #2563eb; }
.modal-overlay.is-dark .task-status.completed { color: #bbf7d0; background: #12351f; border-color: #255f3b; }
.modal-overlay.is-dark .task-status.cancelled { color: #fecaca; background: #4a1515; border-color: #7f1d1d; }
.task-meta, .doc-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 0.45rem 0.8rem; color: var(--v-text-3); font-size: 0.74rem; }
.type-chip { padding: 0.12rem 0.42rem; border-radius: 999px; background: var(--v-accent-bg); color: var(--v-accent); border: 1px solid rgba(37,99,235,0.22); font-weight: 800; }
.task-meta .danger { color: #dc2626; font-weight: 700; }
:global(.dark) .task-meta .danger { color: #f87171; }
.subtask-mini-list { display: grid; gap: 0.35rem; margin-top: 0.75rem; padding-top: 0.65rem; border-top: 1px solid var(--v-border); }
.subtask-mini { display: flex; align-items: center; justify-content: space-between; gap: 0.65rem; padding: 0.42rem 0.55rem; border-radius: 0.5rem; background: var(--v-sub); color: var(--v-text-2); font-size: 0.76rem; }
.subtask-copy { min-width: 0; display: grid; gap: 0.1rem; }
.subtask-copy span { overflow-wrap: anywhere; }
.subtask-mini small { color: var(--v-text-3); font-weight: 700; white-space: nowrap; }
.subtask-actions, .task-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem; flex-wrap: wrap; }
.task-actions { margin-top: 0.25rem; }
.task-action-btn { border: 1px solid var(--v-border); border-radius: 999px; background: var(--v-sub); color: var(--v-text-2); cursor: pointer; font-size: 0.66rem; font-weight: 800; line-height: 1; padding: 0.36rem 0.55rem; white-space: nowrap; transition: all 0.15s; }
.task-action-btn:hover:not(:disabled) { border-color: rgba(37,99,235,0.35); background: var(--v-accent-bg); color: var(--v-accent); }
.task-action-btn.start { color: #1d4ed8; border-color: #bfdbfe; background: #dbeafe; }
.task-action-btn.done { color: #166534; border-color: #bbf7d0; background: #dcfce7; }
.task-action-btn:disabled { opacity: 0.55; cursor: not-allowed; }
:global(.dark) .task-action-btn.start { color: #93c5fd; border-color: #1d4ed8; background: #172554; }
:global(.dark) .task-action-btn.done { color: #86efac; border-color: #166534; background: #14532d; }
.modal-overlay.is-dark .task-action-btn { background: #101a2b; color: #cbd5e1; border-color: #29384e; }
.modal-overlay.is-dark .task-action-btn.start { color: #bfdbfe; border-color: #2563eb; background: #172b4a; }
.modal-overlay.is-dark .task-action-btn.done { color: #bbf7d0; border-color: #255f3b; background: #12351f; }
.task-progress { width: 7.5rem; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 0.4rem; color: var(--v-text); font-weight: 800; font-size: 0.82rem; }
.mini-track { width: 100%; height: 0.45rem; }
.hidden-file { display: none; }
.media-section { display: grid; gap: 0.75rem; margin-bottom: 1rem; padding: 0.85rem; border: 1px solid var(--v-border); border-radius: 0.85rem; background: var(--v-sub); }
.section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.section-head h4 { margin: 0; color: var(--v-text); font-size: 0.92rem; font-weight: 800; }
.section-head p { margin: 0.18rem 0 0; color: var(--v-text-3); font-size: 0.75rem; line-height: 1.45; }
.photo-upload { margin-bottom: 0; }
.image-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: 0.75rem; }
.image-card { min-width: 0; overflow: hidden; border: 1px solid var(--v-border); border-radius: 0.75rem; background: var(--v-card); }
.image-card img { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; background: var(--v-muted); }
.image-card-meta { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.65rem; padding: 0.65rem; }
.image-card-meta div { min-width: 0; display: grid; gap: 0.15rem; }
.image-card-meta strong { overflow: hidden; color: var(--v-text); font-size: 0.78rem; line-height: 1.25; text-overflow: ellipsis; white-space: nowrap; }
.image-card-meta span { color: var(--v-text-3); font-size: 0.7rem; font-weight: 700; }
.thumb-badge { flex-shrink: 0; align-self: flex-start; border: 1px solid #bbf7d0; border-radius: 999px; background: #dcfce7; color: #166534 !important; padding: 0.14rem 0.44rem; font-size: 0.62rem !important; text-transform: uppercase; letter-spacing: 0.04em; }
:global(.dark) .thumb-badge { border-color: #166534; background: #14532d; color: #86efac !important; }
.image-card-actions { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; padding: 0 0.65rem 0.65rem; }
.empty-pane.compact { min-height: 9rem; padding: 1.5rem; border: 1px dashed var(--v-border); border-radius: 0.75rem; background: var(--v-card); }
.upload-card { display: grid; gap: 0.65rem; padding: 0.9rem; margin-bottom: 0.9rem; border: 1px solid rgba(37,99,235,0.28); border-radius: 0.75rem; background: var(--v-accent-bg); }
.upload-copy { display: flex; justify-content: space-between; gap: 0.8rem; color: var(--v-text); font-size: 0.82rem; }
.upload-copy span { color: var(--v-text-3); font-weight: 600; white-space: nowrap; }
.upload-textarea { min-height: 4.75rem; resize: vertical; }
.upload-note, .document-revision-note { margin: 0; color: var(--v-text-3); font-size: 0.74rem; line-height: 1.45; }
.document-revision-note { margin-top: 0.45rem; color: #1d4ed8; font-weight: 600; }
:global(.dark) .document-revision-note { color: #93c5fd; }
.upload-actions { display: flex; justify-content: flex-end; align-items: center; gap: 0.5rem; }
.add-btn:disabled { cursor: not-allowed; opacity: 0.65; }
.document-card { grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; }
.doc-icon { width: 2.35rem; height: 2.35rem; border-radius: 0.65rem; display: flex; align-items: center; justify-content: center; color: var(--v-accent); background: var(--v-accent-bg); border: 1px solid rgba(37,99,235,0.18); }
.doc-main { min-width: 0; }
.doc-actions { display: flex; align-items: center; gap: 0.35rem; }
.doc-action-btn { min-height: 2.05rem; border: 1px solid rgba(37,99,235,0.28); border-radius: 0.5rem; background: var(--v-accent-bg); color: var(--v-accent); padding: 0 0.65rem; font-size: 0.72rem; font-weight: 800; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.doc-action-btn.template-btn { border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.08); color: #059669; }
:global(.dark) .doc-action-btn.template-btn { border-color: rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.15); color: #34d399; }
.doc-action-btn.warn { border-color: #fde68a; background: #fef3c7; color: #92400e; }
.doc-action-btn:hover:not(:disabled) { filter: brightness(0.98); transform: translateY(-1px); }
.doc-action-btn:disabled { cursor: not-allowed; opacity: 0.6; transform: none; }
:global(.dark) .doc-action-btn.warn { border-color: #78350f; background: #451a03; color: #fcd34d; }
.icon-action { width: 2.05rem; height: 2.05rem; border: 1px solid var(--v-border); border-radius: 0.5rem; background: var(--v-sub); color: var(--v-text-2); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.15s; }
.icon-action:hover { color: var(--v-accent); border-color: rgba(37,99,235,0.35); background: var(--v-accent-bg); }
.icon-action.danger:hover { color: #dc2626; border-color: #fecaca; background: #fee2e2; }
:global(.dark) .icon-action.danger:hover { color: #f87171; border-color: #7f1d1d; background: #450a0a; }

/* Member modal */
.member-overlay { z-index: 10010; }
.member-modal { width: 100%; max-width: 560px; border-radius: 0.9rem; background: var(--v-bg); border: 1px solid var(--v-border); box-shadow: 0 18px 42px rgba(0,0,0,0.26); }
.member-head { display: flex; align-items: center; justify-content: space-between; padding: 0.9rem 1rem; border-bottom: 1px solid var(--v-border); }
.member-head h3 { margin: 0; font-size: 1rem; color: var(--v-text); }
.member-body { padding: 1rem; display: flex; flex-direction: column; gap: 0.55rem; }
.member-label { font-size: 0.78rem; font-weight: 600; color: var(--v-text-2); margin-top: 0.15rem; }
.member-input { width: 100%; border-radius: 0.5rem; border: 1px solid var(--v-border); background: var(--v-sub); color: var(--v-text); font-size: 0.82rem; padding: 0.52rem 0.65rem; }
.member-perm-grid { display: grid; gap: 0.45rem; margin-top: 0.45rem; }
.member-check { display: flex; align-items: center; gap: 0.5rem; font-size: 0.78rem; color: var(--v-text-2); }
.member-foot { display: flex; justify-content: flex-end; gap: 0.5rem; padding: 0.85rem 1rem; border-top: 1px solid var(--v-border); }

/* Timeline */
.tl-loading { display: flex; align-items: center; gap: 0.5rem; padding: 2rem; color: var(--v-text-3); font-size: 0.875rem; }
.tl-section { margin-bottom: 1.75rem; }
.tl-title { font-size: 0.72rem; font-weight: 700; color: var(--v-text-3); text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 0.875rem; }
.tl-items { display: flex; flex-direction: column; gap: 0.75rem; }
.tl-item { display: flex; gap: 0.875rem; }
.tl-dot { width: 1.875rem; height: 1.875rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 0.1rem; }
.s-dot { background: var(--v-accent-bg); color: var(--v-accent); }
.st-dot { background: #f0fdf4; color: #16a34a; }
:global(.dark) .st-dot { background: #14532d; color: #4ade80; }
.ti- { width: 0.8rem; height: 0.8rem; }
.tl-content { flex: 1; }
.tl-text { font-size: 0.8625rem; color: var(--v-text-2); margin: 0 0 0.2rem; }
.from { color: var(--v-text-3); }
.tl-reason { font-size: 0.8rem; color: var(--v-text-3); margin: 0 0 0.2rem; font-style: italic; }
.tl-meta { font-size: 0.73rem; color: var(--v-text-3); margin: 0; }

/* Modal transition */
.modal-enter-active { animation: ovIn 0.22s ease; }
.modal-leave-active { animation: ovIn 0.18s ease reverse; }
@keyframes ovIn { from{opacity:0} to{opacity:1} }
.modal-enter-active .modal-panel { animation: panIn 0.28s cubic-bezier(0.34,1.4,0.64,1); }
.modal-leave-active .modal-panel { animation: panIn 0.18s ease reverse; }
@keyframes panIn { from{transform:scale(0.93) translateY(18px)} to{transform:scale(1) translateY(0)} }

@media(max-width:640px) {
  .two-col { grid-template-columns: 1fr; }
  .location-grid { grid-template-columns: 1fr; }
  .fin-grid, .reporting-grid { grid-template-columns: 1fr 1fr; }
  .hero-title { font-size: 1.25rem; }
  .task-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .post-monitoring-form { grid-template-columns: 1fr; }
  .monitoring-gate, .monitoring-activation-form { grid-template-columns:1fr; }
  .monitoring-activation-form .span-2 { grid-column:auto; grid-row:auto; }
  .monitoring-request-banner { flex-direction:column; }
  .monitoring-submission-state { flex-direction:column; }
  .monitor-field.span-2 { grid-column: auto; }
  .requirements-guide { grid-template-columns: 1fr; }
  .requirement-toolbar { grid-template-columns: 1fr; }
  .requirement-search, .requirement-select { width: 100%; }
  .review-grid { grid-template-columns: 1fr; }
  .task-card { grid-template-columns: 1fr; }
  .requirement-card { grid-template-columns: 1fr; }
  .requirement-actions { justify-content: flex-start; }
  .pane-head { align-items: flex-start; flex-direction: column; }
  .pane-actions { justify-content: flex-start; }
  .task-progress { width: 100%; align-items: stretch; }
  .task-actions, .subtask-actions { justify-content: flex-start; }
  .subtask-mini { align-items: flex-start; flex-direction: column; }
  .document-card { grid-template-columns: auto minmax(0, 1fr); align-items: start; }
  .doc-actions { grid-column: 1 / -1; justify-content: flex-end; }
  .upload-copy { flex-direction: column; gap: 0.2rem; }
}

.gate-error-note {
  margin-top: 0.5rem;
  color: #b91c1c;
  font-size: 0.76rem;
  font-weight: 600;
}
:global(.dark) .gate-error-note {
  color: #fca5a5;
}



/* Dossier Report Styles */
.project-dossier-sheet {
  background: var(--v-sub);
  border: 1px solid var(--v-border);
  border-radius: 1rem;
  padding: 1.5rem;
  margin-top: 1rem;
}
.dossier-header-block {
  text-align: center;
  border-bottom: 2px solid var(--v-border);
  padding-bottom: 1rem;
  margin-bottom: 1.5rem;
}
.dossier-badge-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}
.dossier-confidential {
  background: #fef2f2;
  color: #b91c1c;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  letter-spacing: 0.05em;
}
:global(.dark) .dossier-confidential {
  background: rgba(127, 29, 29, 0.2);
  color: #fca5a5;
}
.dossier-date {
  font-size: 0.72rem;
  color: var(--v-text-3);
}
.dossier-title {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: 0.05em;
  margin: 0.25rem 0;
  color: var(--v-text);
}
.dossier-subtitle {
  font-size: 0.8rem;
  color: var(--v-text-3);
  margin: 0;
}
.dossier-grid-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
  background: var(--v-bg);
  border: 1px solid var(--v-border);
  border-radius: 0.75rem;
  padding: 1rem;
  margin-bottom: 1.5rem;
}
@media (max-width: 768px) {
  .dossier-grid-row {
    grid-template-columns: 1fr 1fr;
  }
}
.dossier-grid-item {
  display: flex;
  flex-direction: column;
}
.dossier-grid-item span {
  font-size: 0.65rem;
  color: var(--v-text-3);
  font-weight: 800;
}
.dossier-grid-item strong {
  font-size: 0.85rem;
  color: var(--v-text);
  margin-top: 0.25rem;
}
.dossier-section {
  margin-bottom: 1.5rem;
}
.dossier-section:last-child {
  margin-bottom: 0;
}
.dossier-section-title {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--v-text);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  border-bottom: 1px solid var(--v-border);
  padding-bottom: 0.35rem;
}
.dossier-section-title svg {
  width: 1rem;
  height: 1rem;
  color: var(--v-accent);
}
.dossier-content-card {
  background: var(--v-bg);
  border: 1px solid var(--v-border);
  border-radius: 0.75rem;
  padding: 1rem;
}
.dossier-text {
  font-size: 0.825rem;
  line-height: 1.5;
  color: var(--v-text-2);
  margin: 0;
}
.dossier-sub-field strong {
  display: block;
  font-size: 0.8rem;
  color: var(--v-text);
  margin-bottom: 0.25rem;
}

/* ─── SOI Tracker Timeline ─── */
.tracker-desc {
  font-size: 0.78rem;
  color: var(--v-text-2);
  margin: 0 0 1rem;
  line-height: 1.45;
}
.soi-tracker-timeline {
  display: flex;
  flex-direction: column;
}
.tracker-node {
  display: flex;
  gap: 0.875rem;
  align-items: flex-start;
}
.tracker-dot-col {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}
.tracker-dot {
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--v-card);
  border: 2px solid var(--v-border);
  transition: all 0.2s;
  position: relative;
  z-index: 1;
}
.tracker-node.completed .tracker-dot {
  border-color: #22c55e;
  background: #22c55e;
  color: white;
}
.tracker-node.current .tracker-dot {
  border-color: var(--v-accent);
  background: var(--v-accent);
  color: white;
  box-shadow: 0 0 0 4px rgba(37,99,235,0.15);
  animation: dotPulse 2s ease-in-out infinite;
}
@keyframes dotPulse {
  0%, 100% { box-shadow: 0 0 0 4px rgba(37,99,235,0.15); }
  50% { box-shadow: 0 0 0 7px rgba(37,99,235,0.08); }
}
.tracker-node.pending .tracker-dot {
  background: var(--v-bg);
  border-color: var(--v-border);
}
.td-icon {
  width: 0.875rem;
  height: 0.875rem;
}
.td-num {
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--v-text-3);
}
.tracker-line {
  width: 2px;
  flex: 1;
  min-height: 2rem;
  background: var(--v-border);
  transition: background 0.2s;
}
.tracker-line.completed {
  background: #22c55e;
}
.tracker-line.current {
  background: linear-gradient(180deg, var(--v-accent) 0%, var(--v-border) 100%);
}
.tracker-content {
  padding-bottom: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.tracker-content strong {
  font-size: 0.82rem;
  color: var(--v-text);
  line-height: 1.75rem;
}
.tracker-node.pending .tracker-content strong {
  color: var(--v-text-3);
}
.tracker-date {
  font-size: 0.72rem;
  color: var(--v-text-3);
  font-weight: 500;
}
.tracker-current-tag {
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--v-accent);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.tracker-party {
  font-size: 0.72rem;
  color: var(--v-text-3);
  margin: 0;
  font-style: italic;
}

/* Gantt Chart Container override */
.gantt-target {
  min-height: 250px;
  --scrollbar-thumb: var(--v-text-3);
  --scrollbar-track: var(--v-muted);
  scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
  scrollbar-width: thin;
}
@supports not (scrollbar-color: auto) {
  .gantt-target::-webkit-scrollbar {
    height: 6px;
  }
  .gantt-target::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 99px;
  }
  .gantt-target::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
  }
}
.calendar-view-container {
  --scrollbar-thumb: var(--v-text-3);
  --scrollbar-track: var(--v-muted);
  scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track);
  scrollbar-width: thin;
}
@supports not (scrollbar-color: auto) {
  .calendar-view-container::-webkit-scrollbar {
    height: 6px;
  }
  .calendar-view-container::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 99px;
  }
  .calendar-view-container::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
  }
}
.gantt-target :deep(.gantt) .grid-header {
  fill: var(--v-bg) !important;
  stroke: var(--v-border) !important;
}
.gantt-target :deep(.gantt) .grid-row {
  fill: var(--v-bg) !important;
  stroke: var(--v-border) !important;
}
.gantt-target :deep(.gantt) .grid-row:nth-child(even) {
  fill: var(--v-sub) !important;
}
.gantt-target :deep(.gantt) .tick {
  stroke: var(--v-border) !important;
}
.gantt-target :deep(.gantt) .bar-label {
  fill: #fff !important;
  font-weight: bold;
  font-size: 10px;
}
.gantt-target :deep(.gantt) .bar-label.active {
  fill: var(--v-text) !important;
}

/* FullCalendar styling */
:global(.fc) {
  --fc-button-bg-color: var(--v-bg);
  --fc-button-border-color: var(--v-border);
  --fc-button-text-color: var(--v-text);
  --fc-button-hover-bg-color: var(--v-sub);
  --fc-button-hover-border-color: var(--v-border);
  --fc-button-active-bg-color: var(--v-accent);
  --fc-button-active-border-color: var(--v-accent);
  --fc-border-color: var(--v-border);
  --fc-page-bg-color: var(--v-bg);
}
:global(.fc .fc-toolbar-title) {
  font-size: 1.1rem !important;
  font-weight: 700;
}
:global(.fc .fc-col-header-cell-cushion),
:global(.fc .fc-daygrid-day-number) {
  color: var(--v-text);
  font-size: 0.8rem;
  text-decoration: none !important;
}
</style>
