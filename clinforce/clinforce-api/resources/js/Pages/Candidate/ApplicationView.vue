<template>
  <AppLayout>
    <div class="page">
      <div class="container">
        <div class="header">
          <div class="headerLeft">
            <h1 class="title">Application Details</h1>
            <p class="subtitle">
              Pulled from <span class="mono">GET /api/applications/:id</span>
            </p>
          </div>

          <div class="headerRight">
            <button class="btn" type="button" @click="fetchData()" :disabled="loading">
              Refresh
            </button>
            <button class="btn ghost" type="button" @click="goBack">
              Back
            </button>
          </div>
        </div>

        <div v-if="loading" class="card state">Loading application…</div>

        <div v-else-if="error" class="card alert alertError">
          <div class="alertTitle">Request failed</div>
          <div class="alertMsg">{{ error }}</div>
        </div>

        <div v-else-if="!app" class="card state">No application found.</div>

        <div v-else class="grid">
          <!-- Left: summary -->
          <section class="card panel">
            <div class="panelHead">
              <div class="panelTitle">
                Application #{{ app.id }}
                <span class="pill" :class="statusClass(app.status)">{{ app.status }}</span>
              </div>
              <div class="panelSub">
                Submitted <span class="strong">{{ formatDate(app.submitted_at) }}</span>
              </div>
            </div>

            <div class="divider"></div>

            <div class="kv">
              <div class="k">Job</div>
              <div class="v">{{ app.job?.title || "Job" }}</div>

              <div class="k">Job ID</div>
              <div class="v">{{ app.job_id }}</div>

              <div class="k">Applicant User ID</div>
              <div class="v">{{ app.applicant_user_id }}</div>
            </div>

            <div v-if="app.cover_letter" class="block">
              <div class="kicker">Cover Letter</div>
              <p class="cover">{{ app.cover_letter }}</p>
            </div>

            <div class="actionsRow">
              <button
                v-if="canWithdraw"
                class="btn danger"
                type="button"
                :disabled="busy"
                @click="withdraw()"
              >
                Withdraw application
              </button>
            </div>
          </section>

          <!-- Right: timeline / extra -->
          <section class="card panel">
            <div class="panelHead">
              <div class="panelTitle">Status History</div>
              <div class="panelSub">Updates over time</div>
            </div>

            <div class="divider"></div>

            <div v-if="history.length === 0" class="state small">
              No status history.
            </div>

            <ol v-else class="timeline">
              <li v-for="h in history" :key="h.id" class="tItem">
                <div class="tDot"></div>
                <div class="tBody">
                  <div class="tTop">
                    <div class="tStatus">
                      <span class="mono">{{ h.from_status || "—" }}</span>
                      <span class="arrow">→</span>
                      <span class="mono">{{ h.to_status }}</span>
                    </div>
                    <div class="tTime">{{ formatDate(h.created_at) }}</div>
                  </div>
                  <div v-if="h.note" class="tNote">{{ h.note }}</div>
                </div>
              </li>
            </ol>

            <div class="divider"></div>

            <div class="panelHead">
              <div class="panelTitle">Interview</div>
              <div class="panelSub">If scheduled</div>
            </div>

            <div class="divider"></div>

            <div v-if="app.interview" class="kv">
              <div class="k">Scheduled</div>
              <div class="v">{{ formatDate(app.interview.scheduled_at) }}</div>

              <div class="k">Mode</div>
              <div class="v">{{ app.interview.mode || "—" }}</div>

              <div class="k">Location</div>
              <div class="v">{{ app.interview.location || "—" }}</div>

              <div class="k">Status</div>
              <div class="v">{{ app.interview.status || "—" }}</div>
            </div>

            <div v-else class="state small">
              No interview scheduled.
            </div>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import Swal from "sweetalert2";
import AppLayout from "@/Components/AppLayout.vue";
import api from "@/lib/api";

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const busy = ref(false);
const error = ref("");
const app = ref(null);

const id = computed(() => String(route.params.id || "").trim());

