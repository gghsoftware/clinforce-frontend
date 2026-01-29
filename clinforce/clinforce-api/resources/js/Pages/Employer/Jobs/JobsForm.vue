<!-- resources/js/Pages/Employer/Jobs/JobsForm.vue -->
<script setup>
  import { computed, onMounted, ref } from "vue";
  import { useRoute, useRouter, RouterLink } from "vue-router";
  import UiCard from "../../../Components/UiCard.vue";
  import { http } from "../../../lib/http";
  
  const route = useRoute();
  const router = useRouter();
  
  const id = computed(() => route.params.id || null);
  const editing = computed(() => Boolean(id.value));
  
  const loading = ref(false);
  const error = ref("");
  
  const form = ref({
    title: "",
    description: "",
    employment_type: "full_time",
    work_mode: "on_site",
    country_code: "PH",
    city: "",
  });
  
  function validate() {
    const t = String(form.value.title || "").trim();
    const d = String(form.value.description || "").trim();
  
    if (t.length < 5) return "Title must be at least 5 characters.";
    if (d.length < 30) return "Description must be at least 30 characters.";
    if (!form.value.employment_type) return "Employment type is required.";
    if (!form.value.work_mode) return "Work mode is required.";
  
    const cc = String(form.value.country_code || "").trim();
    if (cc && !/^[A-Z]{2}$/.test(cc)) return "Country code must be 2-letter ISO (e.g., PH).";
  
    return "";
  }
  
  async function load() {
    if (!editing.value) return;
  
    loading.value = true;
    error.value = "";
  
    try {
      const res = await http.get(`/jobs/${id.value}`);
      const j = res.data?.data ?? res.data ?? {};
  
      form.value.title = j.title || "";
      form.value.description = j.description || "";
  
      // backend fields
      form.value.employment_type = j.employment_type || "full_time";
      form.value.work_mode = j.work_mode || "on_site";
      form.value.country_code = (j.country_code || "PH").toUpperCase();
      form.value.city = j.city || "";
    } catch (e) {
      error.value = e?.response?.data?.message || e?.message || "Failed to load job";
    } finally {
      loading.value = false;
    }
  }
  
  async function save() {
    error.value = "";
    const msg = validate();
    if (msg) {
      error.value = msg;
      return;
    }
  
    loading.value = true;
  
    const payload = {
      title: String(form.value.title || "").trim(),
      description: String(form.value.description || "").trim(),
      employment_type: form.value.employment_type,
      work_mode: form.value.work_mode,
      country_code: String(form.value.country_code || "").trim().toUpperCase() || null,
      city: String(form.value.city || "").trim() || null,
    };
  
    try {
      let res;
      if (editing.value) res = await http.put(`/jobs/${id.value}`, payload);
      else res = await http.post(`/jobs`, payload);
  
      const job = res.data?.data ?? res.data ?? {};
      const jobId = job?.id || id.value;
  
      router.push({ name: "employer.jobs.view", params: { id: jobId } });
    } catch (e) {
      const data = e?.response?.data;
      const base = data?.message || e?.message || "Save failed";
  
      // show Laravel validation errors clearly
      if (data?.errors && typeof data.errors === "object") {
        const flat = Object.values(data.errors).flat().join(" ");
        error.value = `${base} ${flat}`.trim();
      } else {
        error.value = base;
      }
    } finally {
      loading.value = false;
    }
  }
  
  onMounted(load);
  </script>
  
  <template>
    <div class="page">
      <div class="wrap">
        <div class="head">
          <div>
            <h1 class="title">{{ editing ? "Edit job" : "Post a new job" }}</h1>
            <p class="sub">Fields here match your backend validation (JobStoreRequest).</p>
          </div>
  
          <RouterLink class="back" :to="{ name: 'employer.jobs' }">Back</RouterLink>
        </div>
  
        <div v-if="error" class="alert" role="alert" aria-live="polite">
          <div class="alertTitle">Action required</div>
          <div class="alertMsg">{{ error }}</div>
        </div>
  
        <UiCard>
          <form class="form" @submit.prevent="save">
            <div class="field">
              <label class="label">Title</label>
              <input v-model="form.title" class="input" placeholder="ICU Nurse, MedTech, General Physician" :disabled="loading" />
            </div>
  
            <div class="grid2">
              <div class="field">
                <label class="label">Employment type</label>
                <select v-model="form.employment_type" class="select" :disabled="loading">
                  <option value="full_time">Full-time</option>
                  <option value="part_time">Part-time</option>
                  <option value="contract">Contract</option>
                  <option value="temporary">Temporary</option>
                  <option value="internship">Internship</option>
                </select>
              </div>
  
              <div class="field">
                <label class="label">Work mode</label>
                <select v-model="form.work_mode" class="select" :disabled="loading">
                  <option value="on_site">On-site</option>
                  <option value="hybrid">Hybrid</option>
                  <option value="remote">Remote</option>
                </select>
              </div>
            </div>
  
            <div class="grid2">
              <div class="field">
                <label class="label">Country code (ISO-2)</label>
                <input v-model="form.country_code" class="input" placeholder="PH" maxlength="2" :disabled="loading" />
                <div class="help">Example: PH, US</div>
              </div>
  
              <div class="field">
                <label class="label">City</label>
                <input v-model="form.city" class="input" placeholder="Quezon City" :disabled="loading" />
              </div>
            </div>
  
            <div class="field">
              <label class="label">Description</label>
              <textarea v-model="form.description" rows="8" class="textarea" :disabled="loading"
                placeholder="Write at least 30 characters. Include duties, schedule, qualifications, and hiring notes." />
              <div class="help">
                Minimum 30 characters (backend-enforced).
              </div>
            </div>
  
            <div class="actions">
              <div class="hint">Creates/updates via <code>/api/jobs</code>.</div>
  
              <div class="btns">
                <button class="btn ghost" type="button" :disabled="loading" @click="router.push({ name: 'employer.jobs' })">
                  Cancel
                </button>
                <button class="btn primary" type="submit" :disabled="loading">
                  {{ loading ? "Saving…" : (editing ? "Save changes" : "Create job") }}
                </button>
              </div>
            </div>
          </form>
        </UiCard>
      </div>
    </div>
  </template>
  
  <style scoped>
  .page {
    max-width: 920px;
    margin: 0 auto;
    padding: 24px 16px;
  }
  
  .wrap {
    display: grid;
    gap: 14px;
  }
  
  .head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
  }
  
  .title {
    margin: 0;
    font-size: 22px;
    font-weight: 950;
    letter-spacing: -0.02em;
    color: #111827;
  }
  
  .sub {
    margin: 6px 0 0;
    font-size: 12px;
    color: #6b7280;
  }
  
  .back {
    font-size: 12px;
    font-weight: 900;
    color: #111827;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
  }
  .back:hover {
    background: #f9fafb;
  }
  
  .alert {
    border: 1px solid rgba(239, 68, 68, 0.25);
    background: rgba(254, 242, 242, 1);
    border-radius: 16px;
    padding: 10px 12px;
  }
  .alertTitle {
    font-size: 12px;
    font-weight: 950;
    color: #991b1b;
  }
  .alertMsg {
    margin-top: 3px;
    font-size: 12px;
    color: #7f1d1d;
  }
  
  .form {
    display: grid;
    gap: 14px;
  }
  
  .field {
    display: grid;
    gap: 6px;
  }
  
  .label {
    font-size: 11px;
    font-weight: 950;
    color: #6b7280;
  }
  
  .help {
    font-size: 11px;
    color: #9ca3af;
  }
  
  .input,
  .select,
  .textarea {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #fff;
    color: #111827;
    font-size: 14px;
    outline: none;
  }
  
  .input,
  .select {
    height: 44px;
    padding: 0 12px;
  }
  
  .textarea {
    padding: 10px 12px;
    resize: vertical;
    min-height: 140px;
  }
  
  .input:focus,
  .select:focus,
  .textarea:focus {
    border-color: #d1d5db;
    box-shadow: 0 0 0 4px rgba(17, 24, 39, 0.08);
  }
  
  .grid2 {
    display: grid;
    gap: 12px;
    grid-template-columns: 1fr;
  }
  
  @media (min-width: 820px) {
    .grid2 {
      grid-template-columns: 1fr 1fr;
    }
  }
  
  .actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-top: 6px;
    border-top: 1px solid #f3f4f6;
    margin-top: 4px;
    padding-top: 14px;
  }
  
  .hint {
    font-size: 11px;
    color: #6b7280;
  }
  .hint code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 11px;
    background: #f9fafb;
    border: 1px solid #eee;
    padding: 2px 6px;
    border-radius: 999px;
    color: #374151;
  }
  
  .btns {
    display: flex;
    gap: 8px;
  }
  
  .btn {
    height: 44px;
    padding: 0 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 950;
    cursor: pointer;
    border: 1px solid transparent;
    transition: transform 120ms ease, background 120ms ease, opacity 120ms ease;
  }
  
  .btn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
    transform: none;
  }
  
  .btn.ghost {
    border-color: #e5e7eb;
    background: #fff;
    color: #111827;
  }
  .btn.ghost:hover {
    background: #f9fafb;
  }
  
  .btn.primary {
    background: #111827;
    color: #fff;
  }
  .btn.primary:hover {
    background: #0b1220;
    transform: translateY(-1px);
  }
  </style>
  