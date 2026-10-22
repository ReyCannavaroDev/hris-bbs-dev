@php
  $req = app()->request;
  $f_id = $req->f_id;

  $raw = \DB::select("
    select
      k.nik, k.jk_id, k.nama_lengkap, kd.nama dir, kdi.nama divisi, kde.nama dept, mp.desc_kerja posisi,
      fd.total_gaji, fd.total_tax, fd.netto, fd.periode_in_date, fd.periode,
      f.created_at, f.desc, f.nomor nomor_final_gaji, f.periode_awal, f.periode_akhir
    from t_final_gaji_det fd
    join t_final_gaji f on fd.t_final_gaji_id = f.id
    join m_kary k on k.id = fd.m_kary_id
    left join m_posisi mp on mp.id = k.m_posisi_id
    left join m_dir kd on kd.id = fd.m_kary_dir_id
    left join m_divisi kdi on kdi.id = fd.m_kary_divisi_id
    left join m_dept kde on kde.id = fd.m_kary_dept_id
    where f.status = 'POSTED' and f.id = ?
      and case when kd.id is not null then kd.id = coalesce(?, kd.id) else true end
      and case when kdi.id is not null then kdi.id = coalesce(?, kdi.id) else true end
      and case when kde.id is not null then kde.id = coalesce(?, kde.id) else true end
      and case when k.m_posisi_id is not null then k.m_posisi_id = coalesce(?, k.m_posisi_id) else true end
      and case when k.id is not null then k.id = coalesce(?, k.id) else true end
    order by fd.id asc
  ", [ $f_id ?? 0, $req->m_dir_id, $req->m_divisi_id, $req->m_dept_id, $req->m_posisi_id, $req->m_kary_id ]);

  $active_filters = [];
  if (!empty($f_id)) {
    $fg = \DB::table('t_final_gaji')->where('id', $f_id)->first();
    if ($fg) $active_filters['Final Gaji'] = $fg->nomor . ($fg->desc ? ' (' . $fg->desc . ')' : '');
  }
  if (!empty($req->m_dir_id)) {
    $dirName = \DB::table('m_dir')->where('id', $req->m_dir_id)->value('nama');
    if ($dirName) $active_filters['Direktorat'] = $dirName;
  }
  if (!empty($req->m_divisi_id)) {
    $divName = \DB::table('m_divisi')->where('id', $req->m_divisi_id)->value('nama');
    if ($divName) $active_filters['Divisi'] = $divName;
  }
  if (!empty($req->m_dept_id)) {
    $deptName = \DB::table('m_dept')->where('id', $req->m_dept_id)->value('nama');
    if ($deptName) $active_filters['Departemen'] = $deptName;
  }
  if (!empty($req->m_posisi_id)) {
    $posName = \DB::table('m_posisi')->where('id', $req->m_posisi_id)->value('desc_kerja');
    if ($posName) $active_filters['Posisi'] = $posName;
  }
  if (!empty($req->m_kary_id)) {
    $kary = \DB::table('m_kary')->where('id', $req->m_kary_id)->first();
    if ($kary) $active_filters['Karyawan'] = ($kary->nik ? $kary->nik . ' - ' : '') . $kary->nama_lengkap;
  }

  $totalGaji = 0;
  foreach ($raw as $row) {
    $totalGaji += $row->netto;
  }

  $rekeningFrom = \DB::select("select g.value from m_general g where g.group = 'REKENING TRANSFER' limit 1");
  $rekeningVal = $rekeningFrom[0]->value ?? '-';
@endphp

@if(!count($raw))
  <div style="font-family: Arial, sans-serif; padding: 20px; text-align: center; color: #64748b; font-style: italic;">
    Data final gaji tidak ditemukan untuk kriteria filter yang dipilih.
  </div>
@else
<div style="width: 100%; font-family: Arial, Helvetica, sans-serif; margin-bottom: 12px;">
  <div style="text-align: center; margin-bottom: 6px;">
    <span style="font-size: 14pt; font-weight: bold; display: block; color: #1e293b; letter-spacing: 0.5px;">LAPORAN PAYROLL</span>
    <span style="font-size: 10pt; color: #64748b; display: block; margin-top: 2px;">
      Nomor Final Gaji: {{ $raw[0]->nomor_final_gaji ?? '-' }}
      @if(!empty($raw[0]->periode_awal) && !empty($raw[0]->periode_akhir))
        | Periode: {{ date('d-m-Y', strtotime($raw[0]->periode_awal)) }} s/d {{ date('d-m-Y', strtotime($raw[0]->periode_akhir)) }}
      @endif
    </span>
  </div>

  @if(count($active_filters) > 0)
  <table width="100%" style="border-collapse: collapse; margin-top: 4px; margin-bottom: 8px;">
    <tr>
      <td style="text-align: left; vertical-align: bottom; border: none; font-size: 9pt; line-height: 1.5; color: #334155; padding: 0;">
        @foreach($active_filters as $fLabel => $fVal)
          <div><strong style="color: #0f172a;">{{ $fLabel }}</strong> = <span style="color: #334155;">{{ $fVal }}</span></div>
        @endforeach
      </td>
    </tr>
  </table>
  @endif

  <table style="border-collapse: collapse; width: 100%; font-size: 9pt; margin-bottom: 12px; background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;" cellpadding="6">
    <tr>
      <td style="width: 33.3%; vertical-align: top; border-right: 1px solid #e2e8f0;">
        <span style="color: #64748b; font-size: 8pt; display: block;">Rekening Pengirim</span>
        <strong style="color: #0f172a; font-size: 10pt;">{{ $rekeningVal }}</strong>
      </td>
      <td style="width: 33.3%; vertical-align: top; border-right: 1px solid #e2e8f0;">
        <span style="color: #64748b; font-size: 8pt; display: block;">Total Karyawan</span>
        <strong style="color: #0f172a; font-size: 10pt;">{{ count($raw) }} Orang</strong>
      </td>
      <td style="width: 33.4%; vertical-align: top;">
        <span style="color: #64748b; font-size: 8pt; display: block;">Total Nominal Netto</span>
        <strong style="color: #0f172a; font-size: 11pt;">Rp {{ number_format($totalGaji, 0, ',', '.') }}</strong>
      </td>
    </tr>
  </table>
</div>

<table width="100%" style="font-size: 9px; font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;" cellpadding="4">
  <thead>
    <tr style="background-color: #e2e8f0; color: #0f172a; text-align: center;">
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">No</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">NIK</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 6px;">Nama Karyawan</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Direktorat</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Divisi</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Departemen</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Posisi</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Mata Uang</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 6px;">Netto Gaji (Rp)</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Bank</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Cabang</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Catatan</th>
    </tr>
  </thead>
  <tbody>
    @foreach($raw as $i => $d)
    @php
        $backgroundColor = $i % 2 === 1 ? '#f8fafc' : '#ffffff';
        $panggilan = ($d->jk_id === 346) ? 'IBU' : 'BPK';
    @endphp
    <tr style="background-color: {{$backgroundColor}}">
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">{{ $i+1 }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">{{ $d->nik }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 6px;">{{ $d->nama_lengkap }} ({{ $panggilan }})</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 5px;">{{ $d->dir ?? '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 5px;">{{ $d->divisi ?? '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 5px;">{{ $d->dept ?? '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 5px;">{{ $d->posisi ?? '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">IDR</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: right; padding: 4px 6px; font-weight: 600;">{{ number_format($d->netto, 0, ',', '.') }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">MANDIRI</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">MADIUN</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 5px;">{{ $d->desc ?? '-' }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr style="background-color: #f1f5f9; font-weight: bold; color: #0f172a;">
      <td colspan="8" style="border: 0.5px solid #94a3b8; text-align: right; padding: 6px 8px;">TOTAL TRANSFER NETTO:</td>
      <td style="border: 0.5px solid #94a3b8; text-align: right; padding: 6px 8px; font-weight: bold; background-color: #e2e8f0;">Rp {{ number_format($totalGaji, 0, ',', '.') }}</td>
      <td colspan="3" style="border: 0.5px solid #94a3b8;"></td>
    </tr>
  </tfoot>
</table>
@endif