@if(!$req->has('id'))
@verbatim
<div class="bg-white p-6 rounded-xl h-[570px]">
  <TableApi ref='apiTable' :api="landing.api" :columns="landing.columns" :actions="landing.actions">
    <template #header>
      <RouterLink :to="$route.path+'/create?'+(Date.parse(new Date()))" class="bg-blue-500 text-white hover:bg-blue-600 rounded-[6px] py-2 px-[12.5px]">
        <icon fa="bolt" /> Generate Gaji Lembur
      </RouterLink>
    </template>
  </TableApi>
</div>
@endverbatim
@else

@verbatim
<div class="flex flex-col gap-y-3">
  <div class="flex gap-x-4 px-2">
    <div class="flex flex-col border rounded shadow-sm px-6 py-6 <md:w-full w-full bg-white">
      <div class="mb-4">
        <h1 class="text-[24px] mb-4 font-bold">
          Perhitungan Gaji Lembur
        </h1>
        <hr>
      </div>
      <div class="grid <md:grid-cols-1 grid-cols-2 gap-2">
        <div>
          <label class="font-semibold">Periode Awal<span class="text-red-500 space-x-0 pl-0">*</span></label>
          <FieldX :bind="{ readonly: !actionText , required: true}" class="w-full py-2 !mt-0"
              :value="values.periode_awal" :errorText="formErrors.periode_awal?'failed':''"
              :hints="formErrors.periode_awal" 
              :check="false"
              type="date"
              label=""
              placeholder="DD/MM/YYYY"
              @input="(v)=>{
                onPeriodeAwalChange(v)
              }"
          />
        </div>
        <div>
          <label class="font-semibold">Periode Akhir<span class="text-red-500 space-x-0 pl-0">*</span></label>
          <FieldX :bind="{ readonly: !actionText , required: true}" class="w-full py-2 !mt-0"
              :value="values.periode_akhir" :errorText="formErrors.periode_akhir?'failed':''"
              :hints="formErrors.periode_akhir" 
              :check="false"
              type="date"
              label=""
              placeholder="DD/MM/YYYY"
              @input="(v)=>{
                onPeriodeAkhirChange(v)
              }"
          />
        </div>
        <div>
          <label class="font-semibold">Company</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText, clearable:true }"
            :value="values.m_dir_id" 
            :check="false"
            @input="(v)=>{
              values.m_dir_id=v
              values.divisi=null
              values.m_divisi_id=null
              values.m_dept_id=null
              values.m_kary_id=null
              detailArr = []
            }"
            :errorText="formErrors.m_dir_id?'failed':''"
            :hints="formErrors.m_dir_id"
            placeholder="Pilih Company"
            label=""
            displayField="nama"
            valueField="id"
            :api="{
                url: `${store.server.url_backend}/operation/m_dir`,
                headers: {
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                params: {
                  simplest:true,
                  single:true,
                  where:`this.is_active='true'`,
                  transform:false,
                }
            }"
            fa-icon="search" :check="true" />
        </div>
        <div>
          <label class="font-semibold">Divisi</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText, clearable:true }"
            :value="values.divisi" 
            :check="false"
            @input="(v)=>{
              values.divisi=v
              values.m_divisi_id=v
              values.m_dept_id=null
              values.m_kary_id=null
              detailArr = []
            }"
            :errorText="formErrors.divisi?'failed':''"
            :hints="formErrors.divisi"
            placeholder="Pilih Divisi"
            label=""
            displayField="nama"
            valueField="id"
            :api="{
                url: `${store.server.url_backend}/operation/m_divisi`,
                headers: {
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                params: {
                  simplest:true,
                  single:true,
                  where: values.m_dir_id ? `this.m_dir_id=${values.m_dir_id} and this.is_active='true'` : `this.is_active='true'`,
                  transform:false,
                }
            }"
            fa-icon="search" :check="true" />
        </div>
        <div>
          <label class="font-semibold">Departemen</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText, clearable:true }"
            :value="values.m_dept_id" 
            :check="false"
            @input="(v)=>{
              values.m_dept_id=v
              values.m_kary_id=null
              detailArr = []
            }"
            :errorText="formErrors.m_dept_id?'failed':''"
            :hints="formErrors.m_dept_id"
            placeholder="Pilih Departemen"
            label=""
            displayField="nama"
            valueField="id"
            :api="{
                url: `${store.server.url_backend}/operation/m_dept`,
                headers: {
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                params: {
                  simplest:true,
                  single:true,
                  where: values.m_divisi_id ? `this.m_divisi_id=${values.m_divisi_id} and this.is_active='true'` : (values.m_dir_id ? `this.m_dir_id=${values.m_dir_id} and this.is_active='true'` : `this.is_active='true'`),
                  transform:false,
                }
            }"
            fa-icon="search" :check="true" />
        </div>
        <div>
          <label class="font-semibold">Karyawan</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText, clearable:true }"
            :value="values.m_kary_id" 
            :check="false"
            @input="(v)=>{
              values.m_kary_id=v
              detailArr = []
            }"
            @update:valueFull="(objVal)=>{
              if (objVal) {
                if (objVal.m_dir_id && !values.m_dir_id) values.m_dir_id = objVal.m_dir_id
                if (objVal.m_divisi_id && !values.m_divisi_id) {
                  values.m_divisi_id = objVal.m_divisi_id
                  values.divisi = objVal.m_divisi_id
                }
                if (objVal.m_dept_id && !values.m_dept_id) values.m_dept_id = objVal.m_dept_id
              }
            }"
            :errorText="formErrors.m_kary_id?'failed':''"
            :hints="formErrors.m_kary_id"
            placeholder="Pilih Karyawan (Kosongkan untuk Semua Karyawan)"
            label=""
            displayField="nama_lengkap"
            valueField="id"
            :api="{
                url: `${store.server.url_backend}/operation/m_kary`,
                headers: {
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                params: {
                  simplest:true,
                  single:true,
                  where: (values.m_dir_id ? `this.m_dir_id=${values.m_dir_id} and ` : '') + (values.m_divisi_id ? `this.m_divisi_id=${values.m_divisi_id} and ` : '') + (values.m_dept_id ? `this.m_dept_id=${values.m_dept_id} and ` : '') + `this.is_active='true'`,
                  transform:false,
                  searchfield: 'this.nik, this.nama_lengkap, this.nama_depan, this.nama_belakang'
                }
            }"
            fa-icon="search" :check="true" />
        </div>
      </div>
      
      <div class="flex flex-row justify-center space-x-[10px] mt-[1.5em]">
        <button @click="generatePerhitungan" class="bg-blue-500 hover:bg-blue-600 text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          <icon fa="bolt" /> Hitung Lembur
        </button>
        <button @click="detailArr = []" class="bg-[#EF4444] hover:bg-[#ed3232] text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          Hapus Detail
        </button>
      </div>
        
      <div class="mt-4">
        <table class="w-full overflow-x-auto table-auto border border-[#CACACA]" style="zoom: 80%">
          <thead>
            <tr class="border">
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 py-[14.5px] text-center w-[4%] border bg-[#f8f8f8] border-[#CACACA]">No.</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[10%] border bg-[#f8f8f8] border-[#CACACA]">Periode</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[10%] border bg-[#f8f8f8] border-[#CACACA]">NIK</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[18%] border bg-[#f8f8f8] border-[#CACACA]">Karyawan</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">Divisi / Dept</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[7%] border bg-[#f8f8f8] border-[#CACACA]">Grading</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[8%] border bg-[#f8f8f8] border-[#CACACA]">Jam Diakui</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[10%] border bg-[#f8f8f8] border-[#CACACA]">Tarif / Jam</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">Total Uang Lembur</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[5%] border bg-[#f8f8f8] border-[#CACACA]">Rincian</td>
            </tr>
          </thead>
          <tbody>
            <template v-if="detailArr && detailArr.length">
              <tr v-for="(item, i) in detailArr" :key="item.id || i" class="border-t hover:bg-yellow-100">
                <td class="text-center border border-[#CACACA] px-2 py-1">
                  {{ i + 1 }}.
                </td>
                <td class="text-left border border-[#CACACA] px-2">
                  {{ item.periode }}
                </td>
                <td class="text-left border border-[#CACACA] px-2">
                  {{ item['m_kary.nik'] || item.nik || item.kode || '-' }}
                </td>
                <td class="text-left border border-[#CACACA] px-2 font-medium">
                  {{ item.karyawan || item.nama_lengkap }}
                </td>
                <td class="text-left border border-[#CACACA] px-2 text-sm">
                  {{ item.divisi_nama || item.m_divisi_nama || item['m_kary_divisi.nama'] || '-' }} / {{ item.dept_nama || item.m_dept_nama || item['m_kary_dept.nama'] || '-' }}
                </td>
                <td class="text-center border border-[#CACACA] px-2">
                  {{ item.grading || '-' }}
                </td>
                <td class="text-center border border-[#CACACA] px-2 font-semibold bg-green-50">
                  {{ item.total_jam_diakui }} jam
                </td>
                <td class="text-right border border-[#CACACA] px-2">
                  {{ formatRupiah(item.tarif_lembur) }}
                </td>
                <td class="text-right border border-[#CACACA] px-2 font-bold text-green-700 bg-green-50">
                  {{ formatRupiah(item.total_lembur) }}
                </td>
                <td class="border border-[#CACACA]">
                  <div class="flex justify-center">
                    <button @click="openDetail(i)" class="rounded-lg flex items-center justify-center p-1 text-blue-600 hover:text-blue-800" title="Lihat SPK Lembur">
                      <icon fa="circle-info" size="lg" />
                    </button>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-else class="text-center">
              <td colspan="10" class="py-[20px] text-gray-500">
                Belum ada data lembur untuk ditampilkan. Silakan pilih rentang periode dan klik "Hitung Lembur".
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-row justify-end space-x-[20px] mt-[4em]">
        <button @click="onBack" class="bg-[#EF4444] hover:bg-[#ed3232] text-white px-[36.5px] py-[12px] rounded-[6px] ">
          Batal
        </button>
        <button v-show="actionText && detailArr.length" @click="onSave" class="bg-[#10B981] hover:bg-[#0ea774] text-white px-[36.5px] py-[12px] rounded-[6px] ">
          Simpan
        </button>
      </div>
    </div>
  </div>
</div>
@endverbatim
@endif

@verbatim
<!-- Modal Rincian SPK Lembur -->
<div v-show="modalOpen" class="fixed inset-0 flex items-center justify-center z-50">
  <div class="modal-overlay fixed inset-0 bg-black opacity-50" @click="closeModal"></div>
  <div class="modal-container bg-white w-[85%] max-h-[85vh] mx-auto rounded shadow-lg z-50 overflow-y-auto">
    <div class="modal-content py-4 text-left px-6">
      <!-- Modal Header -->
      <div class="modal-header flex items-center justify-between pb-3 border-b">
        <h3 class="text-xl font-semibold">Rincian SPK Lembur: {{ titleOpen }}</h3>
        <button @click="closeModal" class="text-gray-500 hover:text-gray-800 font-bold text-xl">&times;</button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body py-4">
        <table class="w-full overflow-x-auto table-auto border border-[#CACACA] text-sm">
          <thead>
            <tr class="bg-dark-500 text-white">
              <th class="p-2 border text-center w-[4%]">No.</th>
              <th class="p-2 border text-center w-[12%]">Tanggal</th>
              <th class="p-2 border text-left w-[15%]">No. SPK</th>
              <th class="p-2 border text-left w-[12%]">Tipe Lembur</th>
              <th class="p-2 border text-center w-[12%]">Jam Rencana</th>
              <th class="p-2 border text-center w-[12%]">Jam Realisasi</th>
              <th class="p-2 border text-center w-[10%]">Diakui (Jam)</th>
              <th class="p-2 border text-right w-[10%]">Tarif</th>
              <th class="p-2 border text-right w-[13%]">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="detailArrOpen.items && detailArrOpen.items.length">
              <tr v-for="(spk, idx) in detailArrOpen.items" :key="idx" class="border hover:bg-gray-50">
                <td class="text-center border p-2">{{ idx + 1 }}</td>
                <td class="text-center border p-2">{{ spk.tanggal }}</td>
                <td class="text-left border p-2 font-mono text-xs">{{ spk.nomor }}</td>
                <td class="text-left border p-2">{{ spk.tipe_lembur }}</td>
                <td class="text-center border p-2">{{ spk.jam_mulai }} - {{ spk.jam_selesai }}</td>
                <td class="text-center border p-2">{{ spk.jam_mulai_realisasi }} - {{ spk.jam_selesai_realisasi }}</td>
                <td class="text-center border p-2 font-semibold bg-green-50">{{ spk.diakui_jam }} jam</td>
                <td class="text-right border p-2">{{ formatRupiah(spk.tarif_lembur) }}</td>
                <td class="text-right border p-2 font-bold text-green-700 bg-green-50">{{ formatRupiah(spk.total_lembur) }}</td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="9" class="text-center py-4 text-gray-500">Tidak ada rincian SPK lembur.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer flex justify-end pt-3 border-t">
        <button @click="closeModal" class="bg-gray-500 hover:bg-gray-600 text-white font-semibold px-4 py-1.5 rounded">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>
@endverbatim

