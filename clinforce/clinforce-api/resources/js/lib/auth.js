// resources/js/lib/auth.js
import api, { setToken, getToken } from "./api";

export function isAuthed() {
  return Boolean(getToken());
}

export async function login({ identifier, password, remember = false }) {
  // IMPORTANT: path is "/auth/login" relative to api.baseURL
  // If baseURL = "/api" or "http://.../api" => final is "/api/auth/login"
  const res = await api.post("/auth/login", { identifier, password, remember });

  const token = res?.data?.data?.token;
  const user = res?.data?.data?.user;

  if (token) setToken(token);
  if (user) localStorage.setItem("auth_user", JSON.stringify(user));

  // notify layouts/components listening for auth changes
  window.dispatchEvent(new Event("auth:changed"));

  return { token, user };
}

export async function register({ role, email, phone = null, password }) {
  const res = await api.post("/auth/register", { role, email, phone, password });

  const token = res?.data?.data?.token;
  const user = res?.data?.data?.user;

  if (token) setToken(token);
  if (user) localStorage.setItem("auth_user", JSON.stringify(user));

  window.dispatchEvent(new Event("auth:changed"));

  return { token, user };
}

export async function me() {
  const res = await api.get("/auth/me");
  const user = res?.data?.data?.user;

  if (user) localStorage.setItem("auth_user", JSON.stringify(user));

  return user;
}

export async function logout() {
  try {
    await api.post("/auth/logout");
  } finally {
    setToken(null);
    localStorage.removeItem("auth_user");
    window.dispatchEvent(new Event("auth:changed"));
  }
}

export function getCachedUser() {
  const raw = localStorage.getItem("auth_user");
  return raw ? JSON.parse(raw) : null;
}
