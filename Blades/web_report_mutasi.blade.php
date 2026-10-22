@php
  $req = app()->request;
  $periode_from = $req->periode_from ?? date('Y-m').'-01';
  $periode_to = $req->periode_to ?? date('Y-m-d');
  $raw = \DB::select("
    select 
      tm.tgl, tm.nomor, tm.keterangan, tm.status, k.nik, k.nama_lengkap, kd.nama dir, kdi.nama divisi_lama, kdi1.nama divisi_baru, kde.nama dept_lama,
      kde1.nama dept_baru, mp.desc_kerja posisi_lama, mp1.desc_kerja posisi_baru, mg.kode gaji_lama, mg1.kode gaji_baru  
    from t_mutasi tm
      join m_kary k on k.id = tm.m_kary_id 
      left join m_dir kd on kd.id = tm.m_dir_id 
      left join m_divisi kdi on kdi.id = tm.m_divisi_lama_id
      left join m_divisi kdi1 on kdi1.id = tm.m_devisi_baru_id 
      left join m_dept kde on kde.id = tm.m_dept_lama_id
      left join m_dept kde1 on kde1.id = tm.m_dept_baru_id
      left join m_posisi mp on mp.id = tm.m_posisi_lama_id
      left join m_posisi mp1 on mp1.id = tm.m_posisi_baru_id
      left join m_standart_gaji mg on mg.id = tm.m_standart_posisi_id
      left join m_standart_gaji mg1 on mg1.id = tm.m_standart_baru_id  
    where tm.status = 'POSTED' and tm.tgl BETWEEN ? AND ? 
      and case when kd.id is not null then kd.id = coalesce(?,kd.id) else true end
      and case when kdi.id is not null then kdi.id = coalesce(?,kdi.id) else true end
      and case when kde.id is not null then kde.id = coalesce(?,kde.id) else true end
      and case when k.m_posisi_id is not null then k.m_posisi_id = coalesce(?, k.m_posisi_id) else true end
      and case when k.id is not null then k.id = coalesce(?, k.id) else true end
    order by tm.id
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
@endphp

<div style="width:100%; font-family: Arial, Helvetica, sans-serif; margin-bottom: 12px;">
  <div style="text-align: center; margin-bottom: 6px;">
    <span style="font-size: 14pt; font-weight: bold; display: block; color: #1e293b; letter-spacing: 0.5px;">LAPORAN MUTASI KARYAWAN</span>
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

<table width="100%" style="font-size: 9px; font-family: Arial, Helvetica, sans-serif; border-collapse: collapse;" cellpadding="3">
  <thead>
    <tr style="background-color: #e2e8f0; color: #0f172a; text-align: center;">
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">No</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">Tanggal</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">No Mutasi</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">NIK</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Nama Karyawan</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Direktorat</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Divisi Lama</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Divisi Baru</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Departemen Lama</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Departemen Baru</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Posisi Lama</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Posisi Baru</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">Std Gaji Lama</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">Std Gaji Baru</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 4px;">Keterangan</th>
      <th style="border: 0.5px solid #94a3b8; font-weight: bold; padding: 6px 3px;">Status</th>
    </tr>
  </thead>
  <tbody>
    @forelse($raw as $i => $d)
    @php
        $backgroundColor = $i % 2 === 1 ? '#f8fafc' : '#ffffff'; 
    @endphp
    <tr style="background-color: {{$backgroundColor}}">
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $i+1 }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->tgl }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->nomor }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->nik }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 5px;">{{ $d->nama_lengkap }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->dir }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->divisi_lama }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->divisi_baru }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->dept_lama }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->dept_baru }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->posisi_lama }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->posisi_baru }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->gaji_lama }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; padding: 3px;">{{ $d->gaji_baru }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: left; padding: 3px 4px;">{{ $d->keterangan }}</td>
      <td style="border: 0.5px solid #cbd5e1; text-align: center; color: #16a34a; font-weight: 600; padding: 3px;">{{ $d->status }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="16" style="border: 0.5px solid #cbd5e1; text-align: center; padding: 12px; color: #64748b; font-style: italic;">
        Tidak ada data mutasi untuk kriteria yang dipilih.
      </td>
    </tr>
    @endforelse
  </tbody>
</table>