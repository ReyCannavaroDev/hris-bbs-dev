@php
  $req = app()->request;
  $periode_from = $req->periode_from ?? date('Y-m').'-01';
  $periode_to = $req->periode_to ?? date('Y-m-d');
  $divisi_id = $req->m_divisi_id ?? $req->m_div_id;

  $raw = \DB::select("
    select 
      tl.tanggal, tl.jam_mulai, tl.jam_selesai, tl.interval_min,
      tl.jam_mulai_realisasi, tl.jam_selesai_realisasi, tl.realisasi_min, tl.diakui_min,
      tl.tarif_lembur, tl.total_lembur,
      tl.nomor, tl.keterangan, tl.status, tl.no_doc, 
      k.kode nik, k.nama_lengkap, kd.nama dir, kdi.nama div, kde.nama dept, 
      mg_grade.value grading, mg.value tipe_lembur, mg1.value alasan  
    from t_lembur tl
      join m_kary k on k.id = tl.m_kary_id 
      left join m_general mg_grade on mg_grade.id = k.grading_id
      left join m_general mg on mg.id = tl.tipe_lembur_id
      left join m_general mg1 on mg1.id = tl.alasan_id
      left join m_dir kd on kd.id = k.m_dir_id 
      left join m_divisi kdi on kdi.id = k.m_divisi_id 
      left join m_dept kde on kde.id = k.m_dept_id 
      where tl.tanggal BETWEEN ? AND ? 
      and case when kd.id is not null then kd.id = coalesce(?,kd.id) else true end
      and case when kdi.id is not null then kdi.id = coalesce(?,kdi.id) else true end
      and case when kde.id is not null then kde.id = coalesce(?,kde.id) else true end
      and case when k.m_posisi_id is not null then k.m_posisi_id = coalesce(?,k.m_posisi_id) else true end
      and case when tl.tipe_lembur_id is not null then tl.tipe_lembur_id = coalesce(?,tl.tipe_lembur_id) else true end
      and case when k.id is not null then k.id = coalesce(?,k.id) else true end
      and tl.status = 'APPROVED'
    order by tl.tanggal asc, tl.id asc
", [ $periode_from, $periode_to, $req->m_dir_id, $divisi_id, $req->m_dept_id, $req->m_posisi_id, $req->tipe_lembur_id, $req->m_kary_id ]);

  $active_filters = [];
  if (!empty($req->m_dir_id)) {
    $dirName = \DB::table('m_dir')->where('id', $req->m_dir_id)->value('nama');
    if ($dirName) $active_filters['Direktorat'] = $dirName;
  }
  if (!empty($divisi_id)) {
    $divName = \DB::table('m_divisi')->where('id', $divisi_id)->value('nama');
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
  if (!empty($req->tipe_lembur_id)) {
    $tipeLembur = \DB::table('m_general')->where('id', $req->tipe_lembur_id)->value('value');
    if ($tipeLembur) $active_filters['Tipe Lembur'] = $tipeLembur;
  }
  if (!empty($req->m_kary_id)) {
    $kary = \DB::table('m_kary')->where('id', $req->m_kary_id)->first();
    if ($kary) $active_filters['Karyawan'] = ($kary->nik ? $kary->nik . ' - ' : '') . $kary->nama_lengkap;
  }

  $total_diakui_menit = 0;
  $total_nominal_lembur = 0;
  $periode_from_label = date('d-m-Y', strtotime($periode_from));
  $periode_to_label = date('d-m-Y', strtotime($periode_to));
@endphp

<div style="width:100%; font-family: Arial, Helvetica, sans-serif; margin-bottom: 12px;">
  <div style="text-align: center; margin-bottom: 6px;">
    <span style="font-size: 14pt; font-weight: bold; display: block; color: #1e293b; letter-spacing: 0.5px;">LAPORAN PERHITUNGAN & PEMBAYARAN LEMBUR</span>
    <span style="font-size: 8pt; color: #64748b; display: block;">(Sesuai MoM BBS Poin 3.J & 3.K: Perhitungan min(Durasi SPK, Durasi Realisasi))</span>
    <span style="font-size: 10pt; color: #475569; display: block; margin-top: 3px;">Periode: {{ $periode_from_label }} s/d {{ $periode_to_label }}</span>
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

<table width="100%" style="font-size: 8px; font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;" cellpadding="3" cellspacing="0">
  <thead>
    <tr style="background-color: #e2e8f0; color: #0f172a; text-align: center;">
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">No</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Tanggal</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">No SPK</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">NIK</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Nama Karyawan</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Divisi / Dept</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Grading</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Jam SPK</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Durasi SPK</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Jam Realisasi</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Durasi Real</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px; background-color: #c6e0b4;">Durasi Diakui</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Tarif/Jam (Rp)</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px; background-color: #c6e0b4;">Total Lembur (Rp)</th>
      <th style="border: 0.5px solid #94a3b8; padding: 5px 3px;">Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse($raw as $i => $d)
    @php
        $backgroundColor = $i % 2 === 1 ? '#f8fafc' : '#ffffff';
        $spk_menit = $d->interval_min ?? 0;
        $real_menit = $d->realisasi_min ?? 0;
        $diakui_menit = $d->diakui_min ?? $spk_menit;
        $tarif = $d->tarif_lembur ?? 0;
        $total_uang = $d->total_lembur ?? round(($diakui_menit / 60) * $tarif, 2);

        $total_diakui_menit += $diakui_menit;
        $total_nominal_lembur += $total_uang;
    @endphp
    <tr style="background-color: {{$backgroundColor}}">
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $i+1 }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->tanggal }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->nomor }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->nik }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 5px;">{{ $d->nama_lengkap }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 5px;">{{ $d->div }} / {{ $d->dept }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->grading ?? '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ substr($d->jam_mulai,0,5) }} - {{ substr($d->jam_selesai,0,5) }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $spk_menit ? round($spk_menit/60, 2).' jam' : '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ ($d->jam_mulai_realisasi && $d->jam_selesai_realisasi) ? substr($d->jam_mulai_realisasi,0,5).' - '.substr($d->jam_selesai_realisasi,0,5) : '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $real_menit ? round($real_menit/60, 2).' jam' : '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; font-weight: bold; background-color: #e2efda; padding: 3px;">{{ $diakui_menit ? round($diakui_menit/60, 2).' jam' : '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: right; padding: 3px 4px;">{{ $tarif ? number_format($tarif, 0, ',', '.') : '-' }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: right; font-weight: bold; background-color: #e2efda; padding: 3px 4px;">{{ number_format($total_uang, 0, ',', '.') }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; color: #16a34a; font-weight: 600; padding: 3px;">{{ $d->status }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="15" style="border: 0.5px solid #cbd5e1; text-align: center; padding: 12px; color: #64748b; font-style: italic;">
        Tidak ada data lembur untuk kriteria yang dipilih.
      </td>
    </tr>
    @endforelse
  </tbody>
  @if(count($raw) > 0)
  <tfoot>
    <tr style="background-color: #f1f5f9; font-weight: bold; color: #0f172a;">
      <td colspan="11" style="border: 0.5px solid #94a3b8; text-align: right; padding: 5px 6px;">GRAND TOTAL PEMBAYARAN:</td>
      <td style="border: 0.5px solid #94a3b8; text-align: center; background-color: #c6e0b4; padding: 5px 3px;">{{ round($total_diakui_menit/60, 2) }} jam</td>
      <td style="border: 0.5px solid #94a3b8;"></td>
      <td style="border: 0.5px solid #94a3b8; text-align: right; background-color: #c6e0b4; padding: 5px 6px;">Rp {{ number_format($total_nominal_lembur, 0, ',', '.') }}</td>
      <td style="border: 0.5px solid #94a3b8;"></td>
    </tr>
  </tfoot>
  @endif
</table>