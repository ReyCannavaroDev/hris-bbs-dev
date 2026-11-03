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
const detailArr = ref([])
const titleOpen = ref('')
const formErrors = ref({})
const modalOpen = ref(false)
const detailArrOpen = reactive({items: []})
const openedRincian = ref([])
const tsId = `ts=`+(Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/t_perhitungan_gaji'
onBeforeMount(()=>{
  document.title = 'Perhitungan Gaji'
})

function openDetailFromLanding(row) {
  titleOpen.value = row['m_kary.nama_lengkap'] + ' - ' +row['m_kary.nik']
  openedRincian.value = []
  let dataFormat =  row?.detail_gaji ?? []
  if (typeof dataFormat === 'string') {
    try { dataFormat = JSON.parse(dataFormat) } catch (e) {}
  }
  if (Array.isArray(dataFormat)) {
    dataFormat.forEach(item => {
      if (typeof item.rincian === 'string') {
        try { item.rincian = JSON.parse(item.rincian) } catch (e) {}
      }
    })
  }

  detailArrOpen.items = dataFormat
  modalOpen.value = true
}

function toggleRincian(idx) {
  if (openedRincian.value.includes(idx)) {
    openedRincian.value = openedRincian.value.filter(i => i !== idx)
  } else {
    openedRincian.value.push(idx)
  }
}

function closeModal(i) {
  openedRincian.value = []
  modalOpen.value = false
}

function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount);
}


//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []
let _id = 0

let now = new Date()
let tempYear = now.getFullYear()
let tempMonth = now.getMonth()

function parseDate(v) {
  if (!v) return null
  if (v instanceof Date) return isNaN(v.getTime()) ? null : v
  if (typeof v !== 'string') return null
  const raw = v.trim().split(' ')[0]
  const parts = raw.split(/[-/.]/)
  if (parts.length !== 3) return null

  let year, month, day
  if (parts[0].length === 4) {
    year = parseInt(parts[0], 10)
    month = parseInt(parts[1], 10) - 1
    day = parseInt(parts[2], 10)
  } else if (parts[2].length === 4) {
    day = parseInt(parts[0], 10)
    month = parseInt(parts[1], 10) - 1
    year = parseInt(parts[2], 10)
  } else {
    return null
  }

  const d = new Date(year, month, day)
  return isNaN(d.getTime()) ? null : d
}

function formatDateDisplay(d) {
  if (!d) return ''
  const dateObj = (d instanceof Date) ? d : parseDate(d)
  if (!dateObj) return d
  const day = String(dateObj.getDate()).padStart(2, '0')
  const month = String(dateObj.getMonth() + 1).padStart(2, '0')
  const year = dateObj.getFullYear()
  return `${day}/${month}/${year}`
}

function formatDateApi(d) {
  if (!d) return ''
  const dateObj = (d instanceof Date) ? d : parseDate(d)
  if (!dateObj) return d
  const day = String(dateObj.getDate()).padStart(2, '0')
  const month = String(dateObj.getMonth() + 1).padStart(2, '0')
  const year = dateObj.getFullYear()
  return `${year}-${month}-${day}`
}

let dStart = new Date(tempYear, tempMonth, 1)
let dEnd = new Date(tempYear, tempMonth + 1, 0)
let firstDay = formatDateDisplay(dStart)
let lastDay = formatDateDisplay(dEnd)

const tipePeriodeText = ref('')

function isTipeMingguan() {
  const t = String(tipePeriodeText.value || '').toLowerCase()
  return t.includes('minggu')
}

function isTipeBulanan() {
  const t = String(tipePeriodeText.value || '').toLowerCase()
  return t.includes('bulan')
}


function calcEndMonthly(d) {
  const year = d.getFullYear()
  const month = d.getMonth()
  const day = d.getDate()
  if (day === 1) {
    return new Date(year, month + 1, 0)
  }
  const maxDaysNextMonth = new Date(year, month + 2, 0).getDate()
  const targetDay = Math.min(day - 1, maxDaysNextMonth)
  return new Date(year, month + 1, targetDay)
}

function calcStartMonthly(d) {
  const year = d.getFullYear()
  const month = d.getMonth()
  const day = d.getDate()
  const lastDayThisMonth = new Date(year, month + 1, 0).getDate()
  if (day === lastDayThisMonth) {
    return new Date(year, month, 1)
  }
  const maxDaysPrevMonth = new Date(year, month, 0).getDate()
  const targetDay = Math.min(day + 1, maxDaysPrevMonth)
  return new Date(year, month - 1, targetDay)
}

const periodeOptions = ref([])
const periodeOptionsMap = reactive({})

function onLoadPeriodeOptions(response) {
  if (!response) return
  const list = response.data || response || []
  if (Array.isArray(list)) {
    periodeOptions.value = list
    list.forEach((item) => {
      if (item.id && item.value) {
        periodeOptionsMap[item.id] = String(item.value).toUpperCase()
      }
    })
    if (values.periode_gaji_id && !tipePeriodeText.value) {
      onPeriodeGajiInput(values.periode_gaji_id)
    }
  }
}

