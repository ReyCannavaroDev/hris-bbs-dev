@if(!$req->has('id'))

@verbatim
<div class="w-full">
  <div class="w-full bg-white min-h-screen p-6">


  <!-- Tabs -->
  <div class="w-full border-b border-gray-200 flex justify-center gap-12 text-sm">
    <button
      type="button"
      class="px-6 py-3 border-b-2 duration-300"
      :class="activeTabIndex === 0
        ? 'border-blue-600 text-blue-600 font-semibold'
        : 'border-transparent text-gray-600 hover:text-blue-600 hover:border-blue-200'"
      @click="activeTabIndex = 0"
    >
      Absen
    </button>

    <button
      type="button"
      class="px-6 py-3 border-b-2 duration-300"
      :class="activeTabIndex === 1
        ? 'border-blue-600 text-blue-600 font-semibold'
        : 'border-transparent text-gray-600 hover:text-blue-600 hover:border-blue-200'"
      @click="activeTabIndex = 1"
    >
      Daftar Absensi
    </button>
  </div>

  <!-- TAB 0: ABSEN -->
  <div v-show="activeTabIndex === 0">
    <h1 class="font-semibold text-xl mt-8 text-center">
      {{form.attending?.toLowerCase() === 'not attend'
        ? 'Absen Checkin'
        : (form.attending?.toLowerCase() === 'working'
          ? 'Absen Checkout'
          : 'Sudah Absen')}}
    </h1>

    <div class="mt-4 lg:mt-6">
      <video
        style="transform: scaleX(-1)"
        v-show="!isImage"
        v-if="form.attending?.toLowerCase() !== 'attend'"
        ref="videoElement"
        autoplay
        playsinline
        muted
        class="rounded-xl h-full lg:h-[20rem] m-auto"
      ></video>

      <img
        v-show="isImage"
        id="imgElem"
        class="w-full lg:max-w-[426px] h-full lg:max-h-[320px] m-auto rounded-xl"
      />
    </div>

    <!-- Face Quality Coach (teks) -->
    <div
      v-if="form.attending?.toLowerCase() !== 'attend' && !isImage"
      class="mt-2 text-xs lg:text-sm text-center"
      :class="qualityLabel === 'Bagus'
        ? 'text-green-600'
        : (qualityLabel === 'Cukup'
          ? 'text-yellow-600'
          : (qualityLabel === 'Buruk'
            ? 'text-red-600'
            : 'text-gray-500'))"
    >
      <span v-if="qualityScore !== null">
        Kualitas wajah: {{ qualityScore }}/100 - {{ qualityMessage }}
      </span>
      <span v-else>
        Mengukur kualitas wajah...
      </span>
    </div>

    <!-- Toggle suara -->
    <div
      v-if="form.attending?.toLowerCase() !== 'attend' && !isImage"
      class="mt-1 text-[11px] lg:text-xs text-center flex items-center justify-center gap-2"
    >
      <input type="checkbox" v-model="voiceEnabled" class="border-gray-300 rounded" />
      <span>Panduan suara {{ voiceEnabled ? 'aktif' : 'mati' }}</span>
    </div>

    <div class="flex mt-4 justify-center space-x-4 lg:mt-6">
      <div v-show="form.attending?.toLowerCase() !== 'attend'">
        <button
          v-show="!isImage"
          @click="capture"
          class="bg-blue-600 hover:bg-blue-700 text-white w-fit px-6 py-2 rounded-lg m-auto"
        >
          Capture
        </button>
        <button
          v-show="isImage"
          @click="recapture"
          class="bg-yellow-600 hover:bg-yellow-700 text-white w-fit px-6 py-2 rounded-lg m-auto"
        >
          Recapture
        </button>
      </div>

      <div v-show="form.attending?.toLowerCase() !== 'attend'">
        <button
          @click="postAttend"
          v-show="isImage"
          class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg"
        >
          Absen {{form.attending?.toLowerCase() === 'not attend' ? 'Checkin' :'Checkout'}}
        </button>
      </div>

      <div v-show="form.attending?.toLowerCase() === 'attend'">
        <button
          @click="activeTabIndex = 1"
          class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg"
        >
          Lihat Detail Absen
        </button>
      </div>
    </div>

    <!-- Status Face Recognition -->
    <div
      v-if="faceResultText"
      class="mt-3 text-center text-xs lg:text-sm"
      :class="faceMatch === true
        ? 'text-green-600'
        : (faceMatch === false
          ? 'text-red-600'
          : 'text-gray-500')"
    >
      {{ faceResultText }}
    </div>

    <!-- Manual input -->
    <div v-if="manualMode" class="mt-3 max-w-md mx-auto">
      <label class="text-xs font-semibold text-gray-700 block mb-1">
        ID Karyawan (terkunci ke akun login)
      </label>
      <input
        :value="manualPilotEmployeeId"
        readonly
        class="border rounded px-3 py-2 w-full bg-gray-100 text-gray-600 cursor-not-allowed"
      />
      <div class="text-xs text-gray-500 mt-2 text-center leading-snug">
        Mode <span class="font-semibold">absen manual</span>.
        ID otomatis diambil dari akun yang sedang login dan akan dikirim ke sistem
        sebagai absen manual (tidak bisa diubah).
      </div>
    </div>

    <div class="flex justify-between mt-6">
      <div class="flex space-x-2 items-center">
        <icon fa="calendar" class="text-blue-600"/>
        <h2 class="text-md lg:pr-10 text-gray-700">
          {{form.day}}, {{form.tanggal}}
        </h2>
      </div>
      <div class="flex space-x-2 items-center">
        <icon fa="clock" class="text-blue-600"/>
        <h2 class="text-md lg:pr-10 text-gray-700">
          {{form.currentTime}}
        </h2>
      </div>
    </div>

    <hr>

    <div class="px-4">
      <table class="mt-2 lg:block hidden">
        <tr>
          <td class="align-top font-semibold">Lokasi</td>
          <td class="px-2 align-top font-semibold">:</td>
          <td class="align-top pb-2">{{form.address}}</td>
        </tr>
        <tr>
          <td class="align-top font-semibold">Koordinat</td>
          <td class="px-2 align-top font-semibold">:</td>
          <td class="align-top pb-2">
            {{ (form.lat && form.long) ? `${form.lat}, ${form.long}` : '-' }}
          </td>
        </tr>
        <tr>
          <td class="align-top font-semibold">Keterangan</td>
          <td class="px-2 align-top font-semibold">:</td>
          <td :class="form.distance_check ? 'text-green-600 align-top':'text-red-600 align-top'">
            {{form.distance_check ? 'On Scope' : 'Out Scope'}}
          </td>
        </tr>
        <tr>
          <td class="align-top font-semibold">Catatan</td>
          <td class="px-2 align-top font-semibold">:</td>
          <td v-if="!form.distance_check">
            <FieldX
              :value="form.catatan_in"
              :errorText="formErrors.catatan_in?'failed':''"
              @input="v=>form.catatan_in=v"
              :hints="formErrors.catatan_in"
              placeholder=""
              label=""
              fa-icon=""
              :check="false"
            />
          </td>
        </tr>
      </table>

      <table class="mt-4 block lg:hidden">
        <tr><td colspan="2" class="align-top font-semibold">Lokasi :</td></tr>
        <tr><td>&nbsp;</td><td class="align-top pb-6">{{form.address}}</td></tr>

        <tr><td colspan="2" class="align-top font-semibold">Koordinat :</td></tr>
        <tr>
          <td>&nbsp;</td>
          <td class="align-top pb-6">{{ (form.lat && form.long) ? `${form.lat}, ${form.long}` : '-' }}</td>
        </tr>

        <tr><td colspan="2" class="align-top font-semibold">Keterangan :</td></tr>
        <tr>
          <td>&nbsp;</td>
          <td :class="form.distance_check ? 'text-green-600 align-top':'text-red-600 align-top'">
            {{form.distance_check ? 'On Scope' : 'Out Scope'}}
          </td>
        </tr>
      </table>
    </div>
  </div>

 <!-- TAB: DAFTAR ABSENSI -->
