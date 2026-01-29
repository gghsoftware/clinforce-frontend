<!-- resources/js/Pages/Candidate/Profile.vue -->
<template>
  <CandidateLayout subtitle="My profile" :displayName="meName">
    <div class="page">
      <div class="shell">
        <!-- Header -->
        <section class="head">
          <div class="headLeft">
            <h1 class="h1">My profile</h1>
            <p class="sub">Update your applicant details and manage documents.</p>
          </div>

          <div class="headRight">
            <button type="button" class="btn ghost" @click="fetchAll()" :disabled="loading">
              {{ loading ? "Loading…" : "Refresh" }}
            </button>
            <button type="button" class="btn" @click="saveProfile()" :disabled="saving">
              {{ saving ? "Saving…" : "Save changes" }}
            </button>
          </div>
        </section>

        <div v-if="error" class="alert danger">
          <div class="alertTitle">Error</div>
          <div class="alertText">{{ error }}</div>
        </div>

        <div v-if="success" class="alert ok">
          <div class="alertTitle">Success</div>
          <div class="alertText">{{ success }}</div>
        </div>

        <div class="grid">
          <!-- Main -->
          <section class="card section">
            <div class="sectionHead">
              <div class="titleBlock">
                <h2 class="h2">Applicant profile</h2>
                <div class="muted">Saved to <span class="mono">PUT /api/me/applicant</span></div>
              </div>

              <div class="rightMeta">
                <span class="pill brand">{{ completeness }}% complete</span>
              </div>
            </div>

            <div class="summary">
              <div class="sumLeft">
                <div class="avatar">{{ initials }}</div>
                <div class="sumText">
                  <div class="sumName">{{ displayName }}</div>
                  <div class="sumMeta">
                    <span class="chip">{{ auth.role || "—" }}</span>
                    <span class="dot">•</span>
                    <span class="metaText">{{ auth.email || "—" }}</span>
                  </div>
                </div>
              </div>

              <div class="sumRight">
                <div class="mutedMini">Completeness</div>
                <div class="bar" aria-label="Profile completeness">
                  <div class="barFill" :style="{ width: `${completeness}%` }" />
                </div>
                <div class="when">{{ completeness }}%</div>
              </div>
            </div>

            <!-- ✅ Fix overlap: box-sizing + grid row sizing + min-width 0 everywhere -->
            <div class="formGrid">
              <div class="field">
                <label class="label">First name</label>
                <input v-model="form.first_name" class="input" type="text" />
              </div>

              <div class="field">
                <label class="label">Last name</label>
                <input v-model="form.last_name" class="input" type="text" />
              </div>

              <div class="field">
                <label class="label">Headline</label>
                <input v-model="form.headline" class="input" type="text" placeholder="e.g. ICU Nurse • RN" />
              </div>

              <div class="field">
                <label class="label">Years of experience</label>
                <input v-model.number="form.years_experience" class="input" type="number" min="0" max="80" />
              </div>

              <div class="field">
                <label class="label">Country code</label>
                <input v-model="form.country_code" class="input" type="text" placeholder="PH" maxlength="2" />
              </div>

              <div class="field">
                <label class="label">City</label>
                <input v-model="form.city" class="input" type="text" placeholder="Makati" />
              </div>

              <div class="field full">
                <label class="label">Summary</label>
                <textarea v-model="form.summary" class="textarea" rows="5" placeholder="Short professional summary…" />
              </div>
            </div>

            <div class="note">
              Note: <span class="mono">email</span> and <span class="mono">role</span> come from <span class="mono">users</span>.
            </div>
          </section>

          <!-- Side -->
          <aside class="side">
            <section class="card section">
              <div class="sectionHead">
                <div class="titleBlock">
                  <h2 class="h2">Documents</h2>
                  <div class="muted">Uploads to <span class="mono">POST /api/documents</span></div>
                </div>
                <div class="rightMeta">
                  <button type="button" class="btn sm" @click="openUpload()">+ Add</button>
                </div>
              </div>

              <div v-if="docsLoading" class="state">
                <div class="spinner" />
                <div>Loading…</div>
              </div>

              <div v-else class="list">
                <article v-for="d in docs" :key="d.id" class="item">
                  <div class="itemTop">
                    <div class="itemLeft">
                      <div class="itemTitle">{{ d.file_name || "Document" }}</div>
                      <div class="itemMeta">
                        <span class="chip">{{ d.doc_type || "—" }}</span>
                        <span class="dot">•</span>
                        <span class="metaText">{{ bytesToLabel(d.file_size_bytes) }}</span>
                        <span v-if="d.created_at" class="metaText">• {{ formatDate(d.created_at) }}</span>
                      </div>
                    </div>

                    <div class="itemRight">
                      <div class="mutedMini">Actions</div>
                      <div class="actionsRow">
                        <a v-if="d.file_url" class="link" :href="d.file_url" target="_blank" rel="noreferrer">
                          Open →
                        </a>

                        <button
                          type="button"
                          class="btn ghost sm"
                          @click="confirmRemoveDoc(d)"
                          :disabled="removingId === d.id"
                        >
                          {{ removingId === d.id ? "Removing…" : "Remove" }}
                        </button>
                      </div>
                    </div>
                  </div>
                </article>

                <div v-if="docs.length === 0" class="empty">
                  <div class="emptyTitle">No documents uploaded</div>
                  <div class="emptyText">Add your resume and licenses to speed up applications.</div>
                </div>
              </div>
            </section>

            <section class="card mini">
              <div class="miniRow">
                <div class="miniTitle">Tip</div>
                <div class="miniText">Use PDF for resumes to preserve formatting.</div>
              </div>
            </section>
          </aside>
        </div>

        <!-- Upload modal -->
        <div v-if="showUpload" class="overlay" @click.self="closeUpload()">
          <div class="modal">
            <div class="modalHead">
              <div>
                <div class="modalTitle">Add document</div>
                <div class="modalSub">Creates via <span class="mono">POST /api/documents</span></div>
              </div>
              <button type="button" class="close" @click="closeUpload()">✕</button>
            </div>

            <div class="modalBody">
              <div v-if="uploadError" class="alert danger">
                <div class="alertTitle">Upload error</div>
                <div class="alertText">{{ uploadError }}</div>
              </div>

              <!-- ✅ Fix overlap in modal: force 1 column -->
              <div class="formGrid one">
                <div class="field">
                  <label class="label">Document type</label>
                  <select v-model="upload.doc_type" class="input">
                    <option v-for="t in allowedDocTypes" :key="t" :value="t">{{ t }}</option>
                  </select>
                </div>

                <div class="field">
                  <label class="label">File</label>
                  <input
                    ref="fileEl"
                    class="input"
                    type="file"
                    accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                    @change="onPickFile"
                  />
                  <div class="hint2" v-if="upload.fileName">
                    Selected: <span class="mono">{{ upload.fileName }}</span>
                    <span v-if="upload.fileSize"> ({{ bytesToLabel(upload.fileSize) }})</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="modalFoot">
              <button type="button" class="btn ghost" @click="closeUpload()" :disabled="uploading">Cancel</button>
              <button type="button" class="btn" @click="confirmUpload()" :disabled="uploading">
                {{ uploading ? "Uploading…" : "Save" }}
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>
  </CandidateLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import CandidateLayout from "@/Components/CandidateLayout.vue";
