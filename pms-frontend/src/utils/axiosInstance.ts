import axios from "axios";
import { useLoadingStore } from "@/store/loading";
import { toast } from "vue3-toastify";

const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_APP_BASE_URL,
  timeout: 50000,
  headers: {
    "Content-Type": "application/json",
    "Accept": "application/json",
  },
  withCredentials: true, // Important for Sanctum CSRF cookies
});

// Track if CSRF cookie has been fetched
let csrfCookieFetched = false;

/**
 * Get CSRF cookie from Laravel Sanctum
 * Required before making any POST/PUT/DELETE requests
 */
async function getCsrfCookie(): Promise<void> {
  if (csrfCookieFetched) {
    return;
  }

  try {
    await axios.get(`${import.meta.env.VITE_APP_BASE_URL}/sanctum/csrf-cookie`, {
      withCredentials: true,
    });
    csrfCookieFetched = true;
  } catch (error) {
    console.error('❌ Failed to fetch CSRF cookie:', error);
    throw error;
  }
}

// Request interceptor - add token, get CSRF cookie, and show loading
axiosInstance.interceptors.request.use(
  async (config) => {
    const loadingStore = useLoadingStore();
    loadingStore.toggleLoadingState(true);
    
    // Get CSRF cookie for state-changing requests (POST, PUT, PATCH, DELETE)
    const methodsNeedingCsrf = ['post', 'put', 'patch', 'delete'];
    if (config.method && methodsNeedingCsrf.includes(config.method.toLowerCase())) {
      await getCsrfCookie();
    }
    
    // Add Sanctum token to headers
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    
    return config;
  },
  (error) => {
    const loadingStore = useLoadingStore();
    loadingStore.toggleLoadingState(false);
    return Promise.reject(error);
  }
);

// Response interceptor - hide loading and handle errors
axiosInstance.interceptors.response.use(
  (response) => {
    const loadingStore = useLoadingStore();
    loadingStore.toggleLoadingState(false);
    return response;
  },
  (error) => {
    const loadingStore = useLoadingStore();
    loadingStore.toggleLoadingState(false);
    
    // Handle 401 Unauthorized
    if (error.response?.status === 401) {
      // Don't redirect if it's a login attempt or logout
      const isAuthEndpoint = error.config?.url?.includes('/login') || 
                             error.config?.url?.includes('/logout') ||
                             error.config?.url?.includes('/register');
      
      if (!isAuthEndpoint) {
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
      }
    }
    
    // Handle 403 Forbidden (no permission)
    if (error.response?.status === 403) {
      console.error('Access forbidden:', error.response.data.message);
      toast.error(error.response.data.message || 'Access forbidden');
    }
    
    // Handle 419 CSRF Token Mismatch
    if (error.response?.status === 419) {
      console.error('CSRF token mismatch - refetching cookie');
      csrfCookieFetched = false; // Reset flag to refetch
      toast.error('Session expired. Please try again.');
    }
    
    // Handle 429 Too Many Requests (rate limiting)
    if (error.response?.status === 429) {
      console.error('Too many requests:', error.response.data.message);
      toast.error(error.response.data.message || 'Too many requests. Please slow down.');
    }
    
    return Promise.reject(error);
  }
);

export default axiosInstance;