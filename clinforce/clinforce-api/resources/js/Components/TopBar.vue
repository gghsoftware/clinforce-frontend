<!-- resources/js/Components/TopBar.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref, watch, nextTick } from "vue";
  import { RouterLink, useRoute, useRouter } from "vue-router";
  import Swal from "sweetalert2";
  
  const route = useRoute();
  const router = useRouter();
  
  /** ---- Auth user (from localStorage) ---- **/
  const user = ref(null);
  
  function safeParse(json) {
    try {
      return JSON.parse(json);
    } catch {
      return null;
    }
  }
  
  function loadAuthUser() {
    const raw = localStorage.getItem("auth_user");
    user.value = raw ? safeParse(raw) : null;
  }
  
  function onStorage(e) {
    if (e.key === "auth_user" || e.key === "auth_token" || e.key === "CLINFORCE_TOKEN") {
      loadAuthUser();
    }
  }
  
  function onAuthChanged() {
    loadAuthUser();
  }
  
  const isAuthed = computed(() => !!user.value);
  
  const displayName = computed(() => {
    const u = user.value;
    if (!u) return "";
    return u.name || u.full_name || u.email || u.phone || `User #${u.id ?? ""}`.trim();
  });
  
  const initials = computed(() => {
    const name = String(displayName.value || "").trim();
    if (!name) return "U";
    const parts = name.split(/\s+/).filter(Boolean);
    const a = parts[0]?.[0] || "";
    const b = parts.length > 1 ? parts[parts.length - 1]?.[0] : "";
    return ((a + b) || name.slice(0, 2)).toUpperCase();
  });
  
  const role = computed(() => String(user.value?.role || "").toLowerCase());
  const isCandidate = computed(() => role.value === "applicant");
  const isEmployer = computed(() => role.value === "employer");
  const isAdmin = computed(() => role.value === "admin");
  const isAgency = computed(() => role.value === "agency");
  const isStaff = computed(() => isEmployer.value || isAdmin.value || isAgency.value);
  
  const sidebarOpen = ref(false);
  
  /** ---- Role-based sidebar groups (matches your router/index.js) ---- **/
  const navGroups = computed(() => {
    if (!isAuthed.value) return [];
  
    if (isCandidate.value) {
      return [
        {
          title: "Candidate",
          items: [
            { label: "Dashboard", to: { name: "candidate.dashboard" }, icon: "grid" },
            { label: "Jobs", to: { name: "candidate.jobs" }, icon: "briefcase" },
            { label: "My Applications", to: { name: "candidate.myapplications" }, icon: "file" },
            { label: "Interviews", to: { name: "candidate.interviews" }, icon: "calendar" },
          ],
        },
        {
          title: "Profile",
          items: [
            { label: "Profile", to: { name: "candidate.profile" }, icon: "user" },
            { label: "Messages", to: { name: "candidate.messages" }, icon: "chat" },
            { label: "Settings", to: { name: "candidate.settings" }, icon: "gear" },
          ],
        },
      ];
    }
  
    // Staff (Employer/Admin/Agency)
    return [
      {
        title: "Employer",
        items: [
          { label: "Dashboard", to: { name: "employer.dashboard" }, icon: "grid" },
          { label: "Jobs", to: { name: "employer.jobs" }, icon: "briefcase" },
          { label: "Applicants", to: { name: "applicants.list" }, icon: "users" },
          { label: "Talent Search", to: { name: "employer.talentsearch" }, icon: "search" },
          { label: "Messages", to: { name: "employer.messages" }, icon: "chat" },
          { label: "Interviews", to: { name: "employer.interviews" }, icon: "calendar" },
          { label: "Billing", to: { name: "employer.billing" }, icon: "money" },
        ],
      },
    ];
  });
  
  function isActive(to) {
    if (!to?.name) return false;
  
    // exact
    if (route.name === to.name) return true;
  
    // highlight parent when viewing detail routes
    const current = String(route.name || "");
    const target = String(to.name || "");
  
    // candidate.jobs.view should keep Jobs active
    if (target === "candidate.jobs" && current.startsWith("candidate.jobs")) return true;
  
    // keep My Applications active (support both new + old route prefixes)
    if (target === "candidate.myapplications" && current.startsWith("candidate.myapplications")) return true;
    if (target === "candidate.myapplications" && current.startsWith("candidate.applications")) return true;
  
    // employer.jobs.view/edit should keep Jobs active
    if (target === "employer.jobs" && current.startsWith("employer.jobs")) return true;
  
    // applicants.view should keep Applicants active
    if (target === "applicants.list" && current.startsWith("applicants.")) return true;
  
    return false;
  }
  
  function homeRoute() {
    if (isCandidate.value) return { name: "candidate.dashboard" };
    if (isStaff.value) return { name: "employer.dashboard" };
    return { name: "auth.login" };
  }
  
  function roleLabel() {
    if (!isAuthed.value) return "Clinical workforce network";
    if (isCandidate.value) return "Candidate";
    if (isEmployer.value) return "Employer";
    if (isAdmin.value) return "Admin";
    if (isAgency.value) return "Agency";
    return "Staff";
  }
  
  /**
   * ✅ FIX: close sidebar + refresh user on EVERY navigation
   * This prevents "need reload" behavior after clicking a sidebar link.
   */
  watch(
    () => route.fullPath,
    async () => {
      sidebarOpen.value = false;
      // ensure localStorage-based user is reflected right away
      loadAuthUser();
      await nextTick();
    }
  );
  
  onMounted(() => {
    loadAuthUser();
    window.addEventListener("storage", onStorage);
    window.addEventListener("auth:changed", onAuthChanged);
  });
  
  onBeforeUnmount(() => {
    window.removeEventListener("storage", onStorage);
    window.removeEventListener("auth:changed", onAuthChanged);
  });
  
  async function logout() {
    const result = await Swal.fire({
      title: "Log out?",
      text: "You will be signed out of your session.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Logout",
      cancelButtonText: "Cancel",
      reverseButtons: true,
      focusCancel: true,
      confirmButtonColor: "#111827",
    });
  
    if (!result.isConfirmed) return;
  
    try {
      await fetch("/api/auth/logout", { method: "POST" });
    } catch {}
  
    localStorage.removeItem("auth_token");
    localStorage.removeItem("CLINFORCE_TOKEN");
    localStorage.removeItem("auth_user");
    sidebarOpen.value = false;
  
    window.dispatchEvent(new Event("auth:changed"));
  
    await Swal.fire({
      title: "Logged out",
      icon: "success",
      timer: 800,
      showConfirmButton: false,
    });
  
    router.push({ name: "auth.login" });
  }
  </script>
  
  <template>
    <!-- Top bar -->
    <header class="topbar">
      <div class="topbar__inner">
        <!-- Mobile menu -->
        <button
          v-if="isAuthed"
          class="iconBtn showMobile"
          type="button"
          @click="sidebarOpen = true"
          aria-label="Open menu"
        >
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M5 7h14M5 12h14M5 17h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </button>
  
        <!-- Brand -->
        <RouterLink class="brand" :to="homeRoute()">
          <span class="brand__mark" aria-hidden="true">AC</span>
          <span class="brand__text">
            <span class="brand__title">AI Clinforce Partners</span>
            <span class="brand__sub">{{ roleLabel() }}</span>
          </span>
        </RouterLink>
  
        <!-- Right -->
        <div class="topbar__right">
          <div v-if="isAuthed" class="who">
            <div class="avatar" :title="displayName">{{ initials }}</div>
            <div class="who__meta hideMobile">
              <div class="who__name">{{ displayName }}</div>
              <div class="who__role">{{ role || "guest" }}</div>
            </div>
          </div>
  
          <button v-if="isAuthed" class="btn" type="button" @click="logout">Logout</button>
          <RouterLink v-else class="btn ghost" :to="{ name: 'auth.login' }">Log in</RouterLink>
        </div>
      </div>
    </header>
  
    <!-- Desktop sidebar -->
    <aside v-if="isAuthed" class="sidebar hideMobile">
      <div class="sidebar__inner">
        <div class="profile">
          <div class="profile__row">
            <div class="avatar lg">{{ initials }}</div>
            <div class="profile__meta">
              <div class="profile__name">{{ displayName || "—" }}</div>
              <div class="profile__role">{{ role || "guest" }}</div>
            </div>
          </div>
  
          <!-- Candidate-only quick actions -->
          <div v-if="isCandidate" class="profile__actions">
            <RouterLink class="linkBtn" :to="{ name: 'candidate.profile' }">Update profile</RouterLink>
            <RouterLink class="linkBtn ghost" :to="{ name: 'candidate.jobs' }">Browse jobs</RouterLink>
            <RouterLink class="linkBtn ghost" :to="{ name: 'candidate.myapplications' }">My applications</RouterLink>
          </div>
  
          <!-- Staff quick action -->
          <div v-else class="profile__actions">
            <RouterLink class="linkBtn" :to="{ name: 'employer.jobs.create' }">Post a job</RouterLink>
            <RouterLink class="linkBtn ghost" :to="{ name: 'applicants.list' }">View applicants</RouterLink>
          </div>
        </div>
  
        <div v-for="group in navGroups" :key="group.title" class="group">
          <div class="group__title">{{ group.title }}</div>
          <nav class="nav">
            <RouterLink
              v-for="item in group.items"
              :key="item.label"
              class="nav__link"
              :class="{ active: isActive(item.to) }"
              :to="item.to"
            >
              <span class="nav__icon" aria-hidden="true">
                <!-- existing icons -->
                <svg v-if="item.icon === 'users'" viewBox="0 0 24 24" fill="none">
                  <path d="M16 20a4 4 0 0 0-8 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M20 20a4.5 4.5 0 0 0-6.5-3.9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M17 12a3 3 0 1 0-3-3 3 3 0 0 0 3 3Z" stroke="currentColor" stroke-width="1.7"/>
                </svg>
  
                <svg v-else-if="item.icon === 'briefcase'" viewBox="0 0 24 24" fill="none">
                  <path d="M9 7V6a3 3 0 0 1 3-3h0a3 3 0 0 1 3 3v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M4 9.5A2.5 2.5 0 0 1 6.5 7h11A2.5 2.5 0 0 1 20 9.5v8A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-8Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M4 12h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
  
                <svg v-else-if="item.icon === 'grid'" viewBox="0 0 24 24" fill="none">
                  <path d="M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z"
                        stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                </svg>
  
                <svg v-else-if="item.icon === 'file'" viewBox="0 0 24 24" fill="none">
                  <path d="M7 3h7l3 3v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M14 3v4h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M8 12h8M8 16h8" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
  
                <svg v-else-if="item.icon === 'calendar'" viewBox="0 0 24 24" fill="none">
                  <path d="M7 3v3M17 3v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M4 8h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M6 6h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/>
                </svg>
  
                <svg v-else-if="item.icon === 'user'" viewBox="0 0 24 24" fill="none">
                  <path d="M20 21a8 8 0 0 0-16 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  <path d="M12 13a4 4 0 1 0-4-4 4 4 0 0 0 4 4Z" stroke="currentColor" stroke-width="1.7"/>
                </svg>
  
                <svg v-else-if="item.icon === 'chat'" viewBox="0 0 24 24" fill="none">
                  <path d="M21 14a4 4 0 0 1-4 4H9l-4 3V6a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                  <path d="M9 9h8M9 13h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
  
                <svg v-else-if="item.icon === 'gear'" viewBox="0 0 24 24" fill="none">
                  <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M19.4 15a8 8 0 0 0 .1-6l-2.1-.6a6.9 6.9 0 0 0-1.2-1.2l.6-2.1a8 8 0 0 0-6-.1l-.6 2.1a6.9 6.9 0 0 0-1.7.7l-2-1.1a8 8 0 0 0-3 5.2l2.1.6c0 .6.1 1.2.2 1.8l-1.7 1.4a8 8 0 0 0 4.1 4.3l1.1-2a6.9 6.9 0 0 0 1.8.2l.6 2.1a8 8 0 0 0 5.2-3l-1.1-2c.5-.3.9-.7 1.3-1.2l2.1.6Z"
                        stroke="currentColor" stroke-width="1.2" stroke-linejoin="round" opacity="0.55"/>
                </svg>
  
                <svg v-else-if="item.icon === 'search'" viewBox="0 0 24 24" fill="none">
                  <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
  
                <svg v-else-if="item.icon === 'money'" viewBox="0 0 24 24" fill="none">
                  <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.7"/>
                  <path d="M7 9h0M17 15h0" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
              </span>
  
              <span class="nav__label">{{ item.label }}</span>
            </RouterLink>
          </nav>
        </div>
  
        <div class="group">
          <div class="group__title">Account</div>
          <button class="nav__link buttonLink danger" type="button" @click="logout">
            <span class="nav__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none">
                <path d="M10 17l-1 0a6 6 0 0 1 0-12l1 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <path d="M14 7l4 5-4 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 12H10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </span>
            <span class="nav__label">Logout</span>
          </button>
        </div>
      </div>
    </aside>
  
    <!-- Mobile drawer -->
    <div v-if="isAuthed && sidebarOpen" class="overlay" @click.self="sidebarOpen = false">
      <div class="sheet" role="dialog" aria-modal="true">
        <div class="sheet__head">
          <div class="sheet__brand">
            <span class="brand__mark mini" aria-hidden="true">AC</span>
            <div class="sheet__brandText">
              <div class="sheet__title">AI Clinforce Partners</div>
              <div class="sheet__sub">{{ roleLabel() }}</div>
            </div>
          </div>
  
          <button class="iconBtn" type="button" @click="sidebarOpen = false" aria-label="Close menu">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </button>
        </div>
  
        <div class="sheet__body">
          <div class="sheet__profile">
            <div class="avatar lg">{{ initials }}</div>
            <div class="sheet__meta">
              <div class="sheet__name">{{ displayName || "—" }}</div>
              <div class="sheet__role">{{ role || "guest" }}</div>
            </div>
          </div>
  
          <div class="sheet__groups">
            <div v-for="group in navGroups" :key="group.title" class="sheet__group">
              <div class="group__title">{{ group.title }}</div>
  
              <RouterLink
                v-for="item in group.items"
                :key="item.label"
                class="sheet__link"
                :class="{ active: isActive(item.to) }"
                :to="item.to"
                @click="sidebarOpen = false"
              >
                {{ item.label }}
              </RouterLink>
            </div>
  
            <button class="sheet__link danger" type="button" @click="logout">Logout</button>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <style scoped>
  :root{
    --bg: #ffffff;
    --panel: rgba(255,255,255,0.92);
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
  
  /* Topbar */
  .topbar{
    position: fixed;
    inset: 0 0 auto 0;
    height: 56px;
    z-index: 60;
    background: var(--panel);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid var(--line);
  }
  .topbar__inner{
    max-width: 1152px;
    margin: 0 auto;
    height: 100%;
    padding: 0 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }
  
  .brand{
    display: inline-flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: var(--ink);
    min-width: 0;
  }
  .brand__mark{
    width: 34px;
    height: 34px;
    border-radius: var(--r14);
    background: #111827;
    color: #fff;
    display: grid;
    place-items: center;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.04em;
  }
  .brand__mark.mini{
    width: 32px;
    height: 32px;
    border-radius: var(--r14);
  }
  .brand__text{
    display: grid;
    line-height: 1.1;
    min-width: 0;
  }
  .brand__title{
    font-weight: 800;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .brand__sub{
    margin-top: 2px;
    font-size: 11px;
    color: var(--muted);
  }
  
  .topbar__right{
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  .who{
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  .who__meta{
    display: grid;
    line-height: 1.1;
    max-width: 220px;
  }
  .who__name{
    font-weight: 800;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .who__role{
    margin-top: 2px;
    font-size: 11px;
    color: var(--muted);
    text-transform: capitalize;
  }
  
  .avatar{
    height: 32px;
    width: 32px;
    border-radius: 999px;
    background: #111827;
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 11px;
    font-weight: 800;
    border: 1px solid rgba(17,24,39,0.14);
  }
  .avatar.lg{ height: 40px; width: 40px; font-size: 12px; }
  
  /* Buttons */
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
  }
  .btn:hover{ transform: translateY(-1px); background: #0b1220; }
  .btn:active{ transform: translateY(0px); }
  .btn.ghost{
    background: #fff;
    color: var(--ink);
    border: 1px solid var(--line);
  }
  .btn.ghost:hover{ background: rgba(17,24,39,0.02); }
  
  .iconBtn{
    width: 36px;
    height: 36px;
    border-radius: var(--r12);
    border: 1px solid var(--line);
    background: #fff;
    display: grid;
    place-items: center;
    cursor: pointer;
    transition: background 120ms ease, transform 120ms ease;
  }
  .iconBtn svg{ width: 18px; height: 18px; color: var(--ink); }
  .iconBtn:hover{ background: rgba(17,24,39,0.02); transform: translateY(-1px); }
  
  /* Sidebar */
  .sidebar{
    position: fixed;
    top: 56px;
    left: 0;
    bottom: 0;
    width: 260px;
    z-index: 40;
    background: var(--panel);
    backdrop-filter: blur(12px);
    border-right: 1px solid var(--line);
  }
  .sidebar__inner{
    height: 100%;
    padding: 12px;
    display: grid;
    gap: 12px;
    overflow: auto;
  }
  
  .profile{
    border: 1px solid var(--line);
    border-radius: var(--r16);
    padding: 12px;
    background: #fff;
  }
  .profile__row{
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .profile__meta{
    display: grid;
    line-height: 1.1;
    min-width: 0;
  }
  .profile__name{
    font-weight: 800;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .profile__role{
    margin-top: 3px;
    font-size: 11px;
    color: var(--muted);
    text-transform: capitalize;
  }
  .profile__actions{
    margin-top: 10px;
    display: grid;
    gap: 8px;
  }
  .linkBtn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 10px;
    border-radius: var(--r12);
    text-decoration: none;
    font-size: 12px;
    font-weight: 800;
    color: #fff;
    background: #111827;
    border: 1px solid rgba(17,24,39,0.14);
  }
  .linkBtn:hover{ background: #0b1220; }
  .linkBtn.ghost{
    background: #fff;
    color: var(--ink);
    border: 1px solid var(--line);
  }
  .linkBtn.ghost:hover{ background: rgba(17,24,39,0.02); }
  
  /* Groups + nav */
  .group{
    border: 1px solid var(--line);
    border-radius: var(--r16);
    padding: 10px;
    background: #fff;
  }
  .group__title{
    font-size: 11px;
    color: var(--muted);
    font-weight: 800;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .nav{ display: grid; gap: 6px; }
  
  .nav__link{
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: var(--r14);
    text-decoration: none;
    color: var(--ink);
    font-size: 13px;
    border: 1px solid transparent;
    background: transparent;
    transition: background 120ms ease, border-color 120ms ease;
  }
  .nav__link:hover{
    background: var(--soft);
    border-color: rgba(17,24,39,0.06);
  }
  .nav__link.active{
    background: var(--soft2);
    border-color: rgba(17,24,39,0.12);
  }
  .nav__icon{ width: 18px; height: 18px; color: #4b5563; flex: 0 0 auto; }
  .nav__icon svg{ width: 18px; height: 18px; }
  .nav__label{ font-weight: 700; }
  
  .buttonLink{ width: 100%; cursor: pointer; text-align: left; }
  .danger{ color: #991b1b; }
  .nav__link.danger:hover{
    background: rgba(153,27,27,0.06);
    border-color: rgba(153,27,27,0.12);
  }
  
  /* Mobile drawer */
  .overlay{
    position: fixed;
    inset: 0;
    z-index: 80;
    background: rgba(17,24,39,0.45);
    display: flex;
  }
  .sheet{
    width: min(86vw, 320px);
    height: 100%;
    background: rgba(255,255,255,0.96);
    backdrop-filter: blur(12px);
    border-right: 1px solid var(--line);
    padding: 14px;
    display: grid;
    grid-template-rows: auto 1fr;
    box-shadow: var(--shadow);
  }
  .sheet__head{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--line);
  }
  .sheet__brand{ display: flex; align-items: center; gap: 10px; }
  .sheet__brandText{ line-height: 1.1; }
  .sheet__title{ font-weight: 800; font-size: 13px; }
  .sheet__sub{ font-size: 11px; color: var(--muted); margin-top: 2px; }
  
  .sheet__body{ padding-top: 12px; display: grid; gap: 12px; }
  .sheet__profile{ display: flex; align-items: center; gap: 10px; }
  .sheet__meta{ display: grid; line-height: 1.1; min-width: 0; }
  .sheet__name{ font-weight: 800; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .sheet__role{ margin-top: 3px; font-size: 11px; color: var(--muted); text-transform: capitalize; }
  
  .sheet__groups{ display: grid; gap: 12px; }
  .sheet__link{
    display: block;
    padding: 10px 12px;
    border-radius: var(--r14);
    text-decoration: none;
    color: var(--ink);
    border: 1px solid var(--line);
    background: #fff;
    font-size: 13px;
    font-weight: 800;
  }
  .sheet__link:hover{ background: rgba(17,24,39,0.02); }
  .sheet__link.active{
    background: var(--soft2);
    border-color: rgba(17,24,39,0.12);
  }
  .sheet__link.danger{
    color: #991b1b;
    border-color: rgba(153,27,27,0.18);
    background: #fff;
  }
  
  /* Responsive helpers */
  .hideMobile{ display: none; }
  .showMobile{ display: grid; }
  @media (min-width: 900px){
    .hideMobile{ display: block; }
    .showMobile{ display: none; }
  }
  </style>