//   javascript

import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, computed } from 'vue'

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

const is_approval = route.query.is_approval ? true : false
const is_to_upload = route.query.is_to_upload ? true : false
const paramLanding = `this.lembur_status!='APPROVED'`


let modalOpen = ref(false)
let isFinish = ref(false)
let isApproved = ref(false)

const is_superadmin = ref(false);

const isReadOnly = computed(() => {
  if (['Create', 'Copy', 'Tambah'].includes(actionText.value)) return false
  if (values.status === 'IN APPROVAL' || is_approval) return true
  return !actionText.value
})

const canApprove = computed(() => {
  if (values.status !== 'IN APPROVAL' && !is_approval) return false
  if (store.user.data?.is_superadmin) return true
  if (values.can_approve) return true
  const userKaryId = store.user.data?.m_kary_id
  const atasanKaryId = values['m_kary.atasan_id'] || values.m_kary?.atasan_id || values.atasan_id
  if (userKaryId && atasanKaryId && userKaryId == atasanKaryId) return true
  return false
})

// ------------------------------ PERSIAPAN
const endpointApi = '/t_lembur'
onBeforeMount(() => {
  document.title = is_approval ? 'Approval Lembur' : 'Transaksi Lembur'
  is_superadmin.value = store.user.data?.is_superadmin ?? false
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []

const getTodayDate = () => {
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const values = reactive({
  m_comp_id: null,
  m_dir_id: null,
  tanggal: getTodayDate(),
})


onBeforeMount(async () => {
  // tampilkan default direktorat dengan store user comp.nama
  values.direktorat = store.user.data?.direktorat

  if (!isRead) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
    values.m_dir_id = store.user.data?.m_dir_id ?? null
    if (!values.tanggal) {
      values.tanggal = getTodayDate()
    }
  }

  if (isRead) {
    //  READ DATA
    try {
      let dataURL = ''
      let dataURLAprv = ''
      let resAprv = ''
      if (route.query.is_approval) {
        dataURLAprv = `${store.server.url_backend}/operation/t_lembur/detail?id=${route.params.id}`
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
        values.approval = resultJson?.data.approval
        values.trx = resultJson?.data.trx
        values.datalog = resultJson?.data.approval_log
        initialValues = resultTrxJson.data

        // logic finish & Approved data
        isApproved.value = resultTrxJson?.data?.lembur_status == 'APPROVED' ? true : false
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
        // logic Approved data
        isApproved.value = resultJson?.data?.status == 'APPROVED' ? true : false

        for (const key in initialValues) {
          values[key] = initialValues[key]
        }

        // Jika status transaksi IN APPROVAL, load juga info approval & cek akses approval
        if (values.status === 'IN APPROVAL') {
          try {
            const apiApp = await fetch(`${store.server.url_backend}/operation/t_lembur/detail?trx_id=${editedId}`, {
              headers: {
                'Content-Type': 'Application/json',
                Authorization: `${store.user.token_type} ${store.user.token}`
              },
            })
            if (apiApp.ok) {
              const resAppJson = await apiApp.json()
              values.approval = resAppJson.data?.approval
              values.trx = resAppJson.data?.trx
              values.datalog = resAppJson.data?.approval_log
              values.can_approve = resAppJson.data?.can_approve
              values.is_atasan = resAppJson.data?.is_atasan
            }
          } catch (e) {
            console.error('Failed to load approval detail for IN APPROVAL status', e)
          }
        }
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

  if (!values.m_comp_id) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
  }

  hitungDurasi()
})
onMounted(() => {
  if (!is_superadmin.value) {
    values.m_kary_id = store.user.data?.m_kary_id
  }
  hitungDurasi()
})

function hitungMenitJs(from, to) {
  if (!from || !to) return 0;
  const [h1, m1] = from.split(':').map(Number);
  const [h2, m2] = to.split(':').map(Number);
  let total1 = h1 * 60 + m1;
  let total2 = h2 * 60 + m2;
  if (total2 < total1) total2 += 24 * 60; // lembur lintas tengah malam
  return Math.max(0, total2 - total1);
}

function hitungDurasi() {
  if (values.jam_mulai && values.jam_selesai) {
    const spkMin = hitungMenitJs(values.jam_mulai, values.jam_selesai);
    values.interval_min = values.interval_min || spkMin;

    if (values.jam_mulai_realisasi && values.jam_selesai_realisasi) {
      const realMin = hitungMenitJs(values.jam_mulai_realisasi, values.jam_selesai_realisasi);
      values.realisasi_min = realMin;
      // MoM BBS 7 Okt 2026 Poin 3.K: min(durasi_spk, durasi_realisasi)
      values.diakui_min = Math.min(spkMin, realMin);
    } else {
      values.realisasi_min = null;
      values.diakui_min = values.interval_min || spkMin;
    }

    if (values.tarif_lembur && !values.total_lembur) {
      values.total_lembur = Math.round((values.diakui_min / 60) * Number(values.tarif_lembur));
    }
  }
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
      if (!isRead && !values.tanggal) {
        values.tanggal = getTodayDate()
      }
    }
  })
}

let dataLog = reactive({ items: [] })
async function loadLog(id) {
  const targetId = id || values.trx?.id || values.id || route.params.id
  const url = `${store.server.url_backend}/operation/t_lembur/log?id=${targetId}`
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

function onSave() {
  try {

    values.status = (values.status === true) ? 'POSTED' : 'DRAFT'
    // if (values.jam_mulai && values.jam_selesai && values.jam_selesai < values.jam_mulai) {
    //   isBadForm.value = true;
    //   swal.fire({
    //     icon: 'error',
    //     text: 'Jam Selesai tidak boleh lebih kecil dari Jam Mulai',
    //   });
    //   return; 
    // }


    // const jamMulai = new Date(`2000-01-01 ${values.jam_mulai}`);
    // const jamSelesai = new Date(`2000-01-01 ${values.jam_selesai}`);
    // const selisihJam = (jamSelesai - jamMulai) / (1000 * 60 * 60); 

    // if (selisihJam > 5) {
    //   isBadForm.value = true;
    //   swal.fire({
    //     icon: 'error',
    //     text: 'Jam lembur tidak dapat melebihi 5 Jam',
    //   });
    //   return; 
    // }




    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value)
    if (values.status === 'REVISED') {
      values.status = 'DRAFT'; // Change status to DRAFT if it was REVISED
    }
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    isRequesting.value = true

    fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(values)
    }).then(async (res) => {
      if (!res.ok) {
        if ([400, 422].includes(res.status)) {
          const responseJson = await res.json()
          formErrors.value = responseJson.errors || {}
          throw new Error(responseJson.message || "Failed when trying to post data")
        } else {
          throw new Error("Failed when trying to post data")
        }
      }
      router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())))
    }).catch((err) => {
      isBadForm.value = true
      swal.fire({
        icon: 'error',
        text: 'Harap Lengkapi Data'
      })
    }).finally(() => {
      isRequesting.value = false
    });
  } catch (err) {
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: 'Harap Lengkapi Data'
    })
    isRequesting.value = false
  }
}


