import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ref, readonly, reactive, inject, onMounted, onBeforeMount, watchEffect, onActivated, watch } from 'vue'

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
const activeTabIndex = ref(0)
const tsId = `ts=` + (Date.parse(new Date()))

// ------------------------------ PERSIAPAN
const endpointApi = '/m_jam_kerja'
onBeforeMount(() => {
  document.title = 'Master Jam Kerja'
})

//  @if( $id )------------------- VALUES FORM ! PENTING JANGAN DIHAPUS
let initialValues = {}
const changedValues = []
const checkedState = ref()

const values = reactive({
  is_active: true,
  is_hari_berikutnya: true,
  m_comp_id: null,
  m_dir_id: null,
})

onBeforeMount(async () => {
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
  if (actionText.value === 'Copy') {
    values.kode = null
  }
  if (values.waktu_mulai && values.waktu_mulai.length > 5) {
    values.waktu_mulai = values.waktu_mulai.slice(0, 5)
  }
  if (values.waktu_akhir && values.waktu_akhir.length > 5) {
    values.waktu_akhir = values.waktu_akhir.slice(0, 5)
  }
  if (!values.m_comp_id) {
    values.m_comp_id = store.user.data?.m_comp_id ?? 1
  }

  checkedState.value = values.hari_berikutnya
})

let _idPend = 0
const detailPendidikan = ref([])
const addPendidikan = () => {
  const tempItem = {
    id: ++_idPend,
    nama: values.nama,
    akses: values.akses,
    admin: values.admin
  }
  detailArr.value = [...detailArr.value, tempItem]
  Object.keys(values).forEach(key => delete values[key]);
  console.log(detailArr.value)
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
  try {

    if (!values.is_hari_berikutnya && values.waktu_akhir < values.waktu_mulai) {
      swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Waktu Berakhir tidak boleh lebih awal dari Waktu Mulai kecuali Hari Berikutnya dipilih.',
      });
      return;
    }

    if (!values.desc) {
      swal.fire({
        icon: 'error',
        title: 'Keterangan Belum Diisi',
        text: 'Silahkan untuk mengisi keterangan jam kerja terlebih dahulu!',
      });
      return;
    }

    const isCreating = ['Create', 'Copy', 'Tambah'].includes(actionText.value);
    const dataURL = `${store.server.url_backend}/operation${endpointApi}${isCreating ? '' : ('/' + route.params.id)}`;
    isRequesting.value = true;
    values.is_active = values.is_active ? 1 : 0;
    values.is_hari_berikutnya = values.is_hari_berikutnya ? 1 : 0;

    const res = await fetch(dataURL, {
      method: isCreating ? 'POST' : 'PUT',
      headers: {
        'Content-Type': 'Application/json',
        Authorization: `${store.user.token_type} ${store.user.token}`
      },
      body: JSON.stringify(values)
    });

    if (!res.ok) {
      if ([400, 422].includes(res.status)) {
        const responseJson = await res.json();
        formErrors.value = responseJson.errors || {};
        throw (responseJson.errors.length ? responseJson.errors[0] : responseJson.message || "Gagal saat mencoba mengirim data");
      } else {
        throw ("Gagal saat mencoba mengirim data");
      }
    }

    router.replace('/' + modulPath + '?reload=' + (Date.parse(new Date())));
  } catch (err) {
    isBadForm.value = true;
    swal.fire({
      icon: 'error',
      title: 'Error',
      text: err,
    });
  }
  isRequesting.value = false;
}