const history = computed(() => {
  const rows = app.value?.status_history || app.value?.statusHistory || [];
  return Array.isArray(rows) ? rows : [];
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

const canWithdraw = computed(() => {
  const s = String(app.value?.status || "");
  // consistent with your controller rules: applicant can only withdraw,
  // but not after rejected/hired
  if (!app.value) return false;
  return s && !["rejected", "hired", "withdrawn"].includes(s);
});

async function fetchData() {
  if (!id.value) {
    error.value = "Missing application id.";
    app.value = null;
    return;
  }

  loading.value = true;
  error.value = "";

  try {
    const res = await api.get(`/applications/${id.value}`);
    // ApiController returns {message,data,errors}
    app.value = res?.data?.data || null;
  } catch (e) {
    error.value =
      e?.response?.data?.message ||
      e?.message ||
      "Request failed";
    app.value = null;
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.push({ name: "candidate.applications" });
}

async function withdraw() {
  if (!app.value?.id) return;

  const result = await Swal.fire({
    title: "Withdraw application?",
    text: "This will mark your application as withdrawn.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Withdraw",
    cancelButtonText: "Cancel",
    reverseButtons: true,
    focusCancel: true,
    confirmButtonColor: "#111827",
  });

  if (!result.isConfirmed) return;

  busy.value = true;
  try {
    await api.post(`/applications/${app.value.id}/status`, { status: "withdrawn" });
    await fetchData();
    await Swal.fire({
      title: "Withdrawn",
      icon: "success",
      timer: 900,
      showConfirmButton: false,
    });
  } catch (e) {
    const msg =
      e?.response?.data?.message ||
      e?.message ||
      "Withdraw failed";
    await Swal.fire({
      title: "Failed",
      text: msg,
      icon: "error",
    });
  } finally {
    busy.value = false;
  }
}

onMounted(() => fetchData());
</script>

<style scoped>
/* Fix overlapping + make margins even */
*, *::before, *::after { box-sizing: border-box; }

.page{
  overflow-x: hidden;
  min-height: 100vh;
  background: linear-gradient(180deg,#fafaf9 0%,#f5f5f4 100%);
  color:#111827;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
}
.container{ max-width:1100px; margin:0 auto; padding:20px 16px 40px; }

.header{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.headerRight{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }

.title{ font-size:26px; line-height:1.15; margin:0; font-weight:800; }
.subtitle{ margin:8px 0 0; font-size:12px; line-height:1.55; color:#4b5563; }
.mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

.card{
  background:#fff;
  border:1px solid rgba(229,231,235,0.9);
  border-radius:18px;
  box-shadow: 0 1px 0 rgba(17,24,39,0.02), 0 14px 35px rgba(17,24,39,0.07);
}

.state{ padding:14px 16px; color:#6b7280; font-size:12px; }
.state.small{ padding:10px 12px; }

.alert{ padding:14px 16px; }
.alertError{ background:#fef2f2; border:1px solid rgba(254,202,202,0.95); color:#b91c1c; }
.alertTitle{ font-weight:900; font-size:12px; }
.alertMsg{ margin-top:4px; font-size:12px; }

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
.btn:disabled{ opacity:0.55; cursor:not-allowed; }
.btn.ghost{ background:#fff; }
.btn.danger{ border-color: rgba(153,27,27,0.25); color:#991b1b; }

.grid{ margin-top:18px; display:grid; grid-template-columns: minmax(0,1.2fr) minmax(0,1fr); gap:18px; }
@media (max-width: 980px){ .grid{ grid-template-columns: 1fr; } }

.panel{ padding:16px; }
.panelHead{ display:grid; gap:6px; }
.panelTitle{ display:flex; align-items:center; gap:10px; font-weight:900; font-size:14px; }
.panelSub{ font-size:12px; color:#6b7280; }
.strong{ font-weight:900; color:#111827; }

.divider{ height:1px; background: rgba(229,231,235,0.9); margin:14px 0; }

.pill{
  display:inline-flex; align-items:center;
  border-radius:999px; padding:4px 10px;
  font-size:11px; font-weight:900;
  border:1px solid rgba(229,231,235,0.95);
}
.pillNeutral{ background:#f9fafb; color:#374151; }
.pillBrand{ background:#fff7ed; border-color:#fed7aa; color:#7c2d12; }
.pillSuccess{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
.pillWarn{ background:#fffbeb; border-color:#fde68a; color:#78350f; }
.pillDanger{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }

.kv{
  display:grid;
  grid-template-columns: 160px 1fr;
  gap:10px 12px;
  font-size:12px;
}
@media (max-width: 560px){ .kv{ grid-template-columns: 1fr; } }
.k{ color:#6b7280; font-weight:800; }
.v{ color:#111827; font-weight:700; min-width:0; word-break: break-word; }

.block{ margin-top:14px; }
.kicker{
  font-size:11px;
  font-weight:900;
  color:#6b7280;
  letter-spacing:0.14em;
  text-transform:uppercase;
  margin-bottom:8px;
}
.cover{
  margin:0;
  color:#374151;
  font-size:12px;
  line-height:1.6;
  white-space: pre-wrap;
}

.actionsRow{ margin-top:14px; display:flex; gap:10px; flex-wrap:wrap; }

.timeline{ list-style:none; padding:0; margin:0; display:grid; gap:12px; }
.tItem{ display:grid; grid-template-columns: 10px 1fr; gap:10px; align-items:flex-start; }
.tDot{
  width:10px; height:10px; border-radius:999px;
  background:#111827; margin-top:5px;
}
.tBody{ min-width:0; }
.tTop{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.tStatus{ font-weight:900; font-size:12px; color:#111827; }
.arrow{ margin:0 6px; color:#9ca3af; font-weight:900; }
.tTime{ font-size:11px; color:#6b7280; }
.tNote{ margin-top:4px; font-size:12px; color:#374151; }
</style>
