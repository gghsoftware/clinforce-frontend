<!-- resources/js/Pages/Candidate/JobView.vue -->
<template>
  <CandidateLayout subtitle="Job details" :displayName="meName">
    <div class="page">
      <div class="shell">
        <!-- Header (same design language as Interviews) -->
        <section class="head">
          <div class="headLeft">
            <h1 class="h1">Job details</h1>
            <p class="sub">
              Review the role overview, requirements, and apply when ready.
            </p>
          </div>

          <div class="headRight">
            <button type="button" class="btn ghost" @click="goBack">← Back</button>
            <button
              v-if="!loading && !error"
              type="button"
              class="btn"
              @click="openApply = true"
            >
              Apply now
            </button>
            <button type="button" class="btn" @click="fetchJob" :disabled="loading">
              {{ loading ? "Loading…" : "Refresh" }}
            </button>
          </div>
        </section>

        <div v-if="loading" class="card section state">
          <div class="spinner" />
          <div>Loading job…</div>
        </div>

        <div v-else-if="error" class="alert danger">
          <div class="alertTitle">Error</div>
          <div class="alertText">{{ error }}</div>
        </div>

        <div v-else class="grid">
          <!-- Main -->
          <section class="card section">
            <div class="sectionHead">
              <div class="titleBlock">
                <h2 class="h2">{{ job.title || "Untitled job" }}</h2>
                <div class="muted">
                  {{ formatLocation(job) }} • {{ prettyEnum(job.employment_type) }} •
                  {{ prettyEnum(job.work_mode) }}
                </div>
              </div>

              <div class="rightMeta">
                <span class="pill neutral">{{ prettyEnum(job.work_mode) }}</span>
                <span class="pill neutral">{{ prettyEnum(job.employment_type) }}</span>
              </div>
            </div>

            <div class="contentBlock">
              <div class="blockTitle">Role overview</div>
              <p class="body">{{ job.description || "—" }}</p>
            </div>

            <div class="actions">
              <button type="button" class="btn primary sm" @click="openApply = true">
                Apply now
              </button>
            </div>
          </section>

          <!-- Side -->
          <aside class="side">
            <section class="card section">
              <div class="sectionHead">
                <div class="titleBlock">
                  <h2 class="h2">Application steps</h2>
                  <div class="muted">What happens next</div>
                </div>
                <span class="pill brand">Steps</span>
              </div>

              <ol class="steps">
                <li>Submit your application.</li>
                <li>Recruiter reviews your profile.</li>
                <li>Interview scheduling (if shortlisted).</li>
                <li>Final decision.</li>
              </ol>
            </section>

            <section class="card section">
              <div class="sectionHead">
                <div class="titleBlock">
                  <h2 class="h2">Job info</h2>
                  <div class="muted">Posting details</div>
                </div>
                <span class="pill neutral">Info</span>
              </div>

              <div class="kv">
                <div class="k">Status</div>
                <div class="v">{{ prettyEnum(job.status || "published") }}</div>
              </div>
              <div class="kv">
                <div class="k">Posted</div>
                <div class="v">{{ fmt(job.published_at || job.created_at) }}</div>
              </div>
            </section>
          </aside>
        </div>

        <!-- Apply modal (same functionality, updated styling) -->
        <div v-if="openApply" class="overlay" @click.self="openApply = false">
          <div class="modal">
            <div class="modalTop">
              <div>
                <div class="modalTitle">Apply to</div>
                <div class="modalSub">{{ job.title || "Job" }}</div>
              </div>
              <button type="button" class="close" @click="openApply = false">✕</button>
            </div>

            <div class="field">
              <label class="label">Cover letter (optional)</label>
              <textarea
                v-model="coverLetter"
                class="textarea"
                rows="6"
                placeholder="Write a short cover letter…"
              />
            </div>

            <div v-if="applyError" class="alert danger">
              <div class="alertTitle">Submit failed</div>
              <div class="alertText">{{ applyError }}</div>
            </div>

            <div v-if="applyOk" class="alert ok">
              <div class="alertTitle">Submitted</div>
              <div class="alertText">{{ applyOk }}</div>
            </div>

            <div class="modalActions">
              <button type="button" class="btn ghost" @click="openApply = false" :disabled="applyLoading">
                Cancel
              </button>
              <button type="button" class="btn primary" @click="applyNow" :disabled="applyLoading">
                {{ applyLoading ? "Submitting…" : "Submit application" }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </CandidateLayout>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import CandidateLayout from "@/Components/CandidateLayout.vue";
import api from "@/lib/api";
import Swal from "sweetalert2";

const router = useRouter();

const props = defineProps({
  id: { type: [String, Number], required: true },
});

const meName = ref("ME");
const loading = ref(false);
const error = ref("");
const job = ref({});

const openApply = ref(false);
const coverLetter = ref("");
const applyLoading = ref(false);
const applyError = ref("");
const applyOk = ref("");

function goBack() {
  router.back();
}

function unwrapPayload(resData) {
  return resData?.data ?? resData;
}

function extractMessage(resData, fallback) {
  const p = unwrapPayload(resData);
  return p?.message || resData?.message || fallback;
}

function fmt(v) {
  if (!v) return "—";
  const d = new Date(v);
  if (Number.isNaN(d.getTime())) return String(v);
  return d.toLocaleString();
}

function formatLocation(j) {
  const parts = [j?.city, j?.country_code].filter(Boolean);
  return parts.length ? parts.join(", ") : "—";
}

function prettyEnum(v) {
  const t = String(v ?? "").trim();
  if (!t) return "—";
  return t
    .split("_")
    .map((w) => (w ? w[0].toUpperCase() + w.slice(1) : w))
    .join(" ");
}

async function fetchMe() {
  try {
    const res = await api.get("/me");
    const p = unwrapPayload(res.data);
    meName.value = p?.data?.full_name || p?.data?.name || p?.full_name || p?.name || "ME";
  } catch {
    meName.value = "ME";
  }
}

async function fetchJob() {
  loading.value = true;
  error.value = "";

  try {
    const id = encodeURIComponent(String(props.id));
    const res = await api.get(`/public/jobs/${id}`);
    const p = unwrapPayload(res.data);
    job.value = p?.data ?? p ?? {};
  } catch (e) {
    error.value = e?.response?.data?.message || e?.message || "Request failed";
  } finally {
    loading.value = false;
  }
}

async function applyNow() {
  applyLoading.value = true;
  applyError.value = "";
  applyOk.value = "";

  const id = encodeURIComponent(String(props.id));
  const payload = {};
  if (coverLetter.value.trim()) payload.cover_letter = coverLetter.value.trim();

  const candidates = [
    { method: "post", url: `/jobs/${id}/apply`, data: payload },
    { method: "post", url: `/jobs/${id}/applications`, data: payload },
    { method: "post", url: `/applications`, data: { job_id: props.id, ...payload } },
  ];

  try {
    let res = null;
    let lastErr = null;

    for (const c of candidates) {
      try {
        // eslint-disable-next-line no-await-in-loop
        res = await api[c.method](c.url, c.data);
        lastErr = null;
        break;
      } catch (err) {
        const status = err?.response?.status;
        if (status === 404 || status === 405) {
          lastErr = err;
          continue;
        }
        throw err;
      }
    }

    if (!res) throw lastErr || new Error("Apply endpoint not reachable");

    const msg = extractMessage(res.data, "Application submitted");

    applyOk.value = msg;
    coverLetter.value = "";
    openApply.value = false;

    await Swal.fire({
      icon: "success",
      title: "Submitted",
      text: msg,
      confirmButtonText: "OK",
    });
  } catch (e) {
    applyError.value = e?.response?.data?.message || e?.message || "Submit failed";
  } finally {
    applyLoading.value = false;
  }
}

onMounted(async () => {
  await fetchMe();
  await fetchJob();
});
</script>

<style scoped>
/* Sidebar-like tokens (match Interviews) */
:root{
  --ink: #111827;
  --muted: #6b7280;
  --line: rgba(229,231,235,1);
  --soft: rgba(17,24,39,0.04);
  --shadow: 0 10px 24px rgba(17,24,39,0.08);
  --r12: 12px;
  --r14: 14px;
  --r16: 16px;
}

.page{
  min-height:100vh;
  background:
    radial-gradient(1200px 700px at 20% -10%, rgba(232,155,15,0.12), transparent 55%),
    radial-gradient(900px 600px at 100% 0%, rgba(232,155,15,0.08), transparent 50%),
    linear-gradient(180deg, #fafafa 0%, #f5f5f4 100%);
  color: var(--ink);
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
}

.shell{
  max-width: 1200px;
  margin: 0 auto;
  padding: 18px 16px 44px;
}

/* Header */
.head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 12px;
  border-radius: var(--r16);
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow);
}
.headLeft{ min-width: 240px; }
.h1{
  margin:0;
  font-size: 18px;
  font-weight: 900;
  letter-spacing: -0.01em;
}
.sub{
  margin: 4px 0 0;
  font-size: 11px;
  color: var(--muted);
  line-height: 1.45;
}
.headRight{ display:flex; gap: 8px; flex-wrap: wrap; }

