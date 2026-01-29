<!-- resources/js/Pages/Auth/Register.vue -->
<script setup>
  import { ref, computed } from "vue";
  import { useRouter, RouterLink } from "vue-router";
  import axios from "axios";
  import AppLayout from "@/Components/AppLayout.vue";
  
  const router = useRouter();
  
  const fullName = ref("");
  const email = ref("");
  const accountType = ref(""); // employer | clinician
  const password = ref("");
  const passwordConfirm = ref("");
  const agree = ref(false);
  
  const loading = ref(false);
  const error = ref("");
  
  const showPassword = ref(false);
  const showConfirm = ref(false);
  
  const year = new Date().getFullYear();
  
  const roleForApi = computed(() => {
    if (accountType.value === "employer") return "employer";
    if (accountType.value === "clinician") return "applicant";
    return "";
  });
  
  function clearErrorOnType() {
    if (error.value) error.value = "";
  }
  
  async function onSubmit() {
    error.value = "";
  
    if (!fullName.value.trim()) return (error.value = "Full name is required.");
    if (!email.value.trim()) return (error.value = "Email is required.");
    if (!roleForApi.value) return (error.value = "Please select an account type.");
    if (!password.value || password.value.length < 8) return (error.value = "Password must be at least 8 characters.");
    if (password.value !== passwordConfirm.value) return (error.value = "Passwords do not match.");
    if (!agree.value) return (error.value = "You must agree to the Terms and Privacy Policy.");
  
    loading.value = true;
    try {
      const res = await axios.post("/api/auth/register", {
        role: roleForApi.value,
        email: email.value.trim(),
        phone: null,
        password: password.value,
      });
  
      const token = res?.data?.data?.token;
      const user = res?.data?.data?.user;
  
      if (token) localStorage.setItem("auth_token", token);
      if (user) localStorage.setItem("auth_user", JSON.stringify(user));
  
      if (user?.role === "applicant") return router.push({ name: "candidate.dashboard" });
      return router.push({ name: "applicants.list" });
    } catch (e) {
      const msg = e?.response?.data?.message || "Registration failed.";
      const errs = e?.response?.data?.errors;
      error.value = errs ? `${msg} ${Object.values(errs).flat().join(" ")}` : msg;
    } finally {
      loading.value = false;
    }
  }
  </script>
  
  <template>
    <AppLayout>
      <div class="page">
        <div class="wrap">
          <!-- Brand -->
          <div class="brand">
            <div class="logo" aria-hidden="true">
              <svg class="logoIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="4" y="4" width="16" height="16" rx="4" />
                <path d="M12 8v8M8 12h8" stroke-linecap="round" />
              </svg>
            </div>
            <div class="brandText">
              <div class="brandTitle">AI Clinforce Partners</div>
              <div class="brandSub">Clinical workforce network</div>
            </div>
          </div>
  
          <!-- Card -->
          <div class="card">
            <div class="cardHead">
              <h1 class="h1">Create your account</h1>
              <p class="p">Join as a healthcare employer or clinician.</p>
            </div>
  
            <div class="inner">
              <form class="form" @submit.prevent="onSubmit">
                <div v-if="error" class="alert" role="alert" aria-live="polite">
                  <div class="alertTitle">Registration failed</div>
                  <div class="alertMsg">{{ error }}</div>
                </div>
  
                <!-- 2x2 grid (mobile -> 1 col) -->
                <div class="grid">
                  <div class="field">
                    <label class="label" for="fullName">Full name</label>
                    <div class="control">
                      <input
                        id="fullName"
                        v-model="fullName"
                        type="text"
                        class="input"
                        placeholder="Dr. Jane Doe"
                        :disabled="loading"
                        @input="clearErrorOnType"
                      />
                    </div>
                  </div>
  
                  <div class="field">
                    <label class="label" for="email">Email</label>
                    <div class="control">
                      <input
                        id="email"
                        v-model="email"
                        type="email"
                        class="input"
                        placeholder="you@example.com"
                        :disabled="loading"
                        @input="clearErrorOnType"
                      />
                    </div>
                  </div>
  
                  <div class="field">
                    <label class="label" for="accountType">Account type</label>
                    <div class="control">
                      <select
                        id="accountType"
                        v-model="accountType"
                        class="input select"
                        :disabled="loading"
                        @change="clearErrorOnType"
                      >
                        <option value="" disabled>Select</option>
                        <option value="employer">Employer / HR</option>
                        <option value="clinician">Clinician / Healthcare professional</option>
                      </select>
                      <svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                      </svg>
                    </div>
                  </div>
  
                  <div class="field">
                    <div class="labelRow">
                      <label class="label" for="password">Password</label>
                      <button
                        class="miniBtn"
                        type="button"
                        :disabled="loading"
                        @click="showPassword = !showPassword"
                      >
                        {{ showPassword ? "Hide" : "Show" }}
                      </button>
                    </div>
  
                    <div class="control">
                      <input
                        id="password"
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        class="input"
                        placeholder="••••••••"
                        :disabled="loading"
                        @input="clearErrorOnType"
                      />
                    </div>
                  </div>
  
                  <!-- full width row -->
                  <div class="field span2">
                    <div class="labelRow">
                      <label class="label" for="passwordConfirm">Confirm password</label>
                      <button
                        class="miniBtn"
                        type="button"
                        :disabled="loading"
                        @click="showConfirm = !showConfirm"
                      >
                        {{ showConfirm ? "Hide" : "Show" }}
                      </button>
                    </div>
  
                    <div class="control">
                      <input
                        id="passwordConfirm"
                        v-model="passwordConfirm"
                        :type="showConfirm ? 'text' : 'password'"
                        class="input"
                        placeholder="••••••••"
                        :disabled="loading"
                        @input="clearErrorOnType"
                      />
                    </div>
                  </div>
                </div>
  
                <div class="terms">
                  <input v-model="agree" type="checkbox" class="check" :disabled="loading" />
                  <div class="termsText">
                    I agree to the <a class="link" href="#">Terms of Service</a> and
                    <a class="link" href="#">Privacy Policy</a>.
                  </div>
                </div>
  
                <button class="btn" type="submit" :disabled="loading">
                  {{ loading ? "Creating..." : "Create account" }}
                </button>
              </form>
  
              <div class="divider" aria-hidden="true">
                <span class="line"></span><span class="or">or</span><span class="line"></span>
              </div>
  
              <div class="bottom">
                <span>Already have an account?</span>
                <RouterLink class="linkStrong" :to="{ name: 'auth.login' }">Log in</RouterLink>
              </div>
            </div>
          </div>
  
          <div class="footer">© {{ year }} AI Clinforce Partners</div>
        </div>
      </div>
    </AppLayout>
  </template>
  
  <style scoped>
  *,
  *::before,
  *::after {
    box-sizing: border-box;
  }
  
  /* Page */
  .page {
    min-height: calc(100vh - 56px);
    display: grid;
    place-items: center;
    padding: 44px 16px;
    color: #111827;
  }
  
  /* Wrap */
  .wrap {
    width: 100%;
    max-width: 560px;
    display: grid;
    gap: 16px;
  }
  
  /* Brand */
  .brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }
  .logo {
    width: 38px;
    height: 38px;
    border-radius: 16px;
    background: #18181b;
    border: 1px solid #e89b0f;
    display: grid;
    place-items: center;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
  }
  .logoIcon {
    width: 20px;
    height: 20px;
    color: #f5b021;
  }
  .brandText {
    line-height: 1.15;
    text-align: left;
  }
  .brandTitle {
    font-weight: 650;
    letter-spacing: -0.01em;
  }
  .brandSub {
    margin-top: 3px;
    font-size: 12px;
    color: #6b7280;
  }
  
  /* Card */
  .card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 8px 30px rgba(17, 24, 39, 0.06);
    padding: 22px;
  }
  @media (min-width: 640px) {
    .card {
      padding: 30px;
    }
  }
  .cardHead {
    text-align: center;
    margin-bottom: 18px;
  }
  .h1 {
    margin: 0;
    font-size: 18px;
    font-weight: 650;
  }
  .p {
    margin: 8px 0 0;
    font-size: 12px;
    color: #4b5563;
  }
  
  /* Inner: even sides */
  .inner {
    width: 100%;
    max-width: 520px;
    margin: 0 auto;
  }
  
  /* Alert */
  .alert {
    border: 1px solid rgba(239, 68, 68, 0.28);
    background: rgba(254, 242, 242, 1);
    border-radius: 14px;
    padding: 10px 12px;
    margin-bottom: 14px;
  }
  .alertTitle {
    font-size: 12px;
    font-weight: 800;
    color: #991b1b;
  }
  .alertMsg {
    margin-top: 2px;
    font-size: 12px;
    color: #7f1d1d;
  }
  
  /* Form */
  .form {
    width: 100%;
    display: grid;
    gap: 14px;
  }
  
  /* Grid */
  .grid {
    display: grid;
    gap: 14px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 640px) {
    .grid {
      grid-template-columns: 1fr 1fr;
    }
  }
  .span2 {
    grid-column: 1 / -1;
  }
  
  .field {
    width: 100%;
    display: grid;
    gap: 8px;
  }
  
  .labelRow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  
  .label {
    font-size: 12px;
    font-weight: 650;
    color: #374151;
  }
  
  /* Control */
  .control {
    position: relative;
    width: 100%;
  }
  
  .input {
    width: 100%;
    display: block;
    border: 1px solid #d1d5db;
    border-radius: 12px;
    background: #fff;
    padding: 12px 12px;
    font-size: 14px;
    outline: none;
    transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
  }
  .input:focus {
    border-color: #e89b0f;
    box-shadow: 0 0 0 4px rgba(255, 231, 191, 0.9);
  }
  .input:disabled {
    background: #f9fafb;
    color: #6b7280;
  }
  
  /* Select */
  .select {
    appearance: none;
    padding-right: 38px;
  }
  .chev {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    color: #6b7280;
    pointer-events: none;
  }
  
  /* Mini button (show/hide) */
  .miniBtn {
    border: none;
    background: transparent;
    padding: 0;
    cursor: pointer;
    font-size: 11px;
    line-height: 1;
    color: #b45309;
    position: relative;
    top: 1px;
  }
  .miniBtn:hover {
    color: #92400e;
  }
  .miniBtn:disabled {
    opacity: 0.65;
    cursor: not-allowed;
  }
  
  /* Terms */
  .terms {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    margin-top: 2px;
  }
  .check {
    margin-top: 3px;
    width: 14px;
    height: 14px;
    accent-color: #b45309;
  }
  .termsText {
    font-size: 11px;
    color: #4b5563;
    line-height: 1.45;
  }
  .link {
    color: #b45309;
    text-decoration: none;
    margin: 0 4px;
  }
  .link:hover {
    color: #92400e;
    text-decoration: underline;
  }
  
  /* Button */
  .btn {
    width: 100%;
    display: block;
    margin-top: 6px;
    border: none;
    border-radius: 12px;
    padding: 12px 12px;
    background: #b45309;
    color: #fff;
    font-size: 14px;
    font-weight: 650;
    cursor: pointer;
    transition: transform 120ms ease, background 120ms ease, opacity 120ms ease;
  }
  .btn:hover {
    background: #92400e;
    transform: translateY(-1px);
  }
  .btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
  }
  
  /* Divider + bottom */
  .divider {
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #6b7280;
    font-size: 11px;
  }
  .line {
    flex: 1;
    height: 1px;
    background: #e5e7eb;
  }
  .or {
    padding: 0 4px;
  }
  
  .bottom {
    margin-top: 12px;
    text-align: center;
    font-size: 11px;
    color: #4b5563;
  }
  .linkStrong {
    margin-left: 6px;
    color: #b45309;
    font-weight: 700;
    text-decoration: none;
  }
  .linkStrong:hover {
    color: #92400e;
    text-decoration: underline;
  }
  
  /* Footer */
  .footer {
    text-align: center;
    font-size: 11px;
    color: #6b7280;
  }
  </style>
  