import api from "@/lib/api";
import Swal from "sweetalert2";

const meName = ref("ME");

const loading = ref(false);
const saving = ref(false);
const docsLoading = ref(false);
const uploading = ref(false);
const removingId = ref(null);

const error = ref("");
const success = ref("");

const auth = ref({ id: null, role: null, email: null });

const form = ref({
  first_name: "",
  last_name: "",
  headline: "",
  summary: "",
  years_experience: null,
  country_code: "",
  city: "",
});

const docs = ref([]);

const allowedDocTypes = ["resume", "license", "certificate", "id_document", "other"];

const showUpload = ref(false);
const uploadError = ref("");
const fileEl = ref(null);
const upload = ref({
  doc_type: "resume",
  file: null,
  fileName: "",
  fileSize: 0,
});

function unwrap(resData) {
  return resData?.data ?? resData;
}

const displayName = computed(() => {
  const f = (form.value.first_name || "").trim();
  const l = (form.value.last_name || "").trim();
  return (f || l) ? `${f} ${l}`.trim() : "Applicant";
});

const initials = computed(() => {
  const f = (form.value.first_name || "").trim()[0] || "";
  const l = (form.value.last_name || "").trim()[0] || "";
  const s = (f + l).toUpperCase();
  return s || "ME";
});

const completeness = computed(() => {
  const fields = [
    form.value.first_name,
    form.value.last_name,
    form.value.headline,
    form.value.summary,
    String(form.value.years_experience ?? ""),
    form.value.country_code,
    form.value.city,
  ];
  const filled = fields.filter((v) => String(v || "").trim().length > 0).length;
  return Math.round((filled / fields.length) * 100);
});

function formatDate(v) {
  if (!v) return "N/A";
  const d = new Date(v);
  return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleString();
}

