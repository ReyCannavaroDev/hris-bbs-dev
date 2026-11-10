@verbatim
<div class="bg-white p-6 rounded-xl border shadow-sm">
  <!-- HEADER -->
  <div class="flex items-center justify-between flex-wrap gap-3 border-b pb-3 mb-4">
    <div>
      <h1 class="text-xl font-bold text-gray-800">Change Responsibility</h1>
      <p class="text-xs text-gray-500 mt-0.5">Pilih responsibility aktif untuk sesi kerja saat ini.</p>
    </div>

    <div v-if="activeRespo" class="text-xs text-gray-600 text-right">
      <span class="text-gray-400">Aktif:</span>
      <span class="font-semibold text-gray-800 ml-1">{{ activeRespo.respo_nama }}</span>
      <span class="text-gray-500 ml-1">({{ activeRespo.dir_nama || 'Holding (Semua Company)' }})</span>
    </div>
  </div>

  <!-- LOADING STATE -->
  <div v-if="isLoading" class="py-12 text-center text-sm text-gray-500">
    Memuat daftar responsibility...
  </div>

  <!-- EMPTY STATE -->
  <div v-else-if="!respoList || respoList.length === 0" class="py-12 text-center text-sm text-gray-500">
    <p class="mb-2">Belum ada responsibility terdaftar untuk akun Anda.</p>
    <RouterLink to="/dashboard" class="text-blue-600 hover:underline text-xs font-medium">
      Kembali ke Dashboard
    </RouterLink>
  </div>

  <!-- GRID CARD RESPONSIBILITY -->
  <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div
      v-for="(item, idx) in respoList"
      :key="item.user_respo_id || item.m_respo_id || idx"
      class="bg-white border rounded-lg p-4 flex flex-col justify-between"
      :class="item.is_primary ? 'border-green-500' : 'border-gray-200 hover:border-gray-300'"
    >
      <div>
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs text-gray-400 whitespace-nowrap">{{ item.respo_kode || item.m_respo_id }}</span>
          <span v-if="item.is_primary" class="text-xs font-medium text-green-500 whitespace-nowrap">Active</span>
          <span v-else class="text-xs font-medium text-gray-500 whitespace-nowrap">Inactive</span>
        </div>

        <h3 class="text-sm font-bold text-gray-800 mt-1.5 leading-snug">
          {{ item.respo_nama }}
        </h3>

        <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5 text-xs">
          <div class="flex justify-between gap-3">
            <span class="text-gray-400 shrink-0">Company</span>
            <span class="text-gray-800 font-medium text-right">{{ item.dir_nama || 'Holding (Semua Company)' }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-gray-400 shrink-0">Divisi</span>
            <span class="text-gray-700 text-right">{{ item.divisi_nama || 'Semua Divisi' }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-gray-400 shrink-0">Departemen</span>
            <span class="text-gray-700 text-right">{{ item.dept_nama || 'Semua Departemen' }}</span>
          </div>
        </div>
      </div>

      <div class="mt-4 pt-3 border-t border-gray-100">
        <button
          v-if="item.is_primary"
          type="button"
          disabled
          class="w-full py-1.5 px-3 rounded text-xs font-medium bg-gray-100 text-gray-400 cursor-default"
        >
          Sedang Aktif
        </button>
        <button
          v-else
          type="button"
          @click="onSwitch(item)"
          :disabled="isSwitching"
          class="w-full py-1.5 px-3 rounded text-xs font-medium border border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white disabled:opacity-50 transition"
        >
          Gunakan Peran Ini
        </button>
      </div>
    </div>
  </div>
</div>
@endverbatim

