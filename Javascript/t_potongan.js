//   javascript

import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, computed } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isTruthyFlag = (val) => val === true || val === 1 || val === '1' || String(val || '').toLowerCase() === 'true' || String(val || '').toLowerCase() === 't'
const isSuperadmin = computed(() => {
  const u = store?.user?.data || {}
  const ar = u.active_respo || null
  if (u.username === 'developer') return true
  if (ar && typeof ar.is_superadmin !== 'undefined') {
    return isTruthyFlag(ar.is_superadmin)
  }
  return isTruthyFlag(u.is_superadmin)
})

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

function pelunasanManual(row) {
  const target = (Array.isArray(row) ? row[0] : (row?.data || row)) || (typeof values !== 'undefined' && values?.id ? values : null)

  if (!target || !target.id) {
    swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih data transaksi terlebih dahulu untuk melakukan pelunasan manual.'
    })
    return
  }

  const rawSisa = target.sisa_angsuran !== undefined ? target.sisa_angsuran : (target.nilai || 0)
  const numSisa = Number(String(rawSisa).replace(/[^0-9.-]/g, '')) || 0
  const nominalStr = 'Rp ' + numSisa.toLocaleString('id-ID')
  const namaKaryawan = target.is_all_kary ? 'Semua Karyawan' : (target['m_kary.nama_lengkap'] || target['m_kary.nama_depan'] || target.karyawan || target.m_kary_nama || 'Karyawan')
  const todayStr = new Date().toISOString().split('T')[0]

  swal.fire({
    title: 'Pelunasan Manual Piutang',
    html: `<div class="text-left text-sm">
             <p class="mb-3">Apakah Anda yakin ingin melakukan <b>Pelunasan Manual di Luar Sistem</b> untuk piutang nomor <b>${target.nomor || '-'}</b>?</p>
             <div class="bg-gray-100 p-3 rounded border space-y-1 mb-3">
               <div><b>Karyawan:</b> ${namaKaryawan}</div>
               <div><b>Sisa Angsuran:</b> <span class="text-red-600 font-bold">${nominalStr}</span></div>
             </div>
             <div class="mb-3">
               <label class="block text-xs font-semibold text-gray-700 mb-1">Tanggal Pelunasan (Bisa Disesuaikan):</label>
               <input type="date" id="swal_paid_at" value="${todayStr}" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
               <small class="text-gray-500 text-[11px] mt-0.5 block">*Default hari ini, ubah jika pembayaran riil diterima pada tanggal lain.</small>
             </div>
             <p class="text-xs text-gray-500 mt-2 leading-relaxed">
               Aksi ini digunakan apabila karyawan (misal: resign) melunasi sisa kasbon langsung di luar payroll rutin. Status piutang akan langsung berubah menjadi <b>LUNAS</b>.
             </p>
           </div>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Lunasi Sekarang',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#EF4444',
    preConfirm: () => {
      const paidAtInput = document.getElementById('swal_paid_at')
      const paidAt = paidAtInput ? paidAtInput.value : ''
      if (!paidAt) {
        if (typeof swal.showValidationMessage === 'function') {
          swal.showValidationMessage('Tanggal pelunasan wajib diisi')
        }
        return false
      }
      return { paid_at: paidAt }
    }
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        isRequesting.value = true
        const paidAtVal = result.value?.paid_at || document.getElementById('swal_paid_at')?.value || todayStr
        const dataURL = `${store.server.url_backend}/operation${endpointApi}/pelunasanManual`
        const res = await fetch(dataURL, {
          method: 'POST',
          headers: {
            'Content-Type': 'Application/json',
            Authorization: `${store.user.token_type} ${store.user.token}`
          },
          body: JSON.stringify({ 
            id: target.id,
            paid_at: paidAtVal
          })
        })
        const resJson = await res.json().catch(() => null)
        if (!res.ok) {
          throw (resJson?.error || resJson?.message || 'Gagal melakukan pelunasan manual')
        }
        swal.fire({
          icon: 'success',
          title: 'Berhasil',
          text: resJson?.message || 'Piutang berhasil dilunasi secara manual'
        })
        if (apiTable.value) {
          apiTable.value.reload()
        }
        if (typeof values !== 'undefined' && values && route.params?.id) {
          values.is_lunas = 1
          values.status_bayar = 'LUNAS MANUAL'
          values.sisa_angsuran = 0
        }
      } catch (err) {
        swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: typeof err === 'string' ? err : (err?.message || 'Terjadi kesalahan sistem')
        })
      } finally {
        isRequesting.value = false
      }
    }
  })
}

async function showHistoryBayar(row) {
  const selectedRow = Array.isArray(row) ? row[0] : (row?.data || row)
  const id = selectedRow?.id || route.params?.id

  if (!id) {
    swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Pilih data transaksi terlebih dahulu untuk melihat riwayat pembayaran.'
    })
    return
  }

  try {
    isRequesting.value = true
    const dataURL = `${store.server.url_backend}/operation${endpointApi}/getHistoryBayar?id=${id}`
    const res = await fetch(dataURL, {
      method: 'GET',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      }
    })
    const resJson = await res.json().catch(() => null)
    if (!res.ok) {
      throw (resJson?.error || resJson?.message || `HTTP ${res.status}: Gagal mengambil riwayat pembayaran`)
    }

    const { potongan, history } = resJson
    const fallbackNama = typeof values !== 'undefined' ? (values.m_kary_nama || values['m_kary.nama_lengkap']) : null
    const namaKaryawan = selectedRow?.is_all_kary ? 'Semua Karyawan' : (selectedRow?.['m_kary.nama_lengkap'] || selectedRow?.['m_kary.nama_depan'] || selectedRow?.karyawan || fallbackNama || 'Karyawan')

    let rowsHtml = ''
    if (!history || history.length === 0) {
      rowsHtml = `<tr><td colspan="4" class="text-center py-4 text-gray-500 italic">Belum ada catatan pemotongan atau pembayaran untuk piutang ini.</td></tr>`
    } else {
      rowsHtml = history.map((item) => `
        <tr class="border-b hover:bg-gray-50 text-xs">
          <td class="py-2.5 px-3 text-center font-medium">${item.no}</td>
          <td class="py-2.5 px-3 text-center">${item.paid_date}</td>
          <td class="py-2.5 px-3">
            <span class="${item.metode === 'POTONG GAJI' ? 'text-blue-700 bg-blue-50 border border-blue-200' : 'text-emerald-700 bg-emerald-50 border border-emerald-200'} px-2 py-0.5 rounded font-semibold text-[11px]">
              ${item.metode}
            </span>
            <div class="text-[11px] text-gray-500 mt-1">${item.keterangan}</div>
          </td>
          <td class="py-2.5 px-3 text-right font-bold text-gray-800">${item.nilai_formatted}</td>
        </tr>
      `).join('')
    }

    const modalHtml = `
      <div class="text-left text-sm space-y-3">
        <div class="bg-gray-50 p-3.5 rounded-lg border text-xs grid grid-cols-2 gap-2.5">
          <div><span class="text-gray-500">Nomor Piutang:</span> <b class="text-gray-800">${potongan.nomor || '-'}</b></div>
          <div><span class="text-gray-500">Karyawan:</span> <b class="text-gray-800">${namaKaryawan}</b></div>
          <div><span class="text-gray-500">Total Pinjaman:</span> <b class="text-gray-800">${potongan.nilai_formatted}</b></div>
          <div><span class="text-gray-500">Total Terbayar:</span> <b class="text-emerald-600">${potongan.total_bayar_formatted}</b></div>
          <div><span class="text-gray-500">Sisa Angsuran:</span> <b class="${potongan.is_lunas ? 'text-gray-500' : 'text-red-600 font-bold'}">${potongan.sisa_formatted}</b></div>
          <div><span class="text-gray-500">Status Pelunasan:</span> <span class="font-bold ${potongan.is_lunas ? 'text-emerald-600' : 'text-orange-600'}">${potongan.status_bayar}</span></div>
        </div>

        <div class="border rounded-lg overflow-hidden max-h-64 overflow-y-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-gray-100 text-gray-700 text-xs font-semibold uppercase sticky top-0">
              <tr>
                <th class="py-2.5 px-3 text-center w-12">No</th>
                <th class="py-2.5 px-3 text-center w-28">Tgl Bayar</th>
                <th class="py-2.5 px-3">Metode / Keterangan</th>
                <th class="py-2.5 px-3 text-right w-36">Nominal</th>
              </tr>
            </thead>
            <tbody>
              ${rowsHtml}
            </tbody>
          </table>
        </div>
      </div>
    `

    swal.fire({
      title: 'Riwayat Pembayaran & Pelunasan',
      html: modalHtml,
      width: '620px',
      showCloseButton: true,
      confirmButtonText: 'Tutup',
      confirmButtonColor: '#3B82F6'
    })
  } catch (err) {
    swal.fire({
      icon: 'error',
      title: 'Gagal Memuat Riwayat',
      text: typeof err === 'string' ? err : (err.message || 'Terjadi kesalahan sistem')
    })
  } finally {
    isRequesting.value = false
  }
}

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
    const activeRespo = store.user?.data?.active_respo
    const respoDirId = activeRespo ? (activeRespo.m_dir_id ?? null) : (store.user?.data?.m_dir_id ?? null)
    values.m_dir_id = respoDirId ? Number(respoDirId) : null
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

function onShowHistoryBayarForm() {
  showHistoryBayar({
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
      show: (row) => {
        const r = Array.isArray(row) ? row[0] : row
        return r?.status?.toUpperCase() === 'POSTED' && !r?.is_lunas
      },
      click(row) {
        pelunasanManual(row)
      }
    },
    {
      icon: 'history',
      title: "Riwayat Pembayaran",
      class: 'bg-blue-600 rounded-lg text-white',
      show: (row) => {
        const r = Array.isArray(row) ? row[0] : row
        return r?.status?.toUpperCase() === 'POSTED'
      },
      click(row) {
        showHistoryBayar(row)
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