function bytesToLabel(n) {
  const v = Number(n || 0);
  if (!v) return "—";
  const kb = v / 1024;
  if (kb < 1024) return `${kb.toFixed(1)} KB`;
  const mb = kb / 1024;
  return `${mb.toFixed(1)} MB`;
}

function toast(icon, title) {
  return Swal.fire({
    toast: true,
    position: "top-end",
    icon,
    title,
    showConfirmButton: false,
    timer: 1600,
    timerProgressBar: true,
  });
}

async function fetchMe() {
  const res = await api.get("/me");
  const u = unwrap(res.data);

  meName.value = u?.full_name || u?.name || u?.user?.name || "ME";

  auth.value = {
    id: u?.id ?? null,
    role: u?.role ?? null,
    email: u?.email ?? null,
  };

  const ap = u?.applicant_profile || u?.applicantProfile || null;
  form.value = {
    first_name: ap?.first_name ?? "",
    last_name: ap?.last_name ?? "",
    headline: ap?.headline ?? "",
    summary: ap?.summary ?? "",
    years_experience: ap?.years_experience ?? null,
    country_code: ap?.country_code ?? "",
    city: ap?.city ?? "",
  };
}

async function fetchDocs() {
  docsLoading.value = true;
  try {
    const res = await api.get("/documents");
    const arr = unwrap(res.data);
    docs.value = Array.isArray(arr) ? arr : Array.isArray(arr?.data) ? arr.data : [];
  } finally {
    docsLoading.value = false;
  }
}

async function fetchAll() {
  loading.value = true;
  error.value = "";
  success.value = "";
  try {
    await fetchMe();
    await fetchDocs();
  } catch (e) {
    error.value = e?.__payload?.message || e?.message || "Request failed";
    await Swal.fire({ icon: "error", title: "Request failed", text: error.value });
  } finally {
    loading.value = false;
  }
}

async function saveProfile() {
  saving.value = true;
  error.value = "";
  success.value = "";
  try {
    const payload = {
      first_name: form.value.first_name,
      last_name: form.value.last_name,
      headline: form.value.headline,
      summary: form.value.summary,
      years_experience: form.value.years_experience,
      country_code: (form.value.country_code || "").toUpperCase(),
      city: form.value.city,
    };

    await api.put("/me/applicant", payload);
    success.value = "Saved.";
    await fetchMe();
    await toast("success", "Profile saved");
  } catch (e) {
    const errs = e?.__payload?.errors;
    if (errs && typeof errs === "object") {
      const firstKey = Object.keys(errs)[0];
      error.value = errs[firstKey]?.[0] || e?.__payload?.message || "Save failed";
    } else {
      error.value = e?.__payload?.message || e?.message || "Save failed";
    }
    await Swal.fire({ icon: "error", title: "Save failed", text: error.value });
  } finally {
    saving.value = false;
  }
}

/* Upload modal */
function openUpload() {
  uploadError.value = "";
  upload.value = { doc_type: "resume", file: null, fileName: "", fileSize: 0 };
  showUpload.value = true;
}

function closeUpload() {
  showUpload.value = false;
  uploadError.value = "";
  if (fileEl.value) fileEl.value.value = "";
}

function onPickFile(ev) {
  const f = ev?.target?.files?.[0] || null;
  upload.value.file = f;
  upload.value.fileName = f?.name || "";
  upload.value.fileSize = f?.size || 0;
}

async function confirmUpload() {
  uploadError.value = "";

  if (!upload.value.file) {
    uploadError.value = "Please choose a file to upload.";
    await Swal.fire({ icon: "warning", title: "No file selected", text: uploadError.value });
    return;
  }

  const result = await Swal.fire({
    icon: "question",
    title: "Upload this document?",
    html: `
      <div style="text-align:left">
        <div><b>Type:</b> ${String(upload.value.doc_type)}</div>
        <div><b>File:</b> ${String(upload.value.fileName || "")}</div>
        <div><b>Size:</b> ${bytesToLabel(upload.value.fileSize)}</div>
      </div>
    `,
    showCancelButton: true,
    confirmButtonText: "Upload",
    cancelButtonText: "Cancel",
  });

  if (!result.isConfirmed) return;

  await submitUpload();
}

