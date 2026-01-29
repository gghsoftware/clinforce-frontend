<!-- resources/js/Pages/Candidate/Dashboard.vue -->
<template>
  <CandidateLayout subtitle="Candidate dashboard" :displayName="meName">
    <div class="page">
      <div class="container">
        <!-- Header -->
        <div class="header">
          <div class="headerLeft">
            <h1 class="h1">Good day, {{ firstName || 'Candidate' }}</h1>
            <p class="sub">
              Here’s a quick snapshot of your applications and recommended roles.
            </p>
          </div>

          <div class="headerRight">
            <RouterLink :to="{ name: 'candidate.jobs' }" class="btn btnPrimary">
              Browse jobs
            </RouterLink>
            <RouterLink :to="{ name: 'candidate.profile' }" class="btn btnGhost">
              Update profile
            </RouterLink>
          </div>
        </div>

        <!-- Stats -->
        <div class="statsGrid">
          <div class="card stat">
            <div class="label">Active applications</div>
            <div class="value">{{ stats.active }}</div>
            <div class="hint">{{ stats.interviewStage }} in interview stage</div>
          </div>

          <div class="card stat">
            <div class="label">Upcoming interviews</div>
            <div class="value">{{ stats.upcomingInterviews }}</div>
            <div class="hint">Next 7 days</div>
          </div>

          <div class="card stat">
            <div class="label">Profile completeness</div>
            <div class="value">{{ stats.profileCompleteness }}%</div>
            <div class="hint">Add licenses to reach 100%</div>
          </div>

          <div class="card stat">
            <div class="label">AI match strength</div>
            <div class="value">{{ stats.matchStrength }}</div>
            <div class="hint">Based on your activity</div>
          </div>
        </div>

        <!-- Main grid -->
        <div class="mainGrid">
          <!-- Left -->
          <div class="col">
            <section class="card section">
              <div class="sectionHead">
                <h2 class="h2">Recommended roles for you</h2>
                <RouterLink :to="{ name: 'candidate.jobs' }" class="link">
                  View all
                </RouterLink>
              </div>

              <div v-if="jobsLoading" class="muted">Loading…</div>

              <div v-else class="list">
                <RouterLink
                  v-for="j in jobs"
                  :key="j.id"
                  :to="{ name: 'candidate.job.view', params: { id: j.id } }"
                  class="listItem"
                >
                  <div class="listTop">
                    <div class="listLeft">
                      <div class="listTitle">{{ j.title }}</div>
                      <div class="listMeta">
                        {{ [j.city, j.country_code].filter(Boolean).join(', ') || '—' }}
                        <span class="dot">•</span>
                        {{ j.employment_type || '—' }}
                      </div>
                    </div>

                    <div class="listRight">
                      <div class="listMeta">Status</div>
                      <div class="statusGood">{{ j.status || 'published' }}</div>
                    </div>
                  </div>
                </RouterLink>

                <div v-if="jobs.length === 0" class="muted">
                  No recommended jobs available.
                </div>
              </div>
            </section>

            <section class="card section">
              <div class="sectionHead">
                <h2 class="h2">Recent activity</h2>
                <span class="mutedSmall">Last 7 days</span>
              </div>

              <div class="activity">
                <div v-for="a in apps.slice(0, 3)" :key="a.id" class="activityRow">
                  <div class="activityTitle">
                    {{ a.job?.title || 'Job' }} — <span class="pill">{{ a.status }}</span>
                  </div>
                  <div class="mutedSmall">
                    Application #{{ a.id }} • Updated
                    {{ relativeTime(a.updated_at || a.submitted_at || a.created_at) }}
                  </div>
                </div>

                <div v-if="apps.length === 0" class="muted">
                  No recent activity.
                </div>
              </div>
            </section>
          </div>

          <!-- Right -->
          <div class="col">
            <section class="card section">
              <div class="sectionHead">
                <h2 class="h2">Upcoming interviews</h2>
                <RouterLink :to="{ name: 'candidate.interviews' }" class="link">
                  View interviews
                </RouterLink>
              </div>

              <div class="rows">
                <div v-for="i in upcomingInterviewRows" :key="i.id" class="row">
                  <span class="truncate">{{ i.title }}</span>
                  <span class="mutedSmall">{{ i.when }}</span>
                </div>

                <div v-if="upcomingInterviewRows.length === 0" class="muted">
                  No upcoming interviews.
                </div>
              </div>
            </section>

            <section class="card section">
              <div class="sectionHead">
                <h2 class="h2">Profile checklist</h2>
                <span class="mutedSmall">{{ stats.profileCompleteness }}% complete</span>
              </div>

              <ul class="checklist">
                <li>✅ Basic details completed</li>
                <li>✅ Applications enabled</li>
                <li>⬜ Add at least one license</li>
                <li>⬜ Upload CV / resume</li>
                <li>⬜ Add top skills</li>
              </ul>

              <RouterLink :to="{ name: 'candidate.profile' }" class="btn btnPrimary btnBlock">
                Complete profile
              </RouterLink>
            </section>
          </div>
        </div>
      </div>
    </div>
  </CandidateLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { RouterLink } from "vue-router";