/* Grid */
.grid{
  margin-top: 12px;
  display:grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 12px;
}
@media (max-width: 980px){
  .grid{ grid-template-columns: 1fr; }
}

/* Cards */
.card{
  border-radius: var(--r16);
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.92);
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow);
}
.section{ padding: 12px; }

.sectionHead{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
  padding: 4px;
  margin-bottom: 8px;
}
.titleBlock{ display:grid; gap: 2px; }
.h2{
  margin:0;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color:#374151;
}
.muted{ font-size: 11px; color: var(--muted); }

/* Pills */
.rightMeta{ display:flex; align-items:center; gap: 8px; flex-wrap: wrap; }
.pill{
  display:inline-flex;
  align-items:center;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(17,24,39,0.12);
  font-size: 11px;
  font-weight: 900;
  white-space: nowrap;
  background: rgba(17,24,39,0.03);
  color: #374151;
}
.pill.neutral{ background: rgba(17,24,39,0.03); }
.pill.brand{
  background: rgba(232,155,15,0.12);
  border-color: rgba(232,155,15,0.35);
  color: #7c2d12;
}

/* Content */
.contentBlock{
  margin-top: 8px;
  border: 1px solid rgba(229,231,235,1);
  border-radius: var(--r14);
  background: #fff;
  padding: 12px;
}
.blockTitle{
  font-size: 11px;
  font-weight: 900;
  letter-spacing: 0.02em;
  color: #374151;
  margin-bottom: 8px;
}
.body{
  margin:0;
  font-size: 12px;
  color:#374151;
  line-height: 1.7;
  white-space: pre-wrap;
  word-break: break-word;
}

