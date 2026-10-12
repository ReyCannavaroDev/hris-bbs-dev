import { useRouter, useRoute } from 'vue-router'
import {
  ref,
  reactive,
  inject,
  onMounted,
  onBeforeMount,
  onBeforeUnmount,
  watchEffect,
} from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isRead = route.params.id && route.params.id !== 'create'
const actionText = ref(route.params.id === 'create' ? 'Tambah' : route.query.action)
const isProfile = ref(!!route.query.profile)
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
const content = ref()

const tsId = `ts=${Date.parse(new Date())}`
const endpointApi = '/m_kary'

onBeforeMount(() => {
  document.title = 'Absen Online'
})

let initialValues = {}
const changedValues = []
const thisYear = new Date().getFullYear()
let tempKTP = ''
let tempBPJS = ''
let tempNPWP = ''
let tempKK = ''
let tempPasfoto = ''

const values = reactive({
  is_active: 1,
  direktorat: store.user.data?.direktorat,
  cuti_p24: 0,
  cuti_reguler: 0,
  cuti_masa_kerja: 0,
  cuti_p24_terpakai: 0,
  sisa_cuti_reguler: 0,
  sisa_cuti_masa_kerja: 0,
})

function onBack() {
  let isChanged = false
  for (const key in initialValues) {
    if (values[key] !== initialValues[key]) {
      isChanged = true
      break
    }
  }
  router.replace('/' + modulPath)
}

function onReset() {
  swal
    .fire({
      icon: 'warning',
      text: 'Reset this form data?',
      showDenyButton: true,
    })
    .then((res) => {
      if (res.isConfirmed) {
        for (const key in initialValues) {
          values[key] = initialValues[key]
        }
      }
    })
}

async function onSave() {
  try {
    const isCreating = !isRead
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${
      isCreating ? '' : '/' + route.params.id
    }`
    isRequesting.value = true
    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`,
      },
      body: JSON.stringify(values),
    })
    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json()
        formErrors.value = responseJson.errors || {}
        throw (
          responseJson.errors?.[0] ||
          responseJson.message ||
          'Failed when trying to post data'
        )
      } else {
        throw 'Failed when trying to post data'
      }
    }
    router.replace('/' + modulPath + '?reload=' + Date.parse(new Date()))
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err,
    })
  }
  isRequesting.value = false
}

watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))

// =======================
// FORM PERIODE
// =======================
const tempDate = new Date()
const listTahun = [] // ✅ sekarang object {id,key}
const tempmonth = tempDate.getMonth() + 1
const tempyear = tempDate.getFullYear()

const form = reactive({
  month: tempmonth,
  year: tempyear,
  lat: null,
  long: null,
  address: '',
  distance_check: null,

  // biar aman, kita declare juga dari awal
  day: '',
  tanggal: '',
  currentTime: '',
  attending: '',
  catatan_in: '',
})

for (let i = form.year; i >= 2010; i--) {
  listTahun.push({ id: i, key: i })
}

// =======================
// STATE ABSEN & CAMERA
// =======================
const videoElement = ref(null)
const coordsLocation = ref()
const listDetail = ref([])
const isImage = ref(false)
const formData = new FormData()

// =======================
// FACE QUALITY COACH + VOICE
// =======================
const qualityScore = ref(null)
const qualityLabel = ref('')
const qualityMessage = ref('')
const voiceEnabled = ref(true)

let qualityTimer = null
let lastSpokenKey = null
let lastSpokenScore = null
let lastSpeakAt = 0
let lastSpeakStartedAt = 0

const MIN_DELTA_SCORE = 15
const MIN_INTERVAL_MS = 10000

let selectedVoice = null

function initVoices() {
  try {
    if (typeof window === 'undefined' || !window.speechSynthesis) return
    const synth = window.speechSynthesis

    const pickVoice = () => {
      const voices = synth.getVoices()
      if (!voices || !voices.length) return

      selectedVoice =
        voices.find((v) =>
          v.name.toLowerCase().includes('google bahasa indonesia'),
        ) ||
        voices.find(
          (v) => v.lang === 'id-ID' && v.name.toLowerCase().includes('google'),
        ) ||
        voices.find((v) => v.lang === 'id-ID') ||
        voices.find((v) => v.lang.toLowerCase().startsWith('id-')) ||
        voices[0]
    }

    pickVoice()
    synth.onvoiceschanged = () => {
      if (!selectedVoice) pickVoice()
    }
  } catch (e) {
    console.log('initVoices error', e)
  }
}

