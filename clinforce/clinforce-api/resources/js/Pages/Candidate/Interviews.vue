<!-- resources/js/Pages/Candidate/Interviews.vue -->
<template>
  <CandidateLayout subtitle="Candidate interviews" :displayName="meName">
    <div class="page">
      <div class="shell">
        <!-- Header (sidebar-like) -->
        <section class="head">
          <div class="headLeft">
            <h1 class="h1">Interviews</h1>
            <p class="sub">See your upcoming and past interviews, plus instructions and links.</p>
          </div>

          <div class="headRight">
            <RouterLink :to="{ name: 'candidate.applications' }" class="btn ghost">
              View all applications
            </RouterLink>
            <button class="btn" type="button" @click="loadInterviews" :disabled="loading">
              {{ loading ? "Loading…" : "Refresh" }}
            </button>
          </div>
        </section>

        <div class="grid">
          <!-- Main -->
          <section class="card section">
            <div class="sectionHead">
              <div class="titleBlock">
                <h2 class="h2">Upcoming interviews</h2>
                <div class="muted">Scheduled interviews for your applications</div>
              </div>

              <div class="rightMeta">
                <span class="pill neutral">Schedule</span>
              </div>
            </div>

            <div v-if="loading" class="state">
              <div class="spinner" />
              <div>Loading…</div>
            </div>

            <div v-else class="list">
              <article v-for="row in upcoming" :key="row.id" class="item">
                <div class="itemTop">
                  <div class="itemLeft">
                    <div class="itemTitle">{{ row.jobTitle }}</div>
                    <div class="itemMeta">
                      <span class="chip">{{ row.modeLabel }}</span>
                      <span class="dot">•</span>
                      <span class="metaText">
                        {{ row.joinUrl ? 'Online' : (row.mode === 'in_person' ? 'On-site' : '—') }}
                      </span>
                    </div>
                  </div>

                  <div class="itemRight">
                    <div class="mutedMini">Schedule</div>
                    <div class="when">{{ fmt(row.scheduled_start) || 'TBA' }}</div>
                  </div>
                </div>

                <div v-if="row.joinUrl" class="infoRow">
                  <div class="infoLabel">Link</div>
                  <a class="infoValue link" :href="row.joinUrl" target="_blank" rel="noreferrer">
                    {{ row.joinUrl }}
                  </a>
                </div>

                <div v-if="row.mode === 'in_person' && row.location_text" class="infoRow">
                  <div class="infoLabel">Location</div>
                  <div class="infoValue plain">{{ row.location_text }}</div>
                </div>

                <div class="actions">
                  <!-- ✅ same functionality -->
                  <RouterLink
                    :to="{ name: 'candidate.applications.view', params: { id: String(row.application_id) } }"
                    class="btn primary sm"
                  >
                    View application
                  </RouterLink>
                </div>
              </article>

              <div v-if="upcoming.length === 0" class="empty">
                <div class="emptyTitle">No upcoming interviews</div>
                <div class="emptyText">When an employer schedules one, it will appear here.</div>
              </div>

              <div v-if="error" class="alert danger">
                <div class="alertTitle">Error</div>
                <div class="alertText">{{ error }}</div>
              </div>
            </div>
          </section>

          <!-- Side -->
          <aside class="side">
            <section class="card section">
              <div class="sectionHead">
                <div class="titleBlock">
                  <h2 class="h2">Interview prep tips</h2>
                  <div class="muted">Quick checklist</div>
                </div>
                <span class="pill brand">Quick prep</span>
              </div>

              <ul class="tips">
                <li>Review the job description and match your experience to their needs.</li>
                <li>Prepare 2–3 real cases that show your clinical decision-making.</li>
                <li>Test your internet connection, camera, and microphone before virtual interviews.</li>
                <li>Have your CV and licenses ready to share if requested.</li>
              </ul>
            </section>

            <section class="card mini">
              <div class="miniRow">
                <div class="miniTitle">Tip</div>
                <div class="miniText">Open your interview link 5–10 minutes early.</div>
              </div>
            </section>
          </aside>
        </div>
      </div>
    </div>
  </CandidateLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import CandidateLayout from "@/Components/CandidateLayout.vue";
import api from "@/lib/api";

const meName = ref("ME");
const loading = ref(false);
const error = ref("");
const interviews = ref([]);

