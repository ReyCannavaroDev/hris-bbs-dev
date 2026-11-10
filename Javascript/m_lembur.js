//   javascript

import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, computed } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isTruthyFlag = (val) => val === true || val === 1 || val === '1' || String(val || '').toLowerCase() === 'true' || String(val || '').toLowerCase() === 't'

const getUserSnapshot = () => {
  const storeData = store.user?.data || {}
  let localData = {}
  if (typeof window !== 'undefined' && window.localStorage?.user) {
    try {
      localData = JSON.parse(window.localStorage.user)?.data || {}
    } catch (e) {}
  }
  return { ...localData, ...storeData }
}

const isHrdOrAdminRoleName = (name) => /(?:^|[^a-z])(hr[a-z]*|personalia|sdm|admin[a-z]*|human[\s_]*capital|human[\s_]*resources?)(?:[^a-z]|$)/i.test(String(name || ''))

const getActiveRespoScope = () => {
  const u = getUserSnapshot()
  const ar = u.active_respo || null
  const isSuper = u.username === 'developer' || isTruthyFlag(u.is_superadmin)
  const hasRespo = isTruthyFlag(u.has_respo) || Boolean(ar)
  const dirId = ar ? (ar.m_dir_id ?? null) : (u.m_dir_id ?? null)
  const divId = ar ? (ar.m_divisi_id ?? null) : null
  const deptId = ar ? (ar.m_dept_id ?? null) : null
  const isHolding = hasRespo ? (ar ? !dirId : false) : !dirId
  const rawRoles = Array.isArray(u.roles) && u.roles.length
    ? u.roles.map(r => (typeof r === 'string' ? r : (r?.name || '')))
    : (typeof u.role_names === 'string' && u.role_names.trim()
        ? u.role_names.split(',').map(s => s.trim())
        : (Array.isArray(u.role_names) ? u.role_names : []))
  const isHrdOrAdmin = isSuper || (!(hasRespo && !ar) && rawRoles.some(rn => isHrdOrAdminRoleName(rn)))
  return {
    isSuper,
    hasRespo,
    isHrdOrAdmin,
    isHolding,
    isUnrestricted: isSuper || isHolding,
    dirId: dirId ? Number(dirId) : null,
    divId: divId ? Number(divId) : null,
    deptId: deptId ? Number(deptId) : null,
    isLockedMissing: hasRespo && !ar
  }
}

const respoScope = ref(getActiveRespoScope())

const isSuperadmin = computed(() => respoScope.value.isSuper)

