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

// ------------------------------ PERSIAPAN
const endpointApi = '/default_users'
onBeforeMount(()=>{
  document.title = 'Master Pengguna'
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
let initialValues2 = {}
const changedValues = []
const trx_respo = ref([])

const values = reactive({
  is_active: true
})

onBeforeMount(async () => {
  values.company = null
  if (isRead) {
    //  READ DATA
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
      initialValues = resultJson.data
      initialValues.username = initialValues.username
      initialValues.email = initialValues.email
      nikChange(initialValues.m_kary_id)

      // Load responsibilities
      if (initialValues.default_users_respo && Array.isArray(initialValues.default_users_respo) && initialValues.default_users_respo.length > 0) {
        trx_respo.value = initialValues.default_users_respo.map(d => ({
          ...d,
          is_primary: d.is_primary === true || d.is_primary === 1 || d.is_primary === '1',
          is_active: d.is_active === true || d.is_active === 1 || d.is_active === '1'
        }))
      } else {
        try {
          const respoRes = await fetch(`${store.server.url_backend}/operation/default_users_respo/getUserRespo?user_id=${editedId}`, {
            headers: {
              'Content-Type': 'Application/json',
              Authorization: `${store.user.token_type} ${store.user.token}`
            }
          })
          if (respoRes.ok) {
            const respoJson = await respoRes.json()
            if (respoJson.data && Array.isArray(respoJson.data)) {
              trx_respo.value = respoJson.data.map(d => ({
                ...d,
                is_primary: d.is_primary === true || d.is_primary === 1 || d.is_primary === '1',
                is_active: d.is_active === true || d.is_active === 1 || d.is_active === '1'
              }))
            }
          }
        } catch (e) {
          console.warn('Gagal memuat list respo user:', e)
        }
      }

      // Pastikan jika ada responsibility, minimal 1 berstatus Primary dan Active
      if (trx_respo.value.length > 0 && !trx_respo.value.some(r => r.is_primary)) {
        trx_respo.value[0].is_primary = true
        trx_respo.value[0].is_active = true
      }
    } catch (err) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: err,
        allowOutsideClick: false,
        confirmButtonText: 'Kembali',
      }).then(() => {
        router.back()
      })
    }
    isRequesting.value = false
  }

  for (const key in initialValues) {
    values[key] = initialValues[key]
  }
  values.is_active = (values.is_active === true || values.is_active === 1 || values.is_active === '1' || values.is_active === 'true' || values.is_active === 't')
})

async function nikChange(id) {
  if(id){
    const url = `${store.server.url_backend}/operation/m_kary/${id}`
    const res = await fetch(url, {
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
    })
    if (!res.ok) throw new Error("Gagal memuat data karyawan, coba lagi nanti")
    const result = await res.json()
    values.company = result.data['m_dir.nama'] || result.data?.m_dir?.nama || result.data['m_comp.nama'] || result.data?.m_comp?.nama || ''
    values.m_dir_id = result.data.m_dir_id ?? result.data?.m_dir?.id ?? null
    values.m_comp_id = result.data.m_comp_id ?? result.data?.m_comp?.id ?? null
    values.divisi = result.data['m_divisi.nama'] || result.data?.m_divisi?.nama || ''
    values.departemen = result.data['m_dept.nama'] || result.data?.m_dept?.nama || ''
    values.name = result.data?.nama_lengkap || ''
    values.username = initialValues.username === values.username ? initialValues.username : (result.data?.kode || '')
    values.kode = result.data?.nik || result.data?.kode || ''
    if(!isRead){
      values.password = "12345"
      values.password_confirm = "12345"
      values.default_password = "12345"
    }
  }else{
    values.company = null
    values.m_dir_id = null
    values.m_comp_id = null
    values.divisi = null
    values.departemen = null
    values.name = null
    values.kode = null
    values.username = null
    values.password = null
    values.password_confirm = null
    values.default_password = null
  }
}

function filterAvailableRespo(response) {
  if (response && response.data && Array.isArray(response.data)) {
    const selectedIds = trx_respo.value.map(r => Number(r.m_respo_id || r.id)).filter(Boolean)
    response.data = response.data.filter(dt => !selectedIds.includes(Number(dt.id)))
  }
  if (response) {
    response.page = 1
    response.hasNext = false
  }
  return response
}

