@php
  $req = app()->request;
  $periode_from = $req->periode_from ?? date('Y-m').'-01';
  $periode_to = $req->periode_to ?? date('Y-m-d');
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
", [ $periode_from, $periode_to, $req->m_dir_id, $req->m_divisi_id, $req->m_dept_id, $req->m_posisi_id, $req->tipe_lembur_id, $req->m_kary_id ]);

  $total_diakui_menit = 0;
  $total_nominal_lembur = 0;
@endphp
<span style="width:100%;text-align:center;font-weight:bold;font-size:12pt;display:block;"> Laporan Perhitungan & Pembayaran Lembur Mingguan </span>
<span style="width:100%;text-align:center;font-size:8pt;color:#666;display:block;">(Sesuai MoM BBS Poin 3.J & 3.K: Perhitungan min(Durasi SPK, Durasi Realisasi))</span><br/>
@php
  $periode_from_label = date('d-m-Y', strtotime($periode_from));
  $periode_to_label = date('d-m-Y', strtotime($periode_to));
@endphp
<span style="width:100%;text-align:center; font-size:10pt;display:block;"> Periode: {{ $periode_from_label }} s/d {{ $periode_to_label }}</span><br/>

<table width="100%" style="font-size:8px;border-collapse:collapse;" cellpadding="3" cellspacing="0">
  <thead style="font-weight:bold;">
    <tr style="background-color: #d9e1f2; text-align:center;">
      <td style="border:0.5px solid black;">No</td>
      <td style="border:0.5px solid black;">Tanggal</td>
      <td style="border:0.5px solid black;">No SPK</td>
      <td style="border:0.5px solid black;">NIK</td>
      <td style="border:0.5px solid black;">Nama Karyawan</td>
      <td style="border:0.5px solid black;">Divisi / Dept</td>
      <td style="border:0.5px solid black;">Grading</td>
      <td style="border:0.5px solid black;">Jam SPK</td>
      <td style="border:0.5px solid black;">Durasi SPK</td>
      <td style="border:0.5px solid black;">Jam Realisasi</td>
      <td style="border:0.5px solid black;">Durasi Real</td>
      <td style="border:0.5px solid black;background-color:#c6e0b4;">Durasi Diakui</td>
      <td style="border:0.5px solid black;">Tarif/Jam (Rp)</td>
      <td style="border:0.5px solid black;background-color:#c6e0b4;">Total Lembur (Rp)</td>
      <td style="border:0.5px solid black;">Status</td>
    </tr>
  </thead>
  <tbody>
    @foreach($raw as $i => $d)
    @php
        $backgroundColor = $i % 2 === 1 ? '#f9f9f9' : '#ffffff';
        $spk_menit = $d->interval_min ?? 0;
        $real_menit = $d->realisasi_min ?? 0;
        $diakui_menit = $d->diakui_min ?? $spk_menit;
        $tarif = $d->tarif_lembur ?? 0;
        $total_uang = $d->total_lembur ?? round(($diakui_menit / 60) * $tarif, 2);

        $total_diakui_menit += $diakui_menit;
        $total_nominal_lembur += $total_uang;
    @endphp
    <tr style="background-color: {{$backgroundColor}}">
      <td style="border:0.5px solid black;text-align:center;">{{ $i+1 }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ $d->tanggal }}</td>
      <td style="border:0.5px solid black;text-align:left;">{{ $d->nomor }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ $d->nik }}</td>
      <td style="border:0.5px solid black;text-align:left;">{{ $d->nama_lengkap }}</td>
      <td style="border:0.5px solid black;text-align:left;">{{ $d->div }} / {{ $d->dept }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ $d->grading ?? '-' }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ substr($d->jam_mulai,0,5) }} - {{ substr($d->jam_selesai,0,5) }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ $spk_menit ? round($spk_menit/60, 2).' jam' : '-' }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ ($d->jam_mulai_realisasi && $d->jam_selesai_realisasi) ? substr($d->jam_mulai_realisasi,0,5).' - '.substr($d->jam_selesai_realisasi,0,5) : '-' }}</td>
      <td style="border:0.5px solid black;text-align:center;">{{ $real_menit ? round($real_menit/60, 2).' jam' : '-' }}</td>
      <td style="border:0.5px solid black;text-align:center;font-weight:bold;background-color:#e2efda;">{{ $diakui_menit ? round($diakui_menit/60, 2).' jam' : '-' }}</td>
      <td style="border:0.5px solid black;text-align:right;">{{ $tarif ? number_format($tarif, 0, ',', '.') : '-' }}</td>
      <td style="border:0.5px solid black;text-align:right;font-weight:bold;background-color:#e2efda;">{{ number_format($total_uang, 0, ',', '.') }}</td>
      <td style="border:0.5px solid black;text-align:center;color:green;font-weight:semibold;">{{ $d->status }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr style="background-color: #d9e1f2; font-weight: bold;">
      <td colspan="11" style="border:0.5px solid black;text-align:right;padding:4px;">GRAND TOTAL PEMBAYARAN:</td>
      <td style="border:0.5px solid black;text-align:center;background-color:#c6e0b4;">{{ round($total_diakui_menit/60, 2) }} jam</td>
      <td style="border:0.5px solid black;"></td>
      <td style="border:0.5px solid black;text-align:right;background-color:#c6e0b4;">Rp {{ number_format($total_nominal_lembur, 0, ',', '.') }}</td>
      <td style="border:0.5px solid black;"></td>
    </tr>
  </tfoot>
</table>