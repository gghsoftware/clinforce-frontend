<!-- resources/js/Pages/Employer/Jobs/JobsIndex.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
  import { RouterLink, useRoute } from "vue-router";
  import UiCard from "../../../Components/UiCard.vue";
  import { http } from "../../../lib/http";
  import { logout } from "@/lib/auth";
  
  /** ===== Sidebar ===== */
  const route = useRoute();
  const sidebarOpen = ref(false);
  
  function safeParse(raw) {
    try {
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }
  
  const user = ref(safeParse(localStorage.getItem("auth_user")));
  
  function refreshUser() {
    user.value = safeParse(localStorage.getItem("auth_user"));
  }
  function onAuthChanged() {
    refreshUser();
  }
  function onStorage(e) {
    if (e.key === "auth_user") refreshUser();
  }
  
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
  
  /** ===== Jobs Index logic (unchanged) ===== */
  const loading = ref(true);
  const error = ref("");
  
  const q = ref("");
  const status = ref("all"); // all | open | closed | draft
  const sort = ref("recent"); // recent | title
  const rows = ref([]);
  
  // ---- helpers ----
  function normalizeStatus(r) {
    const raw = String(r?.status || "").toLowerCase().trim();
    if (raw === "published") return "open";
    if (raw === "archived") return "closed";
    if (raw === "draft") return "draft";
    if (r?.is_published === true) return "open";
    return "draft";
  }
  
  function statusText(uiStatus) {
    if (uiStatus === "open") return "Open";
    if (uiStatus === "closed") return "Closed";
    return "Draft";
  }
  
  function pickTitle(r) {
    return r?.title || r?.role_title || "Untitled role";
  }
  
  function pickDepartment(r) {
    return r?.department || "—";
  }
  
  function pickLocation(r) {
    const city = String(r?.city || "").trim();
    const cc = String(r?.country_code || "").trim();
    const parts = [city, cc].filter(Boolean);
    return parts.length ? parts.join(", ") : (r?.location || "—");
  }
  
  function safeDate(val) {
    const d = new Date(val || 0);
    return Number.isFinite(d.getTime()) ? d : null;
  }
  
  function formatDate(val) {
    const d = safeDate(val);
    if (!d) return "";
    return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "2-digit" });
  }
  
  function formatRelative(val) {
    const d = safeDate(val);
    if (!d) return "";
    const ms = Date.now() - d.getTime();
    const mins = Math.floor(ms / 60000);
    if (mins < 1) return "Just now";
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.floor(hrs / 24);
    return `${days}d ago`;
  }
  
  function initialsFromTitle(title) {
    const t = String(title || "").trim();
    if (!t) return "JR";
    const parts = t.split(/\s+/).filter(Boolean);
    const a = parts[0]?.[0] || "J";
    const b = parts.length > 1 ? parts[1]?.[0] : parts[0]?.[1] || "R";
    return (a + b).toUpperCase();
  }
  
  function normalizeRows(payload) {
    const root = payload?.data ?? payload;
    if (Array.isArray(root)) return root;
    if (Array.isArray(root?.data)) return root.data;
    if (Array.isArray(root?.items)) return root.items;
    if (Array.isArray(payload?.data?.data)) return payload.data.data;
    return [];
  }
  
  // ---- server-side query params ----
  function apiStatusParam(uiStatus) {
    if (uiStatus === "open") return "published";
    if (uiStatus === "closed") return "archived";
    if (uiStatus === "draft") return "draft";
    return null;
  }
  
  function apiSortParam(uiSort) {
    return uiSort === "title" ? "title" : "recent";
  }
  
  // ---- loading (debounced reload + stale response protection) ----
  let debounceTimer = null;
  let reqSeq = 0;
  
  async function load({ silent = false } = {}) {
    const mySeq = ++reqSeq;
  
    if (!silent) {
      loading.value = true;
      error.value = "";
    }
  
    try {
      const params = {
        q: q.value.trim() || undefined,
        status: apiStatusParam(status.value) || undefined,
        sort: apiSortParam(sort.value),
        scope: "owned",
      };
  
      const res = await http.get("/jobs", { params });
  
      if (mySeq !== reqSeq) return;
      rows.value = normalizeRows(res);
    } catch (e) {
      if (mySeq !== reqSeq) return;
  
      const code = e?.response?.status;
      error.value =
        e?.response?.data?.message ||
        (code ? `Request failed (${code})` : "") ||
        e?.message ||
        "Failed to load jobs.";
      rows.value = [];
    } finally {
      if (mySeq === reqSeq && !silent) loading.value = false;
    }
  }
  
  function scheduleReload() {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => load({ silent: true }), 250);
  }
  
  watch([q, status, sort], () => scheduleReload());
  
  // ---- counts ----
  const counts = computed(() => {
    const all = rows.value.length;
    const open = rows.value.filter((r) => normalizeStatus(r) === "open").length;
    const closed = rows.value.filter((r) => normalizeStatus(r) === "closed").length;
    const draft = rows.value.filter((r) => normalizeStatus(r) === "draft").length;
    return { all, open, closed, draft };
  });
  
  // ---- pinned drafts + client-side fallback filtering ----
  function statusWeight(uiStatus) {
    if (uiStatus === "draft") return 0;
    if (uiStatus === "open") return 1;
    return 2;
  }
  
  const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    let data = rows.value.slice();
  
    if (term) {
      data = data.filter((r) => {
        const t = String(pickTitle(r)).toLowerCase();
        const d = String(r?.department || "").toLowerCase();
        const l = String(pickLocation(r)).toLowerCase();
        return t.includes(term) || d.includes(term) || l.includes(term);
      });
    }
  
    if (status.value !== "all") {
      data = data.filter((r) => normalizeStatus(r) === status.value);
    }
  
    data.sort((a, b) => {
      const sa = normalizeStatus(a);
      const sb = normalizeStatus(b);
  
      const wa = statusWeight(sa);
      const wb = statusWeight(sb);
      if (wa !== wb) return wa - wb;
  
      if (sort.value === "title") return String(pickTitle(a)).localeCompare(String(pickTitle(b)));
  
      const ta = safeDate(a?.created_at)?.getTime() || 0;
      const tb = safeDate(b?.created_at)?.getTime() || 0;
      return tb - ta;
    });
  
    return data;
  });
  
  // initial load
  onMounted(() => load());
  onBeforeUnmount(() => {
    if (debounceTimer) clearTimeout(debounceTimer);
  });
  </script>
  
  <template>
    <!-- Match Interviews/Messages page background + prevent overlap -->
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
  
          <RouterLink class="quickCta" :to="{ name: 'employer.jobs.create' }">+ Post</RouterLink>
        </div>
  
        <!-- Mobile overlay -->
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
          <div class="content">
            <!-- Header -->
            <div class="head">
              <div class="titleBlock">
                <div class="eyebrow">Employer</div>
                <h1 class="h1">My roles</h1>
                <p class="sub">Create, manage, and publish clinical roles.</p>
              </div>
  
              <div class="headActions">
                <button class="btn" type="button" :disabled="loading" @click="load()">
                  {{ loading ? "Refreshing…" : "Refresh" }}
                </button>
                <RouterLink class="btn btnPrimary linkBtn" :to="{ name: 'employer.jobs.create' }">+ Post new role</RouterLink>
              </div>
            </div>
  
            <!-- Error -->
            <div v-if="error" class="alert alertError" role="alert">
              <div class="alertLeft">
                <div class="alertTitle">Action required</div>
                <div class="alertMsg">{{ error }}</div>
              </div>
              <button class="btn" type="button" @click="load()">Retry</button>
            </div>
  
            <!-- Toolbar -->
            <UiCard>
              <div class="toolbar">
                <div class="chips">
                  <button class="chip" :class="{ active: status === 'all' }" type="button" @click="status = 'all'">
                    All <span class="chipCount">{{ counts.all }}</span>
                  </button>
                  <button class="chip" :class="{ active: status === 'open' }" type="button" @click="status = 'open'">
                    Open <span class="chipCount">{{ counts.open }}</span>
                  </button>
                  <button class="chip" :class="{ active: status === 'draft' }" type="button" @click="status = 'draft'">
                    Draft <span class="chipCount">{{ counts.draft }}</span>
                  </button>
                  <button class="chip" :class="{ active: status === 'closed' }" type="button" @click="status = 'closed'">
                    Closed <span class="chipCount">{{ counts.closed }}</span>
                  </button>
                </div>
  
                <div class="controls">
                  <div class="searchWrap">
                    <svg class="searchIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M10.5 18.5a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7" />
                      <path d="M16.7 16.7 21 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                    <input v-model="q" class="search" placeholder="Search title or location…" />
                  </div>
  
                  <select v-model="sort" class="select">
                    <option value="recent">Sort: Recent</option>
                    <option value="title">Sort: Title</option>
                  </select>
                </div>
              </div>
            </UiCard>
  
            <!-- Cards -->
            <UiCard>
              <div v-if="loading" class="state">Loading…</div>
  
              <template v-else>
                <div class="cards" v-if="filtered.length">
                  <RouterLink
                    v-for="r in filtered"
                    :key="r.id"
                    class="card"
                    :to="{ name: 'employer.jobs.view', params: { id: r.id } }"
                  >
                    <div class="cardTop">
                      <div class="avatar" aria-hidden="true">{{ initialsFromTitle(pickTitle(r)) }}</div>
  
                      <div class="cardMeta">
                        <div class="cardTitle" :title="pickTitle(r)">{{ pickTitle(r) }}</div>
                        <div class="cardSub">
                          <span class="muted">{{ pickDepartment(r) }}</span>
                          <span class="dot">•</span>
                          <span class="muted" :title="pickLocation(r)">{{ pickLocation(r) }}</span>
                        </div>
                      </div>
  
                      <span class="badge" :data-status="normalizeStatus(r)">{{ statusText(normalizeStatus(r)) }}</span>
                    </div>
  
                    <div class="cardBottom">
                      <div class="metaPill">#{{ r.id }}</div>
                      <div class="metaPill" :title="formatDate(r.created_at)">{{ formatRelative(r.created_at) }}</div>
                      <div class="chevPill" aria-hidden="true">View →</div>
                    </div>
                  </RouterLink>
                </div>
  
                <div v-else class="empty">
                  <div class="emptyTitle">No roles found</div>
                  <div class="emptySub">Try changing filters or creating a new role.</div>
                  <RouterLink class="btn btnPrimary linkBtn" :to="{ name: 'employer.jobs.create' }">+ Post new role</RouterLink>
                </div>
              </template>
            </UiCard>
          </div>
        </main>
      </div>
    </div>
  </template>
  
  <style scoped>
  /* ===== Background + base typography (same as Interviews/Messages) ===== */
  .pageBg{
    min-height:100vh;
    overflow-x:hidden;
    background: linear-gradient(180deg,#fafaf9 0%,#f5f5f4 100%);
    color:#111827;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  }
  .shell, .shell *{ box-sizing:border-box; }
  
  /* ===== shell layout ===== */
  .shell{ min-height:100vh; width:100%; }
  @media (min-width: 981px){
    .shell{ display:flex; }
    .main{ flex:1; min-width:0; padding:16px; }
  }
  @media (max-width: 980px){
    .main{ padding:12px; }
  }
  
  /* main content wrapper (prevents “overlap” and keeps consistent width) */
  .content{
    max-width: 1152px;
    margin: 0 auto;
    padding: 8px 0;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  
  /* ===== mobile topbar ===== */
  .topbar{ display:none; }
  @media (max-width: 980px){
    .topbar{
      display:flex; align-items:center; justify-content:space-between; gap:10px;
      height:56px; padding:0 14px;
      border-bottom:1px solid rgba(17,24,39,.08);
      background:#fff; position:sticky; top:0; z-index:60;
    }
    .burger{ width:42px; height:42px; border-radius:12px; border:1px solid rgba(229,231,235,0.95); background:#fff; display:grid; place-items:center; cursor:pointer; }
    .burger span{ display:block; width:18px; height:2px; background:#111827; border-radius:999px; margin:2px 0; }
    .brand{ display:flex; align-items:center; gap:10px; min-width:0; }
    .logo{ width:34px; height:34px; border-radius:12px; background:#111827; color:#fff; display:grid; place-items:center; font-weight:900; font-size:12px; }
    .brandText{ min-width:0; }
    .brandName{ font-weight:900; font-size:12px; line-height:1.1; }
    .brandSub{ font-size:11px; color:#6b7280; font-weight:900; line-height:1.1; }
    .quickCta{
      height:36px; padding:0 12px; border-radius:999px;
      background:#111827; color:#fff; text-decoration:none;
      font-size:12px; font-weight:900; display:inline-flex; align-items:center; justify-content:center;
      border:1px solid rgba(17,24,39,.16);
      white-space:nowrap;
    }
  }
  
  /* overlay */
  .overlay{ display:none; }
  @media (max-width: 980px){
    .overlay{ display:block; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:70; }
  }
  
  /* ===== sidebar ===== */
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
  .profileRole{ margin-top:2px; font-size:11px; color:#6b7280; font-weight:900; }
  
  .nav{ display:flex; flex-direction:column; gap:6px; padding-top:4px; }
  .navItem{ display:flex; align-items:center; padding:10px; border-radius:14px; text-decoration:none; color:#111827; border:1px solid transparent; font-size:12px; font-weight:900; transition:background 120ms ease, border-color 120ms ease, transform 120ms ease; }
  .navItem:hover{ background:rgba(17,24,39,.03); border-color:rgba(17,24,39,.06); transform:translateY(-1px); }
  .navItem.active{ background:rgba(17,24,39,.06); border-color:rgba(17,24,39,.12); }
  
  .sideBottom{ margin-top:auto; display:flex; flex-direction:column; gap:10px; padding-top:10px; border-top:1px solid rgba(17,24,39,.08); }
  .miniCta{ height:40px; border-radius:999px; background:#111827; color:#fff; text-decoration:none; font-size:12px; font-weight:900; display:inline-flex; align-items:center; justify-content:center; }
  .logout{ height:40px; border-radius:999px; border:1px solid rgba(239,68,68,.25); background:#fff; color:#991b1b; font-size:12px; font-weight:900; cursor:pointer; }
  
  /* ===== page header ===== */
  .head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .titleBlock{ min-width:0; }
  .eyebrow{ font-size:11px; font-weight:900; letter-spacing:.10em; text-transform:uppercase; color:#6b7280; }
  .h1{ margin:6px 0 0; font-size:22px; font-weight:900; letter-spacing:-0.02em; }
  .sub{ margin:6px 0 0; font-size:12px; color:#6b7280; }
  .headActions{ display:flex; gap:10px; flex-wrap:wrap; }
  
  /* ===== shared buttons (match other pages) ===== */
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
    display:inline-flex;
    align-items:center;
    justify-content:center;
    white-space:nowrap;
  }
  .btn:disabled{ opacity:0.55; cursor:not-allowed; }
  .btnPrimary{
    background: linear-gradient(180deg, #111827 0%, #0b1220 100%);
    border-color: rgba(17,24,39,0.55);
    color:#fff;
  }
  .linkBtn{ text-decoration:none; }
  
  /* ===== alerts ===== */
  .alert{ border-radius:16px; padding:12px; display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
  .alertError{ background:#fef2f2; border:1px solid rgba(254,202,202,0.95); color:#b91c1c; }
  .alertTitle{ font-size:12px; font-weight:900; }
  .alertMsg{ margin-top:4px; font-size:12px; color:#7f1d1d; word-break:break-word; }
  
  /* ===== toolbar ===== */
  .toolbar{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
  .chips{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .chip{
    display:inline-flex; align-items:center; gap:8px;
    height:34px; padding:0 12px; border-radius:999px;
    border:1px solid rgba(209,213,219,0.95); background:#fff;
    font-size:12px; font-weight:900; cursor:pointer;
    transition: background 120ms ease, transform 120ms ease, border-color 120ms ease;
  }
  .chip:hover{ background:#f9fafb; transform:translateY(-1px); }
  .chip.active{ background:rgba(17,24,39,.06); border-color:rgba(17,24,39,.18); }
  .chipCount{ min-width:22px; height:20px; padding:0 6px; border-radius:999px; border:1px solid rgba(229,231,235,0.95); font-size:11px; font-weight:900; color:#6b7280; display:inline-flex; align-items:center; justify-content:center; }
  
  .controls{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .searchWrap{ position:relative; }
  .searchIcon{ position:absolute; left:12px; top:50%; transform:translateY(-50%); width:18px; height:18px; color:#9ca3af; pointer-events:none; }
  .search{
    width:min(420px, 72vw);
    height:40px;
    padding:0 12px 0 40px;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    outline:none;
    font-size:13px;
    background:#fff;
  }
  .search:focus{ box-shadow:0 0 0 4px rgba(17,24,39,.08); }
  .select{
    height:40px;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    padding:0 12px;
    font-size:13px;
    font-weight:900;
    background:#fff;
    outline:none;
  }
  .select:focus{ box-shadow:0 0 0 4px rgba(17,24,39,.08); }
  
  /* ===== cards ===== */
  .cards{ display:grid; gap:12px; grid-template-columns:1fr; }
  @media (min-width: 900px){ .cards{ grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  
  .card{
    display:flex; flex-direction:column; gap:12px;
    border:1px solid rgba(17,24,39,.08);
    border-radius:18px;
    padding:14px;
    text-decoration:none;
    color:inherit;
    background:#fff;
    transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease, background 120ms ease;
  }
  .card:hover{ transform:translateY(-2px); border-color:rgba(17,24,39,.14); box-shadow:0 10px 20px rgba(0,0,0,.06); background:rgba(17,24,39,.01); }
  .cardTop{ display:flex; align-items:flex-start; gap:12px; }
  .avatar{
    width:42px; height:42px; border-radius:14px;
    background:#111827; color:#fff; display:grid; place-items:center;
    font-size:12px; font-weight:900; border:1px solid rgba(17,24,39,.18); flex:0 0 auto;
  }
  .cardMeta{ min-width:0; flex:1; display:grid; gap:4px; }
  .cardTitle{ font-size:14px; font-weight:900; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .cardSub{ display:flex; align-items:center; gap:6px; min-width:0; }
  .muted{ font-size:12px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .dot{ color:#9ca3af; }
  
  .badge{
    font-size:11px; font-weight:900;
    padding:6px 10px; border-radius:999px;
    border:1px solid rgba(229,231,235,0.95);
    background:#fff; color:#6b7280;
    text-transform:capitalize;
    flex:0 0 auto;
  }
  .badge[data-status="open"]{ border-color: rgba(16,185,129,.25); color:#065f46; background: rgba(209,250,229,.60); }
  .badge[data-status="draft"]{ border-color: rgba(245,158,11,.28); color:#92400e; background: rgba(255,237,213,.70); }
  .badge[data-status="closed"]{ border-color: rgba(239,68,68,.22); color:#991b1b; background: rgba(254,226,226,.70); }
  
  .cardBottom{ display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
  .metaPill{ font-size:11px; font-weight:900; border:1px solid rgba(229,231,235,0.95); background:#fff; border-radius:999px; padding:6px 10px; }
  .chevPill{ margin-left:auto; font-size:11px; font-weight:900; color:#6b7280; border:1px solid rgba(17,24,39,.08); background: rgba(17,24,39,.02); border-radius:999px; padding:6px 10px; }
  
  /* states */
  .state{ padding:16px 12px; font-size:12px; color:#6b7280; }
  .empty{ padding:18px 12px; display:grid; gap:6px; }
  .emptyTitle{ font-size:13px; font-weight:900; }
  .emptySub{ font-size:12px; color:#6b7280; }
  @media (max-width: 520px){ .search{ width:100%; } }
  </style>