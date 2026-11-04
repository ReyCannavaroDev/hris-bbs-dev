import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, computed, watch, nextTick } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isRead = route.params.id && route.params.id !== 'create'
const actionText = ref(route.params.id === 'create' ? 'Tambah' : route.query.action)
const isProfile = ref(route.query.profile ? true : false)
const isBadForm = ref(false)
const isRequesting = ref(false)
const modulPath = route.params.modul
const currentMenu = store.currentMenu
const apiTable = ref(null)
const formErrors = ref({})
const formErrorsPend = ref({})
const formErrorsKel = ref({})
const formErrorsPel = ref({})
const formErrorsPres = ref({})
const formErrorsOrg = ref({})
const formErrorsBhs = ref({})
const formErrorsPK = ref({})
const activeTabIndex = ref(0)
const tsId = `ts=` + (Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/m_kary'
onBeforeMount(() => {
  document.title = 'Master Karyawan'
})

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

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []
const thisYear = new Date().getFullYear()
let tempKTP = ''
let tempBPJS = ''
let tempNPWP = ''
let tempKK = ''
let tempPasfoto = ''

const setStandartGaji = async () => {
  if (values.m_zona_id && values.grading_id) {
    const fixedParams = new URLSearchParams({ simplest: true, where: `this.is_active='true' AND this.m_zona_id=${values.m_zona_id ?? 0} AND this.grading_id=${values.grading_id ?? 0}` })
    const res = await fetch(`${store.server.url_backend}/operation/m_standart_gaji` + '?' + fixedParams, {
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
    })
    if (!res.ok) throw new Error("Failed when trying to read data")
    const resultJson = await res.json()
    const data = resultJson.data
    console.log(data, 'halo')
    if (initialValues.m_standart_gaji_id === values.m_standart_gaji_id) {
      values.m_standart_gaji_id = initialValues.m_standart_gaji_id
    } else {
      values.m_standart_gaji_id = data[0]?.id
    }

    // Auto-populate gaji_pokok & gaji_acuan jika belum diisi atau 0
    if (data && data.length > 0 && data[0]?.gaji_pokok) {
      if (values.gaji_pokok === null || values.gaji_pokok === undefined || values.gaji_pokok === '' || Number(values.gaji_pokok) === 0) {
        values.gaji_pokok = data[0].gaji_pokok
      }
      if (values.gaji_acuan === null || values.gaji_acuan === undefined || values.gaji_acuan === '' || Number(values.gaji_acuan) === 0) {
        values.gaji_acuan = values.gaji_pokok || data[0].gaji_pokok
      }
    }
  }
}

const onStandartGajiSelect = (item) => {
  if (item && item.gaji_pokok) {
    if (values.gaji_pokok === null || values.gaji_pokok === undefined || values.gaji_pokok === '' || Number(values.gaji_pokok) === 0) {
      values.gaji_pokok = item.gaji_pokok
    }
    if (values.gaji_acuan === null || values.gaji_acuan === undefined || values.gaji_acuan === '' || Number(values.gaji_acuan) === 0) {
      values.gaji_acuan = values.gaji_pokok || item.gaji_pokok
    }
  }
}

const values = reactive({
  is_active: 1,
  can_outscope: false,
  gaji_pokok: null,
  gaji_acuan: null,
  m_comp_id: null,
  m_dir_id: null,
  direktorat: store.user.data?.direktorat,
  username: null,
  tgl_masuk: null,
  tgl_berhenti: null,
  cuti_p24: 0,
  cuti_reguler: 0,
  cuti_masa_kerja: 0,
  cuti_p24_terpakai: 0,
  sisa_cuti_reguler: 0,
  sisa_cuti_masa_kerja: 0,
  cuti_1_hari: 0,
  cuti_satu_hari_terpakai: 0,
  sisa_cuti_1_hari: 0,
  cuti_reguler_terpakai: 0,
  p24_total_jam: 0,
  p24_konversi_hari: 0,
})

// --- SINKRONISASI REAKTIF DUA ARAH (TWO-WAY TRIGGER) RESIGN & INACTIVE ---
const isInitialLoaded = ref(false)
let isSyncingStatusResign = false

const getTodayDate = () => {
  return formatDateDisplay(new Date())
}

const onChangeStatusKaryawan = (val) => {
  values.is_active = (val === 1 || val === true || val === '1' || val === 'true') ? 1 : 0
  if (!isInitialLoaded.value || isSyncingStatusResign) return
  isSyncingStatusResign = true
  const isActive = (values.is_active === 1)
  if (!isActive) {
    if (!values.tgl_berhenti) {
      values.tgl_berhenti = getTodayDate()
    }
  } else {
    if (values.tgl_berhenti) {
      values.tgl_berhenti = null
    }
  }
  nextTick(() => { isSyncingStatusResign = false })
}

const onChangeTanggalBerhenti = (val) => {
  values.tgl_berhenti = val ? formatDateDisplay(val) : null
  if (!isInitialLoaded.value || isSyncingStatusResign) return
  isSyncingStatusResign = true
  if (values.tgl_berhenti) {
    values.is_active = 0
  } else {
    values.is_active = 1
  }
  nextTick(() => { isSyncingStatusResign = false })
}

watch(() => values.tgl_berhenti, (newVal, oldVal) => {
  if (!isInitialLoaded.value || isSyncingStatusResign) return
  isSyncingStatusResign = true
  if (newVal) {
    if (values.is_active !== 0) {
      values.is_active = 0
    }
  } else if (oldVal && !newVal) {
    if (values.is_active === 0) {
      values.is_active = 1
    }
  }
  nextTick(() => { isSyncingStatusResign = false })
})

watch(() => values.is_active, (newVal) => {
  if (!isInitialLoaded.value || isSyncingStatusResign) return
  isSyncingStatusResign = true
  const isActive = (newVal === 1 || newVal === true || newVal === '1' || newVal === 'true')
  if (!isActive) {
    if (!values.tgl_berhenti) {
      values.tgl_berhenti = getTodayDate()
    }
  } else {
    if (values.tgl_berhenti) {
      values.tgl_berhenti = null
    }
  }
  nextTick(() => { isSyncingStatusResign = false })
})

const valuesPendidikan = reactive({
  tingkat_id: null,
  thn_masuk: thisYear,
  nama_sekolah: null,
  thn_lulus: thisYear,
  kota_id: null,
  nilai: null,
  jurusan: null,
  is_pend_terakhir: null,
  desc: null,
  ijazah_foto: null
})

const valuesKeluarga = reactive({
  keluarga_id: null,
  nama: null,
  pend_terakhir_id: null,
  pekerjaan_id: null,
  jk_id: null,
  usia: null,
  desc: null
})

const valuesPelatihan = reactive({
  nama_pel: null,
  tahun: thisYear,
  nama_lem: null,
  kota_id: null
})

const valuesPrestasi = reactive({
  tingkat_pres_id: null,
  tahun: thisYear,
  nama_pres: null
})

const valuesOrganisasi = reactive({
  nama: null,
  tahun: thisYear,
  jenis_org_id: null,
  kota_id: null,
  posisi: null
})

const valuesBahasa = reactive({
  bhs_dikuasai: null,
  nilai_lisan: null,
  nilai_tertulis: null
})

const valuesPengalaman = reactive({
  instansi: null,
  thn_masuk: thisYear,
  thn_keluar: thisYear,
  kota_id: null,
  alamat_kantor: null,
  surat_referensi: null,
  bidang_usaha: null,
  no_tlp: null,
  posisi: null
})

const detailArr_i_cuti = ref([])

onBeforeMount(async () => {
  if (isRead) {
    //  READ DATA
    try {
      const editedId = route.params.id
      const dataURL = `${store.server.url_backend}/operation${endpointApi}/${editedId}`
      isRequesting.value = true

      const params = { transform: false, detail: true }
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

      console.log(initialValues.info_cuti)

      if (initialValues.info_cuti) {
        detailArr_i_cuti.value.push(initialValues.info_cuti)
        console.log('detailCuti', detailArr_i_cuti.value)

        values.cuti_1_hari = initialValues.info_cuti.cuti_reguler ?? initialValues.cuti_reguler ?? initialValues.info_cuti.cuti_satu_hari ?? initialValues.info_cuti.cuti_1_hari ?? 0
        values.cuti_satu_hari_terpakai = initialValues.info_cuti.cuti_reguler_terpakai ?? initialValues.cuti_reguler_terpakai ?? initialValues.info_cuti.cuti_satu_hari_terpakai ?? 0
        values.sisa_cuti_1_hari = initialValues.info_cuti.sisa_cuti_reguler ?? initialValues.sisa_cuti_reguler ?? initialValues.info_cuti.sisa_cuti_satu_hari ?? initialValues.info_cuti.sisa_cuti_1_hari ?? 0
        values.p24_total_jam = initialValues.info_cuti.p24_total_jam ?? initialValues.p24_total_jam ?? 0
        values.p24_konversi_hari = initialValues.info_cuti.p24_konversi_hari ?? initialValues.p24_konversi_hari ?? 0
        values.masa_kerja = initialValues.info_cuti.masa_kerja
        values.remaining_days = initialValues.info_cuti.remaining_days
      }

      // normalize is_active (biar aman)
      if (typeof initialValues.is_active !== 'undefined') {
        initialValues.is_active = (initialValues.is_active === true || initialValues.is_active === 1 || initialValues.is_active === 'true') ? 1 : 0
      }

      // if (initialValues['tipe_jam_kerja.value'] == 'OFFICE') {
      //   getJadwalKerjaOffice()
      // }

      initialValues['m_kary_det_pend']?.forEach(async (item) => {
        const res = await fetch(`${store.server.url_backend}/operation/m_general/${item.tingkat_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res.ok) throw new Error("Failed when trying to read data")
        const resultJson = await res.json()
        const tempinitialValues = resultJson.data
        item.tingkat = tempinitialValues.value
        item._id = ++_idPend
        item.is_pend_terakhir ? item.is_pend_terakhir = 1 : item.is_pend_terakhir = 0
        detailPendidikan.value.push(item)
      })

      initialValues['m_kary_det_bhs']?.forEach((item) => {
        item._id = ++_idBhs
        detailBahasa.value.push(item)
      })

      initialValues['m_kary_det_kel']?.forEach(async (item) => {
        const res = await fetch(`${store.server.url_backend}/operation/m_general/${item.keluarga_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res.ok) throw new Error("Failed when trying to read data")
        const resultJson = await res.json()
        const tempinitialValues = resultJson.data

        const res2 = await fetch(`${store.server.url_backend}/operation/m_general/${item.pend_terakhir_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res2.ok) throw new Error("Failed when trying to read data")
        const resultJson2 = await res2.json()
        const tempinitialValues2 = resultJson2.data

        const res3 = await fetch(`${store.server.url_backend}/operation/m_general/${item.jk_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res3.ok) throw new Error("Failed when trying to read data")
        const resultJson3 = await res3.json()
        const tempinitialValues3 = resultJson3.data

        const res4 = await fetch(`${store.server.url_backend}/operation/m_general/${item.pekerjaan_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res4.ok) throw new Error("Failed when trying to read data")
        const resultJson4 = await res4.json()
        const tempinitialValues4 = resultJson4.data

        item.jk = tempinitialValues3.value
        item.keluarga = tempinitialValues.value
        item.pendidikan = tempinitialValues2.value
        item.pekerjaan = tempinitialValues4.value
        item._id = ++_idKel
        detailKeluarga.value.push(item)
      })

      initialValues['m_kary_det_pk']?.forEach((item) => {
        item._id = ++_idPk
        detailPengalaman.value.push(item)
      })

      initialValues['m_kary_det_org']?.forEach(async (item) => {
        const res = await fetch(`${store.server.url_backend}/operation/m_general/${item.jenis_org_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res.ok) throw new Error("Failed when trying to read data")
        const resultJson = await res.json()
        const tempinitialValues = resultJson.data

        const res2 = await fetch(`${store.server.url_backend}/operation/m_general/${item.kota_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res2.ok) throw new Error("Failed when trying to read data")
        const resultJson2 = await res2.json()
        const tempinitialValues2 = resultJson2.data

        item.kota = tempinitialValues2.value
        item.jenis = tempinitialValues.value
        item._id = ++_idOrg
        detailOrganisasi.value.push(item)
      })

      initialValues['m_kary_det_pres']?.forEach(async (item) => {
        const res = await fetch(`${store.server.url_backend}/operation/m_general/${item.tingkat_pres_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res.ok) throw new Error("Failed when trying to read data")
        const resultJson = await res.json()
        const tempinitialValues = resultJson.data
        item.tingkat = tempinitialValues.value
        item._id = ++_idPres
        detailPrestasi.value.push(item)
      })

      initialValues['m_kary_det_pel']?.forEach(async (item) => {
        const res = await fetch(`${store.server.url_backend}/operation/m_general/${item.kota_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!res.ok) throw new Error("Failed when trying to read data")
        const resultJson = await res.json()
        const tempinitialValues = resultJson.data
        item.kota = tempinitialValues.value
        item._id = ++_idPel
        detailPelatihan.value.push(item)
      })

      if (initialValues.info_cuti) {
        for (let key in initialValues.info_cuti) {
          if (initialValues.info_cuti.hasOwnProperty(key) && initialValues.info_cuti[key] === null) {
            initialValues.info_cuti[key] = 0
          }
        }
      }
      initialValues = { ...initialValues, ...initialValues.info_cuti }
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

  // Format all date values to DD/MM/YYYY for UI display
  if (values.tgl_masuk) values.tgl_masuk = formatDateDisplay(values.tgl_masuk)
  if (values.tgl_berhenti) values.tgl_berhenti = formatDateDisplay(values.tgl_berhenti)
  if (values.tgl_lahir) values.tgl_lahir = formatDateDisplay(values.tgl_lahir)
  if (values.npwp_tgl_berlaku) values.npwp_tgl_berlaku = formatDateDisplay(values.npwp_tgl_berlaku)
  if (values.lama_kontrak_awal) values.lama_kontrak_awal = formatDateDisplay(values.lama_kontrak_awal)
  if (values.lama_kontrak_akhir) values.lama_kontrak_akhir = formatDateDisplay(values.lama_kontrak_akhir)

  if (!isRead) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
    values.m_dir_id = store.user.data?.m_dir_id ?? null
    isInitialLoaded.value = true
  } else if (!values.m_comp_id) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
  }

  if (values.m_kary_det_pemb?.length > 0) {
    values.periode_gaji_id = values.m_kary_det_pemb[0].periode_gaji_id
    values.metode_id = values.m_kary_det_pemb[0].metode_id
    values.tipe_id = values.m_kary_det_pemb[0].tipe_id
    values.bank_id = values.m_kary_det_pemb[0].bank_id
    values.atas_nama_rek = values.m_kary_det_pemb[0].atas_nama_rek
    values.no_rek = values.m_kary_det_pemb[0].no_rek
  }

  if (values.m_kary_det_kartu?.length > 0) {
    values.ktp_no = values.m_kary_det_kartu[0].ktp_no
    values.nik = values.ktp_no || initialValues.nik || ''
    values.kk_no = values.m_kary_det_kartu[0].kk_no
    values.npwp_no = values.m_kary_det_kartu[0].npwp_no
    values.npwp_tgl_berlaku = values.m_kary_det_kartu[0].npwp_tgl_berlaku
    values.bpjs_tipe_id = values.m_kary_det_kartu[0].bpjs_tipe_id
    // values.bpjs_no = values.m_kary_det_kartu[0].bpjs_no
    values.bpjs_no_kesehatan = values.m_kary_det_kartu[0].bpjs_no_kesehatan
    values.bpjs_no_ketenagakerjaan = values.m_kary_det_kartu[0].bpjs_no_ketenagakerjaan
    values.desc_file = values.m_kary_det_kartu[0].desc_file
    values.berkas_lain = values.m_kary_det_kartu[0].berkas_lain
    // urlBPJSFoto.value = values.m_kary_det_kartu[0].bpjs_foto
    urlPasFoto.value = values.m_kary_det_kartu[0].pas_foto
    urlKKFoto.value = values.m_kary_det_kartu[0].kk_foto
    urlKTPFoto.value = values.m_kary_det_kartu[0].ktp_foto
    urlNPWPFoto.value = values.m_kary_det_kartu[0].npwp_foto

    tempBPJS = values.m_kary_det_kartu[0].bpjs_foto
    tempNPWP = values.m_kary_det_kartu[0].npwp_foto
    tempPasfoto = values.m_kary_det_kartu[0].pas_foto
    tempKTP = values.m_kary_det_kartu[0].ktp_foto
    tempKK = values.m_kary_det_kartu[0].kk_foto

    if (actionText.value === 'Copy') {
      urlPasFoto.value = ''
      urlKKFoto.value = ''
      urlKTPFoto.value = ''
      urlNPWPFoto.value = ''
      urlBPJSFoto.value = ''
      tempBPJS = null
      tempNPWP = null
      tempPasfoto = null
      tempKTP = null
      tempKK = null
      values.m_kary_det_kartu[0].pas_foto = null
      values.m_kary_det_kartu[0].ktp_foto = null
      values.m_kary_det_kartu[0].kk_foto = null
      values.m_kary_det_kartu[0].npwp_foto = null
      values.m_kary_det_kartu[0].bpjs_foto = null
    }
  }

  setTimeout(() => {
    isInitialLoaded.value = true
  }, 200)
})

async function getJadwalKerjaOffice() {
  try {
    const response = await fetch(`${store.server.url_backend}/operation/t_jadwal_kerja/get_jadwal_office`, {
      method: 'GET',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
    })

    if (!response.ok) {
      throw new Error('Coba kembali nanti')
    }

    const data = await response.json()
    console.log(data)
    console.log(data?.data?.id)
    values.t_jadwal_kerja_id = data?.data?.id
    values.t_jadwal_kerja_ket = data?.data?.keterangan
  } catch (error) {
    console.error('Error fetching tunjangan kemahalan:', error)
  }
}

const changeTipeJamKerja = (v) => {
  values['tipe_jam_kerja.value'] = v?.value
  // Tidak memaksakan autofill getJadwalKerjaOffice agar user leluasa memilih jadwal kerja
}

// preview image
const refPasFoto = ref()
const urlPasFoto = ref('')
const refKTPFoto = ref()
const urlKTPFoto = ref('')
const urlKKFoto = ref('')
const urlNPWPFoto = ref('')
const urlBPJSFoto = ref('')
const urlImg = ref('')

async function imageChange(e) {
  const file = e.target.files
  if (file[0]) {
    const maxAllowedSize = 1 * 1024 * 1024
    if (file[0].size >= maxAllowedSize) {
      swal.fire({
        icon: 'error',
        text: 'Error: File terlalu besar, max 1MB'
      })
      e.target.value = null
      return
    }
    if (e.target.id === 'inputPasFoto') {
      var formData = new FormData()
      formData.append('file', file[0])
      const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_kartu/upload` + '?' + `field=pas_foto`, {
        method: 'POST',
        headers: {
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
        body: formData
      })
      if (res.ok) {
        tempPasfoto = file[0].name
        urlPasFoto.value = URL.createObjectURL(file[0])
      }
    }
    else if (e.target.id === 'inputKTPFoto') {
      var formData = new FormData()
      formData.append('file', file[0])
      const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_kartu/upload` + '?' + `field=ktp_foto`, {
        method: 'POST',
        headers: {
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
        body: formData
      })
      if (res.ok) {
        tempKTP = file[0].name
        urlKTPFoto.value = URL.createObjectURL(file[0])
      }
    }
    else if (e.target.id === 'inputKKFoto') {
      var formData = new FormData()
      formData.append('file', file[0])
      const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_kartu/upload` + '?' + `field=kk_foto`, {
        method: 'POST',
        headers: {
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
        body: formData
      })
      if (res.ok) {
        tempKK = file[0].name
        urlKKFoto.value = URL.createObjectURL(file[0])
      }
    }
    else if (e.target.id === 'inputNPWPFoto') {
      var formData = new FormData()
      formData.append('file', file[0])
      const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_kartu/upload` + '?' + `field=npwp_foto`, {
        method: 'POST',
        headers: {
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
        body: formData
      })
      if (res.ok) {
        tempNPWP = file[0].name
        urlNPWPFoto.value = URL.createObjectURL(file[0])
      }
    }
    else if (e.target.id === 'inputBPJSFoto') {
      var formData = new FormData()
      formData.append('file', file[0])
      const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_kartu/upload` + '?' + `field=bpjs_foto`, {
        method: 'POST',
        headers: {
          Authorization: `${store.user.token_type} ${store.user.token}`
        },
        body: formData
      })
      if (res.ok) {
        tempBPJS = file[0].name
        urlBPJSFoto.value = URL.createObjectURL(file[0])
      }
    }
  }
}

let ArrTahun = []
for (let i = thisYear; i >= 1973; i--) {
  ArrTahun.push(i)
}

// Helper Preview File
const previewFile = (fileName, folder = '') => {
  if (!fileName) return
  if (fileName.startsWith('http://') || fileName.startsWith('https://') || fileName.startsWith('blob:')) {
    window.open(fileName, '_blank')
  } else {
    window.open(`${store.server.url_backend}/uploads/${folder}/${fileName}`, '_blank')
  }
}
window.previewFileKaryawan = previewFile

// Pendidikan
let _idPend = 0
const detailPendidikan = ref([])
const fileIjz = ref(null)

async function fileIjazah(e) {
  const file = e.target.files
  if (file[0]) {
    const maxAllowedSize = 1 * 1024 * 1024
    if (file[0].size >= maxAllowedSize) {
      swal.fire({
        icon: 'error',
        text: 'File terlalu besar, max 1MB'
      })
      e.target.value = null
      return
    }
    valuesPendidikan.ijazah_url = URL.createObjectURL(file[0])
    var formData = new FormData()
    formData.append('file', file[0])
    const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_pend/upload` + '?' + `field=ijazah_foto`, {
      method: 'POST',
      headers: {
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: formData
    })
    if (res.ok) {
      const resJson = await res.json().catch(() => ({}))
      valuesPendidikan.ijazah_foto = resJson.data || resJson.filename || file[0].name
    } else {
      valuesPendidikan.ijazah_foto = file[0].name
    }
  }
}

// 1. Pendidikan
const addPendidikan = async () => {
  const tempObj = {}
  if (!valuesPendidikan.tingkat_id) tempObj.tingkat_id = ['Tingkat wajib dipilih']
  if (!valuesPendidikan.nama_sekolah) tempObj.nama_sekolah = ['Nama Sekolah wajib diisi']
  if (!valuesPendidikan.thn_masuk) tempObj.thn_masuk = ['Tahun Masuk wajib dipilih']
  if (!valuesPendidikan.thn_lulus) tempObj.thn_lulus = ['Tahun Lulus wajib dipilih']

  if (Object.keys(tempObj).length >= 1) {
    formErrorsPend.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field wajib pendidikan yang belum terisi'
    })
    return
  }

  const payloadPend = { ...valuesPendidikan }
  if (payloadPend._id) {
    const idx = detailPendidikan.value.findIndex(e => e._id === payloadPend._id)
    if (idx !== -1) {
      detailPendidikan.value[idx] = payloadPend
      detailPendidikan.value = [...detailPendidikan.value]
    } else {
      detailPendidikan.value = [...detailPendidikan.value, payloadPend]
    }
  } else {
    payloadPend._id = ++_idPend
    detailPendidikan.value = [...detailPendidikan.value, payloadPend]
  }

  Object.keys(valuesPendidikan).forEach(key => valuesPendidikan[key] = null)
  if (fileIjz.value) fileIjz.value.value = null
  formErrorsPend.value = {}
  valuesPendidikan.thn_masuk = thisYear
  valuesPendidikan.thn_lulus = thisYear
}

const onEditPendidikan = (row) => {
  for (const k in valuesPendidikan) {
    if (k in row) valuesPendidikan[k] = row[k]
  }
}

const onDeletePendidikan = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data pendidikan ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailPendidikan.value = detailPendidikan.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// 2. Keluarga
let _idKel = 0
const detailKeluarga = ref([])

const addKeluarga = async () => {
  const tempObj = {}
  if (!valuesKeluarga.keluarga_id) tempObj.keluarga_id = ['Keluarga wajib dipilih']
  if (!valuesKeluarga.nama) tempObj.nama = ['Nama anggota keluarga wajib diisi']

  if (Object.keys(tempObj).length >= 1) {
    formErrorsKel.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field keluarga yang belum terisi'
    })
    return
  }

  const payloadKel = { ...valuesKeluarga }
  if (payloadKel._id) {
    const idx = detailKeluarga.value.findIndex(e => e._id === payloadKel._id)
    if (idx !== -1) {
      detailKeluarga.value[idx] = payloadKel
      detailKeluarga.value = [...detailKeluarga.value]
    } else {
      detailKeluarga.value = [...detailKeluarga.value, payloadKel]
    }
  } else {
    payloadKel._id = ++_idKel
    detailKeluarga.value = [...detailKeluarga.value, payloadKel]
  }

  Object.keys(valuesKeluarga).forEach(key => valuesKeluarga[key] = null)
  formErrorsKel.value = {}
}

const onEditKeluarga = (row) => {
  for (const k in valuesKeluarga) {
    if (k in row) valuesKeluarga[k] = row[k]
  }
}

const onDeleteKeluarga = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data keluarga ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailKeluarga.value = detailKeluarga.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// 3. Pelatihan
let _idPel = 0
const detailPelatihan = ref([])

const addPelatihan = async () => {
  const tempObj = {}
  if (!valuesPelatihan.nama_pel) tempObj.nama_pel = ['Nama Pelatihan wajib diisi']
  if (!valuesPelatihan.tahun) tempObj.tahun = ['Tahun wajib dipilih']

  if (Object.keys(tempObj).length >= 1) {
    formErrorsPel.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field pelatihan yang belum terisi'
    })
    return
  }

  const payloadPel = { ...valuesPelatihan }
  if (payloadPel._id) {
    const idx = detailPelatihan.value.findIndex(e => e._id === payloadPel._id)
    if (idx !== -1) {
      detailPelatihan.value[idx] = payloadPel
      detailPelatihan.value = [...detailPelatihan.value]
    } else {
      detailPelatihan.value = [...detailPelatihan.value, payloadPel]
    }
  } else {
    payloadPel._id = ++_idPel
    detailPelatihan.value = [...detailPelatihan.value, payloadPel]
  }

  Object.keys(valuesPelatihan).forEach(key => valuesPelatihan[key] = null)
  formErrorsPel.value = {}
  valuesPelatihan.tahun = thisYear
}

const onEditPelatihan = (row) => {
  for (const k in valuesPelatihan) {
    if (k in row) valuesPelatihan[k] = row[k]
  }
}

const onDeletePelatihan = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data pelatihan ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailPelatihan.value = detailPelatihan.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// 4. Prestasi
let _idPres = 0
const detailPrestasi = ref([])

const addPrestasi = async () => {
  const tempObj = {}
  if (!valuesPrestasi.nama_pres) tempObj.nama_pres = ['Nama Prestasi wajib diisi']
  if (!valuesPrestasi.tahun) tempObj.tahun = ['Tahun wajib dipilih']

  if (Object.keys(tempObj).length >= 1) {
    formErrorsPres.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field prestasi yang belum terisi'
    })
    return
  }

  const payloadPres = { ...valuesPrestasi }
  if (payloadPres._id) {
    const idx = detailPrestasi.value.findIndex(e => e._id === payloadPres._id)
    if (idx !== -1) {
      detailPrestasi.value[idx] = payloadPres
      detailPrestasi.value = [...detailPrestasi.value]
    } else {
      detailPrestasi.value = [...detailPrestasi.value, payloadPres]
    }
  } else {
    payloadPres._id = ++_idPres
    detailPrestasi.value = [...detailPrestasi.value, payloadPres]
  }

  Object.keys(valuesPrestasi).forEach(key => valuesPrestasi[key] = null)
  formErrorsPres.value = {}
  valuesPrestasi.tahun = thisYear
}

const onEditPrestasi = (row) => {
  for (const k in valuesPrestasi) {
    if (k in row) valuesPrestasi[k] = row[k]
  }
}

const onDeletePrestasi = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data prestasi ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailPrestasi.value = detailPrestasi.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// 5. Organisasi
let _idOrg = 0
const detailOrganisasi = ref([])

const addOrganisasi = async () => {
  const tempObj = {}
  if (!valuesOrganisasi.nama) tempObj.nama = ['Nama Organisasi wajib diisi']
  if (!valuesOrganisasi.tahun) tempObj.tahun = ['Tahun wajib dipilih']

  if (Object.keys(tempObj).length >= 1) {
    formErrorsOrg.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field organisasi yang belum terisi'
    })
    return
  }

  const payloadOrg = { ...valuesOrganisasi }
  if (payloadOrg._id) {
    const idx = detailOrganisasi.value.findIndex(e => e._id === payloadOrg._id)
    if (idx !== -1) {
      detailOrganisasi.value[idx] = payloadOrg
      detailOrganisasi.value = [...detailOrganisasi.value]
    } else {
      detailOrganisasi.value = [...detailOrganisasi.value, payloadOrg]
    }
  } else {
    payloadOrg._id = ++_idOrg
    detailOrganisasi.value = [...detailOrganisasi.value, payloadOrg]
  }

  Object.keys(valuesOrganisasi).forEach(key => valuesOrganisasi[key] = null)
  formErrorsOrg.value = {}
  valuesOrganisasi.tahun = thisYear
}

const onEditOrganisasi = (row) => {
  for (const k in valuesOrganisasi) {
    if (k in row) valuesOrganisasi[k] = row[k]
  }
}

const onDeleteOrganisasi = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data organisasi ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailOrganisasi.value = detailOrganisasi.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// 6. Bahasa
let _idBhs = 0
const detailBahasa = ref([])

const addBahasa = async () => {
  const tempObj = {}
  if (!valuesBahasa.bhs_dikuasai) tempObj.bhs_dikuasai = ['Bahasa wajib diisi']

  if (Object.keys(tempObj).length >= 1) {
    formErrorsBhs.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field bahasa yang belum terisi'
    })
    return
  }

  const payloadBhs = { ...valuesBahasa }
  if (payloadBhs._id) {
    const idx = detailBahasa.value.findIndex(e => e._id === payloadBhs._id)
    if (idx !== -1) {
      detailBahasa.value[idx] = payloadBhs
      detailBahasa.value = [...detailBahasa.value]
    } else {
      detailBahasa.value = [...detailBahasa.value, payloadBhs]
    }
  } else {
    payloadBhs._id = ++_idBhs
    detailBahasa.value = [...detailBahasa.value, payloadBhs]
  }

  Object.keys(valuesBahasa).forEach(key => valuesBahasa[key] = null)
  formErrorsBhs.value = {}
}

const onEditBahasa = (row) => {
  for (const k in valuesBahasa) {
    if (k in row) valuesBahasa[k] = row[k]
  }
}

const onDeleteBahasa = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data bahasa ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailBahasa.value = detailBahasa.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// 7. Pengalaman Kerja
let _idPk = 0
const detailPengalaman = ref([])
const fileSurat = ref(null)

async function fileSrtRef(e) {
  const file = e.target.files
  if (file[0]) {
    const maxAllowedSize = 1 * 1024 * 1024
    if (file[0].size >= maxAllowedSize) {
      swal.fire({
        icon: 'error',
        text: 'File terlalu besar, max 1MB'
      })
      e.target.value = null
      return
    }
    valuesPengalaman.surat_referensi_url = URL.createObjectURL(file[0])
    var formData = new FormData()
    formData.append('file', file[0])
    const res = await fetch(`${store.server.url_backend}/operation/m_kary_det_pk/upload` + '?' + `field=surat_referensi`, {
      method: 'POST',
      headers: {
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: formData
    })
    if (res.ok) {
      const resJson = await res.json().catch(() => ({}))
      valuesPengalaman.surat_referensi = resJson.data || resJson.filename || file[0].name
    } else {
      valuesPengalaman.surat_referensi = file[0].name
    }
  }
}

const addPengalaman = async () => {
  const tempObj = {}
  if (!valuesPengalaman.instansi) tempObj.instansi = ['Nama Instansi wajib diisi']
  if (!valuesPengalaman.posisi) tempObj.posisi = ['Posisi wajib diisi']
  if (!valuesPengalaman.thn_masuk) tempObj.thn_masuk = ['Tahun Masuk wajib dipilih']
  if (!valuesPengalaman.thn_keluar) tempObj.thn_keluar = ['Tahun Keluar wajib dipilih']

  if (Object.keys(tempObj)?.length >= 1) {
    formErrorsPK.value = tempObj
    swal.fire({
      icon: 'error',
      text: 'Masih ada field wajib pengalaman kerja yang belum terisi'
    })
    return
  }

  const payloadPK = { ...valuesPengalaman }
  if (payloadPK._id) {
    const idx = detailPengalaman.value.findIndex(e => e._id === payloadPK._id)
    if (idx !== -1) {
      detailPengalaman.value[idx] = payloadPK
      detailPengalaman.value = [...detailPengalaman.value]
    } else {
      detailPengalaman.value = [...detailPengalaman.value, payloadPK]
    }
  } else {
    payloadPK._id = ++_idPk
    detailPengalaman.value = [...detailPengalaman.value, payloadPK]
  }

  Object.keys(valuesPengalaman).forEach(key => valuesPengalaman[key] = null)
  if (fileSurat.value) fileSurat.value.value = null
  formErrorsPK.value = {}
  valuesPengalaman.thn_masuk = thisYear
  valuesPengalaman.thn_keluar = thisYear
}

const onEditPengalaman = (row) => {
  for (const k in valuesPengalaman) {
    if (k in row) valuesPengalaman[k] = row[k]
  }
}

const onDeletePengalaman = (row, api) => {
  swal.fire({
    icon: 'warning',
    showDenyButton: true,
    text: 'Hapus baris data pengalaman kerja ini?',
    confirmButtonText: 'Ya, Hapus',
    denyButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) {
      detailPengalaman.value = detailPengalaman.value.filter(e => e._id !== row._id)
      if (api) api.applyTransaction({ remove: [row] })
    }
  })
}

// ================= FACE RECOGNITION (REVISI: 4 FOTO, HIT /register 4x) =================
const FACE_REGISTER_URL = 'https://server.qqltech.com:7089/register'

const faceRegLoading = ref(false)
const faceRegMessage = ref('')
const faceRegStatus = ref({
  is_registered: false,
  registered_count: 0,
  last_registered_at: null,
})

const faceFiles = ref([null, null, null, null])     // File slot 1..4
const facePreviews = ref(['', '', '', ''])          // Preview slot 1..4

const faceAllReady = computed(() => faceFiles.value.every(f => !!f))

// tombol aktif kalau: mode edit (sudah ada data) + 4 foto siap + tidak loading
const canRegisterFace = computed(() => isRead && faceAllReady.value && !faceRegLoading.value)

function onFaceFileChange(e, slot) {
  const files = e.target.files
  if (!files || !files[0]) return

  const file = files[0]
  const maxAllowedSize = 1 * 1024 * 1024
  if (file.size >= maxAllowedSize) {
    swal.fire({ icon: 'error', text: 'File terlalu besar, max 1MB' })
    e.target.value = null
    return
  }

  const idx = slot - 1
  faceFiles.value[idx] = file
  facePreviews.value[idx] = URL.createObjectURL(file)
}

function getEmployeeIdForFace() {
  return String(values.username || values.kode || values.id || route.params.id || '').trim()
}

function getEmployeeNameForFace() {
  // pastikan ada nama walau belum klik Simpan
  const full = String(values.nama_lengkap || '').trim()
  if (full) return full
  return `${values.nama_depan || ''} ${values.nama_belakang || ''}`.trim()
}

async function registerOnePhoto({ employeeId, name, file, slot }) {
  const fd = new FormData()
  fd.append('employee_id', employeeId)
  fd.append('name', name)
  fd.append('image', file, `face_${employeeId}_slot${slot}.jpg`)

  const res = await fetch(FACE_REGISTER_URL, { method: 'POST', body: fd })
  const js = await res.json().catch(() => null)

  if (!res.ok) throw (js?.detail || js?.message || `Register slot ${slot} gagal`)
  if (js?.status !== 'success') throw (js?.detail || js?.message || `Register slot ${slot} gagal`)
  return js
}

// ====== PERSIST STATUS (LOCALSTORAGE) ======
function faceCacheKey() {
  const id = getEmployeeIdForFace()
  return `face_reg_${id}`
}

function loadFaceRegCache() {
  try {
    const raw = localStorage.getItem(faceCacheKey())
    if (!raw) return
    const obj = JSON.parse(raw)
    faceRegStatus.value.is_registered = !!obj.is_registered
    faceRegStatus.value.registered_count = Number(obj.registered_count || 0)
    faceRegStatus.value.last_registered_at = obj.last_registered_at || null
  } catch (e) { }
}

function saveFaceRegCache() {
  const payload = {
    is_registered: faceRegStatus.value.is_registered,
    registered_count: faceRegStatus.value.registered_count,
    last_registered_at: faceRegStatus.value.last_registered_at,
  }
  localStorage.setItem(faceCacheKey(), JSON.stringify(payload))
}

// auto load setiap buka form / ganti karyawan
watchEffect(() => {
  if (isRead) loadFaceRegCache()
})
// ====== END PERSIST ======

const submitFaceRegistration = async () => {
  if (!isRead) {
    swal.fire({ icon: 'warning', text: 'Simpan data karyawan dulu sebelum registrasi wajah.' })
    return
  }
  if (!faceAllReady.value) {
    swal.fire({ icon: 'warning', text: 'Wajib upload 4 foto Face Recognition.' })
    return
  }

  const employeeId = getEmployeeIdForFace()
  const name = getEmployeeNameForFace()

  if (!employeeId) {
    swal.fire({ icon: 'error', text: 'Employee ID kosong. Pastikan Username atau NIK terisi.' })
    return
  }
  if (!name) {
    swal.fire({ icon: 'error', text: 'Nama kosong. Pastikan nama karyawan terisi.' })
    return
  }

  try {
    faceRegLoading.value = true
    faceRegMessage.value = 'Mendaftarkan 4 foto...'

    let ok = 0
    for (let i = 0; i < 4; i++) {
      await registerOnePhoto({ employeeId, name, file: faceFiles.value[i], slot: i + 1 })
      ok++
      faceRegMessage.value = `Berhasil daftar ${ok}/4 foto...`
    }

    faceRegStatus.value.is_registered = true
    faceRegStatus.value.registered_count = ok
    const now = new Date().toISOString()
    faceRegStatus.value.last_registered_at = now
    saveFaceRegCache()
    faceRegMessage.value = 'Registrasi wajah berhasil (4 foto).'

    swal.fire({ icon: 'success', text: faceRegMessage.value })
  } catch (err) {
    console.error(err)
    faceRegStatus.value.is_registered = false
    faceRegStatus.value.registered_count = 0
    faceRegMessage.value = typeof err === 'string' ? err : 'Registrasi wajah gagal'
    swal.fire({ icon: 'error', text: faceRegMessage.value })
  } finally {
    faceRegLoading.value = false
  }
}
// ================= END FACE RECOGNITION =================

function onBack() {
  let isChanged = false
  for (const key in initialValues) {
    if (values[key] !== initialValues[key]) {
      isChanged = true
      break
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
    formErrors.value = {}
    const errors = {}

    if (!values.m_dir_id) errors.m_dir_id = ['Company wajib dipilih']
    if (!values.m_divisi_id) errors.m_divisi_id = ['Divisi wajib dipilih']
    if (!values.m_dept_id) errors.m_dept_id = ['Departemen wajib dipilih']
    if (!values.m_posisi_id) errors.m_posisi_id = ['Posisi wajib dipilih']
    if (!values.m_zona_id) errors.m_zona_id = ['Zona wajib dipilih']
    if (!values.grading_id) errors.grading_id = ['Grading wajib dipilih']
    if (!values.m_standart_gaji_id) errors.m_standart_gaji_id = ['Standart Gaji wajib dipilih']
    if (!values.tipe_jam_kerja_id) errors.tipe_jam_kerja_id = ['Tipe Jam Kerja wajib dipilih']
    if (!values.costcentre_id && !values.costcontre_id) errors.costcontre_id = ['Costcentre wajib dipilih']
    if (!values.kode || String(values.kode).trim() === '') errors.kode = ['NIK wajib diisi']
    if (!values.nama_depan || String(values.nama_depan).trim() === '') errors.nama_depan = ['Nama Karyawan wajib diisi']
    if (!values.nama_panggilan || String(values.nama_panggilan).trim() === '') errors.nama_panggilan = ['Nama Panggilan wajib diisi']
    if (!values.jk_id) errors.jk_id = ['Jenis Kelamin wajib dipilih']
    if (!values.tempat_lahir) errors.tempat_lahir = ['Tempat Lahir wajib diisi']
    if (!values.tgl_lahir) errors.tgl_lahir = ['Tanggal Lahir wajib diisi']
    if (!values.alamat_asli && !values.alamat_domisili) errors.alamat_domisili = ['Alamat Tinggal wajib diisi']
    if (!values.provinsi_id) errors.provinsi_id = ['Provinsi wajib dipilih']
    if (!values.kota_id) errors.kota_id = ['Kota wajib dipilih']
    if (!values.kecamatan_id) errors.kecamatan_id = ['Kecamatan wajib dipilih']
    if (!values.kode_pos) errors.kode_pos = ['Kode Pos wajib diisi']
    if (!values.no_tlp) errors.no_tlp = ['No. Telepon wajib diisi']
    if (!values.no_darurat) errors.no_darurat = ['No. Telepon Darurat wajib diisi']
    if (!values.nama_kontak_darurat) errors.nama_kontak_darurat = ['Nama Kontak Darurat wajib diisi']
    if (!values.hub_dgn_karyawan) errors.hub_dgn_karyawan = ['Hubungan Dengan Karyawan wajib diisi']
    if (!values.agama_id) errors.agama_id = ['Agama wajib dipilih']
    if (!values.gol_darah_id) errors.gol_darah_id = ['Golongan Darah wajib dipilih']
    if (!values.status_nikah_id) errors.status_nikah_id = ['Status Pernikahan wajib dipilih']
    if (!values.status_kary_id) errors.status_kary_id = ['Status Karyawan wajib dipilih']
    if (!values.tgl_masuk) errors.tgl_masuk = ['Tanggal Masuk wajib diisi']

    if (Object.keys(errors).length > 0) {
      formErrors.value = errors
      activeTabIndex.value = 0
      swal.fire({
        icon: 'warning',
        title: 'Validasi Gagal',
        text: 'Mohon lengkapi field mandatory bertanda bintang (*) yang masih kosong'
      })
      return
    }

    values.nik = values.ktp_no || values.nik || ''
    values.nama_lengkap = (values.nama_depan ?? '') + ' ' + (values.nama_belakang ?? '')
    values.m_kary_det_pres = detailPrestasi.value

    if (values.periode_gaji_id) {
      values.m_kary_det_pemb = [{
        periode_gaji_id: values.periode_gaji_id,
        metode_id: values.metode_id,
        tipe_id: values.tipe_id,
        bank_id: values.bank_id,
        no_rek: values.no_rek,
        atas_nama_rek: values.atas_nama_rek,
        desc: values.desc,
        is_active: true, // FIX typo yang sebelumnya values.periode_gaji_idtrue
      }]
    }

    values.can_outscope = values.can_outscope ? 1 : 0
    values.m_kary_det_kel = detailKeluarga.value
    values.m_kary_det_org = detailOrganisasi.value
    values.m_kary_det_bhs = detailBahasa.value
    values.m_kary_det_pend = detailPendidikan.value
    values.m_kary_det_pel = detailPelatihan.value
    values.m_kary_det_pk = detailPengalaman.value

    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value)

    if (actionText.value === 'Copy') {
      if (tempKTP && (tempKTP.startsWith('http://') || tempKTP.startsWith('https://'))) tempKTP = null
      if (tempPasfoto && (tempPasfoto.startsWith('http://') || tempPasfoto.startsWith('https://'))) tempPasfoto = null
      if (tempKK && (tempKK.startsWith('http://') || tempKK.startsWith('https://'))) tempKK = null
      if (tempNPWP && (tempNPWP.startsWith('http://') || tempNPWP.startsWith('https://'))) tempNPWP = null
    }

    if (isCreating || values.m_kary_det_kartu?.length === 0) {
      values.m_kary_det_kartu = [{
        ktp_no: values.ktp_no,
        ktp_foto: tempKTP,
        pas_foto: tempPasfoto,
        kk_no: values.kk_no,
        kk_foto: tempKK,
        npwp_no: values.npwp_no,
        npwp_foto: tempNPWP,
        npwp_tgl_berlaku: values.npwp_tgl_berlaku,
        bpjs_tipe_id: values.bpjs_tipe_id,
        bpjs_no_kesehatan: values.bpjs_no_kesehatan,
        bpjs_no_ketenagakerjaan: values.bpjs_no_ketenagakerjaan,
        berkas_lain: values.berkas_lain,
        desc_file: values.desc_file,
        is_active: true
      }]
    } else {
      console.log(values.m_kary_det_kartu)
      values.m_kary_det_kartu[0].ktp_no = values.ktp_no
      if (initialValues.m_kary_det_kartu[0]?.ktp_foto !== tempKTP) {
        values.m_kary_det_kartu[0].ktp_foto = tempKTP
      }
      if (initialValues.m_kary_det_kartu[0]?.pas_foto !== tempPasfoto) {
        values.m_kary_det_kartu[0].pas_foto = tempPasfoto
      }
      values.m_kary_det_kartu[0].kk_no = values.kk_no
      if (initialValues.m_kary_det_kartu[0]?.kk_foto !== tempKK) {
        values.m_kary_det_kartu[0].kk_foto = tempKK
      }
      values.m_kary_det_kartu[0].npwp_no = values.npwp_no
      values.m_kary_det_kartu[0].npwp_tgl_berlaku = values.npwp_tgl_berlaku
      if (initialValues.m_kary_det_kartu[0]?.npwp_foto !== tempNPWP) {
        values.m_kary_det_kartu[0].npwp_foto = tempNPWP
      }
      values.m_kary_det_kartu[0].bpjs_tipe_id = values.bpjs_tipe_id
      values.m_kary_det_kartu[0].bpjs_no_kesehatan = values.bpjs_no_kesehatan
      values.m_kary_det_kartu[0].bpjs_no_ketenagakerjaan = values.bpjs_no_ketenagakerjaan
      values.m_kary_det_kartu[0].berkas_lain = values.berkas_lain
      values.m_kary_det_kartu[0].desc_file = values.desc_file
    }

    const payload = { ...values }
    if (payload.tgl_masuk) payload.tgl_masuk = formatDateApi(payload.tgl_masuk)
    if (payload.tgl_berhenti) payload.tgl_berhenti = formatDateApi(payload.tgl_berhenti)
    if (payload.tgl_lahir) payload.tgl_lahir = formatDateApi(payload.tgl_lahir)
    if (payload.npwp_tgl_berlaku) payload.npwp_tgl_berlaku = formatDateApi(payload.npwp_tgl_berlaku)
    if (payload.lama_kontrak_awal) payload.lama_kontrak_awal = formatDateApi(payload.lama_kontrak_awal)
    if (payload.lama_kontrak_akhir) payload.lama_kontrak_akhir = formatDateApi(payload.lama_kontrak_akhir)

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
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json()
        formErrors.value = responseJson.errors || {}
        throw (responseJson.errors?.length ? responseJson.errors[0] : responseJson.message || "Failed when trying to post data")
      } else {
        throw ("Failed when trying to post data")
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

watchEffect(() => {
  // ✅ fallback: kalau username kosong tapi NIK (kode) ada, auto isi username
  if (typeof values !== 'undefined' && !values.username && values.kode) {
    values.username = values.kode
  }
})

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

console.log(route.path)
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      title: "Hapus",
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
    },
    {
      icon: 'copy',
      title: "Copy",
      class: 'bg-gray-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&` + tsId)
      }
    },
  ],
  api: {
    url: `${store.server.url_backend}/operation${endpointApi}`,
    headers: {
      'Content-Type': 'Application/json',
      authorization: `${store.user.token_type} ${store.user.token}`
    },
    params: {
      simplest: true,
      searchfield: 'this.id, this.nik, this.kode, atasan.nama_lengkap, this.nama_lengkap, m_dir.nama, m_divisi.nama, m_dept.nama, this.alamat_domisili, this.no_tlp, this.tgl_masuk, this.tgl_berhenti, this.can_outscope, this.is_active',
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
    headerName: 'NIK',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'nik',
    headerName: 'No KTP',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start'],
    valueGetter: (params) => params.data?.nik || params.data?.['m_kary_det_kartu.0.ktp_no'] || '-'
  },
  {
    headerName: 'Nama',
    field: 'nama_lengkap',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'atasan.nama_lengkap',
    headerName: 'Atasan',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Company',
    field: 'm_dir.nama',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Divisi',
    field: 'm_divisi.nama',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Departemen',
    field: 'm_dept.nama',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Tgl Masuk',
    field: 'tgl_masuk',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => formatDateDisplay(params?.data?.tgl_masuk)
  },
  {
    headerName: 'Can Outscope',
    field: 'can_outscope',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-start'],
    valueGetter: (params) => (params?.data?.can_outscope === true || params?.data?.can_outscope === 1 || params?.data?.can_outscope === '1' || params?.data?.can_outscope === 'true' || params?.data?.can_outscope === 't') ? 'Ya' : 'Tidak'
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
watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))
