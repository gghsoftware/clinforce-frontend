<!-- resources/js/Pages/Candidate/Jobs.vue -->
<template>
  <CandidateLayout subtitle="Browse jobs" :displayName="meName">
    <div class="page">
      <div class="shell">
        <!-- Header (sidebar-like) -->
        <section class="head">
          <div class="headLeft">
            <h1 class="h1">Jobs</h1>
            <p class="sub">Browse open roles and filter by location, type, and work mode.</p>
          </div>

          <div class="headRight">
            <button class="btn ghost" type="button" @click="resetFilters" :disabled="loading">
              Reset
            </button>
            <button class="btn" type="button" @click="fetchJobs(1)" :disabled="loading">
              {{ loading ? "Loading…" : "Search" }}
            </button>
          </div>
        </section>

        <div class="grid">
          <!-- Main -->
          <section class="card section">
            <div class="sectionHead">
              <div class="titleBlock">
                <h2 class="h2">Open roles</h2>
                <div class="muted">Use search and filters to discover roles that match your preferences</div>
              </div>

              <div class="rightMeta">
                <span class="pill neutral">Search</span>
              </div>
            </div>

            <!-- Filters (same vibe as interviews “itemMeta / chip” blocks) -->
            <div class="filters">
              <div class="field wide">
                <div class="label">Search</div>
                <div class="inputWrap">
                  <span class="icon">⌕</span>
                  <input
                    v-model="q"
                    class="input"
                    type="text"
                    placeholder="Search by job title, hospital, skill"
                    @keydown.enter.prevent="fetchJobs(1)"
                  />
                </div>
              </div>

              <div class="field">
                <div class="label">Location</div>
                <select v-model="city" class="select" @change="fetchJobs(1)">
                  <option value="">All locations</option>
                  <option value="Metro Manila">Metro Manila</option>
                  <option value="Cebu">Cebu</option>
                  <option value="Davao">Davao</option>
                  <option value="Remote">Remote</option>
                </select>
              </div>

              <!-- UI-only -->
              <div class="field">
                <div class="label">Department</div>
                <select v-model="dept" class="select" @change="fetchJobs(1)">
                  <option value="">All departments</option>
                  <option value="ICU">ICU / Critical Care</option>
                  <option value="Emergency">Emergency</option>
                  <option value="Medical-Surgical">Medical-Surgical</option>
                  <option value="Telehealth">Telehealth</option>
                </select>
              </div>

              <div class="field">
                <div class="label">Employment</div>
                <select v-model="employmentType" class="select" @change="fetchJobs(1)">
                  <option value="">All types</option>
                  <option value="full_time">Full time</option>
                  <option value="part_time">Part time</option>
                  <option value="contract">Contract</option>
                  <option value="temporary">Temporary</option>
                  <option value="internship">Internship</option>
                </select>
              </div>

              <div class="field">
                <div class="label">Work mode</div>
                <select v-model="workMode" class="select" @change="fetchJobs(1)">
                  <option value="">All modes</option>
                  <option value="on_site">On-site</option>
                  <option value="remote">Remote</option>
                  <option value="hybrid">Hybrid</option>
                </select>
              </div>
            </div>

            <div v-if="loading" class="state">
              <div class="spinner" />
              <div>Loading…</div>
            </div>

            <div v-else>
              <div v-if="error" class="alert danger">
                <div class="alertTitle">Error</div>
                <div class="alertText">{{ error }}</div>
              </div>

              <div class="list">
                <article v-for="j in jobs" :key="String(j.id ?? j._id ?? j.uuid ?? j.slug)" class="item">
                  <div class="itemTop">
                    <div class="itemLeft">
                      <div class="itemTitle">{{ j.title || "Untitled job" }}</div>
                      <div class="itemMeta">
                        <span class="chip">{{ prettyEnum(j.employment_type) }}</span>
                        <span class="dot">•</span>
                        <span class="metaText">{{ prettyEnum(j.work_mode) }}</span>
                        <span class="dot">•</span>
                        <span class="metaText">{{ formatLocation(j) }}</span>
                      </div>
                    </div>

                    <div class="itemRight">
                      <div class="mutedMini">Status</div>
                      <div class="when">{{ prettyEnum(j.status) }}</div>
                    </div>
                  </div>

                  <div class="infoRow">
                    <div class="infoLabel">Summary</div>
                    <div class="infoValue plain">
                      {{ j.description ? trim(j.description, 170) : "—" }}
                    </div>
                  </div>

                  <div class="infoRow">
                    <div class="infoLabel">Posted</div>
                    <div class="infoValue plain">
                      {{ relativeTime(j.published_at || j.created_at) }}
                    </div>
                  </div>

                  <div class="actions">
                    <RouterLink
                      v-if="canUseDetailsRoute"
                      :to="{ name: 'candidate.jobs.view', params: { id: j.id } }"
                      class="btn primary sm"
                    >
                      View details
                    </RouterLink>

                    <a
                      v-else
                      class="btn primary sm"
                      :href="`/candidate/jobs/${encodeURIComponent(String(j.id))}`"
                    >
                      View details
                    </a>
                  </div>
                </article>

                <div v-if="jobs.length === 0" class="empty">
                  <div class="emptyTitle">No jobs found</div>
                  <div class="emptyText">Try adjusting your filters and search again.</div>
                </div>

                <div v-if="pagination" class="pager">
                  <div class="pagerLeft">
                    <span class="pill neutral">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                  </div>

                  <div class="pagerRight">
                    <button
                      class="btn ghost sm"
                      type="button"
                      :disabled="loading || pagination.current_page <= 1"
                      @click="fetchJobs(pagination.current_page - 1)"
                    >
                      Prev
                    </button>
                    <button
                      class="btn sm"
                      type="button"
                      :disabled="loading || pagination.current_page >= pagination.last_page"
                      @click="fetchJobs(pagination.current_page + 1)"
                    >
                      Next
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Side -->
          <aside class="side">
            <section class="card section">
              <div class="sectionHead">
                <div class="titleBlock">
                  <h2 class="h2">Tips</h2>
                  <div class="muted">Quick checklist</div>
                </div>
                <span class="pill brand">Quick help</span>
              </div>

              <ul class="tips">
                <li>Use keywords: ICU, ER, Dialysis, NICU, OR.</li>
                <li>Filter by location first, then refine other filters.</li>
                <li>Open a job to view requirements and apply.</li>
              </ul>
            </section>

            <section class="card mini">
              <div class="miniRow">
                <div class="miniTitle">Endpoint</div>
                <div class="miniText">
                  Loads jobs from <code>/api/public/jobs</code>.
                </div>
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
import { RouterLink, useRouter } from "vue-router";
import CandidateLayout from "@/Components/CandidateLayout.vue";
import api from "@/lib/api";

