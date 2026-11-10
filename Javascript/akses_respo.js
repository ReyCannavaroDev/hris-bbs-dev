//   javascript

import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isLoading = ref(true)
const isSwitching = ref(false)
const respoList = ref([])
const activeRespo = ref(null)

onBeforeMount(() => {
  document.title = 'Change Akses Responsibility'
})

const fetchMyRespo = async () => {
  isLoading.value = true
  try {
    const dataURL = `${store.server.url_backend}/operation/default_users_respo/getMyRespo`
    const res = await fetch(dataURL, {
      method: 'GET',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      }
    })
    if (!res.ok) {
      throw new Error("Gagal mengambil data responsibility akun Anda")
    }
    const resultJson = await res.json()
    respoList.value = resultJson.list || []
    activeRespo.value = resultJson.active_respo || null
  } catch (err) {
    swal.fire({
      icon: 'error',
      title: 'Kesalahan Sistem',
      text: err.message || 'Gagal memuat daftar responsibility.'
    })
  } finally {
    isLoading.value = false
  }
}

const onSwitch = (item) => {
  const dirName = item.dir_nama || 'Holding (Semua Company)'
  const respoName = item.respo_nama || 'Responsibility'

  swal.fire({
    icon: 'question',
    title: 'Ganti Responsibility?',
    text: `Beralih ke ${respoName} (${dirName})?`,
    showCancelButton: true,
    confirmButtonText: 'Ya, Ganti',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#2563EB',
    cancelButtonColor: '#6B7280'
  }).then(async (result) => {
    if (result.isConfirmed) {
      isSwitching.value = true
      try {
        const dataURL = `${store.server.url_backend}/operation/default_users_respo/switchRespo`
        const res = await fetch(dataURL, {
          method: 'POST',
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
          body: JSON.stringify({
            m_respo_id: item.m_respo_id,
            user_respo_id: item.user_respo_id
          })
        })
        const resJson = await res.json().catch(() => null)
        if (!res.ok) {
          throw new Error(resJson?.error || resJson?.message || 'Gagal beralih responsibility')
        }

        // Sinkronkan state store frontend & localStorage
        const nextDirId = resJson?.active_respo ? (resJson.active_respo.m_dir_id ?? null) : (item.m_dir_id ?? null)
        const nextCompId = resJson?.active_respo?.m_comp_id || item.m_comp_id
        const nextDirName = resJson?.direktorat || dirName

        if (store.user?.data) {
          store.user.data.m_dir_id = nextDirId
          store.user.data.direktorat = nextDirName
          if (nextCompId) {
            store.user.data.m_comp_id = nextCompId
          }
          if (resJson) {
            if (typeof resJson.is_superadmin !== 'undefined') store.user.data.is_superadmin = resJson.is_superadmin
            if (typeof resJson.can_view_salary !== 'undefined') store.user.data.can_view_salary = resJson.can_view_salary
            if (Array.isArray(resJson.roles)) store.user.data.roles = resJson.roles
            if (typeof resJson.role_names !== 'undefined') store.user.data.role_names = resJson.role_names
            if (resJson.active_respo) store.user.data.active_respo = resJson.active_respo
          }
        }
        if (typeof window !== 'undefined' && window.localStorage?.user) {
          try {
            const localUser = JSON.parse(window.localStorage.user)
            if (localUser?.data) {
              localUser.data.m_dir_id = nextDirId
              localUser.data.direktorat = nextDirName
              if (nextCompId) {
                localUser.data.m_comp_id = nextCompId
              }
              if (resJson) {
                if (typeof resJson.is_superadmin !== 'undefined') localUser.data.is_superadmin = resJson.is_superadmin
                if (typeof resJson.can_view_salary !== 'undefined') localUser.data.can_view_salary = resJson.can_view_salary
                if (Array.isArray(resJson.roles)) localUser.data.roles = resJson.roles
                if (typeof resJson.role_names !== 'undefined') localUser.data.role_names = resJson.role_names
                if (resJson.active_respo) localUser.data.active_respo = resJson.active_respo
              }
              window.localStorage.user = JSON.stringify(localUser)
            }
          } catch (e) {}
        }

        swal.fire({
          icon: 'success',
          title: 'Berhasil Beralih',
          text: `Sekarang Anda aktif sebagai ${respoName} (${dirName}).`,
          timer: 1600,
          showConfirmButton: false
        }).then(() => {
          // Refresh total aplikasi agar seluruh menu sidebar dan cache terupdate seketika
          window.location.href = '/dashboard?ts=' + Date.now()
        })
      } catch (err) {
        swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: err.message || 'Terjadi kesalahan saat mengganti responsibility'
        })
      } finally {
        isSwitching.value = false
      }
    }
  })
}

onMounted(() => {
  fetchMyRespo()
})

