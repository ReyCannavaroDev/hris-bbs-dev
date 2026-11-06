//   javascript

import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isRead = route.params.id && route.params.id !== 'create'
const actionText = ref(route.params.id === 'create' ? 'Tambah' : route.query.action)
const isBadForm = ref(false)
const isRequesting = ref(false)
const modulPath = route.params.modul
const currentMenu = store.currentMenu
const apiTable = ref(null)
const formErrors = ref({})
const tsId = `ts=`+(Date.parse(new Date()))
const activeBtn = ref(1)

// ------------------------------ PERSIAPAN
const endpointApi = '/m_respo'

onBeforeMount(()=>{
  document.title = 'Master Responsibility'
})

const filterShowData = (val, id) => {
  activeBtn.value = id
  if (apiTable.value) {
    apiTable.value.filter('is_active', val)
  }
}

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []
let trx_dtl = ref([])
const detail = ref(null)

const values = reactive({
  m_comp_id: 1,
  m_dir_id: null,
  m_divisi_id: null,
  m_dept_id: null,
  nama: '',
  kode: '',
  desc: '',
  is_active: true
})

const onDetailAdd = (rows) => {
  const dataArr = [...trx_dtl.value]
  rows.forEach(row => {
    const roleId = row.m_role_id || row.id
    const exists = dataArr.some(d => (d.m_role_id || d.id) === roleId)
    if (!exists) {
      dataArr.push({
        m_role_id: roleId,
        role_name: row.name || row['m_role.name'] || 'Role #' + roleId,
        desc: ''
      })
    }
  })
  trx_dtl.value = dataArr
}

const removeRowDetail = (data) => {
  const targetId = data.m_role_id || data.id
  trx_dtl.value = trx_dtl.value.filter(d => (d.m_role_id || d.id) !== targetId)
}

onBeforeMount(async () => {
  if (!isRead) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
    values.m_dir_id = store.user.data?.m_dir_id ?? null
  }

  if (isRead) {
    try {
      const editedId = route.params.id
      const dataURL = `${store.server.url_backend}/operation${endpointApi}/${editedId}`
      isRequesting.value = true

      const params = { join: true, transform: false }
      const fixedParams = new URLSearchParams(params)
      const res = await fetch(dataURL + '?' + fixedParams, {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
      })
      if (!res.ok) throw new Error("Failed when trying to read data")
      const resultJson = await res.json()
      initialValues = resultJson.data || {}

      for (const key in initialValues) {
        values[key] = initialValues[key]
      }

      // Ambil detail roles m_respo_d
      const resDet = await fetch(`${store.server.url_backend}/operation/m_respo_d?join=true&where=this.m_respo_id=${editedId}&paginate=500`, {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
      })
      if (resDet.ok) {
        const resultJsonDet = await resDet.json()
        const items = (resultJsonDet?.data || []).map(d => ({
          id: d.id,
          m_role_id: d.m_role_id,
          role_name: d['m_role.name'] || d.role_name || ('Role #' + d.m_role_id),
          desc: d.desc || ''
        }))
        trx_dtl.value = items
      }
    } catch (err) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: err.message || err,
        allowOutsideClick: false,
        confirmButtonText: 'Kembali',
      }).then(() => {
        router.back()
      })
    } finally {
      isRequesting.value = false
    }
  }
})

function onBack() {
  router.replace('/' + modulPath)
}

