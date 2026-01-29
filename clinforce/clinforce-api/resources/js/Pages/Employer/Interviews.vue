<!-- resources/js/Pages/Employer/Interviews.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
  import { RouterLink, useRoute } from "vue-router";
  import UiCard from "../../Components/UiCard.vue";
  import api from "@/lib/api";
  import { logout } from "@/lib/auth";
  
  /* =========================
     Sidebar + auth (UNCHANGED)
     ========================= */
  const route = useRoute();
  const sidebarOpen = ref(false);
  
  function safeParse(raw) {
    try { return raw ? JSON.parse(raw) : null; } catch { return null; }
  }
  const user = ref(safeParse(localStorage.getItem("auth_user")));
  
  function refreshUser() { user.value = safeParse(localStorage.getItem("auth_user")); }
  function onAuthChanged() { refreshUser(); }
  function onStorage(e) { if (e.key === "auth_user") refreshUser(); }
  
  onMounted(() => {
    refreshUser();
    window.addEventListener("auth:changed", onAuthChanged);
    window.addEventListener("storage", onStorage);
  });
  onBeforeUnmount(() => {
    window.removeEventListener("auth:changed", onAuthChanged);
    window.removeEventListener("storage", onStorage);
  });
  
  const displayName = computed(() => user.value?.name || user.value?.company_name || user.value?.email || "Employer");
  const roleLabel = computed(() => {
    const r = String(user.value?.role || "employer");
    if (r === "admin") return "Admin";
    if (r === "agency") return "Agency";
    return "Employer";
  });
  
  const employerMenu = computed(() => [
    { label: "Dashboard", to: { name: "employer.dashboard" } },
    { label: "My roles", to: { name: "employer.jobs" } },
    { label: "Applicants", to: { name: "applicants.list" } },
    { label: "Talent search", to: { name: "employer.talentsearch" } },
    { label: "Messages", to: { name: "employer.messages" } },
    { label: "Interviews", to: { name: "employer.interviews" } },
    { label: "Billing", to: { name: "employer.billing" } },
  ]);
  
  function isRouteActive(item) {
    const currentName = typeof route?.name === "string" ? route.name : "";
    const currentPath = typeof route?.path === "string" ? route.path : (location.pathname || "");
    const targetName = typeof item?.to?.name === "string" ? item.to.name : "";
    if (!targetName) return false;
  
    if (targetName === "employer.jobs") return currentName.startsWith("employer.jobs") || currentPath.startsWith("/employer/jobs");
    if (targetName === "applicants.list") {
      return (
        currentName.startsWith("applicants.") ||
        currentName.startsWith("employer.applicants") ||
        currentPath.startsWith("/applicants") ||
        currentPath.startsWith("/employer/applicants")
      );
    }
    return currentName === targetName;
  }
  
  async function doLogout() {
    await logout();
    sidebarOpen.value = false;
  }
  
  /* =========================
     Helpers (UNCHANGED)
     ========================= */
  function unwrap(resData) {
    return resData?.data ?? resData;
  }
  function unwrapList(resData) {
    const body = unwrap(resData);
    if (Array.isArray(body)) return body;
    if (Array.isArray(body?.data)) return body.data;
    return [];
  }
  function toText(v) { return String(v ?? "").toLowerCase(); }
  function parseDate(v) {
    const d = new Date(v || 0);
    return Number.isFinite(d.getTime()) ? d : null;
  }
  function fmtDate(d) {
    const dt = parseDate(d);
    if (!dt) return { date: "—", time: "" };
    return {
      date: dt.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "2-digit" }),
      time: dt.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" }),
    };
  }
  function toLocalInputValue(dateObj) {
    const pad = (n) => String(n).padStart(2, "0");
    const y = dateObj.getFullYear();
    const m = pad(dateObj.getMonth() + 1);
    const d = pad(dateObj.getDate());
    const hh = pad(dateObj.getHours());
    const mm = pad(dateObj.getMinutes());
    return `${y}-${m}-${d}T${hh}:${mm}`;
  }
  function fromLocalInputValue(v) {
    const d = new Date(v);
    return Number.isFinite(d.getTime()) ? d : null;
  }
  
  /* =========================
     Data: schedule (UNCHANGED)
     ========================= */
  const loading = ref(true);
  const q = ref("");
  const jobFilter = ref("all");
  const timeFilter = ref("upcoming");
  const interviews = ref([]);
  
  async function loadSchedule() {
    loading.value = true;
    try {
      const res = await api.get("/interviews");
      interviews.value = unwrapList(res.data);
    } finally {
      loading.value = false;
    }
  }
  onMounted(() => loadSchedule());
  
  const jobs = computed(() => {
    const map = new Map();
    for (const i of interviews.value) {
      const j = i?.application?.job;
      if (j?.id) map.set(String(j.id), j);
    }
    return Array.from(map.values());
  });
  
  const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    const now = new Date();
  
    return interviews.value
      .filter((i) => {
        const app = i?.application || null;
        const job = app?.job || null;
  
        if (jobFilter.value !== "all") {
          const jid = job?.id || app?.job_id;
          if (String(jid) !== String(jobFilter.value)) return false;
        }
  
        if (term) {
          const candidateName =
            app?.applicant?.name ||
            app?.user?.name ||
            app?.applicant_name ||
            app?.applicant_full_name ||
            "";
  
          const hay = [
            candidateName,
            job?.title,
            i?.mode,
            i?.status,
            i?.meeting_link,
            i?.location_text,
          ].map(toText).join(" ");
          if (!hay.includes(term)) return false;
        }
  
        const dt = parseDate(i?.scheduled_start);
        if (!dt) return timeFilter.value === "all";
  
        if (timeFilter.value === "upcoming") return dt >= now;
        if (timeFilter.value === "past") return dt < now;
        return true;
      })
      .sort((a, b) => {
        const da = parseDate(a?.scheduled_start)?.getTime() ?? 0;
        const db = parseDate(b?.scheduled_start)?.getTime() ?? 0;
        return da - db;
      });
  });
  
  /* =========================
     Modal: schedule interview (UNCHANGED)
     ========================= */
  const modalOpen = ref(false);
  const saving = ref(false);
  const formError = ref("");
  const formFieldErrors = ref({});
  
  const appsLoading = ref(false);
  const applications = ref([]);
  
  const form = ref({
    application_id: "",
    scheduled_start: "",
    scheduled_end: "",
    mode: "video",
    meeting_link: "",
    location_text: "",
  });
  
  function resetForm() {
    formError.value = "";
    formFieldErrors.value = {};
    const now = new Date();
    const start = new Date(now.getTime() + 60 * 60 * 1000);
    const end = new Date(start.getTime() + 30 * 60 * 1000);
  
    form.value = {
      application_id: "",
      scheduled_start: toLocalInputValue(start),
      scheduled_end: toLocalInputValue(end),
      mode: "video",
      meeting_link: "",
      location_text: "",
    };
  }
  
  async function loadApplications() {
    appsLoading.value = true;
    try {
      let res = null;
      try {
        res = await api.get("/applications", { params: { scope: "owned", with: "job,applicant", status: "open" } });
      } catch {
        res = await api.get("/applications", { params: { scope: "owned" } });
      }
      applications.value = unwrapList(res.data);
    } finally {
      appsLoading.value = false;
    }
  }
  
  function openScheduleModal() {
    resetForm();
    modalOpen.value = true;
    loadApplications();
  }
  
  function closeScheduleModal() {
    modalOpen.value = false;
  }
  
  const applicationOptions = computed(() => {
    return applications.value.map((a) => {
      const jobTitle = a?.job?.title || a?.job_title || "Job";
      const cand =
        a?.applicant?.name ||
        a?.user?.name ||
        a?.applicant_name ||
        a?.applicant_full_name ||
        "Candidate";
      return {
        id: a.id,
        label: `${jobTitle} — ${cand} — App #${a.id}`,
      };
    });
  });
  
  watch(
    () => form.value.mode,
    (m) => {
      if (m !== "in_person") form.value.location_text = "";
    }
  );
  
  function setFieldErrors(payload) {
    const errs = payload?.errors || payload || {};
    formFieldErrors.value = typeof errs === "object" && errs ? errs : {};
  }
  
  async function submitSchedule() {
    formError.value = "";
    formFieldErrors.value = {};
    saving.value = true;
  
    try {
      if (!form.value.application_id) {
        formError.value = "Please select an application.";
        return;
      }
  
      const start = fromLocalInputValue(form.value.scheduled_start);
      const end = fromLocalInputValue(form.value.scheduled_end);
  
      if (!start || !end) {
        formError.value = "Start and end are required.";
        return;
      }
  
      const payload = {
        scheduled_start: start.toISOString(),
        scheduled_end: end.toISOString(),
        mode: form.value.mode,
        meeting_link: form.value.meeting_link?.trim() || null,
        location_text: form.value.location_text?.trim() || null,
      };
  
      const res = await api.post(`/applications/${form.value.application_id}/interviews`, payload);
      const created = unwrap(res.data);
  
      interviews.value = [...interviews.value, created].sort((a, b) => {
        const da = parseDate(a?.scheduled_start)?.getTime() ?? 0;
        const db = parseDate(b?.scheduled_start)?.getTime() ?? 0;
        return da - db;
      });
  
      closeScheduleModal();
    } catch (e) {
      const status = e?.response?.status;
      const payload = e?.response?.data;
      if (status === 422) {
        formError.value = payload?.message || "Validation failed.";
        setFieldErrors(payload);
      } else {
        formError.value = payload?.message || "Failed to schedule interview.";
      }
    } finally {
      saving.value = false;
    }
  }
  </script>
  
  <template>
    <div class="pageBg">
      <div class="shell">
        <!-- Mobile topbar -->
        <div class="topbar">
          <button class="burger" type="button" @click="sidebarOpen = true" aria-label="Open sidebar">
            <span></span><span></span><span></span>
          </button>
  
          <div class="brand">
            <div class="logo" aria-hidden="true">C</div>
            <div class="brandText">
              <div class="brandName">Clinforce</div>
              <div class="brandSub">{{ roleLabel }}</div>
            </div>
          </div>
  
          <RouterLink class="quickLink" :to="{ name: 'employer.interviews' }">Interviews</RouterLink>
        </div>
  
        <div v-if="sidebarOpen" class="overlay" @click="sidebarOpen = false"></div>
  
        <!-- Sidebar -->
        <aside class="sidebar" :class="{ open: sidebarOpen }">
          <div class="sideTop">
            <RouterLink class="brandRow" :to="{ name: 'employer.dashboard' }" @click="sidebarOpen = false">
              <div class="logoLg" aria-hidden="true">C</div>
              <div class="brandText">
                <div class="brandName">Clinforce</div>
                <div class="brandSub">{{ roleLabel }}</div>
              </div>
            </RouterLink>
  
            <button class="closeBtn" type="button" @click="sidebarOpen = false" aria-label="Close sidebar">✕</button>
          </div>
  
          <div class="profile">
            <div class="avatarSide" aria-hidden="true">{{ String(displayName).slice(0, 1).toUpperCase() }}</div>
            <div class="profileMeta">
              <div class="profileName" :title="displayName">{{ displayName }}</div>
            </div>
          </div>
  
          <nav class="nav">
            <RouterLink
              v-for="item in employerMenu"
              :key="item.label"
              class="navItem"
              :class="{ active: isRouteActive(item) }"
              :to="item.to"
              @click="sidebarOpen = false"
            >
              <span class="label">{{ item.label }}</span>
            </RouterLink>
          </nav>
  
          <div class="sideBottom">
            <RouterLink class="miniCta" :to="{ name: 'employer.jobs.create' }" @click="sidebarOpen = false">
              + Post new role
            </RouterLink>
            <button class="logout" type="button" @click="doLogout">Logout</button>
          </div>
        </aside>
  
        <!-- Main -->
        <main class="main">
          <div class="content">
            <!-- Header -->
            <div class="head">
              <div class="titleBlock">
                <div class="eyebrow">Employer</div>
                <h1 class="h1">Interviews</h1>
                <p class="sub">Create via <span class="mono">POST /api/applications/{application}/interviews</span></p>
              </div>
  
              <div class="headActions">
                <button class="btn" type="button" :disabled="loading" @click="loadSchedule">
                  {{ loading ? "Refreshing…" : "Refresh" }}
                </button>
                <button class="btn btnPrimary" type="button" @click="openScheduleModal">
                  + Schedule
                </button>
              </div>
            </div>
  
            <!-- Filters -->
            <UiCard>
              <div class="filtersBar">
                <div class="filtersSearch">
                  <svg class="searchIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M10.5 18.5a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7" />
                    <path d="M16.7 16.7 21 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                  </svg>
                  <input v-model="q" class="search" placeholder="Search by candidate, job, mode, status…" />
                </div>
  
                <div class="filtersRight">
                  <select v-model="jobFilter" class="select">
                    <option value="all">All jobs</option>
                    <option v-for="j in jobs" :key="j.id" :value="j.id">
                      {{ j.title || `Job #${j.id}` }}
                    </option>
                  </select>
  
                  <select v-model="timeFilter" class="select">
                    <option value="upcoming">Upcoming only</option>
                    <option value="all">All</option>
                    <option value="past">Past</option>
                  </select>
                </div>
              </div>
            </UiCard>
  
            <!-- Schedule table -->
            <UiCard>
              <div class="tableHead">
                <div class="tableTitle">Schedule</div>
                <div class="tableMeta">{{ filtered.length }} item(s)</div>
              </div>
  
              <div v-if="loading" class="state">Loading…</div>
  
              <div v-else class="tableWrap">
                <table class="table">
                  <thead>
                    <tr>
                      <th class="colWhen">When</th>
                      <th class="colCandidate">Candidate</th>
                      <th class="colRole">Role</th>
                      <th class="colMode">Mode</th>
                      <th class="colStatus">Status</th>
                      <th class="colLink right">Link</th>
                    </tr>
                  </thead>
  
                  <tbody>
                    <tr v-for="i in filtered" :key="i.id">
                      <td class="colWhen">
                        <div class="cellMain">{{ fmtDate(i.scheduled_start).date }}</div>
                        <div class="cellSub">
                          {{ fmtDate(i.scheduled_start).time }} → {{ fmtDate(i.scheduled_end).time }}
                        </div>
                      </td>
  
                      <td class="colCandidate">
                        <div class="cellMain">
                          {{
                            i?.application?.applicant?.name ||
                            i?.application?.user?.name ||
                            i?.application?.applicant_name ||
                            "Candidate"
                          }}
                        </div>
                        <div class="cellSub">App #{{ i?.application?.id || i?.application_id || "—" }}</div>
                      </td>
  
                      <td class="colRole">
                        <div class="cellMain cellMainWrap">
                          {{ i?.application?.job?.title || "—" }}
                        </div>
                        <div class="cellSub">{{ i?.location_text || " " }}</div>
                      </td>
  
                      <td class="colMode">
                        <span class="badge">{{ i?.mode || "—" }}</span>
                      </td>
  
                      <td class="colStatus">
                        <span class="badge badgeSoft">{{ i?.status || "—" }}</span>
                      </td>
  
                      <td class="colLink right">
                        <a
                          v-if="i?.meeting_link"
                          class="linkBtn"
                          :href="i.meeting_link"
                          target="_blank"
                          rel="noreferrer"
                        >Open</a>
                        <span v-else class="cellSub">—</span>
                      </td>
                    </tr>
  
                    <tr v-if="!filtered.length">
                      <td colspan="6" class="emptyRow">No interviews found.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </UiCard>
          </div>
        </main>
  
        <!-- Modal -->
        <div v-if="modalOpen" class="mOverlay" @click.self="closeScheduleModal">
          <div class="modal">
            <div class="mHead">
              <div>
                <div class="mTitle">Schedule interview</div>
                <div class="mSub">POST /api/applications/{application}/interviews</div>
              </div>
              <button class="mClose" type="button" @click="closeScheduleModal">✕</button>
            </div>
  
            <div v-if="formError" class="mError">{{ formError }}</div>
  
            <div class="mBody">
              <div class="field">
                <label class="lbl">Application</label>
                <select v-model="form.application_id" class="input" :disabled="appsLoading">
                  <option value="">{{ appsLoading ? "Loading..." : "Select application..." }}</option>
                  <option v-for="o in applicationOptions" :key="o.id" :value="o.id">
                    {{ o.label }}
                  </option>
                </select>
                <div v-if="formFieldErrors?.application_id?.length" class="err">{{ formFieldErrors.application_id[0] }}</div>
              </div>
  
              <div class="row2">
                <div class="field">
                  <label class="lbl">Start</label>
                  <input v-model="form.scheduled_start" type="datetime-local" class="input" />
                  <div v-if="formFieldErrors?.scheduled_start?.length" class="err">{{ formFieldErrors.scheduled_start[0] }}</div>
                </div>
  
                <div class="field">
                  <label class="lbl">End</label>
                  <input v-model="form.scheduled_end" type="datetime-local" class="input" />
                  <div v-if="formFieldErrors?.scheduled_end?.length" class="err">{{ formFieldErrors.scheduled_end[0] }}</div>
                </div>
              </div>
  
              <div class="row2">
                <div class="field">
                  <label class="lbl">Mode</label>
                  <select v-model="form.mode" class="input">
                    <option value="video">Video (Zoom)</option>
                    <option value="in_person">In person</option>
                    <option value="phone">Phone</option>
                  </select>
                  <div v-if="formFieldErrors?.mode?.length" class="err">{{ formFieldErrors.mode[0] }}</div>
                </div>
  
                <div class="field" v-if="form.mode === 'in_person'">
                  <label class="lbl">Location</label>
                  <input v-model="form.location_text" class="input" placeholder="Clinic / address" />
                  <div v-if="formFieldErrors?.location_text?.length" class="err">{{ formFieldErrors.location_text[0] }}</div>
                </div>
  
                <div class="field" v-else>
                  <label class="lbl">Meeting link (optional)</label>
                  <input v-model="form.meeting_link" class="input" placeholder="Leave blank to auto-generate Zoom link" />
                  <div class="hint">If blank and mode=video, backend will create meeting_link.</div>
                  <div v-if="formFieldErrors?.meeting_link?.length" class="err">{{ formFieldErrors.meeting_link[0] }}</div>
                </div>
              </div>
            </div>
  
            <div class="mFoot">
              <button class="btn" type="button" @click="closeScheduleModal">Close</button>
              <button class="btn btnPrimary" type="button" :disabled="saving" @click="submitSchedule">
                {{ saving ? "Saving..." : "Save" }}
              </button>
            </div>
          </div>
        </div>
  
      </div>
    </div>
  </template>
  
  <style scoped>
  /* ===== Candidate-style page background ===== */
  .pageBg{
    min-height:100vh;
    overflow-x:hidden;
    background: linear-gradient(180deg,#fafaf9 0%,#f5f5f4 100%);
    color:#111827;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  }
  
  .shell, .shell *{ box-sizing:border-box; }
  .shell{ min-height:100vh; display:flex; width:100%; }
  
  /* ===== Main/content spacing (prevents overlap) ===== */
  .main{ flex:1; min-width:0; padding: 16px; }
  @media (max-width: 980px){ .main{ padding:12px; } }
  
  .content{
    max-width: 1280px;
    margin: 0 auto;
    padding: 8px 0;
    display:flex;
    flex-direction:column;
    gap: 12px;
  }
  
  /* ===== Header ===== */
  .head{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  .titleBlock{ min-width:0; }
  .eyebrow{ font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.14em; text-transform:uppercase; }
  .h1{ margin:6px 0 0; font-size:22px; font-weight:900; letter-spacing:-0.02em; }
  .sub{ margin:6px 0 0; font-size:12px; color:#6b7280; }
  .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
  .headActions{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
  
  /* ===== Buttons (same as candidate pages) ===== */
  .btn{
    border-radius:999px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    padding:9px 12px;
    font-size:11px;
    font-weight:800;
    color:#111827;
    cursor:pointer;
    height:40px;
  }
  .btn:disabled{ opacity:0.55; cursor:not-allowed; }
  .btnPrimary{
    background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
    border-color: rgba(17,24,39,0.55);
    color:#fff;
  }
  
  /* ===== Filters ===== */
  .filtersBar{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .filtersSearch{ position:relative; flex:1 1 520px; min-width:260px; }
  .searchIcon{ position:absolute; left:12px; top:50%; transform:translateY(-50%); width:18px; height:18px; color:#9ca3af; pointer-events:none; }
  .search{
    width:100%;
    height:42px;
    padding:0 12px 0 40px;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    font-size:12px;
    font-weight:800;
    color:#111827;
    outline:none;
  }
  .filtersRight{ display:flex; gap:12px; flex-wrap:wrap; }
  .select{
    height:42px;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    padding:0 12px;
    font-size:12px;
    font-weight:900;
    background:#fff;
    outline:none;
    width:240px;
    min-width:210px;
    max-width:100%;
  }
  
  /* ===== Table ===== */
  .tableHead{ display:flex; align-items:flex-end; justify-content:space-between; gap:10px; padding-bottom:10px; border-bottom:1px solid rgba(17,24,39,.06); margin-bottom:12px; }
  .tableTitle{ font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.10em; color:#6b7280; }
  .tableMeta{ font-size:12px; font-weight:900; color:#6b7280; }
  
  .tableWrap{ width:100%; overflow:auto; -webkit-overflow-scrolling:touch; border-radius:14px; }
  .table{ width:100%; min-width:760px; border-collapse:separate; border-spacing:0; font-size:12px; }
  .table thead th{
    text-align:left;
    font-size:11px;
    color:#6b7280;
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:.10em;
    padding:10px 12px;
    border-bottom:1px solid rgba(17,24,39,.08);
    background:#fff;
    position:sticky;
    top:0;
    z-index:1;
    white-space:nowrap;
  }
  .table tbody td{ padding:12px 12px; border-bottom:1px solid rgba(17,24,39,.06); vertical-align:top; }
  .right{ text-align:right; }
  
  .colWhen{ width:170px; }
  .colCandidate{ width:220px; }
  .colMode{ width:120px; }
  .colStatus{ width:140px; }
  .colLink{ width:110px; }
  
  .cellMain{ font-weight:900; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px; }
  .cellMainWrap{
    white-space:normal;
    overflow:hidden;
    display:-webkit-box;
    -webkit-line-clamp:2;
    -webkit-box-orient:vertical;
    max-width:520px;
  }
  .cellSub{ margin-top:2px; font-size:11px; color:#6b7280; }
  .badge{
    display:inline-flex; align-items:center;
    border-radius:999px; padding:4px 10px;
    font-size:11px; font-weight:900;
    border:1px solid rgba(229,231,235,0.95);
    background:#fff;
  }
  .badgeSoft{ background:#f9fafb; }
  .linkBtn{
    height:34px; padding:0 12px;
    border-radius:999px; border:1px solid rgba(229,231,235,0.95);
    background:#fff; color:#111827;
    font-size:11px; font-weight:900;
    text-decoration:none;
    display:inline-flex; align-items:center; justify-content:center;
  }
  .emptyRow{ padding:14px; color:#6b7280; font-size:12px; }
  .state{ padding:12px 6px; font-size:12px; color:#6b7280; }
  
  /* ===== Sidebar + mobile topbar (kept same structure, just safer layering) ===== */
  .topbar{ display:none; }
  @media (max-width: 980px){
    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      height:56px; padding:0 14px;
      border-bottom:1px solid rgba(17,24,39,.08);
      background:#fff;
      position:sticky; top:0; z-index:60;
    }
    .burger{ width:42px; height:42px; border-radius:12px; border:1px solid rgba(229,231,235,0.95); background:#fff; display:grid; place-items:center; cursor:pointer; }
    .burger span{ display:block; width:18px; height:2px; background:#111827; border-radius:999px; margin:2px 0; }
    .brand{ display:flex; align-items:center; gap:10px; min-width:0; }
    .logo{ width:34px; height:34px; border-radius:12px; background:#111827; color:#fff; display:grid; place-items:center; font-weight:900; font-size:12px; }
    .brandText{ min-width:0; }
    .brandName{ font-weight:900; font-size:12px; line-height:1.1; }
    .brandSub{ font-size:11px; color:#6b7280; font-weight:900; line-height:1.1; }
    .quickLink{ height:36px; padding:0 12px; border-radius:999px; border:1px solid rgba(229,231,235,0.95); background:#fff; color:#111827; font-size:12px; font-weight:900; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
  }
  
  .overlay{ display:none; }
  @media (max-width: 980px){
    .overlay{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:70; }
  }
  
  .sidebar{
    width:280px; min-height:100vh;
    position:sticky; top:0;
    border-right:1px solid rgba(17,24,39,.08);
    background:#fff;
    display:flex; flex-direction:column;
    padding:14px; gap:12px;
    z-index:50;
  }
  @media (max-width: 980px){
    .sidebar{
      position:fixed; top:0; left:0; height:100vh;
      transform:translateX(-110%);
      transition:transform 160ms ease;
      box-shadow:0 18px 40px rgba(0,0,0,.18);
      z-index:80;
    }
    .sidebar.open{ transform:translateX(0); }
  }
  .sideTop{ display:flex; align-items:center; justify-content:space-between; gap:10px; }
  .brandRow{ display:flex; align-items:center; gap:10px; text-decoration:none; color:inherit; min-width:0; }
  .logoLg{ width:40px; height:40px; border-radius:14px; background:#111827; color:#fff; display:grid; place-items:center; font-weight:900; font-size:12px; }
  .closeBtn{ display:none; }
  @media (max-width: 980px){
    .closeBtn{ display:inline-flex; width:40px; height:40px; border-radius:12px; border:1px solid rgba(229,231,235,0.95); background:#fff; cursor:pointer; font-weight:900; }
  }
  .profile{ display:flex; align-items:center; gap:10px; border:1px solid rgba(17,24,39,.08); border-radius:16px; padding:10px; background:rgba(17,24,39,.02); }
  .avatarSide{ width:40px; height:40px; border-radius:14px; background:#111827; color:#fff; display:grid; place-items:center; font-weight:900; }
  .profileName{ font-size:12px; font-weight:900; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:170px; }
  .nav{ display:flex; flex-direction:column; gap:6px; padding-top:4px; }
  .navItem{ display:flex; align-items:center; padding:10px; border-radius:14px; text-decoration:none; color:#111827; border:1px solid transparent; font-size:12px; font-weight:900; transition:background 120ms ease, border-color 120ms ease, transform 120ms ease; }
  .navItem:hover{ background:rgba(17,24,39,.03); border-color:rgba(17,24,39,.06); transform:translateY(-1px); }
  .navItem.active{ background:rgba(17,24,39,.06); border-color:rgba(17,24,39,.12); }
  .sideBottom{ margin-top:auto; display:flex; flex-direction:column; gap:10px; padding-top:10px; border-top:1px solid rgba(17,24,39,.08); }
  .miniCta{ height:40px; border-radius:999px; background:#111827; color:#fff; text-decoration:none; font-size:12px; font-weight:900; display:inline-flex; align-items:center; justify-content:center; }
  .logout{ height:40px; border-radius:999px; border:1px solid rgba(239,68,68,.25); background:#fff; color:#991b1b; font-size:12px; font-weight:900; cursor:pointer; }
  
  /* ===== Modal ===== */
  .mOverlay{ position:fixed; inset:0; background:rgba(0,0,0,.35); display:grid; place-items:center; z-index:120; padding:16px; }
  .modal{
    width:min(860px, 100%);
    background:#fff;
    border:1px solid rgba(229,231,235,0.92);
    border-radius:18px;
    box-shadow: 0 18px 60px rgba(17,24,39,.25);
    overflow:hidden;
  }
  .mHead{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid rgba(17,24,39,.08); }
  .mTitle{ font-size:13px; font-weight:900; }
  .mSub{ margin-top:3px; font-size:11px; color:#6b7280; }
  .mClose{ width:40px; height:40px; border-radius:12px; border:1px solid rgba(229,231,235,0.95); background:#fff; cursor:pointer; font-weight:900; }
  .mError{ margin:12px 16px 0; padding:10px 12px; border-radius:14px; border:1px solid rgba(239,68,68,.25); background:rgba(254,226,226,.55); color:#991b1b; font-size:12px; font-weight:900; }
  .mBody{ padding:14px 16px; display:grid; gap:12px; }
  .field{ display:grid; gap:6px; }
  .lbl{ font-size:11px; font-weight:900; color:#6b7280; text-transform:uppercase; letter-spacing:.08em; }
  .input{
    height:42px; border-radius:14px; border:1px solid rgba(209,213,219,0.95);
    padding:0 12px; outline:none; font-size:12px; font-weight:900; background:#fff;
  }
  .row2{ display:grid; gap:12px; grid-template-columns: 1fr 1fr; }
  @media (max-width: 780px){ .row2{ grid-template-columns:1fr; } }
  .hint{ font-size:11px; color:#6b7280; }
  .err{ font-size:11px; color:#b91c1c; font-weight:900; }
  .mFoot{ padding:14px 16px; border-top:1px solid rgba(17,24,39,.08); display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap; }
  </style>