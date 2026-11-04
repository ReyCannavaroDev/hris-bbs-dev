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
const openedRincian = ref([])
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

const values = reactive({
  periode_awal: firstDay,
  periode_akhir: lastDay,
  m_dir_id: null,
  m_divisi_id: null,
  m_dept_id: null,
  periode_gaji_id: null,
  desc: '',
  total_pengeluaran_gaji: 0,
})

function clearDetail() {
  detailArr.value = []
  values.total_pengeluaran_gaji = 0
}

const tipePeriodeText = ref('')
const periodeOptions = ref([])
const periodeOptionsMap = reactive({
  LEMBUR: 'LEMBUR SAJA'
})

function onLoadPeriodeOptions(response) {
  if (!response) return
  let list = response.data || response || []
  if (Array.isArray(list)) {
    if (!list.some(o => String(o.value || '').toUpperCase().includes('LEMBUR'))) {
      list.push({ id: 'LEMBUR', value: 'LEMBUR SAJA' })
    }
    periodeOptions.value = list
    list.forEach((item) => {
      if (item.id && item.value) {
        periodeOptionsMap[item.id] = String(item.value).toUpperCase()
      }
    })
    periodeOptionsMap['LEMBUR'] = 'LEMBUR SAJA'
  }
}

async function loadPeriodeGajiOptions() {
  try {
    const res = await fetch(`${store.server.url_backend}/operation/m_general?where=this.group='PERIODE GAJI' and this.is_active='true'&simplest=true`, {
      headers: {
        Authorization: `${store.user.token_type} ${store.user.token}`
      }
    })
    if (res.ok) {
      const json = await res.json()
      onLoadPeriodeOptions(json)
    }
  } catch (e) {
    console.warn('Gagal memuat opsi periode gaji:', e)
  }
}

function isTipeLemburSaja() {
  const t = String(tipePeriodeText.value || '').toUpperCase()
  return t.includes('LEMBUR') || values.periode_gaji_id === 'LEMBUR'
}


function onPeriodeAwalChange(v) {
  const d = parseDate(v)
  values.periode_awal = d ? formatDateDisplay(d) : v
  clearDetail()
}

function onPeriodeAkhirChange(v) {
  const d = parseDate(v)
  values.periode_akhir = d ? formatDateDisplay(d) : v
  clearDetail()
}

function onTipePeriodeChange(objVal) {
  if (objVal && typeof objVal === 'object') {
    tipePeriodeText.value = String(objVal.value || objVal.nama || objVal.label || '').toUpperCase()
    if (objVal.id !== undefined) {
      values.periode_gaji_id = objVal.id
      periodeOptionsMap[objVal.id] = tipePeriodeText.value
    }
  } else if (typeof objVal === 'string' || typeof objVal === 'number') {
    values.periode_gaji_id = objVal
    tipePeriodeText.value = periodeOptionsMap[objVal] || String(objVal).toUpperCase()
  } else {
    values.periode_gaji_id = null
    tipePeriodeText.value = ''
  }
  clearDetail()
}