//  @else----------------------- LANDING
// Transform jam kerja query to apply action button concept to table column filters
function transformJamKerjaUrl(url) {
  try {
    if (!url || typeof url !== 'string') return url
    if (!url.includes('m_jam_kerja')) return url
    if (!url.includes('filter_is_active') && !url.includes('filter_status') && !url.includes('filter_is_hari_berikutnya') && !url.includes('filter_hari_lain') && !url.includes('filter_jam_kerja')) {
      return url
    }

    const isRelative = url.startsWith('/')
    const base = typeof window !== 'undefined' && window.location ? window.location.origin : 'http://localhost'
    const urlObj = new URL(url, base)
    const isActive = urlObj.searchParams.get('filter_is_active') ?? urlObj.searchParams.get('filter_status')
    const isHariLain = urlObj.searchParams.get('filter_is_hari_berikutnya') ?? urlObj.searchParams.get('filter_hari_lain')
    const jamKerja = urlObj.searchParams.get('filter_jam_kerja')
    const currentWhere = urlObj.searchParams.get('where') || ''

    let conds = []
    if (currentWhere) {
      conds = currentWhere.split(/\s+and\s+/i).map(c => c.trim()).filter(Boolean)
    }

    if (isActive !== null) {
      const val = String(isActive).toLowerCase().trim()
      const boolVal = (val === 'active' || val === 'true' || val === '1') ? 'true' : ((val === 'inactive' || val === 'false' || val === '0') ? 'false' : null)
      conds = conds.filter(c => !/\bthis\.is_active\b/i.test(c))
      if (boolVal !== null) {
        conds.push('this.is_active=' + boolVal)
      }
      urlObj.searchParams.delete('filter_is_active')
      urlObj.searchParams.delete('filter_status')
    }

    if (isHariLain !== null) {
      const val = String(isHariLain).toLowerCase().trim()
      const boolVal = (val === 'ada' || val === 'yes' || val === 'true' || val === '1') ? 'true' : ((val === 'tidak ada' || val === 'no' || val === 'false' || val === '0') ? 'false' : null)
      conds = conds.filter(c => !/\bthis\.is_hari_berikutnya\b/i.test(c))
      if (boolVal !== null) {
        conds.push('this.is_hari_berikutnya=' + boolVal)
      }
      urlObj.searchParams.delete('filter_is_hari_berikutnya')
      urlObj.searchParams.delete('filter_hari_lain')
    }

    if (jamKerja !== null) {
      const val = String(jamKerja).trim()
      conds = conds.filter(c => !/(?:this|m_jam_kerja)\.waktu_mulai/i.test(c))
      if (val !== '') {
        urlObj.searchParams.set('filter_jam_kerja', val)
      } else {
        urlObj.searchParams.delete('filter_jam_kerja')
      }
    }

    if (conds.length > 0) {
      urlObj.searchParams.set('where', conds.join(' and '))
    } else {
      urlObj.searchParams.delete('where')
    }

    const cleanSearch = decodeURIComponent(urlObj.search).replace(/\+/g, ' ')
    return isRelative ? (urlObj.pathname + cleanSearch) : (urlObj.origin + urlObj.pathname + cleanSearch)
  } catch (e) {
    console.error('Error transforming m_jam_kerja filter:', e)
    return url
  }
}

// Intercept fetch & XMLHttpRequest
if (typeof window !== 'undefined' && !window.__m_jam_kerja_filter_intercepted__) {
  window.__m_jam_kerja_filter_intercepted__ = true

  if (window.fetch) {
    const originalFetch = window.fetch
    window.fetch = async function (input, init) {
      if (typeof input === 'string') {
        input = transformJamKerjaUrl(input)
      } else if (input && input.url) {
        const newUrl = transformJamKerjaUrl(input.url)
        if (newUrl !== input.url && typeof Request !== 'undefined') {
          input = new Request(newUrl, input)
        }
      }
      return originalFetch.call(this, input, init)
    }
  }

  if (typeof XMLHttpRequest !== 'undefined' && XMLHttpRequest.prototype && XMLHttpRequest.prototype.open) {
    const originalXhrOpen = XMLHttpRequest.prototype.open
    XMLHttpRequest.prototype.open = function (method, url, ...rest) {
      if (typeof url === 'string') {
        url = transformJamKerjaUrl(url)
      }
      return originalXhrOpen.call(this, method, url, ...rest)
    }
  }

  if (window.axios && window.axios.interceptors && window.axios.interceptors.request) {
    window.axios.interceptors.request.use(function (config) {
      if (config && config.url) {
        config.url = transformJamKerjaUrl(config.url)
      }
      return config
    })
  }
}