const router = useRouter();

const meName = ref("ME");
const loading = ref(false);
const error = ref("");

// store RAW body returned from API (Laravel paginator object)
const payload = ref(null);

const q = ref("");
const city = ref("");
const dept = ref(""); // UI-only for now
const employmentType = ref("");
const workMode = ref("");

function unwrapJobsList(body) {
  if (!body) return [];
  if (Array.isArray(body)) return body;

  // Laravel paginator: { data: [...], current_page, last_page, ... }
  if (body && typeof body === "object" && Array.isArray(body.data)) return body.data;

  // Some APIs: { data: { data: [...] } }
  if (body?.data?.data && Array.isArray(body.data.data)) return body.data.data;

  return [];
}

function unwrapPagination(body) {
  if (!body || typeof body !== "object") return null;
  if (Number.isFinite(body.current_page) && Number.isFinite(body.last_page)) {
    return { current_page: body.current_page, last_page: body.last_page };
  }
  if (body?.meta && Number.isFinite(body.meta.current_page) && Number.isFinite(body.meta.last_page)) {
    return { current_page: body.meta.current_page, last_page: body.meta.last_page };
  }
  return null;
}

const jobs = computed(() => unwrapJobsList(payload.value));
const pagination = computed(() => unwrapPagination(payload.value));

