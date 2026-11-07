@if(!$req->has('id'))
<div class="bg-white p-6 rounded-xl h-[570px]">
  <TableApi ref='apiTable' :api="landing.api" :columns="landing.columns" :actions="landing.actions">
    <template #header>
      <div class="flex items-center gap-x-2 mr-2">
        <p class="text-sm font-medium">Filter Status :</p>
        <div class="flex gap-x-2">
          <button @click="filterShowData(true,1)" :class="activeBtn === 1 ? 'bg-green-600 text-white hover:bg-green-400' : 'border border-green-600 text-green-600 bg-white hover:bg-green-600 hover:text-white'" class="duration-300 transform hover:-translate-y-0.5 rounded-md py-1 px-2 text-sm">Active</button>
          <div class="flex my-auto h-4 w-0.5 bg-[#6E91D1]"></div>
          <button @click="filterShowData(false,2)" :class="activeBtn === 2 ? 'bg-red-600 text-white hover:bg-red-400' : 'border border-red-600 text-red-600 bg-white hover:bg-red-600 hover:text-white'" class="duration-300 transform hover:-translate-y-0.5 rounded-md py-1 px-2 text-sm">Inactive</button>
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
          Form Master Responsibility
        </h1>
        <hr>
      </div>

      <!-- FORM HEADER -->
      <div class="grid <md:grid-cols-1 grid-cols-2 gap-4">
        <div>
          <label class="font-semibold">Kode</label>
          <FieldX :bind="{ readonly: true }" class="w-full py-2 !mt-0"
            :value="values.kode" :errorText="formErrors.kode?'failed':''"
            @input="v=>values.kode=v" :hints="formErrors.kode" 
            :check="false" placeholder="Auto Generate"
          />
        </div>

        <div>
          <label class="font-semibold">Nama Responsibility <span class="text-red-500">*</span></label>
          <FieldX :bind="{ readonly: !actionText }" class="w-full py-2 !mt-0"
            :value="values.nama" :errorText="formErrors.nama?'failed':''"
            @input="v=>values.nama=v" :hints="formErrors.nama" 
            :check="false" placeholder="Contoh: HRD - PT BBS"
          />
        </div>

        <div>
          <label class="font-semibold">Company / Direktorat <span class="text-red-500">*</span></label>
          <FieldSelect
            :bind="{ disabled: !actionText, clearable: true }"
            class="w-full py-2 !mt-0"
            :value="values.m_dir_id"
            @input="v=>values.m_dir_id=v"
            :errorText="formErrors.m_dir_id?'failed':''"
            :hints="formErrors.m_dir_id"
            placeholder="Pilih Company / Direktorat"
            displayField="nama"
            valueField="id"
            :api="{
              url: `${store.server.url_backend}/operation/m_dir`,
              headers: {
                'Content-Type': 'Application/json',
                Authorization: `${store.user.token_type} ${store.user.token}`
              },
              params: {
                simplest: true,
                where: `this.is_active='true'`,
                transform: false,
                selectfield: 'this.id, this.nama, this.is_active'
              }
            }"
            fa-icon="search" :check="false" />
        </div>

        <div>
          <label class="font-semibold">Divisi <small class="text-gray-500 font-normal">(Kosongkan jika All Divisi)</small></label>
          <FieldSelect
            :bind="{ disabled: !actionText || !values.m_dir_id, clearable: true }"
            class="w-full py-2 !mt-0"
            :value="values.m_divisi_id"
            @input="v=>values.m_divisi_id=v"
            :errorText="formErrors.m_divisi_id?'failed':''"
            :hints="formErrors.m_divisi_id"
            placeholder="Pilih Divisi (Opsional)"
            displayField="nama"
            valueField="id"
            :api="{
              url: `${store.server.url_backend}/operation/m_divisi`,
              headers: {
                'Content-Type': 'Application/json',
                Authorization: `${store.user.token_type} ${store.user.token}`
              },
              params: {
                simplest: true,
                where: values.m_dir_id ? `this.m_dir_id=${values.m_dir_id} AND this.is_active='true'` : `this.is_active='true'`,
                transform: false,
                selectfield: 'this.id, this.nama, this.is_active'
              }
            }"
            fa-icon="search" :check="false" />
        </div>

        <div>
          <label class="font-semibold">Departemen <small class="text-gray-500 font-normal">(Kosongkan jika All Dept)</small></label>
          <FieldSelect
            :bind="{ disabled: !actionText || !values.m_divisi_id, clearable: true }"
            class="w-full py-2 !mt-0"
            :value="values.m_dept_id"
            @input="v=>values.m_dept_id=v"
            :errorText="formErrors.m_dept_id?'failed':''"
            :hints="formErrors.m_dept_id"
            placeholder="Pilih Departemen (Opsional)"
            displayField="nama"
            valueField="id"
            :api="{
              url: `${store.server.url_backend}/operation/m_dept`,
              headers: {
                'Content-Type': 'Application/json',
                Authorization: `${store.user.token_type} ${store.user.token}`
              },
              params: {
                simplest: true,
                where: values.m_divisi_id ? `this.m_divisi_id=${values.m_divisi_id} AND this.is_active='true'` : `this.is_active='true'`,
                transform: false,
                selectfield: 'this.id, this.nama, this.is_active'
              }
            }"
            fa-icon="search" :check="false" />
        </div>

        <div class="flex flex-col justify-center">
          <label class="font-semibold mb-2">Status Active :</label>
          <div class="flex w-40 items-center">
            <span :class="!values.is_active ? 'font-bold text-red-500 text-sm' : 'text-gray-400 text-sm'">Inactive</span>
            <input
              class="mx-3 h-4 w-9 appearance-none rounded-full bg-neutral-300 checked:bg-emerald-500 hover:cursor-pointer transition-colors relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:w-3 after:h-3 after:rounded-full after:transition-transform checked:after:translate-x-5"
              type="checkbox"
              role="switch"
              :disabled="!actionText"
              v-model="values.is_active" />
            <span :class="values.is_active ? 'font-bold text-emerald-600 text-sm' : 'text-gray-400 text-sm'">Active</span>
          </div>
        </div>

        <div class="col-span-2">
          <label class="font-semibold">Keterangan</label>
          <FieldX :bind="{ readonly: !actionText }" class="w-full py-2 !mt-0"
            :value="values.desc" :errorText="formErrors.desc?'failed':''"
            @input="v=>values.desc=v" :hints="formErrors.desc" 
            :check="false" placeholder="Catatan atau ruang lingkup responsibility"
          />
        </div>
      </div>

      <!-- DETAIL ROLES GRID -->
      <div class="mt-6 pt-4 border-t">
        <div class="flex justify-between items-center mb-3">
          <div>
            <h2 class="text-lg font-bold text-gray-800">Daftar Hak Akses (Roles)</h2>
            <p class="text-xs text-gray-500">Tentukan role apa saja yang tergabung dalam responsibility ini.</p>
          </div>
          <div v-if="actionText" class="flex gap-2">
            <ButtonMultiSelect
              title="Pilih Role"
              @add="onDetailAdd"
              :api="{
                url: `${store.server.url_backend}/operation/m_role`,
                headers: {
                  'Content-Type': 'Application/json',
                  Authorization: `${store.user.token_type} ${store.user.token}`
                },
                params: {
                  simplest: true,
                  paginate: 500,
                  where: `this.is_active='true'`,
                  searchfield: 'name'
                },
                onsuccess: (response) => {
                  response.page = 1
                  response.hasNext = false
                  return response
                }
              }"
              :columns="[
                {
                  headerName: 'No',
                  valueGetter: (params) => params.node.rowIndex + 1,
                  width: 60,
                  sortable: false, resizable: false, filter: false,
                  cellClass: ['justify-center', 'bg-gray-50']
                },
                {
                  field: 'name',
                  headerName: 'Nama Role',
                  cellClass: ['border-r', '!border-gray-200'],
                  filter: true,
                  flex: 1
                },
                {
                  checkboxSelection: true,
                  headerCheckboxSelection: true,
                  headerName: 'Pilih',
                  width: 80,
                  sortable: false, resizable: false, filter: false,
                  cellClass: ['justify-center', 'bg-gray-50']
                }
              ]"
            >
              <div class="flex justify-center items-center px-3 py-2 text-xs rounded text-white bg-blue-600 hover:bg-blue-700 font-semibold cursor-pointer transition">
                <icon fa="plus" size="sm mr-1" /> Tambah Role
              </div>
            </ButtonMultiSelect>
          </div>
        </div>

        <TableStatic
          customClass="h-48"
          ref="detail"
          :value="trx_dtl"
          :columns="[
            {
              headerName: 'No',
              cellRenderer: 'ButtonGrid',
              valueGetter: p => p.node.rowIndex + 1,
              cellRendererParams: {
                showValue: true,
                icon: 'times',
                class: 'btn-text-danger',
                click: (app) => {
                  if (app && app.params && actionText) {
                    removeRowDetail(app.params.node.data)
                  }
                }
              },
              width: 70,
              sortable: false, resizable: true, filter: false,
              cellClass: ['justify-center', 'bg-gray-50']
            },
            {
              flex: 1,
              headerName: 'Nama Role',
              field: 'role_name',
              editable: false,
              sortable: false, resizable: true, filter: false,
              cellClass: ['!border-gray-200', 'font-medium']
            },
            {
              flex: 1,
              headerName: 'Keterangan Tambahan',
              field: 'desc',
              editable: !!actionText,
              sortable: false, resizable: true, filter: false,
              cellClass: ['!border-gray-200']
            }
          ]"
        >
          <template #header></template>
        </TableStatic>
      </div>

      <!-- ACTION BUTTONS -->
      <div class="flex flex-row justify-end space-x-3 mt-6 pt-4 border-t">
        <button @click="onBack" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-md font-medium transition">
          Batal
        </button>
        <button v-show="actionText" @click="onSave" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-md font-medium transition flex items-center gap-2">
          <icon fa="save" /> Simpan
        </button>
      </div>

    </div>
  </div>
</div>
@endverbatim
@endif