if (store && store.axios && store.axios.interceptors && store.axios.interceptors.request) {
  store.axios.interceptors.request.use(function (config) {
    if (config && config.url) {
      config.url = transformJamKerjaUrl(config.url)
    }
    return config
  })
}

const activeBtn = ref(null)
const filterJamMulai = ref('')
const filterJamAkhir = ref('')

function applyJamKerjaFilter() {
  const s = filterJamMulai.value ? filterJamMulai.value.trim() : ''
  const e = filterJamAkhir.value ? filterJamAkhir.value.trim() : ''
  let val = ''
  if (s && e) {
    val = `${s} - ${e}`
  } else if (s) {
    val = `${s} -`
  } else if (e) {
    val = `- ${e}`
  }

  if (landing.api && landing.api.params) {
    if (val) {
      landing.api.params.filter_jam_kerja = val
    } else {
      delete landing.api.params.filter_jam_kerja
    }
  }

  // Update inputs in table column if present
  const tableInputs = document.querySelectorAll('.time-filter-table-start, .time-filter-table-end')
  if (tableInputs.length >= 2) {
    tableInputs[0].value = s
    tableInputs[1].value = e
  }

  if (apiTable.value && typeof apiTable.value.reload === 'function') {
    apiTable.value.reload()
  }
}

function resetJamKerjaFilter() {
  filterJamMulai.value = ''
  filterJamAkhir.value = ''
  applyJamKerjaFilter()
}

class JamKerjaFloatingFilter {
  init(params) {
    this.params = params
    this.eGui = document.createElement('div')
    this.eGui.className = 'time-range-filter-wrapper'
    this.eGui.style.display = 'flex'
    this.eGui.style.alignItems = 'center'
    this.eGui.style.justifyContent = 'center'
    this.eGui.style.gap = '2px'
    this.eGui.style.width = '100%'
    this.eGui.style.height = '100%'
    this.eGui.style.padding = '0 2px'

    this.inputStart = document.createElement('input')
    this.inputStart.type = 'time'
    this.inputStart.className = 'time-filter-start'
    this.inputStart.style.width = 'calc(50% - 9px)'
    this.inputStart.style.height = '24px'
    this.inputStart.style.fontSize = '11px'
    this.inputStart.style.padding = '0 2px'
    this.inputStart.style.border = '1px solid #d1d5db'
    this.inputStart.style.borderRadius = '4px'
    this.inputStart.style.backgroundColor = '#ffffff'
    this.inputStart.style.color = '#374151'
    this.inputStart.style.textAlign = 'center'
    this.inputStart.title = 'Jam Mulai'

    this.sep = document.createElement('span')
    this.sep.textContent = '-'
    this.sep.style.fontWeight = 'bold'
    this.sep.style.color = '#6b7280'
    this.sep.style.fontSize = '12px'
    this.sep.style.userSelect = 'none'

    this.inputEnd = document.createElement('input')
    this.inputEnd.type = 'time'
    this.inputEnd.className = 'time-filter-end'
    this.inputEnd.style.width = 'calc(50% - 9px)'
    this.inputEnd.style.height = '24px'
    this.inputEnd.style.fontSize = '11px'
    this.inputEnd.style.padding = '0 2px'
    this.inputEnd.style.border = '1px solid #d1d5db'
    this.inputEnd.style.borderRadius = '4px'
    this.inputEnd.style.backgroundColor = '#ffffff'
    this.inputEnd.style.color = '#374151'
    this.inputEnd.style.textAlign = 'center'
    this.inputEnd.title = 'Jam Selesai'

    this.btnClear = document.createElement('button')
    this.btnClear.type = 'button'
    this.btnClear.innerHTML = '&times;'
    this.btnClear.style.border = 'none'
    this.btnClear.style.background = 'transparent'
    this.btnClear.style.color = '#9ca3af'
    this.btnClear.style.fontSize = '14px'
    this.btnClear.style.fontWeight = 'bold'
    this.btnClear.style.cursor = 'pointer'
    this.btnClear.style.padding = '0 1px'
    this.btnClear.style.lineHeight = '1'
    this.btnClear.title = 'Reset'

    this.eGui.appendChild(this.inputStart)
    this.eGui.appendChild(this.sep)
    this.eGui.appendChild(this.inputEnd)
    this.eGui.appendChild(this.btnClear)

    const onChange = () => {
      const s = this.inputStart.value || ''
      const e = this.inputEnd.value || ''
      let val = ''
      if (s && e) val = `${s} - ${e}`
      else if (s) val = `${s} -`
      else if (e) val = `- ${e}`

      if (landing.api && landing.api.params) {
        if (val) {
          landing.api.params.filter_jam_kerja = val
        } else {
          delete landing.api.params.filter_jam_kerja
        }
        if (apiTable && apiTable.value && typeof apiTable.value.reload === 'function') {
          apiTable.value.reload()
        }
      }
    }

    this.inputStart.addEventListener('change', onChange)
    this.inputEnd.addEventListener('change', onChange)
    this.btnClear.addEventListener('click', (ev) => {
      ev.stopPropagation()
      this.inputStart.value = ''
      this.inputEnd.value = ''
      onChange()
    })
  }

