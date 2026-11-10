import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, computed } from 'vue'

const router = useRouter()
const route = useRoute()
const store = inject('store')
const swal = inject('swal')

const isTruthyFlag = (val) => val === true || val === 1 || val === '1' || String(val || '').toLowerCase() === 'true' || String(val || '').toLowerCase() === 't'

const getUserSnapshot = () => {
  const storeData = store.user?.data || {}
  let localData = {}
  if (typeof window !== 'undefined' && window.localStorage?.user) {
    try {
      localData = JSON.parse(window.localStorage.user)?.data || {}
    } catch (e) {}
  }
  return { ...localData, ...storeData }
}

const isHrdOrAdminRoleName = (name) => /(?:^|[^a-z])(hr[a-z]*|personalia|sdm|admin[a-z]*|human[\s_]*capital|human[\s_]*resources?)(?:[^a-z]|$)/i.test(String(name || ''))

const getActiveRespoScope = () => {
  const u = getUserSnapshot()
  const ar = u.active_respo || null
  const isSuper = u.username === 'developer' || isTruthyFlag(u.is_superadmin)
  const hasRespo = isTruthyFlag(u.has_respo) || Boolean(ar)
  const dirId = ar ? (ar.m_dir_id ?? null) : (u.m_dir_id ?? null)
  const divId = ar ? (ar.m_divisi_id ?? null) : null
  const deptId = ar ? (ar.m_dept_id ?? null) : null
  const isHolding = hasRespo ? (ar ? !dirId : false) : !dirId
  const rawRoles = Array.isArray(u.roles) && u.roles.length
    ? u.roles.map(r => (typeof r === 'string' ? r : (r?.name || '')))
    : (typeof u.role_names === 'string' && u.role_names.trim()
        ? u.role_names.split(',').map(s => s.trim())
        : (Array.isArray(u.role_names) ? u.role_names : []))
  const isHrdOrAdmin = isSuper || (!(hasRespo && !ar) && rawRoles.some(rn => isHrdOrAdminRoleName(rn)))
  return {
    isSuper,
    hasRespo,
    isHrdOrAdmin,
    isHolding,
    isUnrestricted: isSuper || isHolding,
    dirId: dirId ? Number(dirId) : null,
    divId: divId ? Number(divId) : null,
    deptId: deptId ? Number(deptId) : null,
    isLockedMissing: hasRespo && !ar
  }
}

const respoScope = ref(getActiveRespoScope())

const isSuperadmin = computed(() => respoScope.value.isSuper)

const getLandingWhereClause = () => {
  const scope = getActiveRespoScope()
  const u = getUserSnapshot()
  if (scope.isLockedMissing) {
    return '1=0'
  }
  if (!scope.isHrdOrAdmin) {
    return `this.m_kary_id=${u.m_kary_id || 0}`
  }
  return ''
}

const isRead = route.params.id && route.params.id !== 'create'
const actionText = ref(route.params.id === 'create' ? 'Tambah' : route.query.action)
const isBadForm = ref(false)
const isRequesting = ref(false)
const modulPath = route.params.modul
const currentMenu = store.currentMenu
const apiTable = ref(null)
const formErrors = ref({})
const tsId = `ts=`+(Date.parse(new Date()))
const readValue = ref(true)
const adjKary = ref(route.query.action?.toLowerCase() === 'adjusment' ? true : false)

// ------------------------------ PERSIAPAN
const endpointApi = '/t_cuti_adjustment'
onBeforeMount(()=>{
  document.title = 'Adjusment Cuti'
})


//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS

let initialValues = {}
let tempInfo = {}
const changedValues = []
const informasiCuti = reactive({})

const values = reactive({
  date: new Date().toLocaleDateString('en-GB'),
})

const infoCuti = async (id) => {
  tempInfo = {}
  const dataURL = `${store.server.url_backend}/operation/m_kary/${id}`
  const params = { join: true, transform: false, detail: true }
  const fixedParams = new URLSearchParams(params)
  const res = await fetch(dataURL + '?' + fixedParams, {
    headers: {
      'Content-Type': 'Application/json',
      Authorization: `${store.user.token_type} ${store.user.token}`
    },
  })
  if (!res.ok) throw new Error("Failed when trying to read data")
  const resultJson = await res.json()
  let tempValues = resultJson.data
  if (tempValues) {
    if (tempValues.m_dir_id) {
      values.m_dir_id = tempValues.m_dir_id
    }
    if (tempValues.m_comp_id) {
      values.m_comp_id = tempValues.m_comp_id
    }
  }
  if(tempValues.info_cuti){
      for (let key in tempValues.info_cuti) {
          if (tempValues.info_cuti.hasOwnProperty(key) && tempValues.info_cuti[key] === null) {
              tempValues.info_cuti[key] = 0;
          }
      }
    }
  tempInfo = {...tempInfo, ...tempValues.info_cuti}
  Object.assign(informasiCuti,tempValues.info_cuti)
  Object.assign(values, tempValues.info_cuti)
  console.log(values)
  adjKary.value = true
}

