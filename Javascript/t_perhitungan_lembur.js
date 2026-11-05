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
const detailArrOpen = reactive({ items: [] })
const tsId = `ts=` + (Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/t_perhitungan_lembur'
onBeforeMount(() => {
  document.title = 'Perhitungan Gaji Lembur'
})

function formatRupiah(amount) {
  if (amount === null || amount === undefined || isNaN(amount)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount)
}

function openDetailFromLanding(row) {
  titleOpen.value = (row['m_kary.nama_lengkap'] || row.nama_lengkap || '-') + ' (' + (row['m_kary.nik'] || row.nik || '-') + ')'
  let dataFormat = row?.detail_lembur ?? []
  if (typeof dataFormat === 'string') {
    try { dataFormat = JSON.parse(dataFormat) } catch (e) { dataFormat = [] }
  }
  detailArrOpen.items = Array.isArray(dataFormat) ? dataFormat : []
  modalOpen.value = true
}

function openDetail(i) {
  const item = detailArr.value[i]
  titleOpen.value = (item?.nama_lengkap || item?.karyawan || '-') + ' (' + (item?.nik || item?.kode || '-') + ')'
  let dataFormat = item?.detail_lembur ?? []
  if (typeof dataFormat === 'string') {
    try { dataFormat = JSON.parse(dataFormat) } catch (e) { dataFormat = [] }
  }
  detailArrOpen.items = Array.isArray(dataFormat) ? dataFormat : []
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
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

// Durasi perhitungan lembur default 1 minggu (7 hari: +6 hari dari awal)
let dInit = new Date(tempYear, tempMonth, 1)
let dInitEnd = new Date(dInit)
dInitEnd.setDate(dInitEnd.getDate() + 6)
let firstDay = formatDateDisplay(dInit)
let endDay = formatDateDisplay(dInitEnd)

function onPeriodeAwalChange(v) {
  const d = parseDate(v)
  values.periode_awal = d ? formatDateDisplay(d) : v
  detailArr.value = []
  if (!d) return

  // Di perhitungan lembur durasinya selalu 1 minggu (7 hari, yaitu + 6 hari)
  const end = new Date(d)
  end.setDate(end.getDate() + 6)
  values.periode_akhir = formatDateDisplay(end)
}

function onPeriodeAkhirChange(v) {
  const d = parseDate(v)
  values.periode_akhir = d ? formatDateDisplay(d) : v
  detailArr.value = []
  if (!d) return

  // Di perhitungan lembur durasinya selalu 1 minggu (7 hari, yaitu - 6 hari)
  const start = new Date(d)
  start.setDate(start.getDate() - 6)
  values.periode_awal = formatDateDisplay(start)
}

const values = reactive({
  periode_awal: firstDay,
  periode_akhir: endDay,
  m_dir_id: null,
  m_divisi_id: null,
  m_dept_id: null,
  divisi: null,
  m_kary_id: null
})

onBeforeMount(async () => {
  values.direktorat = store.user.data?.direktorat

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
    itemData.nik = itemData['m_kary.nik'] || itemData.nik || itemData.kode || '-'
    itemData.divisi_nama = itemData['m_kary_divisi.nama'] || itemData.divisi_nama || itemData.m_divisi_nama || '-'
    itemData.dept_nama = itemData['m_kary_dept.nama'] || itemData.dept_nama || itemData.m_dept_nama || '-'
    if (typeof itemData.detail_lembur === 'string') {
      try { itemData.detail_lembur = JSON.parse(itemData.detail_lembur) } catch (e) { itemData.detail_lembur = [] }
    }
    detailArr.value = [itemData]
  }
})

function onBack() {
  router.replace('/' + modulPath)
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
    const dataURL = `${store.server.url_backend}/operation${endpointApi}/generate`
    const payload = {
      ...values,
      periode_awal: formatDate(values.periode_awal),
      periode_akhir: formatDate(values.periode_akhir)
    }

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
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Gagal melakukan perhitungan lembur")
      } else {
        throw ("Terjadi kesalahan server saat menghitung data lembur.")
      }
    }

    const result = await res.json()
    const resultData = result.data || []
    if (!resultData.length) {
      swal.fire({
        icon: 'warning',
        text: "Tidak ditemukan data transaksi lembur berstatus APPROVED pada rentang periode ini."
      })
    }
    resultData.forEach((item) => {
      item.id = ++_id
      item.karyawan = item.nama_lengkap || item['m_kary.nama_lengkap'] || item.karyawan
      item.divisi_nama = item.divisi_nama || item.m_divisi_nama || item['m_kary_divisi.nama'] || '-'
      item.dept_nama = item.dept_nama || item.m_dept_nama || item['m_kary_dept.nama'] || '-'
      detailArr.value.push(item)
    })
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: typeof err === 'string' ? err : "Terjadi kesalahan saat menghitung lembur."
    })
  } finally {
    isRequesting.value = false
  }
}

