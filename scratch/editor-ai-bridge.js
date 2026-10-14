/* eslint-disable */
/**
 * editor-ai-bridge.js
 * ---------------------------------------------------------------------------
 * Satu file self-contained (tanpa dependency / tanpa build step) yang
 * mem-publish "tools" ke `window` agar AI/chatbot bisa mengoperasikan
 * project editor ini sebagai tools (function-calling).
 *
 * FUNGSI UTAMA (yang diminta):
 *   1. window.openFile(name, type, opts)          -> buka file di editor
 *   2. window.searchFiles(term, opts)             -> cari file (isi teks / nama)
 *   3. window.readContentByRegex(pattern, opts)   -> baca konten via regex
 *   4. window.editContent(text, opts)             -> edit konten (animasi mengetik)
 *   5. window.saveFile(opts)                      -> simpan file (reuse app onSave)
 *
 * HELPER (untuk kemudahan AI):
 *   window.focusLeftEditor({percent})             -> fokus + perbesar editor kiri (default 75%)
 *   window.EditorAI                               -> namespace berisi semua fungsi
 *   window.stopTyping()                           -> batalkan animasi mengetik
 *   window.getState()                             -> status app / file yang terbuka
 *   window.listModels()                           -> katalog file yang tersedia
 *
 * ATURAN:
 *   - SEMUA operasi AI (openFile/editContent/saveFile) default dilakukan di
 *     editor KIRI (editor 1), sesuai permintaan. Layout otomatis disesuaikan
 *     agar editor 1 mengambil ~75% layar (bisa diatur opts.percent).
 *
 * CARA MEMAKAI:
 *   <script src="/editor-ai-bridge.js" defer></script>
 *   // lalu dari chatbot:
 *   const tools = window.EditorAI
 *   await tools.openFile('User', 'basic')
 *   await tools.searchFiles('orderBy')
 *   await tools.readContentByRegex('class \\w+')
 *   await tools.editContent('// new content typed here...')
 *   await tools.saveFile()
 *
 * CATATAN:
 *   - Fungsi 1 & 4 butuh editor yang sedang terbuka (Nuxt/Vue ter-mount).
 *     Jika belum ter-mount, fungsi tetap bisa bekerja secara "headless"
 *     (menggunakan IndexedDB) dan mengembalikan status yang jelas.
 *   - Skema IndexedDB SAMA dengan app (composables/fileSnapshotDb.js):
 *       DB  = 'editor2022-file-snapshots'
 *       store = 'file_snapshots'
 *       keyPath = ['project','filename','fileType']
 *   - File ini sengaja dibuat plain-script (bukan ES module) supaya bisa
 *     di-`<script>`-kan langsung DAN bisa di-test di Node.
 * ---------------------------------------------------------------------------
 */
