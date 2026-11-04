with open('Javascript/m_jam_kerja.js', 'r', encoding='utf-8') as f:
    content = f.read()

content_norm = content.replace('\r\n', '\n')

# 1. Replace optJamKerja definition with JamKerjaFloatingFilter & attachJamKerjaTimeFilter
old_block_1 = """const activeBtn = ref(null)

const optJamKerja = reactive([
  '06:52 - 04:52',
  '07:00 - 16:00',
  '07:00 - 22:01',
  '08:00 - 17:00',
  '08:30 - 16:30',
  '08:30 - 17:00',
  '08:30 - 17:30',
  '09:00 - 17:00',
  '11:51 - 10:51',
  '14:31 - 15:31',
  '17:00 - 21:00',
  '20:00 - 06:00',
  '21:00 - 07:00',
  '22:00 - 16:00',
  '22:30 - 15:27'
])

function filterShowData(params, noBtn) {"""

new_block_1 = """const activeBtn = ref(null)

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

  const cell = document.querySelector(
    '.ag-header-row-floating-filter [col-id="jam_kerja"], ' +
    '[col-id="jam_kerja"].ag-floating-filter, ' +
    '.ag-floating-filter[col-id="jam_kerja"]'
  )

  if (!cell) return false
  if (cell.querySelector('.time-range-filter-wrapper')) return true

  const defaultInput = cell.querySelector('input')
  const parentContainer = defaultInput ? defaultInput.parentElement : cell

  if (defaultInput) {
    defaultInput.style.display = 'none'
  }

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
  inputStart.className = 'time-filter-start'
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

  const sep = document.createElement('span')
  sep.textContent = '-'
  sep.style.fontWeight = 'bold'
  sep.style.color = '#6b7280'
  sep.style.fontSize = '12px'
  sep.style.userSelect = 'none'

  const inputEnd = document.createElement('input')
  inputEnd.type = 'time'
  inputEnd.className = 'time-filter-end'
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

  const curVal = landing.api?.params?.filter_jam_kerja || ''
  if (curVal && curVal.includes('-')) {
    const parts = curVal.split('-').map(x => x.trim())
    inputStart.value = parts[0] || ''
    inputEnd.value = parts[1] || ''
  }

  const handleTimeChange = () => {
    const s = inputStart.value || ''
    const e = inputEnd.value || ''
    let val = ''
    if (s && e) {
      val = `${s} - ${e}`
    } else if (s) {
      val = `${s} -`
    } else if (e) {
      val = `- ${e}`
    }

    if (defaultInput) {
      defaultInput.value = val
      defaultInput.dispatchEvent(new Event('input', { bubbles: true }))
      defaultInput.dispatchEvent(new Event('change', { bubbles: true }))
    }

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

  inputStart.addEventListener('change', handleTimeChange)
  inputEnd.addEventListener('change', handleTimeChange)

  btnClear.addEventListener('click', (ev) => {
    ev.stopPropagation()
    inputStart.value = ''
    inputEnd.value = ''
    handleTimeChange()
  })

  parentContainer.appendChild(wrapper)
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

function filterShowData(params, noBtn) {"""

assert old_block_1 in content_norm, "old_block_1 not found"
content_norm = content_norm.replace(old_block_1, new_block_1, 1)

# 2. In onsuccess: remove optJamKerja push, add attachJamKerjaTimeFilter call
old_block_2 = """          item.jam_kerja = jamKerja
          item.company = item['m_dir.nama'] || item.m_dir?.nama || item['m_comp.nama'] || '-'

          if (jamKerja && jamKerja !== '-' && !optJamKerja.includes(jamKerja)) {
            optJamKerja.push(jamKerja)
            optJamKerja.sort()
          }

          return item
        })
      }
      response.page = response.current_page
      response.hasNext = response.has_next
      return response"""

new_block_2 = """          item.jam_kerja = jamKerja
          item.company = item['m_dir.nama'] || item.m_dir?.nama || item['m_comp.nama'] || '-'

          return item
        })
      }
      response.page = response.current_page
      response.hasNext = response.has_next
      setTimeout(attachJamKerjaTimeFilter, 50)
      return response"""

assert old_block_2 in content_norm, "old_block_2 not found"
content_norm = content_norm.replace(old_block_2, new_block_2, 1)

# 3. In columns for jam_kerja: replace filterParams with floatingFilterComponent
old_block_3 = """  {
    headerName: 'Jam Kerja',
    field: 'jam_kerja',
    sortable: true,
    filter: 'ColFilter',
    filterParams: {
      options: optJamKerja
    },
    resizable: true,"""

new_block_3 = """  {
    headerName: 'Jam Kerja',
    field: 'jam_kerja',
    sortable: true,
    filter: 'ColFilter',
    floatingFilterComponent: JamKerjaFloatingFilter,
    resizable: true,"""

assert old_block_3 in content_norm, "old_block_3 not found"
content_norm = content_norm.replace(old_block_3, new_block_3, 1)

# 4. In onActivated & onMounted:
old_block_4 = """onActivated(() => {
  //  reload table api landing
  if (apiTable.value) {
    if (route.query.reload) {
      apiTable.value.reload()
    }
  }
})"""

new_block_4 = """onMounted(() => {
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
})"""

assert old_block_4 in content_norm, "old_block_4 not found"
content_norm = content_norm.replace(old_block_4, new_block_4, 1)

with open('Javascript/m_jam_kerja.js', 'w', encoding='utf-8', newline='\n') as f:
    f.write(content_norm)

print("SUCCESS: Updated Javascript/m_jam_kerja.js")