function onRespoAdd(rows) {
  let dataArr = [...trx_respo.value]
  rows.forEach(row => {
    const respoId = row.id
    if (dataArr.some(existing => Number(existing.m_respo_id) === Number(respoId))) {
      return
    }
    const hasPrimary = dataArr.some(item => item.is_primary)
    dataArr.push({
      m_respo_id: respoId,
      respo_nama: row.nama,
      company_nama: row['m_dir.nama'] || row.dir_nama || '-',
      divisi_nama: row['m_divisi.nama'] || row.divisi_nama || '-',
      dept_nama: row['m_dept.nama'] || row.dept_nama || '-',
      is_primary: !hasPrimary,
      is_active: true,
      seq: dataArr.length + 1
    })
  })
  trx_respo.value = dataArr
}

function togglePrimary(selectedItem) {
  if (!actionText.value) return
  trx_respo.value.forEach(item => {
    item.is_primary = (item === selectedItem)
  })
  selectedItem.is_active = true
}

function toggleStatus(selectedItem) {
  if (!actionText.value) return
  if (selectedItem.is_primary) {
    swal.fire({
      icon: 'warning',
      text: 'Responsibility utama (Primary) harus selalu aktif.'
    })
    selectedItem.is_active = true
    return
  }
  selectedItem.is_active = !selectedItem.is_active
}

function onToggleChange(item) {
  if (item.is_primary && !item.is_active) {
    item.is_active = true
    swal.fire({
      icon: 'warning',
      text: 'Responsibility utama (Primary) harus selalu aktif.'
    })
  }
}

function handlePrimaryChange(selectedItem) {
  togglePrimary(selectedItem)
}

function onRemoveRespo(index) {
  const removed = trx_respo.value[index]
  trx_respo.value.splice(index, 1)
  if (removed?.is_primary && trx_respo.value.length > 0) {
    trx_respo.value[0].is_primary = true
    trx_respo.value[0].is_active = true
  }
}

function onBack() {
  let isChanged = false
  for (const key in initialValues) {
    if (values[key] !== initialValues[key]) {
      isChanged = true
      break;
    }
  }

  if (!isChanged) {
    router.replace('/' + modulPath)
    return
  }

      router.replace('/' + modulPath)
}

function onReset() {
  swal.fire({
    icon: 'warning',
    text: 'Reset this form data?',
    showDenyButton: true
  }).then((res) => {
    if (res.isConfirmed) {
      for (const key in initialValues) {
        values[key] = initialValues[key]
      }
    }
  })
}

async function onSave() {
  formErrors.value = {}

  if (isRead) {
    if (values.password || values.password_confirm) {
      if (!values.password) {
        formErrors.value.password = ['Password wajib diisi jika ingin mereset password']
      }
      if (!values.password_confirm) {
        formErrors.value.password_confirm = ['Konfirmasi Password wajib diisi']
      }
      if (values.password && values.password_confirm && values.password !== values.password_confirm) {
        formErrors.value.password_confirm = ['Konfirmasi Password tidak sesuai dengan Password']
      }
    }
  } else {
    if (!values.m_kary_id) {
      formErrors.value.m_kary_id = ['Karyawan (NIK) wajib dipilih']
    }
    if (!values.username || String(values.username).trim() === '') {
      formErrors.value.username = ['Username wajib diisi']
    }
    if (!values.password) {
      formErrors.value.password = ['Password wajib diisi']
    }
    if (!values.password_confirm) {
      formErrors.value.password_confirm = ['Konfirmasi Password wajib diisi']
    }
    if (values.password && values.password_confirm && values.password !== values.password_confirm) {
      formErrors.value.password_confirm = ['Konfirmasi Password tidak sesuai dengan Password']
    }
  }

  if (Object.keys(formErrors.value).length > 0) {
    const errorMsg = formErrors.value.password_confirm?.[0] || formErrors.value.password?.[0] || formErrors.value.m_kary_id?.[0] || formErrors.value.username?.[0] || 'Harap lengkapi semua kolom yang wajib diisi'
    return swal.fire({
      icon: 'error',
      text: errorMsg
    })
  }

  const dataURL = (route.params.id === 'create')
    ? `${store.server.url_backend}/operation${endpointApi}`
    : `${store.server.url_backend}/operation${endpointApi}/reset_password?id_user=${route.params.id}`

  try {
    isRequesting.value = true
    delete values.atasan

    // Pastikan jika ada respo yang diinput, ada yang dijadikan primary
    if (trx_respo.value.length > 0) {
      if (!trx_respo.value.some(r => r.is_primary)) {
        trx_respo.value[0].is_primary = true
        trx_respo.value[0].is_active = true
      }
    }

    const payload = Object.assign({}, values, {
      is_active: values.is_active ? 1 : 0,
      default_users_respo: trx_respo.value.map((r, idx) => ({
        m_respo_id: r.m_respo_id,
        is_primary: (r.is_primary === true || r.is_primary === 1 || r.is_primary === '1') ? 1 : 0,
        is_active: (r.is_active === true || r.is_active === 1 || r.is_active === '1') ? 1 : 0,
        seq: idx + 1
      }))
    })

    const res = await fetch(dataURL, {
      method: 'POST',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(payload)
    })
    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json();
        formErrors.value = responseJson.errors || {};
        const errMsg = (Array.isArray(responseJson.errors) ? responseJson.errors[0] : (typeof responseJson.errors === 'string' ? responseJson.errors : responseJson.message)) || "Failed when trying to post data";
        throw errMsg;
      } else {
        throw "Failed when trying to post data";
      }
    }
    router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err
    })
  }
  isRequesting.value = false
}

