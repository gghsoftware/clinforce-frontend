// resources/js/lib/api.js
import axios from "axios";

const api = axios.create({
  baseURL: "",
  timeout: 20000,
});

const TOKEN_KEYS = ["auth_token", "CLINFORCE_TOKEN"];

export function setToken(token) {
  if (token) {
    localStorage.setItem("auth_token", token);
    localStorage.setItem("CLINFORCE_TOKEN", token);
  } else {
    localStorage.removeItem("auth_token");
    localStorage.removeItem("CLINFORCE_TOKEN");
  }
}

export function getToken() {
  for (const k of TOKEN_KEYS) {
    const v = localStorage.getItem(k);
    if (v) return v;
  }
  return null;
}

function normalizeApiUrl(url) {
  const u = String(url || "");
  if (/^https?:\/\//i.test(u)) return u;

  const path = u.startsWith("/") ? u : `/${u}`;
  if (path === "/api" || path.startsWith("/api/")) return path;
  return `/api${path}`;
}

api.interceptors.request.use((config) => {
  config.url = normalizeApiUrl(config.url);

  // Impersonation header (optional, local dev)
  const asUserId = localStorage.getItem("CLINFORCE_AS_USER_ID");
  if (asUserId) config.headers["X-User-Id"] = asUserId;

  // Bearer token (Sanctum)
  const token = getToken();
  if (token) config.headers.Authorization = `Bearer ${token}`;
  else delete config.headers.Authorization;

  // ✅ IMPORTANT:
  // If sending FormData, DO NOT set Content-Type manually.
  // Axios will set multipart/form-data with the correct boundary.
  const isFormData = typeof FormData !== "undefined" && config.data instanceof FormData;
  if (isFormData) {
    delete config.headers["Content-Type"];
    delete config.headers["content-type"];
  } else {
    config.headers["Content-Type"] = "application/json";
  }

  return config;
});

api.interceptors.response.use(
  (res) => res,
  (err) => {
    err.__payload = err?.response?.data || null;
    throw err;
  }
);

export default api;
 