function unwrap(resData) {
  return resData?.data ?? resData;
}
function unwrapList(resData) {
  const body = unwrap(resData);
  if (body?.data && Array.isArray(body.data)) return body.data;
  if (Array.isArray(body)) return body;
  return [];
}
function fmt(v) {
  if (!v) return "";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return String(v);
  return d.toLocaleString();
}
function normalizeRow(i) {
  const app = i?.application || null;
  const job = app?.job || null;

  // support both schemas
  const joinUrl = i?.meeting_link || i?.provider_join_url || null;

  return {
    id: i?.id,
    application_id: i?.application_id,
    scheduled_start: i?.scheduled_start,
    scheduled_end: i?.scheduled_end,
    mode: i?.mode,
    location_text: i?.location_text,
    status: i?.status,
    joinUrl,
    modeLabel:
      i?.mode === "video"
        ? "Video interview"
        : i?.mode === "phone"
          ? "Phone interview"
          : i?.mode === "in_person"
            ? "In-person interview"
            : "Interview",
    jobTitle: job?.title || `Application #${String(i?.application_id ?? "")}`,
  };
}

async function fetchMe() {
  try {
    const res = await api.get("/me"); // -> /api/me
    const body = unwrap(res.data);
    meName.value = body?.full_name || body?.name || body?.user?.name || "ME";
  } catch {
    meName.value = "ME";
  }
}

async function loadInterviews() {
  loading.value = true;
  error.value = "";
  try {
    const res = await api.get("/interviews"); // -> /api/interviews
    const list = unwrapList(res.data);
    interviews.value = list.map(normalizeRow);
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || "Failed to load interviews.";
    error.value = msg;
    interviews.value = [];
  } finally {
    loading.value = false;
  }
}

const upcoming = computed(() => {
  const now = new Date();
  return interviews.value
    .slice()
    .sort((a, b) => new Date(a.scheduled_start || 0) - new Date(b.scheduled_start || 0))
    .filter((i) => {
      const dt = new Date(i.scheduled_start || 0);
      return Number.isFinite(dt.getTime()) ? dt >= now : true;
    });
});

onMounted(async () => {
  await fetchMe();
  await loadInterviews();
});
</script>

<style scoped>
/* Sidebar-like tokens */
:root{
  --ink: #111827;
  --muted: #6b7280;
  --line: rgba(229,231,235,1);
  --soft: rgba(17,24,39,0.04);
  --soft2: rgba(17,24,39,0.06);
  --shadow: 0 10px 24px rgba(17,24,39,0.08);
  --r12: 12px;
  --r14: 14px;
  --r16: 16px;
}

.page{
  min-height:100vh;
  background:
    radial-gradient(1200px 700px at 20% -10%, rgba(232,155,15,0.12), transparent 55%),
    radial-gradient(900px 600px at 100% 0%, rgba(232,155,15,0.08), transparent 50%),
    linear-gradient(180deg, #fafafa 0%, #f5f5f4 100%);
  color: var(--ink);
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
}

.shell{
  max-width: 1200px;
  margin: 0 auto;
  padding: 18px 16px 44px;
}

/* Header */
.head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px;
  border-radius: var(--r16);
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow);
}
.headLeft{ min-width: 240px; }
.h1{
  margin:0;
  font-size: 18px;
  font-weight: 900;
  letter-spacing: -0.01em;
}
.sub{
  margin: 4px 0 0;
  font-size: 11px;
  color: var(--muted);
  line-height: 1.45;
}
.headRight{ display:flex; gap: 8px; flex-wrap: wrap; }

/* Layout grid */
.grid{
  margin-top: 12px;
  display:grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 12px;
}
@media (max-width: 980px){
  .grid{ grid-template-columns: 1fr; }
}

/* Cards */
.card{
  border-radius: var(--r16);
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow);
}
.section{ padding: 12px; }

/* Section head */
.sectionHead{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
  padding: 4px;
  margin-bottom: 8px;
}
.titleBlock{ display:grid; gap: 2px; }
.h2{
  margin:0;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color:#374151;
}
.muted{ font-size: 11px; color: var(--muted); }
.mutedMini{ font-size: 10px; color: var(--muted); font-weight: 900; }

/* Pills */
.pill{
  display:inline-flex;
  align-items:center;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(17,24,39,0.12);
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
  background: rgba(17,24,39,0.03);
  color: #374151;
}
.pill.neutral{ background: rgba(17,24,39,0.03); }
.pill.brand{
  background: rgba(232,155,15,0.12);
  border-color: rgba(232,155,15,0.35);
  color: #7c2d12;
}

.rightMeta{ display:flex; align-items:center; gap: 8px; }