async function submitUpload() {
  uploadError.value = "";
  uploading.value = true;

  try {
    const fd = new FormData();
    fd.append("doc_type", upload.value.doc_type);
    fd.append("file", upload.value.file);

    await api.post("/documents", fd);

    await fetchDocs();
    showUpload.value = false;
    success.value = "Uploaded.";
    await toast("success", "Document uploaded");
  } catch (e) {
    const errs = e?.__payload?.errors;
    if (errs && typeof errs === "object") {
      const firstKey = Object.keys(errs)[0];
      uploadError.value = errs[firstKey]?.[0] || e?.__payload?.message || "Upload failed";
    } else {
      uploadError.value = e?.__payload?.message || e?.message || "Upload failed";
    }
    await Swal.fire({ icon: "error", title: "Upload failed", text: uploadError.value });
  } finally {
    uploading.value = false;
    if (fileEl.value) fileEl.value.value = "";
  }
}

async function confirmRemoveDoc(d) {
  if (!d?.id) return;

  const result = await Swal.fire({
    icon: "warning",
    title: "Remove this document?",
    text: d?.file_name || "Document",
    showCancelButton: true,
    confirmButtonText: "Remove",
    cancelButtonText: "Cancel",
  });

  if (!result.isConfirmed) return;

  await removeDoc(d);
}

async function removeDoc(d) {
  removingId.value = d.id;
  error.value = "";
  success.value = "";
  try {
    await api.delete(`/documents/${d.id}`);
    await fetchDocs();
    success.value = "Removed.";
    await toast("success", "Document removed");
  } catch (e) {
    error.value = e?.__payload?.message || e?.message || "Remove failed";
    await Swal.fire({ icon: "error", title: "Remove failed", text: error.value });
  } finally {
    removingId.value = null;
  }
}

onMounted(fetchAll);
</script>

<style scoped>
/* ✅ Critical overlap fix: border-box everywhere */
*, *::before, *::after { box-sizing: border-box; }

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
.h1{ margin:0; font-size: 18px; font-weight: 900; letter-spacing: -0.01em; }
.sub{ margin: 4px 0 0; font-size: 11px; color: var(--muted); line-height: 1.45; max-width: 78ch; }
.headRight{ display:flex; gap: 8px; flex-wrap: wrap; }

.grid{
  margin-top: 12px;
  display:grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  gap: 12px;
}
@media (max-width: 980px){ .grid{ grid-template-columns: 1fr; } }

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
.mutedMini{ font-size: 10px; color: var(--muted); font-weight: 900; }

.rightMeta{ display:flex; align-items:center; gap: 8px; }

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
.pill.brand{
  background: rgba(232,155,15,0.12);
  border-color: rgba(232,155,15,0.35);
  color: #7c2d12;
}

.summary{
  border: 1px solid rgba(229,231,235,1);
  border-radius: var(--r14);
  background: #fff;
  padding: 12px;
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.sumLeft{ display:flex; align-items:center; gap: 10px; min-width: 0; }
.avatar{
  height: 36px; width: 36px;
  border-radius: 999px;
  background: #111827;
  color: #fcd34d;
  display:flex; align-items:center; justify-content:center;
  font-size: 12px; font-weight: 900;
  flex: 0 0 auto;
}
.sumText{ min-width:0; }
.sumName{
  font-size: 13px;
  font-weight: 900;
  color: var(--ink);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  max-width: 100%;
}
.sumMeta{
  margin-top: 8px;
  display:flex;
  align-items:center;
  gap: 8px;
  min-width:0;
  flex-wrap: wrap;
}
.sumRight{ text-align:right; min-width: 170px; }

.when{ margin-top: 6px; font-size: 12px; font-weight: 900; color: var(--ink); }
.bar{
  margin-top: 6px;
  height: 6px;
  width: 100%;
  background: rgba(17,24,39,0.06);
  border-radius: 999px;
  overflow:hidden;
  border: 1px solid rgba(17,24,39,0.10);
}
.barFill{ height: 100%; background: rgba(232,155,15,0.55); }

.formGrid{
  margin-top: 10px;
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  align-items: start;
  grid-auto-rows: minmax(min-content, max-content);
}
.formGrid.one{ grid-template-columns: 1fr; } /* ✅ modal fix */
@media (max-width: 720px){ .formGrid{ grid-template-columns: 1fr; } }

.field{
  display:grid;
  gap: 6px;
  min-width: 0;
}
.field.full{ grid-column: 1 / -1; }

.label{
  font-size: 10px;
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--muted);
}

.input, .textarea{
  display:block;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  border-radius: var(--r14);
  border: 1px solid rgba(17,24,39,0.14);
  background: #fff;
  padding: 10px 12px;
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  outline:none;
  transition: border-color 120ms ease, box-shadow 120ms ease;
}
.textarea{ resize: vertical; }
.input:focus, .textarea:focus{
  border-color: rgba(232,155,15,0.55);
  box-shadow: 0 0 0 5px rgba(232,155,15,0.16);
}

