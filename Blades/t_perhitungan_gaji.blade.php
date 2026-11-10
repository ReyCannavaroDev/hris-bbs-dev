@if(!$req->has('id'))
<div class="bg-white p-6 rounded-xl h-[570px]">
  <TableApi ref='apiTable' :api="landing.api" :columns="landing.columns" :actions="landing.actions">
    <template #header>
      <RouterLink v-if="(currentMenu?.can_create||true||store.user.data.username==='developer') && canViewSalary" :to="$route.path+'/create?'+(Date.parse(new Date()))" class="bg-blue-500 text-white hover:bg-blue-600 rounded-[6px] py-2 px-[12.5px]">
        <icon fa="bolt" />Generate Gaji
      </RouterLink>
    </template>
  </TableApi>
</div>
@else

@verbatim
<div class="flex flex-col gap-y-3">
  <div class="flex gap-x-4 px-2">
    <div class="flex flex-col border rounded shadow-sm px-6 py-6 <md:w-full w-full bg-white">
      <div class="mb-4">
        <h1 class="text-[24px] mb-4 font-bold">
          Perhitungan Gaji
        </h1>
        <hr>
      </div>
      <div v-if="!canViewSalary" class="mb-4 p-4 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm">
        Anda tidak memiliki hak akses untuk melihat atau mengelola nominal gaji pada modul ini.
      </div>
      <div class="grid <md:grid-cols-1 grid-cols-2 gap-2">
        <div>
          <label class="font-semibold">Tipe Penggajian</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText, clearable:true }"
            :value="values.periode_gaji_id" 
            :check="false"
            @input="(v)=>{
              onPeriodeGajiInput(v)
            }"
            @update:valueFull="(objVal)=>{
              onTipePeriodeChange(objVal)
            }"
            :errorText="formErrors.periode_gaji_id?'failed':''"
            :hints="formErrors.periode_gaji_id"
            label=""
            placeholder="Pilih Tipe Penggajian"
            displayField="value"
            valueField="id"
            :api="{
                url: `${store.server.url_backend}/operation/m_general`,
                headers: {
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                params: {
                  simplest:true,
                  single:true,
                  where:`this.group='PERIODE GAJI' and this.is_active='true'`,
                  transform:false,
                },
                onsuccess(response) {
                  onLoadPeriodeOptions(response)
                  return response
                }
            }"
            fa-icon="search" :check="true" />
        </div>
        <div class="grid grid-cols-2 gap-2">
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
        </div>
        <div>
          <label class="font-semibold">Company</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText || !isSuperadmin, clearable: Boolean(actionText && isSuperadmin) }"
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
                if (objVal.periode_gaji_id && !values.periode_gaji_id) values.periode_gaji_id = objVal.periode_gaji_id
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
                  where: (values.m_dir_id ? `this.m_dir_id=${values.m_dir_id} and ` : '') + (values.m_divisi_id ? `this.m_divisi_id=${values.m_divisi_id} and ` : '') + (values.m_dept_id ? `this.m_dept_id=${values.m_dept_id} and ` : '') + (values.periode_gaji_id ? `this.periode_gaji_id=${values.periode_gaji_id} and ` : '') + `this.is_active='true'`,
                  transform:false,
                  searchfield: 'this.nik, this.nama_lengkap, this.nama_depan, this.nama_belakang'
                }
            }"
            fa-icon="search" :check="true" />
        </div>
      </div>
      
      <div v-if="canViewSalary" class="flex flex-row justify-center space-x-[10px] mt-[1em]">
        <button @click="generatePerhitungan" class="bg-blue-500 hover:bg-blue-600 text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          <Icon fa="bolt"/> Generate
        </button>
        <button @click="detailArr = []" class="bg-[#EF4444] hover:bg-[#ed3232] text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          Hapus Detail
        </button>
      </div>
        
    <div class="mt-4">
        <table class="w-full overflow-x-auto table-auto border border-[#CACACA] " style="zoom: 80%">
          <thead>
            <tr class="border">
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 py-[14.5px] text-center w-[5%] border bg-[#f8f8f8] border-[#CACACA]">No.</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[10%] border bg-[#f8f8f8] border-[#CACACA]">Periode</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">NIK</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[20%] border bg-[#f8f8f8] border-[#CACACA]">Karyawan</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">Company</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">Divisi</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">Departemen</td>
              <td v-if="canViewSalary" class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[15%] border bg-[#f8f8f8] border-[#CACACA]">Gaji Bersih</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[15%] border bg-[#f8f8f8] border-[#CACACA]">Deskripsi</td>
              <td class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[5%] border bg-[#f8f8f8] border-[#CACACA]">Aksi</td>
            </tr>
          </thead>
          <tbody>
            <tr v-if="detailArr.length" v-for="(item, i) in detailArr" :key="item.id" class="border-t hover:bg-yellow-200">
              <td class="text-center border border-[#CACACA] px-2">
                {{ i + 1 }}.
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item.periode }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary.nik'] || item.nik || item.kode || '-' }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item.karyawan }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary_dir.nama'] || '-' }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary_divisi.nama'] }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary_dept.nama'] }}
              </td>
              <td v-if="canViewSalary" class="text-right border border-[#CACACA] px-2">
                {{ formatRupiah(item.netto) }}
              </td>
              <td class="border border-[#CACACA]">
                <FieldX
                  :bind="{ readonly: !actionText}" class="!mt-0"
                  :value="item.deskripsi" @input="v=>item.deskripsi=v"
                  type="textarea"
                  label=""
                  :check="false"
                />
              </td>
              <td class="border border-[#CACACA]">
                <div class="flex justify-center">
                  <button v-show="actionText && canViewSalary" @click="openDetail(i)" class="rounded-lg flex items-center justify-center">
                      <icon fa="circle-info" size="lg">
                    </button>
                </div>

              </td>
            </tr>
            <tr v-else class="text-center">
              <td :colspan="canViewSalary ? 10 : 9" class="py-[20px]">
                No data to show
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-row justify-end space-x-[20px] mt-[5em]">
        <button @click="onBack" class="bg-[#EF4444] hover:bg-[#ed3232] text-white px-[36.5px] py-[12px] rounded-[6px] ">
          Batal
        </button>
        <button v-show="actionText && canViewSalary" @click="onSave" class="bg-[#10B981] hover:bg-[#0ea774] text-white px-[36.5px] py-[12px] rounded-[6px] ">
          Simpan
        </button>
      </div>
    </div>
  </div>