function speak(text) {
  try {
    if (!voiceEnabled.value) return
    if (typeof window === 'undefined') return
    const synth = window.speechSynthesis
    if (!synth) return

    const now = Date.now()

    if (synth.speaking && now - lastSpeakStartedAt < 1500) return

    if (synth.pending && !synth.speaking) {
      synth.cancel()
    }

    const u = new SpeechSynthesisUtterance(text)
    u.lang = 'id-ID'
    u.rate = 1.1
    u.pitch = 1.1

    if (selectedVoice) u.voice = selectedVoice

    u.onstart = () => {
      lastSpeakStartedAt = Date.now()
    }

    synth.speak(u)
  } catch (e) {
    console.log('Error speak():', e)
  }
}

watchEffect(() => {
  if (typeof window === 'undefined' || !window.speechSynthesis) return
  if (!voiceEnabled.value) {
    window.speechSynthesis.cancel()
  }
})

// helper ambil frame & hitung metrics sederhana
function computeFrameMetricsFromVideo() {
  if (!videoElement.value) return null
  if (!videoElement.value.videoWidth || !videoElement.value.videoHeight) return null

  const w = videoElement.value.videoWidth
  const h = videoElement.value.videoHeight
  if (!w || !h) return null

  const canvas = document.createElement('canvas')
  canvas.width = w
  canvas.height = h
  const ctx = canvas.getContext('2d')
  ctx.drawImage(videoElement.value, 0, 0, w, h)

  const imgData = ctx.getImageData(0, 0, w, h)
  const d = imgData.data

  let sumB = 0
  let sumLap = 0
  let sumLap2 = 0
  let cnt = 0
  const step = 4

  const lumAt = (x, y) => {
    const i = (y * w + x) * 4
    const r = d[i]
    const g = d[i + 1]
    const b = d[i + 2]
    return 0.299 * r + 0.587 * g + 0.114 * b
  }

  for (let y = 2; y < h - 2; y += step) {
    for (let x = 2; x < w - 2; x += step) {
      const c = lumAt(x, y)
      const l = lumAt(x - 1, y)
      const r = lumAt(x + 1, y)
      const u = lumAt(x, y - 1)
      const dn = lumAt(x, y + 1)
      const lap = 4 * c - l - r - u - dn

      sumB += c
      sumLap += lap
      sumLap2 += lap * lap
      cnt++
    }
  }

  const bright = sumB / Math.max(1, cnt)
  const meanLap = sumLap / Math.max(1, cnt)
  const blurVar = sumLap2 / Math.max(1, cnt) - meanLap * meanLap

  return { bright, blurVar }
}

function updateQualityFromMetrics(metrics) {
  if (!metrics) {
    qualityScore.value = null
    qualityLabel.value = ''
    qualityMessage.value = ''
    return
  }

  const { bright, blurVar } = metrics

  let brightScore
  if (bright < 40) brightScore = 30
  else if (bright > 220) brightScore = 30
  else if (bright < 80) brightScore = 60
  else if (bright > 190) brightScore = 60
  else brightScore = 90

  let blurScore
  if (blurVar < 10) blurScore = 20
  else if (blurVar < 30) blurScore = 50
  else if (blurVar < 80) blurScore = 75
  else blurScore = 95

  const score = Math.round(brightScore * 0.4 + blurScore * 0.6)
  qualityScore.value = score

  let label
  let msg
  if (score >= 80) {
    label = 'Bagus'
    msg = 'Kualitas wajah bagus, siap untuk absen.'
  } else if (score >= 55) {
    label = 'Cukup'
    if (bright < 60) msg = 'Sedikit gelap, coba hadap ke arah lampu.'
    else if (bright > 200) msg = 'Agak terlalu terang, kurangi backlight.'
    else if (blurVar < 40) msg = 'Sedikit blur, coba stabilkan kamera atau wajah.'
    else msg = 'Sudah cukup oke, bisa sedikit diperbaiki posisi atau pencahayaannya.'
  } else {
    label = 'Buruk'
    if (bright < 60) msg = 'Terlalu gelap, coba mendekat ke sumber cahaya.'
    else if (bright > 200) msg = 'Terlalu terang, hindari lampu atau jendela tepat di belakang.'
    else if (blurVar < 20) msg = 'Terlalu blur, pastikan kamera fokus dan tidak goyang.'
    else msg = 'Kualitas kurang, coba atur ulang posisi kamera dan wajah.'
  }

  qualityLabel.value = label
  qualityMessage.value = msg

  const now = Date.now()
  const key = label

  if (!voiceEnabled.value || !qualityMessage.value) return

  const bigChange =
    lastSpokenScore === null ? true : Math.abs(score - lastSpokenScore) >= MIN_DELTA_SCORE
  const timeOk = now - lastSpeakAt > MIN_INTERVAL_MS
  const labelChanged = key !== lastSpokenKey

  if ((labelChanged || bigChange) && timeOk) {
    speak(`Kualitas wajah ${label.toLowerCase()}. ${msg}`)
    lastSpokenKey = key
    lastSpokenScore = score
    lastSpeakAt = now
  }
}

