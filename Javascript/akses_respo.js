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
  const dirName = item.dir_nama || 'Holding'
  const respoName = item.respo_nama || 'Responsibility'

  swal.fire({
    title: 'Ganti Responsibility?',
    html: `
      <div class="text-left text-sm space-y-2">
        <p>Anda akan beralih ke peran kerja:</p>
        <div class="bg-blue-50 p-3 rounded-lg border border-blue-200 text-xs">
          <div><b>Responsibility:</b> ${respoName}</div>
          <div><b>Company / Direktorat:</b> <span class="text-blue-700 font-bold">${dirName}</span></div>
          <div><b>Divisi:</b> ${item.divisi_nama || 'Semua Divisi'}</div>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          Hak akses menu sidebar dan filter data transaksi akan otomatis menyesuaikan dengan peran yang baru.
        </p>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Gunakan Peran Ini',
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

        // Sinkronkan state store frontend
        if (store.user?.data) {
          store.user.data.m_dir_id = item.m_dir_id
          store.user.data.direktorat = item.dir_nama
          if (item.m_comp_id) {
            store.user.data.m_comp_id = item.m_comp_id
          }
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

