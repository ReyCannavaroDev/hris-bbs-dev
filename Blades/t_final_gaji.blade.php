@if(!$req->has('id'))
<div class="bg-white p-6 rounded-xl h-[570px]">
  <TableApi ref='apiTable' :api="landing.api" :columns="landing.columns" :actions="landing.actions">
    <template #header>
      <RouterLink v-if="currentMenu?.can_create||true||store.user.data.username==='developer'"
        :to="$route.path+'/create?'+(Date.parse(new Date()))"
        class="bg-green-500 text-white hover:bg-green-600 rounded-[6px] py-2 px-[12.5px]">
        <icon fa="plus" />Tambah Data
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
          Finalisasi Gaji
        </h1>
        <hr>
      </div>
      <div class="grid <md:grid-cols-1 grid-cols-2 gap-2">
        <div>
          <label class="font-semibold">Periode Awal<span class="text-red-500 space-x-0 pl-0">*</span></label>
          <FieldX :bind="{ readonly: !actionText , required: true}" class="w-full py-2 !mt-0"
            :value="values.periode_awal" :errorText="formErrors.periode_awal?'failed':''"
            :hints="formErrors.periode_awal" :check="false" type="date" label="" placeholder="YYYY-MM-DD" @input="(v)=>{
                values.periode_awal=v
                detailArr = []
              }" />
        </div>
        <div>
          <label class="font-semibold">Periode Akhir<span class="text-red-500 space-x-0 pl-0">*</span></label>
          <FieldX :bind="{ readonly: !actionText , required: true}" class="w-full py-2 !mt-0"
            :value="values.periode_akhir" :errorText="formErrors.periode_akhir?'failed':''"
            :hints="formErrors.periode_akhir" :check="false" type="date" label="" placeholder="YYYY-MM-DD" @input="(v)=>{
                values.periode_akhir=v
                detailArr = []
              }" />
        </div>

        <div>
          <label class="font-semibold">Direktorat / Cabang</label>
          <FieldSelect class="w-full py-2 !mt-0" :bind="{ disabled: !actionText, clearable:true }"
            :value="values.m_dir_id" :check="false" @input="(v)=>{
              values.m_dir_id=v
              values.m_divisi_id=null
              values.m_dept_id=null
              detailArr = []
            }" :errorText="formErrors.m_dir_id?'failed':''" :hints="formErrors.m_dir_id" placeholder="Pilih Direktorat / Cabang"
            displayField="nama" valueField="id" :api="{
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
            }" fa-icon="search" :check="true" />
        </div>

        <div>
          <label class="font-semibold">Divisi<span class="text-red-500 space-x-0 pl-0"></span></label>
          <FieldSelect class="w-full py-2 !mt-0" :bind="{ disabled: !actionText, clearable:true }"
            :value="values.m_divisi_id" :check="false" @input="(v)=>{
              values.m_divisi_id=v
              values.m_dept_id=null
              detailArr = []
            }" :errorText="formErrors.m_divisi_id?'failed':''" :hints="formErrors.m_divisi_id" placeholder="Pilih Divisi"
            displayField="nama" valueField="id" :api="{
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
            }" fa-icon="search" :check="true" />
        </div>

        <div>
          <label class="font-semibold">Departemen<span class="text-red-500 space-x-0 pl-0"></span></label>
          <FieldSelect class="w-full py-2 !mt-0" :bind="{ disabled: !actionText, clearable:true }"
            :value="values.m_dept_id" :check="false" @input="(v)=>{
              values.m_dept_id=v
              detailArr = []
            }" :errorText="formErrors.m_dept_id?'failed':''" :hints="formErrors.m_dept_id" placeholder="Pilih Departemen"
            displayField="nama" valueField="id" :api="{
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
            }" fa-icon="search" :check="true" />
        </div>

        <div>
          <label class="font-semibold">Periode Gaji (Tipe Penggajian)</label>
          <FieldSelect
            class="w-full py-2 !mt-0"
            :bind="{ disabled: !actionText, clearable:true }"
            :value="values.periode_gaji_id" 
            :check="false"
            @input="(v)=>{
              values.periode_gaji_id=v
              detailArr = []
            }"
            :errorText="formErrors.periode_gaji_id?'failed':''"
            :hints="formErrors.periode_gaji_id"
            placeholder="Pilih Periode Gaji (Semua / Bulanan / Mingguan)"
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
                }
            }"
            fa-icon="search" :check="true" />
        </div>

        <div class="col-span-2">
          <label class="font-semibold">Total Pengeluaran Gaji<span class="text-red-500 space-x-0 pl-0">*</span></label>
          <FieldNumber :bind="{ readonly: true}" class="!mt-0 flex justify-end" :value="values.total_pengeluaran_gaji"
            @input="v=>values.total_pengeluaran_gaji=v" type="number" label="" :check="false" />
        </div>

        <div class="col-span-2">
          <label class="font-semibold">Deskripsi Pendek<span class="text-red-500 space-x-0 pl-0">*</span></label>
          <FieldX :bind="{ readonly: !actionText}" class="!mt-0" :value="values.desc" @input="v=>values.desc=v"
            :errorText="formErrors.desc?'failed':''" :hints="formErrors.desc" type="textarea" label="" :check="false" />
        </div>
      </div>

      <div class="flex flex-row justify-center space-x-[10px] mt-[1em]">
        <button v-show="actionText" :disabled="!actionText" @click="generatePerhitungan" class="bg-blue-500 hover:bg-blue-600 text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          <Icon fa="bolt"/> Generate
        </button>
        <button v-show="actionText" :disabled="!actionText" @click="detailArr = []" class="bg-[#EF4444] hover:bg-[#ed3232] text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          Hapus Detail
        </button>
      </div>

      <div class="mt-4">
        <table class="w-full overflow-x-auto table-auto border border-[#CACACA] " style="zoom: 80%">
          <thead>
            <tr class="border">
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 py-[14.5px] text-center w-[5%] border bg-[#f8f8f8] border-[#CACACA]">
                No.</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[10%] border bg-[#f8f8f8] border-[#CACACA]">
                Periode</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">
                NIK</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[20%] border bg-[#f8f8f8] border-[#CACACA]">
                Karyawan</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">
                Direktorat</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">
                Divisi</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[12%] border bg-[#f8f8f8] border-[#CACACA]">
                Departemen</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[15%] border bg-[#f8f8f8] border-[#CACACA]">
                Gaji Bersih</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[15%] border bg-[#f8f8f8] border-[#CACACA]">
                Deskripsi</td>
              <td
                class="text-[#8F8F8F] font-semibold text-[14px] text-capitalize px-2 text-center w-[5%] border bg-[#f8f8f8] border-[#CACACA]">
                Aksi</td>
            </tr>
          </thead>
          <tbody>
            <tr v-if="detailArr.length" v-for="(item, i) in detailArr" :key="item.id"
              class="border-t hover:bg-yellow-200">
              <td class="text-center border border-[#CACACA] px-2">
                {{ i + 1 }}.
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item.periode }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary.nik'] || item.m_kary?.nik || '-' }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item.karyawan || item['m_kary.nama_lengkap'] || item.m_kary?.nama_lengkap || '-' }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary_dir.nama'] || item.m_kary_dir?.nama || item['m_dir.nama'] || item.m_dir?.nama || '-' }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary_divisi.nama'] || item.m_kary_divisi?.nama || item['m_divisi.nama'] || item.m_divisi?.nama || '-' }}
              </td>
              <td class="text-left border border-[#CACACA] px-2">
                {{ item['m_kary_dept.nama'] || item.m_kary_dept?.nama || item['m_dept.nama'] || item.m_dept?.nama || '-' }}
              </td>
              <td class="text-right border border-[#CACACA] px-2">
                {{ formatRupiah(item.netto) }}
              </td>
              <td class="border border-[#CACACA]">
                <FieldX :bind="{ readonly: !actionText}" class="!mt-0" :value="item.deskripsi"
                  @input="v=>item.deskripsi=v" type="textarea" label="" :check="false" />
              </td>
              <td class="border border-[#CACACA]">
                <div class="flex justify-center">
                  <button @click="openDetail(i)" class="rounded-lg flex items-center justify-center" title="Rincian / Sesuaikan Gaji">
                    <icon fa="circle-info" size="lg" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-else class="text-center">
              <td colspan="10" class="py-[20px]">
                No data to show
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-row justify-end space-x-[20px] mt-[1em]">
        <button @click="onBack" class="bg-gray-400 hover:bg-gray-500 text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          Kembali
        </button>
        <button v-show="actionText" :disabled="!actionText" @click="onReset" class="bg-[#EF4444] hover:bg-[#ed3232] text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          Reset
        </button>
        <button v-show="values.status === 'DRAFT' && isRead" @click="posted" class="bg-rose-700 hover:bg-rose-800 text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
          <icon fa="paper-plane" /> Posted Data
        </button>
        <button v-show="actionText" :disabled="!actionText" @click="onSave" class="bg-[#22C55E] hover:bg-[#16a34a] text-white text-sm px-[18px] py-[8px] rounded-[4px] ">
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
  <div class="modal-container bg-white  w-[80%] mx-auto rounded shadow-lg z-50 overflow-y-auto">
    <div class="modal-content py-4 text-left px-6">
      <!-- Modal Header -->
      <div class="modal-header flex items-center justify-between flex-wrap">
        <div class="flex items-center">
          <h3 class="text-xl font-semibold ml-2">Rincian Gaji {{ titleOpen }}</h3>
        </div>
      </div>
      <hr class="border-t-1 border-gray-200 my-4">

      <!-- Modal Body -->
      <div class="modal-body">
        <div class="grid grid-cols-2 gap-4" :class="{'grid-cols-1': !actionText}">
          <div>
            <div class="flex justify-between">
              <h3 class="font-semibold">Standar Gaji </h3>
            </div>
            <table class="w-full overflow-x-auto mt-2 table-auto border border-[#CACACA]">
              <thead>
                <tr class="border">
                  <td
                    class="font-bold text-capitalize p-2 text-left w-[20%] table-auto border border-[#CACACA] bg-dark-500 text-white">
                    Komponen</td>
                  <td
                    class="font-bold text-capitalize p-2 text-right w-[15%] table-auto border border-[#CACACA] bg-dark-500 text-white">
                    Besaran</td>
                </tr>
              </thead>
              <tbody>
                <tr class="border" v-for="(a,i) in detailArrOpen.items" :key="i">
                  <td class="text-left border border-gray-300 p-1.8"
                    :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">{{ a.label }}</td>
                  <td class="text-right border border-gray-300 p-1.8"
                    :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">{{ (a.factor == '-' ? '(-) ' : '') +
                    formatRupiah(a.value) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-show="actionText">
            <div class="flex justify-between">
              <h3 class="font-semibold">Sesuaikan Gaji </h3>
            </div>
            <div class="overflow-y-auto max-h-[370px] w-[100%]">
              <table class="w-full overflow-x-auto mt-2 table-auto border border-[#CACACA]">
                <thead>
                  <tr class="border">
                    <td
                      class="font-bold text-capitalize p-2 text-left w-[20%] table-auto border border-[#CACACA] bg-dark-500 text-white">
                      Komponen</td>
                    <td
                      class="font-bold text-capitalize p-2 text-left w-[2%] table-auto border border-[#CACACA] bg-dark-500 text-white">
                      Faktor</td>
                    <td
                      class="font-bold text-capitalize p-2 text-right w-[15%] table-auto border border-[#CACACA] bg-dark-500 text-white">
                      Besaran</td>
                  </tr>
                </thead>
                <tbody class="overflow-y-auto">
                  <tr class="border" v-for="(a,i) in detailArrAdjOpen.items" :key="i">
                    <td class="text-left border border-gray-300 p-0"
                      :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">
                      <span v-if="a.default === true">{{ a.label }}</span>
                      <div v-else class="flex justify-between">
                        <FieldX :bind="{ readonly: !actionText }" class="!mt-0"
                          :value="detailArrAdjOpen.items[i]['label']" @input="v=>detailArrAdjOpen.items[i]['label']=v"
                          placeholder="" label="" :check="false" />
                        <Icon fa="x" @click="deleteRow(a)" title="hapus baris"
                          class="text-red-500 cursor-pointer items-center mt-1 p-2" />
                      </div>
                    </td>
                    <td class="text-left border border-gray-300 p-0"
                      :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">
                      <span v-if="a.default === true">{{ a.factor }}</span>
                      <FieldSelect v-else :bind="{ disabled: !actionText, clearable:false }"
                        :value="detailArrAdjOpen.items[i]['factor']" @input="v=>detailArrAdjOpen.items[i]['factor']=v"
                        valueField="key" displayField="key" :options="['+','-']" placeholder="" label=""
                        :check="false" />
                    </td>
                    <td class="text-right border border-gray-300 p-0"
                      :class="a.factor == '=' ? 'font-bold bg-gray-200' : ''">
                      <FieldNumber :bind="{ readonly: !actionText}" class="!mt-0 flex justify-end !p-0"
                        :value="detailArrAdjOpen.items[i]['value']" @input="(v)=>{
                                detailArrAdjOpen.items[i]['value']=v 
                                summaryAdj()
                              }" type="number" label="" :check="false" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div @click="addRowAdj" class="flex justify-end cursor-pointer hover:text-blue-400">
              <i>Tambah Baris <Icon fa="plus"/></i>
            </div>
            <table class="w-full overflow-x-auto mt-2 table-auto border border-[#CACACA]">
              <tbody>
                <tr class="border">
                  <td class="text-left border border-gray-300 p-1 font-bold bg-gray-200">Total Penyesuaian Gaji</td>
                  <td class="text-right border border-gray-300 p-0 font-bold bg-gray-200">
                    <FieldNumber :bind="{ readonly: !actionText}" class="!mt-0 flex justify-end !p-0"
                      :value="totalAdjOpen.value" type="number" label="" :check="false" />
                  </td>
                </tr>
                <tr class="border">
                  <td class="text-left border border-gray-300 p-1 ">PPH 21 {{ totalAdjPPHOpen.value.length ?
                    totalAdjPPHOpen.value[0].label : '' }}</td>
                  <td class="text-right border border-gray-300 p-0 ">
                    <button v-if="!totalAdjPPHOpen.value.length" @click="generatePPH" class="bg-blue-600 px-3 py-1 w-full text-white cursor-pointer" title="klik untuk menentukan perhitungan pph">Hitung PPH</button>
                    <FieldNumber v-else :bind="{ readonly: true}" class="!mt-0 flex justify-end !p-0"
                      :value="totalAdjPPHOpen.value[0].value" @input="totalAdjPPHOpen.value[0].value" type="number"
                      label="" :check="false" />
                  </td>
                </tr>
                <tr class="border">
                  <td class="text-left border border-gray-300 p-1 font-bold bg-gray-200">Total Penyesuaian Gaji Setelah
                    PPH 21</td>
                  <td class="text-right border border-gray-300 p-0 font-bold bg-gray-200">
                    <FieldNumber :bind="{ readonly: true}" class="!mt-0 flex justify-end !p-0"
                      :value="totalAdjFinalOpen.value" type="number" label="" :check="false" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <!-- Modal Footer -->
      <div class="modal-footer flex justify-end mt-2">
        <button @click="closeModal" class="modal-button bg-red-500 hover:bg-red-600 text-white font-semibold ml-2 px-2 py-1 rounded-sm">
          Tutup
        </button>
        <button v-show="actionText" @click="saveModal(detailArrOpen.items.length)" class="modal-button bg-blue-500 hover:bg-blue-600 text-white font-semibold ml-2 px-2 py-1 rounded-sm">
          Simpan
        </button>
      </div>
    </div>
  </div>
</div>
@endverbatim