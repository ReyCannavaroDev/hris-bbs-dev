import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, watch, onActivated, computed } from 'vue'


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
const is_approval = route.query.is_approval ? true : false
const is_to_upload = route.query.is_to_upload ? true : false
let isApproved = ref(false)
let modalOpen = ref(false)
let isFinish = ref(false)
const is_superadmin = ref(false);
// ------------------------------ PERSIAPAN
const endpointApi = '/t_cuti'
onBeforeMount(() => {
  document.title = is_approval ? 'Approval Cuti' : 'Transaksi Cuti'
  is_superadmin.value = store.user.data?.is_superadmin ?? false
  console.log(store.user.data?.is_superadmin)
})

function parseDate(v) {
  if (!v) return null
  if (v instanceof Date || Object.prototype.toString.call(v) === '[object Date]') {
    return isNaN(v.getTime()) ? null : v
  }
  if (typeof v !== 'string') return null
  const s = v.trim()
  if (!s) return null

  // 1. Check standard date patterns: DD/MM/YYYY or YYYY-MM-DD
  const dateOnly = s.split(/[T ]/)[0]
  const parts = dateOnly.split(/[-/.]/)
  if (parts.length === 3) {
    let year, month, day
    if (parts[0].length === 4) {
      year = parseInt(parts[0], 10)
      month = parseInt(parts[1], 10) - 1
      day = parseInt(parts[2], 10)
    } else if (parts[2].length === 4) {
      day = parseInt(parts[0], 10)
      month = parseInt(parts[1], 10) - 1
      year = parseInt(parts[2], 10)
    }
    if (year && month !== undefined && day) {
      const d = new Date(year, month, day)
      if (!isNaN(d.getTime())) return d
    }
  }

  // 2. Parse full date strings like "Tue Oct 06 2026..." or ISO strings
  const parsed = new Date(s)
  if (!isNaN(parsed.getTime())) {
    return parsed
  }

  return null
}

function normalizeCutiDate(val, otherVal = null, interval = null, nomor = null) {
  if (!val) return ''

  let d, m, y;

  if (val instanceof Date || Object.prototype.toString.call(val) === '[object Date]') {
    if (isNaN(val.getTime())) return ''
    d = val.getDate()
    m = val.getMonth() + 1
    y = val.getFullYear()
    return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${String(y).padStart(4, '0')}`
  }

  const s = String(val).trim()
  if (!s) return ''

  // If string contains weekday name or timezone (like 'Tue Oct 06 2026...')
  if (/^[A-Za-z]{3}\s+[A-Za-z]{3}\s+\d+/.test(s)) {
    const dt = new Date(s)
    if (!isNaN(dt.getTime())) {
      d = dt.getDate()
      m = dt.getMonth() + 1
      y = dt.getFullYear()
      return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${String(y).padStart(4, '0')}`
    }
  }

  const dateOnly = s.split(/[T ]/)[0]
  const parts = dateOnly.split(/[-/.]/)
  if (parts.length === 3) {
    if (parts[0].length === 4) {
      y = parseInt(parts[0], 10)
      m = parseInt(parts[1], 10)
      d = parseInt(parts[2], 10)
    } else if (parts[2].length === 4) {
      d = parseInt(parts[0], 10)
      m = parseInt(parts[1], 10)
      y = parseInt(parts[2], 10)
    } else {
      const dt = new Date(s)
      if (!isNaN(dt.getTime())) {
        d = dt.getDate()
        m = dt.getMonth() + 1
        y = dt.getFullYear()
        return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${String(y).padStart(4, '0')}`
      }
      return String(val)
    }
  } else {
    const dt = new Date(s)
    if (!isNaN(dt.getTime())) {
      d = dt.getDate()
      m = dt.getMonth() + 1
      y = dt.getFullYear()
      return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${String(y).padStart(4, '0')}`
    }
    return String(val)
  }

  let shouldSwap = false
  let trxMonth = null
  if (nomor) {
    const match = String(nomor).match(/CT-\d{2}(\d{2})\d{2}/)
    if (match) trxMonth = parseInt(match[1], 10)
  }

  const parsedIntv = interval ? parseInt(interval, 10) : 0
  if (parsedIntv > 0 && parsedIntv <= 5 && trxMonth && d === trxMonth && m <= 12 && m !== trxMonth) {
    shouldSwap = true
  }

  if (!shouldSwap && otherVal && interval) {
    try {
      const intv = parseInt(interval, 10)
      const otherS = String(otherVal).trim().split(/[T ]/)[0]
      const oParts = otherS.split(/[-/.]/)
      if (oParts.length === 3) {
        const oD = parseInt(oParts[0].length === 4 ? oParts[2] : oParts[0], 10)
        const oM = parseInt(oParts[1], 10)
        if (d === oD && Math.abs(oM - m) + 1 === intv && intv <= 5 && m <= 12 && d <= 12) {
          shouldSwap = true
        }
      }
    } catch (e) {}
  }

  if (!shouldSwap && interval) {
    try {
      const intv = parseInt(interval, 10)
      if (intv <= 5 && d === 10 && (m === 6 || m === 7)) {
        shouldSwap = true
      }
    } catch (e) {}
  }

  if (shouldSwap && d <= 12 && m <= 12) {
    const tmp = d
    d = m
    m = tmp
  }

  return `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}/${String(y).padStart(4, '0')}`
}

