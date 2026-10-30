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
const tsId = `ts=` + (Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/m_posisi'
onBeforeMount(() => {
  document.title = 'Master Posisi'
})
const formatCurrency = (text) => {
  if (!text) text = 0

  const formatter = new Intl.NumberFormat('id', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  })

  if (typeof text === 'string') {
    if (isNaN(parseFloat(text)) || isNaN(parseInt(text))) {
      return formatter.format(0)
    }

    if (text.includes(',') || text.includes('.')) {
      return formatter.format(parseFloat(text))
    }

    return formatter.format(parseInt(text))
  }

  return formatter.format(text)
}
//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []

const values = reactive({
  is_active: true,
  direktorat: store.user.data?.direktorat
})

onBeforeMount(async () => {
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
      initialValues = resultJson.data || {}
      if (initialValues.is_active !== undefined) {
        initialValues.is_active = (initialValues.is_active === 1 || initialValues.is_active === true || initialValues.is_active === '1' || initialValues.is_active === 'true')
      }
      if (route.query.action === 'Copy') {
        delete initialValues.id
        delete initialValues.kode
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
  if (values.is_active === undefined) {
    values.is_active = true
  }
})

let _id = 0
const detailArr = ref([])
const addDetail = () => {
  const tempItem = {
    __id: ++_id,
    komponen: values.komponen,
    biaya: values.biayaKomp,
    catatan_kom: values.catatanKomp
  }
  detailArr.value = [...detailArr.value, tempItem]
  values.komponen = null
  values.biayaKomp = null
  values.catatanKomp = null
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
  try {
    const errors = {}

    if (!values.desc_kerja || !String(values.desc_kerja).trim()) {
      errors.desc_kerja = 'Deskripsi wajib diisi'
    }

    if (values.min_pengalaman === undefined || values.min_pengalaman === null || String(values.min_pengalaman).trim() === '') {
      errors.min_pengalaman = 'Minimal pengalaman wajib diisi'
    } else if (Number(values.min_pengalaman) < 0) {
      errors.min_pengalaman = 'Minimal pengalaman tidak boleh kurang dari 0'
    }

    if (!values.min_pendidikan_id) {
      errors.min_pendidikan_id = 'Minimal pendidikan wajib diisi'
    }

    if (values.min_gaji_pokok === undefined || values.min_gaji_pokok === null || String(values.min_gaji_pokok).trim() === '') {
      errors.min_gaji_pokok = 'Minimal gaji pokok wajib diisi'
    } else if (Number(values.min_gaji_pokok) < 0) {
      errors.min_gaji_pokok = 'Minimal gaji pokok tidak boleh kurang dari 0'
    }

    if (values.max_gaji_pokok === undefined || values.max_gaji_pokok === null || String(values.max_gaji_pokok).trim() === '') {
      errors.max_gaji_pokok = 'Maksimal gaji pokok wajib diisi'
    } else if (Number(values.max_gaji_pokok) < 0) {
      errors.max_gaji_pokok = 'Maksimal gaji pokok tidak boleh kurang dari 0'
    }

    if (
      values.min_gaji_pokok !== undefined && values.min_gaji_pokok !== null && String(values.min_gaji_pokok).trim() !== '' &&
      values.max_gaji_pokok !== undefined && values.max_gaji_pokok !== null && String(values.max_gaji_pokok).trim() !== '' &&
      Number(values.max_gaji_pokok) < Number(values.min_gaji_pokok)
    ) {
      errors.max_gaji_pokok = 'Maksimal gaji pokok tidak boleh lebih kecil dari minimal gaji pokok'
    }

    if (values.potongan_bpjs === undefined || values.potongan_bpjs === null || String(values.potongan_bpjs).trim() === '') {
      errors.potongan_bpjs = 'Potongan BPJS wajib diisi'
    } else if (Number(values.potongan_bpjs) < 0) {
      errors.potongan_bpjs = 'Potongan BPJS tidak boleh kurang dari 0'
    }

    if (!values.tipe_bpjs_id) {
      errors.tipe_bpjs_id = 'Tipe BPJS wajib diisi'
    }

    if (!values.desc || !String(values.desc).trim()) {
      errors.desc = 'Keterangan wajib diisi'
    }

    if (Object.keys(errors).length > 0) {
      formErrors.value = errors
      isBadForm.value = true
      swal.fire({
        icon: 'warning',
        title: 'Data Belum Lengkap',
        text: 'Silakan lengkapi semua kolom yang bertanda bintang (*)'
      })
      return
    }

    formErrors.value = {}
    values.is_active = (values.is_active === true || values.is_active == 1 || values.is_active === '1' || values.is_active === 'true') ? 1 : 0
    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value)
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    isRequesting.value = true
    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(values)
    })
    if (!res.ok) {
      const responseJson = await res.json().catch(() => ({}))
      formErrors.value = responseJson.errors || {}
      let errMsg = responseJson.error || responseJson.message || "Failed when trying to post data"
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
      text: 'Data posisi berhasil disimpan!',
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
      simplest: true,
      searchfield: 'this.id, this.kode, this.desc_kerja, this.min_gaji_pokok, this.max_gaji_pokok, this.desc, this.is_active',
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
    field: 'kode',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Deskripsi',
    field: 'desc_kerja',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Gaji Rata-Rata',
    field: 'min_gaji_pokok',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    wrapText: true,
    flex: 1,
    valueGetter: (params) => formatCurrency(params.data?.min_gaji_pokok) + ' - ' + formatCurrency(params.data?.max_gaji_pokok),
    cellRenderer: (params) => formatCurrency(params.data?.min_gaji_pokok) + ' - ' + formatCurrency(params.data?.max_gaji_pokok),
    cellClass: ['border-r', '!border-gray-200', 'justify-end']
  },
  {
    headerName: 'Status',
    field: 'status',
    sortable: true,
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    cellRenderer: (params) => {
      const val = params?.value !== undefined ? params.value : (params?.data ? (params.data.status ?? params.data.is_active) : params)
      const isActive = val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't'
      return isActive
        ? `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Active</span>`
        : `<span class="text-red-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Inactive</span>`
    },
  },
  {
    field: 'desc',
    headerName: 'Keterangan',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
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
watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))
