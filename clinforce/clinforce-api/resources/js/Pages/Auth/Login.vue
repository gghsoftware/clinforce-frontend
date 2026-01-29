<!-- resources/js/Pages/Auth/Login.vue -->
<script setup>
  import { ref } from "vue";
  import { useRouter, useRoute, RouterLink } from "vue-router";
  import axios from "axios";
  import AppLayout from "@/Components/AppLayout.vue";
  
  const router = useRouter();
  const route = useRoute();
  
  const identifier = ref("");
  const password = ref("");
  const remember = ref(false);
  
  const loading = ref(false);
  const error = ref("");
  
  const showPassword = ref(false);
  const year = new Date().getFullYear();
  
  function isApplicantRole(role) {
    return String(role || "").toLowerCase() === "applicant";
  }
  
  function isStaffRole(role) {
    const r = String(role || "").toLowerCase();
    return r === "admin" || r === "employer" || r === "agency";
  }
  
  function dispatchAuthChanged() {
    window.dispatchEvent(new Event("auth:changed"));
  }
  
  function clearErrorOnType() {
    if (error.value) error.value = "";
  }
  
  async function onSubmit() {
    loading.value = true;
    error.value = "";
  
    try {
      const res = await axios.post("/api/auth/login", {
        identifier: identifier.value.trim(),
        password: password.value,
        remember: remember.value,
      });
  
      const token = res?.data?.data?.token || null;
      const user = res?.data?.data?.user || null;
  
      // IMPORTANT: keep both keys in sync (api.js reads both)
      if (token) {
        localStorage.setItem("auth_token", token);
        localStorage.setItem("CLINFORCE_TOKEN", token);
      } else {
        localStorage.removeItem("auth_token");
        localStorage.removeItem("CLINFORCE_TOKEN");
      }
  
      if (user) localStorage.setItem("auth_user", JSON.stringify(user));
      else localStorage.removeItem("auth_user");
  
      dispatchAuthChanged();
  
      const redirect = route.query.redirect ? String(route.query.redirect) : null;
      if (redirect) return router.push(redirect);
  
      if (isApplicantRole(user?.role)) return router.push({ name: "candidate.dashboard" });
      if (isStaffRole(user?.role)) return router.push({ name: "employer.dashboard" });
  
      return router.push({ name: "candidate.dashboard" });
    } catch (e) {
      error.value = e?.response?.data?.message || "Login failed.";
    } finally {
      loading.value = false;
    }
  }
  
  function goForgot() {
    // router.push({ name: "auth.forgot" })
  }
  </script>
  
  <template>
    <AppLayout>
      <div class="loginPage">
        <div class="loginWrap">
          <!-- Brand -->
          <div class="loginBrand">
            <div class="loginLogo" aria-hidden="true">
              <svg class="loginLogoIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="4" y="4" width="16" height="16" rx="4" />
                <path d="M12 8v8M8 12h8" stroke-linecap="round" />
              </svg>
            </div>
  
            <div class="loginBrandText">
              <div class="loginBrandTitle">AI Clinforce Partners</div>
              <div class="loginBrandSub">Clinical workforce network</div>
            </div>
          </div>
  
          <!-- Card -->
          <div class="loginCard">
            <h1 class="loginH1">Log in</h1>
            <p class="loginP">Access your employer or clinician dashboard.</p>
  
            <div class="loginInner">
              <form class="loginForm" @submit.prevent="onSubmit">
                <div v-if="error" class="loginErrorBox" role="alert" aria-live="polite">
                  <div class="loginErrorTitle">Login failed</div>
                  <div class="loginErrorMsg">{{ error }}</div>
                </div>
  
                <div class="loginField">
                  <label class="loginLabel" for="identifier">Email or Phone</label>
                  <input
                    id="identifier"
                    v-model="identifier"
                    class="loginInput"
                    type="text"
                    placeholder="you@hospital.org / +639..."
                    autocomplete="username"
                    :disabled="loading"
                    required
                    @input="clearErrorOnType"
                  />
                </div>
  
                <div class="loginField">
                  <div class="loginLabelRow">
                    <label class="loginLabel" for="password">Password</label>
  
                    <div class="loginActions">
                      <button class="loginMiniBtn" type="button" :disabled="loading" @click="showPassword = !showPassword">
                        {{ showPassword ? "Hide" : "Show" }}
                      </button>
                      <button class="loginForgot" type="button" :disabled="loading" @click="goForgot">
                        Forgot?
                      </button>
                    </div>
                  </div>
  
                  <input
                    id="password"
                    v-model="password"
                    class="loginInput"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    :disabled="loading"
                    required
                    @input="clearErrorOnType"
                  />
                </div>
  
                <div class="loginRememberRow">
                  <label class="loginRemember">
                    <input v-model="remember" class="loginCheckbox" type="checkbox" :disabled="loading" />
                    <span>Keep me logged in</span>
                  </label>
                </div>
  
                <button class="loginBtn" type="submit" :disabled="loading">
                  <span v-if="!loading">Log in</span>
                  <span v-else>Logging in...</span>
                </button>
              </form>
  
              <div class="loginDivider" aria-hidden="true">
                <span class="loginLine"></span>
                <span class="loginOr">or</span>
                <span class="loginLine"></span>
              </div>
  
              <p class="loginBottom">
                New to AI Clinforce Partners?
                <RouterLink class="loginLink" :to="{ name: 'auth.register' }">Create an account</RouterLink>
              </p>
            </div>
          </div>
  
          <p class="loginFooter">© {{ year }} AI Clinforce Partners</p>
        </div>
      </div>
    </AppLayout>
  </template>
  
  <style scoped>
  *, *::before, *::after { box-sizing: border-box; }
  
  /* Page shell */
  .loginPage{
    min-height: calc(100vh - 56px);
    display: grid;
    place-items: center;
    padding: 40px 16px;
    color: #111827;
  }
  
  /* Stack container */
  .loginWrap{
    width: 100%;
    max-width: 460px;
    display: grid;
    gap: 16px;
  }
  
  /* Brand */
  .loginBrand{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }
  .loginLogo{
    width: 36px;
    height: 36px;
    border-radius: 16px;
    background: #18181b;
    border: 1px solid #e89b0f;
    display: grid;
    place-items: center;
  }
  .loginLogoIcon{
    width: 20px;
    height: 20px;
    color: #f5b021;
  }
  .loginBrandText{
    line-height: 1.15;
    text-align: left;
  }
  .loginBrandTitle{
    font-weight: 600;
    letter-spacing: -0.01em;
  }
  .loginBrandSub{
    margin-top: 3px;
    font-size: 12px;
    color: #6b7280;
  }
  
  /* Card */
  .loginCard{
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.06);
    padding: 26px;
  }
  @media (min-width: 640px){
    .loginCard{ padding: 32px; }
  }
  
  .loginH1{
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    text-align: center;
  }
  .loginP{
    margin: 8px 0 22px;
    font-size: 12px;
    color: #4b5563;
    text-align: center;
  }
  
  /* Even width wrapper */
  .loginInner{
    width: 100%;
    max-width: 380px;
    margin: 0 auto;
  }
  
  /* Error */
  .loginErrorBox{
    border: 1px solid rgba(239,68,68,0.25);
    background: rgba(254,242,242,1);
    border-radius: 12px;
    padding: 10px 12px;
    margin-bottom: 2px;
  }
  .loginErrorTitle{
    font-size: 12px;
    font-weight: 700;
    color: #991b1b;
  }
  .loginErrorMsg{
    margin-top: 2px;
    font-size: 12px;
    color: #7f1d1d;
  }
  
  /* Form */
  .loginForm{
    width: 100%;
    display: grid;
    gap: 18px;
  }
  .loginField{
    width: 100%;
    display: grid;
    gap: 8px;
  }
  .loginLabelRow{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .loginLabel{
    font-size: 12px;
    font-weight: 600;
    color: #374151;
  }
  
  .loginActions{
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }
  
  .loginMiniBtn,
  .loginForgot{
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
  .loginMiniBtn:hover,
  .loginForgot:hover{ color: #92400e; }
  .loginMiniBtn:disabled,
  .loginForgot:disabled{ opacity: 0.65; cursor: not-allowed; }
  
  .loginInput{
    width: 100%;
    display: block;
    border: 1px solid #d1d5db;
    background: #ffffff;
    border-radius: 12px;
    padding: 12px 12px;
    font-size: 14px;
    outline: none;
    transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
  }
  .loginInput:focus{
    border-color: #e89b0f;
    box-shadow: 0 0 0 4px rgba(255,231,191,0.9);
  }
  .loginInput:disabled{
    background: #f9fafb;
    color: #6b7280;
  }
  
  /* Remember */
  .loginRememberRow{
    margin-top: 2px;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .loginRemember{
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: #374151;
  }
  .loginCheckbox{
    width: 14px;
    height: 14px;
    border-radius: 6px;
    accent-color: #b45309;
  }
  
  /* Button */
  .loginBtn{
    width: 100%;
    display: block;
    margin-top: 6px;
    border: none;
    border-radius: 12px;
    padding: 12px 12px;
    background: #b45309;
    color: #ffffff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 120ms ease, background 120ms ease, opacity 120ms ease;
  }
  .loginBtn:hover{
    background: #92400e;
    transform: translateY(-1px);
  }
  .loginBtn:disabled{
    opacity: 0.75;
    cursor: not-allowed;
    transform: none;
  }
  
  /* Divider + bottom aligned */
  .loginDivider{
    width: 100%;
    margin-top: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: #6b7280;
  }
  .loginLine{
    flex: 1;
    height: 1px;
    background: #e5e7eb;
  }
  .loginOr{
    padding: 0 4px;
  }
  
  .loginBottom{
    width: 100%;
    margin-top: 14px;
    font-size: 11px;
    color: #4b5563;
    text-align: center;
  }
  .loginLink{
    margin-left: 6px;
    color: #b45309;
    font-weight: 600;
    text-decoration: none;
  }
  .loginLink:hover{
    color: #92400e;
    text-decoration: underline;
  }
  
  /* Footer */
  .loginFooter{
    text-align: center;
    font-size: 11px;
    color: #6b7280;
  }
  </style>
  