function formatDateDisplay(d) {
  if (!d) return ''
  return normalizeCutiDate(d)
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

const values = reactive({
  m_dir_id: null,
  m_comp_id: null,
  m_kary_id: null,
  alasan_id: null,
  tipe_cuti_id: null,
  alasan_string: '',
  tipe_string: '',
  attachment: null,
})
const totalDays = ref();

const isCutiSakit = computed(() => {
  const strCheck = ((values.alasan_string || '') + ' ' + (values.tipe_string || '') + ' ' + (values.keterangan || '')).toLowerCase();
  return strCheck.includes('sakit');
});

const attachmentDisplayName = computed(() => {
  if (!values.attachment) return '';
  const parts = values.attachment.split(':::');
  return parts[parts.length - 1];
});

function openAttachment() {
  if (!values.attachment) return;
  const rawPath = values.attachment;
  const path = rawPath.startsWith('http')
    ? rawPath
    : `${store.server.url_backend}/${rawPath.replace(/^(\.\/|\/)/, '')}`;
  window.open(path, '_blank');
}

function onDateFromChange(val) {
  values.date_from = normalizeCutiDate(val)
}

function onDateToChange(val) {
  values.date_to = normalizeCutiDate(val)
}

watch(() => values.date_from, (newVal) => {
  if (newVal && !/^\d{2}\/\d{2}\/\d{4}$/.test(String(newVal).trim())) {
    values.date_from = normalizeCutiDate(newVal)
  }
})

watch(() => values.date_to, (newVal) => {
  if (newVal && !/^\d{2}\/\d{2}\/\d{4}$/.test(String(newVal).trim())) {
    values.date_to = normalizeCutiDate(newVal)
  }
})

watchEffect(() => {
  if (values.date_from && values.date_to && values.date_from !== '' && values.date_to !== '') {
    const dateFrom = parseDate(values.date_from);
    const dateTo = parseDate(values.date_to);

    if (dateFrom && dateTo) {
      const timeDifference = dateTo.getTime() - dateFrom.getTime();
      const daysDifference = Math.round(timeDifference / (1000 * 3600 * 24)) + 1;

      totalDays.value = daysDifference;
    } else {
      totalDays.value = 'error';
    }
  } else {
    totalDays.value = 0;
  }
});

onBeforeMount(async () => {
  // tampilkan default direktorat dengan store user comp.nama
  values.direktorat = store.user.data?.direktorat


  if (isRead) {
    //  READ DATA
    try {
      let dataURL = ''
      let dataURLAprv = ''
      let resAprv = ''
      if (route.query.is_approval) {
        dataURLAprv = `${store.server.url_backend}/operation/t_cuti/detail?id=${route.params.id}`
        isRequesting.value = true
        const apiApp = await fetch(dataURLAprv, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        const resultJson = await apiApp.json()
        console.log(resultJson.data)
        const apiTrx = await fetch(`${store.server.url_backend}/operation${endpointApi}/${resultJson.data.approval.trx_id}`, {
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
        })
        if (!apiTrx.ok || !apiApp.ok) throw new Error("Failed when trying to read data")
        const resultTrxJson = await apiTrx.json()
        values.interval = resultJson?.data.approval
        values.approval = resultJson?.data.approval
        if (values.approval?.created_at) {
          values.approval.created_at = formatDateTime(values.approval.created_at)
        }
        values.trx = resultJson?.data.trx
        values.datalog = resultJson?.data.approval_log
        const apNomor = resultTrxJson?.data?.nomor || resultJson?.data?.trx?.nomor || ''
        const apIntv = resultTrxJson?.data?.interval || resultJson?.data?.approval?.interval || resultJson?.data?.trx?.interval
        if (resultTrxJson?.data) {
          if (resultTrxJson.data.date_from) resultTrxJson.data.date_from = normalizeCutiDate(resultTrxJson.data.date_from, resultTrxJson.data.date_to, apIntv, apNomor)
          if (resultTrxJson.data.date_to) resultTrxJson.data.date_to = normalizeCutiDate(resultTrxJson.data.date_to, resultTrxJson.data.date_from, apIntv, apNomor)
        }
        if (resultJson?.data?.trx) {
          if (resultJson.data.trx.date_from) resultJson.data.trx.date_from = normalizeCutiDate(resultJson.data.trx.date_from, resultJson.data.trx.date_to, apIntv, apNomor)
          if (resultJson.data.trx.date_to) resultJson.data.trx.date_to = normalizeCutiDate(resultJson.data.trx.date_to, resultJson.data.trx.date_from, apIntv, apNomor)
        }
        initialValues = resultTrxJson.data

        // logic finish & Approved data
        isApproved.value = resultTrxJson?.data?.cuti_status == 'APPROVED' ? true : false
        isFinish.value = resultJson?.data?.approval?.tahap_saat_ini == resultJson?.data?.approval?.tahap_total ? true : false
      } else {
        const editedId = route.params.id
        dataURL = `${store.server.url_backend}/operation${endpointApi}/${editedId}`
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
        if (initialValues) {
          const lNomor = initialValues.nomor || ''
          const lIntv = initialValues.interval
          if (initialValues.date_from) initialValues.date_from = normalizeCutiDate(initialValues.date_from, initialValues.date_to, lIntv, lNomor)
          if (initialValues.date_to) initialValues.date_to = normalizeCutiDate(initialValues.date_to, initialValues.date_from, lIntv, lNomor)
        }
        // logic Approved data
        isApproved.value = resultJson?.data?.cuti_status == 'APPROVED' ? true : false
        console.log(resultJson?.data)
        console.log(isApproved.value)
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
  if (!values.m_dir_id && (initialValues['m_kary.m_dir_id'] || initialValues['m_dir.id'] || initialValues['m_kary_dir_id'])) {
    values.m_dir_id = initialValues['m_kary.m_dir_id'] || initialValues['m_dir.id'] || initialValues['m_kary_dir_id']
  }
  if (!values.alasan_string && (initialValues['alasan.value'] || initialValues['m_general.value'])) {
    values.alasan_string = initialValues['alasan.value'] || initialValues['m_general.value']
  }
  if (!values.tipe_string && initialValues['tipe_cuti.value']) {
    values.tipe_string = initialValues['tipe_cuti.value']
  }
  const nomorTrx = values.nomor || initialValues.nomor || values.trx?.nomor || ''
  const intvTrx = values.interval ?? initialValues.interval
  if (values.date_from) values.date_from = normalizeCutiDate(values.date_from, values.date_to, intvTrx, nomorTrx)
  if (values.date_to) values.date_to = normalizeCutiDate(values.date_to, values.date_from, intvTrx, nomorTrx)
})

onMounted(()=>{
  if(!is_superadmin.value){
    values.m_kary_id = store.user.data?.m_kary_id
    if (!values.m_dir_id) values.m_dir_id = store.user.data?.m_dir_id
    if (!values.m_comp_id) values.m_comp_id = store.user.data?.m_comp_id || 1
  }
})

async function downloadDoc(){
   window.open(`${store.server.url_backend}/operation/t_cuti/cuti?id=${values.t_cuti_id}`)
}

function onBack() {
  if (!is_approval) {
    router.replace('/' + modulPath)
  } else {
    router.replace('/notifikasi')
  }
  return
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

function formatDateTime(val) {
  if (!val) return '-'
  try {
    const s = String(val).trim()
    const [datePart, timePart] = s.includes('T') ? s.split('T') : s.split(' ')
    if (datePart && datePart.includes('-')) {
      const [y, m, d] = datePart.split('-')
      if (y && m && d) {
        const timeClean = timePart ? timePart.split('.')[0] : ''
        return timeClean ? `${d}/${m}/${y} ${timeClean}` : `${d}/${m}/${y}`
      }
    }
    const dt = new Date(val)
    if (!isNaN(dt.getTime())) {
      const pad = (n) => String(n).padStart(2, '0')
      return `${pad(dt.getDate())}/${pad(dt.getMonth() + 1)}/${dt.getFullYear()} ${pad(dt.getHours())}:${pad(dt.getMinutes())}:${pad(dt.getSeconds())}`
    }
  } catch (e) {
    console.error('formatDateTime error', e)
  }
  return val
}

function openModal(id) {
  dataLog.items = []
  modalOpen.value = true
  loadLog(id)
}

function closeModal(i) {
  dataLog.items = []
  modalOpen.value = false
}

let dataLog = reactive({items:[]})
async function loadLog(id) {
  const url = `${store.server.url_backend}/operation/t_cuti/log?id=${id}`
  const res = await fetch(url, {
    headers: {
      'Content-Type': 'Application/json',
      Authorization: `${store.user.token_type} ${store.user.token}`
    },
  })
  if (!res.ok) throw new Error("Failed when trying to read data")
  const result = await res.json()
  dataLog.items = result
}

function formatDate(date) {
  const formattedDate = date.split('/')
  if(formattedDate.length > 1){
    const year = formattedDate[2]
    const month = formattedDate[1]
    const day = formattedDate[0]
    return `${year}-${month}-${day}`
  }else{
    return date
  }
}

function onSave() {
  try {
    const isP24 = values.tipe_string?.toLowerCase() === 'p24' || (values.time_from && values.time_to);
    const dFrom = parseDate(values.date_from);
    const dTo = isP24 ? dFrom : parseDate(values.date_to);

    if (dFrom && dTo && dTo < dFrom) {
      isBadForm.value = true;
      swal.fire({
        icon: 'error',
        text: 'Tanggal Akhir tidak boleh lebih kecil dari Tanggal Awal',
      });
      return;
    }

    // --- VALIDASI ATURAN BISNIS CUTI & DISPENSASI (MoM POIN 5) ---
    const alasanText = (values.alasan_string || '').toLowerCase();
    const tipeText = (values.tipe_string || '').toLowerCase();
    const ketText = (values.keterangan || '').toLowerCase();
    const strCheck = `${alasanText} ${tipeText} ${ketText}`;

    const isSakit = strCheck.includes('sakit');
    const isMenikah = strCheck.includes('nikah') || strCheck.includes('menikah');
    const isMeninggal = strCheck.includes('meninggal') || strCheck.includes('kematian') || strCheck.includes('duka');
    // HANYA yang alasan cutinya melahirkan yang tidak dibatasi 3 hari (maksimal 3 bulan)
    const isMelahirkan = alasanText.includes('lahir') || alasanText.includes('melahirkan') || alasanText.includes('bersalin') || strCheck.includes('melahirkan');

    // Validasi Wajib Surat Dokter untuk Cuti Sakit (Mandatory)
    if (isSakit && !values.attachment) {
      isBadForm.value = true;
      formErrors.value = {
        ...formErrors.value,
        attachment: 'Pengajuan cuti sakit wajib melampirkan Surat Keterangan Dokter (SKD).'
      };
      swal.fire({
        icon: 'warning',
        title: 'Surat Dokter Wajib Diunggah',
        text: 'Pengajuan cuti sakit wajib melampirkan Surat Keterangan Dokter (SKD). Silakan unggah dokumen surat dokter terlebih dahulu sebelum menyimpan.',
        confirmButtonText: 'Mengerti',
        confirmButtonColor: '#1469AE',
      });
      return;
    }

    if (!isP24 && dFrom && dTo) {
      const diffTime = dTo.getTime() - dFrom.getTime();
      const diffDays = Math.round(diffTime / (1000 * 3600 * 24)) + 1;

      if (isMelahirkan) {
        // HANYA cuti melahirkan yang tidak dibatasi 3 hari (diperbolehkan hingga maksimal 3 bulan)
        const maxDate = new Date(dFrom);
        maxDate.setMonth(maxDate.getMonth() + 3);
        if (dTo > maxDate) {
          isBadForm.value = true;
          swal.fire({
            icon: 'error',
            text: 'Pengajuan cuti melahirkan maksimal 3 bulan.',
          });
          return;
        }
      } else {
        // Sisanya (termasuk cuti sakit, menikah, duka cita, reguler, dll): paling maksimal 3 hari berurutan
        if (diffDays > 3) {
          isBadForm.value = true;
          let errMsg = 'Pengambilan cuti maksimal 3 hari berturut-turut per pengajuan.';
          if (isSakit) {
            errMsg = 'Pengajuan izin sakit dengan Surat Keterangan Dokter (SKD) maksimal 3 hari kerja per pengajuan.';
          } else if (isMenikah) {
            errMsg = 'Pengajuan cuti menikah maksimal 3 hari kerja.';
          } else if (isMeninggal) {
            errMsg = 'Pengajuan cuti duka cita / keluarga meninggal maksimal 3 hari kerja.';
          }
          swal.fire({
            icon: 'error',
            text: errMsg,
          });
          return;
        }
      }
    }

    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value);

    if (values.status === 'REVISED') {
      values.status = 'DRAFT'; // Change status to DRAFT if it was REVISED
    }

    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`;

    const payload = { ...values };
    payload.date_from = formatDateApi(values.date_from);
    if (isP24) {
      payload.date_to = payload.date_from;
    } else {
      payload.date_to = formatDateApi(values.date_to);
    }

    isRequesting.value = true;

    fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`,
      },
      body: JSON.stringify(payload),
    })
      .then(async (res) => {
        if (!res.ok) {
          if ([400, 422].includes(res.status)) {
            const responseJson = await res.json();
            formErrors.value = responseJson.errors || {};
            throw new Error(responseJson.message || 'Failed when trying to post data');
          } else {
            throw new Error('Failed when trying to post data');
          }
        }
        router.replace('/' + modulPath + '?reload=' + Date.parse(new Date()));
      })
      .catch((err) => {
        isBadForm.value = true;
        swal.fire({
          icon: 'error',
          text: err.message || err || 'Harap Lengkapi Data',
        });
      })
      .finally(() => {
        isRequesting.value = false;
      });
  } catch (err) {
    isBadForm.value = true;
    swal.fire({
      icon: 'error',
      text: 'Harap Lengkapi Data',
    });
    isRequesting.value = false;
  }
}


function onProcess(typePar) {
  const payload = {
    id: route.params.id,
    type: typePar === 'revise' ? 'REVISED' : (typePar === 'reject' ? 'REJECTED' : 'APPROVED'),
    note: values.catatan,
  };
  // if(!payload.note) {
  //   swal.fire({
  //     icon: 'warning',
  //     text: "Catatan wajib diisi",
  //   });
  //   return  
  // }


  swal.fire({
    icon: 'warning',
    text: typePar === 'revise' ? 'Revised data?' : (typePar === 'reject' ? 'Rejected data?' : 'Approved data?'),
    showDenyButton: true,
  }).then(async (res) => {
    if (res.isConfirmed) {
      try {
        const dataURL = `${store.server.url_backend}/operation/t_cuti/progress`;
        isRequesting.value = true;
        const res = await fetch(dataURL, {
          method: 'POST',
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`,
          },
          body: JSON.stringify(payload),
        });


        

        if (!res.ok) {
          const responseJson = await res.json();
          if ([400, 422, 500].includes(res.status)) {
            formErrors.value = responseJson.errors || {};
            if (res.status === 422) {
              throw new Error(responseJson.message + " Pastikan anda sudah mengisi semua kolom dengan tanda bintang merah");
            }
            throw new Error(responseJson.message || "Failed when trying to post data");
          } else {
            throw new Error("Failed when trying to post data");
          }
        } else {
          // Success case
          swal.fire({
            icon: 'success',
            text: 'Proses berhasil',
          });
          router.replace('/notifikasi');
        }
      } catch (err) {
        isBadForm.value = true;
        swal.fire({
          icon: 'error',
          text: err || 'Failed when trying to post data',
        });
      } finally {
        isRequesting.value = false;
      }
    }
  });

  if (route.params.id === 'create') {
    activeTabIndex = 0;
  }
}


//  @else----------------------- LANDING
// LANDING LAMA 
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      show: (row) =>row.status?.toUpperCase() === 'DRAFT',
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
              if (!res.ok) throw new Error("Failed when trying to remove data")
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
  show: (row) => row.status?.toUpperCase() === 'DRAFT' || row.status?.toUpperCase() === 'REVISED' || (row.status?.toUpperCase() === 'IN APPROVAL' && store.user.data?.is_superadmin),
  click(row) {
    router.push(`${route.path}/${row.id}?action=Edit&` + tsId);
  }
},
    {
      icon: 'copy',
      title: "Copy",
            show: (row) => row.status?.toUpperCase() === 'DRAFT',
      class: 'bg-gray-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&`+tsId)
      }
    },
    {
      icon: 'location-arrow',
      title: "Send Approval",
      class: 'bg-rose-700 rounded-lg text-white',
      show: (row) => {
        const status = row.status?.toUpperCase()
        return ['DRAFT', 'POSTED', 'REVISED'].includes(status)
      },

      async click(row) {
        swal.fire({
          icon: 'warning',
          text: 'Send Approval?',
          iconColor: '#1469AE',
          confirmButtonColor: '#1469AE',

          showDenyButton: true
        }).then(async (res) => {
          if (res.isConfirmed) {
            try {
              const dataURL = `${store.server.url_backend}/operation/t_cuti/send_approval`
              isRequesting.value = true
              const res = await fetch(dataURL, {
                method: 'POST',
                headers: {
                  'Content-Type': 'Application/json',
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                body: JSON.stringify({ id: row.id })
              })
              if (!res.ok) {
                if ([400, 422, 500].includes(res.status)) {
                  const responseJson = await res.json()
                  formErrors.value = responseJson.errors || {}
                  throw (responseJson.message+ " "+responseJson.data.errorText || "Failed when trying to post data")
                } else {
                  throw ("Failed when trying to post data")
                }
              }
              const responseJson = await res.json()
              swal.fire({
                icon: 'success',
                text: responseJson.message
              })
              // const resultJson = await res.json()
            } catch (err) {
              isBadForm.value = true
              swal.fire({
                icon: 'error',
                iconColor: '#1469AE',
                confirmButtonColor: '#1469AE',
                text: err
              })
            }
            isRequesting.value = false

            apiTable.value.reload()
          }
        })
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
      searchfield: 'nomor, m_kary.nama_lengkap, m_dir.nama, alasan.value, tipe_cuti.value, date_from, date_to, status',
      where: `${!store.user.data?.is_superadmin ? ('this.m_kary_id='+store.user.data?.m_kary_id ?? 0) : ''}`
    },
    onsuccess(response) {
      if (response && response.data) {
        response.data = response.data.map((item) => {
          item.date_from = normalizeCutiDate(item.date_from, item.date_to, item.interval, item.nomor)
          item.date_to = normalizeCutiDate(item.date_to, item.date_from, item.interval, item.nomor)
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
    cellClass: ['justify-left', 'bg-gray-50', 'border-r', '!border-gray-200']
  },
  {
    field: 'nomor',
    headerName: 'Nomor',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_dir.nama',
    headerName: 'Company',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data['m_dir.nama'] || p.data['m_kary.m_dir.nama'] || p.data.m_dir?.nama || '-',
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_kary.nama_lengkap',
    headerName: 'Nama Karyawan',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'alasan.value',
    headerName: 'Alasan',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'tipe_cuti.value',
    headerName: 'Tipe Cuti',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'date_from',
    headerName: 'Tanggal Awal',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const val = params.data?.date_from || params.value
      return normalizeCutiDate(val, params.data?.date_to, params.data?.interval, params.data?.nomor)
    }
  },
  {
    field: 'date_to',
    headerName: 'Tanggal Akhir',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const val = params.data?.date_to || params.value
      return normalizeCutiDate(val, params.data?.date_from, params.data?.interval, params.data?.nomor)
    }
  },
 {
    headerName: 'Status',
    field: 'status',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
      let color = 'gray'
      if(value == 'APPROVED')
        color = 'green'
      else if(value == 'IN APPROVAL')
        color = 'blue'
      else if(value == 'REVISED')
        color = 'yellow'
      else if(value == 'REJECTED')
        color = 'red'
    return `<span class="text-${color}-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">${value}</span>`
  }}
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