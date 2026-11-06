@verbatim
<div class="p-6 bg-gray-50 min-h-screen">
  <!-- HEADER -->
  <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <icon fa="exchange" class="text-blue-600" />
          Ganti Hak Akses & Responsibility
        </h1>
        <p class="text-sm text-gray-500 mt-1">
          Pilih peran dan entitas perusahaan (Direktorat) aktif yang ingin Anda gunakan dalam sesi kerja saat ini.
        </p>
      </div>

      <!-- STATUS RESPO AKTIF -->
      <div v-if="activeRespo" class="bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-2 text-right">
        <span class="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider block">Responsibility Aktif Saat Ini</span>
        <span class="text-sm font-bold text-gray-800">{{ activeRespo.respo_nama }}</span>
        <span class="text-xs text-emerald-600 block">({{ activeRespo.dir_nama || 'Holding / All' }})</span>
      </div>
    </div>
  </div>

  <!-- LOADING STATE -->
  <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
    <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    <p class="text-sm text-gray-500 mt-3 font-medium">Memuat daftar responsibility Anda...</p>
  </div>

  <!-- EMPTY STATE -->
  <div v-else-if="!respoList || respoList.length === 0" class="bg-white rounded-xl border p-12 text-center max-w-lg mx-auto shadow-sm">
    <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
      <icon fa="info-circle" />
    </div>
    <h3 class="text-lg font-bold text-gray-800 mb-1">Belum Ada Responsibility Terdaftar</h3>
    <p class="text-xs text-gray-500 leading-relaxed mb-6">
      Akun Anda belum memiliki penugasan responsibility di sistem. Silakan hubungi Administrator HRD untuk pengaturan peran dan akses direktorat Anda.
    </p>
    <RouterLink to="/dashboard" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
      <icon fa="home" /> Kembali ke Dashboard
    </RouterLink>
  </div>

  <!-- GRID RESPONSIBILITY CARDS -->
  <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div 
      v-for="item in respoList" 
      :key="item.user_respo_id || item.m_respo_id"
      class="bg-white rounded-xl border transition-all duration-200 flex flex-col justify-between overflow-hidden shadow-sm"
      :class="item.is_primary ? 'border-2 border-emerald-500 shadow-md ring-2 ring-emerald-100' : 'hover:border-blue-300 hover:shadow-md'"
    >
      <!-- CARD HEADER -->
      <div class="p-5 border-b" :class="item.is_primary ? 'bg-emerald-50/40' : 'bg-gray-50/60'">
        <div class="flex items-start justify-between gap-2">
          <span 
            v-if="item.is_primary"
            class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center gap-1"
          >
            <icon fa="check" size="xs" /> SEDANG DIGUNAKAN
          </span>
          <span 
            v-else
            class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200"
          >
            Tersedia
          </span>
          <span class="text-xs font-mono text-gray-400">#{{ item.respo_kode || item.m_respo_id }}</span>
        </div>

        <h3 class="text-base font-bold text-gray-800 mt-3 leading-snug">
          {{ item.respo_nama }}
        </h3>
      </div>

      <!-- CARD BODY -->
      <div class="p-5 space-y-3 text-xs flex-1">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-md bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
            <icon fa="building" />
          </div>
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-semibold">Company / Direktorat</span>
            <span class="font-bold text-gray-800 text-xs">{{ item.dir_nama || 'Holding (Semua Direktorat)' }}</span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-md bg-indigo-50 text-indigo-600 flex items-center justify-center flex-shrink-0">
            <icon fa="sitemap" />
          </div>
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-semibold">Divisi</span>
            <span class="text-gray-700">{{ item.divisi_nama || 'Semua Divisi (General)' }}</span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-md bg-purple-50 text-purple-600 flex items-center justify-center flex-shrink-0">
            <icon fa="folder" />
          </div>
          <div>
            <span class="text-gray-400 block text-[10px] uppercase font-semibold">Departemen</span>
            <span class="text-gray-700">{{ item.dept_nama || 'Semua Departemen' }}</span>
          </div>
        </div>
      </div>

      <!-- CARD FOOTER ACTION -->
      <div class="p-4 bg-gray-50 border-t">
        <button 
          v-if="item.is_primary"
          disabled
          class="w-full py-2.5 px-4 rounded-lg bg-emerald-600 text-white font-semibold text-xs flex items-center justify-center gap-2 opacity-90 cursor-default"
        >
          <icon fa="check-circle" /> Tanggung Jawab Aktif
        </button>

        <button 
          v-else
          @click="onSwitch(item)"
          :disabled="isSwitching"
          class="w-full py-2.5 px-4 rounded-lg bg-white border border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white font-semibold text-xs flex items-center justify-center gap-2 transition duration-150 shadow-sm disabled:opacity-50"
        >
          <icon fa="exchange" /> Gunakan Peran Ini
        </button>
      </div>
    </div>
  </div>
</div>
@endverbatim

