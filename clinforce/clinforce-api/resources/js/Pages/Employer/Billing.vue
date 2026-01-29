<!-- resources/js/Pages/Employer/Billing.vue -->
<script setup>
  import { computed, onBeforeUnmount, onMounted, ref } from "vue";
  import AppLayout from "@/Components/AppLayout.vue";
  import UiCard from "@/Components/UiCard.vue";
  import { http } from "@/lib/http";
  
  const loading = ref(true);
  const plans = ref([]);
  const subscription = ref(null);
  
  const company = ref("AI Clinforce Demo Hospital");
  const email = ref("billing@example.com");
  
  const statusMsg = ref("");
  const errorMsg = ref("");
  
  let stripe = null;
  let card = null;
  
  function safeParse(raw) {
    try {
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  }
  
  const user = ref(safeParse(localStorage.getItem("auth_user")));
  function refreshUser() {
    user.value = safeParse(localStorage.getItem("auth_user"));
  }
  function onAuthChanged() {
    refreshUser();
  }
  function onStorage(e) {
    if (e.key === "auth_user") refreshUser();
  }
  
  onMounted(() => {
    refreshUser();
    window.addEventListener("auth:changed", onAuthChanged);
    window.addEventListener("storage", onStorage);
  });
  onBeforeUnmount(() => {
    window.removeEventListener("auth:changed", onAuthChanged);
    window.removeEventListener("storage", onStorage);
  });
  
  const roleLabel = computed(() => {
    const r = String(user.value?.role || "employer");
    if (r === "admin") return "Admin";
    if (r === "agency") return "Agency";
    return "Employer";
  });
  
  function ensureStripeKey() {
    return window.STRIPE_PUBLISHABLE_KEY || import.meta.env?.VITE_STRIPE_PUBLISHABLE_KEY || "";
  }
  
  async function setupStripe() {
    // Prevent layout overlap: Stripe mounts once, container owns height
    if (!window.Stripe) return;
  
    const key = ensureStripeKey();
    if (!key) return;
  
    stripe = window.Stripe(key);
    const elements = stripe.elements();
    card = elements.create("card", { hidePostalCode: true });
  
    const el = document.querySelector("#card-element");
    if (el) el.innerHTML = "";
    card.mount("#card-element");
  }
  
  async function load() {
    loading.value = true;
    errorMsg.value = "";
    try {
      const pr = await http.get("/plans");
      plans.value = Array.isArray(pr.data?.data) ? pr.data.data : (Array.isArray(pr.data) ? pr.data : []);
  
      const sr = await http.get("/subscriptions");
      const subs = Array.isArray(sr.data?.data) ? sr.data.data : (Array.isArray(sr.data) ? sr.data : []);
      subscription.value = subs?.[0] || null;
    } catch (e) {
      errorMsg.value =
        e?.response?.data?.message ||
        e?.response?.data?.error ||
        e?.message ||
        "Failed to load billing data.";
    } finally {
      loading.value = false;
    }
  }
  
  const currentPlan = computed(() => subscription.value?.plan || null);
  
  function planId(p) {
    return p?.stripe_price_id || p?.price_id || p?.id || null;
  }
  function planName(p) {
    return p?.name || p?.title || "Plan";
  }
  function planDesc(p) {
    return p?.description || "";
  }
  function planPriceLabel(p) {
    return p?.price_label || p?.price || (p?.amount ? String(p.amount) : "—");
  }
  function planInterval(p) {
    return p?.interval || "month";
  }
  function planFeatures(p) {
    return Array.isArray(p?.features) ? p.features : [];
  }
  
  function isCurrent(p) {
    const c = currentPlan.value;
    if (!c) return false;
    const a = String(planId(c) ?? c?.id ?? "");
    const b = String(planId(p) ?? p?.id ?? "");
    return a && b && a === b;
  }
  
  function planTier(p) {
    const n = String(planName(p)).toLowerCase();
    if (n.includes("enterprise")) return "enterprise";
    if (n.includes("pro") || n.includes("business")) return "pro";
    return "starter";
  }
  
  const selectedPriceId = ref(null);
  
  onMounted(async () => {
    await load();
    selectedPriceId.value = planId(currentPlan.value) || planId(plans.value?.[0]) || null;
    await setupStripe();
  });
  
  async function startSubscription() {
    statusMsg.value = "";
    errorMsg.value = "";
  
    if (!stripe || !card) {
      errorMsg.value = "Stripe is not initialized (missing publishable key or Stripe.js).";
      return;
    }
  
    try {
      const priceId = selectedPriceId.value;
      if (!priceId) {
        errorMsg.value = "Missing plan priceId (stripe_price_id).";
        return;
      }
  
      const res = await http.post("/subscriptions", {
        priceId,
        company: company.value,
        email: email.value,
      });
  
      const clientSecret = res.data?.clientSecret || res.data?.data?.clientSecret || null;
      if (!clientSecret) {
        errorMsg.value = "Missing clientSecret from /subscriptions response.";
        return;
      }
  
      const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
        payment_method: { card },
      });
  
      if (error) {
        errorMsg.value = error.message || "Payment failed.";
        return;
      }
  
      if (paymentIntent?.status === "succeeded") {
        statusMsg.value = "Payment successful! Subscription is now active.";
        await load();
      }
    } catch (e) {
      errorMsg.value =
        e?.response?.data?.message ||
        e?.response?.data?.error ||
        e?.message ||
        "Unable to start subscription.";
    }
  }
  
  async function cancelSubscription() {
    if (!subscription.value?.id) return;
  
    statusMsg.value = "";
    errorMsg.value = "";
  
    try {
      await http.post(`/subscriptions/${subscription.value.id}/cancel`);
      statusMsg.value = "Subscription canceled.";
      await load();
    } catch (e) {
      errorMsg.value = e?.response?.data?.message || e?.message || "Cancel failed.";
    }
  }
  
  function fmtNextInvoice(val) {
    if (!val) return "—";
    const d = new Date(val);
    if (!Number.isFinite(d.getTime())) return String(val);
    return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "2-digit" });
  }
  
  const activePill = computed(() => (subscription.value?.id ? "Active" : "Not subscribed"));
  </script>
  
  <template>
    <!-- IMPORTANT:
         This page uses AppLayout (global TopBar).
         Removes the per-page sticky topbar/sidebar to prevent overlap/double padding. -->
    <AppLayout>
      <div class="page">
        <!-- Header -->
        <div class="head">
          <div class="titleBlock">
            <div class="eyebrow">{{ roleLabel }} • Billing</div>
            <h1 class="h1">Plans & subscription</h1>
            <p class="sub">Choose a plan, manage billing, and update your card.</p>
          </div>
  
          <div class="headRight">
            <div class="statusPill" :class="{ on: !!subscription?.id }">
              <span class="dot"></span>
              {{ activePill }}
            </div>
  
            <button class="ghostBtn" type="button" :disabled="loading" @click="load">
              {{ loading ? "Refreshing…" : "Refresh" }}
            </button>
          </div>
        </div>
  
        <!-- Alerts -->
        <div v-if="errorMsg" class="alert danger" role="alert">
          <div class="alertTitle">Billing error</div>
          <div class="alertMsg">{{ errorMsg }}</div>
        </div>
        <div v-if="statusMsg" class="alert ok" role="status">
          <div class="alertTitle">Status</div>
          <div class="alertMsg">{{ statusMsg }}</div>
        </div>
  
        <!-- SaaS layout -->
        <div class="saasGrid">
          <!-- Left: Plans -->
          <section class="pricing">
            <UiCard>
              <div class="sectionTop">
                <div>
                  <div class="sectionTitle">Pick a plan</div>
                  <div class="sectionHint">Upgrade or downgrade anytime.</div>
                </div>
              </div>
  
              <div v-if="loading" class="state">Loading plans…</div>
  
              <div v-else class="cards">
                <button
                  v-for="p in plans"
                  :key="planId(p) || p.id"
                  class="planCard"
                  :class="[
                    planTier(p),
                    { selected: String(selectedPriceId) === String(planId(p)), current: isCurrent(p) }
                  ]"
                  type="button"
                  @click="selectedPriceId = planId(p)"
                >
                  <div class="pcTop">
                    <div class="pcLeft">
                      <div class="pcName">{{ planName(p) }}</div>
                      <div class="pcDesc">{{ planDesc(p) || "Best for teams getting started." }}</div>
                    </div>
  
                    <div class="pcRight">
                      <div class="pcPrice">
                        <span class="pcAmount">{{ planPriceLabel(p) }}</span>
                        <span class="pcPer">/ {{ planInterval(p) }}</span>
                      </div>
  
                      <div v-if="isCurrent(p)" class="chip">Current</div>
                      <div v-else-if="planTier(p) === 'pro'" class="chip soft">Popular</div>
                    </div>
                  </div>
  
                  <div class="pcDivider"></div>
  
                  <div class="pcBody">
                    <div v-if="planFeatures(p).length" class="featList">
                      <div v-for="(f, i) in planFeatures(p)" :key="i" class="feat">
                        <span class="tick" aria-hidden="true">✓</span>
                        <span class="fText">{{ f }}</span>
                      </div>
                    </div>
                    <div v-else class="featEmpty">
                      Includes core hiring tools, messaging, and scheduling.
                    </div>
                  </div>
  
                  <div class="pcSelectRow">
                    <div class="radio" :class="{ on: String(selectedPriceId) === String(planId(p)) }" aria-hidden="true"></div>
                    <div class="selectText">
                      {{ String(selectedPriceId) === String(planId(p)) ? "Selected" : "Select" }}
                    </div>
                  </div>
                </button>
              </div>
  
              <div class="note">Your selection will be used for the Stripe payment on the right.</div>
            </UiCard>
          </section>
  
          <!-- Right: Checkout -->
          <aside class="checkout">
            <UiCard>
              <div class="sumTop">
                <div>
                  <div class="sumTitle">Checkout</div>
                  <div class="sumHint">Secure card payment via Stripe.</div>
                </div>
  
                <div v-if="selectedPriceId" class="miniBadge">Selected</div>
              </div>
  
              <div class="sumLine">
                <div class="k">Company</div>
                <div class="v">{{ company }}</div>
              </div>
              <div class="sumLine">
                <div class="k">Email</div>
                <div class="v">{{ email }}</div>
              </div>
              <div class="sumLine">
                <div class="k">Next invoice</div>
                <div class="v">{{ fmtNextInvoice(subscription?.next_invoice_at) }}</div>
              </div>
  
              <div class="sumDivider"></div>
  
              <div class="form">
                <div class="field">
                  <label class="label">Company name</label>
                  <input v-model="company" class="input" />
                </div>
  
                <div class="field">
                  <label class="label">Billing email</label>
                  <input v-model="email" type="email" class="input" />
                </div>
  
                <div class="field">
                  <label class="label">Card details</label>
                  <!-- Fixed: reserve height + prevent overlap -->
                  <div id="card-element" class="cardEl"></div>
                  <div class="help">Card info is handled by Stripe.</div>
                </div>
  
                <button class="primaryBtn" type="button" :disabled="loading || !selectedPriceId" @click="startSubscription">
                  Start subscription
                </button>
  
                <button v-if="subscription?.id" class="dangerBtn" type="button" @click="cancelSubscription">
                  Cancel subscription
                </button>
  
                <div class="tos">By subscribing, you agree to your Terms and Privacy Policy.</div>
              </div>
            </UiCard>
  
            <UiCard>
              <div class="supportTitle">Need help?</div>
              <div class="supportText">
                Add invoice history and payment methods once your backend exposes endpoints.
              </div>
            </UiCard>
          </aside>
        </div>
      </div>
    </AppLayout>
  </template>
  
  <style scoped>
  /* This file no longer defines its own topbar/sidebar.
     AppLayout owns the fixed TopBar + spacing. */
  
  .page{
    max-width: 1152px;
    margin: 0 auto;
    padding: 8px 0;
    display:grid;
    gap: 12px;
  }
  
  /* Header */
  .head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .titleBlock{ min-width:0; }
  .eyebrow{
    font-size: 11px;
    font-weight: 950;
    letter-spacing: .10em;
    text-transform: uppercase;
    color:#6b7280;
  }
  .h1{
    margin: 6px 0 0;
    font-size: 22px;
    font-weight: 950;
    letter-spacing: -0.02em;
    color:#111827;
  }
  .sub{ margin: 6px 0 0; font-size: 12px; color:#6b7280; }
  
  .headRight{ display:flex; gap: 10px; align-items:center; flex-wrap:wrap; }
  
  /* Buttons */
  .ghostBtn{
    height: 40px; padding: 0 14px; border-radius: 999px;
    border: 1px solid #e5e7eb; background:#fff; color:#111827;
    font-size: 12px; font-weight: 950; cursor:pointer;
  }
  .ghostBtn:hover{ background:#f9fafb; }
  .ghostBtn:disabled{ opacity:.65; cursor:not-allowed; }
  
  .statusPill{
    display:inline-flex; align-items:center; gap: 8px;
    font-size: 12px; font-weight: 950;
    border-radius: 999px; padding: 8px 12px;
    border: 1px solid rgba(17,24,39,.10);
    background: rgba(17,24,39,.02);
    color:#111827;
  }
  .statusPill .dot{ width: 8px; height: 8px; border-radius: 999px; background:#9ca3af; }
  .statusPill.on{
    border-color: rgba(16,185,129,.25);
    background: rgba(236,253,245,1);
    color:#065f46;
  }
  .statusPill.on .dot{ background:#10b981; }
  
  /* Alerts */
  .alert{
    border-radius: 16px; padding: 12px;
    border: 1px solid rgba(17,24,39,.08);
    background:#fff; display:grid; gap: 4px;
  }
  .alertTitle{ font-size: 12px; font-weight: 950; }
  .alertMsg{ font-size: 12px; color:#111827; word-break: break-word; }
  .alert.danger{ border-color: rgba(239,68,68,.25); background: rgba(254,242,242,1); }
  .alert.danger .alertTitle{ color:#991b1b; }
  .alert.danger .alertMsg{ color:#7f1d1d; }
  .alert.ok{ border-color: rgba(16,185,129,.25); background: rgba(236,253,245,1); }
  .alert.ok .alertTitle{ color:#065f46; }
  .alert.ok .alertMsg{ color:#064e3b; }
  
  /* SaaS grid */
  .saasGrid{
    display:grid;
    gap: 14px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 980px){
    .saasGrid{ grid-template-columns: 1.2fr .8fr; align-items:start; }
  }
  .pricing{ min-width:0; }
  .checkout{ min-width:0; display:grid; gap: 12px; }
  
  .sectionTop{ display:flex; align-items:flex-start; justify-content:space-between; gap: 10px; margin-bottom: 10px; }
  .sectionTitle{ font-size: 14px; font-weight: 950; color:#111827; }
  .sectionHint{ margin-top: 4px; font-size: 12px; color:#6b7280; }
  .state{ padding: 8px 0; font-size: 12px; color:#6b7280; }
  
  /* Plans grid */
  .cards{
    display:grid;
    gap: 12px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 760px){
    .cards{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }
  
  .planCard{
    text-align:left;
    border: 1px solid rgba(17,24,39,.10);
    background:#fff;
    border-radius: 18px;
    padding: 14px;
    cursor:pointer;
    transition: transform 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
    display:grid;
    gap: 12px;
    position: relative;
    overflow:hidden;
  }
  .planCard:hover{
    transform: translateY(-1px);
    border-color: rgba(17,24,39,.18);
    box-shadow: 0 14px 26px rgba(0,0,0,.06);
  }
  .planCard.selected{
    border-color: rgba(17,24,39,.32);
    box-shadow: 0 16px 30px rgba(0,0,0,.08);
  }
  .planCard.current{
    outline: 2px solid rgba(16,185,129,.35);
    outline-offset: 2px;
  }
  .planCard.pro::before,
  .planCard.enterprise::before{
    content:"";
    position:absolute;
    inset: -2px -2px auto -2px;
    height: 120px;
    background: radial-gradient(closest-side, rgba(17,24,39,.12), rgba(17,24,39,0));
    pointer-events:none;
  }
  
  .pcTop{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
  }
  .pcLeft{ min-width:0; }
  .pcName{ font-size: 14px; font-weight: 950; color:#111827; }
  .pcDesc{
    margin-top: 4px;
    font-size: 12px;
    color:#6b7280;
    line-height: 1.4;
    display:-webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow:hidden;
  }
  .pcRight{ text-align:right; display:grid; gap: 6px; align-items:start; }
  .pcPrice{ display:flex; align-items:baseline; justify-content:flex-end; gap: 6px; white-space:nowrap; }
  .pcAmount{ font-size: 16px; font-weight: 950; color:#111827; }
  .pcPer{ font-size: 11px; font-weight: 900; color:#6b7280; }
  
  .chip{
    justify-self:end;
    font-size: 11px;
    font-weight: 950;
    border-radius: 999px;
    padding: 6px 10px;
    border: 1px solid rgba(16,185,129,.25);
    background: rgba(236,253,245,1);
    color:#065f46;
  }
  .chip.soft{
    border-color: rgba(17,24,39,.12);
    background: rgba(17,24,39,.03);
    color:#111827;
  }
  
  .pcDivider{ height: 1px; background: rgba(17,24,39,.08); }
  
  .pcBody{ min-height: 92px; }
  .featList{ display:grid; gap: 8px; }
  .feat{ display:flex; gap: 8px; align-items:flex-start; }
  .tick{
    width: 18px; height: 18px; border-radius: 999px;
    display:grid; place-items:center;
    font-size: 12px; font-weight: 950;
    background: rgba(17,24,39,.06);
    color:#111827;
    flex: 0 0 auto;
  }
  .fText{ font-size: 12px; color:#111827; line-height: 1.4; }
  .featEmpty{ font-size: 12px; color:#6b7280; line-height: 1.5; }
  
  .pcSelectRow{ display:flex; align-items:center; justify-content:space-between; gap: 10px; }
  .radio{
    width: 18px; height: 18px; border-radius: 999px;
    border: 2px solid rgba(17,24,39,.18);
    position: relative;
  }
  .radio.on{ border-color: rgba(17,24,39,.6); }
  .radio.on::after{
    content:"";
    position:absolute;
    inset: 4px;
    border-radius: 999px;
    background:#111827;
  }
  .selectText{ font-size: 12px; font-weight: 950; color:#111827; }
  
  .note{ margin-top: 10px; font-size: 12px; color:#6b7280; }
  
  /* Checkout */
  .sumTop{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 10px;
    margin-bottom: 10px;
  }
  .sumTitle{ font-size: 14px; font-weight: 950; color:#111827; }
  .sumHint{ margin-top: 4px; font-size: 12px; color:#6b7280; }
  
  .miniBadge{
    font-size: 11px;
    font-weight: 950;
    border-radius: 999px;
    padding: 6px 10px;
    border: 1px solid rgba(17,24,39,.10);
    background: rgba(17,24,39,.02);
    color:#111827;
    white-space: nowrap;
  }
  
  .sumLine{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 6px 0;
  }
  .sumLine .k{
    font-size: 11px;
    font-weight: 950;
    color:#6b7280;
    text-transform: uppercase;
    letter-spacing: .08em;
  }
  .sumLine .v{
    font-size: 12px;
    font-weight: 950;
    color:#111827;
    white-space: nowrap;
    overflow:hidden;
    text-overflow: ellipsis;
    max-width: 260px;
    text-align:right;
  }
  
  .sumDivider{ height: 1px; background: rgba(17,24,39,.08); margin: 8px 0; }
  
  .form{ display:grid; gap: 12px; }
  .field{ display:grid; gap: 6px; }
  .label{ font-size: 11px; color:#6b7280; font-weight: 950; }
  .input{
    width: 100%;
    height: 42px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    padding: 0 12px;
    font-size: 13px;
    color:#111827;
    outline:none;
    background:#fff;
    transition: box-shadow 120ms ease, border-color 120ms ease;
  }
  .input:focus{ border-color:#d1d5db; box-shadow: 0 0 0 4px rgba(17,24,39,.08); }
  
  /* FIX: prevent Stripe element from collapsing/overlapping */
  .cardEl{
    border-radius: 16px;
    border: 1px solid rgba(17,24,39,.10);
    padding: 12px;
    background:#fff;
    min-height: 52px;
    display:flex;
    align-items:center;
  }
  .help{ font-size: 11px; color:#6b7280; font-weight: 900; }
  
  .primaryBtn{
    height: 44px;
    border-radius: 999px;
    border: 1px solid rgba(17,24,39,.16);
    background:#111827;
    color:#fff;
    font-size: 12px;
    font-weight: 950;
    cursor:pointer;
  }
  .primaryBtn:hover{ background:#0b1220; }
  .primaryBtn:disabled{ opacity:.65; cursor:not-allowed; }
  
  .dangerBtn{
    height: 44px;
    border-radius: 999px;
    border: 1px solid rgba(239,68,68,.25);
    background:#fff;
    color:#991b1b;
    font-size: 12px;
    font-weight: 950;
    cursor:pointer;
  }
  .dangerBtn:hover{ background: rgba(254,226,226,.65); }
  
  .tos{
    font-size: 11px;
    color:#6b7280;
    font-weight: 900;
    text-align:center;
    margin-top: 4px;
  }
  
  .supportTitle{ font-size: 13px; font-weight: 950; color:#111827; }
  .supportText{ margin-top: 6px; font-size: 12px; color:#6b7280; line-height: 1.5; }
  </style>