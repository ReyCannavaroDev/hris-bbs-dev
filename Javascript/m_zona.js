import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, watch } from 'vue'

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
const endpointApi = '/m_zona'
onBeforeMount(()=>{
  document.title = 'Master Zona'
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []

const values = reactive({
  m_comp_id: null,
  m_dir_id: null,
  is_active : true
})

let detail = reactive({ items: [] })
const _id = ref(0)
const addDetail = () => {
  const newItem = {
    _id: ++_id.value,
    m_lokasi_id: '',
    desc: '',
  }
  detail.items.push(newItem)
}

const removeDetail = (detailItem) => {
  detail.items = detail.items.filter((e) => e._id != detailItem._id)
}


onBeforeMount(async () => {
  // tampilkan default direktorat dengan store user comp.nama
  values.direktorat = store.user.data?.direktorat

  if (!isRead) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
    values.m_dir_id = store.user.data?.m_dir_id ?? null
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
      initialValues['m_zona_det'].forEach((item) => {
        item._id = ++_id.value
        detail.items.push(item)
      })
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

  if (!values.m_comp_id) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
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
  if (!detail.items || detail.items.length === 0) {
    swal.fire({
      icon: 'warning',
      text: 'Harap mengisi detail zona terlebih dahulu!',
    })
    return
  }

  const hasEmptyLokasi = detail.items.some(item => !item.m_lokasi_id)
  if (hasEmptyLokasi) {
    swal.fire({
      icon: 'warning',
      text: 'Harap melengkapi lokasi pada detail zona!',
    })
    return
  }

  //values.tags = JSON.stringify(values.tags)
      try {

         values.m_zona_det = detail.items.map(item => ({
      m_lokasi_id: item.m_lokasi_id,
      desc: item.desc,
    }));

         const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value);
     const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`;
     isRequesting.value = true;
     if (!values.m_comp_id) {
       values.m_comp_id = store.user.data?.m_comp_id ?? 1
     }
     values.is_active = values.is_active ? 1 : 0
const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`,
      },
      body: JSON.stringify(values),
    });
        if (!res.ok) {
          if ([400, 422].includes(res.status)) {
            const responseJson = await res.json()
            formErrors.value = responseJson.errors || {}
            throw (responseJson.errors.length ? responseJson.errors[0] : responseJson.message || "Failed when trying to post data")
          } else {
            throw ("Failed when trying to post data")
          }
        }
        router.replace('/' + modulPath + '?reload='+(Date.parse(new Date())))
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
// Transform zona query to apply action button concept (where=this.is_active=true/false) to table column filters
function transformZonaUrl(url) {
  try {
    if (!url || typeof url !== 'string') return url
    if (!url.includes('m_zona')) return url
    if (!url.includes('filter_is_active') && !url.includes('filter_status')) {
      return url
    }

    const isRelative = url.startsWith('/')
    const base = typeof window !== 'undefined' && window.location ? window.location.origin : 'http://localhost'
    const urlObj = new URL(url, base)
    const isActive = urlObj.searchParams.get('filter_is_active') ?? urlObj.searchParams.get('filter_status')
    const currentWhere = urlObj.searchParams.get('where') || ''

    let conds = []
    if (currentWhere) {
      conds = currentWhere.split(/\s+and\s+/i).map(c => c.trim()).filter(Boolean)
    }

    if (isActive !== null) {
      const val = String(isActive).toLowerCase().trim()
      const boolVal = (val === 'active' || val === 'true' || val === '1') ? 'true' : ((val === 'inactive' || val === 'false' || val === '0') ? 'false' : null)
      conds = conds.filter(c => !/\bthis\.is_active\b/i.test(c))
      if (boolVal !== null) {
        conds.push('this.is_active=' + boolVal)
      }
      urlObj.searchParams.delete('filter_is_active')
      urlObj.searchParams.delete('filter_status')
    }

    if (conds.length > 0) {
      urlObj.searchParams.set('where', conds.join(' and '))
    } else {
      urlObj.searchParams.delete('where')
    }

    const cleanSearch = decodeURIComponent(urlObj.search).replace(/\+/g, ' ')
    return isRelative ? (urlObj.pathname + cleanSearch) : (urlObj.origin + urlObj.pathname + cleanSearch)
  } catch (e) {
    console.error('Error transforming m_zona filter:', e)
    return url
  }
}

// 1. Intercept fetch & XMLHttpRequest
if (typeof window !== 'undefined' && !window.__m_zona_filter_intercepted__) {
  window.__m_zona_filter_intercepted__ = true

  if (window.fetch) {
    const originalFetch = window.fetch
    window.fetch = async function (input, init) {
      if (typeof input === 'string') {
        input = transformZonaUrl(input)
      } else if (input && input.url) {
        const newUrl = transformZonaUrl(input.url)
        if (newUrl !== input.url && typeof Request !== 'undefined') {
          input = new Request(newUrl, input)
        }
      }
      return originalFetch.call(this, input, init)
    }
  }

  if (typeof XMLHttpRequest !== 'undefined' && XMLHttpRequest.prototype && XMLHttpRequest.prototype.open) {
    const originalXhrOpen = XMLHttpRequest.prototype.open
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
      if (typeof url === 'string') {
        url = transformZonaUrl(url)
      }
      return originalXhrOpen.call(this, method, url, ...rest)
    }
  }

  if (window.axios && window.axios.interceptors && window.axios.interceptors.request) {
    window.axios.interceptors.request.use(function (config) {
      if (config && config.url) {
        config.url = transformZonaUrl(config.url)
      }
      return config
    })
  }
}