function startQualityMonitor() {
  stopQualityMonitor()
  qualityTimer = setInterval(() => {
    if (!videoElement.value || isImage.value) return
    const metrics = computeFrameMetricsFromVideo()
    updateQualityFromMetrics(metrics)
  }, 1200)
}

function stopQualityMonitor() {
  if (qualityTimer) {
    clearInterval(qualityTimer)
    qualityTimer = null
  }
}

// =======================
// FACE API
// =======================
const FACE_API_BASE = 'https://server.qqltech.com:7089'
const faceResultText = ref('')
const faceMatch = ref(null)
const pilotEmployeeId = ref(null)
const pilotEmployeeName = ref(null)

const manualMode = ref(false)
const manualPilotEmployeeId = ref('')
const failStreak = ref(0)
const MANUAL_AFTER_FAILS = 2

function _reasonFromInfo(info) {
  return String(info?.reason || info?.liveness_reason || '').toLowerCase()
}

function _isQualityFail(info) {
  const r = _reasonFromInfo(info)
  const localLowQuality = qualityScore.value !== null && qualityScore.value < 55
  if (r === 'face_not_detected') return false
  if (r && r.includes('spoof')) return false
  if (r === 'quality_low' || r === 'liveness_uncertain') return true
  if (localLowQuality) return true
  if (
    r &&
    (r.includes('no_match') ||
      r.includes('not_recognized') ||
      r.includes('not recognized') ||
      r.includes('not recognised') ||
      r.includes('unknown'))
  ) {
    return true
  }
  return false
}

function _updateFailStreak(info) {
  if (info.match === true) {
    failStreak.value = 0
    return
  }

  if (_isQualityFail(info)) failStreak.value += 1
  else failStreak.value = 0
}

const dataDetail = ref()
const showModal = ref(false)

async function tampilkanModal(data) {
  showModal.value = true
  dataDetail.value = data
}

// =======================
// CAMERA CAPTURE
// =======================
async function capture() {
  try {
    if (!videoElement.value) return

    const canvas = document.createElement('canvas')
    canvas.width = videoElement.value.videoWidth
    canvas.height = videoElement.value.videoHeight

    const ctx = canvas.getContext('2d')
    ctx.translate(canvas.width, 0)
    ctx.scale(-1, 1)
    ctx.drawImage(videoElement.value, 0, 0, canvas.width, canvas.height)

    const capturedImageSrc = canvas.toDataURL('image/jpeg')
    const imgElem = document.getElementById('imgElem')
    if (imgElem) imgElem.setAttribute('src', capturedImageSrc)

    isImage.value = true

    const stream = videoElement.value.srcObject
    if (stream) stream.getTracks()?.forEach((track) => track.stop())

    stopQualityMonitor()
    if (typeof window !== 'undefined' && window.speechSynthesis) {
      window.speechSynthesis.cancel()
    }

    const capturedImageBlob = await new Promise((resolve) =>
      canvas.toBlob(resolve, 'image/jpeg'),
    )

    formData.set('foto', capturedImageBlob, 'captured_image.jpg')

    faceResultText.value = 'Mengirim foto ke Face Recognition...'
    faceMatch.value = null

    await recognizeFaceWithAPI(capturedImageBlob)
    getLocation()
  } catch (error) {
    console.log(error)
    alert('Oh maaf, sepertinyßa kami tidak mendapatkan akses kamera anda')
  }
}

