<!-- resources/js/Pages/Candidate/MyApplications.vue -->
<template>
  <AppLayout>
    <div class="page">
      <div class="shell">
        <section class="head">
          <div class="headLeft">
            <h1 class="h1">My Applications</h1>
            <p class="sub">
              Pulled from <span class="mono">GET /api/applications?scope=mine</span>.
            </p>
          </div>

          <div class="headRight">
            <button class="btn ghost" type="button" @click="fetchData(1)" :disabled="loading">
              {{ loading ? "Loading…" : "Refresh" }}
            </button>
          </div>
        </section>

        <div v-if="error" class="alert danger">
          <div class="alertTitle">Error</div>
          <div class="alertText">{{ error }}</div>
        </div>

        <div class="grid">
          <!-- Filters -->
          <aside class="side">
            <div class="card section sticky">
              <div class="block">
                <div class="kicker">Search</div>
                <input
                  v-model="search"
                  class="input"
                  type="text"
                  placeholder="Job title, application ID…"
                  @keydown.enter="applyFilters"
                />
              </div>

              <div class="block">
                <div class="kicker">Status</div>
                <select v-model="status" class="input">
                  <option value="">All</option>
                  <option v-for="s in allowedStatuses" :key="s" :value="s">
                    {{ s }}
                  </option>
                </select>
              </div>

              <div class="actions">
                <button class="btn" type="button" @click="applyFilters" :disabled="loading">
                  Apply
                </button>
                <button class="btn ghost" type="button" @click="resetFilters" :disabled="loading">
                  Reset
                </button>
              </div>

              <div v-if="error" class="alertInline">{{ error }}</div>
            </div>
          </aside>

          <!-- List -->
          <section class="content">
            <div v-if="loading" class="card section state">
              <div class="spinner" />
              <div>Loading applications…</div>
            </div>

            <div v-else-if="items.length === 0" class="card section empty">
              <div class="emptyTitle">No results</div>
              <div class="emptyText">No applications match your filters.</div>
            </div>

            <article v-for="row in items" :key="row.id" class="card section item">
              <div class="row">
                <div class="main">
                  <div class="top">
                    <div class="appId">Application #{{ row.id }}</div>
                    <span class="dot">•</span>
                    <div class="job">{{ row.job?.title || "Job" }}</div>
                    <span class="pill" :class="statusClass(row.status)">{{ row.status || "—" }}</span>
                  </div>

                  <div class="meta">
                    Submitted <span class="strong">{{ formatDate(row.submitted_at) }}</span>
                  </div>

                  <p v-if="row.cover_letter" class="cover">{{ row.cover_letter }}</p>
                </div>

                <button
                  class="btn sm"
                  type="button"
                  @click="router.push({ name: 'candidate.applications.view', params: { id: row.id } })"
                >
                  View
                </button>
              </div>
            </article>

            <div v-if="pagination" class="pager">
              <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
              <div class="pagerBtns">
                <button
                  class="btn ghost sm"
                  type="button"
                  :disabled="pagination.current_page <= 1 || loading"
                  @click="fetchData(pagination.current_page - 1)"
                >
                  Prev
                </button>
                <button
                  class="btn ghost sm"
                  type="button"
                  :disabled="pagination.current_page >= pagination.last_page || loading"
                  @click="fetchData(pagination.current_page + 1)"
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

const status = ref("");
const search = ref("");
const allowedStatuses = ["submitted", "shortlisted", "rejected", "interview", "hired", "withdrawn"];

const raw = ref(null);

const items = computed(() => {
  const rows = raw.value?.data || [];
  const q = search.value.trim().toLowerCase();

  return rows.filter((r) => {
    if (status.value && r.status !== status.value) return false;
    if (!q) return true;

    const hay = [String(r.id || ""), String(r.status || ""), String(r.job?.title || "")]
      .join(" ")
      .toLowerCase();

    return hay.includes(q);
  });
});