if (store && store.axios && store.axios.interceptors && store.axios.interceptors.request) {
  store.axios.interceptors.request.use(function (config) {
    if (config && config.url) {
      config.url = transformZonaUrl(config.url)
    }
    return config
  })
}

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
      simplest: true,
      join: true,
      searchfield:'this.id, this.kode, this.desc, this.nama, m_dir.nama, this.is_active',
    },
    onsuccess(response) {
      if (response && response.data) {
        response.data = response.data.map((item) => {
          const isActive = (item.is_active === true || item.is_active === 1 || item.is_active === '1' || item.is_active === 'true' || item.is_active === 't')
          item.is_active = isActive
          item.status = isActive ? 'Active' : 'Inactive'
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
    headerName: 'Company',
    field: 'm_dir.nama',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data?.['m_dir.nama'] || p.data?.m_dir?.nama || p.data?.['dir.nama'] || p.data?.dir?.nama || p.data?.m_dir_nama || p.data?.['m_comp.nama'] || '-',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Kode',
    field: 'kode',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Nama',
    field: 'nama',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Keterangan',
    field: 'desc',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
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
  },
  ]
})

watch(() => ({ ...(landing.api && landing.api.params) }), (newParams) => {
  if (!newParams || !landing.api || !landing.api.params) return
  let isActive = newParams.filter_is_active ?? newParams.filter_status
  let modified = false
  let currentWhere = landing.api.params.where || ''
  let conds = currentWhere ? currentWhere.split(/\s+and\s+/i).map(c => c.trim()).filter(Boolean) : []

  if (isActive !== undefined) {
    const val = String(isActive).toLowerCase().trim()
    const boolVal = (val === 'active' || val === 'true' || val === '1') ? 'true' : ((val === 'inactive' || val === 'false' || val === '0') ? 'false' : null)
    conds = conds.filter(c => !/\bthis\.is_active\b/i.test(c))
    if (boolVal !== null) {
      conds.push('this.is_active=' + boolVal)
    }
    delete landing.api.params.filter_is_active
    delete landing.api.params.filter_status
    modified = true
  }

  if (modified) {
    landing.api.params.where = conds.length > 0 ? conds.join(' and ') : null
  }
}, { deep: true })

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
