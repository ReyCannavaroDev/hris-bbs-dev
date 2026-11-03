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
const formErrors = ref({})
const tsId = `ts=`+(Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/t_potongan'
onBeforeMount(()=>{
  document.title = 'Transaksi Potongan'
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []

const values = reactive({
  m_comp_id: null,
  m_dir_id: null,
  status: 'DRAFT',
  is_all_kary: false,
  is_lunas: false
})


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

async function posted() {
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
        throw (responseJson.message || "Failed when trying to post data")
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
  console.log(values)
  //values.tags = JSON.stringify(values.tags)
  try {
    const isCreating = ['Create','Copy','Tambah'].includes(actionText.value)
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    isRequesting.value = true
    values.status = values.status === 'DRAFT' ? 'DRAFT' : 'POSTED'
    values.is_all_kary = values.is_all_kary === true ? 1 : 0
    values.is_lunas = values.is_lunas === true ? 1 : 0 
    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(values)
    })
    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json()
        formErrors.value = responseJson.errors || {}
        throw new Error(responseJson.message || "Failed when trying to post data")
      } else {
        throw new Error("Failed when trying to post data")
      }
    }
    router.replace('/' + modulPath + '?reload='+(Date.parse(new Date())))
  } catch (err) {
    console.log(err)
    isBadForm.value = true
    swal.fire({
      icon: 'error',
      text: err.message
    })
  }
  isRequesting.value = false
}