  getGui() {
    return this.eGui
  }

  onParentModelChanged(parentModel) {
    if (!parentModel) {
      if (this.inputStart) this.inputStart.value = ''
      if (this.inputEnd) this.inputEnd.value = ''
    } else if (typeof parentModel === 'string' && parentModel.includes('-')) {
      const parts = parentModel.split('-').map(x => x.trim())
      if (this.inputStart) this.inputStart.value = parts[0] || ''
      if (this.inputEnd) this.inputEnd.value = parts[1] || ''
    }
  }

  destroy() {}
}

function attachJamKerjaTimeFilter() {
  if (typeof document === 'undefined') return false

  // Find floating filter cell for jam_kerja with flexible selectors
  const cells = document.querySelectorAll(
    '[col-id="jam_kerja"], ' +
    '.ag-header-cell[col-id="jam_kerja"], ' +
    '.ag-floating-filter[col-id="jam_kerja"], ' +
    '.ag-floating-filter-cell[col-id="jam_kerja"]'
  )

  let targetInput = null
  let container = null

  cells.forEach(c => {
    const inp = c.querySelector('input')
    if (inp && !inp.classList.contains('time-filter-table-input')) {
      targetInput = inp
      container = inp.parentElement || c
    }
  })

  if (!targetInput || !container) return false
  if (container.querySelector('.time-range-filter-wrapper')) return true

  // Hide the default single text input
  targetInput.style.display = 'none'

  const wrapper = document.createElement('div')
  wrapper.className = 'time-range-filter-wrapper'
  wrapper.style.display = 'flex'
  wrapper.style.alignItems = 'center'
  wrapper.style.justifyContent = 'center'
  wrapper.style.gap = '2px'
  wrapper.style.width = '100%'
  wrapper.style.height = '100%'
  wrapper.style.padding = '0 2px'

  const inputStart = document.createElement('input')
  inputStart.type = 'time'
  inputStart.className = 'time-filter-table-input time-filter-table-start'
  inputStart.style.width = 'calc(50% - 9px)'
  inputStart.style.height = '24px'
  inputStart.style.fontSize = '11px'
  inputStart.style.padding = '0 2px'
  inputStart.style.border = '1px solid #d1d5db'
  inputStart.style.borderRadius = '4px'
  inputStart.style.backgroundColor = '#ffffff'
  inputStart.style.color = '#374151'
  inputStart.style.textAlign = 'center'
  inputStart.title = 'Jam Mulai'
  inputStart.value = filterJamMulai.value || ''

  const sep = document.createElement('span')
  sep.textContent = '-'
  sep.style.fontWeight = 'bold'
  sep.style.color = '#6b7280'
  sep.style.fontSize = '12px'
  sep.style.userSelect = 'none'

  const inputEnd = document.createElement('input')
  inputEnd.type = 'time'
  inputEnd.className = 'time-filter-table-input time-filter-table-end'
  inputEnd.style.width = 'calc(50% - 9px)'
  inputEnd.style.height = '24px'
  inputEnd.style.fontSize = '11px'
  inputEnd.style.padding = '0 2px'
  inputEnd.style.border = '1px solid #d1d5db'
  inputEnd.style.borderRadius = '4px'
  inputEnd.style.backgroundColor = '#ffffff'
  inputEnd.style.color = '#374151'
  inputEnd.style.textAlign = 'center'
  inputEnd.title = 'Jam Selesai'
  inputEnd.value = filterJamAkhir.value || ''

  const btnClear = document.createElement('button')
  btnClear.type = 'button'
  btnClear.innerHTML = '&times;'
  btnClear.style.border = 'none'
  btnClear.style.background = 'transparent'
  btnClear.style.color = '#9ca3af'
  btnClear.style.fontSize = '14px'
  btnClear.style.fontWeight = 'bold'
  btnClear.style.cursor = 'pointer'
  btnClear.style.padding = '0 1px'
  btnClear.style.lineHeight = '1'
  btnClear.title = 'Reset'

  wrapper.appendChild(inputStart)
  wrapper.appendChild(sep)
  wrapper.appendChild(inputEnd)
  wrapper.appendChild(btnClear)

  const handleTimeChange = () => {
    filterJamMulai.value = inputStart.value || ''
    filterJamAkhir.value = inputEnd.value || ''
    applyJamKerjaFilter()
  }

  inputStart.addEventListener('change', handleTimeChange)
  inputEnd.addEventListener('change', handleTimeChange)

  btnClear.addEventListener('click', (ev) => {
    ev.stopPropagation()
    inputStart.value = ''
    inputEnd.value = ''
    filterJamMulai.value = ''
    filterJamAkhir.value = ''
    applyJamKerjaFilter()
  })

  container.appendChild(wrapper)
  return true
}

