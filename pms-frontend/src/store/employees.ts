import { defineStore } from 'pinia'
import hrisAxiosInstance from "@/utils/hrisAxiosInstance";
import type { Employee } from '@/types/employee'



export const useEmployeeStore = defineStore('employees', {
  state: () => ({
    employees: [] as Employee[],
    currentEmployee: null as Employee | null,
    loading: false,
    error: null as string | null,
    employmentTypeCounts: [] as Array<{ name: string; count: number }>,
    totalEmployees: 0,
  }),

  getters: {
    /**
     * Get employee by ID
     */
    getEmployeeById: (state) => (id: number) => {
      return state.employees.find(emp => emp.ID === id)
    },

    /**
     * Get employees by division
     */
    getEmployeesByDivision: (state) => (divisionId: number) => {
      return state.employees.filter(emp => emp.division_id === divisionId)
    },

    /**
     * Get employees formatted for dropdown (id, name)
     */
    employeesForDropdown: (state) => {
      return state.employees
        .map(emp => ({
          id: emp.ID,
          name: `${emp.first_name} ${emp.last_name}`,
          email: emp.email,
          position: emp.position,
          emp_code: emp.display_emp_code,
          first_name: emp.first_name,
          last_name: emp.last_name,
          middle_name: emp.middle_name,
          suffix: emp.suffix
        }))
        .sort((a, b) => {
          const surnameA = (a.last_name || '').toLowerCase();
          const surnameB = (b.last_name || '').toLowerCase();
          return surnameA.localeCompare(surnameB);
        });
    },

    /**
     * Get active employees only
     */
    activeEmployees: (state) => {
      return state.employees.filter(emp => emp.status === 'active' || emp.status === 'Active')
    },

    /**
     * Get employees count
     */
    employeesCount: (state) => state.employees.length,
  },

  actions: {
    /**
     * Fetch all employees from HRIS
     */
    async fetchEmployees() {
      this.loading = true
      this.error = null
      try {
        const response = await hrisAxiosInstance.get('/api/v1/ims/employees')
        
        this.employees = response.data.data || []
        this.employmentTypeCounts = response.data.employment_type_counts || []
        this.totalEmployees = response.data.total_employees || 0
        
        return this.employees
      } catch (error: any) {
        this.error = error.response?.data?.error || 'Failed to fetch employees from HRIS'
        console.error('Error fetching employees from HRIS:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Fetch a single employee by ID from HRIS
     */
    async fetchEmployee(id: number) {
      this.loading = true
      this.error = null
      try {
        const response = await hrisAxiosInstance.get(`/api/v1/ims/employees/${id}`)
        
        this.currentEmployee = response.data.data
        
        // Also update in the employees array if exists
        const index = this.employees.findIndex(emp => emp.ID === id)
        if (index !== -1) {
          this.employees[index] = response.data.data
        } else {
          this.employees.push(response.data.data)
        }
        
        return this.currentEmployee
      } catch (error: any) {
        this.error = error.response?.data?.error || 'Failed to fetch employee from HRIS'
        console.error('Error fetching employee from HRIS:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    /**
     * Search employees by name or email
     */
    searchEmployees(query: string) {
      if (!query) return this.employees
      
      const lowerQuery = query.toLowerCase()
      return this.employees.filter(emp => 
        emp.first_name.toLowerCase().includes(lowerQuery) ||
        emp.last_name.toLowerCase().includes(lowerQuery) ||
        emp.email.toLowerCase().includes(lowerQuery) ||
        emp.emp_code.toLowerCase().includes(lowerQuery)
      )
    },

    /**
     * Clear error state
     */
    clearError() {
      this.error = null
    },

    /**
     * Clear current employee
     */
    clearCurrentEmployee() {
      this.currentEmployee = null
    },

    /**
     * Reset store to initial state
     */
    $reset() {
      this.employees = []
      this.currentEmployee = null
      this.loading = false
      this.error = null
      this.employmentTypeCounts = []
      this.totalEmployees = 0
    }
  }
})