<!-- resources/js/Applicants/ApplicantProfile.vue -->
<template>
    <AppLayout>
      <div class="page">
        <div class="container">
          <!-- Header -->
          <div class="header">
            <div class="headerLeft">
              <button class="linkBack" type="button" @click="$router.back()">← Back</button>
  
              <h1 class="title">
                Application #{{ app?.id ?? props.id }}
              </h1>
  
              <p class="subtitle" v-if="app">
                Applicant #{{ app.applicant_user_id }} •
                <span class="muted">Job:</span>
                <span class="strong">{{ app.job?.title || 'N/A' }}</span>
              </p>
  
              <p class="subtitle" v-else>
                View applicant application details from your API.
              </p>
            </div>
  
            <div class="headerRight">
              <span v-if="app" class="pill" :class="statusClass(app.status)">
                {{ app.status }}
              </span>
  
              <button class="btn" type="button" @click="fetchOne()">
                Refresh
              </button>
            </div>
          </div>
  
          <div class="grid">
            <!-- Left summary -->
            <aside class="sidebar">
              <div class="card sticky">
                <div class="block">
                  <div class="kicker">Summary</div>
  
                  <div v-if="loading" class="state">
                    Loading…
                  </div>
  
                  <div v-else-if="error" class="alert alertError">
                    {{ error }}
                  </div>
  
                  <template v-else-if="app">
                    <div class="kv">
                      <div class="kvLabel">Application ID</div>
                      <div class="kvValue">#{{ app.id }}</div>
                    </div>
  
                    <div class="kv">
                      <div class="kvLabel">Applicant User ID</div>
                      <div class="kvValue">#{{ app.applicant_user_id }}</div>
                    </div>
  
                    <div class="kv">
                      <div class="kvLabel">Submitted</div>
                      <div class="kvValue">{{ formatDate(app.submitted_at) }}</div>
                    </div>
  
                    <div class="divider"></div>
  
                    <div class="kv">
                      <div class="kvLabel">Job</div>
                      <div class="kvValue clamp1">{{ app.job?.title || 'N/A' }}</div>
                    </div>
  
                    <div class="kv" v-if="app.job?.city || app.job?.country_code">
                      <div class="kvLabel">Location</div>
                      <div class="kvValue clamp1">
                        {{ [app.job?.city, app.job?.country_code].filter(Boolean).join(', ') }}
                      </div>
                    </div>
  
                    <div class="actions">
                      <button class="btn btnPrimary" type="button" @click="scrollTo('#history')">
                        Status History
                      </button>
                      <button class="btn" type="button" @click="scrollTo('#interview')">
                        Interview
                      </button>
                    </div>
                  </template>
  
                  <div v-else class="state">
                    No data loaded.
                  </div>
                </div>
              </div>
            </aside>
  
            <!-- Right content -->
            <section class="content">
              <div v-if="loading" class="card statePad">Loading application…</div>
              <div v-else-if="error" class="card statePad">Fix the request then refresh.</div>
  
              <template v-else-if="app">
                <!-- Cover letter -->
                <article class="card section">
                  <div class="sectionHead">
                    <div>
                      <div class="sectionTitle">Cover letter</div>
                      <div class="sectionSub">
                        Submitted <span class="strong">{{ formatDate(app.submitted_at) }}</span>
                      </div>
                    </div>
                    <span class="pill" :class="statusClass(app.status)">{{ app.status }}</span>
                  </div>
  
                  <p v-if="app.cover_letter" class="sectionText">
                    {{ app.cover_letter }}
                  </p>
                  <p v-else class="sectionEmpty">
                    No cover letter provided.
                  </p>
                </article>
  
                <!-- Status history -->
                <article id="history" class="card section">
                  <div class="sectionHead">
                    <div>
                      <div class="sectionTitle">Status history</div>
                      <div class="sectionSub">All changes recorded for this application</div>
                    </div>
                  </div>
  
                  <div class="list">
                    <div
                      v-for="h in (app.status_history || [])"
                      :key="h.id"
                      class="row"
                    >
                      <div class="rowMain">
                        <div class="rowTitle">
                          <span class="rowFrom">{{ h.from_status || '—' }}</span>
                          <span class="arrow">→</span>
                          <span class="rowTo">{{ h.to_status }}</span>
                          <span v-if="h.note" class="rowNote">· {{ h.note }}</span>
                        </div>
                        <div class="rowMeta">
                          {{ formatDate(h.created_at) }}
                        </div>
                      </div>
  
                      <span class="pill pillNeutral">Log</span>
                    </div>
  
                    <div v-if="(app.status_history || []).length === 0" class="sectionEmpty">
                      No history.
                    </div>
                  </div>
                </article>
  
                <!-- Interview -->
                <article id="interview" class="card section">
                  <div class="sectionHead">
                    <div>
                      <div class="sectionTitle">Interview</div>
                      <div class="sectionSub">Schedule and meeting details</div>
                    </div>
  
                    <span class="pill" :class="app.interview ? 'pillBrand' : 'pillNeutral'">
                      {{ app.interview ? 'Scheduled' : 'None' }}
                    </span>
                  </div>
  
                  <div v-if="app.interview" class="grid2">
                    <div class="kv">
                      <div class="kvLabel">Status</div>
                      <div class="kvValue">{{ app.interview.status }}</div>
                    </div>
  
                    <div class="kv">
                      <div class="kvLabel">Mode</div>
                      <div class="kvValue">{{ app.interview.mode }}</div>
                    </div>
  
                    <div class="kv">
                      <div class="kvLabel">Start</div>
                      <div class="kvValue">{{ formatDate(app.interview.scheduled_start) }}</div>
                    </div>
  
                    <div class="kv">
                      <div class="kvLabel">End</div>
                      <div class="kvValue">{{ formatDate(app.interview.scheduled_end) }}</div>
                    </div>
  
                    <div class="kv" v-if="app.interview.location_text">
                      <div class="kvLabel">Location</div>
                      <div class="kvValue clamp1">{{ app.interview.location_text }}</div>
                    </div>
  
                    <div class="kv" v-if="app.interview.meeting_link">
                      <div class="kvLabel">Meeting link</div>
                      <div class="kvValue">
                        <a class="link" :href="app.interview.meeting_link" target="_blank" rel="noreferrer">
                          {{ app.interview.meeting_link }}
                        </a>
                      </div>
                    </div>
                  </div>
  
                  <div v-else class="sectionEmpty">
                    No interview scheduled.
                  </div>
                </article>
              </template>
  
              <div v-else class="card statePad">
                No application found.
              </div>
            </section>
          </div>
        </div>
      </div>
    </AppLayout>
  </template>
  
  <script setup>
  import { onMounted, ref } from 'vue'
  import AppLayout from '@/Components/AppLayout.vue'
  import api from '@/lib/api'
  
  const props = defineProps({
    id: { type: [String, Number], required: true },
  })
  
  const loading = ref(false)
  const error = ref('')
  const app = ref(null)
  
  function formatDate(v) {
    if (!v) return 'N/A'
    const d = new Date(v)
    if (Number.isNaN(d.getTime())) return String(v)
    return d.toLocaleString()
  }
  
  function statusClass(s) {
    switch (s) {
      case 'submitted': return 'pillNeutral'
      case 'shortlisted': return 'pillBrand'
      case 'interview': return 'pillWarn'
      case 'hired': return 'pillSuccess'
      case 'rejected': return 'pillDanger'
      case 'withdrawn': return 'pillNeutral'
      default: return 'pillNeutral'
    }
  }
  
  function scrollTo(sel) {
    const el = document.querySelector(sel)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  
  async function fetchOne() {
    loading.value = true
    error.value = ''
    try {
      const res = await api.get(`/api/applications/${props.id}`)
      app.value = res.data?.data ?? res.data
    } catch (e) {
      error.value = e?.__payload?.message || e?.message || 'Request failed'
      app.value = null
    } finally {
      loading.value = false
    }
  }
  
  onMounted(fetchOne)
  </script>
  
  <style scoped>
  /* Matches ApplicantsList look: same page background, card system, pills, buttons */
  
  .page{
    min-height:100vh;
    background:
      radial-gradient(1200px 700px at 20% -10%, rgba(232,155,15,0.16), transparent 55%),
      radial-gradient(900px 600px at 100% 0%, rgba(232,155,15,0.10), transparent 50%),
      linear-gradient(180deg, #fafaf9 0%, #f5f5f4 100%);
    color:#111827;
    font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  }
  
  .container{
    max-width:1100px;
    margin:0 auto;
    padding:18px 18px 40px;
  }
  
  .header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
  }
  
  .headerLeft{ min-width:240px; }
  .headerRight{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
  }
  
  .linkBack{
    border:none;
    background:transparent;
    padding:0;
    font-size:12px;
    font-weight:800;
    color:#6b7280;
    cursor:pointer;
  }
  .linkBack:hover{ color:#374151; }
  
  .title{
    margin:10px 0 0;
    font-size:26px;
    line-height:1.15;
    letter-spacing:-0.02em;
    font-weight:800;
  }
  
  .subtitle{
    margin:8px 0 0;
    font-size:12px;
    line-height:1.55;
    color:#4b5563;
  }
  
  .grid{
    margin-top:18px;
    display:grid;
    grid-template-columns: 280px 1fr;
    gap:18px;
  }
  @media (max-width: 980px){
    .grid{ grid-template-columns: 1fr; }
  }
  
  .content{ display:flex; flex-direction:column; gap:12px; }
  
  .card{
    background:#fff;
    border:1px solid rgba(229,231,235,0.9);
    border-radius:18px;
    box-shadow:
      0 1px 0 rgba(17,24,39,0.02),
      0 14px 35px rgba(17,24,39,0.07);
  }
  
  .sticky{
    padding:16px;
    position:sticky;
    top:20px;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  @media (max-width: 980px){
    .sticky{ position:relative; top:auto; }
  }
  
  .block{ display:flex; flex-direction:column; gap:12px; }
  
  .kicker{
    font-size:11px;
    font-weight:800;
    color:#6b7280;
    letter-spacing:0.14em;
    text-transform:uppercase;
  }
  
  .divider{ height:1px; background:rgba(229,231,235,0.9); margin:6px 0; }
  
  .kv{ display:flex; justify-content:space-between; gap:12px; }
  .kvLabel{ font-size:11px; color:#6b7280; font-weight:800; letter-spacing:0.02em; }
  .kvValue{ font-size:12px; color:#111827; font-weight:900; text-align:right; }
  .clamp1{ overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:180px; }
  
  .actions{ display:flex; gap:10px; flex-wrap:wrap; }
  
  .btn{
    border-radius:999px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    padding:9px 12px;
    font-size:11px;
    font-weight:800;
    color:#111827;
    letter-spacing:0.02em;
    cursor:pointer;
    transition: transform .06s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease;
  }
  .btn:hover{
    background:#f9fafb;
    box-shadow: 0 16px 38px rgba(17,24,39,0.08);
    border-color: rgba(232,155,15,0.28);
  }
  .btn:active{ transform: translateY(0.5px) scale(0.99); }
  
  .btnPrimary{
    background: linear-gradient(180deg, #b45309 0%, #92400e 100%);
    border-color: rgba(146,64,14,0.55);
    color:#fff;
    box-shadow: 0 18px 42px rgba(232,155,15,0.18);
  }
  .btnPrimary:hover{
    background: linear-gradient(180deg, #92400e 0%, #7c2d12 100%);
    box-shadow: 0 22px 55px rgba(232,155,15,0.22);
  }
  
  .pill{
    display:inline-flex;
    align-items:center;
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:900;
    border:1px solid rgba(229,231,235,0.95);
  }
  .pillNeutral{ background:#f9fafb; color:#374151; }
  .pillBrand{ background:#fff7ed; border-color:#fed7aa; color:#7c2d12; }
  .pillSuccess{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
  .pillWarn{ background:#fffbeb; border-color:#fde68a; color:#78350f; }
  .pillDanger{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }
  
  .alert{
    border-radius:14px;
    padding:10px 12px;
    font-size:12px;
    line-height:1.45;
  }
  .alertError{
    background:#fef2f2;
    border:1px solid rgba(254,202,202,0.95);
    color:#b91c1c;
  }
  
  .state{ color:#6b7280; font-size:12px; }
  .statePad{ padding:14px 16px; color:#6b7280; font-size:12px; }
  
  .section{ padding:14px 16px; }
  .sectionHead{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
  }
  .sectionTitle{
    font-size:13px;
    font-weight:900;
    color:#111827;
  }
  .sectionSub{
    margin-top:4px;
    font-size:12px;
    color:#6b7280;
  }
  .sectionText{
    margin:12px 0 0;
    color:#374151;
    font-size:12px;
    line-height:1.65;
    white-space:pre-wrap;
  }
  .sectionEmpty{
    margin-top:12px;
    color:#6b7280;
    font-size:12px;
  }
  
  .list{ margin-top:12px; display:flex; flex-direction:column; gap:10px; }
  .row{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    padding:10px 12px;
    background:#f9fafb;
    border:1px solid rgba(229,231,235,0.95);
    border-radius:14px;
  }
  .rowMain{ min-width:0; display:flex; flex-direction:column; gap:6px; }
  .rowTitle{
    font-size:12px;
    color:#111827;
    font-weight:900;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  .rowFrom,.rowTo{ color:#111827; }
  .arrow{ color:#d1d5db; }
  .rowNote{ color:#6b7280; font-weight:800; }
  .rowMeta{ font-size:12px; color:#6b7280; font-weight:800; }
  
  .grid2{
    margin-top:12px;
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:12px 16px;
  }
  @media (max-width: 720px){
    .grid2{ grid-template-columns: 1fr; }
  }
  
  .link{ color:#2563eb; text-decoration:underline; word-break:break-all; }
  .link:hover{ color:#1d4ed8; }
  
  .muted{ color:#6b7280; font-weight:800; }
  .strong{ font-weight:900; color:#111827; }
  </style>