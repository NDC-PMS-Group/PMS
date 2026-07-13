<template>
  <Teleport to="body">
    <div class="backdrop" @mousedown.self="$emit('close')">
      <aside class="drawer" role="dialog" aria-modal="true" :aria-labelledby="titleId" @keydown.esc="$emit('close')">
        <header><div><small>{{ task ? task.project?.project_code || 'TASK' : 'NEW TASK' }}</small><h2 :id="titleId">{{ task ? task.title : 'Create task' }}</h2></div><button ref="closeButton" type="button" title="Close" aria-label="Close task details" @click="$emit('close')"><X /></button></header>
        <div v-if="loading" class="drawer-state"><Loader2 class="spin" />Loading task details...</div>
        <form v-else class="content" @submit.prevent="submit">
          <fieldset :disabled="!editable || saving">
            <label v-if="!task">Project<select v-model.number="form.project_id" required><option :value="0" disabled>Select project</option><option v-for="project in projects" :key="project.id" :value="project.id">{{ project.label }}</option></select></label>
            <label>Title<input v-model.trim="form.title" required maxlength="255" /></label>
            <label>Description<textarea v-model="form.description" rows="4"></textarea></label>
            <div class="form-grid">
              <label>Status<select v-model="form.status"><option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label>
              <label>Priority<select v-model="form.priority"><option value="critical">Critical</option><option value="urgent">Urgent</option><option value="high">High</option><option value="normal">Normal</option><option value="medium">Medium</option><option value="low">Low</option></select></label>
              <label>Start date<input v-model="form.start_date" type="date" /></label>
              <label>Due date<input v-model="form.due_date" type="date" :min="form.start_date || undefined" /></label>
              <label class="wide">Assignee<select v-model.number="form.assigned_to"><option :value="0">Unassigned</option><option v-for="person in assignees" :key="person.id" :value="person.id">{{ person.label }}</option></select></label>
            </div>
          </fieldset>

          <section v-if="task" class="subtasks"><div class="section-head"><h3>Checklist</h3><span>{{ completedSubtasks }}/{{ task.subtasks?.length || 0 }}</span></div><div v-if="task.subtasks?.length" class="subtask-list"><label v-for="subtask in task.subtasks" :key="subtask.id"><input type="checkbox" :checked="subtask.status === 'completed'" :disabled="!editable || saving" @change="$emit('toggle-subtask', subtask)" /><span>{{ subtask.title }}</span></label></div><p v-else>No checklist items.</p><div v-if="editable" class="add-subtask"><input v-model.trim="subtaskTitle" placeholder="Add checklist item" @keyup.enter.prevent="addSubtask" /><button type="button" :disabled="!subtaskTitle || saving" title="Add checklist item" aria-label="Add checklist item" @click="addSubtask"><Plus /></button></div></section>

          <section v-if="task?.status_history?.length" class="history"><h3>Activity</h3><ol><li v-for="event in task.status_history" :key="event.id"><span></span><div><strong>{{ event.notes || label(event.event_type) }}</strong><small>{{ dateTime(event.changed_at) }}</small></div></li></ol></section>
        </form>
        <footer v-if="!loading"><button v-if="task && canDelete" type="button" class="danger" :disabled="saving" @click="$emit('delete', task)"><Trash2 />Delete</button><span></span><button type="button" class="secondary" @click="$emit('close')">Close</button><button v-if="editable" type="button" class="primary" :disabled="saving || !form.title || (!task && !form.project_id)" @click="submit"><Loader2 v-if="saving" class="spin" /><Save v-else />{{ task ? 'Save changes' : 'Create task' }}</button></footer>
      </aside>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
