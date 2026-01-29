<!-- resources/js/Pages/Candidate/Messages.vue -->
<template>
  <CandidateLayout subtitle="Candidate messages" :displayName="meName">
    <div class="page">
      <div class="shell">
        <!-- Header -->
        <section class="head">
          <div class="headLeft">
            <h1 class="h1">Messages</h1>
            <p class="sub">View conversations and send messages.</p>
          </div>

          <div class="headRight">
            <button class="btn ghost" @click="openNew = true">
              New conversation
            </button>
            <button class="btn" @click="loadConversations" :disabled="loadingList">
              {{ loadingList ? 'Refreshing…' : 'Refresh' }}
            </button>
          </div>
        </section>

        <div class="grid">
          <!-- LEFT: Conversations -->
          <section class="card panel">
            <div class="panelHead">
              <div class="panelTitle">
                <div class="h2">Conversations</div>
                <div class="muted">Your recent threads</div>
              </div>

              <div class="tools">
                <div class="searchWrap">
                  <svg viewBox="0 0 24 24" fill="none" class="searchIcon" aria-hidden="true">
                    <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="1.7"/>
                    <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                  </svg>
                  <input
                    v-model="q"
                    class="search"
                    placeholder="Search…"
                    autocomplete="off"
                  />
                </div>
              </div>
            </div>

            <div v-if="listError" class="alert danger">
              <div class="alertTitle">Failed to load</div>
              <div class="alertText">{{ listError }}</div>
            </div>

            <div v-if="loadingList" class="state mutedPad">
              <div class="spinner" />
              <div>Loading…</div>
            </div>

            <div v-else class="convList">
              <button
                v-for="c in filteredConversations"
                :key="c.id"
                class="convItem"
                :class="{ active: selectedId === c.id }"
                @click="selectConversation(c.id)"
              >
                <div class="convTop">
                  <div class="convTitle">
                    {{ conversationTitle(c) }}
                  </div>
                  <div class="convTime">{{ fmtTime(c?.lastMessage?.created_at || c?.last_message_at) }}</div>
                </div>

                <div class="convSub">
                  <span class="chip">{{ lastFrom(c) }}</span>
                  <span class="convSnippet">{{ snippet(c?.lastMessage?.body) }}</span>
                </div>
              </button>

              <div v-if="filteredConversations.length === 0" class="empty">
                <div class="emptyTitle">No matches</div>
                <div class="emptyText">Try a different keyword.</div>
              </div>
            </div>
          </section>

          <!-- RIGHT: Thread -->
          <section class="card thread">
            <div class="threadHead">
              <div class="threadLeft">
                <div class="threadTitle">
                  {{ selected ? conversationTitle(selected) : 'Select a conversation' }}
                </div>
                <div class="threadMeta">
                  <span v-if="selected">{{ participantsLabel(selected) }}</span>
                  <span v-else class="muted">Pick a thread on the left</span>
                </div>
              </div>

              <div class="threadRight" v-if="selected">
                <button class="btn ghost sm" @click="loadThread(selected.id)" :disabled="loadingThread">
                  {{ loadingThread ? 'Loading…' : 'Reload' }}
                </button>
              </div>
            </div>

            <div v-if="threadError" class="alert danger threadAlert">
              <div class="alertTitle">Failed to load thread</div>
              <div class="alertText">{{ threadError }}</div>
            </div>

            <div v-if="loadingThread" class="state mutedPad">
              <div class="spinner" />
              <div>Loading messages…</div>
            </div>

            <div v-else class="messages" ref="messagesEl">
              <div v-if="!selected" class="empty pad">
                <div class="emptyTitle">No conversation selected</div>
                <div class="emptyText">Choose a thread to view messages.</div>
              </div>

              <template v-else>
                <div v-if="messages.length === 0" class="empty pad">
                  <div class="emptyTitle">No messages yet</div>
                  <div class="emptyText">Send the first message below.</div>
                </div>

                <div
                  v-for="m in messages"
                  :key="m.id"
                  class="msgRow"
                  :class="{ me: isMe(m), them: !isMe(m) }"
                >
                  <div class="msgBubble">
                    <div class="msgBody">{{ m.body }}</div>
                    <div class="msgMetaRow">
                      <span class="msgMeta">{{ who(m) }}</span>
                      <span class="dot">•</span>
                      <span class="msgMeta">{{ fmt(m.created_at) }}</span>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <!-- Composer -->
            <div class="composer" v-if="selected">
              <textarea
                v-model="draft"
                class="input"
                rows="2"
                placeholder="Type a message…"
                @keydown.enter.exact.prevent="sendMessage"
              />
              <button class="btn primary" @click="sendMessage" :disabled="sending || !draftTrimmed">
                {{ sending ? 'Sending…' : 'Send' }}
              </button>
            </div>
          </section>
        </div>
      </div>

      <!-- New conversation modal -->
      <div v-if="openNew" class="modalBackdrop" @click.self="openNew = false">
        <div class="modalCard">
          <div class="modalHead">
            <div>
              <div class="modalTitle">New conversation</div>
              <div class="muted">Enter user ID(s) and the first message.</div>
            </div>
            <button class="btn ghost sm" @click="openNew = false">Close</button>
          </div>

          <div class="formRow">
            <label class="label">Participant user IDs (comma-separated)</label>
            <input v-model="newParticipantsRaw" class="input" placeholder="e.g. 9004, 9006" />
          </div>

          <div class="formRow">
            <label class="label">Subject (optional)</label>
            <input v-model="newSubject" class="input" placeholder="e.g. Interview follow-up" />
          </div>

          <div class="formRow">
            <label class="label">First message</label>
            <textarea v-model="newFirstMessage" class="input" rows="3" placeholder="Write your message…" />
          </div>

          <div v-if="newError" class="alert danger">
            <div class="alertTitle">Cannot create</div>
            <div class="alertText">{{ newError }}</div>
          </div>

          <div class="modalActions">
            <button class="btn ghost" @click="openNew = false">Cancel</button>
            <button class="btn primary" @click="createConversation" :disabled="creating || !canCreate">
              {{ creating ? 'Creating…' : 'Create' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </CandidateLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import CandidateLayout from '@/Components/CandidateLayout.vue'
import api from '@/lib/api'

const meName = ref('ME')
const meId = ref(null)

const loadingList = ref(false)
const loadingThread = ref(false)
const listError = ref('')
const threadError = ref('')

const conversations = ref([])
const selectedId = ref(null)
const selected = ref(null)
const messages = ref([])

const draft = ref('')
const sending = ref(false)

const messagesEl = ref(null)

// search
const q = ref('')

// New conversation UI
const openNew = ref(false)
const newParticipantsRaw = ref('')
const newSubject = ref('')
const newFirstMessage = ref('')
const creating = ref(false)
const newError = ref('')

function unwrap(resData) {
  return resData?.data ?? resData
}
function asArray(payload) {
  const body = unwrap(payload)
  if (Array.isArray(body)) return body
  if (Array.isArray(body?.data)) return body.data
  return []
}

function fmt(v) {
  if (!v) return ''
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return String(v)
  return d.toLocaleString()
}
function fmtTime(v) {
  if (!v) return ''
  const d = new Date(v)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString(undefined, { month: 'short', day: '2-digit' })
}
function snippet(s) {
  const t = String(s || '').trim()
  if (!t) return '—'
  return t.length > 70 ? t.slice(0, 70) + '…' : t
}

function isMe(m) {
  const sid = m?.sender_user_id ?? m?.sender?.id ?? null
  return meId.value != null && Number(sid) === Number(meId.value)
}
function who(m) {
  if (isMe(m)) return 'You'
  const email = m?.sender?.email
  const role = m?.sender?.role
  return email || role || 'Sender'
}

function participantsLabel(c) {
  const parts = c?.participants || []
  const others = parts
    .map(p => p?.user)
    .filter(u => u && Number(u.id) !== Number(meId.value))
    .map(u => u.email || `${u.role}#${u.id}`)
  if (others.length === 0) return 'Just you'
  return others.join(', ')
}

function conversationTitle(c) {
  if (c?.subject) return c.subject
  const label = participantsLabel(c)
  return label === 'Just you' ? `Conversation #${c?.id}` : label
}

function lastFrom(c) {
  const m = c?.lastMessage
  if (!m) return '—'
  const sid = m?.sender_user_id ?? m?.sender?.id
  if (meId.value != null && Number(sid) === Number(meId.value)) return 'You'
  return m?.sender?.email || m?.sender?.role || 'Them'
}

async function fetchMe() {
  try {
    const res = await api.get('/auth/me')
    const body = unwrap(res.data)
    const user = body?.user ?? body
    meId.value = user?.id ?? null
    meName.value = user?.email || user?.name || 'ME'
  } catch {
    meName.value = 'ME'
    meId.value = null
  }
}

async function loadConversations() {
  listError.value = ''
  loadingList.value = true
  try {
    const res = await api.get('/conversations')
    conversations.value = asArray(res.data)
    if (!selectedId.value && conversations.value.length) {
      await selectConversation(conversations.value[0].id)
    }
  } catch (e) {
    listError.value = e?.__payload?.message || e?.message || 'Unable to load conversations'
    conversations.value = []
  } finally {
    loadingList.value = false
  }
}

async function selectConversation(id) {
  selectedId.value = id
  await loadThread(id)
}

async function loadThread(id) {
  threadError.value = ''
  loadingThread.value = true
  selected.value = null
  messages.value = []

  try {
    const res = await api.get(`/conversations/${id}`)
    const c = unwrap(res.data)
    selected.value = c
    messages.value = Array.isArray(c?.messages) ? c.messages : []

    await nextTick()
    scrollToBottom()

    const lastId = messages.value.length ? messages.value[messages.value.length - 1].id : null
    if (lastId) {
      api.post(`/conversations/${id}/read`, { last_read_message_id: lastId }).catch(() => {})
    }
  } catch (e) {
    threadError.value = e?.__payload?.message || e?.message || 'Unable to load thread'
  } finally {
    loadingThread.value = false
  }
}

function scrollToBottom() {
  const el = messagesEl.value
  if (!el) return
  el.scrollTop = el.scrollHeight + 9999
}

const draftTrimmed = computed(() => String(draft.value || '').trim())

async function sendMessage() {
  if (!selected.value) return
  const body = draftTrimmed.value
  if (!body) return

  sending.value = true
  try {
    const res = await api.post(`/conversations/${selected.value.id}/messages`, { body })
    const msg = unwrap(res.data)
    messages.value.push(msg)
    draft.value = ''
    await nextTick()
    scrollToBottom()

    const idx = conversations.value.findIndex(c => c.id === selected.value.id)
    if (idx >= 0) {
      conversations.value[idx] = { ...conversations.value[idx], lastMessage: msg }
    }
  } catch (e) {
    threadError.value = e?.__payload?.message || e?.message || 'Unable to send'
  } finally {
    sending.value = false
  }
}

const canCreate = computed(() => {
  const ids = parseParticipants(newParticipantsRaw.value)
  return ids.length > 0 && String(newFirstMessage.value || '').trim().length > 0
})

function parseParticipants(raw) {
  return String(raw || '')
    .split(',')
    .map(s => s.trim())
    .filter(Boolean)
    .map(x => Number(x))
    .filter(n => Number.isFinite(n) && n > 0)
}

async function createConversation() {
  newError.value = ''
  creating.value = true
  try {
    const ids = parseParticipants(newParticipantsRaw.value)
    const payload = {
      subject: String(newSubject.value || '').trim() || null,
      participant_user_ids: ids,
      first_message: String(newFirstMessage.value || '').trim(),
    }

    const res = await api.post('/conversations', payload)
    const convo = unwrap(res.data)

    openNew.value = false
    newParticipantsRaw.value = ''
    newSubject.value = ''
    newFirstMessage.value = ''

    await loadConversations()
    if (convo?.id) await selectConversation(convo.id)
  } catch (e) {
    newError.value = e?.__payload?.message || e?.message || 'Unable to create conversation'
  } finally {
    creating.value = false
  }
}

const filteredConversations = computed(() => {
  const term = String(q.value || '').trim().toLowerCase()
  if (!term) return conversations.value
  return conversations.value.filter(c => {
    const t = (conversationTitle(c) || '').toLowerCase()
    const last = (c?.lastMessage?.body || '').toLowerCase()
    return t.includes(term) || last.includes(term)
  })
})

onMounted(async () => {
  await fetchMe()
  await loadConversations()
})
</script>

<style scoped>
/* Match TopBar / Sidebar tokens */
:root{
  --bg: #ffffff;
  --panel: rgba(255,255,255,0.92);
  --ink: #111827;
  --muted: #6b7280;
  --line: rgba(229,231,235,1);
  --soft: rgba(17,24,39,0.04);
  --soft2: rgba(17,24,39,0.06);
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
.headLeft{ min-width: 220px; }
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

/* Layout grid */
.grid{
  margin-top: 12px;
  display:grid;
  grid-template-columns: 420px minmax(0, 1fr);
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
.panel{ padding: 12px; }
.thread{ display:flex; flex-direction:column; min-height: 560px; overflow:hidden; }

/* Panel head */
.panelHead{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
  padding: 4px;
  margin-bottom: 8px;
}
.panelTitle{ display:grid; gap: 2px; }
.h2{
  font-weight: 900;
  font-size: 12px;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: #374151;
}
.muted{ font-size: 11px; color: var(--muted); }

/* Search */
.tools{ display:flex; align-items:center; gap: 8px; }
.searchWrap{
  display:flex;
  align-items:center;
  gap: 8px;
  border: 1px solid var(--line);
  border-radius: var(--r14);
  background: #fff;
  padding: 8px 10px;
  min-width: 220px;
}
.searchIcon{ width: 16px; height: 16px; color: #4b5563; }
.search{
  border: 0;
  outline: none;
  font-size: 12px;
  width: 100%;
  color: var(--ink);
}

/* Conversation list */
.convList{
  display:flex;
  flex-direction:column;
  gap: 8px;
}
.convItem{
  width: 100%;
  text-align: left;
  border: 1px solid rgba(229,231,235,1);
  border-radius: var(--r14);
  background: #fff;
  padding: 10px;
  cursor:pointer;
  transition: background 120ms ease, transform 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
}
.convItem:hover{
  background: var(--soft);
  border-color: rgba(17,24,39,0.12);
  transform: translateY(-1px);
  box-shadow: 0 12px 22px rgba(17,24,39,0.10);
}
.convItem.active{
  background: var(--soft2);
  border-color: rgba(17,24,39,0.18);
  box-shadow: 0 12px 22px rgba(17,24,39,0.10);
}
.convTop{
  display:flex;
  justify-content:space-between;
  gap: 10px;
}
.convTitle{
  font-weight: 800;
  font-size: 12px;
  color: var(--ink);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  max-width: 280px;
}
.convTime{
  font-size: 11px;
  color: var(--muted);
  flex: 0 0 auto;
}
.convSub{
  margin-top: 8px;
  display:flex;
  align-items:center;
  gap: 8px;
  min-width: 0;
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
.convSnippet{
  font-size: 11px;
  color: var(--muted);
  overflow:hidden;
  white-space:nowrap;
  text-overflow:ellipsis;
  min-width: 0;
}

/* Thread head */
.threadHead{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--line);
  background: rgba(255,255,255,0.95);
}
.threadTitle{
  font-size: 13px;
  font-weight: 900;
  color: var(--ink);
}
.threadMeta{
  margin-top: 3px;
  font-size: 11px;
  color: var(--muted);
}
.threadAlert{ margin: 10px 12px 0; }

/* Messages */
.messages{
  padding: 14px;
  overflow:auto;
  flex: 1 1 auto;
}
.pad{ padding: 14px; }

.msgRow{
  display:flex;
  margin: 10px 0;
}
.msgRow.me{ justify-content:flex-end; }
.msgRow.them{ justify-content:flex-start; }

.msgBubble{
  max-width: 78%;
  border-radius: var(--r14);
  border: 1px solid rgba(229,231,235,1);
  padding: 10px 10px;
  background: #fff;
  box-shadow: 0 10px 20px rgba(17,24,39,0.06);
}
.msgRow.me .msgBubble{
  border-color: rgba(17,24,39,0.18);
  background: rgba(17,24,39,0.04);
}
.msgBody{
  font-size: 12px;
  line-height: 1.6;
  color: var(--ink);
  white-space: pre-wrap;
  word-break: break-word;
}
.msgMetaRow{
  margin-top: 8px;
  display:flex;
  gap: 6px;
  align-items:center;
}
.msgMeta{
  font-size: 10px;
  color: var(--muted);
  font-weight: 800;
}
.dot{
  color: #d1d5db;
  font-weight: 900;
}

/* Composer */
.composer{
  display:flex;
  gap: 10px;
  align-items:flex-end;
  padding: 12px;
  border-top: 1px solid var(--line);
  background: rgba(255,255,255,0.95);
}
.input{
  width: 100%;
  border: 1px solid var(--line);
  border-radius: var(--r14);
  padding: 10px 10px;
  font-size: 12px;
  outline: none;
  background: #fff;
}
.input:focus{
  border-color: rgba(17,24,39,0.22);
  box-shadow: 0 0 0 4px rgba(17,24,39,0.06);
}

/* Buttons (match TopBar style) */
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
  white-space: nowrap;
}
.btn:hover{ transform: translateY(-1px); background: #0b1220; }
.btn:active{ transform: translateY(0px); }
.btn:disabled{ opacity: .6; cursor: not-allowed; transform:none; }

.btn.ghost{
  background: #fff;
  color: var(--ink);
  border: 1px solid var(--line);
}
.btn.ghost:hover{ background: rgba(17,24,39,0.02); transform: translateY(-1px); }
.btn.sm{ padding: 7px 10px; font-size: 11px; border-radius: var(--r12); }

.btn.primary{
  background: #111827;
  color: #fff;
}

/* Alerts + empty */
.alert{
  border-radius: var(--r14);
  border: 1px solid rgba(229,231,235,1);
  background: rgba(255,255,255,0.9);
  padding: 10px;
  margin: 6px 4px 10px;
}
.alertTitle{ font-weight: 900; font-size: 12px; }
.alertText{ margin-top: 4px; font-size: 11px; color: var(--muted); }
.alert.danger{
  border-color: rgba(153,27,27,0.18);
  background: rgba(254,242,242,0.85);
}
.alert.danger .alertTitle{ color:#991b1b; }
.alert.danger .alertText{ color:#7f1d1d; }

.empty{
  border: 1px dashed rgba(229,231,235,1);
  border-radius: var(--r14);
  background: rgba(255,255,255,0.8);
  padding: 14px;
  text-align: center;
}
.emptyTitle{ font-weight: 900; font-size: 12px; color: var(--ink); }
.emptyText{ margin-top: 4px; font-size: 11px; color: var(--muted); }
.mutedPad{
  display:flex;
  align-items:center;
  gap: 10px;
  padding: 14px;
  color: var(--muted);
  font-size: 12px;
}

/* Spinner */
.spinner{
  width: 14px;
  height: 14px;
  border-radius: 999px;
  border: 2px solid rgba(17,24,39,0.18);
  border-top-color: rgba(17,24,39,0.55);
  animation: spin 700ms linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal */
.modalBackdrop{
  position: fixed;
  inset: 0;
  background: rgba(17,24,39,0.45);
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 18px;
  z-index: 80;
}
.modalCard{
  width: min(640px, 100%);
  border-radius: var(--r16);
  border: 1px solid var(--line);
  background: rgba(255,255,255,0.96);
  backdrop-filter: blur(12px);
  box-shadow: 0 26px 70px rgba(17,24,39,0.20);
  padding: 14px;
}
.modalHead{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap: 12px;
  padding: 4px 4px 10px;
  border-bottom: 1px solid rgba(229,231,235,0.9);
}
.modalTitle{ font-weight: 900; font-size: 14px; color: var(--ink); }
.formRow{
  display:flex;
  flex-direction:column;
  gap: 6px;
  padding: 10px 4px;
}
.label{
  font-size: 11px;
  font-weight: 900;
  color: #374151;
}
.modalActions{
  display:flex;
  justify-content:flex-end;
  gap: 10px;
  padding: 10px 4px 4px;
  border-top: 1px solid rgba(229,231,235,0.9);
}
</style>