/* State */
.state{
  display:flex;
  align-items:center;
  gap: 10px;
  padding: 14px 8px;
  color: var(--muted);
  font-size: 12px;
}
.spinner{
  width: 14px;
  height: 14px;
  border-radius: 999px;
  border: 2px solid rgba(17,24,39,0.18);
  border-top-color: rgba(17,24,39,0.55);
  animation: spin 700ms linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* List + items */
.list{ display:flex; flex-direction:column; gap: 10px; }

.item{
  border: 1px solid rgba(229,231,235,1);
  border-radius: var(--r14);
  background: #fff;
  padding: 12px;
  transition: background 120ms ease, transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
}
.item:hover{
  background: var(--soft);
  border-color: rgba(17,24,39,0.14);
  transform: translateY(-1px);
  box-shadow: 0 12px 22px rgba(17,24,39,0.10);
}

.itemTop{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
}
.itemLeft{ min-width: 0; }
.itemTitle{
  font-size: 13px;
  font-weight: 900;
  color: var(--ink);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  max-width: 100%;
}
.itemMeta{
  margin-top: 8px;
  display:flex;
  align-items:center;
  gap: 8px;
  min-width:0;
}
.chip{
  font-size: 10px;
  font-weight: 900;
  padding: 3px 8px;
  border-radius: 999px;
  border: 1px solid rgba(17,24,39,0.12);
  background: rgba(17,24,39,0.03);
  color: #374151;
  flex: 0 0 auto;
}
.metaText{
  font-size: 11px;
  color: var(--muted);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  min-width:0;
}
.dot{ color:#d1d5db; font-weight: 900; }

.itemRight{
  flex: 0 0 auto;
  text-align: right;
  min-width: 140px;
}
.when{
  margin-top: 4px;
  font-size: 12px;
  font-weight: 900;
  color: var(--ink);
}

/* Info rows */
.infoRow{
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed rgba(229,231,235,1);
  display:grid;
  grid-template-columns: 80px minmax(0, 1fr);
  gap: 10px;
  align-items:start;
}
.infoLabel{
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}
.infoValue{
  font-size: 11px;
  font-weight: 800;
  color: var(--ink);
  word-break: break-word;
}
.infoValue.link{
  color: #b45309;
  text-decoration: underline;
  font-weight: 900;
}
.infoValue.plain{ color: var(--ink); text-decoration: none; }

/* Actions */
.actions{
  margin-top: 10px;
  display:flex;
  justify-content:flex-end;
}

/* Buttons (match TopBar) */
.btn{
  border: 1px solid rgba(17,24,39,0.14);
  background: #111827;
  color: #fff;
  border-radius: var(--r12);
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  transition: transform 120ms ease, background 120ms ease;
  text-decoration: none;
  white-space: nowrap;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.btn:hover{ transform: translateY(-1px); background: #0b1220; }
.btn:active{ transform: translateY(0px); }

.btn.ghost{
  background: #fff;
  color: var(--ink);
  border: 1px solid var(--line);
}
.btn.ghost:hover{ background: rgba(17,24,39,0.02); transform: translateY(-1px); }

.btn.primary{ background: #111827; color:#fff; }
.btn.sm{ padding: 7px 10px; font-size: 11px; border-radius: var(--r12); }

/* Side */
.side{ display:flex; flex-direction:column; gap: 12px; }

.tips{
  margin: 0;
  padding-left: 18px;
  color: #374151;
  font-size: 12px;
  line-height: 1.7;
}
.mini{
  padding: 12px;
}
.miniRow{
  display:grid;
  gap: 4px;
}
.miniTitle{
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.02em;
  color: #374151;
}
.miniText{
  font-size: 12px;
  color: var(--muted);
  line-height: 1.5;
}

/* Empty + alerts */
.empty{
  border: 1px dashed rgba(229,231,235,1);
  border-radius: var(--r14);
  background: rgba(255,255,255,0.8);
  padding: 14px;
  text-align: center;
}
.emptyTitle{ font-weight: 900; font-size: 12px; color: var(--ink); }
.emptyText{ margin-top: 4px; font-size: 11px; color: var(--muted); }

.alert{
  border-radius: var(--r14);
  border: 1px solid rgba(229,231,235,1);
  background: rgba(255,255,255,0.9);
  padding: 10px;
  margin-top: 10px;
}
.alertTitle{ font-weight: 900; font-size: 12px; }
.alertText{ margin-top: 4px; font-size: 11px; color: var(--muted); }
.alert.danger{
  border-color: rgba(153,27,27,0.18);
  background: rgba(254,242,242,0.85);
}
.alert.danger .alertTitle{ color:#991b1b; }
.alert.danger .alertText{ color:#7f1d1d; }
</style>