function openDetail(i) {
  idxOpen.value = i
  openedRincian.value = []
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

function toggleRincian(idx) {
  if (openedRincian.value.includes(idx)) {
    openedRincian.value = openedRincian.value.filter(i => i !== idx)
  } else {
    openedRincian.value.push(idx)
  }
}

function closeModal() {
  openedRincian.value = []
  detailArrOpen.items = []
  openedRincian.value = []
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
      // Skema Gross-up PPh 21 disubsidi perusahaan: net potongan pajak 0 terhadap take-home pay
      let taxDeduction = 0
      result.forEach(item => {
        if (item.factor === '+') taxDeduction -= Number(item.value ?? 0)
        else if (item.factor === '-') taxDeduction += Number(item.value ?? 0)
      })
      totalAdjFinalOpen.value = totalAdjOpen.value - taxDeduction
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
  return formatDateApi(dateStr)
}

async function generatePerhitungan() {
  if (!values.periode_awal || !values.periode_akhir) {
    return swal.fire({
      icon: 'warning',
      text: 'Periode Awal dan Periode Akhir wajib diisi terlebih dahulu!'
    })
  }

  clearDetail()
  const tglAwalFormatted = formatDate(values.periode_awal)
  const tglAkhirFormatted = formatDate(values.periode_akhir)

  // 1. KASUS KHUSUS: TIPE LEMBUR SAJA (Hanya tarik data dari perhitungan lembur)
  if (isTipeLemburSaja()) {
    try {
      isRequesting.value = true
      const lemburURL = `${store.server.url_backend}/operation/t_perhitungan_lembur`
      const lemburParams = {
        scopes: 'GenerateForFinal',
        periode_awal: tglAwalFormatted,
        periode_akhir: tglAkhirFormatted,
        paginate: 9999
      }
      if (values.m_dir_id) lemburParams.m_dir_id = values.m_dir_id
      if (values.m_divisi_id) lemburParams.m_divisi_id = values.m_divisi_id
      if (values.m_dept_id) lemburParams.m_dept_id = values.m_dept_id

      const resLembur = await fetch(lemburURL + '?' + new URLSearchParams(lemburParams), {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`,
        }
      })
      if (!resLembur.ok) {
        throw new Error('Gagal mengambil data perhitungan lembur')
      }
      const jsonLembur = await resLembur.json()
      const dataLembur = jsonLembur.data || []

      if (!dataLembur.length) {
        return swal.fire({
          icon: 'info',
          text: 'Tidak ada data perhitungan lembur pada rentang periode dan filter yang dipilih.'
        })
      }

      const rows = dataLembur.map((l) => {
        const nominal = Number(l.total_lembur || 0)
        return {
          _id: ++_id,
          id: null,
          t_perhitungan_gaji_id: null,
          m_kary_id: l.m_kary_id,
          'm_kary.nik': l['m_kary.nik'] || l.nik || '-',
          'm_kary.nama_lengkap': l['m_kary.nama_lengkap'] || l.nama_lengkap || '-',
          karyawan: l['m_kary.nama_lengkap'] || l.nama_lengkap || '-',
          'm_kary_dir.nama': l['m_kary_dir.nama'] || '-',
          'm_kary_divisi.nama': l['m_kary_divisi.nama'] || l.divisi_nama || '-',
          'm_kary_dept.nama': l['m_kary_dept.nama'] || l.dept_nama || '-',
          periode: l.periode || (values.periode_awal + ' s/d ' + values.periode_akhir),
          periode_in_date: l.periode_awal || tglAwalFormatted,
          total_tax: 0,
          total_gaji: nominal,
          netto: nominal,
          detail_gaji: [
            {
              label: 'Upah Lembur',
              factor: '+',
              value: nominal,
              type: 'Lembur',
              can_adjust: 1
            }
          ],
          deskripsi: l.deskripsi || ''
        }
      })

      detailArr.value = rows
      summaryPengeluaranGaji()
    } catch (err) {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: err?.message || err || 'Gagal mengambil data perhitungan lembur'
      })
    } finally {
      isRequesting.value = false
    }
    return
  }

  // 2. KASUS PERHITUNGAN GAJI (BULANAN / MINGGUAN / SEMUA)
  try {
    isRequesting.value = true
    const dataURL = `${store.server.url_backend}/operation/t_perhitungan_gaji`

    const params = {
      scopes: 'GenerateForFinal',
      periode_awal: tglAwalFormatted,
      periode_akhir: tglAkhirFormatted,
      paginate: 9999
    }
    if (values.m_dir_id) params.m_dir_id = values.m_dir_id
    if (values.m_divisi_id) params.m_divisi_id = values.m_divisi_id
    if (values.m_dept_id) params.m_dept_id = values.m_dept_id
    if (values.periode_gaji_id) params.periode_gaji_id = values.periode_gaji_id

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
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || 'Gagal mengambil data perhitungan gaji')
      } else if (res.status === 500) {
        const responseJson = await res.json().catch(() => ({}))
        throw (responseJson.message || 'Terjadi kesalahan server saat mengambil data perhitungan gaji.')
      } else {
        throw ('Gagal mengambil data perhitungan gaji')
      }
    }

    const result = await res.json()
    const resultData = result.data || []

    let filteredData = Array.isArray(resultData) ? [...resultData] : []

    // Filter sisi client jika tipe periode gaji dipilih (Bulanan / Mingguan)
    if (values.periode_gaji_id) {
      const selectedId = String(values.periode_gaji_id)
      const selectedText = String(tipePeriodeText.value || '').toUpperCase()
      filteredData = filteredData.filter((item) => {
        const itemPeriodeId = String(item.periode_id || item['m_kary.periode_gaji_id'] || '')
        const itemPeriodeText = String(item.periode_text || item['periode.value'] || '').toUpperCase()
        if (selectedId && itemPeriodeId && itemPeriodeId === selectedId) return true
        if (selectedText && itemPeriodeText && (itemPeriodeText.includes(selectedText) || selectedText.includes(itemPeriodeText))) return true
        if (!itemPeriodeId && !itemPeriodeText) return true
        return false
      })
    }

    // Integrasi: Tarik data perhitungan lembur untuk memperbarui komponen lembur karyawan terkait
    try {
      const lemburURL = `${store.server.url_backend}/operation/t_perhitungan_lembur`
      const lemburParams = {
        scopes: 'GenerateForFinal',
        periode_awal: tglAwalFormatted,
        periode_akhir: tglAkhirFormatted,
        paginate: 9999
      }
      if (values.m_dir_id) lemburParams.m_dir_id = values.m_dir_id
      if (values.m_divisi_id) lemburParams.m_divisi_id = values.m_divisi_id
      if (values.m_dept_id) lemburParams.m_dept_id = values.m_dept_id

      const resLembur = await fetch(lemburURL + '?' + new URLSearchParams(lemburParams), {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`,
        }
      })
      if (resLembur.ok) {
        const jsonLembur = await resLembur.json()
        const dataLembur = jsonLembur.data || []
        if (dataLembur.length) {
          const lemburMap = {}
          dataLembur.forEach((l) => {
            const kId = l.m_kary_id
            lemburMap[kId] = (lemburMap[kId] || 0) + Number(l.total_lembur || 0)
          })

          filteredData.forEach((item) => {
            const nominalLembur = lemburMap[item.m_kary_id] || 0
            if (nominalLembur > 0) {
              if (typeof item.detail_gaji === 'string') {
                try { item.detail_gaji = JSON.parse(item.detail_gaji) } catch (e) { item.detail_gaji = [] }
              }
              if (!Array.isArray(item.detail_gaji)) item.detail_gaji = []

              const existingIdx = item.detail_gaji.findIndex((c) => c.label === 'Upah Lembur')
              if (existingIdx >= 0) {
                item.detail_gaji[existingIdx].value = nominalLembur
              } else {
                item.detail_gaji.push({
                  label: 'Upah Lembur',
                  factor: '+',
                  value: nominalLembur,
                  type: 'Lembur',
                  can_adjust: 1
                })
              }
              item.total_gaji = Number(item.total_gaji || 0) + nominalLembur
              item.netto = Number(item.netto || 0) + nominalLembur
            }
          })
        }
      }
    } catch (e) {
      console.warn('Perhitungan lembur tidak ditemukan atau gagal dimuat:', e)
    }

    if (!filteredData.length) {
      return swal.fire({
        icon: 'warning',
        text: 'Tidak ditemukan data Perhitungan Gaji untuk periode dan filter yang dipilih.'
      })
    }

    filteredData.forEach((item) => {
      item._id = ++_id
      item.t_perhitungan_gaji_id = item.t_perhitungan_gaji_id !== undefined ? item.t_perhitungan_gaji_id : item.id
      item.karyawan = item['m_kary.nama_lengkap'] || item.karyawan
      item.deskripsi = item['deskripsi'] || ''
      if (typeof item.detail_gaji === 'string') {
        try { item.detail_gaji = JSON.parse(item.detail_gaji) } catch (e) {}
      }
    })

    detailArr.value = filteredData
    summaryPengeluaranGaji()
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err?.message || err || 'Terjadi kesalahan'
    })
  } finally {
    isRequesting.value = false
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
  const taxItem = totalAdjPPHOpen.value.length ? (totalAdjPPHOpen.value.find(x => x.factor === '-') || totalAdjPPHOpen.value[0]) : null
  detailArr.value[idxOpen.value]['total_tax'] = taxItem ? Number(taxItem.value ?? 0) : 0
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
  delete dataSave['periode_gaji_id']
  
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
      t_perhitungan_gaji_id: isTipeLemburSaja() ? null : (v.t_perhitungan_gaji_id || (isCreating ? v.id : null)),
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

      if (initialValues.periode_awal) {
        values.periode_awal = formatDateDisplay(initialValues.periode_awal)
      }
      if (initialValues.periode_akhir) {
        values.periode_akhir = formatDateDisplay(initialValues.periode_akhir)
      }
    }
  })
}

onBeforeMount(async () => {
  await loadPeriodeGajiOptions()
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