const getLandingWhereClause = (activeFilter = null) => {
  const scope = getActiveRespoScope()
  if (scope.isLockedMissing) {
    return '1=0'
  }
  if (activeFilter !== null) {
    return `this.is_active=${activeFilter ? 'true' : 'false'}`
  }
  return ''
}

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
const endpointApi = '/m_lembur'
onBeforeMount(()=>{
  document.title = 'Tarif Lembur'
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []

const values = reactive({
   is_active: true,
})

onBeforeMount(async () => {
  const scope = getActiveRespoScope()
  respoScope.value = scope
  const u = getUserSnapshot()

  // tampilkan default direktorat dengan store user comp.nama
  values.direktorat = u.direktorat
  if (!isRead) {
    values.m_dir_id = (!scope.isUnrestricted && scope.dirId) ? Number(scope.dirId) : null
  }
  if (isRead) {
    //  READ DATA
    try {
      const editedId = route.params.id
      const dataURL = `${store.server.url_backend}/operation${endpointApi}/${editedId}`
      isRequesting.value = true

      const params = { join: false, transform: false }
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
  if (initialValues.m_dir_id) {
    values.m_dir_id = Number(initialValues.m_dir_id)
  }
  if (initialValues.is_active !== undefined) {
    values.is_active = (initialValues.is_active === true || initialValues.is_active === 1 || initialValues.is_active === '1' || initialValues.is_active === 'true' || initialValues.is_active === 't')
  }
  if (actionText.value === 'Copy' || route.query.action === 'Copy') {
    values.kode = null
    delete values.id
    if (!scope.isUnrestricted && scope.dirId) {
      values.m_dir_id = Number(scope.dirId)
    }
  }
})

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
  let cleanBesaran = values.besaran
  if (typeof cleanBesaran === 'string') {
    cleanBesaran = cleanBesaran.replace(/\./g, '').replace(/,/g, '.')
  }
  const besaranNum = Number(cleanBesaran)

  if (cleanBesaran === null || cleanBesaran === undefined || cleanBesaran === '' || isNaN(besaranNum) || besaranNum <= 0) {
    formErrors.value = { besaran: ['Tarif / Jam (Besaran) wajib diisi dengan nominal lebih dari 0'] }
    swal.fire({
      icon: 'error',
      text: 'Tarif / Jam (Besaran) wajib diisi dengan nominal lebih dari 0'
    })
    return
  }

  try {
    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value)
    const payload = {
      m_dir_id: values.m_dir_id || null,
      kode: values.kode || null,
      m_divisi_id: values.m_divisi_id || null,
      m_dept_id: values.m_dept_id || null,
      grading_id: values.grading_id || null,
      grading: values.grading || null,
      besaran: besaranNum,
      desc: values.desc || null,
      is_active: (values.is_active === true || values.is_active === 1 || values.is_active === '1' || values.is_active === 'true' || values.is_active === 't') ? 1 : 0
    }

    if (isCreating) {
      delete payload.id
      if (actionText.value === 'Copy' || route.query.action === 'Copy') {
        payload.kode = null
      }
    } else {
      payload.id = Number(route.params.id)
    }

    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    isRequesting.value = true
    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(payload)
    })

    if (!res.ok) {
      const responseJson = await res.json().catch(() => ({}))
      formErrors.value = responseJson.errors || {}
      let errMsg = responseJson.error || responseJson.message || 'Failed when trying to post data'
      if (Array.isArray(responseJson.errors) && responseJson.errors.length) {
        errMsg = responseJson.errors.join('\n')
      } else if (typeof responseJson.errors === 'object' && responseJson.errors !== null && !Array.isArray(responseJson.errors)) {
        const firstErrKey = Object.keys(responseJson.errors)[0]
        if (firstErrKey && responseJson.errors[firstErrKey]) {
          errMsg = Array.isArray(responseJson.errors[firstErrKey]) ? responseJson.errors[firstErrKey][0] : responseJson.errors[firstErrKey]
        }
      }
      throw new Error(errMsg)
    }

    swal.fire({
      icon: 'success',
      text: 'Data tarif lembur berhasil disimpan!'
    }).then(() => {
      router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
    })
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err.message || err || 'Terjadi kesalahan saat menyimpan data'
    })
  } finally {
    isRequesting.value = false
  }
}

// Filter Action Button
const activeBtn = ref(null)

function filterShowData(params, noBtn) {
  if (activeBtn.value === noBtn) {
    activeBtn.value = null
    const baseWhere = getLandingWhereClause()
    if (baseWhere) {
      landing.api.params.where = baseWhere
    } else {
      delete landing.api.params.where
    }
  } else {
    activeBtn.value = noBtn
    landing.api.params.where = getLandingWhereClause(params)
  }

  if (apiTable.value) {
    apiTable.value.reload()
  }
}

//  @else----------------------- LANDING
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
    },
    {
      icon: 'copy',
      title: "Copy",
      class: 'bg-gray-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&`+tsId)
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
      where: getLandingWhereClause(),
    },
    onsuccess(response) {
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
    field: 'kode',
    headerName: 'Kode',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'm_dir.nama',
    headerName: 'Company',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-left'],
    valueGetter: (params) => params.data?.['m_dir.nama'] || params.data?.m_dir?.nama || 'Semua Company'
  },
  {
    field: 'm_divisi.nama',
    headerName: 'Divisi',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-left', 'capitalize'],
    valueGetter: (params) => params.data?.['m_divisi.nama'] || params.data?.m_divisi?.nama || 'Semua Divisi'
  },
  {
    field: 'm_dept.nama',
    headerName: 'Departemen',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-left', 'capitalize'],
    valueGetter: (params) => params.data?.['m_dept.nama'] || params.data?.m_dept?.nama || 'Semua Dept'
  },
  {
    field: 'grading',
    headerName: 'Grading',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-left', 'capitalize'],
    valueGetter: (params) => params.data?.grading || 'Semua Grading'
  },
  {
    field: 'besaran',
    headerName: 'Tarif / Jam',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-end', 'font-semibold'],
    valueFormatter: (params) => {
      const val = Number(params.value || 0)
      return 'Rp ' + val.toLocaleString('id-ID')
    }
  },
  {
    headerName: 'Status',
    field: 'status',
    filter: 'ColFilter',
    filterParams: {
      options: ['Active', 'Inactive']
    },
    filterValueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    valueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    sortable: true,
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
      return value === 'Active' || value === true
        ? `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Active</span>`
        : `<span class="text-red-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Inactive</span>`
    }
  }]
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