<div v-show="activeTabIndex === 1">
  <!-- Filter Bulan/Tahun (Desktop) -->
  <div class="hidden lg:flex my-8 items-end gap-8">
    <div class="flex items-center gap-3">
      <label class="w-16 text-gray-700">Bulan :</label>
      <FieldSelect
        class="w-64"
        :bind="{ disabled: false, clearable:false }"
        :value="form.month" @input="v=>form.month=v"
        valueField="id" displayField="name"
        :options="listMonths"
        @update:valueFull="(e)=>{ getDetailAbsen(form.year, e.id) }"
        placeholder="Pilih Bulan" label="" :check="false"
      />
    </div>

    <div class="flex items-center gap-3">
      <label class="w-16 text-gray-700">Tahun :</label>
      <FieldSelect
        class="w-64"
        :bind="{ disabled: false, clearable:false }"
        :value="form.year" @input="v=>{ form.year=v }"
        valueField="id" displayField="key"
        :options="listTahun"
        @update:valueFull="(e)=>{ getDetailAbsen(e.id, form.month) }"
        placeholder="Tahun" label="" :check="false"
      />
    </div>
  </div>

  <!-- Filter Bulan/Tahun (Mobile) -->
  <div class="block lg:hidden my-8 space-y-4">
    <div class="flex items-center gap-3">
      <label class="w-16 text-gray-700">Bulan :</label>
      <FieldSelect
        class="w-full"
        :bind="{ disabled: false, clearable:false }"
        :value="form.month" @input="v=>form.month=v"
        valueField="id" displayField="name"
        :options="listMonths"
        @update:valueFull="(e)=>{ getDetailAbsen(form.year, e.id) }"
        placeholder="Pilih Bulan" label="" :check="false"
      />
    </div>

    <div class="flex items-center gap-3">
      <label class="w-16 text-gray-700">Tahun :</label>
      <FieldSelect
        class="w-full"
        :bind="{ disabled: false, clearable:false }"
        :value="form.year" @input="v=>{ form.year=v }"
        valueField="id" displayField="key"
        :options="listTahun"
        @update:valueFull="(e)=>{ getDetailAbsen(e.id, form.month) }"
        placeholder="Tahun" label="" :check="false"
      />
    </div>
  </div>

  <!-- LIST ABSENSI (PAKSA GRID, MIRIP GAMBAR 2) -->
  <div
    class="mt-4"
    style="
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 16px;
    "
  >
    <div v-if="isRequesting" style="grid-column: 1 / -1;" class="text-center text-gray-500 py-10">
      Memuat data absensi...
    </div>

    <div v-else-if="!listDetail || listDetail.length === 0" style="grid-column: 1 / -1;" class="text-center text-gray-500 py-10">
      Tidak ada data absensi untuk bulan & tahun ini.
    </div>

    <template v-else>
      <div
        v-for="(item, index) in listDetail"
        :key="index"
        @click="tampilkanModal(item)"
        style="
          background: #fff;
          border: 1px solid #e5e7eb;
          border-radius: 10px;
          padding: 16px;
          box-shadow: 0 1px 2px rgba(0,0,0,.06);
          transition: .15s;
        "
        onmouseover="this.style.boxShadow='0 6px 16px rgba(0,0,0,.10)'"
        onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,.06)'"
      >
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
          <span
            v-if="item.type?.toLowerCase() === 'hari kerja'"
            :class="item.status?.toLowerCase() === 'attend'
              ? 'bg-green-200 text-green-800'
              : (item.status?.toLowerCase() === 'working'
                ? 'bg-blue-200 text-blue-800'
                : 'bg-red-200 text-red-800')"
            class="inline-block font-semibold px-4 py-1 rounded"
          >
            {{ item.status?.toLowerCase() === 'attend'
              ? 'Hadir'
              : (item.status?.toLowerCase() === 'working'
                ? 'Belum Check Out'
                : 'Tidak Hadir') }}
          </span>

          <span v-else class="inline-block font-semibold px-4 py-1 rounded bg-gray-200 text-gray-800">
            Hari Libur
          </span>
        </div>

        <div class="font-medium text-gray-900" style="margin-bottom:10px;">
          {{ item.day_name_idn }}, {{ formatTanggal(item.date_to_idn) }}
        </div>

        <!-- jangan pakai justify-between, biar gak “Out” lari ke kanan jauh -->
        <div style="display:flex; gap:16px; font-size: 14px; color:#111827;">
          <span>In : <span style="font-weight:600;">{{ item.checkin_time ? item.checkin_time : '-' }}</span></span>
          <span>Out : <span style="font-weight:600;">{{ item.checkout_time ? item.checkout_time : '-' }}</span></span>
        </div>
      </div>
    </template>
  </div>