function pelunasanManual(row) {
  const sisa = row.sisa_angsuran !== undefined ? row.sisa_angsuran : (row.nilai || 0)
  const namaKaryawan = row.is_all_kary ? 'Semua Karyawan' : (row['m_kary.nama_lengkap'] || row['m_kary.nama_depan'] || row.karyawan || 'Karyawan')
  const nominalStr = 'Rp ' + Number(sisa).toLocaleString('id-ID')

  swal.fire({
    title: 'Pelunasan Manual Piutang',
    html: `<div class="text-left text-sm">
             <p class="mb-3">Apakah Anda yakin ingin melakukan <b>Pelunasan Manual di Luar Sistem</b> untuk piutang nomor <b>${row.nomor || '-'}</b>?</p>
             <div class="bg-gray-100 p-3 rounded border space-y-1">
               <div><b>Karyawan:</b> ${namaKaryawan}</div>
               <div><b>Sisa Angsuran:</b> <span class="text-red-600 font-bold">${nominalStr}</span></div>
             </div>
             <p class="text-xs text-gray-500 mt-3 leading-relaxed">
               Aksi ini digunakan apabila karyawan (misal: resign) melunasi sisa kasbon langsung di luar payroll rutin. Status piutang akan langsung berubah menjadi <b>LUNAS</b>.
             </p>
           </div>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Lunasi Sekarang',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#EF4444'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        isRequesting.value = true
        const dataURL = `${store.server.url_backend}/operation${endpointApi}/pelunasanManual`
        const res = await fetch(dataURL, {
          method: 'POST',
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
          body: JSON.stringify({ id: row.id })
        })
        const resJson = await res.json()
        if (!res.ok) {
          throw (resJson.error || resJson.message || 'Gagal melakukan pelunasan manual')
        }
        swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: resJson.message || 'Piutang berhasil dilunasi secara manual'
        })
        if (apiTable.value) {
          apiTable.value.reload()
        }
      } catch (err) {
        swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: typeof err === 'string' ? err : (err.message || 'Terjadi kesalahan sistem')
        })
      } finally {
        isRequesting.value = false
      }
    }
  })
}

function onPelunasanManualForm() {
  pelunasanManual({
    id: route.params.id,
    nomor: values.nomor,
    'm_kary.nama_lengkap': values.m_kary_nama || values['m_kary.nama_lengkap'],
    is_all_kary: values.is_all_kary,
    nilai: values.nilai,
    sisa_angsuran: values.sisa_angsuran
  })
}


//  @else----------------------- LANDING
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      title: "Hapus",
                  show: (row) =>row.status?.toUpperCase() === 'DRAFT ',
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
        router.push(`${route.path}/${row.id}?`+tsId)
      }
    },
    {
      icon: 'edit',
      title: "Edit",
      class: 'bg-blue-600 text-light-100',
      show: (row) =>row.status?.toUpperCase() === 'DRAFT',
      // show: (row) => (currentMenu?.can_update)||store.user.data.username==='developer',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&`+tsId)
      }
    },
    {
      icon: 'copy',
      title: "Copy",
      class: 'bg-gray-600 text-light-100',
      show: (row) =>row.status?.toUpperCase() === 'DRAFT ',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&`+tsId)
      }
    },
    {
      icon: 'paper-plane',
      title: "Posted Data",
      class: 'bg-rose-700 rounded-lg text-white',
      show: (row) =>row.status?.toUpperCase() === 'DRAFT',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Verifikasi&`+tsId)
      }
    },
    {
      icon: 'check-circle',
      title: "Pelunasan Manual",
      class: 'bg-emerald-600 rounded-lg text-white',
      show: (row) => row.status?.toUpperCase() === 'POSTED' && !row.is_lunas,
      click(row) {
        pelunasanManual(row)
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
      searchfield:'this.id, this.nomor, m_comp.nama, m_dir.nama, m_kary.nama_depan, m_general.value, this.keterangan, this.status',
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
    headerName: 'Nomor',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    field: 'm_dir.nama',
    headerName: 'Company',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data['m_dir.nama'] || '-',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Karyawan',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data.is_all_kary ? 'Semua Karyawan' : (p.data['m_kary.nama_depan'] || p.data['m_kary.nama_lengkap'] || '-'),
    cellClass: (p) => [ 'border-r', '!border-gray-200', p.data.is_all_kary ? '!text-blue-600 font-semibold justify-center' : 'justify-left']
  },
  {
    headerName: 'Jenis Potongan',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data['m_general.value'] || p.data['jenis_potongan.value'] || '-',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-left']
  },
  {
    headerName: 'Nilai Total',
    field: 'nilai',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data.nilai ? 'Rp ' + Number(p.data.nilai).toLocaleString('id-ID') : 'Rp 0',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-end', 'font-medium']
  },
  {
    headerName: 'Sisa Angsuran',
    field: 'sisa_angsuran',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => {
      if (p.data.is_lunas) return 'Rp 0';
      const sisa = p.data.sisa_angsuran !== undefined ? p.data.sisa_angsuran : p.data.nilai;
      return sisa ? 'Rp ' + Number(sisa).toLocaleString('id-ID') : 'Rp 0';
    },
    cellClass: (p) => [
      'border-r', 
      '!border-gray-200', 
      'justify-end', 
      'font-bold',
      p.data.is_lunas ? 'text-gray-400' : 'text-red-600'
    ]
  },
  {
    headerName: 'Periode',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => (p.data.date_from && p.data.date_to) ? `${p.data.date_from} s/d ${p.data.date_to}` : '-',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center', 'text-xs']
  },
  {
    field: 'status',
    headerName: 'Status',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    width: 110,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
    return value 
      === "POSTED"
      ? `<span class="text-green-600 font-semibold text-xs px-2 py-1 rounded bg-green-50 border border-green-200">${value}</span>`
      : `<span class="text-amber-600 font-semibold text-xs px-2 py-1 rounded bg-amber-50 border border-amber-200">${value}</span>`
  }},
  {
    headerName: 'Pelunasan',
    filter: true,
    sortable: true,
    width: 120,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data.is_lunas ? 'LUNAS' : 'BELUM LUNAS',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
    return value === "LUNAS"
      ? `<span class="text-emerald-700 font-bold text-xs px-2 py-1 rounded bg-emerald-100">${value}</span>`
      : `<span class="text-orange-700 font-semibold text-xs px-2 py-1 rounded bg-orange-100">${value}</span>`
  }},
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