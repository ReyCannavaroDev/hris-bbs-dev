//   javascript

import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isRead = route.params.id && route.params.id !== 'create'
const actionText = ref(route.params.id === 'create' ? 'Tambah' : (route.query.action?.toLowerCase() === 'verifikasi' ? null : route.query.action))
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
const detailArrAdjOpen = reactive({ items: [] })
let objectOpen = reactive({ items: 0 })
let idxOpen = reactive({ value: null })
let totalAdjOpen = reactive({ value: 0 })
let totalAdjPPHOpen = reactive({ value: [] })
let totalAdjFinalOpen = reactive({ value: 0 })
const tsId = `ts=` + (Date.parse(new Date()))
let isApproved = ref(false)
let isFinish = ref(false)
const is_approval = route.query.is_approval ? true : false

// ------------------------------ PERSIAPAN
const endpointApi = '/t_final_gaji'
onBeforeMount(() => {
  document.title = 'Finalisasi Gaji'
})

function formatRupiah(amount) {
  if (amount === undefined || amount === null || isNaN(amount)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount)
}

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
let _id = 0
const values = reactive({
  periode_awal: '',
  periode_akhir: '',
  m_dir_id: null,
  m_divisi_id: null,
  m_dept_id: null,
  desc: '',
  total_pengeluaran_gaji: 0,
})

function openDetail(i) {
  idxOpen.value = i
  detailArrAdjOpen.items = []
  objectOpen.items = detailArr.value[i]
  titleOpen.value = (detailArr.value[i]['m_kary.nama_lengkap'] || detailArr.value[i].m_kary?.nama_lengkap || detailArr.value[i].karyawan || '-') + ' - ' + (detailArr.value[i]['m_kary.nik'] || detailArr.value[i].m_kary?.nik || '')
  
  let dataFormat = (detailArr.value[i]?.detail_gaji && detailArr.value[i].detail_gaji.length) 
    ? detailArr.value[i].detail_gaji.slice() 
    : (detailArr.value[i]?.t_final_gaji_det_rincian?.slice().sort((a, b) => a.seq - b.seq) ?? [])

  detailArrOpen.items = dataFormat

  dataFormat = detailArr.value[i]?.detail_adj?.length ? detailArr.value[i]?.detail_adj : dataFormat
  dataFormat.filter(item => item.can_adjust == 1).forEach((v) => {
    const formattedValue = parseFloat(v.value) || 0
    detailArrAdjOpen.items.push({
      ...v,
      default: v.default ?? true,
      type: v.type || 'Bulanan',
      value_ref: formattedValue,
      value: formattedValue
    })
  })

  summaryAdj()
  generatePPH(false)

  modalOpen.value = true
}

function addRowAdj() {
  detailArrAdjOpen.items.push({
    _id: ++_id,
    label: '',
    name: '',
    can_adjust: 1,
    default: false,
    factor: '+',
    type: 'Bulanan',
    value_ref: null,
    value: 0
  })
}

function deleteRow(item) {
  detailArrAdjOpen.items = detailArrAdjOpen.items.filter((e) => e._id != item._id)
  summaryAdj()
  generatePPH(false)
}

function summaryAdj() {
  totalAdjPPHOpen.value = []
  totalAdjOpen.value = 0
  detailArrAdjOpen.items.forEach((v) => {
    if (v.factor == '-') {
      totalAdjOpen.value -= Number(v.value ?? 0)
    } else if (v.factor == '+') {
      totalAdjOpen.value += Number(v.value ?? 0)
    }
  })

  totalAdjFinalOpen.value = totalAdjOpen.value
  summaryPengeluaranGaji()
}

function summaryPengeluaranGaji() {
  values.total_pengeluaran_gaji = detailArr.value.reduce((a, b) => {
    const netto = Number(b.netto)
    if (!isNaN(netto)) {
      return a + netto
    } else {
      return a
    }
  }, 0)
}

function closeModal() {
  detailArrOpen.items = []
  detailArrAdjOpen.items = []
  modalOpen.value = false
}

