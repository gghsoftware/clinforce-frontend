<!-- resources/js/Applicants/ApplicantsList.vue -->
<template>
  <AppLayout>
    <div class="page">
      <div class="container">
        <!-- Header -->
        <div class="header">
          <div class="headerLeft">
            <h1 class="title">Applicants</h1>
            <p class="subtitle">This list is driven by job applications from your API.</p>

            <!-- Debug line -->
            <p class="subtitle" style="margin-top:6px;">
              Role: <b>{{ meRole || "unknown" }}</b> • Scope: <b>{{ scope }}</b>
            </p>
          </div>

          <div class="headerRight">
            <!-- Show only scopes that user is allowed to use -->
            <select v-model="scope" class="control controlSelect controlSelectAuto" @change="fetchData(1)">
              <option v-if="canOwned" value="owned">Owned (employer / agency)</option>
              <option v-if="canMine" value="mine">Mine (applicant)</option>
            </select>

            <button class="btn" type="button" @click="fetchData(1)" :disabled="loading">
              {{ loading ? "Refreshing…" : "Refresh" }}
            </button>
          </div>
        </div>

        <div class="grid">
          <!-- Filters -->
          <aside class="sidebar">
            <div class="card sticky">
              <div class="block">
                <div class="kicker">Search</div>
                <input
                  v-model="search"
                  class="control"
                  type="text"
                  placeholder="Job title, applicant ID…"
                  @keydown.enter="applyFilters"
                />
              </div>

              <div class="block">
                <div class="kicker">Status</div>
                <select v-model="status" class="control controlSelect" @change="applyFilters">
                  <option value="">All</option>
                  <option v-for="s in allowedStatuses" :key="s" :value="s">
                    {{ s }}
                  </option>
                </select>
              </div>

              <div class="actions">
                <button class="btn btnPrimary" type="button" @click="applyFilters" :disabled="loading">
                  Apply
                </button>
                <button class="btn" type="button" @click="resetFilters" :disabled="loading">
                  Reset
                </button>
              </div>

              <div v-if="error" class="alert alertError">
                {{ error }}
              </div>

              <div v-if="debugHint" class="alert" style="margin-top:10px; background:#f9fafb; border:1px solid rgba(229,231,235,0.9);">
                {{ debugHint }}
              </div>
            </div>
          </aside>

          <!-- Results -->
          <section class="content">
            <div v-if="loading" class="card state">Loading applicants…</div>

            <div v-else-if="items.length === 0" class="card state">
              No applicants match your filters.
            </div>

            <article v-for="row in items" :key="row.id" class="card item">
              <div class="itemRow">
                <div class="itemMain">
                  <div class="itemTop">
                    <div class="itemTitle">Applicant #{{ row.applicant_user_id }}</div>
                    <span class="dot">•</span>
                    <div class="itemJob">{{ row.job?.title || "Job" }}</div>

                    <span class="pill" :class="statusClass(row.status)">
                      {{ row.status }}
                    </span>
                  </div>

                  <div class="meta">
                    <span>
                      Submitted <span class="strong">{{ formatDate(row.submitted_at) }}</span>
                    </span>
                    <span class="metaDot">•</span>
                    <span>
                      ID <span class="strong">#{{ row.id }}</span>
                    </span>
                  </div>

                  <p v-if="row.cover_letter" class="cover">
                    {{ row.cover_letter }}
                  </p>
                </div>

                <button
                  class="btn btnSmall btnView"
                  type="button"
                  @click="router.push({ name: 'applicants.view', params: { id: row.id } })"
                >
                  View
                </button>
              </div>
            </article>

            <div v-if="pagination" class="pager">
              <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>

              <div class="pagerBtns">
                <button
                  class="btn btnSmall"
                  type="button"
                  :disabled="loading || pagination.current_page <= 1"
                  @click="fetchPage(pagination.current_page - 1)"
                >
                  Prev
                </button>
                <button
                  class="btn btnSmall"
                  type="button"
                  :disabled="loading || pagination.current_page >= pagination.last_page"
                  @click="fetchPage(pagination.current_page + 1)"
                >
                  Next
                </button>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import AppLayout from "@/Components/AppLayout.vue";
import api from "@/lib/api";

const router = useRouter();

const loading = ref(false);
const error = ref("");
const debugHint = ref("");

const meRole = ref(""); // admin|employer|agency|applicant

const scope = ref("mine"); // default safe for applicants
const status = ref("");
const search = ref("");

const allowedStatuses = [
  "submitted",
  "shortlisted",
  "rejected",
  "interview",
  "hired",
  "withdrawn",
];

