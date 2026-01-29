<!-- resources/js/Applicants/ApplicantsList.vue -->
<template>
    <AppLayout>
      <div class="page">
        <div class="container">
          <!-- Top header -->
          <div class="top">
            <div class="topLeft">
              <h1 class="title">Applicants</h1>
              <p class="subtitle">Driven by job applications from your API.</p>
  
              <div class="stats">
                <div class="stat">
                  <div class="statK">Total</div>
                  <div class="statV">{{ stats.total }}</div>
                </div>
                <div class="stat">
                  <div class="statK">Submitted</div>
                  <div class="statV">{{ stats.submitted }}</div>
                </div>
                <div class="stat">
                  <div class="statK">Interview</div>
                  <div class="statV">{{ stats.interview }}</div>
                </div>
                <div class="stat">
                  <div class="statK">Hired</div>
                  <div class="statV">{{ stats.hired }}</div>
                </div>
              </div>
            </div>
  
            <div class="topRight">
              <div class="seg">
                <button
                  type="button"
                  class="segBtn"
                  :class="{ segActive: scope === 'owned' }"
                  @click="scope = 'owned'; fetchData(1)"
                >
                  Owned
                </button>
                <button
                  type="button"
                  class="segBtn"
                  :class="{ segActive: scope === 'mine' }"
                  @click="scope = 'mine'; fetchData(1)"
                >
                  Mine
                </button>
              </div>
  
              <button class="btn btnGhost" type="button" @click="fetchData(1)">
                Refresh
              </button>
            </div>
          </div>
  
          <div class="grid">
            <!-- Filters -->
            <aside class="sidebar">
              <div class="card filters">
                <div class="filtersHead">
                  <div>
                    <div class="cardTitle">Filters</div>
                    <div class="cardSub">Search and narrow results.</div>
                  </div>
  
                  <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="chip"
                    @click="resetFilters"
                  >
                    Clear
                  </button>
                </div>
  
                <div class="block">
                  <div class="kicker">Search</div>
                  <div class="inputWrap">
                    <span class="icon">⌕</span>
                    <input
                      v-model="search"
                      class="control controlInput"
                      type="text"
                      placeholder="Job title, applicant ID…"
                      @keydown.enter="applyFilters"
                    />
                  </div>
                </div>
  
                <div class="block">
                  <div class="kicker">Status</div>
                  <select v-model="status" class="control controlSelect">
                    <option value="">All</option>
                    <option v-for="s in allowedStatuses" :key="s" :value="s">
                      {{ s }}
                    </option>
                  </select>
  
                  <div class="quick">
                    <button
                      v-for="s in quickStatuses"
                      :key="s"
                      type="button"
                      class="qBtn"
                      :class="{ qActive: status === s }"
                      @click="status = (status === s ? '' : s); fetchData(1)"
                    >
                      {{ s }}
                    </button>
                  </div>
                </div>
  
                <div class="actions">
                  <button class="btn btnPrimary" type="button" @click="applyFilters">
                    Apply
                  </button>
                  <button class="btn btnGhost" type="button" @click="resetFilters">
                    Reset
                  </button>
                </div>
  
                <div v-if="error" class="alert alertError">
                  {{ error }}
                </div>
  
                <div class="hint">
                  Tip: press <span class="kbd">Enter</span> to search.
                </div>
              </div>
            </aside>
  
            <!-- Results -->
            <section class="content">
              <div v-if="loading" class="card state">
                <div class="spinner" aria-hidden="true"></div>
                <div>
                  <div class="stateTitle">Loading applicants</div>
                  <div class="stateSub">Fetching latest data…</div>
                </div>
              </div>
  
              <div v-else-if="items.length === 0" class="card state">
                <div class="stateTitle">No results</div>
                <div class="stateSub">Try clearing filters or switching scope.</div>
                <button class="btn btnPrimary" type="button" @click="resetFilters">
                  Clear filters
                </button>
              </div>
  
              <article v-for="row in items" :key="row.id" class="card item">
                <div class="itemRow">
                  <div class="avatar" :title="`Applicant #${row.applicant_user_id}`">
                    {{ initials(row.applicant_user_id) }}
                  </div>
  
                  <div class="itemMain">
                    <div class="itemTop">
                      <div class="itemTitle">
                        Applicant #{{ row.applicant_user_id }}
                      </div>
  
                      <span class="dot">•</span>
  
                      <div class="itemJob" :title="row.job?.title || 'Job'">
                        {{ row.job?.title || 'Job' }}
                      </div>
  
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
  
                  <div class="itemRight">
                    <button
                      class="btn btnSmall btnPrimarySoft"
                      type="button"
                      @click="router.push({ name: 'applicants.view', params: { id: row.id } })"
                    >
                      View
                    </button>
                  </div>
                </div>
              </article>
  
              <div v-if="pagination" class="pager">
                <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
  
                <div class="pagerBtns">
                  <button
                    class="btn btnSmall btnGhost"
                    type="button"
                    :disabled="!pagination.prev_page_url"
                    @click="fetchPage(pagination.current_page - 1)"
                  >
                    Prev
                  </button>
                  <button
                    class="btn btnSmall btnGhost"
                    type="button"
                    :disabled="!pagination.next_page_url"
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
  import { computed, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import AppLayout from '@/Components/AppLayout.vue'
  import api from '@/lib/api'
  
  const router = useRouter()
  
  const loading = ref(false)
  const error = ref('')
  const scope = ref('owned') // owned|mine
  
  const status = ref('')
  const search = ref('')
  
  const allowedStatuses = [
    'submitted',
    'shortlisted',
    'rejected',
    'interview',
    'hired',
    'withdrawn',
  ]
  
  const quickStatuses = ['submitted', 'interview', 'hired', 'rejected']
  
  const raw = ref(null)
  
  const hasActiveFilters = computed(() => !!status.value || !!search.value.trim())
  
  const items = computed(() => {
    const rows = raw.value?.data || []
    const q = search.value.trim().toLowerCase()
  
    return rows.filter((r) => {
      if (status.value && r.status !== status.value) return false
      if (!q) return true
  
      const hay = [
        String(r.id || ''),
        String(r.applicant_user_id || ''),
        String(r.status || ''),
        String(r.job?.title || ''),
      ].join(' ').toLowerCase()
  
      return hay.includes(q)
    })
  })
  
  const stats = computed(() => {
    const rows = raw.value?.data || []
    const count = (s) => rows.filter((r) => r.status === s).length
    return {
      total: rows.length,
      submitted: count('submitted'),
      interview: count('interview'),
      hired: count('hired'),
    }
  })
  
  const pagination = computed(() => {
    if (!raw.value) return null
    return {
      current_page: raw.value.current_page,
      last_page: raw.value.last_page,
      next_page_url: raw.value.next_page_url,
      prev_page_url: raw.value.prev_page_url,
    }
  })
  
  function initials(v) {
    const s = String(v ?? '').trim()
    if (!s) return 'A'
    const last = s.slice(-2)
    return last.toUpperCase()
  }
  
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
  
  async function fetchData(page = 1) {
    loading.value = true
    error.value = ''
    try {
      const params = { scope: scope.value, page }
      if (status.value) params.status = status.value
  
      const res = await api.get('/api/applications', { params })
      raw.value = res.data?.data ?? res.data
      if (res.data?.data?.data) raw.value = res.data.data
    } catch (e) {
      error.value = e?.__payload?.message || e?.message || 'Request failed'
      raw.value = null
    } finally {
      loading.value = false
    }
  }
  
  function fetchPage(p) { fetchData(p) }
  function applyFilters() { fetchData(1) }
  function resetFilters() { status.value = ''; search.value = ''; fetchData(1) }
  
  onMounted(() => { fetchData(1) })
  </script>
  
  <style scoped>
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
  
  /* Header */
  .top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
  }
  
  .title{
    font-size:28px;
    line-height:1.1;
    letter-spacing:-0.03em;
    margin:0;
    font-weight:900;
  }
  
  .subtitle{
    margin:8px 0 0;
    font-size:12px;
    line-height:1.55;
    color:#4b5563;
  }
  
  .stats{
    margin-top:14px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }
  
  .stat{
    background: rgba(255,255,255,0.75);
    border: 1px solid rgba(229,231,235,0.95);
    border-radius: 14px;
    padding: 10px 12px;
    min-width: 110px;
    box-shadow: 0 10px 25px rgba(17,24,39,0.06);
  }
  
  .statK{
    font-size:10px;
    font-weight:900;
    letter-spacing:0.14em;
    text-transform:uppercase;
    color:#6b7280;
  }
  
  .statV{
    margin-top:6px;
    font-size:16px;
    font-weight:900;
    color:#111827;
  }
  
  .topRight{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
  }
  
  /* Segmented scope */
  .seg{
    display:flex;
    background:#fff;
    border:1px solid rgba(229,231,235,0.95);
    border-radius:999px;
    padding:4px;
    box-shadow: 0 14px 35px rgba(17,24,39,0.06);
  }
  
  .segBtn{
    border:0;
    background:transparent;
    padding:8px 12px;
    border-radius:999px;
    font-size:11px;
    font-weight:900;
    letter-spacing:0.02em;
    color:#4b5563;
    cursor:pointer;
  }
  
  .segActive{
    background: linear-gradient(180deg, #b45309 0%, #92400e 100%);
    color:#fff;
  }
  
  /* Layout */
  .grid{
    margin-top:18px;
    display:grid;
    grid-template-columns: 320px 1fr;
    gap:18px;
  }
  
  @media (max-width: 980px){
    .grid{ grid-template-columns: 1fr; }
  }
  
  /* Cards */
  .card{
    background:#fff;
    border:1px solid rgba(229,231,235,0.9);
    border-radius:18px;
    box-shadow:
      0 1px 0 rgba(17,24,39,0.02),
      0 18px 45px rgba(17,24,39,0.08);
  }
  
  .filters{
    padding:16px;
    position:sticky;
    top:20px;
    display:flex;
    flex-direction:column;
    gap:14px;
  }
  
  @media (max-width: 980px){
    .filters{ position:relative; top:auto; }
  }
  
  .filtersHead{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:10px;
  }
  
  .cardTitle{
    font-size:13px;
    font-weight:950;
  }
  
  .cardSub{
    margin-top:4px;
    font-size:11px;
    color:#6b7280;
  }
  
  .chip{
    border-radius:999px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    padding:7px 10px;
    font-size:11px;
    font-weight:900;
    cursor:pointer;
  }
  
  .chip:hover{
    background:#f9fafb;
    border-color: rgba(232,155,15,0.28);
  }
  
  .block{ display:flex; flex-direction:column; gap:8px; }
  
  .kicker{
    font-size:10px;
    font-weight:700;
    color:#6b7280;
    letter-spacing:0.14em;
    text-transform:uppercase;
  }
  
  /* Inputs */
 /* keep it centered and make it not too wide */
.inputWrap{
  position: relative;
  max-width: 200px;  /* adjust: 420-620 */
  width: 100%;
}
  
  .icon{
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    font-size:12px;
    color:#9ca3af;
    pointer-events:none;
  }
  
  .control{
    width:100%;
    border-radius:14px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    padding:10px 12px;
    font-size:12px;
    color:#111827;
    outline:none;
    transition: box-shadow .18s ease, border-color .18s ease;
  }
  
  .controlInput{
    padding-left:32px;
  }
  
  .control:focus{
    border-color: rgba(232,155,15,0.9);
    box-shadow: 0 0 0 5px rgba(232,155,15,0.18);
  }
  
  .controlSelect{
    appearance:none;
    background-image:
      linear-gradient(45deg, transparent 50%, #9ca3af 50%),
      linear-gradient(135deg, #9ca3af 50%, transparent 50%);
    background-position:
      calc(100% - 16px) calc(1em + 1px),
      calc(100% - 11px) calc(1em + 1px);
    background-size:5px 5px, 5px 5px;
    background-repeat:no-repeat;
    padding-right:34px;
  }
  
  .quick{
    margin-top:10px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }
  
  .qBtn{
    border-radius:999px;
    border:1px solid rgba(229,231,235,0.95);
    background:#fff;
    padding:7px 10px;
    font-size:11px;
    font-weight:900;
    color:#374151;
    cursor:pointer;
  }
  
  .qBtn:hover{ background:#f9fafb; }
  .qActive{
    border-color:#fed7aa;
    background:#fff7ed;
    color:#7c2d12;
  }
  
  /* Buttons */
  .actions{
    display:flex;
    gap:10px;
  }
  
  .btn{
    border-radius:999px;
    border:1px solid rgba(209,213,219,0.95);
    background:#fff;
    padding:9px 12px;
    font-size:11px;
    font-weight:900;
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
  .btn:disabled{ opacity:0.55; cursor:not-allowed; box-shadow:none; transform:none; }
  
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
  
  .btnGhost{
    background: rgba(255,255,255,0.75);
  }
  
  .btnSmall{ padding:8px 11px; }
  
  .btnPrimarySoft{
    background:#fff7ed;
    border-color:#fed7aa;
    color:#7c2d12;
  }
  
  .btnPrimarySoft:hover{
    background:#ffedd5;
    border-color:#fdba74;
    box-shadow: 0 18px 40px rgba(17,24,39,0.10);
  }
  
  /* Alerts */
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
  
  .hint{
    font-size:11px;
    color:#6b7280;
  }
  
  .kbd{
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas;
    font-size:10px;
    padding:2px 6px;
    border-radius:8px;
    border:1px solid rgba(229,231,235,0.95);
    background:#fff;
    color:#111827;
  }
  
  /* Content */
  .content{ display:flex; flex-direction:column; gap:12px; }
  
  .state{
    padding:14px 16px;
    display:flex;
    gap:12px;
    align-items:center;
    color:#6b7280;
    font-size:12px;
  }
  
  .stateTitle{
    font-weight:900;
    color:#111827;
  }
  
  .stateSub{
    margin-top:2px;
    font-size:11px;
    color:#6b7280;
  }
  
  .spinner{
    width:18px;
    height:18px;
    border-radius:999px;
    border:2px solid rgba(209,213,219,1);
    border-top-color: rgba(180,83,9,1);
    animation: spin 0.8s linear infinite;
  }
  
  @keyframes spin{
    to { transform: rotate(360deg); }
  }
  
  /* Items */
  .item{
    padding:14px 16px;
    transition: transform .14s ease, box-shadow .18s ease, border-color .18s ease;
  }
  
  .item:hover{
    transform: translateY(-1px);
    border-color: rgba(232,155,15,0.28);
    box-shadow: 0 26px 70px rgba(17,24,39,0.12);
  }
  
  .itemRow{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
  }
  
  .avatar{
    width:40px;
    height:40px;
    border-radius:999px;
    background: rgba(24,24,27,0.92);
    border:1px solid rgba(232,155,15,0.55);
    color: rgba(245,176,33,1);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:12px;
    font-weight:900;
    flex: 0 0 auto;
  }
  
  .itemMain{
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:8px;
    flex: 1 1 auto;
  }
  
  .itemRight{
    flex: 0 0 auto;
    display:flex;
    align-items:center;
  }
  
  .itemTop{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:8px;
  }
  
  .itemTitle{
    font-size:13px;
    font-weight:950;
    color:#111827;
    max-width:100%;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  
  .itemJob{
    font-size:13px;
    font-weight:800;
    color:#374151;
    max-width:520px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  
  .dot{ color:#d1d5db; font-weight:900; }
  
  .pill{
    display:inline-flex;
    align-items:center;
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:950;
    border:1px solid rgba(229,231,235,0.95);
  }
  
  .pillNeutral{ background:#f9fafb; color:#374151; }
  .pillBrand{ background:#fff7ed; border-color:#fed7aa; color:#7c2d12; }
  .pillSuccess{ background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }
  .pillWarn{ background:#fffbeb; border-color:#fde68a; color:#78350f; }
  .pillDanger{ background:#fef2f2; border-color:#fecaca; color:#7f1d1d; }
  
  .meta{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    font-size:12px;
    color:#6b7280;
  }
  
  .metaDot{ color:#d1d5db; font-weight:900; }
  
  .strong{
    font-weight:950;
    color:#111827;
  }
  
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
  
  /* Pager */
  .pager{
    display:flex;
    flex-wrap:wrap;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    padding-top:6px;
    color:#6b7280;
    font-size:12px;
  }
  
  .pagerBtns{ display:flex; gap:10px; }
  </style>