async function recapture() {
  isImage.value = false
  faceResultText.value = ''
  manualMode.value = false
  manualPilotEmployeeId.value = ''
  pilotEmployeeId.value = null
  pilotEmployeeName.value = null

  if (typeof window !== 'undefined' && window.speechSynthesis) {
    window.speechSynthesis.cancel()
  }

  await mountCam()
}

// =======================
// JAM REALTIME
// =======================
function updateTime() {
  const now = new Date()
  let hours = now.getHours()
  let minutes = now.getMinutes()
  let seconds = now.getSeconds()

  hours = hours < 10 ? '0' + hours : hours
  minutes = minutes < 10 ? '0' + minutes : minutes
  seconds = seconds < 10 ? '0' + seconds : seconds

  form.currentTime = `${hours}:${minutes}:${seconds}`
}

const _timeInterval = setInterval(updateTime, 1000)

const listMonths = [
  { id: 1, name: 'Januari' },
  { id: 2, name: 'Februari' },
  { id: 3, name: 'Maret' },
  { id: 4, name: 'April' },
  { id: 5, name: 'Mei' },
  { id: 6, name: 'Juni' },
  { id: 7, name: 'Juli' },
  { id: 8, name: 'Agustus' },
  { id: 9, name: 'September' },
  { id: 10, name: 'Oktober' },
  { id: 11, name: 'November' },
  { id: 12, name: 'Desember' },
]

// =======================
// LIFECYCLE
// =======================
onMounted(async () => {
  initVoices()
  updateTime()

  isRequesting.value = true
  await getLocation()
  await checkLastStatus()

  await getDetailAbsen(form.year, form.month)

  const tempDate = new Date()
  const day = tempDate.getDate()
  const monthName = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember', // ✅ perbaikan typo
  ][tempDate.getMonth()]
  const year = tempDate.getFullYear()

  form.tanggal = `${String(day).padStart(2, '0')} ${monthName} ${year}`
  form.day = [
    'Minggu',
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu',
  ][tempDate.getDay()]

  isRequesting.value = false
})

onBeforeUnmount(() => {
  if (videoElement.value && videoElement.value.srcObject) {
    const stream = videoElement.value.srcObject
    stream.getTracks()?.forEach((track) => track.stop())
  }
  stopQualityMonitor()
  try {
    if (typeof window !== 'undefined' && window.speechSynthesis) {
      window.speechSynthesis.cancel()
    }
  } catch (e) {
    console.log(e)
  }
  clearInterval(_timeInterval)
})

// =======================
// LOCATION
// =======================
function getLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(setPosition, showError)
  } else {
    swal.fire({
      icon: 'error',
      text: 'Geolocation is not supported by this browser.',
    })
  }
}

function showError(error) {
  switch (error.code) {
    case error.PERMISSION_DENIED:
      swal.fire({ icon: 'error', text: 'Please enable location permissions to access your current location.' })
      break
    case error.POSITION_UNAVAILABLE:
      swal.fire({ icon: 'error', text: 'Location information is unavailable.' })
      break
    case error.TIMEOUT:
      swal.fire({ icon: 'error', text: 'The request to get user location timed out.' })
      break
    default:
      swal.fire({ icon: 'error', text: 'An unknown error occurred.' })
      break
  }
}

async function setPosition(position) {
  coordsLocation.value = position?.coords
  form.lat = position?.coords?.latitude ?? null
  form.long = position?.coords?.longitude ?? null

  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=json&accept-language=id&lat=${position.coords.latitude}&lon=${position.coords.longitude}`,
    )
    const resultJson = await res.json()
    form.address = resultJson?.display_name || ''

    await getDistance(position.coords.latitude, position.coords.longitude)
  } catch (err) {
    swal.fire({ icon: 'error', text: err })
  }
}

async function getDistance(lat, long) {
  try {
    const res = await fetch(
      `${store.server.url_backend}/operation/presensi_absensi/distance_check?lat=${lat}&long=${long}`,
      {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`,
        },
      },
    )
    if (!res.ok) {
      const responseJson = await res.json().catch(() => ({}))
      throw responseJson.message || 'Failed when trying to get distance'
    }
    const resultJson = await res.json()
    const data = resultJson.data
    form.distance_check = data?.on_scope
  } catch (err) {
    swal.fire({ icon: 'error', text: err })
  }
}

