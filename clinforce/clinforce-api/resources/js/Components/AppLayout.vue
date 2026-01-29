<!-- resources/js/Components/AppLayout.vue -->
<template>
  <div class="layout" :class="{ 'layout--authed': isAuthed }">
    <!-- Only show TopBar (and sidebar) when authenticated -->
    <TopBar v-if="isAuthed" />

    <main class="main" :class="{ 'main--noTopbar': !isAuthed }">
      <!-- Authed pages: normal container spacing -->
      <div v-if="isAuthed" class="container">
        <slot />
      </div>

      <!-- Guest/auth pages: center content -->
      <div v-else class="center">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import TopBar from "@/Components/TopBar.vue";

const authed = ref(false);

function safeParse(raw) {
  try {
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function getToken() {
  // ✅ match the rest of your app (TopBar + router)
  return localStorage.getItem("auth_token") || localStorage.getItem("CLINFORCE_TOKEN");
}

function checkAuth() {
  const token = getToken();
  const user = safeParse(localStorage.getItem("auth_user"));
  authed.value = Boolean(token && user && (user.id || user.email || user.role));
}

function onStorage(e) {
  // ✅ include CLINFORCE_TOKEN and auth_user updates
  if (
    e.key === "auth_token" ||
    e.key === "CLINFORCE_TOKEN" ||
    e.key === "auth_user"
  ) {
    checkAuth();
  }
}

// For same-tab updates (storage event doesn't fire in same tab)
function onAuthChanged() {
  checkAuth();
}

// ✅ extra: when tab regains focus, re-check (covers cases where localStorage changes via redirects/login flows)
function onFocus() {
  checkAuth();
}

onMounted(() => {
  checkAuth();
  window.addEventListener("storage", onStorage);
  window.addEventListener("auth:changed", onAuthChanged);
  window.addEventListener("focus", onFocus);
});

onBeforeUnmount(() => {
  window.removeEventListener("storage", onStorage);
  window.removeEventListener("auth:changed", onAuthChanged);
  window.removeEventListener("focus", onFocus);
});

const isAuthed = computed(() => authed.value);
</script>

<style scoped>
/* Consistent app shell palette */
.layout{
  min-height: 100dvh;
  color: #111827;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  --topbar-h: 56px;
}

/* Main area */
.main{
  min-height: 100dvh;
  padding-top: var(--topbar-h); /* TopBar height (fixed) */
  padding-bottom: 24px;
}

/* When NOT authed, remove top padding so auth card is truly centered */
.main--noTopbar{
  padding-top: 0;
  padding-bottom: 0;
}

/* Authed pages container
   - Your TopBar has a fixed sidebar on desktop (260px),
     so we offset content on wide screens to avoid being under it. */
.container{
  max-width: 1300px;
  margin: 0 auto;
  padding: 18px 16px 32px;
}

/* Prevent authed content from hiding behind the fixed sidebar */
@media (min-width: 900px){
  .container{
    padding-left: calc(16px + 260px); /* sidebar width */
  }
}
@media (min-width: 1024px){
  .container{
    padding-left: calc(32px + 260px);
    padding-right: 32px;
  }
}

/* Guest/auth pages center wrapper */
.center{
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: 28px 16px;
}
</style>