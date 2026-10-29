//   javascriptimport { useRouter, useRoute, RouterLink } from 'vue-router'
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
const formErrors = ref({})
const tsId = `ts=`+(Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/m_standart_gaji'
onBeforeMount(()=>{
  document.title = 'Master Standart Gaji'
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []

const values = reactive({
  is_active: 1,
  direktorat: store.user.data?.direktorat,
  gaji_pokok_periode: 'Bulanan',
  uang_saku_periode: 'Bulanan',
  tunjangan_posisi_periode: 'Bulanan',
  tunjangan_kemahalan_periode: 'Bulanan',
  uang_makan_periode: 'Bulanan',
  tunjangan_tetap_periode: 'Bulanan',
})

watchEffect(() => {
  updateTunjanganKemahalan();
});

function updateTunjanganKemahalan() {
  if (values.grading_id && values.m_zona_id) {
    fetchTunjanganKemahalan(values.grading_id, values.m_zona_id);
  }
}

async function fetchTunjanganKemahalan(gradingId, zonaId) {
  try {
    const response = await fetch(`${store.server.url_backend}/operation/m_tunj_kemahalan?`+ new URLSearchParams({where: `this.is_active='true' AND this.m_zona_id=${zonaId} AND this.grading_id=${gradingId}`}), {
      method: 'GET',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },

      // ajarane sopo get ngirim body
      // body: JSON.stringify({
      //   where: `this.is_active='true' AND this.m_zona_id=${zonaId} AND this.grading_id=${gradingId}`,
      // })
    });

    if (!response.ok) {
      throw new Error('Failed to fetch tunjangan kemahalan data');
    }

    const data = await response.json();
    console.log(data.data.length)
    if (data.data.length > 0) {
      values.tunjangan_kemahalan_id = data.data[0].id;
    }
  } catch (error) {
    console.error('Error fetching tunjangan kemahalan:', error);

  }
}



onBeforeMount(async () => {

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
      initialValues.is_active = (initialValues.is_active === true || initialValues.is_active == 1 || initialValues.is_active === '1' || initialValues.is_active === 'true') ? 1 : 0
      detailArr.value = []
      _id = 0
      initialValues.m_standart_gaji_det?.forEach((items)=>{
        detailArr.value.push({ ...items, __id: ++_id })
      })
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
  } else {
    values.is_active = 1
    detailArr.value = []
  }

  for (const key in initialValues) {
    values[key] = initialValues[key]
  }
  if (isRead) {
    values.is_active = (initialValues.is_active === true || initialValues.is_active == 1 || initialValues.is_active === '1' || initialValues.is_active === 'true') ? 1 : 0
  }
})

let _id = 0
const detailArr = ref([])
const addDetail = () => {
  const tempItem = {
    __id: ++_id,
    komponen: '',
    faktor: '+',
    nilai: 0,
    periode: 'Bulanan',
  }
  detailArr.value = [...detailArr.value, tempItem]
}

const removeDetail = (detailItem) => {
  detailArr.value = detailArr.value.filter((e) => e.__id != detailItem.__id)
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
      if (isRead) {
        for (const key in initialValues) {
          values[key] = initialValues[key]
        }
        values.is_active = (initialValues.is_active === true || initialValues.is_active == 1 || initialValues.is_active === '1' || initialValues.is_active === 'true') ? 1 : 0
        detailArr.value = []
        _id = 0
        if (initialValues.m_standart_gaji_det && Array.isArray(initialValues.m_standart_gaji_det)) {
          initialValues.m_standart_gaji_det.forEach((item) => {
            detailArr.value.push({ ...item, __id: ++_id })
          })
        }
      } else {
        for (const key in values) {
          delete values[key]
        }
        values.is_active = 1
        values.direktorat = store.user.data?.direktorat
        values.gaji_pokok_periode = 'Bulanan'
        values.uang_saku_periode = 'Bulanan'
        values.tunjangan_posisi_periode = 'Bulanan'
        values.tunjangan_kemahalan_periode = 'Bulanan'
        values.uang_makan_periode = 'Bulanan'
        values.tunjangan_tetap_periode = 'Bulanan'
        detailArr.value = []
      }
      formErrors.value = {}
    }
  })
}

async function onSave() {
  try {
    if (!detailArr.value || detailArr.value.length === 0) {
      swal.fire({
        icon: 'warning',
        title: 'Perhatian',
        text: 'Detail komponen gaji belum diisi. Silakan tambahkan minimal 1 detail komponen gaji.',
      })
      return
    }

    const invalidRow = detailArr.value.find(item => !item.komponen || item.nilai === undefined || item.nilai === null || item.nilai === '')
    if (invalidRow) {
      swal.fire({
        icon: 'warning',
        title: 'Perhatian',
        text: 'Mohon lengkapi Komponen dan Nilai pada setiap baris tabel detail.',
      })
      return
    }

    values.is_active = (values.is_active === true || values.is_active == 1 || values.is_active === '1' || values.is_active === 'true') ? 1 : 0
    values.m_standart_gaji_det = detailArr.value
    const isCreating = ['Create','Copy','Tambah'].includes(actionText.value)
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
    isRequesting.value = true
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
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
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
              if (!res.ok) {
                const resultJson = await res.json()
                throw (resultJson.message || "Failed when trying to remove data")
              }
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
      // show: (row) => (currentMenu?.can_update)||store.user.data.username==='developer',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Edit&`+tsId)
      }
    },
    {
      icon: 'copy',
      title: "Copy",
      class: 'bg-gray-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&`+tsId)
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
      searchfield:'this.id, this.kode, m_general.value, m_zona.nama, m_posisi.desc_kerja, this.is_active',
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
    headerName:"Kode",
    field: 'kode',
    filter: true,
    sortable: true,
    flex:1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName:"Grading",
    field: 'grading.value',
    filter: true,
    sortable: true,
    width: 100,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data['grading.value'] || p.data['grading.code'] || '-',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center']
  },
  {
    headerName:"Zona",
    field: 'm_zona.nama',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName:"Posisi",
    field: 'm_posisi.desc_kerja',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    valueGetter: (p) => p.data['m_posisi.desc_kerja'] || '-',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName:"Gaji Pokok",
    field: 'gaji_pokok',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    flex:1,
    valueGetter: (p) => p.data.gaji_pokok ? 'Rp ' + Number(p.data.gaji_pokok).toLocaleString('id-ID') : 'Rp 0',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-end']
  },
  {
    headerName:"Periode",
    field: 'gaji_pokok_periode',
    filter: true,
    sortable: true,
    filter: 'ColFilter',
    resizable: true,
    width: 110,
    valueGetter: (p) => p.data.gaji_pokok_periode || 'Bulanan',
    cellClass: [ 'border-r', '!border-gray-200', 'justify-center']
  },
  {
    headerName: "Status",
    field: 'status',
    filter: 'ColFilter',
    filterParams: {
      options: ['Active', 'Inactive']
    },
    filterValueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    sortable: true,
    resizable: true,
    width: 100,
    valueGetter: (p) => {
      const val = p.data ? (p.data.status !== undefined ? p.data.status : p.data.is_active) : p.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    cellClass: (p) => {
      const val = p.data ? (p.data.status !== undefined ? p.data.status : p.data.is_active) : p.value
      const isActive = val === 'Active' || val === true || val === 1 || val === '1' || val === 'true'
      return [ 'border-r', '!border-gray-200', 'justify-center', 'font-semibold', isActive ? '!text-green-600' : '!text-red-500']
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
watchEffect(()=>store.commit('set', ['isRequesting', isRequesting.value]))
