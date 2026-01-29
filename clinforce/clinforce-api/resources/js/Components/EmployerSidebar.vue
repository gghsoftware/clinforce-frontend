<!-- resources/js/Components/EmployerSidebar.vue -->
<script setup>
    import { computed, onBeforeUnmount, onMounted, ref } from "vue";
    import { RouterLink, useRoute, useRouter } from "vue-router";
    import { logout } from "@/lib/auth";
    
    const route = useRoute();
    const router = useRouter();
    
    const open = ref(false);
    
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
    
    const displayName = computed(() => {
      const u = user.value || {};
      return u.name || u.company_name || u.email || "Employer";
    });
    
    const roleLabel = computed(() => {
      const r = String(user.value?.role || "employer");
      if (r === "admin") return "Admin";
      if (r === "agency") return "Agency";
      return "Employer";
    });
    
    const menu = computed(() => {
      // route names based on your router
      return [
        { name: "Dashboard", to: { name: "employer.dashboard" }, icon: "home" },
        { name: "My roles", to: { name: "employer.jobs" }, icon: "briefcase" },
        { name: "Applicants", to: { name: "applicants.list" }, icon: "users" },
        { name: "Talent search", to: { name: "employer.talentsearch" }, icon: "search" },
        { name: "Messages", to: { name: "employer.messages" }, icon: "chat" },
        { name: "Interviews", to: { name: "employer.interviews" }, icon: "calendar" },
        { name: "Billing", to: { name: "employer.billing" }, icon: "card" },
      ];
    });
    
    function isActive(item) {
      const n = route.name ? String(route.name) : "";
      const target = item?.to?.name ? String(item.to.name) : "";
      if (!target) return false;
    
      // group job routes under employer.jobs
      if (target === "employer.jobs") return n.startsWith("employer.jobs");
      if (target === "applicants.list") return n.startsWith("applicants.") || n.startsWith("employer.applicants");
      return n === target;
    }
    
    async function doLogout() {
      await logout();
      open.value = false;
      router.push({ name: "auth.login" });
    }
    
    function closeMobile() {
      open.value = false;
    }
    </script>
    
    <template>
      <!-- mobile top bar -->
      <div class="topbar">
        <button class="burger" type="button" @click="open = true" aria-label="Open sidebar">
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
    
      <!-- overlay for mobile -->
      <div v-if="open" class="overlay" @click="open = false"></div>
    
      <!-- sidebar -->
      <aside class="sidebar" :class="{ open }">
        <div class="sideTop">
          <RouterLink class="brandRow" :to="{ name: 'employer.dashboard' }" @click="closeMobile">
            <div class="logoLg" aria-hidden="true">C</div>
            <div class="brandText">
              <div class="brandName">Clinforce</div>
              <div class="brandSub">{{ roleLabel }}</div>
            </div>
          </RouterLink>
    
          <button class="closeBtn" type="button" @click="open = false" aria-label="Close sidebar">
            ✕
          </button>
        </div>
    
        <div class="profile">
          <div class="avatar" aria-hidden="true">{{ String(displayName).slice(0, 1).toUpperCase() }}</div>
          <div class="profileMeta">
            <div class="profileName" :title="displayName">{{ displayName }}</div>
            <div class="profileRole">{{ roleLabel }}</div>
          </div>
        </div>
    
        <nav class="nav">
          <RouterLink
            v-for="item in menu"
            :key="item.name"
            class="navItem"
            :class="{ active: isActive(item) }"
            :to="item.to"
            @click="closeMobile"
          >
            <span class="ico" aria-hidden="true">
              <svg v-if="item.icon === 'home'" viewBox="0 0 24 24" fill="none">
                <path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-10.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
              </svg>
              <svg v-else-if="item.icon === 'briefcase'" viewBox="0 0 24 24" fill="none">
                <path d="M9 7V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <path d="M4 8h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="M4 12h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
              <svg v-else-if="item.icon === 'users'" viewBox="0 0 24 24" fill="none">
                <path d="M16 18c0-2.2-1.8-4-4-4s-4 1.8-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <path d="M12 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/>
                <path d="M20 18c0-1.6-1.1-3-2.6-3.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <path d="M17 5.5a3 3 0 0 1 0 6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
              <svg v-else-if="item.icon === 'search'" viewBox="0 0 24 24" fill="none">
                <path d="M10.5 18.5a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7"/>
                <path d="M16.7 16.7 21 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
              <svg v-else-if="item.icon === 'chat'" viewBox="0 0 24 24" fill="none">
                <path d="M7 18l-3 3V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="M8 9h8M8 12h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
              <svg v-else-if="item.icon === 'calendar'" viewBox="0 0 24 24" fill="none">
                <path d="M7 3v3M17 3v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                <path d="M4 7h16v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="M8 11h8M8 15h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
              <svg v-else viewBox="0 0 24 24" fill="none">
                <path d="M3 7h18v10H3V7Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="M7 15h2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
              </svg>
            </span>
    
            <span class="label">{{ item.name }}</span>
          </RouterLink>
        </nav>
    
        <div class="sideBottom">
          <RouterLink class="miniCta" :to="{ name: 'employer.jobs.create' }" @click="closeMobile">
            + Post new role
          </RouterLink>
    
          <button class="logout" type="button" @click="doLogout">Logout</button>
        </div>
      </aside>
    </template>
    
    <style scoped>
    /* Layout wrapper usage:
       Put sidebar next to content in your Employer layout (see next file). */
    
    .topbar{
      display:none;
    }
    
    /* Mobile topbar */
    @media (max-width: 980px){
      .topbar{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        height:56px;
        padding: 0 14px;
        border-bottom: 1px solid rgba(17,24,39,.08);
        background:#fff;
        position: sticky;
        top: 0;
        z-index: 40;
      }
      .burger{
        width:42px;
        height:42px;
        border-radius:12px;
        border:1px solid #e5e7eb;
        background:#fff;
        display:grid;
        place-items:center;
        cursor:pointer;
      }
      .burger span{
        display:block;
        width:18px;
        height:2px;
        background:#111827;
        border-radius: 999px;
        margin: 2px 0;
      }
      .brand{
        display:flex;
        align-items:center;
        gap:10px;
        min-width:0;
      }
      .logo{
        width:34px;
        height:34px;
        border-radius:12px;
        background:#111827;
        color:#fff;
        display:grid;
        place-items:center;
        font-weight:950;
        font-size:12px;
      }
      .brandText{ min-width:0; }
      .brandName{
        font-weight:950;
        font-size:12px;
        color:#111827;
        line-height: 1.1;
      }
      .brandSub{
        font-size:11px;
        color:#6b7280;
        font-weight:900;
        line-height: 1.1;
      }
      .quickCta{
        height:36px;
        padding: 0 12px;
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
        white-space: nowrap;
      }
      .quickCta:hover{ background:#0b1220; }
    }
    
    /* overlay */
    .overlay{
      display:none;
    }
    @media (max-width: 980px){
      .overlay{
        display:block;
        position:fixed;
        inset:0;
        background: rgba(0,0,0,.35);
        z-index: 45;
      }
    }
    
    /* Sidebar */
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
    
    /* Desktop: fixed column; Mobile: slide-in drawer */
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
      .sidebar.open{
        transform: translateX(0);
      }
    }
    
    .sideTop{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
    }
    
    .brandRow{
      display:flex;
      align-items:center;
      gap: 10px;
      text-decoration:none;
      color: inherit;
      min-width: 0;
    }
    
    .logoLg{
      width:40px;
      height:40px;
      border-radius:14px;
      background:#111827;
      color:#fff;
      display:grid;
      place-items:center;
      font-weight:950;
      font-size:12px;
      border: 1px solid rgba(17,24,39,.16);
    }
    .brandText{ min-width:0; }
    .brandName{
      font-weight:950;
      font-size:12px;
      color:#111827;
      line-height: 1.1;
    }
    .brandSub{
      margin-top: 2px;
      font-size:11px;
      color:#6b7280;
      font-weight:900;
      line-height: 1.1;
    }
    
    .closeBtn{
      display:none;
    }
    @media (max-width: 980px){
      .closeBtn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:40px;
        height:40px;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        background:#fff;
        cursor:pointer;
        color:#111827;
        font-weight:900;
      }
    }
    
    /* Profile */
    .profile{
      display:flex;
      align-items:center;
      gap: 10px;
      border: 1px solid rgba(17,24,39,.08);
      border-radius: 16px;
      padding: 10px;
      background: rgba(17,24,39,.02);
    }
    .avatar{
      width:40px;
      height:40px;
      border-radius: 14px;
      background:#111827;
      color:#fff;
      display:grid;
      place-items:center;
      font-weight:950;
    }
    .profileMeta{ min-width:0; }
    .profileName{
      font-size: 12px;
      font-weight: 950;
      color:#111827;
      white-space: nowrap;
      overflow:hidden;
      text-overflow: ellipsis;
      max-width: 170px;
    }
    .profileRole{
      margin-top: 2px;
      font-size: 11px;
      color:#6b7280;
      font-weight:900;
    }
    
    /* Nav */
    .nav{
      display:flex;
      flex-direction: column;
      gap: 6px;
      padding-top: 4px;
    }
    .navItem{
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 10px 10px;
      border-radius: 14px;
      text-decoration:none;
      color:#111827;
      border: 1px solid transparent;
      transition: background 120ms ease, border-color 120ms ease, transform 120ms ease;
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
    
    .ico{
      width:18px;
      height:18px;
      color:#111827;
      flex: 0 0 auto;
    }
    .ico svg{
      width:18px;
      height:18px;
    }
    
    .label{
      font-size: 12px;
      font-weight: 950;
      color:#111827;
    }
    
    /* Bottom */
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
    .logout:hover{
      background: rgba(254,226,226,.65);
    }
    </style>
    