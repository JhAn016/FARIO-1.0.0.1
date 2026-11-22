import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../services/api'

export const useAuthStore = defineStore('auth', () => {
    const user  = ref(JSON.parse(sessionStorage.getItem('fario_user') || 'null'))
    const error = ref('')

    async function login(usuario, password) {
        error.value = ''
        try {
            const res = await api.login(usuario, password)
            user.value = res.data.data
            sessionStorage.setItem('fario_user', JSON.stringify(user.value))
            return true
        } catch(e) {
            error.value = e.response?.data?.msg || 'Error de conexión'
            return false
        }
    }

    function logout() {
        user.value = null
        sessionStorage.removeItem('fario_user')
    }

    function tienePermiso(modulo) {
        return user.value?.perms?.includes(modulo) ?? false
    }

    return { user, error, login, logout, tienePermiso }
})