const pagination = computed(() => {
  if (!raw.value) return null;
  return {
    current_page: raw.value.current_page,
    last_page: raw.value.last_page,
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
    case "submitted":
    case "withdrawn":
      return "pillNeutral";
    case "shortlisted":
      return "pillBrand";
    case "interview":
      return "pillWarn";
    case "hired":
      return "pillSuccess";
    case "rejected":
      return "pillDanger";
    default:
      return "pillNeutral";
  }
}

function normalizePayload(resData) {
  // supports:
  // A) { data: { data: [...], current_page, last_page } }
  // B) { data: [...], current_page, last_page }
  // C) { data: { ...payload } } from some wrappers
  const root = resData?.data ?? resData;
  if (root?.data?.data && Array.isArray(root.data.data)) return root.data;
  if (root?.data && Array.isArray(root.data)) return root;
  if (root?.data && typeof root.data === "object" && root.data.data) return root.data;
  return root;
}

async function fetchData(page = 1) {
  loading.value = true;
  error.value = "";
  try {
    const params = { scope: "mine", page };
    if (status.value) params.status = status.value;

    const res = await api.get("/applications", { params });
    raw.value = normalizePayload(res.data);
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Request failed";
    raw.value = null;
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  fetchData(1);
}
function resetFilters() {
  status.value = "";
  search.value = "";
  fetchData(1);
}

onMounted(() => fetchData(1));
</script>

<style scoped>
/* ✅ fixes overlap: box-sizing + grid minmax(0,*) + min-width:0 + sticky top uses your topbar var */
*, *::before, *::after { box-sizing: border-box; }

.page{
  overflow-x: hidden;
  min-height: 100vh;
  background:
    radial-gradient(1200px 700px at 20% -10%, rgba(232,155,15,0.12), transparent 55%),
    radial-gradient(900px 600px at 100% 0%, rgba(232,155,15,0.08), transparent 50%),
    linear-gradient(180deg, #fafaf9 0%, #f5f5f4 100%);
  color:#111827;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
}

.shell{ max-width: 1200px; margin: 0 auto; padding: 18px 16px 44px; }

.head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px;
  border-radius: 18px;
  border: 1px solid rgba(229,231,235,0.92);
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  box-shadow: 0 14px 35px rgba(17,24,39,0.07);
}
.h1{ margin:0; font-size: 18px; font-weight: 900; letter-spacing: -0.01em; }
.sub{ margin: 4px 0 0; font-size: 11px; color:#6b7280; line-height: 1.45; max-width: 80ch; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-weight: 900; }

.headRight{ display:flex; gap: 8px; flex-wrap: wrap; }

.grid{
  margin-top: 12px;
  display:grid;
  grid-template-columns: minmax(0, 320px) minmax(0, 1fr);
  gap: 12px;
}
@media (max-width: 980px){ .grid{ grid-template-columns: 1fr; } }

.side, .content{ min-width: 0; }

.card{
  border-radius: 18px;
  border: 1px solid rgba(229,231,235,0.92);
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  box-shadow: 0 14px 35px rgba(17,24,39,0.07);
}
.section{ padding: 12px; }

.sticky{
  position: sticky;
  top: calc(var(--topbar-h, 56px) + 16px); /* ✅ avoids topbar overlap */
  z-index: 1;
}
@media (max-width: 980px){ .sticky{ position: relative; top: auto; } }

.block{ display:grid; gap: 8px; }
.kicker{
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color:#6b7280;
}

.input{
  width: 100%;
  min-width: 0;
  border-radius: 14px;
  border: 1px solid rgba(209,213,219,0.95);
  padding: 10px 12px;
  font-size: 12px;
  font-weight: 700;
  color:#111827;
  outline: none;
  background:#fff;
}
.input:focus{
  border-color: rgba(232,155,15,0.55);
  box-shadow: 0 0 0 5px rgba(232,155,15,0.16);
}

.actions{ display:flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }

.btn{
  border: 1px solid rgba(17,24,39,0.14);
  background:#111827;
  color:#fff;
  border-radius: 12px;
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 800;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  white-space: nowrap;
}
.btn:disabled{ opacity: 0.6; cursor: not-allowed; }
.btn.ghost{ background:#fff; color:#111827; border-color: rgba(229,231,235,0.92); }
.btn.sm{ padding: 7px 10px; font-size: 11px; }

.alert{ margin-top: 12px; border-radius: 14px; padding: 10px 12px; border:1px solid rgba(229,231,235,0.92); background: rgba(255,255,255,0.92); }
.alertTitle{ font-weight: 900; font-size: 12px; }
.alertText{ margin-top: 4px; font-size: 11px; color:#6b7280; }
.alert.danger{ border-color: rgba(153,27,27,0.18); background: rgba(254,242,242,0.88); }
.alert.danger .alertTitle{ color:#991b1b; }
.alert.danger .alertText{ color:#7f1d1d; }
.alertInline{
  border-radius: 14px;
  border: 1px solid rgba(153,27,27,0.18);
  background: rgba(254,242,242,0.88);
  padding: 10px 12px;
  color:#7f1d1d;
  font-size: 12px;
  line-height: 1.45;
}

.content{ display:flex; flex-direction:column; gap: 12px; }

.state{
  display:flex;
  align-items:center;
  gap: 10px;
  color:#6b7280;
  font-size: 12px;
}
.spinner{
  width: 14px;
  height: 14px;
  border-radius: 999px;
  border: 2px solid rgba(17,24,39,0.18);
  border-top-color: rgba(17,24,39,0.55);
  animation: spin 700ms linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.empty{ text-align:center; }
.emptyTitle{ font-weight: 900; font-size: 12px; color:#111827; }
.emptyText{ margin-top: 4px; font-size: 11px; color:#6b7280; }

.item{ background:#fff; }
.row{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
}
.main{ min-width: 0; flex: 1 1 auto; display:flex; flex-direction:column; gap: 8px; }

.top{
  display:flex;
  align-items:center;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
}
.appId{
  font-size: 13px;
  font-weight: 900;
  color:#111827;
  white-space: nowrap;
}
.job{
  font-size: 13px;
  font-weight: 800;
  color:#374151;
  max-width: 520px;
  min-width: 0;
  overflow:hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.dot{ color:#d1d5db; font-weight: 900; }

.pill{
  display:inline-flex;
  align-items:center;
  border-radius: 999px;
  padding: 4px 10px;
  font-size: 11px;
  font-weight: 900;
  border: 1px solid rgba(229,231,235,0.95);
  white-space: nowrap;
}
.pillNeutral{ background:#f9fafb; color:#374151; }
.pillBrand{ background:#fff7ed; border-color:#fed7aa; color:#7c2d12; }
.pillSuccess{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
.pillWarn{ background:#fffbeb; border-color:#fde68a; color:#78350f; }
.pillDanger{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }

.meta{ font-size: 12px; color:#6b7280; }
.strong{ font-weight: 900; color:#111827; }

.cover{
  margin: 0;
  color:#374151;
  font-size: 12px;
  line-height: 1.55;
  display:-webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow:hidden;
}

.pager{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap: 10px;
  flex-wrap: wrap;
  color:#6b7280;
  font-size: 12px;
}
.pagerBtns{ display:flex; gap: 10px; flex-wrap: wrap; }
</style>