const changeSisaCuti = async (data,value)=>{
  console.log(data,'fiq')
  parseInt(value??0)
  if(data === '01'){
    informasiCuti.sisa_cuti_p24 = tempInfo.sisa_cuti_p24
    informasiCuti.sisa_cuti_reguler = tempInfo.sisa_cuti_reguler
    informasiCuti.sisa_cuti_masa_kerja = tempInfo.sisa_cuti_masa_kerja +(value)
  }else if(data === '03'){
    informasiCuti.sisa_cuti_masa_kerja = tempInfo.sisa_cuti_masa_kerja
    informasiCuti.sisa_cuti_reguler = tempInfo.sisa_cuti_reguler
    informasiCuti.sisa_cuti_p24 = tempInfo.sisa_cuti_p24+(value)
  }else if(data === '02'){
    informasiCuti.sisa_cuti_masa_kerja = tempInfo.sisa_cuti_masa_kerja
    informasiCuti.sisa_cuti_p24 = tempInfo.sisa_cuti_p24
    informasiCuti.sisa_cuti_reguler = tempInfo.sisa_cuti_reguler+(value)
  }
}

const setNullInfoCuti = (v)=> {
  if(v === '01'){
    informasiCuti.sisa_cuti_p24 = tempInfo.sisa_cuti_p24
    informasiCuti.sisa_cuti_reguler = tempInfo.sisa_cuti_reguler
  }else if(v === '03'){
    informasiCuti.sisa_cuti_masa_kerja = tempInfo.sisa_cuti_masa_kerja
    informasiCuti.sisa_cuti_reguler = tempInfo.sisa_cuti_reguler
  }else if(v === '02'){
    informasiCuti.sisa_cuti_masa_kerja = tempInfo.sisa_cuti_masa_kerja
    informasiCuti.sisa_cuti_p24 = tempInfo.sisa_cuti_p24
  }
  if(values.value!==null){
    values.value=null
  }
}
onBeforeMount(async () => {
  const scope = getActiveRespoScope()
  respoScope.value = scope
  console.log(adjKary.value)
    //  READ DATA
    
  if (isRead) {
    try {
      if(actionText.value === undefined){
        readValue.value = true
      }
    isRequesting.value = true
      const editedId = route.params.id
      const dataURL = adjKary.value === true ? `${store.server.url_backend}/operation/m_kary/${editedId}` : `${store.server.url_backend}/operation${endpointApi}/${editedId}`
      const params = { join: true, transform: false, ...(adjKary.value === true && { detail: true }) }
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
      if(initialValues.info_cuti){
          for (let key in initialValues.info_cuti) {
              if (initialValues.info_cuti.hasOwnProperty(key) && initialValues.info_cuti[key] === null) {
                  initialValues.info_cuti[key] = 0;
              }
          }
        }
        initialValues = {...initialValues, ...initialValues.info_cuti}
        if(adjKary.value === true){        
          initialValues.m_kary_id = initialValues.id
        }
        if (actionText.value === 'Copy' || route.query.action === 'Copy') {
          initialValues.nomor = null
          initialValues.status = 'DRAFT'
          delete initialValues.id
        }
        initialValues.tipe_key = initialValues['tipe_cuti.key']
        infoCuti(initialValues.m_kary_id)
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
})


function onBack() {
  adjKary.value === true ? router.replace('/m_karyawan') : router.replace('/' + modulPath)
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
  //values.tags = JSON.stringify(values.tags)
      try {
        const isCreating = ['Create','Copy','Tambah','Adjusment'].includes(actionText.value)
        const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`
        isRequesting.value = true
         values.is_active = values.is_active ? 1 : 0
        const tipeMap = {
          '01': 'sisa_cuti_masa_kerja',
          '02': 'sisa_cuti_reguler',
          '03': 'sisa_cuti_p24'
        }
        const activeKey = tipeMap[values.tipe_key]
        if (activeKey && informasiCuti[activeKey] != null && informasiCuti[activeKey] != undefined && informasiCuti[activeKey] < 0 && (tempInfo[activeKey] ?? 0) >= 0) {
          throw(`Data Tidak Boleh Kurang Dari 0`)
        }
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
            const responseJson = await res.json().catch(() => ({}))
            formErrors.value = responseJson.errors || {}
            const errs = responseJson.errors
            const errMsg = Array.isArray(errs)
              ? (errs[0] || responseJson.message)
              : (errs && typeof errs === 'object' ? Object.values(errs).flat()[0] : null)
            throw (errMsg || responseJson.message || "Failed when trying to post data")
          } else {
            const responseJson = await res.json().catch(() => ({}))
            throw (responseJson.error || responseJson.message || "Failed when trying to post data")
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
// LANDING LAMA 
const landing = reactive({
  actions: [
    {
      icon: 'trash',
      class: 'bg-red-600 text-light-100',
      show: (row) => respoScope.value.isHrdOrAdmin,
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
    show: (row) => respoScope.value.isHrdOrAdmin,
    click(row) {
      router.push(`${route.path}/${row.id}?action=Edit&` + tsId);
    }
  },
    {
      icon: 'copy',
      title: "Copy",
      show: (row) => respoScope.value.isHrdOrAdmin && row.status?.trim()?.toUpperCase() === 'DRAFT',
      class: 'bg-gray-600 text-light-100',
      click(row) {
        router.push(`${route.path}/${row.id}?action=Copy&`+tsId)
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
      join: true,
      where: getLandingWhereClause(),
      searchfield: 'nomor, m_kary.nama_depan, m_kary.nama_lengkap, m_dir.nama, tipe_cuti.value, value, keterangan, status',
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
    field: 'date',
    headerName: 'Tanggal',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
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
    field: 'm_kary.nama_depan',
    headerName: 'Nama Karyawan',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    valueGetter: (p) => p.data['m_kary.nama_lengkap'] || p.data['m_kary.nama_depan'] || '-',
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
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
  },{
    field: 'value',
    headerName: 'Nilai',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
  },
  {
    field: 'keterangan',
    headerName: 'Keterangan',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-left']
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