import CandidateLayout from "@/Components/CandidateLayout.vue";
import api from "@/lib/api";

const meName = ref("ME");
const firstName = computed(() => (meName.value || "").split(" ")[0] || "");

const apps = ref([]);
const jobs = ref([]);
const jobsLoading = ref(false);

function unwrap(resData) {
  return resData?.data ?? resData;
}
function unwrapPaginated(resData) {
  const body = unwrap(resData);
  if (body?.data && Array.isArray(body.data)) return body.data;
  if (Array.isArray(body)) return body;
  return [];
}
function relativeTime(v) {
  if (!v) return "—";
  const d = new Date(v);
  const diff = Date.now() - d.getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 60) return `${mins} min ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs} hours ago`;
  const days = Math.floor(hrs / 24);
  return `${days} days ago`;
}

const stats = computed(() => {
  const active = apps.value.filter((a) => !["rejected", "withdrawn"].includes(a.status)).length;
  const interviewStage = apps.value.filter((a) => a.status === "interview").length;
  const upcomingInterviews = interviewStage;
  const profileCompleteness = 84;
  const matchStrength = active > 0 ? "High" : "—";
  return { active, interviewStage, upcomingInterviews, profileCompleteness, matchStrength };
});

const upcomingInterviewRows = computed(() => {
  return apps.value
    .filter((a) => a.status === "interview")
    .slice(0, 3)
    .map((a) => ({
      id: a.id,
      title: a.job?.title || `Application #${a.id}`,
      when: "Scheduled",
    }));
});

async function fetchMe() {
  try {
    // ✅ do NOT include "/api" because api.js already prepends it
    const res = await api.get("/auth/me");
    const body = unwrap(res.data);
    const u = body?.user ?? body;
    meName.value = u?.full_name || u?.name || u?.email || "ME";
  } catch {
    meName.value = "ME";
  }
}

async function fetchApps() {
  // ✅ do NOT include "/api"
  const res = await api.get("/applications", { params: { scope: "mine" } });
  apps.value = unwrapPaginated(res.data);
}

async function fetchJobs() {
  jobsLoading.value = true;
  try {
    // ✅ do NOT include "/api"
    const res = await api.get("/public/jobs", { params: { per_page: 3 } });
    jobs.value = unwrapPaginated(res.data);
  } finally {
    jobsLoading.value = false;
  }
}

onMounted(async () => {
  await fetchMe();
  await fetchApps();
  await fetchJobs();
});
</script>

<style scoped>
/* (styles unchanged) */
*, *::before, *::after { box-sizing: border-box; }
.page{ overflow-x: hidden; }

