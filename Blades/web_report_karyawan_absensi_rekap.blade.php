@php
  $req = app()->request;
  $tipe = $req->tipe_report;

  $rekap = [];
  $periode = $req->periode.'-01';
  $dateNow = date('Y-m-d');
  $data = \DB::select("
    select 
      employee_attendance(?,k.id) absen,
      (select   
        TO_CHAR(INTERVAL '1 second' * AVG(EXTRACT(EPOCH FROM pa.checkin_time::TIME)), 'HH24:MI:SS')
        from presensi_absensi pa where pa.default_user_id = u.id) checkin_avg,
      (select   
        TO_CHAR(INTERVAL '1 second' * AVG(EXTRACT(EPOCH FROM pa.checkout_time::TIME)), 'HH24:MI:SS')
        from presensi_absensi pa where pa.default_user_id = u.id) checkout_avg,
      k.id, kode, nama_lengkap, d.nama dept 
    from m_kary k
    join default_users u on u.m_kary_id = k.id
    join m_dept d on d.id = k.m_dept_id
        where k.is_active = true 
        and k.m_dept_id IS NOT NULL and k.m_dept_id != 0
        and k.m_divisi_id = COALESCE(?, k.m_divisi_id) 
        and k.m_dept_id = COALESCE(?, k.m_dept_id)
        and k.id = COALESCE(?, k.id)
        ",[
          $periode, $req->m_divisi_id, $req->m_dept_id, $req->kary_id
        ]);
  
@endphp
<span style="font-weight:bold; font-size: 10pt">{{$tipe}}</span><br/>
<span style="font-weight:bold; font-size: 9pt"> Periode  {{$req->periode}}</span><br/></br>
<span style="font-weight:bold; font-style: italic; font-size: 8pt"> HK : Hari Kerja</span><br/>
<span style="font-weight:bold; font-style: italic; font-size: 8pt"> H : Masuk Kerja</span><br/>
<span style="font-weight:bold; font-style: italic; font-size: 8pt"> I : Ijin / Sakit / Cuti</span><br/>
<span style="font-weight:bold; font-style: italic; font-size: 8pt"> A : Tidak Masuk Kerja / Tanpa Keterangan</span><br/>
<span style="font-weight:bold; font-style: italic; font-size: 8pt"> CI AVG : Rata-rata Jam Checkin</span><br/>
<span style="font-weight:bold; font-style: italic; font-size: 8pt"> CO AVG : Rata-rata Jam Checkout</span><br/>
<br/>
<table v-else class="table-auto w-full" cellpadding="3">
  <thead class="bg-[#c6c6c6]">
    <tr>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6;">NIK</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6; width: 25%;">Karyawan</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6; width: 27%;">Departemen</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6; width: 3.5%;">HK</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6; width: 3.5%;">H</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6; width: 3.5%;">I</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6; width: 3.5%;">A</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6;">CI AVG</th>
      <th style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; background-color: #c6c6c6;">CO AVG</th>
    </tr>
  </thead>
  <tbody>
    @foreach($data as $dt)
      @php
        $absenData = @json_decode($dt->absen);
        $workDays = $absenData->work_days_in_month ?? '-';
        $workPresent = $absenData->work_present ?? 0;
        $cutiCount = $absenData->cuti_terpakai ?? 0;
        $workNotPresent = $absenData->work_not_present ?? 0;

        if ($cutiCount == 0) {
            $startOfMonth = \Carbon\Carbon::parse($periode)->startOfMonth()->format('Y-m-d');
            $endOfMonth = \Carbon\Carbon::parse($periode)->endOfMonth()->format('Y-m-d');
            $cutiRecs = \DB::table('t_cuti')
                ->where('m_kary_id', $dt->id)
                ->whereIn('status', ['APPROVED', 'POSTED'])
                ->where('date_from', '<=', $endOfMonth)
                ->where('date_to', '>=', $startOfMonth)
                ->get();
            if ($cutiRecs->isNotEmpty()) {
                $addedCuti = 0;
                foreach ($cutiRecs as $cr) {
                    $from = max($startOfMonth, $cr->date_from);
                    $to = min($endOfMonth, $cr->date_to);
                    $addedCuti += (\Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) + 1);
                }
                $cutiCount = $addedCuti;
                if (is_numeric($workNotPresent)) {
                    $workNotPresent = max(0, $workNotPresent - $addedCuti);
                }
            }
        }
      @endphp
        <tr>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse;">{{ $dt->kode }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; width: 25%;">{{ $dt->nama_lengkap }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; width: 27%;">{{ $dt->dept }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; text-align: right; width: 3.5%">{{ $workDays }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; text-align: right; width: 3.5%;">{{ $workPresent }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; text-align: right; width: 3.5%;">{{ $cutiCount }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; text-align: right; width: 3.5%;">{{ $workNotPresent }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; text-align: right;">{{ $dt->checkin_avg }}</td>
          <td style="border:0.5px solid black; padding: 2px; font-size: 9pt; border-collapse: collapse; text-align: right;">{{ $dt->checkout_avg }}</td>
        </tr>
    @endforeach
  </tbody>
</table>
