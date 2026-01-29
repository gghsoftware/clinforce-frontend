<!-- resources/js/Pages/Employer/Messages.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from "vue";
  import { RouterLink, useRoute } from "vue-router";
  import UiCard from "../../Components/UiCard.vue";
  import { logout } from "@/lib/auth";
  import api from "@/lib/api";
  
  /** ===== Sidebar ===== */
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
    loadConversations();
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
  
  /** ============================
   *  Conversations (wired)
   * ============================ */
  const loading = ref(false);
  const threadLoading = ref(false);
  const error = ref("");
  const q = ref("");
  
  const conversations = ref([]);
  const activeId = ref(null);
  const active = ref(null);
  
  function unwrap(resData) {
    return resData?.data ?? resData;
  }
  function unwrapArray(resData) {
    const body = unwrap(resData);
    if (Array.isArray(body)) return body;
    if (Array.isArray(body?.data)) return body.data;
    return [];
  }
  function fmtTime(v) {
    if (!v) return "";
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return String(v);
    return d.toLocaleString();
  }
  function initialsFrom(nameOrEmail) {
    const s = String(nameOrEmail || "C").trim();
    if (!s) return "C";
    const parts = s.split(/\s+/).filter(Boolean);
    const a = parts[0]?.[0] || "C";
    const b = parts.length > 1 ? parts[1]?.[0] : (parts[0]?.[1] || "");
    return (a + b).toUpperCase();
  }
  
  async function loadConversations() {
    loading.value = true;
    error.value = "";
    try {
      // IMPORTANT: keep as-is (your backend is mounted on /api, api lib may already include baseURL)
      const res = await api.get("/api/conversations");
      const list = unwrapArray(res?.data);
      conversations.value = list;
  
      if (!activeId.value && list[0]?.id) {
        activeId.value = list[0].id;
        await loadConversation(activeId.value);
      }
    } catch (e) {
      error.value = e?.response?.data?.message || "Failed to load conversations";
    } finally {
      loading.value = false;
    }
  }
  
  async function loadConversation(id) {
    if (!id) return;
    threadLoading.value = true;
    error.value = "";
    try {
      const res = await api.get(`/api/conversations/${id}`);
      const body = unwrap(res?.data);
      active.value = body;
    } catch (e) {
      error.value = e?.response?.data?.message || "Failed to load conversation";
      active.value = null;
    } finally {
      threadLoading.value = false;
    }
  }
  
  const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return conversations.value;
    return conversations.value.filter((c) => {
      const hay = `${c.title || ""} ${c.last_message?.body || ""} ${c.last_message_body || ""}`.toLowerCase();
      return hay.includes(term);
    });
  });
  
  async function pickConversation(id) {
    activeId.value = id;
    await loadConversation(id);
  }
  
  /** ===== Composer ===== */
  const draft = ref("");
  const sending = ref(false);
  
  async function send() {
    if (!active.value) return;
    const t = draft.value.trim();
    if (!t) return;
  
    sending.value = true;
    try {
      await api.post(`/api/conversations/${active.value.id}/messages`, { body: t });
      draft.value = "";
      await loadConversation(active.value.id);
      await loadConversations();
    } catch (e) {
      error.value = e?.response?.data?.message || "Failed to send message";
    } finally {
      sending.value = false;
    }
  }
  
  /** ===== New conversation modal (dropdown multi-select) ===== */
  const newOpen = ref(false);
  const newLoading = ref(false);
  
  const userSearch = ref("");
  const userOptions = ref([]);
  const usersLoading = ref(false);
  let usersTimer = null;
  
  const pickedUserIds = ref([]);
  const newSubject = ref("");
  const newFirstMessage = ref("");
  
  function labelUser(u) {
    const name = u?.display_name || u?.email || u?.phone || `User #${u?.id}`;
    const meta = [];
    if (u?.role) meta.push(u.role);
    if (u?.email) meta.push(u.email);
    if (!u?.email && u?.phone) meta.push(u.phone);
    return meta.length ? `${name} • ${meta.join(" • ")}` : name;
  }
  
  async function loadUserOptions() {
    usersLoading.value = true;
    try {
      const res = await api.get("/api/users", {
        params: { role: "applicant", q: userSearch.value || "" },
      });
      userOptions.value = unwrapArray(res?.data);
    } finally {
      usersLoading.value = false;
    }
  }
  
  function debounceLoadUsers() {
    clearTimeout(usersTimer);
    usersTimer = setTimeout(loadUserOptions, 250);
  }
  
  function togglePick(userId) {
    const id = Number(userId);
    if (!Number.isFinite(id)) return;
    if (pickedUserIds.value.includes(id)) {
      pickedUserIds.value = pickedUserIds.value.filter((x) => x !== id);
    } else {
      pickedUserIds.value = [...pickedUserIds.value, id];
    }
  }
  function removePicked(id) {
    pickedUserIds.value = pickedUserIds.value.filter((x) => x !== id);
  }
  
  function openNewConversation() {
    newOpen.value = true;
    userSearch.value = "";
    userOptions.value = [];
    pickedUserIds.value = [];
    newSubject.value = "";
    newFirstMessage.value = "";
    loadUserOptions();
  }
  
  async function createConversation() {
    if (!pickedUserIds.value.length) return;
  
    newLoading.value = true;
    error.value = "";
    try {
      await api.post("/api/conversations", {
        participant_user_ids: pickedUserIds.value,
        subject: newSubject.value || null,
        first_message: newFirstMessage.value || null,
      });
      newOpen.value = false;
      await loadConversations();
    } catch (e) {
      error.value = e?.response?.data?.message || "Failed to create conversation";
    } finally {
      newLoading.value = false;
    }
  }
  
  /** ===== Display helpers for list ===== */
  function convTitle(c) {
    const t = c?.title || c?.subject || c?.display_name || "";
    return t || `Conversation #${c?.id ?? ""}`;
  }
  function convSnippet(c) {
    return c?.last_message?.body || c?.last_message_body || "";
  }
  function convTime(c) {
    return fmtTime(c?.last_message?.created_at || c?.updated_at || c?.created_at);
  }
  function convInitials(c) {
    return initialsFrom(convTitle(c));
  }
  </script>
  
  <template>
    <!-- Match Interviews page background + spacing (prevents overlaps) -->
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
  
          <RouterLink class="quickLink" :to="{ name: 'employer.messages' }">Messages</RouterLink>
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
          <div class="content">
            <div class="head">
              <div class="titleBlock">
                <div class="eyebrow">Employer</div>
                <h1 class="h1">Messages</h1>
                <p class="sub">Connected to your backend conversations.</p>
              </div>
            </div>
  
            <UiCard>
              <div class="wrap">
                <!-- Left list -->
                <aside class="listPane">
                  <div class="listTop">
                    <div class="searchWrap">
                      <svg class="searchIcon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M10.5 18.5a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7" />
                        <path d="M16.7 16.7 21 21" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                      </svg>
                      <input v-model="q" class="search" placeholder="Search messages" />
                    </div>
  
                    <div class="listActions">
                      <button class="btn" type="button" @click="loadConversations" :disabled="loading">
                        {{ loading ? "Refreshing…" : "Refresh" }}
                      </button>
                      <button class="btn btnPrimary" type="button" @click="openNewConversation">
                        New
                      </button>
                    </div>
  
                    <div v-if="error" class="alert alertError">{{ error }}</div>
                  </div>
  
                  <div class="listBody">
                    <div v-if="loading" class="emptyList">Loading…</div>
  
                    <button
                      v-else
                      v-for="c in filtered"
                      :key="c.id"
                      class="item"
                      :class="{ active: c.id === activeId }"
                      type="button"
                      @click="pickConversation(c.id)"
                    >
                      <div class="itemRow">
                        <div class="avatar">{{ convInitials(c) }}</div>
  
                        <div class="itemMeta">
                          <div class="itemLine1">
                            <div class="name" :title="convTitle(c)">{{ convTitle(c) }}</div>
                            <div class="time">{{ convTime(c) }}</div>
                          </div>
  
                          <div class="snippet" :title="convSnippet(c)">{{ convSnippet(c) || "—" }}</div>
                          <div class="job" :title="c.subject || ''">{{ c.subject || "" }}</div>
                        </div>
                      </div>
                    </button>
  
                    <div v-if="!loading && !filtered.length" class="emptyList">No conversations.</div>
                  </div>
                </aside>
  
                <!-- Thread -->
                <section class="thread">
                  <div v-if="threadLoading" class="threadEmpty">Loading conversation…</div>
                  <div v-else-if="!active" class="threadEmpty">Select a conversation.</div>
  
                  <template v-else>
                    <div class="threadTop">
                      <div class="threadWho">
                        <div class="whoName" :title="active.title || active.subject || ''">
                          {{ active.title || active.subject || `Conversation #${active.id}` }}
                        </div>
                        <div class="whoJob" :title="active.subject || ''">
                          {{ active.subject || "" }}
                        </div>
                      </div>
  
                      <div class="threadActions">
                        <button class="btn" type="button">View profile</button>
                        <button class="btn btnPrimary" type="button">Schedule interview</button>
                      </div>
                    </div>
  
                    <div class="threadBody">
                      <div
                        v-for="(m, idx) in (active.messages || [])"
                        :key="m.id || idx"
                        class="msgRow"
                        :class="{ me: m.is_mine || m.from === 'me' }"
                      >
                        <div class="msgWrap">
                          <div class="bubble" :class="{ me: m.is_mine || m.from === 'me' }">
                            {{ m.body || m.text || "" }}
                          </div>
                          <div class="metaLine" :class="{ me: m.is_mine || m.from === 'me' }">
                            {{ (m.is_mine || m.from === "me") ? "You" : "User" }} • {{ fmtTime(m.created_at || m.at) }}
                          </div>
                        </div>
                      </div>
  
                      <div v-if="!(active.messages || []).length" class="threadEmpty">No messages yet.</div>
                    </div>
  
                    <div class="composer">
                      <textarea v-model="draft" rows="2" class="textarea" placeholder="Write a message…" />
                      <button class="btn btnPrimary" type="button" @click="send" :disabled="sending || !draft.trim()">
                        {{ sending ? "Sending…" : "Send" }}
                      </button>
                    </div>
                  </template>
                </section>
              </div>
            </UiCard>
          </div>
        </main>
  
        <!-- New conversation modal -->
        <div v-if="newOpen" class="modalOverlay" @click.self="newOpen = false">
          <div class="modalCard" role="dialog" aria-modal="true">
            <div class="modalHead">
              <div>
                <div class="modalTitle">New conversation</div>
                <div class="modalSub">Choose participants and send the first message.</div>
              </div>
              <button class="btn" type="button" @click="newOpen = false">Close</button>
            </div>
  
            <div class="field">
              <label class="lbl">Search applicants</label>
              <div class="row2">
                <input
                  v-model="userSearch"
                  class="input"
                  placeholder="Search by email / phone / ID…"
                  @input="debounceLoadUsers"
                />
                <button class="btn" type="button" @click="loadUserOptions" :disabled="usersLoading">
                  {{ usersLoading ? "Loading…" : "Search" }}
                </button>
              </div>
  
              <div class="dropdown">
                <div v-if="usersLoading" class="ddEmpty">Loading…</div>
  
                <button
                  v-else
                  v-for="u in userOptions"
                  :key="u.id"
                  type="button"
                  class="ddItem"
                  :class="{ picked: pickedUserIds.includes(Number(u.id)) }"
                  @click="togglePick(u.id)"
                >
                  <span class="ddMain">{{ labelUser(u) }}</span>
                  <span class="ddMark">{{ pickedUserIds.includes(Number(u.id)) ? "✓" : "" }}</span>
                </button>
  
                <div v-if="!usersLoading && !userOptions.length" class="ddEmpty">
                  No users found.
                </div>
              </div>
            </div>
  
            <div class="field">
              <label class="lbl">Selected</label>
              <div class="chips">
                <div v-if="!pickedUserIds.length" class="mutedTiny">No participants selected.</div>
  
                <button
                  v-for="id in pickedUserIds"
                  :key="id"
                  type="button"
                  class="chip"
                  @click="removePicked(id)"
                  title="Remove"
                >
                  User #{{ id }} ✕
                </button>
              </div>
              <div class="mutedTiny">Click a chip to remove.</div>
            </div>
  
            <div class="field">
              <label class="lbl">Subject (optional)</label>
              <input v-model="newSubject" class="input" placeholder="e.g. Interview follow-up" />
            </div>
  
            <div class="field">
              <label class="lbl">First message</label>
              <textarea v-model="newFirstMessage" class="textarea2" rows="3" placeholder="Write a message…"></textarea>
            </div>
  
            <div class="modalFoot">
              <button class="btn" type="button" @click="newOpen = false" :disabled="newLoading">Cancel</button>
              <button
                class="btn btnPrimary"
                type="button"
                @click="createConversation"
                :disabled="newLoading || !pickedUserIds.length"
              >
                {{ newLoading ? "Creating…" : "Create" }}
              </button>
            </div>
          </div>
        </div>
  
      </div>
    </div>
  </template>
  
  <style scoped>
  /* Match Interviews page background + typography */
  .pageBg{
    min-height:100vh;
    overflow-x:hidden;
    background: linear-gradient(180deg,#fafaf9 0%,#f5f5f4 100%);
    color:#111827;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  }
  
  .shell, .shell *{ box-sizing:border-box; }
  .shell{ min-height:100vh; display:flex; width:100%; }
  
  /* main spacing (prevents overlap with sidebar/topbar on different screens) */
  .main{ flex:1; min-width:0; padding:16px; }
  @media (max-width: 980px){ .main{ padding:12px; } }
  
  .content{
    max-width: 1280px;
    margin: 0 auto;
    padding: 8px 0;
    display:flex;
    flex-direction:column;
    gap: 12px;
  }
  
  /* Header styles (same as interviews) */
  .head{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  .titleBlock{ min-width:0; }
  .eyebrow{ font-size:11px; font-weight:900; letter-spacing:.10em; text-transform:uppercase; color:#6b7280; }
  .h1{ margin:6px 0 0; font-size:22px; font-weight:900; letter-spacing:-0.02em; }
  .sub{ margin:6px 0 0; font-size:12px; color:#6b7280; }
  
  /* Buttons (same system as candidate pages) */
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
  
  /* alerts */
  .alert{ border-radius:14px; padding:10px 12px; font-size:12px; line-height:1.45; }
  .alertError{ background:#fef2f2; border:1px solid rgba(254,202,202,0.95); color:#b91c1c; font-weight:900; }
  
  /* layout card */
  .wrap{
    display:flex;
    min-height: 560px;
    border:1px solid rgba(17,24,39,.08);
    border-radius:18px;
    overflow:hidden;
    background:#fff;
  }
  
  /* left list */
  .listPane{
    width: 360px;
    min-width: 280px;
    border-right:1px solid rgba(17,24,39,.08);
    display:flex;
    flex-direction:column;
  }
  .listTop{
    padding:12px;
    border-bottom:1px solid rgba(17,24,39,.08);
    display:grid;
    gap:10px;
  }
  .searchWrap{ position:relative; }
  .searchIcon{
    position:absolute; left:12px; top:50%;
    transform:translateY(-50%);
    width:18px; height:18px;
    color:#9ca3af;
    pointer-events:none;
  }
  .search{
    width:100%;
    height:42px;
    padding:0 12px 0 40px;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    font-size:12px;
    font-weight:800;
    outline:none;
  }
  .listActions{ display:flex; gap:10px; flex-wrap:wrap; }
  
  .listBody{ overflow:auto; max-height: calc(560px - 132px); }
  
  .item{
    width:100%;
    text-align:left;
    padding:12px;
    border:0;
    border-bottom:1px solid rgba(17,24,39,.06);
    background:#fff;
    cursor:pointer;
    transition: background 120ms ease;
  }
  .item:hover{ background: rgba(17,24,39,.02); }
  .item.active{ background: rgba(17,24,39,.05); }
  
  .itemRow{ display:flex; gap:10px; align-items:flex-start; }
  .avatar{
    width:36px; height:36px;
    border-radius:14px;
    background:#111827; color:#fff;
    display:grid; place-items:center;
    font-size:11px; font-weight:900;
    flex:0 0 auto;
  }
  .itemMeta{ min-width:0; flex:1; display:grid; gap:4px; }
  .itemLine1{ display:flex; align-items:center; justify-content:space-between; gap:8px; }
  .name{
    font-size:13px; font-weight:900;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  }
  .time{ font-size:11px; color:#6b7280; font-weight:900; white-space:nowrap; }
  .snippet{ font-size:12px; color:#374151; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .job{ font-size:11px; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .emptyList{ padding:12px; font-size:12px; color:#6b7280; }
  
  /* thread */
  .thread{ flex:1; min-width:0; display:flex; flex-direction:column; }
  .threadEmpty{ padding:16px; font-size:12px; color:#6b7280; }
  
  .threadTop{
    padding:14px;
    border-bottom:1px solid rgba(17,24,39,.08);
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
    background:#fff;
  }
  .threadWho{ min-width:0; }
  .whoName{ font-size:13px; font-weight:900; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .whoJob{ margin-top:2px; font-size:12px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .threadActions{ display:flex; gap:10px; flex-wrap:wrap; }
  
  /* bubbles */
  .threadBody{
    flex:1;
    overflow:auto;
    padding:14px;
    background:#f7f7f8;
    display:grid;
    gap:10px;
  }
  .msgRow{ display:flex; justify-content:flex-start; }
  .msgRow.me{ justify-content:flex-end; }
  .msgWrap{ max-width:min(520px, 78%); }
  
  .bubble{
    padding:10px 12px;
    border-radius:18px;
    border:1px solid rgba(17,24,39,.10);
    background:#fff;
    color:#111827;
    font-size:13px;
    line-height:1.35;
    border-bottom-left-radius:8px;
  }
  .bubble.me{
    background:#111827;
    color:#fff;
    border-color: rgba(17,24,39,.18);
    border-bottom-right-radius:8px;
    border-bottom-left-radius:18px;
  }
  .metaLine{ margin-top:4px; font-size:11px; color:#6b7280; font-weight:900; }
  .metaLine.me{ text-align:right; }
  
  /* composer */
  .composer{
    padding:12px;
    border-top:1px solid rgba(17,24,39,.08);
    background:#fff;
    display:flex;
    gap:10px;
    align-items:flex-end;
  }
  .textarea{
    flex:1;
    border-radius:16px;
    border:1px solid rgba(209,213,219,0.95);
    padding:10px 12px;
    font-size:13px;
    outline:none;
    resize:none;
    min-height:42px;
    max-height:120px;
    line-height:1.35;
  }
  
  /* responsive: stack panes */
  @media (max-width: 860px){
    .wrap{ flex-direction:column; min-height:720px; }
    .listPane{ width:100%; min-width:0; border-right:0; border-bottom:1px solid rgba(17,24,39,.08); }
    .listBody{ max-height:260px; }
  }
  
  /* ===== Modal (kept functionality, styled to match) ===== */
  .modalOverlay{
    position:fixed; inset:0;
    background: rgba(17,24,39,.38);
    display:grid; place-items:center;
    z-index:120;
    padding:18px;
  }
  .modalCard{
    width:min(720px, 96vw);
    background:#fff;
    border-radius:18px;
    border:1px solid rgba(17,24,39,.10);
    box-shadow: 0 28px 70px rgba(0,0,0,.22);
    padding:14px;
  }
  .modalHead{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:6px 6px 10px; }
  .modalTitle{ font-size:13px; font-weight:900; }
  .modalSub{ margin-top:2px; font-size:12px; color:#6b7280; font-weight:900; }
  
  .field{ padding:8px 6px; display:grid; gap:8px; }
  .lbl{ font-size:11px; font-weight:900; color:#6b7280; text-transform:uppercase; letter-spacing:.08em; }
  .row2{ display:flex; gap:10px; align-items:center; }
  
  .input{
    width:100%;
    height:42px;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    padding:0 12px;
    font-size:13px;
    outline:none;
  }
  .textarea2{
    width:100%;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    padding:10px 12px;
    font-size:13px;
    outline:none;
    resize:vertical;
  }
  
  .dropdown{
    border:1px solid rgba(17,24,39,.10);
    border-radius:14px;
    overflow:hidden;
    max-height:220px;
    overflow-y:auto;
    background:#fff;
  }
  .ddItem{
    width:100%;
    text-align:left;
    border:0;
    background:#fff;
    padding:10px 12px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    cursor:pointer;
    border-bottom:1px solid rgba(17,24,39,.06);
  }
  .ddItem:hover{ background: rgba(17,24,39,.03); }
  .ddItem.picked{ background: rgba(17,24,39,.06); }
  .ddMain{ font-size:12px; font-weight:900; color:#111827; }
  .ddMark{ font-size:12px; font-weight:900; }
  .ddEmpty{ padding:12px; font-size:12px; color:#6b7280; font-weight:900; }
  
  .chips{ display:flex; flex-wrap:wrap; gap:8px; }
  .chip{
    border:1px solid rgba(17,24,39,.12);
    background: rgba(17,24,39,.04);
    color:#111827;
    border-radius:999px;
    padding:8px 10px;
    font-size:12px;
    font-weight:900;
    cursor:pointer;
  }
  .mutedTiny{ font-size:11px; color:#6b7280; font-weight:900; }
  .modalFoot{ display:flex; justify-content:flex-end; gap:10px; padding:10px 6px 6px; }
  
  /* ===== Sidebar + mobile topbar (same as Interviews) ===== */
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
    .quickLink{ height:36px; padding:0 12px; border-radius:999px; border:1px solid rgba(229,231,235,0.95); background:#fff; color:#111827; font-size:12px; font-weight:900; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; }
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
  .profileRole{ margin-top:2px; font-size:11px; color:#6b7280; font-weight:900; }
  .nav{ display:flex; flex-direction:column; gap:6px; padding-top:4px; }
  .navItem{ display:flex; align-items:center; padding:10px; border-radius:14px; text-decoration:none; color:#111827; border:1px solid transparent; font-size:12px; font-weight:900; transition:background 120ms ease, border-color 120ms ease, transform 120ms ease; }
  .navItem:hover{ background:rgba(17,24,39,.03); border-color:rgba(17,24,39,.06); transform:translateY(-1px); }
  .navItem.active{ background:rgba(17,24,39,.06); border-color:rgba(17,24,39,.12); }
  .sideBottom{ margin-top:auto; display:flex; flex-direction:column; gap:10px; padding-top:10px; border-top:1px solid rgba(17,24,39,.08); }
  .miniCta{ height:40px; border-radius:999px; background:#111827; color:#fff; text-decoration:none; font-size:12px; font-weight:900; display:inline-flex; align-items:center; justify-content:center; }
  .logout{ height:40px; border-radius:999px; border:1px solid rgba(239,68,68,.25); background:#fff; color:#991b1b; font-size:12px; font-weight:900; cursor:pointer; }
  </style>