// =======================
// DETAIL ABSEN (FORMAT TANGGAL)
// =======================
function formatTanggal(value) {
  if (!value) return '-'
  const s = String(value).trim()

  // kalau sudah bentuk "01 Desember 2025" biarkan
  if (/[A-Za-zÀ-ÿ]/.test(s)) return s

  const sep = s.includes('/') ? '/' : (s.includes('-') ? '-' : null)
  if (!sep) return s

  const parts = s.split(sep)
  if (parts.length !== 3) return s

  let y, m, d
  if (parts[0].length === 4) [y, m, d] = parts       // YYYY-MM-DD
  else if (parts[2].length === 4) [d, m, y] = parts  // DD-MM-YYYY
  else return s

  const bulan = [
    'Januari','Februari','Maret','April','Mei','Juni',
    'Juli','Agustus','September','Oktober','November','Desember'
  ]

  const mm = parseInt(m, 10)
  const dd = parseInt(d, 10)
  const namaBulan = bulan[mm - 1]
  if (!namaBulan || Number.isNaN(dd)) return s

  return `${String(dd).padStart(2, '0')} ${namaBulan} ${y}`
}

// compatibility: kalau blade lama masih pakai removeStrip()
function removeStrip(value) {
  return formatTanggal(value)
}

// =======================
// GET LIST ABSEN (ENDPOINT BENAR)
// =======================
async function getDetailAbsen(year, month) {
  try {
    if (!year || !month) return

    isRequesting.value = true
    listDetail.value = []

    // penting: bulan harus 2 digit (01..12)
    const mm = String(month).padStart(2, '0')
    const periode = `${year}-${mm}`

    const url = `${store.server.url_backend}/operation/presensi_absensi/get_absen?periode=${periode}`

    const res = await fetch(url, {
      headers: {
        'Content-Type': 'application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`,
      },
    })

    if (!res.ok) {
      const responseJson = await res.json().catch(() => ({}))
      throw responseJson.message || `Gagal ambil data absensi (${res.status})`
    }

    const resultJson = await res.json().catch(() => ({}))
    const data = resultJson.data

    // jaga-jaga kalau backend balikin bentuk lain
    listDetail.value =
      Array.isArray(data) ? data :
      Array.isArray(data?.rows) ? data.rows :
      Array.isArray(data?.list) ? data.list :
      []
  } catch (err) {
    swal.fire({ icon: 'error', text: String(err) })
  } finally {
    isRequesting.value = false
  }
}

// =======================
// CEK STATUS ABSEN TERAKHIR
// =======================
async function checkLastStatus() {
  try {
    const res = await fetch(
      `${store.server.url_backend}/operation/presensi_absensi/status`,
      {
        headers: {
          'Content-Type': 'Application/json',
          Authorization: `${store.user.token_type} ${store.user.token}`,
        },
      },
    )
    if (!res.ok) {
      const responseJson = await res.json().catch(() => ({}))
      throw responseJson.message || 'Failed when trying to get status'
    }

    const resultJson = await res.json()
    const data = resultJson.data
    form.attending = data.status

    if (form.attending?.toLowerCase() === 'attend') {
      isImage.value = false
      manualMode.value = false
      failStreak.value = 0
      faceResultText.value = ''
    } else {
      mountCam()
    }
  } catch (err) {
    swal.fire({ icon: 'error', text: err })
  }
}