async function onSave() {
  if (!values.nama) {
    swal.fire({ icon: 'warning', text: 'Nama responsibility wajib diisi.' })
    return
  }
  if (!values.m_dir_id) {
    swal.fire({ icon: 'warning', text: 'Company / Direktorat wajib dipilih.' })
    return
  }

  try {
    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value)
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    isRequesting.value = true

    values.m_comp_id = 1
    values.is_active = values.is_active === true || values.is_active === 1 ? 1 : 0
    values.m_respo_d = trx_dtl.value.map(d => ({
      m_role_id: d.m_role_id,
      desc: d.desc || null
    }))

    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(values)
    })
    if (!res.ok) {
      const responseJson = await res.json().catch(() => null)
      throw new Error(responseJson?.message || responseJson?.error || "Gagal menyimpan data responsibility")
    }

    swal.fire({
      icon: 'success',
      title: 'Berhasil',
      text: 'Data responsibility berhasil disimpan'
    }).then(() => {
      router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
    })
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err.message || 'Terjadi kesalahan sistem'
    })
  } finally {
    isRequesting.value = false
  }
}

//  @else----------------------- LANDING
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      title: "Hapus",
      show: () => store.user.data?.username === 'developer' || currentMenu?.can_delete,
      click(row) {
        swal.fire({
          icon: 'warning',
          text: `Hapus Responsibility ${row.nama || ''}?`,
          confirmButtonText: 'Ya, Hapus',
          showDenyButton: true,
          denyButtonText: 'Batal'
        }).then(async (result) => {
          if (result.isConfirmed) {
            try {
              const dataURL = `${store.server.url_backend}/operation${endpointApi}/${row.id}`
              isRequesting.value = true
              const res = await fetch(dataURL, {
                method: 'DELETE',
                headers: {
                  'Content-Type': 'Application/json',
                  Authorization: `${store.user.token_type} ${store.user.token}`
                }
              })
              if (!res.ok) throw new Error("Gagal menghapus data")
              apiTable.value.reload()
            } catch (err) {
              swal.fire({ icon: 'error', text: err.message || err })
            } finally {
              isRequesting.value = false
            }
          }
        })
      }
    },
    {
      icon: 'eye',
      title: "Detail",
      class: 'bg-green-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?` + tsId)
      }
    },
    {
      icon: 'edit',
      title: "Edit",
      class: 'bg-blue-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&` + tsId)
      }
    }
  ],
  api: {
    url: `${store.server.url_backend}/operation${endpointApi}`,
    headers: {
      'Content-Type': 'Application/json',
      authorization: `${store.user.token_type} ${store.user.token}`
    },
    params: {
      simplest: true,
      searchfield: 'this.id, this.kode, this.nama, m_dir.nama, m_divisi.nama, m_dept.nama, this.desc',
    },
    onsuccess(response) {
      response.page = response.current_page
      response.hasNext = response.has_next
      return response
    }
  },
  columns: [
    {
      headerName: 'No',
      valueGetter: (params) => params.node.rowIndex + 1,
      width: 65,
      sortable: false,
      resizable: false,
      filter: false,
      cellClass: ['justify-center', 'bg-gray-50']
    },
    {
      field: 'kode',
      headerName: 'Kode',
      width: 130,
      cellClass: ['border-r', '!border-gray-200', 'justify-center', 'font-mono']
    },
    {
      field: 'nama',
      headerName: 'Nama Responsibility',
      flex: 1.5,
      cellClass: ['border-r', '!border-gray-200', 'font-semibold']
    },
    {
      field: 'm_dir.nama',
      headerName: 'Company / Direktorat',
      flex: 1.2,
      cellClass: ['border-r', '!border-gray-200']
    },
    {
      field: 'm_divisi.nama',
      headerName: 'Divisi',
      flex: 1,
      valueGetter: (params) => params.data?.['m_divisi.nama'] || 'Semua Divisi',
      cellClass: ['border-r', '!border-gray-200']
    },
    {
      field: 'is_active',
      headerName: 'Status',
      width: 110,
      cellClass: ['justify-center'],
      valueGetter: (params) => {
        const val = params.data?.is_active
        return (val === true || val === 1 || val === '1' || val === 'true') ? 'Active' : 'Inactive'
      },
      cellRenderer: (params) => {
        const isActive = params.value === 'Active'
        return `<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}">${params.value}</span>`
      }
    }
  ]
})