</div>
<!-- END TAB: DAFTAR ABSENSI -->

  <!-- MODAL DETAIL -->
  <div v-if="showModal" class="fixed inset-0 flex items-center justify-center z-50">
    <div class="fixed inset-0 bg-black opacity-50" @click="showModal=false"></div>

    <div class="bg-white w-[90%] lg:w-[70%] rounded shadow-lg z-10 overflow-auto max-h-[70%]">
      <div class="flex justify-between items-center px-[30px] py-[27px] border-b">
        <h2 class="text-2xl font-semibold">Detail</h2>
        <icon
          fa="remove"
          class="cursor-pointer text-[30px] font-normal text-[#8F8F8F]"
          @click="showModal=false"
        />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 text-[14px] gap-x-[29px] gap-y-[20px] px-[10px] lg:px-[30px] py-[27px]">

        <!-- CHECKIN -->
        <div class="flex space-x-4">
          <div class="w-[60%] lg:w-[40%]">
            <img
              v-if="dataDetail && dataDetail.checkin_foto"
              :src="`${dataDetail.checkin_foto}`"
              class="!mt-2 w-full lg:w-[166px]"
            >
            <div
              v-else
              class="h-[166px] bg-gray-500 w-full lg:w-[166px] rounded-[10px]"
            ></div>
          </div>

          <table class="w-full lg:block hidden table-auto">
            <tr class="h-fit">
              <td class="w-[30%] align-top h-fit font-semibold">Alamat Checkin</td>
              <td class="align-top h-fit px-2 w-[10%] font-semibold">:</td>
              <td class="align-top h-fit pb-2">
                {{dataDetail && dataDetail.checkin_address ? dataDetail.checkin_address : '-'}}
              </td>
            </tr>
            <tr>
              <td class="align-top h-fit font-semibold">Jam Checkin</td>
              <td class="align-top h-fit px-2 w-[10%] font-semibold">:</td>
              <td class="align-top h-fit pb-2">
                {{dataDetail && dataDetail.checkin_time ? dataDetail.checkin_time : '-'}}
              </td>
            </tr>
            <tr>
              <td class="align-top h-fit font-semibold">Office</td>
              <td class="align-top h-fit px-2 w-[10%] font-semibold">:</td>
              <td
                :class="(dataDetail?.checkin_region?.toLowerCase() === 'in scope')
                  ? 'text-green-600'
                  : ((dataDetail?.checkin_region?.toLowerCase() === 'out scope')
                    ? 'text-red-600'
                    : 'text-black')"
                class="align-top h-fit pb-2"
              >
                {{dataDetail && dataDetail.checkin_region ? dataDetail.checkin_region : '-'}}
              </td>
            </tr>
          </table>
        </div>

        <!-- CHECKOUT -->
        <div class="flex space-x-4">
          <div class="w-[60%] lg:w-[40%]">
            <img
              v-if="dataDetail && dataDetail.checkout_foto"
              :src="`${dataDetail.checkout_foto}`"
              class="!mt-2 w-full lg:w-[166px]"
            >
            <div
              v-else
              class="h-[166px] bg-gray-500 w-full lg:w-[166px] rounded-[10px]"
            ></div>
          </div>

          <table class="w-full lg:block hidden table-auto">
            <tr class="h-fit">
              <td class="w-[30%] align-top h-fit font-semibold">Alamat Checkout</td>
              <td class="align-top px-2 h-fit w-[10%] font-semibold">:</td>
              <td class="align-top h-fit pb-2">
                {{dataDetail && dataDetail.checkout_address ? dataDetail.checkout_address : '-'}}
              </td>
            </tr>
            <tr>
              <td class="align-top h-fit font-semibold">Jam Checkout</td>
              <td class="align-top px-2 h-fit w-[10%] font-semibold">:</td>
              <td class="align-top h-fit pb-2">
                {{dataDetail && dataDetail.checkout_time ? dataDetail.checkout_time : '-'}}
              </td>
            </tr>
            <tr>
              <td class="align-top h-fit font-semibold">Office</td>
              <td class="align-top px-2 h-fit w-[10%] font-semibold">:</td>
              <td
                :class="(dataDetail?.checkout_region?.toLowerCase() === 'in scope')
                  ? 'text-green-600'
                  : ((dataDetail?.checkout_region?.toLowerCase() === 'out scope')
                    ? 'text-red-600'
                    : 'text-black')"
                class="align-top h-fit pb-2"
              >
                {{dataDetail && dataDetail.checkout_region ? dataDetail.checkout_region : '-'}}
              </td>
            </tr>
          </table>
        </div>

      </div>
    </div>
  </div>

</div>
@endverbatim

@else

@verbatim
<div>
  <div class="flex flex-col bg-white p-6 w-full h-full">
    <Writer :value="values.content" @input="$log('halo')" />
  </div>
</div>
@endverbatim

@endif
