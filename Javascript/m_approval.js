import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isRead = route.params.id && route.params.id !== 'create'
const actionText = ref(route.params.id === 'create' ? 'Create' : route.query.action)
const isBadForm = ref(false)
const isRequesting = ref(false)
const modulPath = route.params.modul
const currentMenu = store.currentMenu
const currentUser = store.user.data
const apiTable = ref(null)
const formErrors = ref({})
const tsId = `ts=` + (Date.parse(new Date()))
const detail = ref(null)
const detailKey = ref(0)
// ------------------------------ PERSIAPAN

const endpointApi = '/m_approval'
onBeforeMount(() => {
  document.title = 'Master Approval'
})

//  @if( $id )------------------- VALUES FORM
let initialValues = {}
const changedValues = []

const values = reactive({
  is_active: true
})
const detailArr = ref([])

function clearDetailArr() {
  swal.fire({
    icon: 'warning',
    text: 'Clear Details?',
    showDenyButton: true
  }).then((res) => {
    if (res.isConfirmed) {
      detailArr.value = [{ tipe: 'MENGAJUKAN', level: 1, is_full_approve: false, is_skippable: false }]
      detailKey.value++
      if (detail.value?.key !== undefined) {
        detail.value.key++
      }
    }
  })
}

function onDetailAdd() {
  const data = [...detailArr.value]
  const row = {
    level: data.length + 1,
    tipe: 'MENGETAHUI',
    is_full_approve: false,
    is_skippable: false
  }
  data.push(row)
  detailArr.value = data
  detailKey.value++
  if (detail.value?.key !== undefined) {
    detail.value.key++
  }
}

function onDetailDelete(index) {
  if (index === 0) {
    swal.fire({
      icon: 'warning',
      text: 'Data level 1 tipe MENGAJUKAN tidak dapat dihapus',
    })
    return
  }
  const data = [...detailArr.value]
  data.splice(index, 1)
  data.forEach((item, idx) => {
    item.level = idx + 1
  })
  detailArr.value = data
  detailKey.value++
  if (detail.value?.key !== undefined) {
    detail.value.key++
  }
}

function populateFromInitial() {
  const initialData = JSON.parse(JSON.stringify(initialValues || {}))
  if (actionText.value === 'Create') {
    values.nama = ''
    values.m_menu_id = null
    values.desc = ''
    values.is_active = true
    detailArr.value = [{ tipe: 'MENGAJUKAN', level: 1, is_full_approve: false, is_skippable: false }]
  } else {
    values.nama = actionText.value === 'Copy' ? (initialData.nama ? `${initialData.nama} - Copy` : '') : (initialData.nama || '')
    values.m_menu_id = initialData.m_menu_id || null
    values.desc = initialData.desc || ''
    values.m_comp_id = initialData.m_comp_id || null
    values.m_dir_id = initialData.m_dir_id || null
    values.is_active = (initialData.is_active === true || initialData.is_active === 1 || initialData.is_active === '1' || initialData.is_active === 'true' || initialData.is_active === 't')

    const rawDetails = initialData.m_approval_det || []
    detailArr.value = rawDetails.map((item, idx) => {
      const clean = {
        level: idx + 1,
        tipe: item.tipe || (idx === 0 ? 'MENGAJUKAN' : 'MENGETAHUI'),
        m_role_id: item.m_role_id || null,
        default_user_id: item.default_user_id || null,
        is_full_approve: Boolean(item.is_full_approve),
        is_skippable: Boolean(item.is_skippable),
        'm_role.name': item['m_role.name'] || item.m_role?.name || null,
        'default_user.username': item['default_user.username'] || item.default_user?.username || null,
      }
      if (actionText.value !== 'Copy' && item.id) {
        clean.id = item.id
        if (item.m_approval_id) {
          clean.m_approval_id = item.m_approval_id
        }
      }
      return clean
    })
  }

  if (!detailArr.value.length) {
    detailArr.value = [{ tipe: 'MENGAJUKAN', level: 1, is_full_approve: false, is_skippable: false }]
  }
}