//  @else----------------------- LANDING
const activeBtn = ref(null)

function filterShowData(params, noBtn) {
  if (activeBtn.value === noBtn) {
    activeBtn.value = null
    landing.api.params.where = null
  } else {
    activeBtn.value = noBtn
    landing.api.params.where = `this.is_active=${params ? 'true' : 'false'}`
  }

  if (apiTable.value) {
    apiTable.value.reload()
  }
}

const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      title: "Hapus",
      // show: () => store.user.data.username==='developer',
      click(row) {
        swal.fire({
          icon: 'warning',
          text: 'Hapus Data Terpilih?',
          confirmButtonText: 'Yes',
          showDenyButton: true,
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
              if (!res.ok) {
                const resultJson = await res.json()
                throw (resultJson.message || "Failed when trying to remove data")
              }
              apiTable.value.reload()
              // const resultJson = await res.json()
            } catch (err) {
              isBadForm.value = true
              swal.fire({
                icon: 'error',
                text: err
              })
            }
            isRequesting.value = false
          }
        })
      }
    },
    {
      icon: 'eye',
      title: "Read",
      class: 'bg-green-600 text-light-100',
      // show: (row) => (currentMenu?.can_read)||store.user.data.username==='developer',
      click(row) {
        router.push(`${route.path}/${row.id}?`+tsId)
      }
    },
    {
      icon: 'edit',
      title: "Edit",
      class: 'bg-blue-600 text-light-100',
      // show: (row) => (currentMenu?.can_update)||store.user.data.username==='developer',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&`+tsId)
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
      searchfield:'this.id, this.name, this.username, m_kary.kode, this.email, this.is_active',
    },
    onsuccess(response) {
      if (response && response.data) {
        response.data = response.data.map((item) => {
          item.status = (item.is_active === true || item.is_active === 1 || item.is_active === '1' || item.is_active === 'true' || item.is_active === 't') ? 'Active' : 'Inactive'
          return item
        })
      }
      response.page = response.current_page
      response.hasNext = response.has_next
      return response
    }
  },
  columns: [{
    headerName: 'No',
    valueGetter: (params) => params.node.rowIndex + 1,
    width: 60,
    sortable: true,
    resizable: true,
    filter: true,
    cellClass: ['justify-center', 'bg-gray-50', 'border-r', '!border-gray-200']
  },
  {
    headerName: 'Nama',
    field: 'name',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200']
  },
  {
    headerName: 'NIK',
    field: 'm_kary.kode',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200']
  },
  {
    headerName: 'Username',
    field: 'username',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200']
  },
  {
    headerName: 'Email',
    field: 'email',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200']
  },
  {
    headerName: 'Company',
    field: 'm_dir.nama',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200']
  },
  {
    headerName: 'Status',
    field: 'status',
    sortable: true,
    resizable: true,
    flex: 1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    cellRenderer: ({ value }) => {
      return value === 'Active' || value === true
        ? `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Active</span>`
        : `<span class="text-gray-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Inactive</span>`
    }
  },
  ]
})
onActivated(() => {
  //  reload table api landing
  if (apiTable.value) {
    if (route.query.reload) {
      apiTable.value.reload()
    }
  }
})

//  @endif -------------------------------------------------END
watchEffect(()=>store.commit('set', ['isRequesting', isRequesting.value]))