const raw = ref(null);

const canOwned = computed(() => ["admin", "employer", "agency"].includes(meRole.value));
const canMine = computed(() => ["admin", "applicant"].includes(meRole.value) || !meRole.value); // allow while loading

function unwrapPaginator(resData) {
  // ApiController::ok($paginator) => { message, data: paginator }
  const outer = resData?.data ?? resData;
  return outer?.data ?? outer;
}

function buildUrl() {
  const base = String(api?.defaults?.baseURL ?? "");
  return base.includes("/api") ? "/applications" : "/api/applications";
}

const items = computed(() => {
  const rows = raw.value?.data || [];
  const q = search.value.trim().toLowerCase();

  return rows.filter((r) => {
    if (status.value && r.status !== status.value) return false;
    if (!q) return true;

    const hay = [
      String(r.id || ""),
      String(r.applicant_user_id || ""),
      String(r.status || ""),
      String(r.job?.title || ""),
    ].join(" ").toLowerCase();

    return hay.includes(q);
  });
});

const pagination = computed(() => {
  if (!raw.value) return null;
  return {
    current_page: Number(raw.value.current_page || 1),
    last_page: Number(raw.value.last_page || 1),
  };
});

function formatDate(v) {
  if (!v) return "N/A";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return String(v);
  return d.toLocaleString();
}

function statusClass(s) {
  switch (s) {
    case "submitted": return "pillNeutral";
    case "shortlisted": return "pillBrand";
    case "interview": return "pillWarn";
    case "hired": return "pillSuccess";
    case "rejected": return "pillDanger";
    case "withdrawn": return "pillNeutral";
    default: return "pillNeutral";
  }
}

async function fetchMeRole() {
  // If baseURL is "/api", use "/me"
  // If baseURL is "/", use "/api/me"
  const base = String(api?.defaults?.baseURL ?? "");
  const url = base.includes("/api") ? "/me" : "/api/me";

  const res = await api.get(url);
  const outer = res.data?.data ?? res.data; // { message, data } or plain
  const user = outer?.data ?? outer?.user ?? outer;
  meRole.value = user?.role || "";

  // Auto-fix scope (this is the main reason for 403)
  if (meRole.value === "admin") scope.value = "owned";
  else if (["employer", "agency"].includes(meRole.value)) scope.value = "owned";
  else scope.value = "mine";
}

async function fetchData(page = 1) {
  loading.value = true;
  error.value = "";
  debugHint.value = "";

  try {
    const params = { scope: scope.value, page };
    if (status.value) params.status = status.value;

    const url = buildUrl();
    const res = await api.get(url, { params });

    const paginator = unwrapPaginator(res.data);
    raw.value = paginator;

    if (!raw.value || !Array.isArray(raw.value.data)) {
      raw.value = { data: [], current_page: 1, last_page: 1 };
    }
  } catch (e) {
    const code = e?.response?.status;
    const msg = e?.response?.data?.message || e?.message || "Request failed";
    error.value = msg;

    debugHint.value = `HTTP ${code || "?"} — role=${meRole.value || "unknown"} scope=${scope.value} url=${buildUrl()}`;
    raw.value = { data: [], current_page: 1, last_page: 1 };
  } finally {
    loading.value = false;
  }
}

function fetchPage(p) {
  fetchData(Math.max(1, Number(p || 1)));
}
function applyFilters() {
  fetchData(1);
}
function resetFilters() {
  status.value = "";
  search.value = "";
  fetchData(1);
}

onMounted(async () => {
  try {
    await fetchMeRole();
  } catch {
    // if /me fails, you’ll see it on debugHint after fetchData
  }
  await fetchData(1);
});
</script>