.note{ margin-top: 10px; font-size: 11px; color: var(--muted); }

.mono{
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-weight: 800;
}

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

.list{ display:flex; flex-direction:column; gap: 10px; }

.item{
  border: 1px solid rgba(229,231,235,1);
  border-radius: var(--r14);
  background: #fff;
  padding: 12px;
  transition: background 120ms ease, transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
}
.item:hover{
  background: var(--soft);
  border-color: rgba(17,24,39,0.14);
  transform: translateY(-1px);
  box-shadow: 0 12px 22px rgba(17,24,39,0.10);
}

.itemTop{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 12px;
}
.itemLeft{ min-width: 0; }
.itemTitle{
  font-size: 13px;
  font-weight: 900;
  color: var(--ink);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  max-width: 100%;
}
.itemMeta{
  margin-top: 8px;
  display:flex;
  align-items:center;
  gap: 8px;
  min-width:0;
  flex-wrap: wrap;
}
.chip{
  font-size: 10px;
  font-weight: 900;
  padding: 3px 8px;
  border-radius: 999px;
  border: 1px solid rgba(17,24,39,0.12);
  background: rgba(17,24,39,0.03);
  color: #374151;
  flex: 0 0 auto;
}
.metaText{
  font-size: 11px;
  color: var(--muted);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  min-width:0;
}
.dot{ color:#d1d5db; font-weight: 900; }

.itemRight{ text-align:right; min-width: 160px; }
.actionsRow{
  margin-top: 8px;
  display:flex;
  gap: 10px;
  justify-content:flex-end;
  flex-wrap: wrap;
}

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
  text-decoration: none;
  white-space: nowrap;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}
.btn:hover{ transform: translateY(-1px); background: #0b1220; }
.btn:active{ transform: translateY(0px); }
.btn:disabled{ opacity: 0.6; cursor: not-allowed; }

.btn.ghost{ background:#fff; color: var(--ink); border: 1px solid var(--line); }
.btn.ghost:hover{ background: rgba(17,24,39,0.02); }
.btn.sm{ padding: 7px 10px; font-size: 11px; border-radius: var(--r12); }

.link{ color:#b45309; text-decoration: underline; font-weight: 900; font-size: 11px; }
.link:hover{ color:#92400e; }

.side{ display:flex; flex-direction:column; gap: 12px; }
.mini{ padding: 12px; }
.miniRow{ display:grid; gap: 4px; }
.miniTitle{ font-size: 11px; font-weight: 900; letter-spacing: 0.02em; color: #374151; }
.miniText{ font-size: 12px; color: var(--muted); line-height: 1.5; }

.empty{
  border: 1px dashed rgba(229,231,235,1);
  border-radius: var(--r14);
  background: rgba(255,255,255,0.8);
  padding: 14px;
  text-align: center;
}
.emptyTitle{ font-weight: 900; font-size: 12px; color: var(--ink); }
.emptyText{ margin-top: 4px; font-size: 11px; color: var(--muted); }

.alert{
  margin-top: 12px;
  border-radius: var(--r14);
  border: 1px solid rgba(229,231,235,1);
  background: rgba(255,255,255,0.9);
  padding: 10px;
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
  border-color: rgba(6,95,70,0.16);
  background: rgba(236,253,245,0.85);
}
.alert.ok .alertTitle{ color:#065f46; }
.alert.ok .alertText{ color:#064e3b; }

.overlay{
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.35);
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 16px;
  z-index: 60;
}
.modal{
  width: 100%;
  max-width: 720px;
  background:#fff;
  border:1px solid rgba(229,231,235,0.92);
  border-radius: var(--r16);
  box-shadow: 0 28px 90px rgba(17,24,39,0.22);
  overflow:hidden;
}
.modalHead{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
  padding: 12px 14px;
  border-bottom: 1px solid rgba(229,231,235,0.92);
}
.modalTitle{ font-size: 12px; font-weight: 900; }
.modalSub{ margin-top: 3px; font-size: 11px; color: var(--muted); }
.close{
  border:0;
  background: transparent;
  font-size: 14px;
  font-weight: 900;
  color: var(--muted);
  cursor:pointer;
  padding: 4px 6px;
}
.close:hover{ color: var(--ink); }
.modalBody{ padding: 14px; display:flex; flex-direction:column; gap: 12px; }
.modalFoot{
  padding: 12px 14px;
  border-top: 1px solid rgba(229,231,235,0.92);
  display:flex;
  justify-content:flex-end;
  gap: 10px;
}

.hint2{ margin-top: 6px; font-size: 11px; color: var(--muted); }
</style>