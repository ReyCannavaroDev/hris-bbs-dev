@if(!$req->has('id'))
<div class="bg-white p-6 rounded-xl h-[570px]">
  <TableApi ref='apiTable' :api="landing.api" :columns="landing.columns" :actions="landing.actions">
    <template #header>
      <div class="flex items-center gap-x-2 mr-2">
        <p class="text-sm font-medium">Filter Status :</p>
        <div class="flex gap-x-2">
          <button @click="filterShowData(true,1)" :class="activeBtn === 1?'bg-green-600 text-white hover:bg-green-400':'border border-green-600 text-green-600 bg-white hover:bg-green-600 hover:text-white'" class="duration-300 transform hover:-translate-y-0.5 rounded-md py-1 px-2 text-sm">Active</button>
          <div class="flex my-auto h-4 w-0.5 bg-[#6E91D1]"></div>
          <button @click="filterShowData(false,2)" :class="activeBtn === 2?'bg-red-600 text-white hover:bg-red-400':'border border-red-600 text-red-600 bg-white hover:bg-red-600 hover:text-white'" class="duration-300 transform hover:-translate-y-0.5 rounded-md py-1 px-2 text-sm">Inactive</button>
        </div>
      </div>
      <RouterLink v-if="currentMenu?.can_create||true||store.user.data.username==='developer'" :to="$route.path+'/create?'+(Date.parse(new Date()))" class="bg-green-500 text-white hover:bg-green-600 rounded-[6px] py-2 px-[12.5px]">
        Tambah
        <icon fa="plus" />
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
          Form Pengguna
        </h1>
        <hr>
      </div>
      <div class="grid grid-cols-2 gap-2">
        <!-- START COLUMN -->
        <div>
          <label class="font-semibold">Company <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: true }" class="w-full py-2 !mt-0"
              :value="values.company" :errorname="formErrors.company?'failed':''"
              @input="v=>values.company=v" :hints="formErrors.company" 
              label=""
              :check="false"
          />
        </div>
        <div>
          <label class="font-semibold">Divisi <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: true }" class="w-full py-2 !mt-0"
              :value="values.divisi" :errorname="formErrors.divisi?'failed':''"
              @input="v=>values.divisi=v" :hints="formErrors.divisi" 
              label=""
              :check="false"
          />
        </div>
        <div>
          <label class="font-semibold">Departemen <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: true }" class="w-full py-2 !mt-0"
              :value="values.departemen" :errorname="formErrors.departemen?'failed':''"
              @input="v=>values.departemen=v" :hints="formErrors.departemen" 
              label=""
              :check="false"
          />
        </div>
        <div>
          <label class="font-semibold">Kode <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: true }" class="w-full py-2 !mt-0"
              :value="values.kode" :errorname="formErrors.kode?'failed':''"
              @input="v=>values.kode=v" :hints="formErrors.kode" 
              :check="false"
              placeholder="Auto Generate"
              label=""
          />
        </div>
        <div>
          <label class="font-semibold">NIK <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldPopup 
              :bind="{ readonly: !actionText, disabled: !actionText }"
              class="w-full py-2 !mt-0"
              valueField="id" displayField="kode"
              :value="values.m_kary_id"
              @input="(v)=>{ values.m_kary_id = v; nikChange(v); }"
              :api="{
                url: `${store.server.url_backend}/operation/m_kary`,
                headers: { 'Content-Type': 'Application/json', Authorization: `${store.user.token_type} ${store.user.token}`},
                params: {
                  simplest:true,
                }
              }"
              placeholder="Pilih NIK"
              label=""
              fa-icon="user" :check="false" 
              :columns="[{
                headerName: 'No',
                valueGetter:(p)=>p.node.rowIndex + 1,
                width: 60,
                sortable: false, resizable: false, filter: false,
                cellClass: ['justify-center', 'bg-gray-50']
              },
              {
                flex: 1,
                field: 'nama_lengkap',
                headerName:  'Nama Lengkap',
                sortable: false, resizable: true, filter: 'ColFilter',
                cellClass: ['border-r', '!border-gray-200', 'justify-center']
              },
              {
                flex: 1,
                field: 'kode',
                headerName:  'NIK',
                sortable: false, resizable: true, filter: 'ColFilter',
                cellClass: ['border-r', '!border-gray-200', 'justify-center']
              },
              {
                flex: 1,
                field: 'nik',
                headerName: 'No KTP',
                sortable: false, resizable: true, filter: 'ColFilter',
                cellClass: ['border-r', '!border-gray-200', 'justify-center']
              },
              {
                flex: 1,
                field: 'm_dept.nama',
                headerName:  'Departemen',
                sortable: false, resizable: true, filter: 'ColFilter',
                cellClass: ['border-r', '!border-gray-200', 'justify-center']
              },
              {
                flex: 1,
                field: 'm_zona.nama',
                headerName:  'Zona',
                sortable: false, resizable: true, filter: 'ColFilter',
                cellClass: ['border-r', '!border-gray-200', 'justify-center']
              },
              ]"
            />
        </div>
        <div>
          <label class="font-semibold">Nama Lengkap <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: true }" class="w-full py-2 !mt-0"
              :value="values.name" :errorname="formErrors.name?'failed':''"
              @input="v=>values.name=v" :hints="formErrors.name" 
              label=""
              :check="false"
          />
        </div>
        <div>
          <label class="font-semibold">Username <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: !actionText }" class="w-full py-2 !mt-0"
              :value="values.username" :errorname="formErrors.username?'failed':''"
              @input="v=>values.username=v" :hints="formErrors.username" 
              label=""
              :check="false"
          />
        </div>
        <div>
          <label class="font-semibold">Email <label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: !actionText }" class="w-full py-2 !mt-0"
              :value="values.email" :errorname="formErrors.email?'failed':''"
              @input="v=>values.email=v" :hints="formErrors.email" 
              label=""
              :check="false"
          />
        </div>
        <div class="col-span-2 text-red-500" v-if="isRead && actionText">
          <i>Mengisi kolom password akan mereset password pengguna</i>
        </div>
        <div class="col-span-2 text-red-500" v-if="values.default_password">
          <i>Default Password : {{values.default_password}}</i>
        </div>
        <div v-if="actionText">
          <label class="font-semibold">Password<label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: !actionText }" class="w-full py-2 !mt-0"
              :value="values.password" :errorname="formErrors.password?'failed':''"
              @input="v=>values.password=v" :hints="formErrors.password" 
              label=""
              :check="false"
              type="password"
              fa-icon="lock"
          />
        </div>

        <div v-if="actionText">
          <label class="font-semibold">Konfirmasi Password<label class="text-red-500 space-x-0 pl-0"></label></label>
          <FieldX :bind="{ readonly: !actionText }" class="w-full py-2 !mt-0"
              :value="values.password_confirm" :errorname="formErrors.password_confirm?'failed':''"
              @input="v=>values.password_confirm=v" :hints="formErrors.password_confirm" 
              label=""
              :check="false"
              type="password"
              fa-icon="lock"
          />
        </div>
        
        <div class="flex flex-col gap-2">
          <label
            class="inline-block pl-[0.15rem] hover:cursor-pointer font-semibold"
            for="is_active"
            >Status :</label
          >
          <div class="flex w-40">
            <div class="flex-auto">
              <i class="text-red-500">InActive</i>
            </div>
            <div class="flex-auto">
              <input
                class="mr-2 mt-[0.3rem] h-3.5 w-8 appearance-none rounded-[0.4375rem] bg-neutral-300 before:pointer-events-none before:absolute before:h-3.5 before:w-3.5 before:rounded-full before:bg-transparent before:content-[''] after:absolute after:z-[2] after:-mt-[0.1875rem] after:h-5 after:w-5 after:rounded-full after:border-none after:bg-blue-500 after:shadow-[0_0px_3px_0_rgb(0_0_0_/_7%),_0_2px_2px_0_rgb(0_0_0_/_4%)] after:transition-[background-color_0.2s,transform_0.2s] after:content-[''] checked:bg-primary checked:after:absolute checked:after:z-[2] checked:after:-mt-[3px] checked:after:ml-[1.0625rem] checked:after:h-5 checked:after:w-5 checked:after:rounded-full checked:after:border-none checked:after:bg-primary checked:after:shadow-[0_3px_1px_-2px_rgba(0,0,0,0.2),_0_2px_2px_0_rgba(0,0,0,0.14),_0_1px_5px_0_rgba(0,0,0,0.12)] checked:after:transition-[background-color_0.2s,transform_0.2s] checked:after:content-[''] hover:cursor-pointer focus:outline-none focus:ring-0 focus:before:scale-100 focus:before:opacity-[0.12] focus:before:shadow-[3px_-1px_0px_13px_rgba(0,0,0,0.6)] focus:before:transition-[box-shadow_0.2s,transform_0.2s] focus:after:absolute focus:after:z-[1] focus:after:block focus:after:h-5 focus:after:w-5 focus:after:rounded-full focus:after:content-[''] checked:focus:border-primary checked:focus:bg-primary checked:focus:before:ml-[1.0625rem] checked:focus:before:scale-100 checked:focus:before:shadow-[3px_-1px_0px_13px_#3b71ca] checked:focus:before:transition-[box-shadow_0.2s,transform_0.2s] dark:bg-neutral-600 dark:after:bg-neutral-400 dark:checked:bg-primary dark:checked:after:bg-primary dark:focus:before:shadow-[3px_-1px_0px_13px_rgba(255,255,255,0.4)] dark:checked:focus:before:shadow-[3px_-1px_0px_13px_#3b71ca]"
                type="checkbox"
                role="switch"
                id="is_active"
                :disabled="!actionText"
                v-model="values.is_active" />
            </div>
            <div class="flex-auto">
              <i class="text-green-500">Active</i>
            </div>
          </div>
        </div>
        <!-- END COLUMN -->
      </div>

      <!-- DAFTAR RESPONSIBILITY SECTION -->
      <div class="mt-8 border-t pt-6">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h2 class="text-[18px] font-bold text-gray-800">Daftar Responsibility</h2>
            <p class="text-xs text-gray-500 mt-0.5">Tentukan responsibility dan ruang lingkup hak akses untuk pengguna ini.</p>
          </div>
          <div v-if="actionText">
            <ButtonMultiSelect title="Tambah Responsibility" @add="onRespoAdd" :api="{
                url: `${store.server.url_backend}/operation/m_respo`,
                headers: {'Content-Type': 'Application/json', authorization: `${store.user.token_type} ${store.user.token}`},
                params: { 
                  simplest: true,
                  join: true,
                  where: 'this.is_active = true'  
                },
                onsuccess: (response) => filterAvailableRespo(response)
              }" :columns="[
                {
                  headerName: 'No',
                  valueGetter:(params)=>params.node.rowIndex + 1,
                  width: 60,
                  sortable: false, resizable: false, filter: false,
                  cellClass: ['justify-center', 'bg-gray-50']
                },
                {
                  field: 'nama',
                  headerName: 'Nama Responsibility',
                  flex: 1
                },
                {
                  field: 'm_dir.nama',
                  headerName: 'Company',
                  flex: 1,
                  valueGetter: (p) => p.data?.['m_dir.nama'] || (!p.data?.m_dir_id ? 'Holding (Semua Company)' : '-')
                },
                {
                  field: 'm_divisi.nama',
                  headerName: 'Divisi',
                  flex: 1
                },
                {
                  field: 'm_dept.nama',
                  headerName: 'Departemen',
                  flex: 1
                },
                {
                  headerName: 'Pilih',
                  checkboxSelection: true,
                  headerCheckboxSelection: true,
                  width: 80,
                  sortable: false, resizable: false, filter: false,
                  cellClass: ['justify-center', 'bg-gray-50']
                }
              ]">
              <div class="flex justify-center items-center px-4 py-2 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-all duration-200 cursor-pointer shadow-sm">
                <icon fa="plus" size="sm mr-1.5" /> Tambah Responsibility
              </div>
            </ButtonMultiSelect>
          </div>
        </div>

        <!-- TABLE RESPONSIBILITY -->
        <div class="overflow-x-auto border border-gray-200 rounded-lg shadow-sm">
          <table class="w-full text-sm text-left border-collapse">
            <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold">
              <tr>
                <th class="p-3 text-center border-b border-r border-gray-200 w-12">No.</th>
                <th class="p-3 border-b border-r border-gray-200">Company</th>
                <th class="p-3 border-b border-r border-gray-200">Divisi</th>
                <th class="p-3 border-b border-r border-gray-200">Departemen</th>
                <th class="p-3 border-b border-r border-gray-200">Responsibility</th>
                <th class="p-3 text-center border-b border-r border-gray-200 w-28">Is Primary</th>
                <th class="p-3 text-center border-b border-r border-gray-200 w-36">Status</th>
                <th class="p-3 text-center border-b border-gray-200 w-16" v-if="actionText">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="(item, i) in trx_respo" :key="item.m_respo_id || i" class="hover:bg-gray-50 transition-colors">
                <td class="p-3 text-center border-r border-gray-200 font-medium text-gray-600">{{ i + 1 }}.</td>
                <td class="p-3 border-r border-gray-200 font-medium text-gray-800">{{ item.company_nama || (!item.m_dir_id ? 'Holding (Semua Company)' : '-') }}</td>
                <td class="p-3 border-r border-gray-200 text-gray-600">{{ item.divisi_nama || '-' }}</td>
                <td class="p-3 border-r border-gray-200 text-gray-600">{{ item.dept_nama || '-' }}</td>
                <td class="p-3 border-r border-gray-200 font-semibold text-primary">{{ item.respo_nama || '-' }}</td>
                <td class="p-3 text-center border-r border-gray-200">
                  <div class="flex justify-center">
                    <input 
                      type="checkbox"
                      class="w-5 h-5 text-blue-600 bg-white border-2 border-gray-400 rounded focus:ring-blue-500 transition-all"
                      :class="(trx_respo.length === 1 || !actionText) ? 'cursor-not-allowed opacity-80' : 'cursor-pointer'"
                      :checked="Boolean(item.is_primary)"
                      :disabled="!actionText || (trx_respo.length === 1 && item.is_primary)"
                      :title="trx_respo.length === 1 ? 'Pengguna dengan 1 responsibility wajib berstatus Primary' : (item.is_primary ? 'Responsibility utama yang aktif' : 'Jadikan responsibility utama')"
                      @click="togglePrimary(item)"
                      @change="togglePrimary(item)"
                    />
                  </div>
                </td>
                <td class="p-3 text-center border-r border-gray-200">
                  <div class="flex w-36 mx-auto items-center">
                    <div class="flex-auto text-right">
                      <i class="text-red-500 text-xs">InActive</i>
                    </div>
                    <div class="flex-auto flex justify-center">
                      <input
                        class="mr-2 mt-[0.3rem] h-3.5 w-8 appearance-none rounded-[0.4375rem] bg-neutral-300 before:pointer-events-none before:absolute before:h-3.5 before:w-3.5 before:rounded-full before:bg-transparent before:content-[''] after:absolute after:z-[2] after:-mt-[0.1875rem] after:h-5 after:w-5 after:rounded-full after:border-none after:bg-blue-500 after:shadow-[0_0px_3px_0_rgb(0_0_0_/_7%),_0_2px_2px_0_rgb(0_0_0_/_4%)] after:transition-[background-color_0.2s,transform_0.2s] after:content-[''] checked:bg-primary checked:after:absolute checked:after:z-[2] checked:after:-mt-[3px] checked:after:ml-[1.0625rem] checked:after:h-5 checked:after:w-5 checked:after:rounded-full checked:after:border-none checked:after:bg-primary checked:after:shadow-[0_3px_1px_-2px_rgba(0,0,0,0.2),_0_2px_2px_0_rgba(0,0,0,0.14),_0_1px_5px_0_rgba(0,0,0,0.12)] checked:after:transition-[background-color_0.2s,transform_0.2s] checked:after:content-[''] hover:cursor-pointer focus:outline-none focus:ring-0 focus:before:scale-100 focus:before:opacity-[0.12] focus:before:shadow-[3px_-1px_0px_13px_rgba(0,0,0,0.6)] focus:before:transition-[box-shadow_0.2s,transform_0.2s] focus:after:absolute focus:after:z-[1] focus:after:block focus:after:h-5 focus:after:w-5 focus:after:rounded-full focus:after:content-[''] checked:focus:border-primary checked:focus:bg-primary checked:focus:before:ml-[1.0625rem] checked:focus:before:scale-100 checked:focus:before:shadow-[3px_-1px_0px_13px_#3b71ca] checked:focus:before:transition-[box-shadow_0.2s,transform_0.2s] dark:bg-neutral-600 dark:after:bg-neutral-400 dark:checked:bg-primary dark:checked:after:bg-primary dark:focus:before:shadow-[3px_-1px_0px_13px_rgba(255,255,255,0.4)] dark:checked:focus:before:shadow-[3px_-1px_0px_13px_#3b71ca]"
                        type="checkbox"
                        role="switch"
                        :id="'respo_active_' + i"
                        :disabled="!actionText"
                        v-model="item.is_active"
                        @change="onToggleChange(item)" />
                    </div>
                    <div class="flex-auto text-left">
                      <i class="text-green-500 text-xs">Active</i>
                    </div>
                  </div>
                </td>
                <td class="p-3 text-center" v-if="actionText">
                  <button 
                    type="button" 
                    @click="onRemoveRespo(i)" 
                    class="text-red-500 hover:text-red-700 transition-colors p-1"
                    title="Hapus Responsibility"
                  >
                    <icon fa="trash" size="sm" />
                  </button>
                </td>
              </tr>
              <tr v-if="trx_respo.length === 0">
                <td :colspan="actionText ? 8 : 7" class="text-center py-8 text-gray-400 italic bg-gray-50">
                  Belum ada responsibility yang ditambahkan untuk pengguna ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ACTION BUTTON START -->
      <div class="flex flex-row justify-end space-x-[20px] mt-[3em]">
        <button @click="onBack" class="bg-[#EF4444] hover:bg-[#ed3232] text-white px-[36.5px] py-[12px] rounded-[6px] transition-colors">
          Kembali
        </button>
        <button v-show="actionText" @click="onSave" class="bg-[#10B981] hover:bg-[#0ea774] text-white px-[36.5px] py-[12px] rounded-[6px] transition-colors">
          Simpan
        </button>
      </div>
      </div>
    </div>
  </div>
</div>
@endverbatim
@endif