.page{
  min-height: 100vh;
  background:
    radial-gradient(1200px 700px at 20% -10%, rgba(232,155,15,0.16), transparent 55%),
    radial-gradient(900px 600px at 100% 0%, rgba(232,155,15,0.10), transparent 50%),
    linear-gradient(180deg, #fafaf9 0%, #f5f5f4 100%);
  color:#111827;
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
}

.container{ max-width: 1100px; margin: 0 auto; padding: 20px 16px 44px; }
.header{ display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.headerLeft{ min-width: 0; flex: 1 1 280px; }
.headerRight{ display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end; max-width: 100%; }

.h1{ margin:0; font-size: 24px; line-height: 1.15; letter-spacing: -0.02em; font-weight: 900; max-width: 100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sub{ margin: 8px 0 0; font-size: 12px; line-height: 1.55; color:#4b5563; max-width: 100%; }

.card{ background:#fff; border:1px solid rgba(229,231,235,0.92); border-radius: 18px; box-shadow: 0 1px 0 rgba(17,24,39,0.02), 0 14px 35px rgba(17,24,39,0.07); }

.btn{ display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:999px; padding: 9px 14px; border:1px solid rgba(209,213,219,0.95); background:#fff; color:#111827; text-decoration:none; font-size: 12px; font-weight: 900; letter-spacing: 0.02em; transition: transform .06s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease; max-width: 100%; }
.btn:hover{ background:#f9fafb; box-shadow: 0 16px 38px rgba(17,24,39,0.08); border-color: rgba(232,155,15,0.28); }
.btn:active{ transform: translateY(0.5px) scale(0.99); }

.btnPrimary{ background: linear-gradient(180deg, #b45309 0%, #92400e 100%); border-color: rgba(146,64,14,0.55); color:#fff; box-shadow: 0 18px 42px rgba(232,155,15,0.18); }
.btnPrimary:hover{ background: linear-gradient(180deg, #92400e 0%, #7c2d12 100%); box-shadow: 0 22px 55px rgba(232,155,15,0.22); }
.btnGhost{ background:#fff; border-color: rgba(209,213,219,0.95); color:#111827; }
.btnBlock{ width:100%; margin-top: 12px; }

.statsGrid{ margin-top: 14px; display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 980px){ .statsGrid{ grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 480px){ .statsGrid{ grid-template-columns: 1fr; } }
.stat{ padding: 14px; min-width: 0; }
.label{ color:#6b7280; font-size: 12px; }
.value{ margin-top: 6px; font-size: 20px; font-weight: 900; color:#111827; max-width: 100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.hint{ margin-top: 6px; font-size: 11px; color:#6b7280; max-width: 100%; }

.mainGrid{ margin-top: 14px; display:grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 14px; }
@media (max-width: 980px){ .mainGrid{ grid-template-columns: 1fr; } }
.col{ display:flex; flex-direction:column; gap: 14px; min-width:0; }

.section{ padding: 14px 16px; }
.sectionHead{ display:flex; align-items:center; justify-content:space-between; gap: 12px; margin-bottom: 10px; flex-wrap:wrap; }
.h2{ margin:0; font-size: 14px; font-weight: 900; letter-spacing: -0.01em; color:#111827; }

.link{ font-size: 11px; font-weight: 900; color:#b45309; text-decoration:none; }
.link:hover{ color:#92400e; text-decoration:underline; }

.muted{ color:#6b7280; font-size: 12px; padding: 6px 0; }
.mutedSmall{ color:#6b7280; font-size: 11px; }

.list{ display:flex; flex-direction:column; gap: 10px; }
.listItem{ display:block; text-decoration:none; color: inherit; border:1px solid rgba(229,231,235,0.92); border-radius: 14px; padding: 12px; transition: transform .12s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease; }
.listItem:hover{ background: rgba(245,245,244,0.6); border-color: rgba(232,155,15,0.28); transform: translateY(-1px); box-shadow: 0 18px 50px rgba(17,24,39,0.09); }

.listTop{ display:flex; align-items:flex-start; justify-content:space-between; gap: 12px; }
.listLeft{ min-width: 0; }
.listTitle{ font-size: 13px; font-weight: 900; color:#111827; max-width: 100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.listMeta{ margin-top: 4px; font-size: 11px; color:#6b7280; max-width: 100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dot{ margin: 0 6px; color:#d1d5db; font-weight: 900; }

.listRight{ text-align:right; flex: 0 0 auto; }
.statusGood{ margin-top: 2px; font-size: 13px; font-weight: 900; color:#059669; max-width: 100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

.activity{ display:flex; flex-direction:column; gap: 10px; }
.activityRow{ min-width:0; }
.activityTitle{ font-size: 12px; font-weight: 900; color:#111827; display:flex; align-items:center; gap: 8px; flex-wrap:wrap; min-width:0; }

.pill{ display:inline-flex; align-items:center; padding: 3px 10px; border-radius: 999px; border: 1px solid rgba(229,231,235,0.92); background:#f9fafb; font-size: 11px; font-weight: 900; color:#374151; max-width: 100%; }

.rows{ display:flex; flex-direction:column; gap: 10px; }
.row{ display:flex; align-items:center; justify-content:space-between; gap: 12px; border: 1px solid rgba(229,231,235,0.92); border-radius: 14px; padding: 10px 12px; background:#fff; min-width:0; }
.truncate{ min-width: 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size: 12px; font-weight: 800; color:#111827; }

.checklist{ margin: 0; padding-left: 18px; color:#374151; font-size: 12px; line-height: 1.7; }
</style>