(function () {
  'use strict'

  // Global object: `window` di browser, `globalThis` di Node (untuk test).
  function G () {
    if (typeof window !== 'undefined') return window
    if (typeof globalThis !== 'undefined') return globalThis
    return this
  }

  // -------------------------------------------------------------------------
  // IndexedDB client (skema identik dengan app, agar data saling terbaca)
  // -------------------------------------------------------------------------
  var DB_NAME = 'editor2022-file-snapshots'
  var DB_VERSION = 1
  var STORE = 'file_snapshots'

  var _dbPromise = null
  function getDb () {
    var g = G()
    var idb = g.indexedDB
    if (!idb) return Promise.reject(new Error('indexedDB tidak tersedia di environment ini'))
    if (!_dbPromise) {
      _dbPromise = new Promise(function (resolve, reject) {
        var req = idb.open(DB_NAME, DB_VERSION)
        req.onupgradeneeded = function () {
          var db = req.result
          if (!db.objectStoreNames.contains(STORE)) {
            var store = db.createObjectStore(STORE, { keyPath: ['project', 'filename', 'fileType'] })
            store.createIndex('project', 'project', { unique: false })
          }
        }
        req.onsuccess = function () { resolve(req.result) }
        req.onerror = function () { reject(req.error || new Error('gagal membuka IndexedDB')) }
      })
    }
    return _dbPromise
  }

  function idbGet (db, key) {
    return new Promise(function (resolve, reject) {
      var req = db.transaction(STORE, 'readonly').objectStore(STORE).get(key)
      req.onsuccess = function () { resolve(req.result) }
      req.onerror = function () { reject(req.error) }
    })
  }

  function idbPut (db, record) {
    return new Promise(function (resolve, reject) {
      var tx = db.transaction(STORE, 'readwrite')
      tx.objectStore(STORE).put(record)
      tx.oncomplete = function () { resolve() }
      tx.onerror = function () { reject(tx.error) }
    })
  }

  function idbAllByProject (db, project) {
    return new Promise(function (resolve, reject) {
      var req = db.transaction(STORE, 'readonly').objectStore(STORE).index('project').getAll(project)
      req.onsuccess = function () { resolve(req.result || []) }
      req.onerror = function () { reject(req.error) }
    })
  }

  // -------------------------------------------------------------------------
  // Menemukan instance editor (komponen halaman /p/_id.vue) dari pohon Vue
  // -------------------------------------------------------------------------
  function isEditorVM (vm) {
    return !!(vm && Array.isArray(vm.editor1Files) &&
      typeof vm.setDataEditor === 'function' &&
      typeof vm.getFile === 'function')
  }

  function findEditorVM (root, depth) {
    depth = depth || 0
    if (!root || depth > 60) return null
    if (isEditorVM(root)) return root
    var kids = root.$children || []
    for (var i = 0; i < kids.length; i++) {
      var r = findEditorVM(kids[i], depth + 1)
      if (r) return r
    }
    return null
  }

  function getVueRoots () {
    var g = G()
    var roots = []
    if (g.$nuxt) {
      if (g.$nuxt.$children) roots.push(g.$nuxt)
      // defensive: beberapa build Nuxt menaruh instance Vue di properti lain
      if (g.$nuxt._VueInstance) roots.push(g.$nuxt._VueInstance)
    }
    if (g.document) {
      var el = g.document.getElementById && g.document.getElementById('__nuxt')
      if (el && el.__vue__) roots.push(el.__vue__)
    }
    return roots
  }

  function getEditorVM () {
    var roots = getVueRoots()
    for (var i = 0; i < roots.length; i++) {
      var found = findEditorVM(roots[i])
      if (found) return found
    }
    return null
  }

  // -------------------------------------------------------------------------
  // Utilitas umum
  // -------------------------------------------------------------------------
  function resolveProject () {
    var g = G()
    try {
      var nuxt = g.$nuxt
      var store = nuxt && (nuxt.$store || (nuxt.context && nuxt.context.store))
      if (store && store.state && store.state.projectConfig && store.state.projectConfig.serverName) {
        return store.state.projectConfig.serverName
      }
    } catch (e) { /* ignore */ }
    try {
      var saved = JSON.parse((g.localStorage && g.localStorage.projects) || '[]')
      if (saved && saved.length && saved[0].serverName) return saved[0].serverName
    } catch (e) { /* ignore */ }
    if (g.location && g.location.pathname) {
      var m = g.location.pathname.match(/\/p\/([^/?#]+)/)
      if (m) return m[1]
    }
    return null
  }

  function snippetAround (content, term, radius) {
    radius = radius || 70
    var s = String(content)
    var lower = s.toLowerCase()
    var idx = lower.indexOf(String(term).toLowerCase())
    if (idx < 0) return s.slice(0, radius * 2).replace(/\s+/g, ' ')
    var start = Math.max(0, idx - radius)
    var end = Math.min(s.length, idx + String(term).length + radius)
    var out = s.slice(start, end).replace(/\s+/g, ' ')
    if (start > 0) out = '\u2026' + out
    if (end < s.length) out = out + '\u2026'
    return out
  }

  function waitFor (predicate, timeout, interval) {
    timeout = timeout || 10000
    interval = interval || 60
    var start = Date.now()
    return new Promise(function (resolve) {
      function check () {
        var ok = false
        try { ok = predicate() } catch (e) { ok = false }
        if (ok) return resolve(true)
        if (Date.now() - start >= timeout) return resolve(false)
        setTimeout(check, interval)
      }
      check()
    })
  }

  // Normalisasi `type` agar cocok dengan mekanisme app (getFile / onSave).
  function normalizeType (name, type) {
    var t = (type || '').toString().trim()
    var map = {
      basic: 'Basic', custom: 'Custom', migration: 'Migration',
      alter: 'Alter', test: 'Test', truncate: 'Truncate', 'last 10 rows': 'Last 10 Rows'
    }
    if (t && map[t.toLowerCase()]) return map[t.toLowerCase()]
    var n = (name || '').toString()
    if (n.endsWith('.js')) return n.replace(/\.js$/i, '')
    if (n.endsWith('.blade.php')) return n.replace(/\.blade\.php$/i, '')
    if (n.endsWith('.php')) return n.replace(/\.php$/i, '')
    return 'Basic'
  }

  function iconFor (name) {
    var n = (name || '').toLowerCase()
    if (n.endsWith('.js')) return ['fas', 'file-code']
    if (n.endsWith('.blade.php')) return ['fas', 'file-code']
    if (n.endsWith('.php')) return ['fas', 'file-code']
    return ['fas', 'cog']
  }

  function getModelCatalog () {
    var g = G()
    var vm = getEditorVM()
    var src = null
    if (vm && vm.models && vm.models.models) src = vm.models
    if (!src) {
      try {
        var pm = JSON.parse((g.localStorage && g.localStorage.projectModels) || '{}')
        var project = resolveProject()
        var key = project && pm[project] ? project : (Object.keys(pm)[0] || null)
        if (key) src = pm[key]
      } catch (e) { src = null }
    }
    src = src || {}
    return {
      models: (src.models || []).map(function (m) {
        return { name: String(m.file || m.name || '').replace(/\.php$/i, ''), file: m.file }
      }),
      js: src.js || [],
      blades: src.blades || [],
      cores: src.cores || []
    }
  }

  // SEMUA operasi AI dilakukan di editor KIRI (editor 1) sesuai permintaan.
  var AI_EDITOR_ID = 1

  // Pastikan operasi AI menarget editor 1: buat editor 1 aktif, fokus,
  // dan (opsional) resize layout agar editor 1 mengambil `percent`% layar.
  function ensureLeftEditor (vm, resize, percent) {
    if (!vm) return
    vm.editorActive = 0 // index 0 => editor 1 (kiri) menjadi aktif
    if (resize) {
      percent = percent || 75
      var sidebar = Math.min(15, Math.max(5, 100 - percent - 5)) // sidebar max 15
      vm.sizeSidebar = sidebar
      vm.sizeEditor1 = percent
      vm.sizeEditor2 = Math.max(5, 100 - percent - sidebar)
      vm.sizeBottomTable = 0 // tutup tabel hasil di bawah
    }
    var ed = vm.editorInstance1
    if (ed && ed.focus) { try { ed.focus() } catch (e) { /* ignore */ } }
  }

  // Fokus + perbesar editor kiri (default 75% layar).
  function focusLeftEditor (opts) {
    opts = opts || {}
    var percent = opts.percent || 75
    var vm = getEditorVM()
    if (!vm) return { ok: false, code: 'NOT_MOUNTED', error: 'Editor belum terbuka.' }
    ensureLeftEditor(vm, true, percent)
    return {
      ok: true, editorId: AI_EDITOR_ID,
      sizes: { sidebar: vm.sizeSidebar, editor1: vm.sizeEditor1, editor2: vm.sizeEditor2, bottom: 0 }
    }
  }

  function entryContent (entry) {
    if (!entry) return ''
    if (entry.model && typeof entry.model.getValue === 'function') return entry.model.getValue()
    return entry.stringData || ''
  }

  // -------------------------------------------------------------------------
  // 1. OPEN FILE — membuka file di editor (reuse app.setDataEditor)
  // -------------------------------------------------------------------------
  async function openFile (name, type, opts) {
    opts = opts || {}
    if (!name) return { ok: false, code: 'BAD_ARGS', error: 'nama file (name) wajib diisi' }
    var vm = getEditorVM()
    if (!vm) {
      return {
        ok: false, code: 'NOT_MOUNTED',
        error: 'Halaman editor belum terbuka. Buka dulu halaman project (/p/<nama>).',
        tip: 'Panggil getState() untuk melihat status.'
      }
    }
    var normType = normalizeType(name, type)
    var editorId = opts.editorId || AI_EDITOR_ID
    ensureLeftEditor(vm, opts.resize !== false, opts.percent || 75)
    var data = { name: name, type: normType, icon: iconFor(name), properties: { table: true } }

    vm.setDataEditor(data, editorId, true, function () { /* noop */ })

    var files = function () { return (vm['editor' + editorId + 'Files'] || []) }
    var loaded = await waitFor(function () {
      var f = files().find(function (x) { return x.name === name })
      return !!(f && f.loadingFromServer !== true && f.model)
    }, opts.timeout || 10000, 60)

    if (!loaded) {
      return {
        ok: false, code: 'LOAD_TIMEOUT', name: name, type: normType,
        error: 'File tidak selesai dimuat dalam ' + (opts.timeout || 10000) + 'ms.'
      }
    }

    var f = files().find(function (x) { return x.name === name })
    var content = entryContent(f)
    return {
      ok: true, name: name, type: normType, editorId: editorId,
      lines: content ? content.split('\n').length : 0,
      length: content ? content.length : 0,
      content: opts.returnContent ? content : undefined
    }
  }

  // -------------------------------------------------------------------------
  // 1b. RELOAD FILE — ambil ulang isi dari server (SPA, tanpa reload browser)
  // -------------------------------------------------------------------------
  async function reloadFile (name, opts) {
    opts = opts || {}
    var vm = getEditorVM()
    if (!vm) return { ok: false, code: 'NOT_MOUNTED', error: 'Editor belum terbuka.' }
    if (typeof vm.getFile !== 'function') return { ok: false, code: 'NO_GETFILE', error: 'app.getFile tidak tersedia.' }

    var editorId = opts.editorId || AI_EDITOR_ID
    var list = vm['editor' + editorId + 'Files'] || []
    var entry = name
      ? list.find(function (f) { return f.name === name })
      : list[vm['editor' + editorId + 'ActiveIdx']]
    if (!entry) {
      // belum terbuka: openFile sudah mengambil dari server
      return openFile(name, opts.type, { editorId: editorId, returnContent: opts.returnContent })
    }

    var before = entryContent(entry)
    var fresh
    try {
      fresh = await vm.getFile(entry.name, entry.type)
    } catch (e) {
      return { ok: false, code: 'FETCH_FAIL', name: entry.name, error: String(e) }
    }
    if (fresh == null) return { ok: false, code: 'EMPTY', name: entry.name, error: 'Server tidak mengembalikan isi file.' }

    try { entry.model.setValue(fresh) } catch (e) { /* ignore */ }
    entry.stringData = fresh
    entry.loadingFromServer = false

    var project = resolveProject()
    if (project) {
      try {
        var db = await getDb()
        await idbPut(db, { project: project, filename: entry.name, fileType: entry.type, content: fresh, updatedAt: Date.now() })
      } catch (e) { /* best-effort */ }
    }

    return {
      ok: true, name: entry.name, type: entry.type, editorId: editorId,
      length: fresh.length, changed: before !== fresh,
      content: opts.returnContent ? fresh : undefined
    }
  }

  // -------------------------------------------------------------------------
  // 2. SEARCH FILES — cari file mengandung teks (IndexedDB) atau berdasarkan nama
  // -------------------------------------------------------------------------
  async function searchFiles (term, opts) {
    opts = opts || {}
    var mode = opts.mode || 'both' // 'content' | 'name' | 'both'
    var project = opts.project || resolveProject()
    var res = { ok: true, project: project, mode: mode, term: term, content: [], names: [], total: 0 }
    if (!term) { res.ok = false; res.error = 'istilah pencarian (term) kosong'; return res }
    var lower = String(term).toLowerCase()

    // a) Pencarian ISI TEKS via IndexedDB
    if (mode !== 'name') {
      if (!project) {
        res.contentError = 'project tidak ditemukan (butuh serverName untuk memfilter cache)'
      } else {
        try {
          var db = await getDb()
          var rows = await idbAllByProject(db, project)
          res.content = rows
            .filter(function (r) { return r.content && String(r.content).toLowerCase().indexOf(lower) !== -1 })
            .sort(function (a, b) { return (b.updatedAt || 0) - (a.updatedAt || 0) })
            .slice(0, opts.limit || 50)
            .map(function (r) {
              return {
                filename: r.filename, type: r.fileType, updatedAt: r.updatedAt,
                snippet: snippetAround(r.content, lower)
              }
            })
        } catch (e) { res.contentError = String(e) }
      }
    }

    // b) Pencarian NAMA FILE dari katalog model
    if (mode !== 'content') {
      try {
        var catalog = getModelCatalog()
        function nameMatch (s) {
          var x = String(s).toLowerCase()
          return x.indexOf(lower) !== -1 || x.replace(/_/g, '').indexOf(lower.replace(/ /g, '')) !== -1
        }
        res.names = []
          .concat((catalog.models || []).filter(function (m) { return nameMatch(m.name) })
            .map(function (m) {
              return { name: m.name, kind: 'model', types: ['basic', 'custom', 'migration', 'alter', 'test'] }
            }))
          .concat((catalog.js || []).filter(nameMatch).map(function (n) { return { name: n, kind: 'js' } }))
          .concat((catalog.blades || []).filter(nameMatch).map(function (n) { return { name: n, kind: 'blade' } }))
          .concat((catalog.cores || []).filter(nameMatch).map(function (n) { return { name: n, kind: 'core' } }))
      } catch (e) { res.namesError = String(e) }
    }

    res.total = (res.content || []).length + (res.names || []).length
    return res
  }

  // -------------------------------------------------------------------------
  // 3. READ CONTENT BY REGEX — membaca isi konten lalu mencocokkan regex
  // -------------------------------------------------------------------------
  async function resolveFileContent (vm, name, type) {
    // 1) file yang sudah terbuka di editor (paling mutakhir, bisa belum di-save)
    if (vm) {
      for (var id = 1; id <= 2; id++) {
        var list = vm['editor' + id + 'Files'] || []
        var f = list.find(function (x) { return x.name === name })
        if (f && f.model) return { content: entryContent(f), source: 'editor-' + id }
      }
    }
    // 2) cache IndexedDB
    var project = resolveProject()
    if (project) {
      try {
        var db = await getDb()
        var rec = await idbGet(db, [project, name, type])
        if (rec && rec.content != null) return { content: rec.content, source: 'indexeddb' }
      } catch (e) { /* ignore */ }
    }
    // 3) ambil dari server via app
    if (vm && typeof vm.getFile === 'function') {
      try {
        var c = await vm.getFile(name, type)
        if (c) return { content: c, source: 'server' }
      } catch (e) { /* ignore */ }
    }
    return null
  }

  async function readContentByRegex (pattern, opts) {
    opts = opts || {}
    if (pattern == null) return { ok: false, code: 'BAD_ARGS', error: 'pola regex (pattern) wajib diisi' }
    var vm = getEditorVM()
    var name, type, content, source

    if (opts.name) {
      name = opts.name
      type = normalizeType(name, opts.type)
      var r = await resolveFileContent(vm, name, type)
      if (!r) return { ok: false, code: 'NO_CONTENT', name: name, type: type, tip: 'File tidak ditemukan di editor/cache/server.' }
      content = r.content; source = r.source
    } else {
      // file aktif
      if (!vm) return { ok: false, code: 'NOT_MOUNTED', error: 'Editor belum terbuka dan tidak ada nama file yang ditentukan.' }
      var editorId = AI_EDITOR_ID
      ensureLeftEditor(vm, opts.resize !== false, opts.percent || 75)
      var entry = (vm['editor' + editorId + 'Files'] || [])[vm['editor' + editorId + 'ActiveIdx']]
      if (!entry) return { ok: false, code: 'NO_ACTIVE_FILE', editorId: editorId, tip: 'Buka file dulu dengan openFile().' }
      name = entry.name; type = entry.type
      content = entryContent(entry); source = 'active-editor-' + editorId
    }

    var re
    try {
      re = new RegExp(pattern, opts.flags || 'g')
    } catch (e) {
      return { ok: false, code: 'BAD_REGEX', error: String(e), pattern: pattern }
    }
    if (!re.global) re = new RegExp(pattern, (opts.flags || '') + 'g')

    var matches = []
    var m
    var cap = opts.limit || 200
    while ((m = re.exec(content)) !== null) {
      var offset = m.index
      var line = content.slice(0, offset).split('\n').length
      matches.push({
        match: m[0],
        groups: m.slice(1),
        index: offset,
        line: line,
        context: snippetAround(content, m[0])
      })
      if (matches.length >= cap) break
      if (m[0].length === 0) re.lastIndex++ // jaga-jaga pola panjang-nol
    }

    return {
      ok: true, name: name, type: type, source: source,
      pattern: pattern, flags: re.flags,
      totalMatches: matches.length,
      matches: matches,
      contentLength: content ? content.length : 0,
      content: opts.returnContent ? content : undefined
    }
  }

  // -------------------------------------------------------------------------
  // 4. EDIT CONTENT — mengganti isi file aktif dengan animasi mengetik
  // -------------------------------------------------------------------------
  var typing = { handle: null }

  function stopTyping () {
    if (typing.handle && typing.handle.cancel) typing.handle.cancel()
    typing.handle = null
    return true
  }

  function typeInto (editor, model, text, speed, chunk) {
    return new Promise(function (resolve, reject) {
      // speed<=0  => langsung (tanpa animasi)
      if (speed <= 0 || chunk <= 0 || text.length === 0) {
        if (text.length > 0) {
          try { editor.executeEdits('editor-ai-bridge', [{ range: fullRange(model), text: text, forceMoveMarkers: true }]) }
          catch (e) { return reject(e) }
        }
        return resolve()
      }
      var i = 0
      var cancelled = false
      var timer = null
      typing.handle = { cancel: function () { cancelled = true; if (timer) clearTimeout(timer) } }

      function step () {
        if (cancelled) { typing.handle = null; return resolve() }
        if (i >= text.length) {
          typing.handle = null
          try { editor.revealPositionInCenter(model.getPositionAt(model.getValueLength())) } catch (e) {
            try { editor.revealLineInCenter(model.getLineCount()) } catch (e2) { /* ignore */ }
          }
          return resolve()
        }
        var end = model.getPositionAt(model.getValueLength())
        var piece = text.slice(i, i + chunk)
        try {
          editor.executeEdits('editor-ai-bridge', [{
            range: { startLineNumber: end.lineNumber, startColumn: end.column, endLineNumber: end.lineNumber, endColumn: end.column },
            text: piece,
            forceMoveMarkers: true
          }])
        } catch (e) { typing.handle = null; return reject(e) }
        i += chunk
        try { editor.revealPositionInCenter({ lineNumber: end.lineNumber, column: end.column + piece.length }) } catch (e) {
          try { editor.revealLineInCenter(end.lineNumber) } catch (e2) { /* ignore */ }
        }
        timer = setTimeout(step, speed)
      }
      step()
    })
  }

  function fullRange (model) {
    var end = model.getPositionAt(model.getValueLength())
    return { startLineNumber: 1, startColumn: 1, endLineNumber: end.lineNumber, endColumn: end.column }
  }

  async function editContent (text, opts) {
    opts = opts || {}
    if (text == null) return { ok: false, code: 'BAD_ARGS', error: 'isi konten (text) wajib diisi' }
    text = String(text)
    var speed = (opts.speed != null) ? opts.speed : 5      // ms per chunk
    var chunk = (opts.chunk != null) ? opts.chunk : 2       // jumlah karakter per chunk

    var vm = getEditorVM()
    if (!vm) {
      // Fallback headless: tulis ke cache IndexedDB (tanpa animasi)
      var project = resolveProject()
      var name = opts.name
      if (!project || !name) {
        return { ok: false, code: 'NOT_MOUNTED', error: 'Editor belum terbuka dan tidak ada (name/project) untuk mode headless.' }
      }
      var type = normalizeType(name, opts.type)
      try {
        var db = await getDb()
        await idbPut(db, { project: project, filename: name, fileType: type, content: text, updatedAt: Date.now() })
        return { ok: true, headless: true, name: name, type: type, length: text.length, note: 'Editor tidak ter-mount; konten ditulis ke cache IndexedDB saja.' }
      } catch (e) {
        return { ok: false, code: 'HEADLESS_FAIL', error: String(e) }
      }
    }

    var editorId = opts.editorId || AI_EDITOR_ID
    ensureLeftEditor(vm, opts.resize !== false, opts.percent || 75)
    var list = vm['editor' + editorId + 'Files'] || []
    var idx
    if (opts.name) {
      idx = list.findIndex(function (f) { return f.name === opts.name })
      if (idx < 0) return { ok: false, code: 'FILE_NOT_OPEN', name: opts.name, tip: 'Buka dulu dengan openFile().' }
    } else {
      idx = vm['editor' + editorId + 'ActiveIdx']
    }
    var entry = list[idx]
    var editor = vm['editorInstance' + editorId]
    if (!entry || !editor) return { ok: false, code: 'NO_EDITOR', editorId: editorId }
    var model = (typeof editor.getModel === 'function') ? editor.getModel() : null
    if (!model) return { ok: false, code: 'NO_MODEL', editorId: editorId }

    stopTyping()
    try { editor.focus() } catch (e) { /* ignore */ }
    try { model.setValue('') } catch (e) { /* ignore */ } // bersihkan dulu, lalu ketik

    await typeInto(editor, model, text, speed, chunk)

    // Verifikasi: load file yang masih berjalan bisa menimpa hasil ketik.
    // Kalau isi tidak sama, tulis ulang sekali secara instan.
    // ponytail: satu kali retry cukup; kalau masih meleset, laporkan apa adanya.
    if (model.getValue() !== text) {
      await new Promise(function (r) { setTimeout(r, 300) })
      try { model.setValue(text) } catch (e) { /* ignore */ }
    }

    // sinkronkan data entry agar konsisten dengan editor
    try { entry.stringData = model.getValue() } catch (e) { /* ignore */ }

    // perbarui cache IndexedDB (best-effort)
    var project2 = resolveProject()
    if (project2) {
      try {
        var db2 = await getDb()
        await idbPut(db2, {
          project: project2, filename: entry.name, fileType: entry.type,
          content: model.getValue(), updatedAt: Date.now()
        })
      } catch (e) { /* best-effort */ }
    }

    return {
      ok: true, name: entry.name, type: entry.type, editorId: editorId,
      length: model.getValue().length,
      verified: model.getValue() === text,
      animated: speed > 0
    }
  }

  // -------------------------------------------------------------------------
  // 5. SAVE FILE — menyimpan file (reuse app.onSave agar konsisten)
  // -------------------------------------------------------------------------
  async function saveFile (opts) {
    opts = opts || {}
    var vm = getEditorVM()
    if (!vm) return { ok: false, code: 'NOT_MOUNTED', error: 'Editor belum terbuka, tidak ada yang bisa disimpan.' }
    if (typeof vm.onSave !== 'function') return { ok: false, code: 'NO_SAVE_METHOD', error: 'Metode onSave tidak ditemukan di app.' }

    var editorId = opts.editorId || AI_EDITOR_ID
    ensureLeftEditor(vm, opts.resize !== false, opts.percent || 75)
    var list = vm['editor' + editorId + 'Files'] || []
    var idx
    var entry
    if (opts.name) {
      idx = list.findIndex(function (f) { return f.name === opts.name })
      if (idx < 0) {
        // cek apakah kebetulan terbuka di editor 2, agar pesan lebih jelas
        var inEd2 = (vm['editor2Files'] || []).findIndex(function (f) { return f.name === opts.name })
        return { ok: false, code: 'FILE_NOT_OPEN', name: opts.name, inEditor2: inEd2 >= 0, tip: inEd2 >= 0 ? 'File ada di editor 2; buka ke editor 1 dulu dengan openFile().' : 'Buka dulu dengan openFile().' }
      }
    } else {
      idx = vm['editor' + editorId + 'ActiveIdx']
    }
    entry = list[idx]
    if (!entry) return { ok: false, code: 'NO_ACTIVE_FILE', editorId: editorId, tip: 'Buka file dulu dengan openFile().' }
    vm['editor' + editorId + 'ActiveIdx'] = idx // jadikan aktif agar onSave menarget file ini

    var type = entry.type
    // Meniru aturan app: model "Basic" (hasil generate) tidak boleh disimpan
    if (String(type).toLowerCase() === 'basic') {
      return { ok: false, code: 'FORBIDDEN_BASIC', name: entry.name, error: 'Menyimpan model Basic (hasil generate) dilarang (sesuai aturan app).' }
    }

    var content = entryContent(entry)
    var changedBefore = entry.changed
    var result = vm.onSave(editorId, content)
    if (result && result.then) { try { await result } catch (e) { /* app menanganinya sendiri (swal) */ } }
    var changedAfter = entry.changed

    return {
      ok: changedAfter === false,
      saved: changedAfter === false,
      name: entry.name, type: type, editorId: editorId,
      changedBefore: changedBefore, changedAfter: changedAfter,
      contentLength: content.length,
      note: changedAfter === false ? undefined : 'Gagal menyimpan; kemungkinan dialog error muncul di app.'
    }
  }

  // -------------------------------------------------------------------------
  // RUN QUERY — eksekusi SQL ke backend Laravel (/laradev/run-query)
  // SELECT  : statement dikirim apa adanya, hasil array dikembalikan.
  // ACTIVE  : DML (INSERT/UPDATE/DELETE/DDL) WAJIB memakai sufiks `:commit`.
  //           `commit:true` menambahkannya otomatis bila belum ada.
  // -------------------------------------------------------------------------
  var DML_RE = /^\s*(insert|update|delete|replace|alter|create|drop|truncate|rename|set|call|grant|revoke)\b/i

  function getProjectConfig () {
    var g = G()
    var vm = getEditorVM()
    if (vm && vm.xProjectConfig && vm.xProjectConfig.address) return vm.xProjectConfig
    try {
      var store = g.$nuxt && (g.$nuxt.$store || (g.$nuxt.context && g.$nuxt.context.store))
      if (store && store.state && store.state.projectConfig) return store.state.projectConfig
    } catch (e) { /* ignore */ }
    return null
  }

  async function runQuery (statement, opts) {
    opts = opts || {}
    var sql = String(statement || '').trim()
    if (!sql) return { ok: false, code: 'BAD_ARGS', error: 'statement SQL wajib diisi' }

    var cfg = getProjectConfig()
    if (!cfg || !cfg.address) {
      return { ok: false, code: 'NO_PROJECT_CONFIG', error: 'Konfigurasi project belum dimuat. Buka halaman project (/p/<nama>) dulu.' }
    }

    var isDml = DML_RE.test(sql)
    var hasCommit = /:commit\s*$/i.test(sql)
    // Commit TIDAK pernah implisit: harus opts.commit===true atau sufiks eksplisit.
    var wantCommit = opts.commit === true || hasCommit
    if (wantCommit && !hasCommit) sql = sql.replace(/;\s*$/, '') + ':commit'
    if (!wantCommit && isDml) {
      return {
        ok: false, code: 'NEEDS_COMMIT', statement: sql,
        error: 'Query yang mengubah data harus dijalankan dengan commit. Panggil runQuery(sql, { commit: true }) atau akhiri statement dengan ":commit".'
      }
    }

    var limit = opts.limit == null ? 200 : opts.limit
    var res
    try {
      res = await fetch(cfg.address + '/laradev/run-query', {
        method: 'POST',
        headers: {
          'Content-Type': 'Application/json',
          'Developer-Token': cfg.token,
          laradev: cfg.password
        },
        body: JSON.stringify({ statement: sql })
      })
    } catch (e) {
      return { ok: false, code: 'NETWORK_ERROR', statement: sql, error: String((e && e.message) || e) }
    }

    if (!res.ok) {
      var text = ''
      try { text = await res.text() } catch (e) { /* ignore */ }
      return { ok: false, code: 'QUERY_ERROR', status: res.status, statement: sql, error: text.slice(0, 2000) }
    }

    var payload = null
    try { payload = await res.json() } catch (e) { payload = null }
    var data = payload && payload.data

    if (Array.isArray(data)) {
      var table = sql.toLowerCase().split('from ')[1]
      table = table ? table.split(/[\s;]/)[0] : null
      // tampilkan juga di tabel hasil app bila tersedia
      if (opts.showTable !== false) {
        try {
          var vm = getEditorVM()
          if (vm && typeof vm.setResultData === 'function') {
            vm.setResultData(data, table)
            if (vm.sizeBottomTable === 0) vm.sizeBottomTable = 30
          }
        } catch (e) { /* ignore */ }
      }
      return {
        ok: true, kind: 'select', committed: hasCommit || wantCommit,
        statement: sql, table: table, rowCount: data.length,
        truncated: limit > 0 && data.length > limit,
        rows: limit > 0 ? data.slice(0, limit) : data
      }
    }

    return { ok: true, kind: 'active', committed: true, statement: sql, result: data == null ? payload : data }
  }

  // -------------------------------------------------------------------------
  // CAPABILITIES — apa yang boleh/tidak boleh disentuh
  // -------------------------------------------------------------------------
  function getCapabilities () {
    return {
      ok: true,
      editable: {
        custom: 'Model Custom (type:"custom") — SATU-SATUNYA tempat menulis logika model. Boleh edit + save.',
        migration: 'File migration (type:"migration") — boleh edit + save.',
        alter: 'File alter (type:"alter") — boleh edit + save.',
        test: 'File test (type:"test") — boleh edit + save.',
        js: 'File JavaScript, buka dengan nama file penuh (mis. app.js) — boleh edit + save.',
        blade: 'File Blade, nama penuh (mis. view.blade.php) — boleh edit + save.',
        core: 'File PHP di Cores/, nama penuh (mis. helper.php) — boleh edit + save.'
      },
      readOnly: {
        basic: 'Model Basic (type:"basic") hasil generate Laravel — HANYA untuk membaca/memeriksa struktur model. saveFile() akan menolak dengan code FORBIDDEN_BASIC.'
      },
      database: {
        select: 'runQuery("SELECT ...") — hasil dikembalikan sebagai rows + tampil di tabel bawah.',
        active: 'runQuery("UPDATE ...", { commit: true }) — DML wajib commit; sufiks ":commit" ditambahkan otomatis.',
        guard: 'Tanpa commit, DML ditolak dengan code NEEDS_COMMIT.'
      },
      conventions: {
        publicEndpoint: 'Fungsi public_xxxx($request) di model custom otomatis jadi endpoint publik ' +
          '/public/<model>/<xxxx>, menerima GET dan POST. Mis. public_cekStok($request) di model ' +
          'barang -> /public/barang/cekStok. Argumen $request berisi query string (GET) atau body (POST).'
      },
      editorRule: 'Semua operasi file di editor KIRI (editor 1).'
    }
  }

  // -------------------------------------------------------------------------
  // HELPER
  // -------------------------------------------------------------------------
  function listOpenFiles () {
    var vm = getEditorVM()
    if (!vm) return { ok: false, code: 'NOT_MOUNTED', openFiles: [] }
    var out = []
    for (var id = 1; id <= 2; id++) {
      var list = vm['editor' + id + 'Files'] || []
      var activeIdx = vm['editor' + id + 'ActiveIdx']
      for (var i = 0; i < list.length; i++) {
        var f = list[i]
        out.push({
          editorId: id, idx: i, name: f.name, type: f.type,
          active: i === activeIdx,
          length: entryContent(f).length
        })
      }
    }
    return { ok: true, openFiles: out }
  }

  function getState () {
    var g = G()
    var vm = getEditorVM()
    var project = resolveProject()
    return {
      ok: true,
      bridge: 'editor-ai-bridge',
      version: '1.0.0',
      editorMounted: !!vm,
      project: project,
      route: (g.location && g.location.pathname) || null,
      openFiles: vm ? listOpenFiles().openFiles : [],
      tools: {
        openFile: 'openFile(name, type, {editorId, timeout, returnContent}) -> Promise',
        searchFiles: 'searchFiles(term, {mode:"content"|"name"|"both", limit, project}) -> Promise',
        readContentByRegex: 'readContentByRegex(pattern, {name, type, flags, limit, returnContent}) -> Promise',
        editContent: 'editContent(text, {editorId, name, speed, chunk}) -> Promise',
        saveFile: 'saveFile({name | editorId}) -> Promise',
        focusLeftEditor: 'focusLeftEditor({percent}) -> fokus + perbesar editor kiri (default 75% layar)',
        stopTyping: 'stopTyping() -> boolean',
        listModels: 'listModels() -> katalog file',
        listOpenFiles: 'listOpenFiles() -> file yang terbuka'
      },
      note: 'Semua operasi AI (openFile/editContent/saveFile) default dilakukan di editor KIRI (editor 1).'
    }
  }

  function listModels () {
    return { ok: true, project: resolveProject(), catalog: getModelCatalog(),
      hint: 'Untuk model, type salah satu: basic|custom|migration|alter|test. Untuk js/blade/core, gunakan nama file penuh (mis. app.js, view.blade.php, helper.php).' }
  }

  function ping () {
    var g = G()
    return {
      ok: true,
      bridge: 'editor-ai-bridge',
      version: '1.0.0',
      hasWindow: typeof window !== 'undefined',
      hasIndexedDB: !!(g.indexedDB),
      hasNuxt: !!(g.$nuxt),
      editorMounted: !!getEditorVM(),
      project: resolveProject()
    }
  }

  // -------------------------------------------------------------------------
  // PUBLISH ke window
  // -------------------------------------------------------------------------
  // Skema tool (untuk function-calling LLM/chatbot). Di sini (bukan di HTML)
  // karena inline script di HTML di-escape oleh Nuxt, sehingga JSON rusak.
  var TOOL_SCHEMA = {
    name: 'editor-ai-tools',
    description: 'Tools untuk mengoperasikan project editor melalui window.EditorAI.',
    base: 'window.EditorAI (alias window.openFile, window.searchFiles, ...)',
    editorRule: 'Semua operasi wajib di editor KIRI (editor 1); layout otomatis 75%.',
    fileRules: {
      editable: ['custom', 'migration', 'alter', 'test', '*.js', '*.blade.php', 'Cores/*.php'],
      readOnly: ['basic (model generate Laravel, hanya untuk pemeriksaan)']
    },
    workflow: ['getState', 'listModels|searchFiles', 'openFile', 'readContentByRegex', 'editContent', 'saveFile'],
    tools: [
      { name: 'getState', params: [], desc: 'Status app (editorMounted, project, openFiles).' },
      { name: 'listModels', params: [], desc: 'Katalog file yang tersedia.' },
      { name: 'searchFiles', params: ['term', '{mode:content|name|both, limit, project}'], desc: 'Cari file dari isi teks (IndexedDB) atau nama.' },
      { name: 'openFile', params: ['name', 'type', '{editorId, percent, returnContent, timeout}'], desc: 'Buka file di editor 1. type: basic|custom|migration|alter|test atau nama file.' },
      { name: 'readContentByRegex', params: ['pattern', '{name, type, flags, limit, returnContent}'], desc: 'Baca isi konten lalu cocokkan regex (default file aktif).' },
      { name: 'editContent', params: ['text', '{name, editorId, speed, chunk}'], desc: 'Ganti isi file aktif dengan animasi mengetik (speed:0 = instan).' },
      { name: 'saveFile', params: ['{name | editorId}'], desc: 'Simpan file (model basic dilarang disimpan).' },
      { name: 'runQuery', params: ['statement', '{commit, limit, showTable}'], desc: 'Jalankan SQL. SELECT mengembalikan rows; DML wajib commit:true (sufiks ":commit").' },
      { name: 'getCapabilities', params: [], desc: 'Daftar apa yang boleh diedit (custom/migration/alter/test/js/blade/core) vs read-only (basic) + aturan query.' },
      { name: 'focusLeftEditor', params: ['{percent}'], desc: 'Fokus + perbesar editor kiri (default 75).' },
      { name: 'reloadFile', params: ['name', '{type, editorId, returnContent}'], desc: 'Ambil ulang isi file dari server tanpa reload halaman.' },
      { name: 'stopTyping', params: [], desc: 'Hentikan animasi mengetik.' },
      { name: 'ping', params: [], desc: 'Cek kesiapan bridge (editorMounted, project).' }
    ]
  }

  var api = {
    version: '1.0.0',
    toolSchema: TOOL_SCHEMA,
    ping: ping,
    getState: getState,
    listModels: listModels,
    listOpenFiles: listOpenFiles,
    openFile: openFile,
    reloadFile: reloadFile,
    searchFiles: searchFiles,
    readContentByRegex: readContentByRegex,
    editContent: editContent,
    saveFile: saveFile,
    runQuery: runQuery,
    getCapabilities: getCapabilities,
    focusLeftEditor: focusLeftEditor,
    stopTyping: stopTyping
  }

  var g = G()
  g.EditorAI = api
  g.EDITOR_AI_TOOLS = TOOL_SCHEMA // skema tool untuk function-calling
  // Publikasikan juga sebagai window variables agar mudah dipanggil chatbot
  g.ping = ping
  g.getState = getState
  g.listModels = listModels
  g.listOpenFiles = listOpenFiles
  g.openFile = openFile
  g.reloadFile = reloadFile
  g.searchFiles = searchFiles
  g.readContentByRegex = readContentByRegex
  g.editContent = editContent
  g.saveFile = saveFile
  g.runQuery = runQuery
  g.getCapabilities = getCapabilities
  g.focusLeftEditor = focusLeftEditor
  g.stopTyping = stopTyping

  // -------------------------------------------------------------------------
  // SKILL untuk ekstensi AI (Browserfly/Lenny) — dibaca dari localStorage
  // dengan awalan `lenny-skill-`. Berlaku di domain mana pun, tanpa perlu
  // mendaftarkan skill per-domain di sisi ekstensi.
  // -------------------------------------------------------------------------
  try {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('lenny-skill-editor-ai', [
        'Halaman ini adalah editor project berbasis Monaco dan MENYEDIAKAN TOOL SENDIRI di window.EditorAI.',
        'Jalankan tool itu lewat eval JavaScript di halaman. JANGAN mengetik manual ke Monaco lewat DOM/keyboard.',
        'Semua fungsi async (pakai await) dan mengembalikan objek JSON.',
        '',
        'ATURAN WAJIB:',
        '1. Semua operasi wajib di editor KIRI (editor 1); openFile sudah mengaturnya (layout 75%).',
        '2. Cek dulu: await EditorAI.ping(). Jika editorMounted=false, buka halaman project /p/NAMA lebih dulu.',
        '3. YANG BOLEH DIEDIT: model "custom", "migration", "alter", "test", file *.js, *.blade.php, dan PHP di Cores/.',
        '   Model "basic" READ-ONLY — hanya untuk memeriksa model Laravel hasil generate; saveFile menolaknya (FORBIDDEN_BASIC).',
        '4. editContent(text) mengganti SELURUH isi file aktif — baca isi lama dulu, lalu tulis ulang penuh.',
        '5. Setelah edit selalu await EditorAI.saveFile().',
        '',
        'TOOLS: ping(), getState(), listModels(), searchFiles(term,{mode:content|name|both}),',
        'openFile(name,type) [type: basic|custom|migration|alter|test atau nama file mis. app.js],',
        'readContentByRegex(pola,{name,type,flags,returnContent}), editContent(text,{speed}) [speed 0 = instan],',
        'saveFile({name}), runQuery(statement,{commit,limit}), getCapabilities(), focusLeftEditor({percent}), stopTyping().',
        '',
        'DATABASE: runQuery("SELECT * FROM users LIMIT 10") mengembalikan rows dan menampilkannya di tabel bawah.',
        'Query yang MENGUBAH data (INSERT/UPDATE/DELETE/ALTER/CREATE/DROP) wajib commit:',
        '  await EditorAI.runQuery("UPDATE users SET name=\'X\' WHERE id=1", { commit: true })',
        'Tanpa commit, DML ditolak dengan code NEEDS_COMMIT. Sufiks ":commit" ditambahkan otomatis.',
        '',
        'ALUR BAKU: ping -> listModels|searchFiles -> openFile -> readContentByRegex -> editContent -> saveFile.',
        '',
        'CONTOH menambah fungsi login di custom model User:',
        "  await EditorAI.openFile('User','custom',{returnContent:true})",
        "  const cur = await EditorAI.readContentByRegex('class\\\\s+\\\\w+',{returnContent:true})",
        '  const isi = cur.content; const i = isi.lastIndexOf("}")',
        '  const baru = isi.slice(0,i) + METHOD_BARU + isi.slice(i)',
        '  await EditorAI.editContent(baru,{speed:0}); await EditorAI.saveFile()',
        'Pola sama untuk register/logout/resetPassword dan penambahan method lainnya.',
        'Skema tool lengkap untuk function-calling ada di window.EDITOR_AI_TOOLS.'
      ].join('\n'))
    }
  } catch (e) { /* localStorage diblokir — abaikan */ }

  // Untuk test di Node (bukan module, jadi attach ke globalThis)
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api
  }
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this))