</div>
@endverbatim
@endif

@verbatim
      <!-- Modal Content -->
      <div v-show="modalOpen" class="fixed inset-0 flex items-center justify-center z-50">
        <div class="modal-overlay fixed inset-0 bg-black opacity-50"></div>
        <div class="modal-container bg-white  w-[70%] mx-auto rounded shadow-lg z-50 overflow-y-auto">
          <div class="modal-content py-4 text-left px-6">
            <!-- Modal Header -->
          <div class="modal-header flex items-center justify-between flex-wrap">
            <div class="flex items-center">
              <h3 class="text-xl font-semibold ml-2">Rincian Gaji {{ titleOpen }}</h3>
            </div>
          </div>

            <!-- Modal Body -->
            <div class="modal-body">
               <table class="w-full overflow-x-auto mt-2 table-auto border border-[#CACACA]">
                  <thead>
                    <tr class="border">
                      <td class="font-bold text-capitalize p-2 text-left w-[20%] table-auto border border-[#CACACA] bg-dark-500 text-white">Komponen</td>
                      <td class="font-bold text-capitalize p-2 text-right w-[15%] table-auto border border-[#CACACA] bg-dark-500 text-white">Besaran</td>
                    </tr>
                  </thead>
                  <tbody>
                    <template v-for="(a,i) in detailArrOpen.items" :key="i">
                      <tr class="border" :class="{'bg-blue-50/20': a.rincian && a.rincian.length > 0 && openedRincian.includes(i)}">
                        <td class="text-left border border-gray-300 p-1.5" :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">
                          <div>{{ a.label }}</div>
                          <div v-if="a.rincian && a.rincian.length > 0" class="mt-0.5">
                            <button 
                              type="button" 
                              @click="toggleRincian(i)" 
                              class="text-[11px] text-blue-600 hover:text-blue-800 underline inline-flex items-center gap-1 cursor-pointer focus:outline-none">
                              <span>rincian keterlambatan</span>
                              <svg 
                                :class="{'rotate-180': openedRincian.includes(i)}" 
                                class="w-3 h-3 transition-transform duration-200 inline" 
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                              </svg>
                            </button>
                          </div>
                        </td>
                        <td class="text-right border border-gray-300 p-1.5 align-top" :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">{{ (a.factor == '-' ? '(-) ' : '') + formatRupiah(a.value) }}</td>
                      </tr>
                      <tr v-if="a.rincian && a.rincian.length > 0 && openedRincian.includes(i)" class="bg-gray-50 border border-gray-300">
                        <td colspan="2" class="p-2 border border-gray-300">
                          <div class="bg-white border border-gray-200 rounded p-2 shadow-sm">
                            <div class="text-xs font-semibold text-gray-700 mb-1.5 flex items-center justify-between">
                              <span>History Keterlambatan ({{ a.rincian.length }} Kejadian):</span>
                              <span class="text-gray-500 font-normal">Tarif Denda: Rp 25.000 / 30 Menit</span>
                            </div>
                            <table class="w-full text-xs border border-gray-200">
                              <thead>
                                <tr class="bg-gray-100 text-gray-700 font-semibold border-b border-gray-200">
                                  <th class="p-1 text-left border-r border-gray-200 w-8">No</th>
                                  <th class="p-1 text-left border-r border-gray-200">Tanggal</th>
                                  <th class="p-1 text-center border-r border-gray-200">Jadwal Masuk</th>
                                  <th class="p-1 text-center border-r border-gray-200">Jam Masuk</th>
                                  <th class="p-1 text-center border-r border-gray-200">Terlambat</th>
                                  <th class="p-1 text-right">Denda</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-for="(r, rIdx) in a.rincian" :key="rIdx" class="border-b border-gray-200 hover:bg-gray-50">
                                  <td class="p-1 text-left border-r border-gray-200">{{ rIdx + 1 }}</td>
                                  <td class="p-1 text-left border-r border-gray-200 font-medium text-gray-800">{{ r.tanggal }}</td>
                                  <td class="p-1 text-center border-r border-gray-200 text-gray-600">{{ r.jadwal_masuk }}</td>
                                  <td class="p-1 text-center border-r border-gray-200 text-red-600 font-semibold">{{ r.checkin_time }}</td>
                                  <td class="p-1 text-center border-r border-gray-200 text-red-600">{{ r.menit_telat }} Menit</td>
                                  <td class="p-1 text-right font-medium text-red-700">{{ formatRupiah(r.denda) }}</td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </td>
                      </tr>
                    </template>
                </tbody>
            </table>
            </div>
            <!-- Modal Footer -->
            <div class="modal-footer flex justify-center mt-2">
              <button @click="closeModal" class="modal-button bg-red-500 hover:bg-red-600 text-white font-semibold ml-2 px-2 py-1 rounded-sm">
                Tutup
              </button>
            </div>
          </div>
        </div>
      </div>
      
@endverbatim