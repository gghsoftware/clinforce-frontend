<!-- resources/js/Pages/Employer/Jobs/JobsDetails.vue -->
<script setup>
  import { computed, onMounted, ref } from "vue";
  import { RouterLink, useRoute, useRouter } from "vue-router";
  import UiCard from "../../../Components/UiCard.vue";
  import { http } from "../../../lib/http";
  
  const route = useRoute();
  const router = useRouter();
  
  const id = computed(() => route.params.id);
  
  const loading = ref(true);
  const working = ref(false);
  const error = ref("");
  
  const job = ref(null);
  const apps = ref([]);
  const appsError = ref("");
  
  function normalizeJob(payload) {
    return payload?.data ?? payload ?? null;
  }
  
  function normalizeList(payload) {
    const d = payload?.data ?? payload ?? [];
    if (Array.isArray(d)) return d;
    if (Array.isArray(d?.data)) return d.data;
    if (Array.isArray(d?.items)) return d.items;
    return [];
  }
  
  const status = computed(() => {
    const s = String(job.value?.status || "").toLowerCase();
    if (s === "published") return "published";
    if (s === "archived") return "archived";
    return "draft";
  });
  
  const statusText = computed(() => {
    if (status.value === "published") return "Published";
    if (status.value === "archived") return "Archived";
    return "Draft";
  });
  
  function formatDate(d) {
    if (!d) return "—";
    const dt = new Date(d);
    if (!Number.isFinite(dt.getTime())) return "—";
    return dt.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "2-digit" });
  }
  
  async function load() {
    loading.value = true;
    error.value = "";
    appsError.value = "";
  
    try {
      const jr = await http.get(`/jobs/${id.value}`);
      job.value = normalizeJob(jr?.data);
  
      // optional preview (won't break if forbidden/not found)
      try {
        const ar = await http.get("/applications", { params: { scope: "owned" } });
        const all = normalizeList(ar?.data);
        apps.value = all
          .filter(a => String(a.job_id || a.job?.id) === String(id.value))
          .slice(0, 5);
      } catch (e) {
        const code = e?.response?.status;
        if (code === 403) appsError.value = "Applicants preview is not allowed for your account.";
        else if (code === 404) appsError.value = "Applicants endpoint not available yet.";
        else appsError.value = "Applicants preview unavailable.";
        apps.value = [];
      }
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || "Failed to load job.";
      job.value = null;
    } finally {
      loading.value = false;
    }
  }
  
  async function publish() {
    working.value = true;
    try {
      await http.post(`/jobs/${id.value}/publish`);
      await load();
    } finally {
      working.value = false;
    }
  }
  
  async function archive() {
    working.value = true;
    try {
      await http.post(`/jobs/${id.value}/archive`);
      await load();
    } finally {
      working.value = false;
    }
  }
  
  async function destroyJob() {
    if (!confirm("Delete this role?")) return;
    working.value = true;
    try {
      await http.delete(`/jobs/${id.value}`);
      router.push({ name: "employer.jobs" });
    } finally {
      working.value = false;
    }
  }
  
  async function copyShareLink() {
    // if you have a public route, replace this
    const url = `${window.location.origin}/candidate/jobs/${id.value}`;
    try {
      await navigator.clipboard.writeText(url);
      alert("Link copied.");
    } catch {
      prompt("Copy this link:", url);
    }
  }
  
  onMounted(load);
  </script>
  
  <template>
    <div class="page">
      <div class="top">
        <RouterLink :to="{ name: 'employer.jobs' }" class="back">← Back to roles</RouterLink>
  
        <button class="ghost" type="button" :disabled="loading" @click="load">
          {{ loading ? "Refreshing…" : "Refresh" }}
        </button>
      </div>
  
      <div v-if="loading" class="state">Loading…</div>
  
      <template v-else>
        <div v-if="error" class="alert" role="alert">
          <div class="alertTitle">Error</div>
          <div class="alertMsg">{{ error }}</div>
        </div>
  
        <template v-if="job">
          <div class="header">
            <div class="titleBlock">
              <div class="eyebrow">Role details</div>
              <h1 class="h1">{{ job.title || "Role" }}</h1>
  
              <div class="sub">
                <span>{{ job.city || "—" }}</span>
                <span class="dot">•</span>
                <span>{{ job.country_code || "—" }}</span>
                <span class="dot">•</span>
                <span class="muted">#{{ job.id }}</span>
              </div>
  
              <div class="chips">
                <span class="chip" :data-status="status">{{ statusText }}</span>
                <span v-if="job.employment_type" class="chip">{{ job.employment_type }}</span>
                <span v-if="job.work_mode" class="chip">{{ job.work_mode }}</span>
                <span v-if="job.published_at" class="chip">Published {{ formatDate(job.published_at) }}</span>
                <span v-if="job.archived_at" class="chip">Archived {{ formatDate(job.archived_at) }}</span>
              </div>
            </div>
  
            <div class="actions">
              <RouterLink
                class="btn"
                :to="{ name: 'employer.jobs.edit', params: { id: job.id } }"
              >
                Edit
              </RouterLink>
  
              <button class="btn" type="button" :disabled="working" @click="publish">
                Publish
              </button>
  
              <button class="btn" type="button" :disabled="working" @click="archive">
                Archive
              </button>
  
              <button class="btn danger" type="button" :disabled="working" @click="destroyJob">
                Delete
              </button>
            </div>
          </div>
  
          <div class="grid">
            <div class="col">
              <UiCard title="Role description">
                <p class="p">{{ job.description || "—" }}</p>
  
                <div class="metaGrid">
                  <div class="metaItem">
                    <div class="k">Employment type</div>
                    <div class="v">{{ job.employment_type || "—" }}</div>
                  </div>
                  <div class="metaItem">
                    <div class="k">Work mode</div>
                    <div class="v">{{ job.work_mode || "—" }}</div>
                  </div>
                  <div class="metaItem">
                    <div class="k">Location</div>
                    <div class="v">{{ job.city || "—" }}, {{ job.country_code || "—" }}</div>
                  </div>
                </div>
              </UiCard>
  
              <UiCard title="Applicants preview" subtitle="Uses /applications?scope=owned (optional)">
                <div v-if="appsError" class="miniWarn">{{ appsError }}</div>
  
                <div v-if="apps.length" class="apps">
                  <div v-for="a in apps" :key="a.id" class="appRow">
                    <div class="appLeft">
                      <div class="appName">
                        {{ a.applicant_name || a.user?.name || "Applicant" }}
                      </div>
                      <div class="appMeta">
                        <span class="pill">{{ a.status || "submitted" }}</span>
                        <span class="sep">•</span>
                        <span class="muted">#{{ a.id }}</span>
                      </div>
                    </div>
                  </div>
                </div>
  
                <div v-else class="empty">No applicants found.</div>
              </UiCard>
            </div>
  
            <div class="col">
              <UiCard title="Quick actions">
                <div class="quick">
                  <button class="primary" type="button" @click="copyShareLink">
                    Copy share link
                  </button>
  
                  <RouterLink
                    class="ghostLink"
                    :to="{ name: 'employer.jobs.edit', params: { id: job.id } }"
                  >
                    Edit role
                  </RouterLink>
                </div>
              </UiCard>
  
              <UiCard title="Timeline">
                <dl class="dl">
                  <div class="dlRow">
                    <dt>Created</dt>
                    <dd>{{ formatDate(job.created_at) }}</dd>
                  </div>
                  <div class="dlRow">
                    <dt>Updated</dt>
                    <dd>{{ formatDate(job.updated_at) }}</dd>
                  </div>
                  <div class="dlRow">
                    <dt>Status</dt>
                    <dd>{{ statusText }}</dd>
                  </div>
                </dl>
              </UiCard>
            </div>
          </div>
        </template>
  
        <div v-else class="state">
          Role not found.
        </div>
      </template>
    </div>
  </template>
  
  <style scoped>
  /* Page shell */
  .page{
    max-width: 1152px;
    margin: 0 auto;
    padding: 20px 16px;
    display: grid;
    gap: 14px;
  }
  
  /* Top bar */
  .top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
  }
  
  .back{
    display:inline-flex;
    align-items:center;
    gap: 8px;
    text-decoration:none;
    color:#111827;
    font-weight:900;
    font-size: 12px;
    padding: 8px 10px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
  }
  .back:hover{ background:#f9fafb; }
  
  .ghost{
    height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #111827;
    font-size: 12px;
    font-weight: 900;
    cursor: pointer;
  }
  .ghost:hover{ background:#f9fafb; }
  .ghost:disabled{ opacity:.65; cursor:not-allowed; }
  
  /* Header */
  .header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 14px;
    flex-wrap: wrap;
  }
  
  .titleBlock{
    min-width: 0;
    flex: 1;
  }
  
  .eyebrow{
    font-size: 11px;
    font-weight: 900;
    letter-spacing: .10em;
    text-transform: uppercase;
    color:#6b7280;
  }
  
  .h1{
    margin: 6px 0 0;
    font-size: 22px;
    font-weight: 950;
    letter-spacing: -0.02em;
    color:#111827;
    line-height: 1.15;
  }
  
  .sub{
    margin-top: 6px;
    display:flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items:center;
    font-size: 12px;
    color:#6b7280;
  }
  
  .dot{ color:#9ca3af; }
  .muted{ color:#6b7280; }
  
  .chips{
    margin-top: 10px;
    display:flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  
  .chip{
    display:inline-flex;
    align-items:center;
    height: 26px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    font-size: 11px;
    font-weight: 900;
    color:#374151;
    text-transform: capitalize;
  }
  
  .chip[data-status="published"]{
    border-color: rgba(16,185,129,.28);
    background: rgba(209,250,229,.65);
    color: #065f46;
  }
  .chip[data-status="draft"]{
    border-color: rgba(245,158,11,.30);
    background: rgba(255,237,213,.75);
    color: #92400e;
  }
  .chip[data-status="archived"]{
    border-color: rgba(107,114,128,.35);
    background: rgba(243,244,246,1);
    color: #374151;
  }
  
  /* Actions */
  .actions{
    display:flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  
  .btn{
    height: 36px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color:#111827;
    font-size: 12px;
    font-weight: 950;
    cursor: pointer;
    text-decoration: none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    transition: transform 120ms ease, background 120ms ease, border-color 120ms ease;
  }
  .btn:hover{
    background:#f9fafb;
    transform: translateY(-1px);
  }
  .btn:disabled{
    opacity: .65;
    cursor: not-allowed;
    transform: none;
  }
  .btn.danger{
    border-color: rgba(239,68,68,.25);
    color: #991b1b;
  }
  .btn.danger:hover{
    background: rgba(254,242,242,1);
  }
  
  /* Grid */
  .grid{
    display:grid;
    gap: 12px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 980px){
    .grid{
      grid-template-columns: 1.6fr 1fr;
      align-items: start;
    }
  }
  
  .col{
    display:grid;
    gap: 12px;
  }
  
  /* Text */
  .p{
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color:#374151;
    white-space: pre-line;
  }
  
  /* Meta */
  .metaGrid{
    margin-top: 14px;
    display:grid;
    gap: 10px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 820px){
    .metaGrid{
      grid-template-columns: 1fr 1fr 1fr;
    }
  }
  
  .metaItem{
    border: 1px solid #eef2f7;
    background: #fbfcfe;
    border-radius: 14px;
    padding: 10px;
  }
  
  .k{
    font-size: 10px;
    font-weight: 950;
    letter-spacing: .10em;
    text-transform: uppercase;
    color:#6b7280;
  }
  
  .v{
    margin-top: 6px;
    font-size: 13px;
    font-weight: 900;
    color:#111827;
  }
  
  /* Applicants preview */
  .miniWarn{
    font-size: 12px;
    color:#6b7280;
    border: 1px dashed #e5e7eb;
    border-radius: 12px;
    padding: 10px;
    background: #fff;
    margin-bottom: 10px;
  }
  
  .apps{
    display:grid;
    gap: 8px;
  }
  
  .appRow{
    border: 1px solid #eef2f7;
    background: #fff;
    border-radius: 14px;
    padding: 10px;
  }
  
  .appName{
    font-size: 13px;
    font-weight: 950;
    color:#111827;
  }
  
  .appMeta{
    margin-top: 4px;
    display:flex;
    align-items:center;
    gap: 8px;
    font-size: 11px;
    color:#6b7280;
  }
  
  .pill{
    display:inline-flex;
    align-items:center;
    height: 20px;
    padding: 0 8px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    font-weight: 900;
    color:#374151;
    text-transform: capitalize;
  }
  
  .sep{ color:#9ca3af; }
  
  .empty{
    font-size: 12px;
    color:#6b7280;
    padding: 6px 0;
  }
  
  /* Quick actions */
  .quick{
    display:grid;
    gap: 10px;
  }
  
  .primary{
    height: 40px;
    border-radius: 14px;
    border: 1px solid rgba(17,24,39,.14);
    background:#111827;
    color:#fff;
    font-size: 12px;
    font-weight: 950;
    cursor:pointer;
    transition: transform 120ms ease, background 120ms ease;
  }
  .primary:hover{
    background:#0b1220;
    transform: translateY(-1px);
  }
  .primary:active{ transform: translateY(0); }
  
  .ghostLink{
    height: 40px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    background:#fff;
    color:#111827;
    font-size: 12px;
    font-weight: 950;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .ghostLink:hover{ background:#f9fafb; }
  
  /* Timeline */
  .dl{
    margin: 0;
    display:grid;
    gap: 10px;
    font-size: 12px;
  }
  
  .dlRow{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 12px;
    padding: 10px;
    border-radius: 14px;
    border: 1px solid #eef2f7;
    background: #fbfcfe;
  }
  
  .dlRow dt{
    color:#6b7280;
    font-weight: 900;
  }
  
  .dlRow dd{
    margin: 0;
    color:#111827;
    font-weight: 950;
  }
  
  /* States + alerts */
  .state{
    font-size: 12px;
    color:#6b7280;
    padding: 12px 2px;
  }
  
  .alert{
    border: 1px solid rgba(239,68,68,.25);
    background: rgba(254,242,242,1);
    border-radius: 14px;
    padding: 10px 12px;
  }
  .alertTitle{
    font-size: 12px;
    font-weight: 950;
    color:#991b1b;
  }
  .alertMsg{
    margin-top: 4px;
    font-size: 12px;
    color:#7f1d1d;
  }
  </style>
  