import { Loader2, Plus, Save, Trash2, X } from "lucide-vue-next";
import type { TaskFacetOption, TaskItem, TaskPriority, TaskStatus } from "@/types/task";
const props = defineProps<{ task: TaskItem | null; initialStatus?: TaskStatus; projects: TaskFacetOption[]; assignees: TaskFacetOption[]; editable: boolean; canDelete: boolean; loading: boolean; saving: boolean; projectId?: number }>();
const emit = defineEmits<{ close: []; save: [payload: any]; delete: [task: TaskItem]; "toggle-subtask": [task: TaskItem]; "add-subtask": [title: string] }>();
const titleId = `task-drawer-${Math.random().toString(36).slice(2)}`;
const closeButton = ref<HTMLButtonElement | null>(null);
const subtaskTitle = ref("");
const form = reactive({ project_id: 0, title: "", description: "", status: "pending" as TaskStatus, priority: "normal" as TaskPriority, start_date: "", due_date: "", assigned_to: 0 });
const hydrate = () => { const task = props.task; form.project_id = task?.project_id || task?.project?.id || props.projectId || 0; form.title = task?.title || ""; form.description = task?.description || ""; form.status = task?.status || props.initialStatus || "pending"; form.priority = task?.priority || "normal"; form.start_date = task?.start_date || ""; form.due_date = task?.due_date || ""; form.assigned_to = task?.assigned_to?.id || 0; };
watch(() => props.task, hydrate, { immediate: true });
watch(() => props.initialStatus, hydrate);
onMounted(() => nextTick(() => closeButton.value?.focus()));
const completedSubtasks = computed(() => props.task?.subtasks?.filter((item) => item.status === "completed").length || 0);
const submit = () => emit("save", { ...form, description: form.description || null, start_date: form.start_date || null, due_date: form.due_date || null, assigned_to: form.assigned_to || null });
const addSubtask = () => { if (!subtaskTitle.value) return; emit("add-subtask", subtaskTitle.value); subtaskTitle.value = ""; };
const label = (value: string) => value.replaceAll("_", " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
const dateTime = (value?: string | null) => value ? new Date(value).toLocaleString("en-PH", { dateStyle: "medium", timeStyle: "short" }) : "";
</script>

<style scoped>
.backdrop { position:fixed;inset:0;z-index:9999;display:flex;justify-content:flex-end;background:rgb(15 23 42 / .55) }.drawer { width:min(560px,100%);height:100%;display:grid;grid-template-rows:auto 1fr auto;background:#fff;box-shadow:-12px 0 30px rgb(15 23 42 / .18) }.drawer>header { min-height:68px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid #e2e8f0 }.drawer header div { min-width:0 }.drawer header small { color:#2563eb;font-size:9px;font-weight:900 }.drawer h2 { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#0f172a;font-size:16px;font-weight:800 }.drawer header button { width:36px;height:36px;display:grid;place-items:center;border:1px solid #cbd5e1;color:#475569 }.drawer header svg { width:17px }.content { overflow-y:auto;padding:16px }.content fieldset { display:grid;gap:13px }.content label { display:grid;gap:5px;color:#475569;font-size:11px;font-weight:800 }.content input,.content textarea,.content select { width:100%;border:1px solid #cbd5e1;background:#fff;color:#0f172a;padding:8px 10px;font-size:13px }.content input,.content select { height:38px }.content textarea { resize:vertical }.content fieldset:disabled input,.content fieldset:disabled textarea,.content fieldset:disabled select { border-color:transparent;background:#f8fafc;color:#475569;opacity:1 }.form-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px }.wide { grid-column:1/-1 }.subtasks,.history { margin-top:20px;padding-top:15px;border-top:1px solid #e2e8f0 }.section-head { display:flex;justify-content:space-between }.subtasks h3,.history h3 { color:#1e293b;font-size:12px;font-weight:900 }.section-head span { color:#64748b;font-size:11px }.subtask-list { display:grid;margin-top:9px }.subtask-list label { display:grid;grid-template-columns:18px 1fr;align-items:center;gap:8px;min-height:34px;border-bottom:1px solid #f1f5f9;color:#334155;font-size:12px;font-weight:600 }.subtask-list input { width:15px;height:15px;padding:0 }.subtasks p { margin-top:8px;color:#94a3b8;font-size:11px }.add-subtask { display:grid;grid-template-columns:1fr 36px;gap:6px;margin-top:10px }.add-subtask button { display:grid;place-items:center;background:#e2e8f0;color:#334155 }.add-subtask svg { width:15px }.history ol { display:grid;gap:10px;margin-top:10px }.history li { display:grid;grid-template-columns:9px 1fr;gap:9px }.history li>span { width:7px;height:7px;margin-top:5px;border-radius:50%;background:#2563eb }.history li div { display:grid }.history strong { color:#334155;font-size:11px;font-weight:700 }.history small { color:#94a3b8;font-size:10px }.drawer>footer { min-height:62px;display:flex;align-items:center;gap:8px;padding:10px 16px;border-top:1px solid #e2e8f0 }.drawer>footer>span { flex:1 }.drawer footer button { min-height:36px;display:flex;align-items:center;gap:7px;padding:0 12px;border:1px solid #cbd5e1;font-size:12px;font-weight:800 }.drawer footer svg { width:15px }.primary { border-color:#2563eb!important;background:#2563eb;color:#fff }.danger { border-color:#fecaca!important;color:#dc2626 }.secondary { color:#475569 }.drawer-state { display:flex;align-items:center;justify-content:center;gap:8px;color:#64748b;font-size:13px }.spin { animation:spin 1s linear infinite }@keyframes spin{to{transform:rotate(360deg)}}
:global(.dark) .drawer { background:#0f172a }:global(.dark) .drawer>header,:global(.dark) .drawer>footer,:global(.dark) .subtasks,:global(.dark) .history { border-color:#334155 }:global(.dark) .drawer h2,:global(.dark) .subtasks h3,:global(.dark) .history h3 { color:#f8fafc }:global(.dark) .content label { color:#cbd5e1 }:global(.dark) .content input,:global(.dark) .content textarea,:global(.dark) .content select { border-color:#475569;background:#1e293b;color:#f8fafc }:global(.dark) .content fieldset:disabled input,:global(.dark) .content fieldset:disabled textarea,:global(.dark) .content fieldset:disabled select { background:#111827;color:#cbd5e1 }:global(.dark) .subtask-list label { border-color:#1e293b;color:#e2e8f0 }:global(.dark) .history strong { color:#cbd5e1 }
@media(max-width:560px){.form-grid{grid-template-columns:1fr}.wide{grid-column:auto}.drawer>footer{flex-wrap:wrap}.drawer>footer>span{display:none}.drawer footer button{flex:1;justify-content:center}}
</style>