async function onSave() {
  if (!detailArr.value.length) {
    return swal.fire({
      icon: 'warning',
      text: 'Belum ada data hasil perhitungan lembur untuk disimpan!'
    })
  }

  const payload = {
    ...values,
    periode_awal: formatDateApi(values.periode_awal),
    periode_akhir: formatDateApi(values.periode_akhir),
    detail: detailArr.value
  }
  try {
    const dataURL = `${store.server.url_backend}/operation${endpointApi}/save`
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
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Gagal menyimpan perhitungan lembur")
      } else {
        throw ("Gagal menyimpan data perhitungan lembur")
      }
    }

    swal.fire({
      icon: 'success',
      text: 'Data perhitungan gaji lembur berhasil disimpan!'
    }).then(() => {
      router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
    })
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err
    })
  } finally {
    isRequesting.value = false
  }
}

//  @else----------------------- LANDING
const landing = reactive({
  actions: [
    {
      icon: 'eye',
      title: "Lihat Rincian",
      class: 'bg-green-600 text-light-100',
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
      searchfield: 'this.id, this.nomor, m_kary.nik, m_kary.nama_lengkap, m_kary_divisi.nama, m_kary_dept.nama, this.periode, this.total_lembur',
    },
    onsuccess(response) {
      if (response) {
        response.page = response.current_page || 1
        response.hasNext = response.has_next || false
      }
      return response
    }
  },
  columns: [
    {
      headerName: 'No',
      valueGetter: (params) => (params && params.node) ? params.node.rowIndex + 1 : 1,
      width: 60,
      sortable: true,
      resizable: true,
      filter: true,
      cellClass: ['justify-center', 'bg-gray-50', 'border-r', '!border-gray-200']
    },
    {
      field: 'nomor',
      headerName: 'Nomor Dokumen',
      filter: true,
      wrapText: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-start', 'w-[200px]']
    },
    {
      field: 'm_kary.nik',
      headerName: 'NIK',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 1,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'm_kary.nama_lengkap',
      headerName: 'Karyawan',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 1.5,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'm_kary_divisi.nama',
      headerName: 'Divisi',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 1,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'm_kary_dept.nama',
      headerName: 'Departemen',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 1,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'periode',
      headerName: 'Periode',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 1.2,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'total_jam_diakui',
      headerName: 'Jam Diakui',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 0.8,
      cellClass: ['border-r', '!border-gray-200', 'justify-center', 'font-semibold'],
      cellRenderer: (params) => {
        const val = params?.value !== undefined ? params.value : (params?.data?.total_jam_diakui ?? 0)
        return `${val ?? 0} jam`
      }
    },
    {
      field: 'total_lembur',
      headerName: 'Total Lembur',
      filter: true,
      sortable: true,
      filter: 'ColFilter',
      resizable: true,
      flex: 1.2,
      cellClass: ['border-r', '!border-gray-200', 'justify-end', 'font-bold', 'text-green-700'],
      cellRenderer: (params) => {
        const val = params?.value !== undefined ? params.value : (params?.data?.total_lembur ?? 0)
        return formatRupiah(val)
      }
    }
  ]
})

onActivated(() => {
  if (apiTable.value && route.query.reload) {
    apiTable.value.reload()
  }
})

//  @endif -------------------------------------------------END
watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))
