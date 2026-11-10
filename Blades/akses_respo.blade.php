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

  <!-- TABLE RESPONSIBILITY -->
  <div v-else class="overflow-x-auto border border-gray-200 rounded">
    <table class="w-full text-sm text-left border-collapse">
      <thead class="bg-gray-50 text-gray-600 text-xs font-semibold border-b border-gray-200">
        <tr>
          <th class="px-3 py-2.5 text-center border-r border-gray-200 w-12">No</th>
          <th class="px-3 py-2.5 border-r border-gray-200 whitespace-nowrap">Kode</th>
          <th class="px-3 py-2.5 border-r border-gray-200">Responsibility</th>
          <th class="px-3 py-2.5 border-r border-gray-200">Company</th>
          <th class="px-3 py-2.5 border-r border-gray-200">Divisi</th>
          <th class="px-3 py-2.5 border-r border-gray-200">Departemen</th>
          <th class="px-3 py-2.5 text-center border-r border-gray-200 w-24">Status</th>
          <th class="px-3 py-2.5 text-center w-24">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200 bg-white">
        <tr
          v-for="(item, idx) in respoList"
          :key="item.user_respo_id || item.m_respo_id || idx"
          class="hover:bg-gray-50"
        >
          <td class="px-3 py-2.5 text-center border-r border-gray-200 text-gray-600">{{ idx + 1 }}</td>
          <td class="px-3 py-2.5 border-r border-gray-200 text-gray-600 whitespace-nowrap text-xs">
            {{ item.respo_kode || item.m_respo_id }}
          </td>
          <td class="px-3 py-2.5 border-r border-gray-200 font-medium text-gray-800">
            {{ item.respo_nama }}
          </td>
          <td class="px-3 py-2.5 border-r border-gray-200 text-gray-700">
            {{ item.dir_nama || 'Holding (Semua Company)' }}
          </td>
          <td class="px-3 py-2.5 border-r border-gray-200 text-gray-600">
            {{ item.divisi_nama || 'Semua Divisi' }}
          </td>
          <td class="px-3 py-2.5 border-r border-gray-200 text-gray-600">
            {{ item.dept_nama || 'Semua Departemen' }}
          </td>
          <td class="px-3 py-2.5 text-center border-r border-gray-200">
            <span v-if="item.is_primary" class="text-green-500 text-xs font-medium">Active</span>
            <span v-else class="text-gray-500 text-xs font-medium">Inactive</span>
          </td>
          <td class="px-3 py-2.5 text-center">
            <span v-if="item.is_primary" class="text-xs text-gray-400">-</span>
            <button
              v-else
              type="button"
              @click="onSwitch(item)"
              :disabled="isSwitching"
              class="px-3 py-1 rounded text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50"
            >
              Pilih
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endverbatim