/* Steps */
.steps{
  margin: 0;
  padding-left: 18px;
  color:#374151;
  font-size: 12px;
  line-height: 1.7;
}

/* KV */
.kv{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid rgba(229,231,235,1);
}
.kv:first-of-type{ border-top: 0; padding-top: 0; }
.k{
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}
.v{
  font-size: 12px;
  font-weight: 900;
  color: var(--ink);
  text-align:right;
}

/* Actions */
.actions{
  margin-top: 10px;
  display:flex;
  justify-content:flex-end;
}

/* State */
.state{
  display:flex;
  align-items:center;
  gap: 10px;
  padding: 14px 8px;
  color: var(--muted);
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

/* Buttons (match Interviews) */
.btn{
  border: 1px solid rgba(17,24,39,0.14);
  background: #111827;
  color: #fff;
  border-radius: var(--r12);
  padding: 8px 12px;
  font-size: 12px;
  font-weight: 800;
  cursor: pointer;
  transition: transform 120ms ease, background 120ms ease, opacity 120ms ease;
  text-decoration: none;
  white-space: nowrap;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.btn:hover{ transform: translateY(-1px); background: #0b1220; }
.btn:active{ transform: translateY(0px); }
.btn:disabled{ opacity: 0.65; cursor: not-allowed; }

.btn.ghost{
  background: #fff;
  color: var(--ink);
  border: 1px solid var(--line);
}
.btn.ghost:hover{ background: rgba(17,24,39,0.02); }

.btn.primary{ background: #111827; color:#fff; }
.btn.sm{ padding: 7px 10px; font-size: 11px; border-radius: var(--r12); }

/* Side */
.side{ display:flex; flex-direction:column; gap: 12px; }

/* Alerts */
.alert{
  border-radius: var(--r14);
  border: 1px solid rgba(229,231,235,1);
  background: rgba(255,255,255,0.9);
  padding: 10px;
  margin-top: 12px;
}
.alertTitle{ font-weight: 900; font-size: 12px; }
.alertText{ margin-top: 4px; font-size: 11px; color: var(--muted); }

.alert.danger{
  border-color: rgba(153,27,27,0.18);
  background: rgba(254,242,242,0.85);
}
.alert.danger .alertTitle{ color:#991b1b; }
.alert.danger .alertText{ color:#7f1d1d; }

.alert.ok{
  border-color: rgba(6,95,70,0.18);
  background: rgba(236,253,245,0.85);
}
.alert.ok .alertTitle{ color:#065f46; }
.alert.ok .alertText{ color:#065f46; }

/* Modal */
.overlay{
  position: fixed;
  inset: 0;
  background: rgba(17,24,39,0.35);
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 16px;
  z-index: 60;
}

.modal{
  width: 100%;
  max-width: 620px;
  background: rgba(255,255,255,0.95);
  border: 1px solid var(--line);
  border-radius: var(--r16);
  box-shadow: 0 28px 90px rgba(17,24,39,0.22);
  padding: 14px 14px 12px;
  max-height: calc(100vh - 32px);
  overflow: auto;
  backdrop-filter: blur(10px);
}

.modalTop{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
}

.modalTitle{
  font-size: 12px;
  font-weight: 900;
  color:#374151;
}
.modalSub{
  margin-top: 3px;
  color: var(--muted);
  font-size: 12px;
}

.close{
  border: 1px solid rgba(17,24,39,0.14);
  background: #fff;
  color: var(--ink);
  border-radius: var(--r12);
  padding: 6px 10px;
  font-weight: 900;
  cursor:pointer;
}
.close:hover{ background: rgba(17,24,39,0.02); }

.field{ margin-top: 12px; }

.label{
  display:block;
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 6px;
}

.textarea{
  width:100%;
  border-radius: var(--r14);
  border: 1px solid rgba(17,24,39,0.14);
  padding: 10px 12px;
  font-size: 12px;
  outline:none;
  resize: vertical;
  background:#fff;
}
.textarea:focus{
  border-color: rgba(17,24,39,0.28);
  box-shadow: 0 0 0 4px rgba(17,24,39,0.08);
}

.modalActions{
  display:flex;
  justify-content:flex-end;
  gap: 10px;
  margin-top: 12px;
}
</style>