function onPeriodeGajiInput(v) {
  values.periode_gaji_id = v
  values.m_kary_id = null
  detailArr.value = []
  if (!v) {
    tipePeriodeText.value = ''
    return
  }

  const mapped = periodeOptionsMap[v]
  if (mapped) {
    tipePeriodeText.value = mapped
  } else if (isNaN(v)) {
    tipePeriodeText.value = String(v).toUpperCase()
  } else {
    const found = periodeOptions.value.find((o) => String(o.id) === String(v))
    if (found) {
      tipePeriodeText.value = String(found.value || '').toUpperCase()
    }
  }

  if (values.periode_awal) {
    onPeriodeAwalChange(values.periode_awal)
  }
}

function onPeriodeAwalChange(v) {
  const d = parseDate(v)
  if (d) {
    values.periode_awal = formatDateDisplay(d)
  } else {
    values.periode_awal = v
  }
  detailArr.value = []
  if (!d) return

  // Jika periode gaji (tipe penggajian) tidak diisi, periode tanggal bebas diisi
  if (!values.periode_gaji_id && !tipePeriodeText.value) return

  if (isTipeMingguan()) {
    const end = new Date(d)
    end.setDate(end.getDate() + 6)
    values.periode_akhir = formatDateDisplay(end)
  } else if (isTipeBulanan()) {
    values.periode_akhir = formatDateDisplay(calcEndMonthly(d))
  }
}

function onPeriodeAkhirChange(v) {
  const d = parseDate(v)
  if (d) {
    values.periode_akhir = formatDateDisplay(d)
  } else {
    values.periode_akhir = v
  }
  detailArr.value = []
  if (!d) return

  // Jika periode gaji (tipe penggajian) tidak diisi, periode tanggal bebas diisi
  if (!values.periode_gaji_id && !tipePeriodeText.value) return

  if (isTipeMingguan()) {
    const start = new Date(d)
    start.setDate(start.getDate() - 6)
    values.periode_awal = formatDateDisplay(start)
  } else if (isTipeBulanan()) {
    values.periode_awal = formatDateDisplay(calcStartMonthly(d))
  }
}

function onTipePeriodeChange(objVal) {
  if (objVal && typeof objVal === 'object') {
    tipePeriodeText.value = String(objVal.value || objVal.nama || objVal.label || '').toUpperCase()
    if (objVal.id) periodeOptionsMap[objVal.id] = tipePeriodeText.value
  } else if (typeof objVal === 'string' || typeof objVal === 'number') {
    onPeriodeGajiInput(objVal)
    return
  } else {
    tipePeriodeText.value = ''
  }
  if (values.periode_gaji_id && tipePeriodeText.value && values.periode_awal) {
    onPeriodeAwalChange(values.periode_awal)
  }
}

const values = reactive({
  periode_awal: firstDay,
  periode_akhir: lastDay,
  m_dir_id: null,
  m_divisi_id: null,
  m_dept_id: null,
  divisi: null,
  periode_gaji_id: null,
  m_kary_id: null
})

function openDetail(i) {
  titleOpen.value = detailArr.value[i]?.nama_lengkap ?? '-' +' - ' +detailArr.value[i]?.nama_lengkap ?? ''
  openedRincian.value = []
  let dataFormat =  detailArr.value[i]?.detail_gaji ?? []
  if (typeof dataFormat === 'string') {
    try { dataFormat = JSON.parse(dataFormat) } catch (e) {}
  }
  if (Array.isArray(dataFormat)) {
    dataFormat.forEach(item => {
      if (typeof item.rincian === 'string') {
        try { item.rincian = JSON.parse(item.rincian) } catch (e) {}
      }
    })
  }

  detailArrOpen.items = dataFormat
  modalOpen.value = true
}
onBeforeMount(async () => {
  console.log(values.periode_awal)
  // tampilkan default direktorat dengan store user comp.nama
  values.direktorat = store.user.data?.direktorat

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

  if (initialValues.periode_awal) {
    values.periode_awal = formatDateDisplay(initialValues.periode_awal)
  }
  if (initialValues.periode_akhir) {
    values.periode_akhir = formatDateDisplay(initialValues.periode_akhir)
  }

  if (isRead && initialValues && initialValues.id) {
    const itemData = { ...initialValues }
    itemData.karyawan = itemData['m_kary.nama_lengkap'] || itemData.karyawan || itemData.nama_lengkap || '-'
    itemData.deskripsi = itemData.deskripsi || ''
    if (typeof itemData.detail_gaji === 'string') {
      try { itemData.detail_gaji = JSON.parse(itemData.detail_gaji) } catch (e) {}
    }
    detailArr.value = [itemData]
  }
})