async function generatePPH(popup = true) {
  try {
    const dataURL = `${store.server.url_backend}/operation/t_perhitungan_gaji/generatePPH`
    const params = {
      m_kary_id: objectOpen.items['m_kary_id'],
      netto: totalAdjOpen.value,
    }
    const fixedParams = new URLSearchParams(params)
    const res = await fetch(dataURL + '?' + fixedParams, {
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`,
      }
    })
    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json()
        formErrors.value = responseJson.errors || {}
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Failed when trying to calculate PPH")
      } else {
        throw ("Failed when trying to calculate PPH")
      }
    }

    const result = await res.json()
    if (result && result.length) {
      totalAdjPPHOpen.value = result
      totalAdjFinalOpen.value = totalAdjOpen.value - (totalAdjPPHOpen.value[0]?.value ?? 0)
    } else {
      totalAdjPPHOpen.value = []
      if (!popup) return
      swal.fire({
        icon: 'warning',
        text: 'Total Gaji berdasarkan jenis tanggungan dibawah standar minimum perhitungan PPH'
      })
    }
  } catch (err) {
    if (popup) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: err?.message || err || 'Gagal menghitung PPH'
      })
    }
  }
}

function formatDate(dateStr) {
  if (!dateStr) return null
  if (typeof dateStr === 'string' && dateStr.includes('/')) {
    const parts = dateStr.split('/')
    if (parts.length === 3) {
      return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`
    }
  }
  return dateStr
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
    const dataURL = `${store.server.url_backend}/operation/t_perhitungan_gaji`
    const params = {
      scopes: 'GenerateForFinal',
      periode_awal: formatDate(values.periode_awal),
      periode_akhir: formatDate(values.periode_akhir),
      paginate: 9999
    }
    if (values.m_dir_id) params.m_dir_id = values.m_dir_id
    if (values.m_divisi_id) params.m_divisi_id = values.m_divisi_id
    if (values.m_dept_id) params.m_dept_id = values.m_dept_id

    const fixedParams = new URLSearchParams(params)
    const res = await fetch(dataURL + '?' + fixedParams, {
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`,
      }
    })

    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json()
        formErrors.value = responseJson.errors || {}
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Gagal mengambil data perhitungan gaji")
      } else if (res.status === 500) {
        const responseJson = await res.json().catch(() => ({}))
        throw (responseJson.message || "Terjadi kesalahan server saat mengambil data perhitungan gaji.")
      } else {
        throw ("Gagal mengambil data perhitungan gaji")
      }
    }

    const result = await res.json()
    const resultData = result.data || []

    if (!resultData.length) {
      return swal.fire({
        icon: 'warning',
        text: 'Tidak ditemukan data Perhitungan Gaji untuk periode ini. Pastikan Perhitungan Gaji sudah dilakukan terlebih dahulu.'
      })
    }

    resultData.forEach((item) => {
      item._id = ++_id
      item.t_perhitungan_gaji_id = item.id
      item.karyawan = item['m_kary.nama_lengkap'] || item.karyawan
      item.deskripsi = item['deskripsi'] || ''
      if (typeof item.detail_gaji === 'string') {
        try { item.detail_gaji = JSON.parse(item.detail_gaji) } catch (e) {}
      }
    })

    detailArr.value = resultData
    summaryPengeluaranGaji()
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err?.message || err || 'Terjadi kesalahan'
    })
  }
}

function saveModal() {
  if (totalAdjPPHOpen.value.length) {
    detailArrAdjOpen.items = detailArrAdjOpen.items.concat(totalAdjPPHOpen.value)
  }

  detailArrAdjOpen.items.forEach((v, i) => {
    if (v == 0) return
    v.seq = i + 1
  })

  detailArr.value[idxOpen.value]['detail_adj'] = detailArrAdjOpen.items
  detailArr.value[idxOpen.value]['total_tax'] = totalAdjPPHOpen.value.length ? totalAdjPPHOpen.value[0].value : 0
  detailArr.value[idxOpen.value]['netto'] = totalAdjFinalOpen.value
  summaryPengeluaranGaji()
  modalOpen.value = false
}

async function posted() {
  swal.fire({
    icon: 'warning',
    text: 'Posting data ini? Setelah diposting, data akan difinalkan dan potongan kasbon/piutang karyawan akan otomatis diproses.',
    showDenyButton: true,
    confirmButtonText: 'Ya, Posting',
    denyButtonText: 'Batal'
  }).then(async (resConfirm) => {
    if (!resConfirm.isConfirmed) return

    const payload = {
      id: route.params.id
    }
    try {
      const dataURL = `${store.server.url_backend}/operation${endpointApi}/postData`
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
          throw (responseJson.message || "Gagal memposting data")
        } else {
          throw ("Gagal memposting data")
        }
      }
      swal.fire({
        icon: 'success',
        text: 'Data final gaji berhasil diposting!'
      })
      router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
    } catch (err) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: err?.message || err || 'Terjadi kesalahan saat memposting data'
      })
    } finally {
      isRequesting.value = false
    }
  })
}

async function onSave() {
  const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value)
  let dataSave = JSON.parse(JSON.stringify(values))
  
  dataSave['periode_awal'] = formatDate(dataSave['periode_awal'])
  dataSave['periode_akhir'] = formatDate(dataSave['periode_akhir'])

  if (!detailArr.value.length) {
    return swal.fire({
      icon: 'warning',
      text: 'Belum ada data detail karyawan! Klik tombol Generate terlebih dahulu.'
    })
  }

  // Clean and map details so only valid columns are sent
  dataSave['t_final_gaji_det'] = detailArr.value.map((v) => {
    const rincianSource = (v.detail_adj && v.detail_adj.length) ? v.detail_adj : (v.detail_gaji || [])
    
    const cleanedRincian = rincianSource.map((d, i) => {
      const rincianObj = {
        seq: i + 1,
        name: d.name || d.label || '',
        label: d.label || d.name || '',
        type: d.type || 'Bulanan',
        factor: d.factor || '+',
        value_ref: d.value_ref !== undefined && d.value_ref !== null ? d.value_ref : d.value,
        value: Number(d.value) || 0,
        can_adjust: d.can_adjust ? 1 : 0,
        t_potongan_id: d.t_potongan_id || null,
        t_cuti_id: d.t_cuti_id || null,
        deskripsi: d.deskripsi || null
      }
      if (!isCreating && d.id) {
        rincianObj.id = d.id
      }
      return rincianObj
    })

    const detObj = {
      t_perhitungan_gaji_id: v.t_perhitungan_gaji_id || (isCreating ? v.id : undefined),
      m_kary_id: v.m_kary_id,
      m_kary_dir_id: v.m_kary_dir_id || null,
      m_kary_divisi_id: v.m_kary_divisi_id || null,
      m_kary_dept_id: v.m_kary_dept_id || null,
      periode: v.periode || (dataSave['periode_awal'] + ' s/d ' + dataSave['periode_akhir']),
      periode_in_date: formatDate(v.periode_in_date) || dataSave['periode_awal'],
      total_gaji: Number(v.total_gaji) || 0,
      total_tax: Number(v.total_tax) || 0,
      netto: Number(v.netto) || 0,
      periode_id: v.periode_id || null,
      deskripsi: v.deskripsi || '',
      status: v.status || 'DRAFT',
      t_final_gaji_det_rincian: cleanedRincian
    }

    if (!isCreating && v.id) {
      detObj.id = v.id
    }

    return detObj
  })

  try {
    isRequesting.value = true
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(dataSave)
    })
    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json()
        formErrors.value = responseJson.errors || {}
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Gagal menyimpan data")
      } else if (res.status === 500) {
        const responseJson = await res.json().catch(() => ({}))
        throw (responseJson.message || "Terjadi kesalahan server saat menyimpan data finalisasi gaji.")
      } else {
        throw ("Gagal menyimpan data finalisasi gaji")
      }
    }
    swal.fire({
      icon: 'success',
      text: 'Data final gaji berhasil disimpan!'
    })
    router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err?.message || err || 'Terjadi kesalahan saat menyimpan'
    })
  } finally {
    isRequesting.value = false
  }
}

function onBack() {
  router.replace('/' + modulPath)
}

function onReset() {
  swal.fire({
    icon: 'warning',
    text: 'Reset data formulir ke kondisi semula?',
    showDenyButton: true,
    confirmButtonText: 'Ya',
    denyButtonText: 'Tidak'
  }).then((res) => {
    if (res.isConfirmed) {
      for (const key in initialValues) {
        values[key] = initialValues[key]
      }
    }
  })
}

onBeforeMount(async () => {
  if (isRead) {
    try {
      const editedId = route.params.id
      const dataURL = `${store.server.url_backend}/operation${endpointApi}/${editedId}`
      isRequesting.value = true

      const params = { transform: false }
      const fixedParams = new URLSearchParams(params)
      const res = await fetch(dataURL + '?' + fixedParams, {
        headers: {
          'Content-Type': 'application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
      })

      if (!res.ok) throw new Error("Gagal membaca data final gaji")
      const resultJson = await res.json()
      initialValues = resultJson.data || {}

      detailArr.value = initialValues.t_final_gaji_det?.sort((a, b) => a.id - b.id) || []
      detailArr.value.forEach((item) => {
        item['m_kary.nik'] = item['m_kary.nik'] || item.m_kary?.nik || ''
        item['m_kary.nama_lengkap'] = item['m_kary.nama_lengkap'] || item.m_kary?.nama_lengkap || ''
        item.karyawan = item.karyawan || item['m_kary.nama_lengkap'] || item.m_kary?.nama_lengkap || '-'
        item['m_kary_dir.nama'] = item['m_kary_dir.nama'] || item.m_kary_dir?.nama || item['m_dir.nama'] || item.m_dir?.nama || '-'
        item['m_kary_divisi.nama'] = item['m_kary_divisi.nama'] || item.m_kary_divisi?.nama || item.m_divisi?.nama || '-'
        item['m_kary_dept.nama'] = item['m_kary_dept.nama'] || item.m_kary_dept?.nama || item.m_dept?.nama || '-'
        if ((!item.detail_gaji || !item.detail_gaji.length) && item.t_final_gaji_det_rincian?.length) {
          item.detail_gaji = item.t_final_gaji_det_rincian.slice().sort((a, b) => a.seq - b.seq)
        }
      })

      for (const key in initialValues) {
        values[key] = initialValues[key]
      }
    } catch (err) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: err.message || err,
        allowOutsideClick: false,
        confirmButtonText: 'Kembali'
      }).then(() => router.back())
    } finally {
      isRequesting.value = false
    }
  }
})

//  @else----------------------- LANDING
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      title: "Hapus",
      show: (row) => row.status?.toUpperCase() === 'DRAFT',
      click(row) {
        swal.fire({
          icon: 'warning',
          text: 'Hapus Data Terpilih?',
          confirmButtonText: 'Ya',
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
              if (!res.ok) {
                const resultJson = await res.json()
                throw (resultJson.message || "Gagal menghapus data")
              }
              apiTable.value.reload()
            } catch (err) {
              isBadForm.value = true
              swal.fire({
                icon: 'error',
                text: err?.message || err || 'Gagal menghapus data'
              })
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
      show: (row) => row.status?.toUpperCase() === 'DRAFT',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&` + tsId)
      }
    },
    {
      icon: 'paper-plane',
      title: "Posted Data",
      class: 'bg-rose-700 rounded-lg text-white',
      show: (row) => row.status?.toUpperCase() === 'DRAFT',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Verifikasi&` + tsId)
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
      join: false,
      searchfield: 'this.nomor, this.desc, this.periode_awal, this.periode_akhir, this.total_pengeluaran_gaji, this.status',
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
      flex: 1,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'desc',
      headerName: 'Deskripsi Pendek',
      filter: true,
      sortable: true,
      wrapText: true,
      flex: 1,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'periode_awal',
      headerName: 'Periode Awal',
      filter: true,
      wrapText: true,
      sortable: true,
      flex: 1,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'periode_akhir',
      headerName: 'Periode Akhir',
      filter: true,
      wrapText: true,
      sortable: true,
      flex: 1,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-start']
    },
    {
      field: 'total_pengeluaran_gaji',
      headerName: 'Total Pengeluaran Gaji',
      filter: true,
      sortable: true,
      wrapText: true,
      flex: 1,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-end'],
      cellRenderer: ({ value }) => {
        return formatRupiah(value)
      }
    },
    {
      field: 'status',
      headerName: 'Status',
      filter: true,
      wrapText: true,
      sortable: true,
      flex: 1,
      filter: 'ColFilter',
      resizable: true,
      cellClass: ['border-r', '!border-gray-200', 'justify-center'],
      cellRenderer: ({ value }) => {
        return value === 'DRAFT'
          ? `<span class="text-gray-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">${value}</span>`
          : `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize font-bold">${value}</span>`
      },
    },
  ]
})

onActivated(() => {
  if (apiTable.value) {
    if (route.query.reload) {
      apiTable.value.reload()
    }
  }
})

//  @endif -------------------------------------------------END
watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))