// =======================
// FACE RECOGNITION CALL
// =======================
async function recognizeFaceWithAPI(imageBlob) {
  try {
    const fd = new FormData()
    fd.append('image', imageBlob, 'captured_image.jpg')

    const res = await fetch(`${FACE_API_BASE}/recognize`, {
      method: 'POST',
      body: fd,
    })

    if (!res.ok) {
      faceResultText.value = 'Wajah tidak ditemukan.'
      faceMatch.value = null
      return
    }

    const data = await res.json()

    if (data.status === 'success' && data.data) {
      const info = data.data
      const r = _reasonFromInfo(info)

      if (info.match === true) {
        faceMatch.value = true
        manualMode.value = false
        manualPilotEmployeeId.value = ''
        failStreak.value = 0

        pilotEmployeeId.value = String(info.employee_id || '')
        pilotEmployeeName.value = String(info.name || '')

        faceResultText.value = `Wajah dikenali sebagai: ${pilotEmployeeName.value} (ID: ${pilotEmployeeId.value}).`
      } else {
        faceMatch.value = false
        pilotEmployeeId.value = ''
        pilotEmployeeName.value = ''

        _updateFailStreak(info)

        const isQualityFail = _isQualityFail(info)
        const needManual = failStreak.value >= MANUAL_AFTER_FAILS && isQualityFail

        manualMode.value = needManual

        if (needManual) {
          const loginPilotId = String(
            store.user.data?.pilot_employee_id || store.user.data?.username || '', //Mengambil username/ employee id
          ).trim()

          manualPilotEmployeeId.value = loginPilotId

          faceResultText.value =
            `Kualitas gambar kurang baik (percobaan ${failStreak.value}x). ` +
            'Silakan lanjut menggunakan absen manual, ID sudah dikunci ke akun login.'
        } else if (r === 'face_not_detected') {
          faceResultText.value =
            'Wajah tidak terdeteksi. Pastikan wajah menghadap kamera dan ulangi capture.'
        } else if (r && r.includes('spoof')) {
          faceResultText.value =
            'Deteksi spoof / bukan wajah live. Silakan ulangi dengan wajah asli di depan kamera.'
        } else {
          faceResultText.value = 'Wajah TIDAK dikenali di sistem.'
        }
      }
    } else {
      faceResultText.value = 'Response Face API tidak sesuai format.'
      faceMatch.value = null
    }
  } catch (err) {
    const msg = err?.message || String(err || 'Unknown error')
    faceResultText.value = 'Gagal menghubungi Face API: ' + msg
    faceMatch.value = null
  }
}

// POST ATTEND
async function postAttend() {
  try {
    const loginPilotId = String(
      store.user.data?.pilot_employee_id || store.user.data?.username || '',
    ).trim()

    const detectedId = String(
      pilotEmployeeId.value ||
        (manualMode.value ? loginPilotId : manualPilotEmployeeId.value) || //Digunakan untuk id/username yang dikirim
        '',
    ).trim()

    if (!detectedId) {
      swal.fire({
        icon: 'error',
        text:
          'ID karyawan belum ditemukan. Silakan capture ulang atau gunakan mode manual jika tersedia.',
      })
      return
    }

    if (loginPilotId && detectedId !== loginPilotId) {
      swal.fire({
        icon: 'error',
        text:
          'Absen ditolak. Wajah yang terdeteksi tidak sesuai dengan akun yang sedang login.',
      })
      return
    }

//Field yang dikirim, termasuk mode absen
    formData.set('pilot_employee_id', detectedId)
    formData.set('pilot_username', store.user.data?.username || '')
    formData.set('absen_mode', manualMode.value ? 'manual' : 'face')

    formData.set('lat', coordsLocation.value?.latitude)
    formData.set('long', coordsLocation.value?.longitude)
    formData.set('address', form.address)

    if (form.attending?.toLowerCase() === 'not attend') {
      formData.set('checkin_time', form.currentTime)
      formData.delete('checkout_time')
    } else {
      formData.set('checkout_time', form.currentTime)
      formData.delete('checkin_time')
    }

//Mengirim endpoint
    const res = await fetch(
      `${store.server.url_backend}/operation/presensi_absensi/${
        form.attending?.toLowerCase() === 'not attend' ? 'checkin' : 'checkout' 
      }`, 
      {
        method: 'POST',
        headers: {
          Authorization: `${store.user.token_type} ${store.user.token}`,
        },
        body: formData,
      },
    )

    if (!res.ok) {
      const responseJson = await res.json().catch(() => ({}))
      throw responseJson.message || 'Failed when trying to post data'
    }

    const resultJson = await res.json()
    swal
      .fire({
        icon: 'success',
        text: resultJson.message,
        iconColor: '#1469AE',
        confirmButtonColor: '#1469AE',
      })
      .then(async (r) => {
        if (r.isConfirmed) {
          pilotEmployeeId.value = ''
          pilotEmployeeName.value = ''
          manualPilotEmployeeId.value = ''
          manualMode.value = false
          failStreak.value = 0
          faceResultText.value = ''

          await getLocation()
          await checkLastStatus()
          await getDetailAbsen(form.year, form.month)
          isImage.value = false
        }
      })
  } catch (err) {
    swal.fire({ icon: 'error', text: err })
  }
}

// =======================
// CAMERA START
// =======================
async function mountCam() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: true,
      audio: false,
    })
    if (videoElement.value) {
      videoElement.value.srcObject = stream
    }
    isImage.value = false
    startQualityMonitor()
  } catch (error) {
    alert('Oh maaf, sepertinyßa kami tidak mendapatkan akses kamera anda')
  }
}