const canUseDetailsRoute = computed(() => {
  try {
    return router.hasRoute("candidate.jobs.view");
  } catch {
    return false;
  }
});

function trim(s, n) {
  const t = String(s ?? "");
  return t.length > n ? t.slice(0, n - 1) + "…" : t;
}

function prettyEnum(v) {
  const t = String(v ?? "").trim();
  if (!t) return "—";
  return t
    .split("_")
    .map((w) => (w ? w[0].toUpperCase() + w.slice(1) : w))
    .join(" ");
}

function relativeTime(v) {
  if (!v) return "—";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return String(v);

  const diff = Date.now() - d.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${mins} min ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs} hours ago`;
  return `${Math.floor(hrs / 24)} days ago`;
}

function formatLocation(j) {
  const parts = [j.city, j.country_code].filter(Boolean);
  return parts.length ? parts.join(", ") : "—";
}

async function fetchJobs(page = 1) {
  loading.value = true;
  error.value = "";

  try {
    const params = { page };

    if (q.value) params.q = q.value;
    if (city.value) params.city = city.value;

    // match your DB schema columns:
    if (employmentType.value) params.employment_type = employmentType.value;
    if (workMode.value) params.work_mode = workMode.value;

    // dept is UI-only (no column yet), so do NOT send it
    const res = await api.get("/public/jobs", { params });
    payload.value = res.data;
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Failed to load jobs.";
    payload.value = null;
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  q.value = "";
  city.value = "";
  dept.value = "";
  employmentType.value = "";
  workMode.value = "";
  fetchJobs(1);
}

onMounted(() => fetchJobs(1));
</script>

<style scoped>
/* Sidebar-like tokens */
:root{
  --ink: #111827;
  --muted: #6b7280;
  --line: rgba(229,231,235,1);
  --soft: rgba(17,24,39,0.04);
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

/* Filters */
.filters{
  display:grid;
  grid-template-columns: 2fr repeat(4, 1fr);
  gap: 10px;
  padding: 6px 4px 10px;
}
@media (max-width: 980px){
  .filters{ grid-template-columns: 1fr 1fr; }
}
@media (max-width: 560px){
  .filters{ grid-template-columns: 1fr; }
}

.field{ display:grid; gap: 6px; }
.field.wide{ grid-column: span 2; }
@media (max-width: 980px){
  .field.wide{ grid-column: auto; }
}

.label{
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}

.inputWrap{ position: relative; }
.icon{
  position:absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: rgba(17,24,39,0.35);
  font-size: 12px;
  pointer-events: none;
}

.input, .select{
  width:100%;
  border: 1px solid rgba(17,24,39,0.12);
  border-radius: var(--r12);
  padding: 9px 10px;
  font-size: 12px;
  background:#fff;
  color: var(--ink);
  outline: none;
}
.input{ padding-left: 32px; }
.input:focus, .select:focus{
  border-color: rgba(232,155,15,0.45);
  box-shadow: 0 0 0 4px rgba(232,155,15,0.10);
}

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
  flex-wrap: wrap;
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
.infoValue.plain{ color: var(--ink); text-decoration: none; }

/* Actions */
.actions{
  margin-top: 10px;
  display:flex;
  justify-content:flex-end;
}

/* Buttons (match Interviews style you sent) */
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
.btn:disabled{ opacity: 0.6; cursor: not-allowed; transform: none; }

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
  margin-bottom: 10px;
}
.alertTitle{ font-weight: 900; font-size: 12px; }
.alertText{ margin-top: 4px; font-size: 11px; color: var(--muted); }
.alert.danger{
  border-color: rgba(153,27,27,0.18);
  background: rgba(254,242,242,0.85);
}
.alert.danger .alertTitle{ color:#991b1b; }
.alert.danger .alertText{ color:#7f1d1d; }

/* Pager */
.pager{
  margin-top: 10px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap: 10px;
}
.pagerRight{ display:flex; gap: 8px; flex-wrap: wrap; }
</style>