<!-- resources/js/Pages/Employer/TalentSearch.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from "vue";
  import { RouterLink, useRoute } from "vue-router";
  import UiCard from "../../Components/UiCard.vue";
  import { http } from "../../lib/http";
  import { logout } from "@/lib/auth";
  
  /** ===== Router ===== */
  const route = useRoute();
  
  /** ===== Sidebar ===== */
  const sidebarOpen = ref(false);
  
  /** ===== Auth user (localStorage) ===== */
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
  
  /** ===== Display helpers ===== */
  const displayName = computed(() => user.value?.name || user.value?.company_name || user.value?.email || "Employer");
  
  const roleLabel = computed(() => {
    const r = String(user.value?.role || "employer");
    if (r === "admin") return "Admin";
    if (r === "agency") return "Agency";
    return "Employer";
  });
  
  /** ===== Menu ===== */
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
  
    if (targetName === "employer.jobs") {
      return currentName.startsWith("employer.jobs") || currentPath.startsWith("/employer/jobs");
    }
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
  
  /** ===== Talent Search logic ===== */
  const loading = ref(true);
  const error = ref("");
  
  const q = ref("");
  const specialty = ref("all");
  const locationFilter = ref("all");
  const candidates = ref([]);
  
  async function load() {
    loading.value = true;
    error.value = "";
  
    try {
      const ar = await http.get("/applications", { params: { scope: "owned" } });
      const raw = ar?.data?.data ?? ar?.data ?? [];
      const apps = Array.isArray(raw) ? raw : (Array.isArray(raw?.data) ? raw.data : []);
  
      const map = new Map();
      for (const a of apps) {
        const key = a.user_id || a.user?.id || a.id;
        if (!map.has(key)) {
          map.set(key, {
            id: key,
            name: a.applicant_name || a.user?.name || "Candidate",
            location: a.user?.location || a.location || "—",
            specialty: a.job?.department || a.department || "—",
            role: a.job?.title || a.job_title || "—",
            last_active: a.updated_at || a.created_at,
            match: a.ai_match_score ?? null,
            blurb: a.ai_summary || a.summary || "",
          });
        }
      }
      candidates.value = Array.from(map.values());
    } catch (e) {
      const code = e?.response?.status;
      error.value =
        e?.response?.data?.message ||
        (code ? `Request failed (${code})` : "") ||
        e?.message ||
        "Failed to load candidates.";
      candidates.value = [];
    } finally {
      loading.value = false;
    }
  }
  
  onMounted(() => load());
  
  const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    return candidates.value.filter((c) => {
      if (specialty.value !== "all" && String(c.specialty) !== specialty.value) return false;
      if (locationFilter.value !== "all" && String(c.location) !== locationFilter.value) return false;
      if (!term) return true;
      const hay = `${c.name} ${c.specialty} ${c.role} ${c.location}`.toLowerCase();
      return hay.includes(term);
    });
  });
  
  const specialties = computed(() => {
    const s = new Set(candidates.value.map((c) => String(c.specialty || "—")));
    return Array.from(s).sort();
  });
  
  const locations = computed(() => {
    const s = new Set(candidates.value.map((c) => String(c.location || "—")));
    return Array.from(s).sort();
  });
  
  function safeDate(val) {
    const d = new Date(val || 0);
    return Number.isFinite(d.getTime()) ? d : null;
  }
  function fmtDate(val) {
    const d = safeDate(val);
    if (!d) return "—";
    return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "2-digit" });
  }
  function initials(name) {
    const t = String(name || "").trim();
    if (!t) return "C";
    const parts = t.split(/\s+/).filter(Boolean);
    const a = parts[0]?.[0] || "C";
    const b = parts.length > 1 ? parts[1]?.[0] : (parts[0]?.[1] || "");
    return (a + b).toUpperCase();
  }
  function pct(val) {
    const n = Number(val);
    if (!Number.isFinite(n)) return "—";
    return `${Math.max(0, Math.min(100, Math.round(n)))}%`;
  }
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
        <div class="page">
          <!-- Header -->
          <div class="head">
            <div class="titleBlock">
              <div class="eyebrow">Employer</div>
              <h1 class="h1">Talent search</h1>
              <p class="sub">Find clinicians from your applicant pool (fallback from applications).</p>
            </div>
  
            <div class="headActions">
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
  
          <!-- Search/filters -->
          <UiCard>
            <div class="filters">
              <div class="searchWrap">
                <svg class="searchIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M10.5 18.5a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7" />
                  <path d="M16.7 16.7 21 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                </svg>
                <input v-model="q" class="search" placeholder="Search by name, role, specialty, location…" />
              </div>
  
              <div class="selects">
                <select v-model="specialty" class="select">
                  <option value="all">All specialties</option>
                  <option v-for="s in specialties" :key="s" :value="s">{{ s }}</option>
                </select>
  
                <select v-model="locationFilter" class="select">
                  <option value="all">Any location</option>
                  <option v-for="l in locations" :key="l" :value="l">{{ l }}</option>
                </select>
              </div>
            </div>
          </UiCard>
  
          <div class="grid">
            <!-- Results -->
            <div class="leftCol">
              <UiCard>
                <div class="resultsHead">
                  <div>
                    <div class="k">Results</div>
                    <div class="v">{{ filtered.length }}</div>
                  </div>
                  <div class="hint">Based on /applications?scope=owned</div>
                </div>
  
                <div v-if="loading" class="state">Loading…</div>
  
                <div v-else class="list">
                  <div v-for="c in filtered" :key="c.id" class="row">
                    <div class="rowTop">
                      <div class="avatar">{{ initials(c.name) }}</div>
  
                      <div class="meta">
                        <div class="name" :title="c.name">{{ c.name }}</div>
                        <div class="subline" :title="`${c.role} • ${c.specialty} • ${c.location}`">
                          {{ c.role }} • {{ c.specialty }} • {{ c.location }}
                        </div>
                      </div>
  
                      <div class="match">
                        <div class="matchLabel">Match</div>
                        <div class="matchVal">{{ c.match != null ? pct(c.match) : "—" }}</div>
                      </div>
                    </div>
  
                    <p v-if="c.blurb" class="blurb">{{ c.blurb }}</p>
  
                    <div class="rowBottom">
                      <div class="lastActive">Last active: {{ fmtDate(c.last_active) }}</div>
  
                      <div class="actions">
                        <button class="ghostSmall" type="button">View profile</button>
                        <button class="primarySmall" type="button">Invite to apply</button>
                      </div>
                    </div>
                  </div>
  
                  <div v-if="!filtered.length" class="empty">
                    <div class="emptyTitle">No results</div>
                    <div class="emptySub">Try changing filters or search terms.</div>
                  </div>
                </div>
              </UiCard>
            </div>
  
            <!-- Right rail -->
            <div class="rightCol">
              <UiCard>
                <div class="railTitle">Filters</div>
                <div class="railText">
                  Add real filters once you have a candidates endpoint (experience, license, shift, etc.).
                </div>
              </UiCard>
  
              <UiCard>
                <div class="railTitle">AI suggestions</div>
                <ul class="railList">
                  <li>Filter for <strong>3+ years</strong> and ICU specialty.</li>
                  <li>Prioritize candidates open to night shift.</li>
                  <li>Save searches (requires backend support).</li>
                </ul>
              </UiCard>
            </div>
          </div>
        </div>
      </main>
    </div>
  </template>
  
  <style scoped>
  /* ===== shell ===== */
  .shell { min-height: 100vh; background: #f7f7f8; }
  @media (min-width: 981px) {
    .shell { display: flex; }
    .main { flex: 1; min-width: 0; padding: 16px; }
  }
  @media (max-width: 980px) {
    .shell { display: block; }
    .main { padding: 12px; }
  }
  
  /* ===== mobile topbar ===== */
  .topbar { display: none; }
  @media (max-width: 980px) {
    .topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      height: 56px;
      padding: 0 14px;
      border-bottom: 1px solid rgba(17,24,39,.08);
      background: #fff;
      position: sticky;
      top: 0;
      z-index: 40;
    }
    .burger {
      width: 42px; height: 42px;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      background: #fff;
      display: grid;
      place-items: center;
      cursor: pointer;
    }
    .burger span {
      display: block;
      width: 18px;
      height: 2px;
      background: #111827;
      border-radius: 999px;
      margin: 2px 0;
    }
    .brand { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .logo {
      width: 34px; height: 34px;
      border-radius: 12px;
      background: #111827;
      color: #fff;
      display: grid;
      place-items: center;
      font-weight: 950;
      font-size: 12px;
    }
    .brandText { min-width: 0; }
    .brandName { font-weight: 950; font-size: 12px; color: #111827; line-height: 1.1; }
    .brandSub { font-size: 11px; color: #6b7280; font-weight: 900; line-height: 1.1; }
    .quickLink {
      height: 36px;
      padding: 0 12px;
      border-radius: 999px;
      border: 1px solid #e5e7eb;
      background: #fff;
      color: #111827;
      font-size: 12px;
      font-weight: 950;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      white-space: nowrap;
    }
    .quickLink:hover { background: #f9fafb; }
  }
  
  /* overlay */
  .overlay { display: none; }
  @media (max-width: 980px) {
    .overlay {
      display: block;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.35);
      z-index: 45;
    }
  }
  
  /* ===== sidebar ===== */
  .sidebar {
    width: 280px;
    min-height: 100vh;
    position: sticky;
    top: 0;
    border-right: 1px solid rgba(17,24,39,.08);
    background: #fff;
    display: flex;
    flex-direction: column;
    padding: 14px;
    gap: 12px;
    z-index: 50;
  }
  @media (max-width: 980px) {
    .sidebar {
      position: fixed;
      top: 0; left: 0;
      height: 100vh;
      transform: translateX(-110%);
      transition: transform 160ms ease;
      box-shadow: 0 18px 40px rgba(0,0,0,.18);
    }
    .sidebar.open { transform: translateX(0); }
  }
  .sideTop { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
  .brandRow { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; min-width: 0; }
  .logoLg {
    width: 40px; height: 40px;
    border-radius: 14px;
    background: #111827;
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 950;
    font-size: 12px;
    border: 1px solid rgba(17,24,39,.16);
  }
  .closeBtn { display: none; }
  @media (max-width: 980px) {
    .closeBtn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px; height: 40px;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      background: #fff;
      cursor: pointer;
      color: #111827;
      font-weight: 900;
    }
  }
  .profile {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(17,24,39,.08);
    border-radius: 16px;
    padding: 10px;
    background: rgba(17,24,39,.02);
  }
  .avatarSide {
    width: 40px; height: 40px;
    border-radius: 14px;
    background: #111827;
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 950;
  }
  .profileMeta { min-width: 0; }
  .profileName {
    font-size: 12px;
    font-weight: 950;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 170px;
  }
  .profileRole { margin-top: 2px; font-size: 11px; color: #6b7280; font-weight: 900; }
  
  .nav { display: flex; flex-direction: column; gap: 6px; padding-top: 4px; }
  .navItem {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 10px;
    border-radius: 14px;
    text-decoration: none;
    color: #111827;
    border: 1px solid transparent;
    transition: background 120ms ease, border-color 120ms ease, transform 120ms ease;
    font-size: 12px;
    font-weight: 950;
  }
  .navItem:hover {
    background: rgba(17,24,39,.03);
    border-color: rgba(17,24,39,.06);
    transform: translateY(-1px);
  }
  .navItem.active {
    background: rgba(17,24,39,.06);
    border-color: rgba(17,24,39,.12);
  }
  
  .sideBottom {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(17,24,39,.08);
  }
  .miniCta {
    height: 40px;
    border-radius: 999px;
    background: #111827;
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    font-weight: 950;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(17,24,39,.16);
  }
  .miniCta:hover { background: #0b1220; }
  .logout {
    height: 40px;
    border-radius: 999px;
    border: 1px solid rgba(239,68,68,.25);
    background: #fff;
    color: #991b1b;
    font-size: 12px;
    font-weight: 950;
    cursor: pointer;
  }
  .logout:hover { background: rgba(254,226,226,.65); }
  
  /* ===== page ===== */
  .page {
    max-width: 1152px;
    margin: 0 auto;
    padding: 24px 16px;
    display: grid;
    gap: 14px;
  }
  @media (min-width: 981px) {
    .page { padding: 8px 0; }
  }
  
  /* Header */
  .head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .titleBlock { min-width: 0; }
  .eyebrow {
    font-size: 11px;
    font-weight: 950;
    letter-spacing: .10em;
    text-transform: uppercase;
    color: #6b7280;
  }
  .h1 {
    margin: 6px 0 0;
    font-size: 22px;
    font-weight: 950;
    letter-spacing: -0.02em;
    color: #111827;
  }
  .sub { margin: 6px 0 0; font-size: 12px; color: #6b7280; }
  .headActions { display: flex; gap: 8px; flex-wrap: wrap; }
  
  /* Buttons */
  .ghostBtn {
    height: 40px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #111827;
    font-size: 12px;
    font-weight: 950;
    cursor: pointer;
  }
  .ghostBtn:hover { background: #f9fafb; }
  .ghostBtn:disabled { opacity: .65; cursor: not-allowed; }
  
  /* Alert */
  .alert {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid rgba(239,68,68,.25);
    background: rgba(254,242,242,1);
    border-radius: 16px;
    padding: 12px;
  }
  .alertTitle { font-size: 12px; font-weight: 950; color: #991b1b; }
  .alertMsg { margin-top: 4px; font-size: 12px; color: #7f1d1d; word-break: break-word; }
  .alertBtn {
    height: 34px;
    padding: 0 12px;
    border-radius: 999px;
    border: 1px solid rgba(239,68,68,.25);
    background: #fff;
    color: #991b1b;
    font-size: 12px;
    font-weight: 950;
    cursor: pointer;
  }
  .alertBtn:hover { background: rgba(254,226,226,.7); }
  
  /* =========================
     FIX: filters overlap
     Use flex-wrap + min-widths
     ========================= */
  .filters{
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .searchWrap{
    position: relative;
    flex: 1 1 520px;   /* takes remaining space */
    min-width: 320px;  /* prevents crushing into selects */
  }
  .searchIcon{
    position:absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color:#9ca3af;
    pointer-events:none;
  }
  .search{
    width: 100%;
    height: 42px;
    padding: 0 12px 0 40px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    outline: none;
    font-size: 13px;
    color: #111827;
    background: #fff;
    transition: box-shadow 120ms ease, border-color 120ms ease;
  }
  .search:focus{ border-color:#d1d5db; box-shadow: 0 0 0 4px rgba(17,24,39,.08); }
  
  .selects{
    display: flex;
    gap: 10px;
    flex: 0 0 auto;   /* do not overlap search */
    flex-wrap: wrap;  /* allow wrap when tight */
  }
  .select{
    height: 42px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 900;
    color:#111827;
    background:#fff;
    outline:none;
    width: 220px;        /* stable desktop width */
    max-width: 100%;
  }
  .select:focus{ border-color:#d1d5db; box-shadow: 0 0 0 4px rgba(17,24,39,.08); }
  
  @media (max-width: 620px){
    .searchWrap{ flex: 1 1 100%; min-width: 0; }
    .selects{ width: 100%; }
    .select{ width: 100%; }
  }
  
  /* Grid */
  .grid{ display:grid; gap: 14px; grid-template-columns: 1fr; }
  @media (min-width: 980px){ .grid{ grid-template-columns: 1.6fr 1fr; } }
  .leftCol{ min-width:0; }
  .rightCol{ display:grid; gap: 14px; }
  
  /* Results head */
  .resultsHead{
    display:flex;
    align-items:flex-end;
    justify-content:space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(17,24,39,.06);
    margin-bottom: 12px;
  }
  .k{ font-size: 11px; color:#6b7280; font-weight: 950; text-transform: uppercase; letter-spacing: .10em; }
  .v{ margin-top: 4px; font-size: 22px; font-weight: 950; color:#111827; }
  .hint{ font-size: 11px; color:#6b7280; font-weight: 900; }
  
  /* List rows */
  .list{ display:grid; gap: 10px; }
  .row{
    border: 1px solid rgba(17,24,39,.08);
    border-radius: 18px;
    padding: 14px;
    background:#fff;
    transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
  }
  .row:hover{
    transform: translateY(-1px);
    border-color: rgba(17,24,39,.14);
    box-shadow: 0 10px 20px rgba(0,0,0,.06);
  }
  
  .rowTop{
    display:flex;
    align-items:flex-start;
    gap: 12px;
  }
  .avatar{
    width:42px;
    height:42px;
    border-radius: 14px;
    background:#111827;
    color:#fff;
    display:grid;
    place-items:center;
    font-size: 12px;
    font-weight: 950;
    border: 1px solid rgba(17,24,39,.18);
    flex: 0 0 auto;
  }
  .meta{ min-width:0; flex:1; display:grid; gap: 4px; }
  .name{
    font-size: 14px;
    font-weight: 950;
    color:#111827;
    white-space: nowrap;
    overflow:hidden;
    text-overflow: ellipsis;
  }
  .subline{
    font-size: 12px;
    color:#6b7280;
    white-space: nowrap;
    overflow:hidden;
    text-overflow: ellipsis;
  }
  .match{ text-align:right; flex: 0 0 auto; min-width: 72px; }
  .matchLabel{ font-size: 11px; color:#6b7280; font-weight: 900; }
  .matchVal{ margin-top: 2px; font-size: 14px; font-weight: 950; color:#111827; }
  
  .blurb{ margin: 10px 0 0; font-size: 13px; color:#111827; line-height: 1.4; }
  
  /* =========================
     FIX: row buttons overlap
     Force wrap + full-width actions on small screens
     ========================= */
  .rowBottom{
    margin-top: 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    flex-wrap: wrap;
  }
  .lastActive{ font-size: 11px; color:#6b7280; font-weight: 900; }
  .actions{
    display:flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: auto; /* keeps buttons on the right in wide screens */
  }
  .ghostSmall, .primarySmall{
    height: 36px;
    padding: 0 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 950;
    cursor:pointer;
    white-space: nowrap; /* prevents text wrapping inside buttons */
  }
  .ghostSmall{
    border: 1px solid #e5e7eb;
    background:#fff;
    color:#111827;
  }
  .ghostSmall:hover{ background:#f9fafb; }
  .primarySmall{
    border: 1px solid rgba(17,24,39,.16);
    background:#111827;
    color:#fff;
  }
  .primarySmall:hover{ background:#0b1220; }
  
  @media (max-width: 560px){
    .match{ min-width: 56px; }
    .actions{
      width: 100%;
      margin-left: 0;
      justify-content: flex-end;
    }
  }
  
  /* Rail */
  .railTitle{ font-size: 12px; font-weight: 950; color:#111827; }
  .railText{ margin-top: 6px; font-size: 12px; color:#6b7280; line-height: 1.5; }
  .railList{ margin: 10px 0 0; padding-left: 18px; font-size: 12px; color:#111827; line-height: 1.6; }
  .railList li{ margin: 6px 0; }
  
  .state{ padding: 12px 6px; font-size: 12px; color:#6b7280; }
  .empty{ padding: 14px 8px; display:grid; gap: 6px; }
  .emptyTitle{ font-size: 13px; font-weight: 950; color:#111827; }
  .emptySub{ font-size: 12px; color:#6b7280; }
  </style>
  