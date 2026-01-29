<!-- resources/js/Pages/Employer/Dashboard.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from "vue";
  import { RouterLink, useRoute } from "vue-router";
  import UiCard from "../../Components/UiCard.vue";
  import { http } from "../../lib/http";
  import { logout } from "@/lib/auth";
  
  /** =======================
   *  Sidebar + Shell
   *  ======================= */
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
    return currentName === targetName || currentPath.includes(targetName.replaceAll(".", "/"));
  }
  
  async function doLogout() {
    await logout();
    sidebarOpen.value = false;
  }
  
  /** =======================
   *  Dashboard Data (from APIs with safe fallbacks)
   *  ======================= */
  const loading = ref(true);
  const error = ref("");
  
  const range = ref("30"); // 7 | 30 | 90
  const jobs = ref([]);
  const apps = ref([]);
  
  // derived “event” list for charts
  const applicantEvents = ref([]); // { dayKey:'YYYY-MM-DD', ts:Date, status:'new'|'review'|'shortlisted'|'rejected'|'hired' }
  const interviewEvents = ref([]); // { dayKey:'YYYY-MM-DD', ts:Date }
  
  function safeDate(val) {
    const d = new Date(val || 0);
    return Number.isFinite(d.getTime()) ? d : null;
  }
  function dayKey(d) {
    if (!d) return null;
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const da = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${da}`;
  }
  function clamp(n, a, b) {
    return Math.max(a, Math.min(b, n));
  }
  
  function normalizeJobs(payload) {
    const root = payload?.data ?? payload;
    if (Array.isArray(root)) return root;
    if (Array.isArray(root?.data)) return root.data;
    if (Array.isArray(root?.items)) return root.items;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
  }
  function normalizeApps(payload) {
    const root = payload?.data ?? payload;
    if (Array.isArray(root)) return root;
    if (Array.isArray(root?.data)) return root.data;
    if (Array.isArray(root?.items)) return root.items;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
  }
  
  function normalizeJobStatus(r) {
    const raw = String(r?.status || "").toLowerCase().trim();
    if (raw === "published") return "open";
    if (raw === "archived") return "closed";
    if (raw === "draft") return "draft";
    if (r?.is_published === true) return "open";
    return "draft";
  }
  
  function normalizeAppStage(a) {
    // adjust to your backend fields if you have them
    const s = String(a?.status || a?.stage || a?.application_status || "").toLowerCase().trim();
    if (["hired", "accepted"].includes(s)) return "hired";
    if (["rejected", "declined"].includes(s)) return "rejected";
    if (["shortlisted", "shortlist"].includes(s)) return "shortlisted";
    if (["review", "reviewing", "screening"].includes(s)) return "review";
    // default
    return "new";
  }
  
  function extractInterviewEvents(appList) {
    const out = [];
    for (const a of appList) {
      const list = Array.isArray(a?.interviews) ? a.interviews : [];
      for (const i of list) {
        const d = safeDate(i?.scheduled_at || i?.start_at || i?.created_at);
        if (!d) continue;
        out.push({ ts: d, dayKey: dayKey(d) });
      }
    }
    return out;
  }
  
  function extractApplicantEvents(appList) {
    const out = [];
    for (const a of appList) {
      const d = safeDate(a?.created_at || a?.applied_at || a?.submitted_at);
      if (!d) continue;
      out.push({ ts: d, dayKey: dayKey(d), status: normalizeAppStage(a) });
    }
    return out;
  }
  
  async function load() {
    loading.value = true;
    error.value = "";
    try {
      // jobs
      const jr = await http.get("/jobs", { params: { scope: "owned" } }).catch(() => http.get("/jobs"));
      jobs.value = normalizeJobs(jr);
  
      // applications
      const ar = await http.get("/applications", { params: { scope: "owned" } });
      apps.value = normalizeApps(ar);
  
      applicantEvents.value = extractApplicantEvents(apps.value);
      interviewEvents.value = extractInterviewEvents(apps.value);
    } catch (e) {
      const code = e?.response?.status;
      error.value =
        e?.response?.data?.message ||
        (code ? `Request failed (${code})` : "") ||
        e?.message ||
        "Failed to load dashboard.";
      jobs.value = [];
      apps.value = [];
      applicantEvents.value = [];
      interviewEvents.value = [];
    } finally {
      loading.value = false;
    }
  }
  
  onMounted(() => load());
  
  /** =======================
   *  Range + Chart Buckets
   *  ======================= */
  const rangeDays = computed(() => {
    const n = Number(range.value);
    return Number.isFinite(n) ? clamp(n, 1, 365) : 30;
  });
  
  function startOfDay(d) {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
  }
  
  const windowDays = computed(() => {
    const days = rangeDays.value;
    const end = startOfDay(new Date());
    const start = new Date(end.getTime() - (days - 1) * 86400000);
    const labels = [];
    for (let i = 0; i < days; i++) {
      const d = new Date(start.getTime() + i * 86400000);
      labels.push({
        ts: d,
        key: dayKey(d),
        short: d.toLocaleDateString(undefined, { month: "short", day: "2-digit" }),
      });
    }
    return labels;
  });
  
  const applicantsSeries = computed(() => {
    const map = new Map();
    for (const ev of applicantEvents.value) {
      map.set(ev.dayKey, (map.get(ev.dayKey) || 0) + 1);
    }
    const points = windowDays.value.map((d) => map.get(d.key) || 0);
    return points;
  });
  
  const interviewsSeries = computed(() => {
    const map = new Map();
    for (const ev of interviewEvents.value) {
      map.set(ev.dayKey, (map.get(ev.dayKey) || 0) + 1);
    }
    return windowDays.value.map((d) => map.get(d.key) || 0);
  });
  
  const maxApplicants = computed(() => Math.max(1, ...applicantsSeries.value));
  const maxInterviews = computed(() => Math.max(1, ...interviewsSeries.value));
  
  function sparkPath(points, width = 520, height = 120, pad = 10) {
    const n = points.length;
    if (!n) return "";
    const max = Math.max(1, ...points);
    const innerW = width - pad * 2;
    const innerH = height - pad * 2;
  
    const x = (i) => pad + (n === 1 ? innerW / 2 : (i * innerW) / (n - 1));
    const y = (v) => pad + (innerH - (v / max) * innerH);
  
    let d = "";
    for (let i = 0; i < n; i++) {
      const xi = x(i);
      const yi = y(points[i]);
      d += i === 0 ? `M ${xi} ${yi}` : ` L ${xi} ${yi}`;
    }
    return d;
  }
  
  const applicantsPath = computed(() => sparkPath(applicantsSeries.value));
  const interviewsPath = computed(() => sparkPath(interviewsSeries.value));
  
  const kpis = computed(() => {
    const totalJobs = jobs.value.length;
    const openJobs = jobs.value.filter((j) => normalizeJobStatus(j) === "open").length;
    const draftJobs = jobs.value.filter((j) => normalizeJobStatus(j) === "draft").length;
  
    const totalApps = apps.value.length;
  
    const stages = { new: 0, review: 0, shortlisted: 0, rejected: 0, hired: 0 };
    for (const a of apps.value) stages[normalizeAppStage(a)] = (stages[normalizeAppStage(a)] || 0) + 1;
  
    const now = new Date();
    const in7 = new Date(now.getTime() + 7 * 86400000);
    let upcomingInterviews = 0;
    for (const ev of interviewEvents.value) {
      if (ev.ts >= now && ev.ts <= in7) upcomingInterviews++;
    }
  
    return {
      totalJobs,
      openJobs,
      draftJobs,
      totalApps,
      stages,
      upcomingInterviews,
    };
  });
  
  const topRoles = computed(() => {
    // counts by job title from applications
    const m = new Map();
    for (const a of apps.value) {
      const title = a?.job?.title || a?.job_title || a?.job?.role_title || "Untitled role";
      m.set(title, (m.get(title) || 0) + 1);
    }
    return Array.from(m.entries())
      .map(([title, count]) => ({ title, count }))
      .sort((a, b) => b.count - a.count)
      .slice(0, 6);
  });
  
  const stageBars = computed(() => {
    const s = kpis.value.stages;
    const total = Math.max(1, Object.values(s).reduce((a, b) => a + b, 0));
    const rows = [
      { key: "new", label: "New", val: s.new || 0 },
      { key: "review", label: "In review", val: s.review || 0 },
      { key: "shortlisted", label: "Shortlisted", val: s.shortlisted || 0 },
      { key: "rejected", label: "Rejected", val: s.rejected || 0 },
      { key: "hired", label: "Hired", val: s.hired || 0 },
    ];
    return rows.map((r) => ({ ...r, pct: Math.round((r.val / total) * 100) }));
  });
  </script>
  
  <template>
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
  
        <RouterLink class="quickLink" :to="{ name: 'employer.jobs' }">Roles</RouterLink>
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
            <div class="profileRole">{{ roleLabel }}</div>
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
        <div class="page">
          <!-- Header -->
          <div class="head">
            <div class="titleBlock">
              <div class="eyebrow">Employer</div>
              <h1 class="h1">Dashboard</h1>
              <p class="sub">Overview of roles, applicants, and pipeline activity.</p>
            </div>
  
            <div class="headActions">
              <div class="rangeWrap">
                <div class="rangeLabel">Range</div>
                <select v-model="range" class="select">
                  <option value="7">Last 7 days</option>
                  <option value="30">Last 30 days</option>
                  <option value="90">Last 90 days</option>
                </select>
              </div>
  
              <button class="ghostBtn" type="button" :disabled="loading" @click="load">
                {{ loading ? "Refreshing…" : "Refresh" }}
              </button>
            </div>
          </div>
  
          <!-- Error -->
          <div v-if="error" class="alert" role="alert">
            <div class="alertLeft">
              <div class="alertTitle">Action required</div>
              <div class="alertMsg">{{ error }}</div>
            </div>
            <button class="alertBtn" type="button" @click="load">Retry</button>
          </div>
  
          <!-- KPIs -->
          <div class="kpiGrid">
            <UiCard>
              <div class="kpi">
                <div class="kpiTop">
                  <div class="kpiLabel">Open roles</div>
                  <RouterLink class="kpiLink" :to="{ name: 'employer.jobs' }">View</RouterLink>
                </div>
                <div class="kpiVal">{{ kpis.openJobs }}</div>
                <div class="kpiSub">Draft: {{ kpis.draftJobs }} • Total: {{ kpis.totalJobs }}</div>
              </div>
            </UiCard>
  
            <UiCard>
              <div class="kpi">
                <div class="kpiTop">
                  <div class="kpiLabel">Applicants</div>
                  <RouterLink class="kpiLink" :to="{ name: 'applicants.list' }">View</RouterLink>
                </div>
                <div class="kpiVal">{{ kpis.totalApps }}</div>
                <div class="kpiSub">New: {{ kpis.stages.new }} • Shortlisted: {{ kpis.stages.shortlisted }}</div>
              </div>
            </UiCard>
  
            <UiCard>
              <div class="kpi">
                <div class="kpiTop">
                  <div class="kpiLabel">Upcoming interviews</div>
                  <RouterLink class="kpiLink" :to="{ name: 'employer.interviews' }">View</RouterLink>
                </div>
                <div class="kpiVal">{{ kpis.upcomingInterviews }}</div>
                <div class="kpiSub">Next 7 days (from interviews[] in applications)</div>
              </div>
            </UiCard>
  
            <UiCard>
              <div class="kpi">
                <div class="kpiTop">
                  <div class="kpiLabel">Time window</div>
                  <div class="pill">{{ rangeDays }} days</div>
                </div>
                <div class="kpiVal">{{ applicantsSeries.reduce((a,b)=>a+b,0) }}</div>
                <div class="kpiSub">New applications in selected range</div>
              </div>
            </UiCard>
          </div>
  
          <!-- Charts grid -->
          <div class="chartGrid">
            <!-- Applicants trend -->
            <UiCard>
              <div class="cardHead">
                <div class="cardTitle">Applications trend</div>
                <div class="cardHint">Daily counts (last {{ rangeDays }} days)</div>
              </div>
  
              <div v-if="loading" class="state">Loading…</div>
              <div v-else class="chartBox">
                <svg class="spark" viewBox="0 0 520 120" preserveAspectRatio="none" aria-hidden="true">
                  <path class="sparkLine" :d="applicantsPath" />
                </svg>
  
                <div class="bars">
                  <div
                    v-for="(v, idx) in applicantsSeries"
                    :key="idx"
                    class="bar"
                    :title="`${windowDays[idx]?.short}: ${v}`"
                  >
                    <div class="barFill" :style="{ height: `${Math.round((v / maxApplicants) * 100)}%` }"></div>
                  </div>
                </div>
  
                <div class="axis">
                  <div class="axisLeft">0</div>
                  <div class="axisRight">{{ maxApplicants }}</div>
                </div>
              </div>
            </UiCard>
  
            <!-- Interviews trend -->
            <UiCard>
              <div class="cardHead">
                <div class="cardTitle">Interviews scheduled</div>
                <div class="cardHint">Daily counts (last {{ rangeDays }} days)</div>
              </div>
  
              <div v-if="loading" class="state">Loading…</div>
              <div v-else class="chartBox">
                <svg class="spark" viewBox="0 0 520 120" preserveAspectRatio="none" aria-hidden="true">
                  <path class="sparkLine" :d="interviewsPath" />
                </svg>
  
                <div class="bars">
                  <div
                    v-for="(v, idx) in interviewsSeries"
                    :key="idx"
                    class="bar"
                    :title="`${windowDays[idx]?.short}: ${v}`"
                  >
                    <div class="barFill soft" :style="{ height: `${Math.round((v / maxInterviews) * 100)}%` }"></div>
                  </div>
                </div>
  
                <div class="axis">
                  <div class="axisLeft">0</div>
                  <div class="axisRight">{{ maxInterviews }}</div>
                </div>
              </div>
            </UiCard>
  
            <!-- Pipeline breakdown -->
            <UiCard>
              <div class="cardHead">
                <div class="cardTitle">Pipeline breakdown</div>
                <div class="cardHint">By current stage</div>
              </div>
  
              <div v-if="loading" class="state">Loading…</div>
              <div v-else class="pipe">
                <div v-for="r in stageBars" :key="r.key" class="pipeRow">
                  <div class="pipeLeft">
                    <div class="pipeLabel">{{ r.label }}</div>
                    <div class="pipeMeta">{{ r.val }} • {{ r.pct }}%</div>
                  </div>
                  <div class="pipeTrack" :title="`${r.label}: ${r.val}`">
                    <div class="pipeFill" :style="{ width: `${r.pct}%` }"></div>
                  </div>
                </div>
              </div>
            </UiCard>
  
            <!-- Top roles -->
            <UiCard>
              <div class="cardHead">
                <div class="cardTitle">Top roles by applicants</div>
                <div class="cardHint">Based on applications payload</div>
              </div>
  
              <div v-if="loading" class="state">Loading…</div>
              <div v-else class="topList">
                <div v-if="!topRoles.length" class="emptySmall">No data yet.</div>
  
                <div v-for="r in topRoles" :key="r.title" class="topRow">
                  <div class="topTitle" :title="r.title">{{ r.title }}</div>
                  <div class="topCount">{{ r.count }}</div>
                </div>
  
                <RouterLink class="miniLink" :to="{ name: 'employer.jobs' }">Manage roles →</RouterLink>
              </div>
            </UiCard>
          </div>
  
          <!-- Quick actions + Recent activity -->
          <div class="bottomGrid">
            <UiCard>
              <div class="cardHead">
                <div class="cardTitle">Quick actions</div>
                <div class="cardHint">Common workflows</div>
              </div>
  
              <div class="qa">
                <RouterLink class="qaBtn primary" :to="{ name: 'employer.jobs.create' }">+ Post new role</RouterLink>
                <RouterLink class="qaBtn" :to="{ name: 'applicants.list' }">Review applicants</RouterLink>
                <RouterLink class="qaBtn" :to="{ name: 'employer.interviews' }">Schedule interviews</RouterLink>
                <RouterLink class="qaBtn" :to="{ name: 'employer.messages' }">Open messages</RouterLink>
              </div>
            </UiCard>
  
            <UiCard>
              <div class="cardHead">
                <div class="cardTitle">Recent activity</div>
                <div class="cardHint">Latest applicants (fallback from apps)</div>
              </div>
  
              <div v-if="loading" class="state">Loading…</div>
              <div v-else class="recent">
                <div v-if="!apps.length" class="emptySmall">No applicants yet.</div>
  
                <div
                  v-for="a in apps.slice(0, 6)"
                  :key="a.id || (a.user_id + '-' + a.created_at)"
                  class="recentRow"
                >
                  <div class="recentAvatar">{{ String(a?.applicant_name || a?.user?.name || "C").slice(0,1).toUpperCase() }}</div>
                  <div class="recentMeta">
                    <div class="recentName">{{ a?.applicant_name || a?.user?.name || "Candidate" }}</div>
                    <div class="recentSub">
                      {{ a?.job?.title || a?.job_title || "Role" }} • {{ normalizeAppStage(a) }}
                    </div>
                  </div>
                  <div class="recentTime">
                    {{ safeDate(a?.created_at)?.toLocaleDateString() || "—" }}
                  </div>
                </div>
  
                <RouterLink class="miniLink" :to="{ name: 'applicants.list' }">View all applicants →</RouterLink>
              </div>
            </UiCard>
          </div>
        </div>
      </main>
    </div>
  </template>
  
  <style scoped>
  .shell, .shell * { box-sizing: border-box; }
  
  /* ===== shell ===== */
  .shell{ min-height: 100vh; background: #f7f7f8; }
  @media (min-width: 981px){
    .shell{ display:flex; }
    .main{ flex:1; min-width:0; padding: 16px; }
  }
  @media (max-width: 980px){
    .main{ padding: 12px; }
  }
  
  /* ===== mobile topbar ===== */
  .topbar{ display:none; }
  @media (max-width: 980px){
    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      height:56px; padding: 0 14px; border-bottom: 1px solid rgba(17,24,39,.08);
      background:#fff; position: sticky; top: 0; z-index: 40;
    }
    .burger{
      width:42px; height:42px; border-radius:12px; border:1px solid #e5e7eb;
      background:#fff; display:grid; place-items:center; cursor:pointer;
    }
    .burger span{ display:block; width:18px; height:2px; background:#111827; border-radius: 999px; margin: 2px 0; }
    .brand{ display:flex; align-items:center; gap:10px; min-width:0; }
    .logo{
      width:34px; height:34px; border-radius:12px;
      background:#111827; color:#fff; display:grid; place-items:center;
      font-weight:950; font-size:12px;
    }
    .brandText{ min-width:0; }
    .brandName{ font-weight:950; font-size:12px; color:#111827; line-height: 1.1; }
    .brandSub{ font-size:11px; color:#6b7280; font-weight:900; line-height: 1.1; }
    .quickLink{
      height:36px; padding: 0 12px; border-radius:999px;
      border:1px solid #e5e7eb; background:#fff; color:#111827;
      font-size:12px; font-weight:950; text-decoration:none;
      display:inline-flex; align-items:center; justify-content:center;
      white-space: nowrap;
    }
    .quickLink:hover{ background:#f9fafb; }
  }
  
  /* overlay */
  .overlay{ display:none; }
  @media (max-width: 980px){
    .overlay{
      display:block;
      position:fixed;
      inset:0;
      background: rgba(0,0,0,.35);
      z-index: 45;
    }
  }
  
  /* ===== sidebar ===== */
  .sidebar{
    width: 280px;
    min-height: 100vh;
    position: sticky;
    top: 0;
    border-right: 1px solid rgba(17,24,39,.08);
    background: #fff;
    display:flex;
    flex-direction: column;
    padding: 14px;
    gap: 12px;
    z-index: 50;
  }
  @media (max-width: 980px){
    .sidebar{
      position: fixed;
      top: 0;
      left: 0;
      height: 100vh;
      transform: translateX(-110%);
      transition: transform 160ms ease;
      box-shadow: 0 18px 40px rgba(0,0,0,.18);
    }
    .sidebar.open{ transform: translateX(0); }
  }
  .sideTop{ display:flex; align-items:center; justify-content:space-between; gap: 10px; }
  .brandRow{ display:flex; align-items:center; gap: 10px; text-decoration:none; color: inherit; min-width:0; }
  .logoLg{
    width:40px; height:40px; border-radius:14px;
    background:#111827; color:#fff; display:grid; place-items:center;
    font-weight:950; font-size:12px; border: 1px solid rgba(17,24,39,.16);
  }
  .closeBtn{ display:none; }
  @media (max-width: 980px){
    .closeBtn{
      display:inline-flex; align-items:center; justify-content:center;
      width:40px; height:40px; border-radius: 12px;
      border: 1px solid #e5e7eb; background:#fff; cursor:pointer;
      color:#111827; font-weight:900;
    }
  }
  .profile{
    display:flex; align-items:center; gap: 10px;
    border: 1px solid rgba(17,24,39,.08);
    border-radius: 16px;
    padding: 10px;
    background: rgba(17,24,39,.02);
  }
  .avatarSide{
    width:40px; height:40px; border-radius:14px;
    background:#111827; color:#fff; display:grid; place-items:center;
    font-weight:950;
  }
  .profileMeta{ min-width:0; }
  .profileName{
    font-size:12px; font-weight:950; color:#111827;
    white-space: nowrap; overflow:hidden; text-overflow: ellipsis; max-width: 170px;
  }
  .profileRole{ margin-top:2px; font-size:11px; color:#6b7280; font-weight:900; }
  
  .nav{ display:flex; flex-direction: column; gap: 6px; padding-top: 4px; }
  .navItem{
    display:flex; align-items:center;
    padding: 10px 10px;
    border-radius: 14px;
    text-decoration:none;
    color:#111827;
    border: 1px solid transparent;
    transition: background 120ms ease, border-color 120ms ease, transform 120ms ease;
    font-size: 12px;
    font-weight: 950;
  }
  .navItem:hover{
    background: rgba(17,24,39,.03);
    border-color: rgba(17,24,39,.06);
    transform: translateY(-1px);
  }
  .navItem.active{
    background: rgba(17,24,39,.06);
    border-color: rgba(17,24,39,.12);
  }
  
  .sideBottom{
    margin-top: auto;
    display:flex;
    flex-direction: column;
    gap: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(17,24,39,.08);
  }
  .miniCta{
    height: 40px;
    border-radius: 999px;
    background:#111827;
    color:#fff;
    text-decoration:none;
    font-size:12px;
    font-weight:950;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border: 1px solid rgba(17,24,39,.16);
  }
  .miniCta:hover{ background:#0b1220; }
  .logout{
    height: 40px;
    border-radius: 999px;
    border: 1px solid rgba(239,68,68,.25);
    background:#fff;
    color:#991b1b;
    font-size:12px;
    font-weight:950;
    cursor:pointer;
  }
  .logout:hover{ background: rgba(254,226,226,.65); }
  
  /* ===== page ===== */
  .page{
    max-width: 1152px;
    margin: 0 auto;
    padding: 24px 16px;
    display:grid;
    gap: 14px;
  }
  @media (min-width: 981px){ .page{ padding: 8px 0; } }
  
  .head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .titleBlock{ min-width:0; }
  .eyebrow{
    font-size: 11px;
    font-weight: 950;
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
  }
  .sub{ margin: 6px 0 0; font-size: 12px; color:#6b7280; }
  
  .headActions{
    display:flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items:flex-end;
  }
  .rangeWrap{ display:grid; gap: 6px; }
  .rangeLabel{ font-size: 11px; color:#6b7280; font-weight: 950; letter-spacing:.08em; text-transform: uppercase; }
  .select{
    height: 40px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 900;
    color:#111827;
    background:#fff;
    outline:none;
  }
  .select:focus{ border-color:#d1d5db; box-shadow: 0 0 0 4px rgba(17,24,39,.08); }
  
  .ghostBtn{
    height: 40px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background:#fff;
    color:#111827;
    font-size: 12px;
    font-weight: 950;
    cursor:pointer;
  }
  .ghostBtn:hover{ background:#f9fafb; }
  .ghostBtn:disabled{ opacity:.65; cursor:not-allowed; }
  
  /* Alert */
  .alert{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
    border: 1px solid rgba(239,68,68,.25);
    background: rgba(254,242,242,1);
    border-radius: 16px;
    padding: 12px;
  }
  .alertTitle{ font-size:12px; font-weight:950; color:#991b1b; }
  .alertMsg{ margin-top:4px; font-size:12px; color:#7f1d1d; word-break: break-word; }
  .alertBtn{
    height: 34px; padding: 0 12px; border-radius: 999px;
    border: 1px solid rgba(239,68,68,.25);
    background:#fff; color:#991b1b; font-size:12px; font-weight:950;
    cursor:pointer;
  }
  .alertBtn:hover{ background: rgba(254,226,226,.7); }
  
  /* KPI grid */
  .kpiGrid{
    display:grid;
    gap: 12px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 780px){
    .kpiGrid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (min-width: 1100px){
    .kpiGrid{ grid-template-columns: repeat(4, minmax(0, 1fr)); }
  }
  .kpi{ display:grid; gap: 8px; }
  .kpiTop{ display:flex; align-items:center; justify-content:space-between; gap: 10px; }
  .kpiLabel{ font-size: 12px; color:#6b7280; font-weight: 950; }
  .kpiVal{ font-size: 28px; font-weight: 950; color:#111827; letter-spacing:-0.02em; }
  .kpiSub{ font-size: 12px; color:#6b7280; font-weight: 900; }
  .kpiLink{
    font-size: 12px;
    font-weight: 950;
    text-decoration:none;
    color:#111827;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid rgba(17,24,39,.10);
    background: rgba(17,24,39,.02);
  }
  .kpiLink:hover{ background: rgba(17,24,39,.04); }
  .pill{
    font-size: 11px;
    font-weight: 950;
    color:#111827;
    border: 1px solid rgba(17,24,39,.10);
    background: rgba(17,24,39,.02);
    border-radius: 999px;
    padding: 6px 10px;
    white-space: nowrap;
  }
  
  /* Charts grid */
  .chartGrid{
    display:grid;
    gap: 12px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 980px){
    .chartGrid{ grid-template-columns: 1fr 1fr; }
  }
  .cardHead{ display:flex; align-items:flex-end; justify-content:space-between; gap: 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(17,24,39,.06); margin-bottom: 12px; }
  .cardTitle{ font-size: 13px; font-weight: 950; color:#111827; }
  .cardHint{ font-size: 11px; color:#6b7280; font-weight: 900; }
  
  .state{ padding: 12px 6px; font-size: 12px; color:#6b7280; }
  
  /* Chart box */
  .chartBox{ display:grid; gap: 10px; }
  .spark{
    width: 100%;
    height: 120px;
    display:block;
  }
  .sparkLine{
    fill: none;
    stroke: currentColor;
    stroke-width: 2.2;
    opacity: .9;
  }
  .bars{
    display:grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    gap: 6px;
    height: 90px;
    align-items:end;
  }
  .bar{
    border-radius: 10px;
    border: 1px solid rgba(17,24,39,.08);
    background: rgba(17,24,39,.02);
    overflow:hidden;
    min-width: 0;
  }
  .barFill{
    width: 100%;
    height: 0%;
    background: #111827;
    border-radius: 10px;
  }
  .barFill.soft{ opacity: .7; }
  
  .axis{
    display:flex;
    align-items:center;
    justify-content:space-between;
    font-size: 11px;
    color:#6b7280;
    font-weight: 900;
  }
  
  /* Pipeline */
  .pipe{ display:grid; gap: 10px; }
  .pipeRow{ display:grid; gap: 6px; }
  .pipeLeft{ display:flex; align-items:center; justify-content:space-between; gap: 10px; }
  .pipeLabel{ font-size: 12px; font-weight: 950; color:#111827; }
  .pipeMeta{ font-size: 11px; color:#6b7280; font-weight: 900; }
  .pipeTrack{
    height: 12px;
    border-radius: 999px;
    border: 1px solid rgba(17,24,39,.08);
    background: rgba(17,24,39,.02);
    overflow:hidden;
  }
  .pipeFill{
    height: 100%;
    width: 0%;
    background:#111827;
    border-radius: 999px;
  }
  
  /* Top roles */
  .topList{ display:grid; gap: 10px; }
  .topRow{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid rgba(17,24,39,.08);
    background:#fff;
  }
  .topTitle{
    font-size: 12px;
    font-weight: 950;
    color:#111827;
    white-space: nowrap;
    overflow:hidden;
    text-overflow: ellipsis;
    min-width:0;
  }
  .topCount{
    font-size: 12px;
    font-weight: 950;
    color:#111827;
    border: 1px solid rgba(17,24,39,.10);
    background: rgba(17,24,39,.02);
    border-radius: 999px;
    padding: 6px 10px;
    flex: 0 0 auto;
  }
  .miniLink{
    margin-top: 2px;
    font-size: 12px;
    font-weight: 950;
    text-decoration:none;
    color:#111827;
    width: fit-content;
    padding: 8px 10px;
    border-radius: 999px;
    border: 1px solid rgba(17,24,39,.10);
    background: rgba(17,24,39,.02);
  }
  .miniLink:hover{ background: rgba(17,24,39,.04); }
  
  /* Bottom grid */
  .bottomGrid{
    display:grid;
    gap: 12px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 980px){
    .bottomGrid{ grid-template-columns: 1fr 1fr; }
  }
  
  /* Quick actions */
  .qa{
    display:grid;
    gap: 10px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 520px){
    .qa{ grid-template-columns: 1fr 1fr; }
  }
  .qaBtn{
    height: 44px;
    border-radius: 16px;
    border: 1px solid rgba(17,24,39,.10);
    background:#fff;
    color:#111827;
    text-decoration:none;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size: 12px;
    font-weight: 950;
    transition: transform 120ms ease, background 120ms ease, border-color 120ms ease;
  }
  .qaBtn:hover{ background: rgba(17,24,39,.02); transform: translateY(-1px); }
  .qaBtn.primary{
    background:#111827;
    color:#fff;
    border-color: rgba(17,24,39,.16);
  }
  .qaBtn.primary:hover{ background:#0b1220; }
  
  /* Recent */
  .recent{ display:grid; gap: 10px; }
  .recentRow{
    display:flex;
    align-items:center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid rgba(17,24,39,.08);
    background:#fff;
  }
  .recentAvatar{
    width: 34px; height: 34px; border-radius: 14px;
    background:#111827; color:#fff; display:grid; place-items:center;
    font-weight: 950; font-size: 12px;
    border: 1px solid rgba(17,24,39,.16);
    flex: 0 0 auto;
  }
  .recentMeta{ min-width:0; flex:1; display:grid; gap: 2px; }
  .recentName{
    font-size: 12px; font-weight: 950; color:#111827;
    white-space: nowrap; overflow:hidden; text-overflow: ellipsis;
  }
  .recentSub{
    font-size: 11px; color:#6b7280; font-weight: 900;
    white-space: nowrap; overflow:hidden; text-overflow: ellipsis;
  }
  .recentTime{
    font-size: 11px; color:#6b7280; font-weight: 900; white-space: nowrap;
    flex: 0 0 auto;
  }
  
  .emptySmall{ padding: 10px 0; font-size: 12px; color:#6b7280; }
  </style>
  