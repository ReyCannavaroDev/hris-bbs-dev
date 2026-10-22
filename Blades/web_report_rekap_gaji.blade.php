@php
  $req = app()->request;
  $periode_from = $req->periode_from ?? '2023-01-01';
  $periode_to = $req->periode_to ?? '2023-12-30';
  $raw = \DB::select("
    select 
      k.nik, k.nama_lengkap, kd.nama dir, kdi.nama divisi, kde.nama dept, (fd.netto+fd.total_tax) total_gaji, fd.total_tax, fd.netto, fd.periode_in_date , fd.periode 
    from t_final_gaji_det fd
      join t_final_gaji f on fd.t_final_gaji_id = f.id
      join m_kary k on k.id = fd.m_kary_id 
      left join m_dir kd on kd.id = fd.m_kary_dir_id 
      left join m_divisi kdi on kdi.id = fd.m_kary_divisi_id 
      left join m_dept kde on kde.id = fd.m_kary_dept_id  
      where f.status = 'POSTED' 
      and f.periode_awal >= ? and f.periode_akhir <= ? 
      and case when kd.id is not null then kd.id = coalesce(?,kd.id) else true end
      and case when kdi.id is not null then kdi.id = coalesce(?,kdi.id) else true end
      and case when kde.id is not null then kde.id = coalesce(?,kde.id) else true end
      and case when k.m_posisi_id is not null then k.m_posisi_id = coalesce(?,k.m_posisi_id) else true end
      and case when k.id is not null then k.id = coalesce(?,k.id) else true end
  ", [ $periode_from, $periode_to, $req->m_dir_id, $req->m_divisi_id, $req->m_dept_id, $req->m_posisi_id, $req->m_kary_id ]);

  $active_filters = [];
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

  $periode_from_label = date('d-m-Y', strtotime($periode_from));
  $periode_to_label = date('d-m-Y', strtotime($periode_to));

  $grand_total_gaji = 0;
  $grand_total_tax = 0;
  $grand_total_netto = 0;
@endphp

<div style="width:100%; font-family: Arial, Helvetica, sans-serif; margin-bottom: 12px;">
  <div style="text-align: center; margin-bottom: 6px;">
    <span style="font-size: 14pt; font-weight: bold; display: block; color: #1e293b; letter-spacing: 0.5px;">LAPORAN REKAPITULASI GAJI</span>
    <span style="font-size: 10pt; color: #64748b; display: block; margin-top: 2px;">Periode: {{ $periode_from_label }} s/d {{ $periode_to_label }}</span>
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
</div>

<table width="100%" style="font-size: 10px; font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;" cellpadding="4">
  <thead>
    <tr style="background-color: #e2e8f0; color: #0f172a;">
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">No</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Tanggal</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">NIK</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Nama Karyawan</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Direktorat</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Divisi</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Departemen</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Gaji</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">PPH</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; text-align: center; padding: 6px 4px;">Total Gaji</th>
    </tr>
  </thead>
  <tbody>
    @forelse($raw as $i => $d)
    @php
        $backgroundColor = $i % 2 === 1 ? '#f8fafc' : '#ffffff';
        $gaji = $d->total_gaji ?? 0;
        $tax = $d->total_tax ?? 0;
        $netto = $d->netto ?? 0;

        $grand_total_gaji += $gaji;
        $grand_total_tax += $tax;
        $grand_total_netto += $netto;

        $formatted_gaji = ($gaji != 0) ? number_format($gaji, 2, ',', '.') : '0,00';
        $formatted_tax = ($tax != 0) ? number_format($tax, 2, ',', '.') : '0,00';
        $formatted_netto = ($netto != 0) ? number_format($netto, 2, ',', '.') : '0,00';
    @endphp
    <tr style="background-color: {{$backgroundColor}}">
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">{{ $i+1 }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">{{ $d->periode }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 4px;">{{ $d->nik }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 6px;">{{ $d->nama_lengkap }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 6px;">{{ $d->dir }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 6px;">{{ $d->divisi }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 4px 6px;">{{ $d->dept }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: right; padding: 4px 6px;">{{ $formatted_gaji }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: right; padding: 4px 6px;">{{ $formatted_tax }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: right; padding: 4px 6px; font-weight: bold;">{{ $formatted_netto }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="10" style="border: 0.5px solid #cbd5e1; text-align: center; padding: 12px; color: #64748b; font-style: italic;">
        Tidak ada data rekap gaji untuk kriteria yang dipilih.
      </td>
    </tr>
    @endforelse
  </tbody>
  @if(count($raw) > 0)
  <tfoot>
    <tr style="background-color: #f1f5f9; font-weight: bold; color: #0f172a;">
      <td colspan="7" style="border: 0.5px solid #94a3b8; text-align: right; padding: 6px 8px;">TOTAL:</td>
      <td style="border: 0.5px solid #94a3b8; text-align: right; padding: 6px 8px;">{{ number_format($grand_total_gaji, 2, ',', '.') }}</td>
      <td style="border: 0.5px solid #94a3b8; text-align: right; padding: 6px 8px;">{{ number_format($grand_total_tax, 2, ',', '.') }}</td>
      <td style="border: 0.5px solid #94a3b8; text-align: right; padding: 6px 8px; font-weight: bold; background-color: #e2e8f0;">{{ number_format($grand_total_netto, 2, ',', '.') }}</td>
    </tr>
  </tfoot>
  @endif
</table>