onBeforeMount(async () => {
  if (route.params.id === 'create') {
    detailArr.value.push({ tipe: 'MENGAJUKAN', level: 1, is_full_approve: false, is_skippable: false })
  }
  if (isRead) {
    try {
      const editedId = route.params.id
      const dataURL = `${store.server.url_backend}/operation${endpointApi}/${editedId}`
      isRequesting.value = true

      const params = {}
      const fixedParams = new URLSearchParams(params)
      const res = await fetch(dataURL + '?' + fixedParams, {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
      })
      if (!res.ok) throw new Error("Failed when trying to read data")
      const resultJson = await res.json()
      resultJson.data.m_approval_det = (resultJson.data?.m_approval_det || []).sort((a, b) => a.level - b.level)

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

  populateFromInitial()
})

function onBack() {
  if (!route.query.action) {
    router.replace('/' + modulPath)
    return
  }
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

  swal.fire({
    icon: 'warning',
    text: 'Buang semua perubahan dan kembali ke list data?',
    showDenyButton: true
  }).then((res) => {
    if (res.isConfirmed) {
      router.replace('/' + modulPath)
    }
  })
}

function onReset() {
  swal.fire({
    icon: 'warning',
    text: 'Reset this form data?',
    showDenyButton: true
  }).then((res) => {
    if (res.isConfirmed) {
      populateFromInitial()
      detailKey.value++
      if (detail.value?.key !== undefined) {
        detail.value.key++
      }
    }
  })
}

function onSave() {
  formErrors.value = {}
  if (!values.nama || String(values.nama).trim() === '') {
    formErrors.value.nama = ['Kolom Nama wajib diisi']
  }
  if (!values.m_menu_id) {
    formErrors.value.m_menu_id = ['Kolom Menu wajib dipilih']
  }

  if (Object.keys(formErrors.value).length > 0) {
    return swal.fire({
      icon: 'error',
      text: 'Harap lengkapi semua kolom yang bertanda bintang (*)'
    })
  }

  const details = [...detailArr.value]
  const approvers = details.filter((dt, idx) => idx > 0 || ['MENGETAHUI', 'MENYETUJUI'].includes(dt.tipe))
  if (details.length <= 1 || approvers.length === 0) {
    return swal.fire({
      icon: 'error',
      text: 'Detail Approval wajib diisi minimal 1 level persetujuan (Mengetahui / Menyetujui)'
    })
  }

  for (let i = 0; i < details.length; i++) {
    const dt = details[i]
    dt.level = i + 1

    if (i === 0) {
      dt.tipe = dt.tipe || 'MENGAJUKAN'
    } else {
      if (!dt.tipe || !['MENGETAHUI', 'MENYETUJUI'].includes(dt.tipe)) {
        return swal.fire({
          icon: 'error',
          text: `Data baris ke ${i + 1}: Tipe approval wajib dipilih (MENGETAHUI / MENYETUJUI)`
        })
      }
      if (!dt.m_role_id && !dt.default_user_id) {
        return swal.fire({
          icon: 'error',
          text: `Data baris ke ${i + 1}: Role atau User Action wajib dipilih`
        })
      }
    }
  }

  swal.fire({
    icon: 'warning',
    text: 'Save data?',
    showDenyButton: true
  }).then(async (res) => {
    if (res.isConfirmed) {
      try {
        const isCreating = ['Create', 'Copy'].includes(actionText.value)
        const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
        isRequesting.value = true

        const cleanDetails = details.map((dt, idx) => {
          const row = {
            level: idx + 1,
            tipe: idx === 0 ? 'MENGAJUKAN' : dt.tipe,
            m_role_id: dt.m_role_id ? parseInt(dt.m_role_id) : null,
            default_user_id: dt.default_user_id ? parseInt(dt.default_user_id) : null,
            is_full_approve: dt.is_full_approve ? 1 : 0,
            is_skippable: dt.is_skippable ? 1 : 0
          }
          if (!isCreating && dt.id) {
            row.id = dt.id
            if (dt.m_approval_id) {
              row.m_approval_id = dt.m_approval_id
            }
          }
          return row
        })

        const payload = {
          nama: values.nama,
          m_menu_id: values.m_menu_id,
          desc: values.desc || null,
          is_active: values.is_active ? 1 : 0,
          m_approval_det: cleanDetails
        }
        if (values.m_comp_id) payload.m_comp_id = values.m_comp_id
        if (values.m_dir_id) payload.m_dir_id = values.m_dir_id

        const res = await fetch(dataURL, {
          method: isCreating ? 'POST' : 'PUT',
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
          body: JSON.stringify(payload)
        })
        if (!res.ok) {
          if ([400, 422].includes(res.status)) {
            const responseJson = await res.json()
            formErrors.value = responseJson.errors || {}
            throw new Error(responseJson.message || (Array.isArray(responseJson.errors) ? responseJson.errors[0] : "Failed when trying to post data"))
          } else {
            throw new Error("Failed when trying to post data")
          }
        }
        router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
      } catch (err) {
        isBadForm.value = true
        swal.fire({
          icon: 'error',
          text: err?.message || err
        })
      }
      isRequesting.value = false
    }
  })
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
  //  ACTIONS sesuai priviledge user dan data
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
                if ([400, 422].includes(res.status)) {
                  const responseJson = await res.json()
                  formErrors.value = responseJson.errors || {}
                  throw new Error(responseJson.message || "Failed when trying to post data")
                } else {
                  throw new Error("Failed when trying to post data")
                }
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
        router.push(`${route.path}/${row.id}?` + tsId)
      }
    },
    {
      icon: 'edit',
      title: "Edit",
      class: 'bg-blue-600 text-light-100',
      // show: (row) => (currentMenu?.can_update)||store.user.data.username==='developer',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&` + tsId)
      }
    },
    {
      icon: 'copy',
      title: "Copy",
      class: 'bg-gray-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&` + tsId)
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
      simplest: true
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
    cellClass: ['justify-center', 'bg-gray-50']
  },
  {
    flex: 1,
    field: 'nama',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    cellClass: ['border-r', '!border-gray-200']
  },
  {
    headerName: "Menu",
    flex: 1,
    field: 'm_menu.menu',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    cellClass: ['border-r', '!border-gray-200']
  },
  {
    headerName: 'Keterangan',
    field: 'desc',
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    sortable: true,
    filter: true,
    filter: 'ColFilter',
  },
  {
    headerName: 'Status',
    field: 'status',
    sortable: true,
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const val = params?.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params?.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    cellRenderer: (params) => {
      const val = params?.value !== undefined ? params.value : (params?.data?.status ?? params?.data?.is_active ?? params)
      const isActive = val === 'Active' || val === true || val === 1 || val === '1' || val === 'true'
      return isActive
        ? `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Active</span>`
        : `<span class="text-gray-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Inactive</span>`
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
watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))