function onProcess(typePar) {
  const payload = {
    id: values.approval?.id || route.params.id,
    type: typePar === 'revise' ? 'REVISED' : (typePar === 'reject' ? 'REJECTED' : 'APPROVED'),
    note: values.catatan || (typePar === 'approve' ? 'Approved' : (typePar === 'revise' ? 'Revised' : 'Rejected')),
  };

  swal.fire({
    icon: 'warning',
    text: typePar === 'revise' ? 'Revised data?' : (typePar === 'reject' ? 'Rejected data?' : 'Approved data?'),
    showDenyButton: true,
  }).then(async (res) => {
    if (res.isConfirmed) {
      try {
        const dataURL = `${store.server.url_backend}/operation/t_lembur/progress`;
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
          if (route.query.is_approval) {
            router.replace('/notifikasi');
          } else {
            router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())));
          }
        }
      } catch (err) {
        isBadForm.value = true;
        swal.fire({
          icon: 'error',
          text: err?.message || err || 'Failed when trying to post data',
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
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      show: (row) => row.status?.toUpperCase() === 'DRAFT',
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
      show: (row) => row.status?.toUpperCase() === 'DRAFT' || row.status?.toUpperCase() === 'REVISED',
      // show: (row) => (currentMenu?.can_update)||store.user.data.username==='developer',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&` + tsId)
      }
    },
    {
      icon: 'copy',
      title: "Copy",
      class: 'bg-gray-600 text-light-100',
      show: (row) => row.status?.toUpperCase() === 'DRAFT',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&` + tsId)
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
              const dataURL = `${store.server.url_backend}/operation/t_lembur/send_approval`
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
                  throw (responseJson.message + " " + responseJson.data.errorText || "Failed when trying to post data")
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
      searchfield: 'm_kary.nama_depan, tanggal, jam_mulai, jam_selesai, tipe_lembur.value, status, m_comp.nama, m_dir.nama',
      where: `${!store.user.data?.is_superadmin ? ('this.m_kary_id=' + store.user.data?.m_kary_id ?? 0) : ''}`
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
    field: 'tanggal',
    headerName: 'Tanggal Lembur',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'jam_mulai',
    headerName: 'Jam Mulai',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'jam_selesai',
    headerName: 'Jam Akhir',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'tipe_lembur.value',
    headerName: 'Tipe Lembur',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'interval_min',
    headerName: 'Durasi SPK',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const min = params.data?.interval_min;
      return min ? `${min} mnt (${(min/60).toFixed(1)} jam)` : '-';
    }
  },
  {
    field: 'realisasi',
    headerName: 'Jam Realisasi',
    filter: false,
    sortable: false,
    flex: 1,
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    valueGetter: (params) => {
      const inReal = params.data?.jam_mulai_realisasi;
      const outReal = params.data?.jam_selesai_realisasi;
      return (inReal && outReal) ? `${inReal} - ${outReal}` : '-';
    }
  },
  {
    field: 'diakui_min',
    headerName: 'Durasi Diakui',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-center', 'font-semibold', '!text-blue-800'],
    valueGetter: (params) => {
      const min = params.data?.diakui_min;
      return min ? `${min} mnt (${(min/60).toFixed(1)} jam)` : '-';
    }
  },
  {
    field: 'total_lembur',
    headerName: 'Total Lembur',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-end', 'font-semibold'],
    valueGetter: (params) => {
      const tot = params.data?.total_lembur;
      return tot ? `Rp ${Number(tot).toLocaleString('id-ID')}` : '-';
    }
  },
  {
    headerName: 'Status',
    field: 'status',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
      let color = 'gray'
      if (value == 'APPROVED')
        color = 'green'
      else if (value == 'IN APPROVAL')
        color = 'blue'
      else if (value == 'REVISED')
        color = 'yellow'
      else if (value == 'REJECTED')
        color = 'red'
      return `<span class="text-${color}-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">${value}</span>`
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