function initJamKerjaFilterObserver() {
  if (typeof window === 'undefined') return
  attachJamKerjaTimeFilter()

  let retries = 0
  const interval = setInterval(() => {
    const attached = attachJamKerjaTimeFilter()
    retries++
    if (attached || retries > 20) {
      clearInterval(interval)
    }
  }, 250)

  const target = document.querySelector('.bg-white') || document.body
  if (target && window.MutationObserver) {
    const observer = new MutationObserver(() => {
      attachJamKerjaTimeFilter()
    })
    observer.observe(target, { childList: true, subtree: true })
  }
}

function filterShowData(params, noBtn) {
  if (activeBtn.value === noBtn) {
    activeBtn.value = null
    landing.api.params.where = null
  } else {
    activeBtn.value = noBtn
    landing.api.params.where = `this.is_active=${params ? 'true' : 'false'}`
  }
  apiTable.value.reload()
}

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
        router.push(`${route.path}/${row.id}?` + tsId)
      }
    },
    {
      icon: 'edit',
      title: "Edit",
      class: 'bg-blue-600 text-light-100',
      // show: (row) => (currentMenu?.can_update)||store.user.data.username==='developer',
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
      searchfield: 'this.id, this.kode, m_dir.nama, this.waktu_mulai, this.waktu_akhir, this.desc, tipe_jam_kerja.value, this.is_active, this.is_hari_berikutnya',
    },
    onsuccess(response) {
      if (response && response.data) {
        response.data = response.data.map((item) => {
          const isActive = (item.is_active === true || item.is_active === 1 || item.is_active === '1' || item.is_active === 'true' || item.is_active === 't')
          const isHariLain = (item.is_hari_berikutnya === true || item.is_hari_berikutnya === 1 || item.is_hari_berikutnya === '1' || item.is_hari_berikutnya === 'true' || item.is_hari_berikutnya === 't')
          const mulai = item.waktu_mulai ? (item.waktu_mulai.length >= 5 ? item.waktu_mulai.slice(0, 5) : item.waktu_mulai) : ''
          const akhir = item.waktu_akhir ? (item.waktu_akhir.length >= 5 ? item.waktu_akhir.slice(0, 5) : item.waktu_akhir) : ''
          const jamKerja = (mulai && akhir) ? `${mulai} - ${akhir}` : (mulai || akhir || '-')

          item.is_active = isActive
          item.status = isActive ? 'Active' : 'Inactive'
          item.is_hari_berikutnya = isHariLain
          item.hari_lain = isHariLain ? 'Ada' : 'Tidak Ada'
          item.jam_kerja = jamKerja
          item.company = item['m_dir.nama'] || item.m_dir?.nama || item['m_comp.nama'] || '-'

          return item
        })
      }
      response.page = response.current_page
      response.hasNext = response.has_next
      setTimeout(attachJamKerjaTimeFilter, 50)
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
    headerName: 'Company',
    field: 'm_dir.nama',
    valueGetter: (p) => p.data?.['m_dir.nama'] || p.data?.m_dir?.nama || p.data?.company || p.data?.['m_comp.nama'] || '-',
    wrapText: true,
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Kode Presensi',
    field: 'kode',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start']
  },
  {
    headerName: 'Tipe',
    field: 'tipe_jam_kerja.value',
    valueGetter: (p) => p.data?.['tipe_jam_kerja.value'] || p.data?.tipe_jam_kerja?.value || '-',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start'],
  },
  {
    headerName: 'Jam Kerja',
    field: 'jam_kerja',
    sortable: true,
    filter: 'ColFilter',
    floatingFilterComponent: JamKerjaFloatingFilter,
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-start'],
    filterValueGetter: (params) => {
      if (params.data?.jam_kerja) return params.data.jam_kerja
      const mulai = params.data?.waktu_mulai ? (params.data.waktu_mulai.length >= 5 ? params.data.waktu_mulai.slice(0, 5) : params.data.waktu_mulai) : ''
      const akhir = params.data?.waktu_akhir ? (params.data.waktu_akhir.length >= 5 ? params.data.waktu_akhir.slice(0, 5) : params.data.waktu_akhir) : ''
      if (mulai && akhir) return `${mulai} - ${akhir}`
      return mulai || akhir || ''
    },
    valueGetter: (params) => {
      if (params.data?.jam_kerja) return params.data.jam_kerja
      const mulai = params.data?.waktu_mulai ? (params.data.waktu_mulai.length >= 5 ? params.data.waktu_mulai.slice(0, 5) : params.data.waktu_mulai) : ''
      const akhir = params.data?.waktu_akhir ? (params.data.waktu_akhir.length >= 5 ? params.data.waktu_akhir.slice(0, 5) : params.data.waktu_akhir) : ''
      if (mulai && akhir) return `${mulai} - ${akhir}`
      return mulai || akhir || '-'
    },
    cellRenderer: (params) => params.value || '-'
  },
  {
    headerName: 'Hari Lain',
    field: 'hari_lain',
    filter: 'ColFilter',
    filterParams: {
      options: ['Ada', 'Tidak Ada']
    },
    filterValueGetter: (params) => {
      const val = params.data ? (params.data.hari_lain !== undefined ? params.data.hari_lain : params.data.is_hari_berikutnya) : params.value
      return (val === 'Ada' || val === true || val === 1 || val === '1' || val === 'true') ? 'Ada' : 'Tidak Ada'
    },
    valueGetter: (params) => {
      const val = params.data ? (params.data.hari_lain !== undefined ? params.data.hari_lain : params.data.is_hari_berikutnya) : params.value
      return (val === 'Ada' || val === true || val === 1 || val === '1' || val === 'true') ? 'Ada' : 'Tidak Ada'
    },
    sortable: true,
    resizable: true,
    flex: 1,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
      return value === 'Ada'
        ? `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Ada</span>`
        : `<span class="text-red-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Tidak Ada</span>`
    }
  },
  {
    headerName: 'Keterangan',
    field: 'desc',
    filter: true,
    sortable: true,
    flex: 1,
    filter: 'ColFilter',
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-start'],
  },
  {
    headerName: 'Status',
    field: 'status',
    filter: 'ColFilter',
    filterParams: {
      options: ['Active', 'Inactive']
    },
    filterValueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    valueGetter: (params) => {
      const val = params.data ? (params.data.status !== undefined ? params.data.status : params.data.is_active) : params.value
      return (val === 'Active' || val === true || val === 1 || val === '1' || val === 'true' || val === 't') ? 'Active' : 'Inactive'
    },
    sortable: true,
    flex: 1,
    resizable: true,
    cellClass: ['border-r', '!border-gray-200', 'justify-center'],
    cellRenderer: ({ value }) => {
      return value === 'Active' || value === true
        ? `<span class="text-green-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Active</span>`
        : `<span class="text-gray-500 rounded-md text-xs font-medium px-4 py-1 inline-block capitalize">Inactive</span>`
    }
  },
  ]
})