function onBack() {
  if (route.query.view_gaji) {
    router.replace('/t_info_gaji')
  }else{
    router.replace('/' + modulPath)
  }
  return
  // let isChanged = false
  // for (const key in initialValues) {
  //   if (values[key] !== initialValues[key]) {
  //     isChanged = true
  //     break;
  //   }
  // }

  // if (!isChanged) {
  //   router.replace('/' + modulPath)
  //   return
  // }

  // swal.fire({
  //   icon: 'warning',
  //   text: 'Buang semua perubahan dan kembali ke list data?',
  //   showDenyButton: true
  // }).then((res) => {
  //   if (res.isConfirmed) {
  //     router.replace('/' + modulPath)
  //   }
  // })
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


function formatDate(dateStr) {
  return formatDateApi(dateStr)
}

async function generatePerhitungan() {
    if (!values.periode_awal || !values.periode_akhir) {
      return swal.fire({
        icon: 'warning',
        text: 'Periode Awal dan Periode Akhir wajib diisi terlebih dahulu!'
      })
    }

    detailArr.value = []
    try {
      const dataURL = `${store.server.url_backend}/operation/t_perhitungan_gaji/generate`
      const payload = {
        ...values,
        periode_awal: formatDate(values.periode_awal),
        periode_akhir: formatDate(values.periode_akhir)
      }

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
          const responseJson = await res.json()
          formErrors.value = responseJson.errors || {}
          throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Failed when trying to post data")
        } else if(res.status == 500) {
          throw ("Sesuatu yang salah terjadi, silahkan coba kembali nanti atau hubungi administrator")
        } else {
          throw ("Failed when trying to post data")
        }
      }
      
      const result = await res.json()
      const resultData = result.data
      if(!resultData?.length) {
        swal.fire({
          icon: 'warning',
          text: "Tidak ditemukan data Perhitungan Gaji, pastikan karyawan sudah memiliki standar gaji"
        })
      }
      resultData.forEach((item) => {
        item.id           = ++_id
        item.karyawan     = item['nama_lengkap']
        item.deskripsi    = item['desc'] || ''
        detailArr.value.push(item)
      })
      
    } catch (err) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: typeof err === 'string' ? err : "Sesuatu yang salah terjadi, silahkan coba kembali nanti atau hubungi administrator"
      })
    }
}

async function onSave() {
  // merging detail data
    const payload = {
          ...values,
          periode_awal: formatDateApi(values.periode_awal),
          periode_akhir: formatDateApi(values.periode_akhir),
          detail: detailArr.value ?? detailArr._value
        }
        try {
        const dataURL = `${store.server.url_backend}/operation/t_perhitungan_gaji/save`
        isRequesting.value = true

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
const landing = reactive({
  actions: [
    // {
    //   icon: 'trash',
    //   class: 'bg-red-600 text-light-100',
    //   title: "Hapus",
    //   // show: () => store.user.data.username==='developer',
    //   click(row) {
    //     swal.fire({
    //       icon: 'warning',
    //       text: 'Hapus Data Terpilih?',
    //       confirmButtonText: 'Yes',
    //       showDenyButton: true,
    //     }).then(async (result) => {
    //       if (result.isConfirmed) {
    //         try {
    //             const dataURL = `${store.server.url_backend}/operation${endpointApi}/${row.id}`
    //             isRequesting.value = true
    //             const res = await fetch(dataURL, {
    //               method: 'DELETE',
    //               headers: {
    //                 'Content-Type': 'Application/json',
    //                 Authorization: `${store.user.token_type} ${store.user.token}`
    //               }
    //             })
    //             if (!res.ok) {
    //             const resultJson = await res.json()
    //             throw (resultJson.message || "Failed when trying to remove data")
    //           }
    //             apiTable.value.reload()
    //             const resultJson = await res.json()
    //           } catch (err) {
    //             isBadForm.value = true
    //             swal.fire({
    //               icon: 'error',
    //               text: err
    //             })
    //           }
    //           isRequesting.value = false
    //       }
    //     })
    //   }
    // },
    {
      icon: 'eye',
      title: "Read",
      class: 'bg-green-600 text-light-100',
      // show: (row) => (currentMenu?.can_read)||store.user.data.username==='developer',
      click(row) {
        openDetailFromLanding(row)
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
      searchfield:'this.id, this.nomor, m_kary.nik, m_kary.nama_lengkap, m_kary_dir.nama, m_kary_divisi.nama, m_kary_dept.nama, this.periode, periode.value, this.netto',
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
    field: 'nomor',
    headerName: 'Nomor Generate',
    filter: true,
    wrapText: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start','w-[400px]']
  },
  {
    field: 'm_kary.nik',
    headerName: 'NIK',
    filter: true,
    wrapText: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_kary.nama_lengkap',
    headerName: 'Karyawan',
    filter: true,
    wrapText: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_kary_dir.nama',
    headerName: 'Company',
    filter: true,
    wrapText: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_kary_divisi.nama',
    headerName: 'Divisi',
    filter: true,
    wrapText: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_kary_dept.nama',
    headerName: 'Departement',
    filter: true,
    wrapText: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'periode',
    headerName: 'Tgl Periode',
    filter: true,
    wrapText: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'periode.value',
    headerName: 'Periode Gaji',
    filter: true,
    wrapText: true,
    sortable: true,
    filter: 'ColFilter',
    flex:1,
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'netto',
    headerName: 'Gaji',
    filter: true,
    wrapText: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-end'],
    cellRenderer: ({ value }) => {
      return formatRupiah(value);
    },
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