<style scoped>
/* keep your existing CSS (unchanged) */
*, *::before, *::after{ box-sizing:border-box; }
.page{ overflow-x:hidden; }
.page{
  min-height:100vh;
  background:
    radial-gradient(1200px 700px at 20% -10%, rgba(232,155,15,0.16), transparent 55%),
    radial-gradient(900px 600px at 100% 0%, rgba(232,155,15,0.10), transparent 50%),
    linear-gradient(180deg, #fafaf9 0%, #f5f5f4 100%);
  color:#111827;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
}
.container{ max-width:1100px; margin:0 auto; padding:20px 16px 40px; }
.header{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.headerLeft{ min-width:240px; }
.headerRight{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:flex-end; max-width:100%; }
.title{ font-size:26px; line-height:1.15; letter-spacing:-0.02em; margin:0; font-weight:800; }
.subtitle{ margin:8px 0 0; font-size:12px; line-height:1.55; color:#4b5563; }

.grid{ margin-top:18px; display:grid; grid-template-columns: minmax(260px, 300px) minmax(0, 1fr); gap:18px; }
@media (max-width: 980px){ .grid{ grid-template-columns: 1fr; } }
.sidebar{ min-width:0; }
.content{ min-width:0; display:flex; flex-direction:column; gap:12px; }

.card{
  background:#fff;
  border:1px solid rgba(229,231,235,0.9);
  border-radius:18px;
  box-shadow: 0 1px 0 rgba(17,24,39,0.02), 0 14px 35px rgba(17,24,39,0.07);
}
.sticky{ padding:16px; position:sticky; top:20px; display:flex; flex-direction:column; gap:14px; z-index:1; }
@media (max-width: 980px){ .sticky{ position:relative; top:auto; } }

.block{ display:flex; flex-direction:column; gap:8px; }
.kicker{ font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.14em; text-transform:uppercase; }

.control{
  width:100%;
  border-radius:14px;
  border:1px solid rgba(209,213,219,0.95);
  background:#fff;
  padding:10px 12px;
  font-size:12px;
  color:#111827;
  outline:none;
}
.controlSelectAuto{ width:auto; min-width:240px; max-width:100%; }
@media (max-width: 520px){ .controlSelectAuto{ width:100%; min-width:0; } .headerRight{ width:100%; } }

.controlSelect{
  appearance:none;
  background-image:
    linear-gradient(45deg, transparent 50%, #9ca3af 50%),
    linear-gradient(135deg, #9ca3af 50%, transparent 50%),
    linear-gradient(to right, transparent, transparent);
  background-position:
    calc(100% - 16px) calc(1em + 1px),
    calc(100% - 11px) calc(1em + 1px),
    100% 0;
  background-size:5px 5px, 5px 5px, 2.5em 2.5em;
  background-repeat:no-repeat;
  padding-right:34px;
}

.actions{ display:flex; gap:10px; }

.btn{
  border-radius:999px;
  border:1px solid rgba(209,213,219,0.95);
  background:#fff;
  padding:9px 12px;
  font-size:11px;
  font-weight:800;
  color:#111827;
  cursor:pointer;
}
.btnPrimary{
  background: linear-gradient(180deg, #b45309 0%, #92400e 100%);
  border-color: rgba(146,64,14,0.55);
  color:#fff;
}
.btnSmall{ padding:8px 11px; }
.btnView{ flex:0 0 auto; }

.alert{ border-radius:14px; padding:10px 12px; font-size:12px; line-height:1.45; }
.alertError{ background:#fef2f2; border:1px solid rgba(254,202,202,0.95); color:#b91c1c; }

.state{ padding:14px 16px; color:#6b7280; font-size:12px; }

.item{ padding:14px 16px; }
.itemRow{ display:flex; align-items:flex-start; justify-content:space-between; gap:14px; }
.itemMain{ min-width:0; flex:1 1 auto; display:flex; flex-direction:column; gap:8px; }
.itemTop{ display:flex; flex-wrap:wrap; align-items:center; gap:8px; min-width:0; }
.itemTitle{ font-size:13px; font-weight:900; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.itemJob{ font-size:13px; font-weight:700; color:#374151; max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
@media (max-width: 520px){ .itemJob{ max-width:100%; } }
.dot{ color:#d1d5db; font-weight:900; }

.pill{ display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px; font-size:11px; font-weight:900; border:1px solid rgba(229,231,235,0.95); }
.pillNeutral{ background:#f9fafb; color:#374151; }
.pillBrand{ background:#fff7ed; border-color:#fed7aa; color:#7c2d12; }
.pillSuccess{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
.pillWarn{ background:#fffbeb; border-color:#fde68a; color:#78350f; }
.pillDanger{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }

.meta{ display:flex; flex-wrap:wrap; gap:8px; font-size:12px; color:#6b7280; }
.metaDot{ color:#d1d5db; font-weight:900; }
.strong{ font-weight:900; color:#111827; }

.cover{
  margin:0;
  color:#374151;
  font-size:12px;
  line-height:1.55;
  display:-webkit-box;
  -webkit-line-clamp:2;
  -webkit-box-orient:vertical;
  overflow:hidden;
}

.pager{ display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:10px; padding-top:6px; color:#6b7280; font-size:12px; }
.pagerBtns{ display:flex; gap:10px; }
</style>