watch(() => ({ ...(landing.api && landing.api.params) }), (newParams) => {
  if (!newParams || !landing.api || !landing.api.params) return
  let isActive = newParams.filter_is_active ?? newParams.filter_status
  let isHariLain = newParams.filter_is_hari_berikutnya ?? newParams.filter_hari_lain
  let jamKerja = newParams.filter_jam_kerja
  let modified = false
  let currentWhere = landing.api.params.where || ''
  let conds = currentWhere ? currentWhere.split(/\s+and\s+/i).map(c => c.trim()).filter(Boolean) : []

  if (isActive !== undefined) {
    const val = String(isActive).toLowerCase().trim()
    const boolVal = (val === 'active' || val === 'true' || val === '1') ? 'true' : ((val === 'inactive' || val === 'false' || val === '0') ? 'false' : null)
    conds = conds.filter(c => !/\bthis\.is_active\b/i.test(c))
    if (boolVal !== null) {
      conds.push('this.is_active=' + boolVal)
    }
    delete landing.api.params.filter_is_active
    delete landing.api.params.filter_status
    modified = true
  }

  if (isHariLain !== undefined) {
    const val = String(isHariLain).toLowerCase().trim()
    const boolVal = (val === 'ada' || val === 'yes' || val === 'true' || val === '1') ? 'true' : ((val === 'tidak ada' || val === 'no' || val === 'false' || val === '0') ? 'false' : null)
    conds = conds.filter(c => !/\bthis\.is_hari_berikutnya\b/i.test(c))
    if (boolVal !== null) {
      conds.push('this.is_hari_berikutnya=' + boolVal)
    }
    delete landing.api.params.filter_is_hari_berikutnya
    delete landing.api.params.filter_hari_lain
    modified = true
  }

  if (jamKerja !== undefined) {
    const val = String(jamKerja).trim()
    conds = conds.filter(c => !/(?:this|m_jam_kerja)\.waktu_mulai/i.test(c))
    if (val === '') {
      delete landing.api.params.filter_jam_kerja
    }
    modified = true
  }

  if (modified) {
    landing.api.params.where = conds.length > 0 ? conds.join(' and ') : null
  }
}, { deep: true })

onMounted(() => {
  initJamKerjaFilterObserver()
})

onActivated(() => {
  //  reload table api landing
  if (apiTable.value) {
    if (route.query.reload) {
      apiTable.value.reload()
    }
  }
  setTimeout(attachJamKerjaTimeFilter, 50)
})

//  @endif -------------------------------------------------END
watchEffect(() => store.commit('set', ['isRequesting', isRequesting.value]))
