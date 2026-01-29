// resources/js/lib/http.js
import api from "./api";
export const http = api;
export const get = (...args) => api.get(...args);
export const post = (...args) => api.post(...args);
export const put = (...args) => api.put(...args);
export const del = (...args) => api.delete(...args);
export default api;
